<?php
session_start();
require "../config/db.php";
require '../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

header('Content-Type: application/json');

function enviarRespuesta($success, $message) {
    echo json_encode(['success' => $success, 'message' => $message]);
    exit();
}

if (!isset($_SESSION['loggedin']) || !$_SESSION['loggedin']) {
    enviarRespuesta(false, 'Acceso no autorizado.');
}

if (!isset($_GET['id'])) {
    enviarRespuesta(false, 'ID de solicitud no proporcionado.');
}

$solicitud_id_inicial = intval($_GET['id']);
$usuario_id_autorizador = $_SESSION['usuario_id'];
$motivo_rechazo = trim($_POST['motivo'] ?? '');

if (empty($motivo_rechazo)) {
    enviarRespuesta(false, 'El motivo del rechazo es obligatorio.');
}

$conn->begin_transaction();
try {
    // --- PASO 1: OBTENER EL FOLIO Y VERIFICAR PERMISO ---
    $stmt_folio = $conn->prepare("SELECT folio FROM transferencias_clara_tcl WHERE id = ? AND autorizacion_id = ?");
    if (!$stmt_folio) throw new Exception('Error al preparar consulta de folio.');
    $stmt_folio->bind_param("ii", $solicitud_id_inicial, $usuario_id_autorizador);
    $stmt_folio->execute();
    $result_folio = $stmt_folio->get_result();
    if ($result_folio->num_rows === 0) {
        throw new Exception('Solicitud no encontrada o no tiene permisos para procesarla.');
    }
    $folio_a_rechazar = $result_folio->fetch_assoc()['folio'];
    $stmt_folio->close();

    // --- PASO 2: OBTENER TODOS LOS DATOS (CON JOINS) DE LAS TRANSFERENCIAS DE ESE FOLIO ---
    $sql_transferencias = 'SELECT
            t.*, s.sucursal, b.nombre AS nombre_beneficiario, b.email AS email_beneficiario, d.departamento, c.categoria,
            u_sol.nombre AS nombre_solicitante, u_sol.email AS email_solicitante,
            u_aut.nombre AS nombre_autoriza
        FROM transferencias_clara_tcl t
        LEFT JOIN sucursales s ON t.sucursal_id = s.id
        JOIN usuarios b ON t.beneficiario_id = b.id
        LEFT JOIN departamentos d ON t.departamento_id = d.id
        LEFT JOIN categorias c ON t.categoria_id = c.id
        LEFT JOIN usuarios u_sol ON t.usuario_solicitante_id = u_sol.id
        LEFT JOIN usuarios u_aut ON t.autorizacion_id = u_aut.id
        WHERE t.folio = ? FOR UPDATE';
        
    $stmt = $conn->prepare($sql_transferencias);
    if (!$stmt) throw new Exception('Error al preparar consulta de transferencias.');
    $stmt->bind_param("s", $folio_a_rechazar);
    $stmt->execute();
    $transferencias_a_rechazar = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    if (empty($transferencias_a_rechazar)) {
        throw new Exception('No se encontraron transferencias para el folio ' . htmlspecialchars($folio_a_rechazar));
    }
    
    // --- PASO 3: BUCLE PARA REVERTIR PRESUPUESTO DE CADA SUCURSAL ---

    // --- PASO 4: ACTUALIZAR EL ESTADO DE TODAS LAS TRANSFERENCIAS DEL FOLIO ---
    $sql_update = 'UPDATE transferencias_clara_tcl SET estado = "Rechazado", motivo = ? WHERE folio = ?';
    $stmt_update = $conn->prepare($sql_update);
    if (!$stmt_update) throw new Exception('Error al preparar la actualización de estado.');
    $stmt_update->bind_param("ss", $motivo_rechazo, $folio_a_rechazar);
    $stmt_update->execute();
    $stmt_update->close();

    // --- PASO 5: PREPARAR DATOS Y ENVIAR CORREO DE RESUMEN ---
    $info_para_correo = $transferencias_a_rechazar[0]; // Tomamos la primera fila para datos comunes
    
    // --- LÓGICA PARA DETECTAR SI ES CORPORATIVO Y SUMAR TOTALES ---
    $es_corporativo = count($transferencias_a_rechazar) > 1;
    $importe_total_pesos = 0;
    $importe_total_dolares = 0;
    
    foreach ($transferencias_a_rechazar as $solicitud_item) {
        $importe_total_pesos += floatval($solicitud_item['importe']);
        $importe_total_dolares += floatval($solicitud_item['importedls']);
    }
    
    // Determinamos la sucursal y el importe a mostrar en el correo
    if ($es_corporativo) {
        $sucursal_a_mostrar = 'Corporativo';
        // Para el importe, decidimos si es en dólares o pesos y usamos el total
        if ($importe_total_dolares > 0) {
            $importe_a_mostrar = 'US$ ' . number_format($importe_total_dolares, 2);
            $importe_letra_a_mostrar = $info_para_correo['importedls_letra']; // Asumimos que la letra es la misma para el total
        } else {
            $importe_a_mostrar = '$ ' . number_format($importe_total_pesos, 2);
            $importe_letra_a_mostrar = $info_para_correo['importe_letra'];
        }
    } else {
        // Si no es corporativo, usamos los datos de la única transferencia
        $sucursal_a_mostrar = $info_para_correo['sucursal'];
        if ($importe_total_dolares > 0) {
            $importe_a_mostrar = 'US$ ' . number_format($importe_total_dolares, 2);
            $importe_letra_a_mostrar = $info_para_correo['importedls_letra'];
        } else {
            $importe_a_mostrar = '$ ' . number_format($importe_total_pesos, 2);
            $importe_letra_a_mostrar = $info_para_correo['importe_letra'];
        }
    }
    
    // --- ENVÍO DEL CORREO ---
    $mail = new PHPMailer(true);
    try {
        // ... Tu configuración SMTP (Host, Username, Password, etc.) ...
        $mail->isSMTP();
        $mail->Host = 'mail.intranetdrg.com.mx';
        $mail->SMTPAuth = true;
        $mail->Username = 'administrador@intranetdrg.com.mx';
        $mail->Password = 'WbrE5%7p';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port = 465;
        $mail->CharSet = 'UTF-8';
    
        $mail->setFrom('administrador@intranetdrg.com.mx', 'Sistema de Transferencias');
        if (!empty($info_para_correo['email_solicitante'])) {
            $mail->addAddress($info_para_correo['email_solicitante']);
        }
        if (!empty($info_para_correo['email_beneficiario'])) {
            $mail->addAddress($info_para_correo['email_beneficiario']);
        }

        
    
        $fechaSolicitudFormateada = (new DateTime($info_para_correo['fecha_solicitud']))->format('d/m/Y');
        $fechaVencimientoFormateada = (new DateTime($info_para_correo['fecha_vencimiento']))->format('d/m/Y');
    
        $mail->isHTML(true);
        $mail->Subject = "Solicitud Rechazada - Folio: {$folio_a_rechazar}";
        
        // --- CUERPO COMPLETO DEL CORREO CON EL FORMATO SOLICITADO ---
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
                <h1>Solicitud de transferencia electrónica Rechazada.</h1>
                <h2>Solicitante: <strong>{$info_para_correo['nombre_solicitante']}</strong></h2>
                
                <div class='info-row'>
                    <span class='label'>Folio:</span>
                    <span class='value'>{$folio_a_rechazar}</span>
                </div>
                <div class='info-row'>
                    <span class='label'>Sucursal:</span>
                    <span class='value'>{$sucursal_a_mostrar}</span>
                </div>
                <div class='info-row'>
                    <span class='label'>Beneficiario:</span>
                    <span class='value'>{$info_para_correo['nombre_beneficiario']}</span>
                </div>
                <div class='info-row'>
                    <span class='label'>Fecha de Solicitud:</span>
                    <span class='value'>{$fechaSolicitudFormateada}</span>
                </div>
                <div class='info-row'>
                    <span class='label'>No. de Cuenta:</span>
                    <span class='value'>{$info_para_correo['no_cuenta']}</span>
                </div>
                <div class='info-row'>
                    <span class='label'>Fecha de Vencimiento:</span>
                    <span class='value'>{$fechaVencimientoFormateada}</span>
                </div>
                <div class='info-row'>
                    <span class='label'>Importe:</span>
                    <span class='value'>{$importe_a_mostrar}</span>
                </div>
                <div class='info-row'>
                    <span class='label'>Importe con Letra:</span>
                    <span class='value'>{$importe_letra_a_mostrar}</span>
                </div>
                <div class='info-row'>
                    <span class='label'>Departamento:</span>
                    <span class='value'>{$info_para_correo['departamento']}</span>
                </div>
                <div class='info-row'>
                    <span class='label'>Categoría:</span>
                    <span class='value'>{$info_para_correo['categoria']}</span>
                </div>
                <div class='info-row'>
                    <span class='label'>Descripción:</span>
                    <span class='value'>{$info_para_correo['descripcion']}</span>
                </div>
                <div class='info-row'>
                    <span class='label'>Motivo de Rechazo:</span>
                    <span class='value'>".htmlspecialchars($motivo_rechazo)."</span>
                </div>
                <div class='info-row'>
                    <span class='label'>Observaciones:</span>
                    <span class='value'>{$info_para_correo['observaciones']}</span>
                </div>
                <div class='info-row'>
                    <span class='label'>Rechazada por:</span>
                    <span class='value'>{$_SESSION['nombre']}</span>
                </div>
                <h1>¡Solicitud Rechazada!</h1>
            </div>
        </body>
        </html>";
        
        $mail->send();
    } catch (Exception $e) {
        error_log("Correo de rechazo no enviado para folio {$folio_a_rechazar}: {$mail->ErrorInfo}");
    }

    $conn->commit();
    enviarRespuesta(true, 'La solicitud ha sido rechazada correctamente.');

} catch (Exception $e) {
    $conn->rollback();
    enviarRespuesta(false, $e->getMessage());
}
?>