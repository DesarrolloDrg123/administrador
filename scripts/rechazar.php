<?php
session_start();
require "config/db.php";
require 'vendor/autoload.php'; // Asegúrate de que la ruta a PHPMailer sea correcta

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
    $sql = 'SELECT t.id, t.autorizacion_id, t.sucursal_id, t.estado, u.email, u.nombre 
            FROM transferencias t
            JOIN usuarios u ON t.autorizacion_id = u.id
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
    if ($solicitud['estado'] !== 'Pendiente') {
        echo "La solicitud ya ha sido procesada.";
        exit();
    }

    // Actualizar el estado de la solicitud a "aprobado"
    $sql = 'UPDATE transferencias SET estado = "Rechazado" WHERE id = ?';
    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        throw new Exception($conn->error);
    }
    $stmt->bind_param("i", $solicitud_id);
    $stmt->execute();

    // Enviar correo de confirmación
    $mail = new PHPMailer(true);

    try {
        // Configuración SMTP
        $mail->isSMTP();
        $mail->Host = 'mail.intranetdrg.com.mx'; // Reemplaza con tu dominio
        $mail->SMTPAuth = true;
        $mail->Username = 'administrador@intranetdrg.com.mx'; // Reemplaza con tu correo
        $mail->Password = 'WbrE5%7p'; // Reemplaza con tu contraseña
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; // Usa ENCRYPTION_STARTTLS si el puerto es 587
        $mail->Port = 465; // Usa 587 si usas TLS

        $mail->setFrom('administrador@intranetdrg.com.mx', 'Administrador');
        $mail->addAddress('ebetancourt@drg.mx');
        //$mail->addAddress($solicitud['']); // Asegúrate de que esta es la dirección correcta
        //$mail->addAddress('usuario_fijo1@example.com');
        //$mail->addAddress('usuario_fijo2@example.com');

        $fechaSolicitud = new DateTime($solicitud['fecha_solicitud']);
        $fmt = new IntlDateFormatter('es_ES', IntlDateFormatter::LONG, IntlDateFormatter::NONE);
        $fmt->setPattern('d MMMM yyyy'); // Cambia el patrón según sea necesario
        $fechaSolicitudFormateada = $fmt->format($fechaSolicitud);

        $mail->isHTML(true);
        $mail->Subject = 'Solicitud de Rechazada';
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
                <h1>Nueva Solicitud de transferencia electrónica Rechazada.</h1>
                <h2>Solicitante: <strong>{$solicitud['u.nombre']}</strong></h2>
                
                <div class='info-row'>
                    <span class='label'>Sucursal:</span>
                    <span class='value'>{$solicitud['t.sucursal']}</span>
                </div>
                
                <div class='info-row'>
                    <span class='label'>Beneficiario:</span>
                    <span class='value'>{$solicitud['beneficiario']}</span>
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
                    <span class='label'>Fecha de Vencimiento:</span>
                    <span class='value'>{$solicitud['fecha_vencimiento']}</span>
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
                    <span class='label'>Departamento:</span>
                    <span class='value'>{$solicitud['departamento']}</span>
                </div>
                
                <div class='info-row'>
                    <span class='label'>Categoria:</span>
                    <span class='value'>{$solicitud['categoria']}</span>
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
                    <span class='value'>{$solicitud['usuario_autoriza']}</span>
                </div>
                <h1>Solicitud Rechazada!.</h1>

            </div>
        </body>
        </html>
        ";
        $mail->send();
        echo 'Mensaje enviado';
    } catch (Exception $e) {
        echo "No se pudo enviar el correo. Error: {$mail->ErrorInfo}";
    }
    $conn->close();
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
    exit();
}
?>
