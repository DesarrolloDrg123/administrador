<?php
session_start();
require '../config/db.php'; 
require_once '../vendor/autoload.php'; 

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Configuración de respuesta JSON
header('Content-Type: application/json');
$response = ['success' => false, 'message' => 'Error desconocido.'];

if (empty($_SESSION['usuario_id'])) {
    echo json_encode(['success' => false, 'message' => 'Error: Sesión expirada. Por favor, inicie sesión de nuevo.']);
    exit();
}

if ($_SERVER["REQUEST_METHOD"] != "POST") {
    echo json_encode(['success' => false, 'message' => 'Método no permitido.']);
    exit();
}

// Datos de sesión y variables iniciales
$usuario_solicitante_id = $_SESSION['usuario_id'];
$nombreSolicitante = $_SESSION['nombre'];
$folio_formateado = '';

/*------------------ Calcular Periodo Actual (Mes-Año) --------------------*/
$meses = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
$mes_num = date('n') - 1; 
$anio = date('y');
$periodoAct = $meses[$mes_num] . '-' . $anio; // Ejemplo: Ene-25

$conn->begin_transaction();

try {
    // =========================================================================
    // 1. GENERACIÓN DE FOLIO (Tabla control_folios_tcl)
    // =========================================================================
    $stmt_folio = $conn->prepare("SELECT folio FROM control_folios_tcl WHERE id = 1 FOR UPDATE");
    $stmt_folio->execute();
    $res_folio = $stmt_folio->get_result();
    
    if ($res_folio->num_rows === 0) throw new Exception("Error: No se encontró configuración de folios.");
    
    $row_folio = $res_folio->fetch_assoc();
    $nuevo_folio = $row_folio['folio'] + 1;
    $folio_formateado = sprintf("%09d", $nuevo_folio);

    // Actualizamos el folio inmediatamente
    $stmt_upd_folio = $conn->prepare("UPDATE control_folios_tcl SET folio = ? WHERE id = 1");
    $stmt_upd_folio->bind_param("i", $nuevo_folio);
    $stmt_upd_folio->execute();

    // =========================================================================
    // 2. RECOLECCIÓN DE DATOS Y VALIDACIÓN
    // =========================================================================
    $requiredFields = ['sucursales', 'beneficiario', 'date', 'endDate', 'departamento', 'categoria', 'descripcion', 'autorizacion_hidden', 'importe'];
    foreach ($requiredFields as $field) {
        if (empty($_POST[$field])) throw new Exception('Falta el campo obligatorio: ' . $field);
    }

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

    // Limpieza del importe (quitar comas)
    $importe_raw = $_POST['importe'];
    $importe_val = floatval(str_replace(',', '', $importe_raw));
    $importe_letra = $_POST['importe-letra'] ?? '';

    if ($importe_val <= 0) {
        throw new Exception("El importe debe ser mayor a 0.");
    }

    // =========================================================================
    // 3. VERIFICACIÓN DE PRESUPUESTO (Solo Moneda Nacional)
    // =========================================================================
    
    // Consultamos el presupuesto disponible para esa Sucursal + Departamento + Periodo
    $stmt_pres = $conn->prepare("SELECT restante, registrado FROM presupuestos WHERE periodo = ? AND sucursal_id = ? AND departamento_id = ? FOR UPDATE");
    $stmt_pres->bind_param("sii", $periodoAct, $sucursal_id, $departamento_id);
    $stmt_pres->execute();
    $res_pres = $stmt_pres->get_result();

    if ($res_pres->num_rows === 0) {
        throw new Exception("No existe un presupuesto asignado para esta Sucursal y Departamento en el periodo $periodoAct.");
    }

    $row_pres = $res_pres->fetch_assoc();
    $presupuesto_restante = floatval($row_pres['restante']);
    $presupuesto_registrado = floatval($row_pres['registrado']);

    // Validamos si alcanza
    if ($importe_val > $presupuesto_restante) {
        throw new Exception("Presupuesto insuficiente. Disponible: $" . number_format($presupuesto_restante, 2) . " | Solicitado: $" . number_format($importe_val, 2));
    }

    // Calculamos los nuevos saldos para actualizar después de insertar
    $nuevo_restante = $presupuesto_restante - $importe_val;
    $nuevo_registrado = $presupuesto_registrado + $importe_val;

    // =========================================================================
    // 4. SUBIDA DE ARCHIVO
    // =========================================================================
    $documento_adjunto = null;
    if (isset($_FILES['documento_adjunto']) && $_FILES['documento_adjunto']['error'] == UPLOAD_ERR_OK) {
        $upload_dir = '../uploads/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
        
        $extension = pathinfo($_FILES['documento_adjunto']['name'], PATHINFO_EXTENSION);
        $nombre_archivo = "CLARA_" . $folio_formateado . "_" . time() . "." . $extension;
        $documento_adjunto = $upload_dir . $nombre_archivo;
        
        if (!move_uploaded_file($_FILES['documento_adjunto']['tmp_name'], $documento_adjunto)) {
            throw new Exception('Error al mover el archivo al servidor.');
        }
    }

    // =========================================================================
    // 5. INSERCIÓN DE LA TRANSFERENCIA
    // =========================================================================
    $fields = ["folio", "sucursal_id", "beneficiario_id", "fecha_solicitud", "no_cuenta", "fecha_vencimiento", "departamento_id", "categoria_id", "descripcion", "observaciones", "autorizacion_id", "usuario_solicitante_id", "estado", "importe", "importe_letra"];
    $values = [$folio_formateado, $sucursal_id, $beneficiario_id, $fecha_solicitud, $no_cuenta, $fecha_vencimiento, $departamento_id, $categoria_id, $descripcion, $observaciones, $autorizacion_id, $usuario_solicitante_id, 'Pendiente', $importe_raw, $importe_letra];

    if ($documento_adjunto) {
        $fields[] = "documento_adjunto";
        $values[] = $documento_adjunto;
    }

    $sql = "INSERT INTO transferencias_clara_tcl (" . implode(", ", $fields) . ") VALUES (" . implode(", ", array_fill(0, count($values), "?")) . ")";
    $stmt = $conn->prepare($sql);
    
    // Binding dinámico de parámetros
    $types = str_repeat('s', count($values));
    $stmt->bind_param($types, ...$values);
    
    if (!$stmt->execute()) {
        throw new Exception("Error al guardar la transferencia: " . $stmt->error);
    }
    $last_id = $conn->insert_id;

    // =========================================================================
    // 6. ACTUALIZACIÓN DEL PRESUPUESTO
    // =========================================================================
    $stmt_upd_pres = $conn->prepare("UPDATE presupuestos SET restante = ?, registrado = ? WHERE periodo = ? AND sucursal_id = ? AND departamento_id = ?");
    $stmt_upd_pres->bind_param("ddsii", $nuevo_restante, $nuevo_registrado, $periodoAct, $sucursal_id, $departamento_id);
    
    if (!$stmt_upd_pres->execute()) {
        throw new Exception("La transferencia se creó, pero falló la actualización del presupuesto.");
    }

    // =========================================================================
    // 7. ENVÍO DE CORREO Y CONFIRMACIÓN
    // =========================================================================
    
    // Si llegamos aquí, todo en BD está correcto.
    $conn->commit();

    // Enviamos el correo (fuera del riesgo de rollback)
    MandarCorreo($nombreSolicitante, $folio_formateado, $no_cuenta, $importe_raw, $importe_letra, $descripcion, $observaciones, $last_id, $conn, $fecha_solicitud, $fecha_vencimiento);

    echo json_encode(['success' => true, 'message' => 'Transferencia Clara registrada exitosamente. Folio: ' . $folio_formateado]);

} catch (Exception $e) {
    // Si algo falló, deshacemos todos los cambios en BD
    $conn->rollback();
    
    // Borramos el archivo si se llegó a subir
    if (isset($documento_adjunto) && file_exists($documento_adjunto)) {
        unlink($documento_adjunto);
    }

    echo json_encode(['success' => false, 'message' => 'Ocurrió un error: ' . $e->getMessage()]);
}
exit;

