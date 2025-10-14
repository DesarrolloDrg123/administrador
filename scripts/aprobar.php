<?php
session_start();
require "config/db.php";
require 'vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if (!isset($_SESSION['loggedin']) || !$_SESSION['loggedin']) {
    header("Location: index.php");
    exit();
}

if (!isset($_GET['id'])) {
    echo "ID de solicitud no proporcionado.";
    exit();
}

$solicitud_id = intval($_GET['id']);
$usuario_id = $_SESSION['usuario_id'];

try {
    // Verificar que la solicitud pertenece al usuario
    $sql = 'SELECT b.beneficiario AS nombre_beneficiario, c.categoria AS nombre_categoria, t.id, t.autorizacion_id, t.sucursal_id, t.estado, t.usuario_id, 
            t.fecha_solicitud, t.no_cuenta, t.fecha_vencimiento, t.importe, 
            t.importe_letra, t.importedls, t.importedls_letra, t.departamento_id, d.departamento AS departamento, t.categoria_id, t.descripcion, 
            t.observaciones, t.beneficiario_id, s.sucursal AS sucursal_nombre, 
            u.nombre AS autorizador_nombre, u.email
            FROM transferencias t
            JOIN categorias c ON t.categoria_id = c.id
            JOIN beneficiarios b ON t.beneficiario_id = b.id
            JOIN usuarios u ON t.autorizacion_id = u.id
            JOIN sucursales s ON t.sucursal_id = s.id
            JOIN departamentos d on t.departamento_id
            WHERE t.id = ? AND t.autorizacion_id = ?';
    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        throw new Exception($conn->error);
    }
    $stmt->bind_param("ii", $solicitud_id, $usuario_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        echo "Solicitud no encontrada o no pertenece a este usuario.";
        exit();
    }

    $solicitud = $result->fetch_assoc();
    $id = $solicitud['id'];
    $autorizacion_id = $solicitud['autorizacion_id'];
    $sucursal_id = $solicitud['sucursal_id'];
    $estado = $solicitud['estado'];
    $email = $solicitud['email'];
    $nombre_autorizador = $solicitud['autorizador_nombre'];
    $solicitante_id = $solicitud['usuario_id'];

    // Obtener el nombre del usuario solicitante
    $sql_user = 'SELECT nombre FROM usuarios WHERE id = ?';
    $stmt_user = $conn->prepare($sql_user);
    if ($stmt_user === false) {
        throw new Exception($conn->error);
    }
    $stmt_user->bind_param("i", $solicitante_id);
    $stmt_user->execute();
    $result_user = $stmt_user->get_result();

    if ($result_user->num_rows === 0) {
        echo "Usuario solicitante no encontrado.";
        exit();
    }

    $user_info = $result_user->fetch_assoc();
    $solicitante_nombre = $user_info['nombre'];

    if ($estado !== 'Pendiente') {
        echo "La solicitud ya ha sido procesada.";
        exit();
    }

    // Actualizar el estado de la solicitud a "Aprobado"
    $sql_update = 'UPDATE transferencias SET estado = "Aprobado" WHERE id = ?';
    $stmt_update = $conn->prepare($sql_update);
    if ($stmt_update === false) {
        throw new Exception($conn->error);
    }
    $stmt_update->bind_param("i", $solicitud_id);
    $stmt_update->execute();

    // Enviar correo de confirmación
    $mail = new PHPMailer(true);

    try {
        // Configuración SMTP
        $mail->isSMTP();
        $mail->Host = 'mail.intranetdrg.com.mx'; 
        $mail->SMTPAuth = true;
        $mail->Username = 'administrador@intranetdrg.com.mx'; 
        $mail->Password = 'WbrE5%7p'; 
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; 
        $mail->Port = 465;

        $mail->setFrom('administrador@intranetdrg.com.mx', 'Solicitudes Electronicas');
        $mail->addAddress('ebetancourt@drg.mx');
        $mail->addAddress('msalas@drg.mx');
        $mail->addAddress('jpauda@drg.mx');
        $mail->addAddress($email);

        $fechaSolicitud = new DateTime($solicitud['fecha_solicitud']);
        $fmt = new IntlDateFormatter('es_ES', IntlDateFormatter::LONG, IntlDateFormatter::NONE);
        $fmt->setPattern('d MMMM yyyy');
        $fechaSolicitudFormateada = $fmt->format($fechaSolicitud);
    
        $mail->isHTML(true);
        $mail->Subject = 'Solicitud Aprobada';
        $mail->Body = "
        <html>
        <head>
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
                <img src='https://i.ibb.co/drvS4yF/logo-drg.png' alt='Logo' class='logo'>
                <h1>Solicitud de transferencia electrónica Aprobada.</h1>
                <h2>Solicitante: <strong>{$solicitante_nombre}</strong></h2>
                
                <div class='info-row'>
                    <span class='label'>Sucursal:</span>
                    <span class='value'>{$solicitud['sucursal_nombre']}</span>
                </div>
                
                <div class='info-row'>
                    <span class='label'>Beneficiario:</span>
                    <span class='value'>{$solicitud['nombre_beneficiario']}</span>
                </div>
                
                <div class='info-row'>
                    <span class='label'>Fecha de Solicitud:</span>
                    <span class='value'>{$fechaSolicitudFormateada}</span>  
                </div>
                
                <div class='info-row'>
                    <span class='label'>No. de Cuenta:</span>
                    <span class='value'>{$solicitud['no_cuenta']}</span>
                </div>
                
                <div class='info-row'>
                    <span class='label'>Importe:</span>
                    <span class='value'>{$solicitud['importe']}</span>
                </div>
                
                <div class='info-row'>
                    <span class='label'>Importe con Letra:</span>
                    <span class='value'>{$solicitud['importe_letra']}</span>
                </div>

                <div class='info-row'>
                    <span class='label'>Importe en DLS:</span>
                    <span class='value'>{$solicitud['importedls']}</span>
                </div>
                
                <div class='info-row'>
                    <span class='label'>Importe con Letra DLS:</span>
                    <span class='value'>{$solicitud['importedls_letra']}</span>
                </div>
                
                <div class='info-row'>
                    <span class='label'>Departamento:</span>
                    <span class='value'>{$solicitud['departamento']}</span>
                </div>
                
                <div class='info-row'>
                    <span class='label'>Categoria:</span>
                    <span class='value'>{$solicitud['nombre_categoria']}</span>
                </div>
                
                <div class='info-row'>
                    <span class='label'>Descripción:</span>
                    <span class='value'>{$solicitud['descripcion']}</span>
                </div>
                
                <div class='info-row'>
                    <span class='label'>Observaciones:</span>
                    <span class='value'>{$solicitud['observaciones']}</span>
                </div>
                
                <div class='info-row'>
                    <span class='label'>Autoriza:</span>
                    <span class='value'>{$nombre_autorizador}</span>
                </div>
                <h1>Solicitud Autorizada.</h1>

            </div>
        </body>
        </html>
        ";
        $mail->send();
        $response = ['success' => true, 'message' => 'Correo enviado con éxito.'];
    } catch (Exception $e) {
        $response = ['success' => false, 'message' => "No se pudo enviar el correo. Error: {$mail->ErrorInfo}"];
    }

    header('Content-Type: application/json');
    echo json_encode($response);
    exit();

} catch (Exception $e) {
    $response = ['success' => false, 'message' => "Error: " . $e->getMessage()];
    header('Content-Type: application/json');
    echo json_encode($response);
    exit();
}
?>