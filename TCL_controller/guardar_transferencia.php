<?php
session_start();
require '../config/db.php'; // Tu archivo de conexión
require_once '../vendor/autoload.php'; // PHPMailer

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Respuesta por defecto si algo falla antes de tiempo
$response = ['success' => false, 'message' => 'Error de servidor desconocido.'];
header('Content-Type: application/json');

if (empty($_SESSION['usuario_id'])) {
    $response['message'] = 'Error: No se pudo guardar la transferencia. Por favor, inicie sesión de nuevo.';
    echo json_encode($response);
    exit();
}

if ($_SERVER["REQUEST_METHOD"] != "POST") {
    $response['message'] = 'Método no permitido.';
    echo json_encode($response);
    exit();
}

$usuario_solicitante_id = $_SESSION['usuario_id'];
$nombreSolicitante = $_SESSION['nombre'];
$folio_formateado = '';
$last_id = null;

$conn->begin_transaction();

try {
    // 1. GENERACIÓN Y ACTUALIZACIÓN DEL FOLIO DENTRO DE LA TRANSACCIÓN
    // Asumimos que la tabla se llama 'control_folios_tcl'
    $stmt_folio_select = $conn->prepare("SELECT folio FROM control_folios_tcl WHERE id = 1 FOR UPDATE");
    $stmt_folio_select->execute();
    $result_folio = $stmt_folio_select->get_result();
    
    if ($result_folio->num_rows === 0) {
        throw new Exception("No se encontró el registro de control de folios.");
    }
    
    $row_folio = $result_folio->fetch_assoc();
    $ultimo_folio = $row_folio['folio'];
    $nuevo_folio = $ultimo_folio + 1;
    $folio_formateado = sprintf("%09d", $nuevo_folio);

    $stmt_folio_update = $conn->prepare("UPDATE control_folios_tcl SET folio = ? WHERE id = 1");
    $stmt_folio_update->bind_param("i", $nuevo_folio);
    $stmt_folio_update->execute();

    // 2. RECOLECCIÓN Y VALIDACIÓN BÁSICA DE DATOS
    $requiredFields = ['sucursales', 'beneficiario', 'date', 'endDate', 'departamento', 'categoria', 'descripcion', 'autorizacion_hidden'];
    foreach ($requiredFields as $field) {
        if (empty($_POST[$field])) {
            throw new Exception('Falta el campo obligatorio: ' . $field);
        }
    }

    // Mapeo de POST a variables
    $sucursal_id = $_POST['sucursales'];
    $beneficiario_id = $_POST['beneficiario'];
    $fecha_solicitud = $_POST['date'];
    $no_cuenta = $_POST['noCuenta'] ?? '';
    $fecha_vencimiento = $_POST['endDate'];
    $departamento_id = $_POST['departamento'];
    $categoria_id = $_POST['categoria'];
    $descripcion = $_POST['descripcion'];
    $observaciones = $_POST['observaciones'] ?? '';
    $autorizacion_id = $_POST['autorizacion_hidden'];
    
    // Importes (Asumimos que solo viene uno, pesos o dólares, para la simplificación)
    $importe = $_POST['importe'] ?? null;
    $importe_letra = $_POST['importe-letra'] ?? null;
    
    // El campo importedls y tipo_cambio se maneja si existe
    $importedls = $_POST['importedls'] ?? null;
    $importedls_letra = $_POST['importedls_letra'] ?? null;
    // Debes obtener el tipo de cambio de tu base de datos si usas dólares,
    // o hardcodearlo como 1.0 si es solo pesos.
    $tipo_cambio = "1"; // Valor por defecto para pesos

    // 3. MANEJO DEL ARCHIVO ADJUNTO
    $documento_adjunto = null;
    if (isset($_FILES['documento_adjunto']) && $_FILES['documento_adjunto']['error'] == UPLOAD_ERR_OK) {
        $upload_dir = '../uploads/';
        if (!is_dir($upload_dir) && !mkdir($upload_dir, 0777, true)) {
            throw new Exception('No se pudo crear el directorio de subidas.');
        }
        $documento_adjunto_name = $folio_formateado . '_' . basename($_FILES['documento_adjunto']['name']);
        $documento_adjunto = $upload_dir . $documento_adjunto_name;
        if (!move_uploaded_file($_FILES['documento_adjunto']['tmp_name'], $documento_adjunto)) {
            throw new Exception('Error al subir el archivo.');
        }
    }
    
    // 4. INSERCIÓN EN LA BASE DE DATOS
    
    $fields = ["folio", "sucursal_id", "beneficiario_id", "fecha_solicitud", "no_cuenta", "fecha_vencimiento", "departamento_id", "categoria_id", "descripcion", "observaciones", "autorizacion_id", "usuario_solicitante_id", "estado"];
    $values = [$folio_formateado, $sucursal_id, $beneficiario_id, $fecha_solicitud, $no_cuenta, $fecha_vencimiento, $departamento_id, $categoria_id, $descripcion, $observaciones, $autorizacion_id, $usuario_solicitante_id, 'Pendiente'];
    
    // Agregar campos de importe según lo que se haya enviado
    if (!empty($importe)) {
        $fields[] = "importe"; $values[] = $importe;
        $fields[] = "importe_letra"; $values[] = $importe_letra;
    }
    if (!empty($importedls)) {
        // En el caso de dólares, se requeriría el tipo de cambio real
        $fields[] = "importe_dls"; $values[] = $importedls;
        $fields[] = "importe_letra_dls"; $values[] = $importedls_letra;
        // Asumiendo que $tipo_cambio se obtendría aquí
    }

    if ($documento_adjunto) { 
        $fields[] = "documento_adjunto"; 
        $values[] = $documento_adjunto; 
    }

    $sql = "INSERT INTO transferencias_clara_tcl (" . implode(", ", $fields) . ") VALUES (" . implode(", ", array_fill(0, count($values), "?")) . ")";
    $stmt = $conn->prepare($sql);
    if ($stmt === false) throw new Exception('Error al preparar la consulta de inserción: ' . $conn->error);
    
    // Usamos 's' para todos los tipos para simplificar el binding, ya que la base de datos es flexible.
    $types = str_repeat('s', count($values));
    $stmt->bind_param($types, ...$values);
    $stmt->execute();
    $last_id = $conn->insert_id;

    // 5. LLAMADA AL CORREO (Se ejecuta si la inserción fue exitosa)
    MandarCorreo($nombreSolicitante, $folio_formateado, $no_cuenta, $importe, $importe_letra, $importedls, $importedls_letra, $descripcion, $observaciones, $last_id, $conn, $fecha_solicitud, $fecha_vencimiento);

    // 6. CIERRE DE TRANSACCIÓN Y RESPUESTA
    $conn->commit();
    $response = ['success' => true, 'message' => 'Transferencia registrada correctamente con el folio - ' . $folio_formateado];

} catch (Exception $e) {
    $conn->rollback();
    $response = ['success' => false, 'message' => 'Ocurrió un error: ' . $e->getMessage()];
    // Opcionalmente, borrar el archivo subido si la DB falló después de subirlo
    if ($documento_adjunto && file_exists($documento_adjunto)) {
        // unlink($documento_adjunto);
    }
}

