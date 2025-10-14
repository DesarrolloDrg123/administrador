<?php
session_start();
if (!isset($_SESSION['loggedin']) || !$_SESSION['loggedin']) {
    header("Location: index.php");
    exit();
}

require("config/db.php");
require 'vendor/autoload.php'; // Asegúrate de que PHPMailer esté instalado y autoloaded

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if (!isset($_GET['id'])) {
    die('ID de transferencia no especificado.');
}

$transferencia_id = $_GET['id'];

$conn->begin_transaction();
try {
    // Verificar si el estado actual es 'Subido a Pago'
    $sql = "SELECT estado FROM transferencias WHERE id = ?";
    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        throw new Exception($conn->error);
    }
    $stmt->bind_param("i", $transferencia_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $transferencia = $result->fetch_assoc();

    if (!$transferencia) {
        throw new Exception("Transferencia no encontrada.");
    }

    if ($transferencia['estado'] !== 'Subido a Pago') {
        throw new Exception("El estado de la transferencia no permite esta operación.");
    }

    // Actualizar el estado de la transferencia a 'Pagado'
    $sql = "UPDATE transferencias SET estado = 'Pagado' WHERE id = ?";
    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        throw new Exception($conn->error);
    }
    $stmt->bind_param("i", $transferencia_id);
    $stmt->execute();

    if ($stmt->affected_rows > 0) {
        // Obtener información de la transferencia para el correo electrónico
        $sql = "
            SELECT 
                t.id, s.sucursal, b.beneficiario, t.fecha_solicitud, t.importe, t.descripcion, t.observaciones, u.nombre AS usuario_autoriza, t.no_cuenta
            FROM 
                transferencias t
            JOIN 
                sucursales s ON t.sucursal_id = s.id
            JOIN 
                beneficiarios b ON t.beneficiario_id = b.id
            JOIN 
                usuarios u ON t.autorizacion_id = u.id
            WHERE 
                t.id = ?";
        $stmt = $conn->prepare($sql);
        if ($stmt === false) {
            throw new Exception($conn->error);
        }
        $stmt->bind_param("i", $transferencia_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $transferencia = $result->fetch_assoc();

        if (!$transferencia) {
            throw new Exception("Transferencia no encontrada.");
        }

        // Configurar y enviar el correo
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host = 'mail.intranetdrg.com.mx'; // Reemplaza con tu dominio
            $mail->SMTPAuth = true;
            $mail->Username = 'administrador@intranetdrg.com.mx'; // Reemplaza con tu correo
            $mail->Password = 'WbrE5%7p'; // Reemplaza con tu contraseña
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; // Usa ENCRYPTION_STARTTLS si el puerto es 587
            $mail->Port = 465; // Usa 587 si usas TLS

            $mail->setFrom('administrador@intranetdrg.com.mx', 'Transferencia Electronica Pagada');
            $mail->addAddress('ebetancourt@drg.mx');
            $mail->addAddress('jpauda@drg.mx');
            $mail->addAddress('msalas@drg.mx');

            $fechaSolicitud = new DateTime($transferencia['fecha_solicitud']);
            $fechaSolicitudFormateada = $fechaSolicitud->format('d M Y');

            $mail->isHTML(true);
            $mail->Subject = 'Solicitud de Transferencia Electronica Pagada';
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
                    <h1>Transferencia Electrónica Pagada.</h1>
                    <h2>Solicitante: <strong>{$transferencia['usuario_autoriza']}</strong></h2>
                    
                    <div class='info-row'>
                        <span class='label'>Sucursal:</span>
                        <span class='value'>{$transferencia['sucursal']}</span>
                    </div>
                    
                    <div class='info-row'>
                        <span class='label'>Beneficiario:</span>
                        <span class='value'>{$transferencia['beneficiario']}</span>
                    </div>
                    
                    <div class='info-row'>
                        <span class='label'>Fecha de Solicitud:</span>
                        <span class='value'>$fechaSolicitudFormateada</span>
                    </div>
                    
                    <div class='info-row'>
                        <span class='label'>No. de Cuenta:</span>
                        <span class='value'>{$transferencia['no_cuenta']}</span>
                    </div>
                    
                    <div class='info-row'>
                        <span class='label'>Importe Pesos:</span>
                        <span class='value'>$ {$transferencia['importe']}</span>
                    </div>
                    
                    <div class='info-row'>
                        <span class='label'>Descripción:</span>
                        <span class='value'>{$transferencia['descripcion']}</span>
                    </div>
                    
                    <div class='info-row'>
                        <span class='label'>Observaciones:</span>
                        <span class='value'>{$transferencia['observaciones']}</span>
                    </div>
                    
                    <div class='info-row'>
                        <span class='label'>Autoriza:</span>
                        <span class='value'>{$transferencia['usuario_autoriza']}</span>
                    </div>
                    <h1>Confirmar Transferencia en el Portal.</h1>

                </div>
            </body>
            </html>
            ";
            $mail->send();
            $_SESSION['alert_message'] = "Transferencia pagada con éxito y correo enviado.";
            $_SESSION['alert_type'] = "success";
        } catch (Exception $e) {
            $_SESSION['alert_message'] = "Transferencia pagada con éxito, pero no se pudo enviar el correo. Error: {$mail->ErrorInfo}";
            $_SESSION['alert_type'] = "warning";
        }
    } else {
        $_SESSION['alert_message'] = "No se pudo actualizar la transferencia.";
        $_SESSION['alert_type'] = "danger";
    }

    $conn->commit();
} catch (Exception $e) {
    $conn->rollback();
    $_SESSION['alert_message'] = "Error al procesar la solicitud: " . $e->getMessage();
    $_SESSION['alert_type'] = "danger";
}

header("Location: pagos.php");
exit();
?>
