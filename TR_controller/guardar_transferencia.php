<?php
session_start();
require '../config/db.php'; // Tu archivo de conexión
require_once '../vendor/autoload.php'; // PHPMailer

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if (empty($_SESSION['usuario_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Error: No se pudo guardar la transferncia. Por favor, inicie sesión de nuevo.']);
    exit();
}

$nombreSolicitante = $_SESSION['nombre'];
$usuario_id = $_SESSION['usuario_id'];
$autorizacion_id = $usuario_id;
$folio_formateado = '';

/*------------------Sacar Periodo Actual--------------------*/
$meses = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
$mes_num = date('n') - 1; // n va de 1 a 12, restamos 1 para índice 0-based
$anio = date('y');
$periodoAct = $meses[$mes_num] . '-' . $anio;

/*----------Sacar el precio del dolar en tiempo real----------*/
$token = '4c9318d814aa4ff3b305c4ee0e6cd65a7a6b23043428024980f3bfe854fd0b28';
$url = 'https://www.banxico.org.mx/SieAPIRest/service/v1/series/SF43718/datos/oportuno'; //Si el token expira, sacar uno nuevo en https://www.banxico.org.mx/SieAPIRest/service/v1/token

$ch = curl_init($url);

curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Bmx-Token: $token"
]);

curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$response = curl_exec($ch);
curl_close($ch);

$data = json_decode($response, true);

if (isset($data['bmx']['series'][0]['datos'][0]['dato'])) {
    $dolar = $data['bmx']['series'][0]['datos'][0]['dato'];
    $tipo_cambio = number_format($dolar, 2);
    //echo "El tipo de cambio FIX del día es: " . number_format($dolar, 2) . " MXN por USD";
    
} else {
    //echo "No se pudo obtener el valor del dólar.";
}
/*----------Sacar el precio del dolar en tiempo real----------*/