// --------------------------------------------------------------------------
// FUNCIÓN DE CORREO (Ajustada para recibir solo los argumentos necesarios)
// --------------------------------------------------------------------------
function MandarCorreo($nombreSolicitante, $folio_formateado, $no_cuenta, $importe, $importe_letra, $descripcion, $observaciones, $last_id, $conn, $fecha_solicitud, $fecha_vencimiento){
    
    // Re-consultamos los nombres (Joins) para armar el correo bonito
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
        
    if ($stmt === false) { error_log('Error SQL Mail: ' . $conn->error); return; }
    
    $stmt->bind_param('i', $last_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    
    $importe_fmt = number_format(floatval(str_replace(',', '', $importe)), 2, '.', ',');

    $mail = new PHPMailer(true);
    
    try {
        $mail->isSMTP();
        $mail->Host = 'mail.intranetdrg.com.mx';
        $mail->SMTPAuth = true;
        $mail->Username = 'administrador@intranetdrg.com.mx';
        $mail->Password = 'WbrE5%7p';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port = 465;

        $mail->setFrom('administrador@intranetdrg.com.mx', 'Transferencias Clara');
        $mail->addAddress($row['email_autoriza']);

        $fmt = new IntlDateFormatter('es_ES', IntlDateFormatter::LONG, IntlDateFormatter::NONE);
        $fechaSolicitudFmt = $fmt->format(new DateTime($fecha_solicitud));
        $fechaVencimientoFmt = $fmt->format(new DateTime($fecha_vencimiento));

        $mail->isHTML(true);
        $mail->Subject = 'Solicitud Clara TCL - Folio: ' . $folio_formateado;
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
                
                <h2>Beneficiario: <strong>{$row['beneficiario']}</strong></h2>
                <h2>Solicitante: <strong>{$nombreSolicitante}</strong></h2>
                
                
                <div class='info-row'><span class='label'>Folio: </span><span>{$folio_formateado}</span></div>
                <div class='info-row'><span class='label'>Sucursal: </span><span>{$row['sucursal']}</span></div>
                <div class='info-row'><span class='label'>Fecha de Solicitud: </span><span>$fechaSolicitudFmt</span></div>
                <div class='info-row'><span class='label'>Fecha de Vencimiento: </span><span>$fechaVencimientoFmt</span></div>
                <div class='info-row'><span class='label'>No. de Tarjeta: </span><span>$no_cuenta</span></div>
                
                <div class='info-row'><span class='label'>Importe Pesos: </span><span>\$ $importe_fmt</span></div>
                <div class='info-row'><span class='label'>Importe con Letra Pesos: </span><span>{$importe_letra}</span></div>
                
                <div class='info-row'><span class='label'>Departamento: </span><span>{$row['departamento']}</span></div>
                <div class='info-row'><span class='label'>Categoría: </span><span>{$row['categoria']}</span></div>
                <div class='info-row'><span class='label'>Descripción: </span><span>$descripcion</span></div>
                <div class='info-row'><span class='label'>Observaciones: </span><span>$observaciones</span></div>
                
                <div class='info-row'><span class='label'>Autorización Pendiente: </span><span>{$row['usuario_autoriza']}</span></div>
                <h1>Autorizar Transferencia en el Portal.</h1>
            </div>
        </body>
        </html>
        ";
        $mail->send();
    } catch (Exception $e) {
        error_log("Error enviando correo Clara: " . $mail->ErrorInfo);
    }
}
?>