echo json_encode($response);
exit;

function MandarCorreo($nombreSolicitante, $folio_formateado,$no_cuenta,$importe,$importe_letra,$importedls,$importedls_letra,$descripcion,$observaciones,$last_id,$conn, $fecha_solicitud, $fecha_vencimiento){
    
    $stmt = $conn->prepare("
            SELECT 
                s.sucursal, 
                b.nombre AS beneficiario, 
                d.departamento, 
                c.categoria, 
                u.nombre AS usuario_autoriza,
                u.email AS email_autoriza
            FROM transferencias_clara_tcl t
            JOIN sucursales s ON t.sucursal_id = s.id
            JOIN usuarios b ON t.beneficiario_id = b.id
            JOIN departamentos d ON t.departamento_id = d.id
            JOIN categorias c ON t.categoria_id = c.id
            JOIN usuarios u ON t.autorizacion_id = u.id
            WHERE t.id = ?
        ");
        if ($stmt === false) {
            // No lanzar excepción crítica aquí, solo registrar, para no revertir el guardado
            error_log('Error al preparar la consulta de correo: ' . $conn->error);
            return; 
        }
        $stmt->bind_param('i', $last_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        
        $importe_formateado = !empty($importe) ? number_format(floatval(str_replace(',', '', $importe)), 2, '.', ',') : 'N/A';
        $importedls_formateado = !empty($importedls) ? number_format(floatval(str_replace(',', '', $importedls)), 2, '.', ',') : 'N/A';
    
    $mail = new PHPMailer(true);
    
    try {
        $mail->isSMTP();
        $mail->Host = 'mail.intranetdrg.com.mx';
        $mail->SMTPAuth = true;
        $mail->Username = 'administrador@intranetdrg.com.mx';
        $mail->Password = 'WbrE5%7p';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port = 465;

        $mail->setFrom('administrador@intranetdrg.com.mx', 'Transferencias Electronicas');
        $mail->addAddress($row['email_autoriza']);

        $fechaSolicitud = new DateTime($fecha_solicitud);
        $fmt = new IntlDateFormatter('es_ES', IntlDateFormatter::LONG, IntlDateFormatter::NONE);
        $fmt->setPattern('d MMMM yyyy');
        $fechaSolicitudFormateada = $fmt->format($fechaSolicitud);

        $fechaVencimiento = new DateTime($fecha_vencimiento);
        $fechaVencimientoFormateada = $fmt->format($fechaVencimiento);

        $mail->isHTML(true);
        $mail->Subject = 'Nueva Solicitud de Transferencia Clara TCL - Folio: ' . $folio_formateado;
        $mail->Body = "
        <html>
        <head>
        <meta charset='utf-8' />
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                h1 { color: #2c3e50; border-bottom: 2px solid #3498db; padding-bottom: 10px; }
                h2 { color: #2980b9; }
                strong { color: #2c3e50; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .info-row { margin-bottom: 10px; }
                .label { font-weight: bold; color: #34495e; }
                .value { margin-left: 10px; }
                .logo { position: absolute; top: 20px; right: 100px; max-width: 300px; height: 250; width: 250px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <h1>Nueva Solicitud de Transferencia Clara TCL.</h1>
                <h2>Solicitante: <strong>{$nombreSolicitante}</strong></h2>
                
                <div class='info-row'><span class='label'>Folio: </span><span>{$folio_formateado}</span></div>
                <div class='info-row'><span class='label'>Sucursal: </span><span>{$row['sucursal']}</span></div>
                <div class='info-row'><span class='label'>Beneficiario: </span><span>{$row['beneficiario']}</span></div>
                <div class='info-row'><span class='label'>Fecha de Solicitud: </span><span>$fechaSolicitudFormateada</span></div>
                <div class='info-row'><span class='label'>Fecha de Vencimiento: </span><span>$fechaVencimientoFormateada</span></div>
                <div class='info-row'><span class='label'>No. de Tarjeta: </span><span>$no_cuenta</span></div>
                
                <div class='info-row'><span class='label'>Importe Pesos: </span><span>\$ $importe_formateado</span></div>
                <div class='info-row'><span class='label'>Importe con Letra Pesos: </span><span>$importe_letra</span></div>
                
                <div class='info-row'><span class='label'>Departamento: </span><span>{$row['departamento']}</span></div>
                <div class='info-row'><span class='label'>Categoría: </span><span>{$row['categoria']}</span></div>
                <div class='info-row'><span class='label'>Descripción: </span><span>$descripcion</span></div>
                <div class='info-row'><span class='label'>Observaciones: </span><span>$observaciones</span></div>
                
                <div class='info-row'><span class='label'>Autoriza Pendiente: </span><span>{$row['usuario_autoriza']}</span></div>
                <h1>Autorizar Transferencia en el Portal.</h1>
            </div>
        </body>
        </html>
        ";
        $mail->send();
    } catch (Exception $e) {
        error_log("El correo no pudo ser enviado a {$row['email_autoriza']}. Error: {$mail->ErrorInfo}");
        // No lanzamos excepción aquí para no hacer rollback del guardado de la solicitud
    }
}
?>