try {
    $conn->begin_transaction();
    $stmt = $conn->prepare("SELECT ultimo_folio FROM folio WHERE id = 1 FOR UPDATE");
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $ultimo_folio = $row['ultimo_folio'];
    
    $nuevo_folio = $ultimo_folio + 1;
    $folio_formateado = sprintf("%09d", $nuevo_folio);

    $_SESSION['folio_formateado'] = $folio_formateado;

    $conn->commit();
} catch (Exception $e) {
    $conn->rollback();
    die("Error al generar el folio: " . $e->getMessage());
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // 1. Recolección de Datos Comunes y Validación de Campos
    $requiredFields = ['sucursales', 'beneficiario', 'date', 'endDate', 'departamento', 'categoria', 'descripcion', 'autorizacion_hidden'];
    foreach ($requiredFields as $field) {
        if (empty($_POST[$field])) {
            throw new Exception('Falta el campo obligatorio: ' . $field);
        }
    }

    $beneficiario_id = $_POST['beneficiario'];
    $fecha_solicitud = $_POST['date'];
    $no_cuenta = $_POST['noCuenta'] ?? '';
    $fecha_vencimiento = $_POST['endDate'];
    $departamento_id = $_POST['departamento'];
    $categoria_id = $_POST['categoria'];
    $descripcion = $_POST['descripcion'];
    $observaciones = $_POST['observaciones'] ?? '';
    $autorizacion_id = $_POST['autorizacion_hidden'];
    $seleccion_sucursal = $_POST['sucursales'];
    $periodo = $periodoAct;
    $usuario_id = $_SESSION['usuario_id'];
    $nombreSolicitante = $_SESSION['nombre'];

    // Manejo del archivo adjunto
    $documento_adjunto = null;
    if (isset($_FILES['documento_adjunto']) && $_FILES['documento_adjunto']['error'] == UPLOAD_ERR_OK) {
        $upload_dir = '../uploads/';
        if (!is_dir($upload_dir) && !mkdir($upload_dir, 0777, true)) {
            throw new Exception('No se pudo crear el directorio de subidas.');
        }
        $documento_adjunto = $upload_dir . basename($_FILES['documento_adjunto']['name']);
        if (!move_uploaded_file($_FILES['documento_adjunto']['tmp_name'], $documento_adjunto)) {
            throw new Exception('Error al subir el archivo.');
        }
    }

    $conn->begin_transaction();
    try {
        // Actualizar el folio una sola vez
        $stmt_folio = $conn->prepare("UPDATE folio SET ultimo_folio = ? WHERE id = 1");
        $stmt_folio->bind_param("i", $nuevo_folio);
        $stmt_folio->execute();

        // ===============================================
        // CASO 1: MÚLTIPLES SUCURSALES ("varias")
        // ===============================================
        if ($seleccion_sucursal === 'varias') {

            $es_dolares = !empty($_POST['importedls']);
            $tipo_cambio_actual = $es_dolares ? floatval($tipo_cambio) : 1.0;
            $sucursales_ids = $_POST['sucursal_ids'] ?? [];
            $montos = $_POST['montos'] ?? [];
            $importe_letra_general = $es_dolares ? ($_POST['importedls_letra'] ?? '') : ($_POST['importe-letra'] ?? '');

            // A. Filtrar y Validar Presupuestos
            $sucursales_a_procesar = [];
            foreach ($sucursales_ids as $key => $id_suc) {
                if (isset($montos[$key]) && floatval($montos[$key]) > 0) {
                    $sucursales_a_procesar[] = ['id' => $id_suc, 'monto' => floatval($montos[$key])];
                }
            }
            if (empty($sucursales_a_procesar)) throw new Exception("Debe especificar un monto para al menos una sucursal.");

            $errores_presupuesto = [];
            $datos_validados = [];
            foreach ($sucursales_a_procesar as $suc) {
                $importe_en_pesos = $suc['monto'] * $tipo_cambio_actual;
                $stmt_presupuesto = $conn->prepare("SELECT p.restante, p.registrado, s.sucursal FROM presupuestos p JOIN sucursales s ON p.sucursal_id = s.id WHERE p.periodo = ? AND p.sucursal_id = ? AND p.departamento_id = ? FOR UPDATE");
                $stmt_presupuesto->bind_param("sii", $periodo, $suc['id'], $departamento_id);
                $stmt_presupuesto->execute();
                $result_presupuesto = $stmt_presupuesto->get_result();

                if ($result_presupuesto->num_rows === 0) {
                    $errores_presupuesto[] = "• Sucursal ID {$suc['id']}: No tiene presupuesto asignado.";
                } else {
                    $presupuesto = $result_presupuesto->fetch_assoc();
                    if ($importe_en_pesos > $presupuesto['restante']) {
                        $errores_presupuesto[] = "• {$presupuesto['sucursal']}: Presupuesto insuficiente.";
                    } else {
                        $datos_validados[] = ['sucursal_id' => $suc['id'], 'importe_original' => $suc['monto'], 'restante_actual' => $presupuesto['restante'], 'registrado_actual' => $presupuesto['registrado']];
                    }
                }
            }
            if (!empty($errores_presupuesto)) throw new Exception("No se puede proceder:\n\n" . implode("\n", $errores_presupuesto));

            // B. Bucle de Guardado Individual
            $ids_insertados = [];
            foreach ($datos_validados as $data) {
                $fields = ["folio", "sucursal_id", "beneficiario_id", "fecha_solicitud", "no_cuenta", "fecha_vencimiento", "departamento_id", "categoria_id", "descripcion", "observaciones", "autorizacion_id", "usuario_id"];
                $values = [$folio_formateado, $data['sucursal_id'], $beneficiario_id, $fecha_solicitud, $no_cuenta, $fecha_vencimiento, $departamento_id, $categoria_id, $descripcion, $observaciones, $autorizacion_id, $usuario_id];

                if ($es_dolares) {
                    $fields[] = "importedls"; $values[] = $data['importe_original'];
                    $fields[] = "importedls_letra"; $values[] = $importe_letra_general;
                    $fields[] = "tipo_cambio"; $values[] = $tipo_cambio;
                } else {
                    $fields[] = "importe"; $values[] = $data['importe_original'];
                    $fields[] = "importe_letra"; $values[] = $importe_letra_general;
                    $fields[] = "tipo_cambio"; $values[] = "1";
                }
                if ($documento_adjunto) { $fields[] = "documento_adjunto"; $values[] = $documento_adjunto; }
                
                $sql_insert = "INSERT INTO transferencias (" . implode(", ", $fields) . ") VALUES (" . implode(", ", array_fill(0, count($values), "?")) . ")";
                $stmt_insert = $conn->prepare($sql_insert);
                $types = str_repeat('s', count($values));
                $stmt_insert->bind_param($types, ...$values);
                $stmt_insert->execute();
                $last_id = $conn->insert_id;

                $importe_en_pesos_update = $data['importe_original'] * $tipo_cambio_actual;
                $nuevo_restante = $data['restante_actual'] - $importe_en_pesos_update;
                $nuevo_registrado = $data['registrado_actual'] + $importe_en_pesos_update;
                $stmt_update = $conn->prepare("UPDATE presupuestos SET restante = ?, registrado = ? WHERE periodo = ? AND sucursal_id = ? AND departamento_id = ?");
                $stmt_update->bind_param("ddsii", $nuevo_restante, $nuevo_registrado, $periodo, $data['sucursal_id'], $departamento_id);
                $stmt_update->execute();
                
                // Lógica de notificaciones por cada transferencia
                // ...
            }
            // Aquí llamarías a la función de correo con un resumen si lo deseas.
            // Llamada al correo DESPUÉS de todas las inserciones
            if(!empty($ids_insertados)) {
                MandarCorreoCorp($nombreSolicitante, $folio_formateado,$no_cuenta,$importe,$importe_letra,$importedls_letra,$descripcion,$observaciones,$last_id,$conn, $fecha_solicitud, $fecha_vencimiento);
            }

        } else {
            // ===============================================
            // CASO 2: SUCURSAL ÚNICA
            // ===============================================
            $sucursal_id = $seleccion_sucursal;
            $importe = $_POST['importe'] ?? null;
            $importe_letra = $_POST['importe-letra'] ?? null;
            $importedls = $_POST['importedls'] ?? null;
            $importedls_letra = $_POST['importedls_letra'] ?? null;

            if (!empty($importe) && !empty($importedls)) {
                throw new Exception('No puedes ingresar importe en pesos y dólares al mismo tiempo.');
            }

            $importe_solicitado = !empty($importedls) ? floatval(str_replace(',', '', $importedls)) * floatval($tipo_cambio) : floatval(str_replace(',', '', $importe ?? '0'));

            $stmt_presupuesto = $conn->prepare("SELECT restante, registrado FROM presupuestos WHERE periodo = ? AND sucursal_id = ? AND departamento_id = ? LIMIT 1");
            $stmt_presupuesto->bind_param("sii", $periodo, $sucursal_id, $departamento_id);
            $stmt_presupuesto->execute();
            $result_presupuesto = $stmt_presupuesto->get_result();

            if ($result_presupuesto->num_rows === 0) {
                throw new Exception('No se encontró presupuesto para el periodo, sucursal y departamento indicados.');
            }
            $row_presupuesto = $result_presupuesto->fetch_assoc();
            $presupuesto_disponible = floatval($row_presupuesto['restante']);
            $presupuesto_registrado = floatval($row_presupuesto['registrado']);
            
            if ($importe_solicitado > $presupuesto_disponible) {
                throw new Exception('El importe solicitado excede el presupuesto disponible.');
            }

            $fields = ["folio", "sucursal_id", "beneficiario_id", "fecha_solicitud", "no_cuenta", "fecha_vencimiento", "departamento_id", "categoria_id", "descripcion", "observaciones", "autorizacion_id", "usuario_id"];
            $values = [$folio_formateado, $sucursal_id, $beneficiario_id, $fecha_solicitud, $no_cuenta, $fecha_vencimiento, $departamento_id, $categoria_id, $descripcion, $observaciones, $autorizacion_id, $usuario_id];

            if (!empty($importe)) {
                $fields[] = "importe"; $values[] = $importe;
                $fields[] = "importe_letra"; $values[] = $importe_letra;
                $fields[] = "tipo_cambio"; $values[] = "1";
            }
            if (!empty($importedls)) {
                $fields[] = "importedls"; $values[] = $importedls;
                $fields[] = "importedls_letra"; $values[] = $importedls_letra;
                $fields[] = "tipo_cambio"; $values[] = $tipo_cambio;
            }
            if ($documento_adjunto) {
                $fields[] = "documento_adjunto"; $values[] = $documento_adjunto;
            }

            $sql = "INSERT INTO transferencias (" . implode(", ", $fields) . ") VALUES (" . implode(", ", array_fill(0, count($values), "?")) . ")";
            $stmt = $conn->prepare($sql);
            if ($stmt === false) throw new Exception('Error al preparar la consulta de inserción: ' . $conn->error);
            
            $types = str_repeat('s', count($values));
            $stmt->bind_param($types, ...$values);
            $stmt->execute();
            $last_id = $conn->insert_id;
            
            $nuevo_restante = $presupuesto_disponible - $importe_solicitado;
            $nuevo_registrado = $presupuesto_registrado + $importe_solicitado;
            $stmt_update_presupuesto = $conn->prepare("UPDATE presupuestos SET restante = ?, registrado = ? WHERE periodo = ? AND sucursal_id = ? AND departamento_id = ?");
            $stmt_update_presupuesto->bind_param("ddsii", $nuevo_restante, $nuevo_registrado, $periodo, $sucursal_id, $departamento_id);
            $stmt_update_presupuesto->execute();

            // Aquí iría tu llamada a MandarCorreo para el caso único
            MandarCorreo($nombreSolicitante, $folio_formateado,$no_cuenta,$importe,$importe_letra,$importedls_letra,$descripcion,$observaciones,$last_id,$conn, $fecha_solicitud, $fecha_vencimiento);
        }

        // Si el script llegó hasta aquí sin lanzar una excepción, todo salió bien.
        // Hacemos permanentes todos los cambios en la base de datos.
        $conn->commit();
        
        // Preparamos la respuesta de éxito para enviarla como JSON.
        $response = ['success' => true, 'message' => 'Transferencia(s) registrada(s) correctamente con el folio - ' . $folio_formateado];

    } catch (Exception $e) {
        // Si algo falla en cualquier punto del 'try', se revierte toda la operación.
        $conn->rollback();
        // Preparamos la respuesta de error para enviarla como JSON.
        $response = ['success' => false, 'message' => 'Ocurrió un error: ' . $e->getMessage()];
    }
    
    // Al final del script, enviamos la respuesta JSON y terminamos la ejecución.
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

function MandarCorreo($nombreSolicitante, $folio_formateado,$no_cuenta,$importe,$importe_letra,$importedls_letra,$descripcion,$observaciones,$last_id,$conn, $fecha_solicitud, $fecha_vencimiento){
    
    $stmt = $conn->prepare("
            SELECT 
                s.sucursal, 
                b.beneficiario, 
                d.departamento, 
                c.categoria, 
                u.nombre AS usuario_autoriza,
                u.email AS email_autoriza
            FROM transferencias t
            JOIN sucursales s ON t.sucursal_id = s.id
            JOIN beneficiarios b ON t.beneficiario_id = b.id
            JOIN departamentos d ON t.departamento_id = d.id
            JOIN categorias c ON t.categoria_id = c.id
            JOIN usuarios u ON t.autorizacion_id = u.id
            WHERE t.id = ?
        ");
        if ($stmt === false) {
            throw new Exception('Error al preparar la consulta: ' . $conn->error);
        }
        $stmt->bind_param('i', $last_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        
        $importe_formateado = isset($importe) ? number_format(floatval(str_replace(',', '', $importe)), 2, '.', ',') : '';
        $importedls_formateado = isset($importedls) ? number_format(floatval(str_replace(',', '', $importedls)), 2, '.', ',') : '';
    
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
        //$mail->addAddress('ebetancourt@drg.mx');
        $mail->addAddress($row['email_autoriza']);

        $fechaSolicitud = new DateTime($fecha_solicitud);
        $fmt = new IntlDateFormatter('es_ES', IntlDateFormatter::LONG, IntlDateFormatter::NONE);
        $fmt->setPattern('d MMMM yyyy');
        $fechaSolicitudFormateada = $fmt->format($fechaSolicitud);

        $fechaVencimiento = new DateTime($fecha_vencimiento);
        $fechaVencimientoFormateada = $fmt->format($fechaVencimiento);

        $mail->isHTML(true);
        $mail->Subject = 'Solicitud de Transferencia Electronica';
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
                <img src='https://administrador.intranetdrg.com.mx/img/logo-drg.png' alt='Logo' class='logo'>
                <h1>Nueva Solicitud de transferencia electrónica.</h1>
                <h2>Solicitante: <strong>{$nombreSolicitante}</strong></h2>
                
                
                <div class='info-row'>
                    <span class='label'>Folio:</span>
                    <span class='value'>{$folio_formateado}</span>
                </div>
                
                <div class='info-row'>
                    <span class='label'>Sucursal:</span>
                    <span class='value'>{$row['sucursal']}</span>
                </div>
                
                <div class='info-row'>
                    <span class='label'>Beneficiario:</span>
                    <span class='value'>{$row['beneficiario']}</span>
                </div>
                
                <div class='info-row'>
                    <span class='label'>Fecha de Solicitud:</span>
                    <span class='value'>$fechaSolicitudFormateada</span>
                </div>
                
                <div class='info-row'>
                    <span class='label'>No. de Cuenta:</span>
                    <span class='value'>$no_cuenta</span>
                </div>
                
                
                <div class='info-row'>
                    <span class='label'>Importe Pesos:</span>
                    <span class='value'>$ $importe_formateado</span>
                </div>
                
                <div class='info-row'>
                    <span class='label'>Importe con Letra Pesos:</span>
                    <span class='value'>$importe_letra</span>
                </div>

                <div class='info-row'>
                    <span class='label'>Importe en Dolares:</span>
                    <span class='value'>$$importedls_formateado</span>
                </div>
                
                <div class='info-row'>
                    <span class='label'>Importe con Letra Dolares:</span>
                    <span class='value'>$importedls_letra</span>
                </div>
                
                <div class='info-row'>
                    <span class='label'>Departamento:</span>
                    <span class='value'>{$row['departamento']}</span>
                </div>
                
                <div class='info-row'>
                    <span class='label'>Categoria:</span>
                    <span class='value'>{$row['categoria']}</span>
                </div>
                
                <div class='info-row'>
                    <span class='label'>Descripción:</span>
                    <span class='value'>$descripcion</span>
                </div>
                
                <div class='info-row'>
                    <span class='label'>Observaciones:</span>
                    <span class='value'>$observaciones</span>
                </div>
                
                <div class='info-row'>
                    <span class='label'>Autoriza:</span>
                    <span class='value'>{$row['usuario_autoriza']}</span>
                </div>
                <h1>Confirmar Transferencia en el Portal.</h1>

            </div>
        </body>
        </html>
        ";
        $mail->send();
    } catch (Exception $e) {
        // Si PHPMailer falla, lanzamos una excepción para que el 'catch' principal la atrape.
        throw new Exception("El correo no pudo ser enviado. Error: {$mail->ErrorInfo}");
    }
}
function MandarCorreoCorp($nombreSolicitante, $folio_formateado,$no_cuenta,$importe,$importe_letra,$importedls_letra,$descripcion,$observaciones,$last_id,$conn, $fecha_solicitud, $fecha_vencimiento){
    
    $stmt = $conn->prepare("
            SELECT 
                s.sucursal, 
                b.beneficiario, 
                d.departamento, 
                c.categoria, 
                u.nombre AS usuario_autoriza,
                u.email AS email_autoriza
            FROM transferencias t
            JOIN sucursales s ON t.sucursal_id = s.id
            JOIN beneficiarios b ON t.beneficiario_id = b.id
            JOIN departamentos d ON t.departamento_id = d.id
            JOIN categorias c ON t.categoria_id = c.id
            JOIN usuarios u ON t.autorizacion_id = u.id
            WHERE t.id = ?
        ");
        if ($stmt === false) {
            throw new Exception('Error al preparar la consulta: ' . $conn->error);
        }
        $stmt->bind_param('i', $last_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        
        $importe_formateado = isset($importe) ? number_format(floatval(str_replace(',', '', $importe)), 2, '.', ',') : '';
        $importedls_formateado = isset($importedls) ? number_format(floatval(str_replace(',', '', $importedls)), 2, '.', ',') : '';
    
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
        //$mail->addAddress('ebetancourt@drg.mx');
        $mail->addAddress($row['email_autoriza']);

        $fechaSolicitud = new DateTime($fecha_solicitud);
        $fmt = new IntlDateFormatter('es_ES', IntlDateFormatter::LONG, IntlDateFormatter::NONE);
        $fmt->setPattern('d MMMM yyyy');
        $fechaSolicitudFormateada = $fmt->format($fechaSolicitud);

        $fechaVencimiento = new DateTime($fecha_vencimiento);
        $fechaVencimientoFormateada = $fmt->format($fechaVencimiento);

        $mail->isHTML(true);
        $mail->Subject = 'Solicitud de Transferencia Electronica';
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
                <img src='https://administrador.intranetdrg.com.mx/img/logo-drg.png' alt='Logo' class='logo'>
                <h1>Nueva Solicitud de transferencia electrónica.</h1>
                <h2>Solicitante: <strong>{$nombreSolicitante}</strong></h2>
                
                
                <div class='info-row'>
                    <span class='label'>Folio:</span>
                    <span class='value'>{$folio_formateado}</span>
                </div>
                
                <div class='info-row'>
                    <span class='label'>Sucursal:</span>
                    <span class='value'>Corporativo</span>
                </div>
                
                <div class='info-row'>
                    <span class='label'>Beneficiario:</span>
                    <span class='value'>{$row['beneficiario']}</span>
                </div>
                
                <div class='info-row'>
                    <span class='label'>Fecha de Solicitud:</span>
                    <span class='value'>$fechaSolicitudFormateada</span>
                </div>
                
                <div class='info-row'>
                    <span class='label'>No. de Cuenta:</span>
                    <span class='value'>$no_cuenta</span>
                </div>
                
                
                <div class='info-row'>
                    <span class='label'>Importe Pesos:</span>
                    <span class='value'>$ $importe_formateado</span>
                </div>
                
                <div class='info-row'>
                    <span class='label'>Importe con Letra Pesos:</span>
                    <span class='value'>$importe_letra</span>
                </div>

                <div class='info-row'>
                    <span class='label'>Importe en Dolares:</span>
                    <span class='value'>$$importedls_formateado</span>
                </div>
                
                <div class='info-row'>
                    <span class='label'>Importe con Letra Dolares:</span>
                    <span class='value'>$importedls_letra</span>
                </div>
                
                <div class='info-row'>
                    <span class='label'>Departamento:</span>
                    <span class='value'>{$row['departamento']}</span>
                </div>
                
                <div class='info-row'>
                    <span class='label'>Categoria:</span>
                    <span class='value'>{$row['categoria']}</span>
                </div>
                
                <div class='info-row'>
                    <span class='label'>Descripción:</span>
                    <span class='value'>$descripcion</span>
                </div>
                
                <div class='info-row'>
                    <span class='label'>Observaciones:</span>
                    <span class='value'>$observaciones</span>
                </div>
                
                <div class='info-row'>
                    <span class='label'>Autoriza:</span>
                    <span class='value'>{$row['usuario_autoriza']}</span>
                </div>
                <h1>Confirmar Transferencia en el Portal.</h1>

            </div>
        </body>
        </html>
        ";
        $mail->send();

    } catch (Exception $e) {
        // Si PHPMailer falla, lanzamos una excepción.
        throw new Exception("El correo corporativo no pudo ser enviado. Error: {$mail->ErrorInfo}");
    }
}
?>
