<?php
session_start();
require("../config/db.php");
require '../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Preparamos la respuesta para AJAX
$response = ['success' => false, 'message' => ''];
header('Content-Type: application/json');

// =================================================================
// === FUNCIÓN PARA ENVIAR CORREOS (la puedes mover a un archivo central)
// =================================================================
function enviarNotificacion($destinatarios, $asunto, $cuerpo_html) {
    if (empty($destinatarios)) {
        error_log("No se encontraron destinatarios para el correo.");
        return;
    }
    $mail = new PHPMailer(true);
    try {
        // Configuración del servidor
        $mail->isSMTP();
        $mail->Host = 'mail.intranetdrg.com.mx';
        $mail->SMTPAuth = true;
        $mail->Username = 'notification@intranetdrg.com.mx';
        $mail->Password = 'r-eHQi64a7!3QT9';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port = 465;
        $mail->CharSet = 'UTF-8';
        $mail->setFrom('notification@intranetdrg.com.mx', 'DRG Notification');
        foreach ($destinatarios as $email) {
            $mail->addAddress($email);
        }
        $mail->isHTML(true);
        $mail->Subject = $asunto;
        $mail->Body = $cuerpo_html;
        $mail->send();
    } catch (Exception $e) {
        error_log("Error al enviar correo: " . $mail->ErrorInfo);
    }
}

// 1. VALIDACIONES DE SEGURIDAD (sin cambios)
if ($_SERVER["REQUEST_METHOD"] !== "POST") { /* ... */ exit(); }
if (!isset($_SESSION['loggedin']) || !$_SESSION['loggedin']) { /* ... */ exit(); }
if (!isset($_POST['folio'], $_POST['estatus'], $_POST['motivo'])) { /* ... */ exit(); }

// 2. RECOPILAR Y LIMPIAR DATOS (sin cambios)
$folio = $_POST['folio'];
$nuevoEstatus = $_POST['estatus'];
$motivo = trim($_POST['motivo']);

if (($nuevoEstatus !== 'Rechazada' && $nuevoEstatus !== 'Devuelta') || empty($motivo)) {
    $response['message'] = 'Error: Datos no válidos.';
    echo json_encode($response);
    exit();
}

// 3. ACTUALIZAR LA BASE DE DATOS Y ENVIAR CORREO
try {
    // --- PASO A: Obtener el nombre del solicitante original ---
    $sql_solicitante = "SELECT user_solicitante FROM datos_generales_co WHERE folio = ?";
    $stmt_solicitante = $conn->prepare($sql_solicitante);
    $stmt_solicitante->bind_param("s", $folio);
    $stmt_solicitante->execute();
    $result_solicitante = $stmt_solicitante->get_result();
    
    if ($result_solicitante->num_rows === 0) {
        throw new Exception("No se encontró la cotización con el folio proporcionado.");
    }
    $solicitante_data = $result_solicitante->fetch_assoc();
    $nombre_solicitante = $solicitante_data['user_solicitante'];
    $stmt_solicitante->close();

    // --- PASO B: Actualizar el estatus y motivo de la cotización ---
    $sql = "UPDATE datos_generales_co SET estatus = ?, motivo = ? WHERE folio = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sss", $nuevoEstatus, $motivo, $folio);

    if ($stmt->execute()) {
        $response['success'] = true;

        // --- PASO C: Enviar correo de notificación al solicitante ---
        $sql_email = "SELECT email FROM usuarios WHERE nombre = ?";
        $stmt_email = $conn->prepare($sql_email);
        $stmt_email->bind_param("s", $nombre_solicitante);
        $stmt_email->execute();
        $result_email = $stmt_email->get_result();
        $usuario_data = $result_email->fetch_assoc();
        $stmt_email->close();

        if ($usuario_data && !empty($usuario_data['email'])) {
            $email_destinatario = [$usuario_data['email']];
            $folio_formateado = sprintf('%09d', $folio);
            
            // Preparamos el contenido del correo
            $asunto = "Actualización de tu Cotización - Folio {$folio_formateado} ha sido {$nuevoEstatus}";
            $cuerpo_html = "
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
                        <img src='https://administrador.intranetdrg.com.mx/img/logo-drg.png' alt='Logo' class='logo'>
                        <h1>Actualización de Cotización</h1>
                        <p>Hola " . htmlspecialchars($nombre_solicitante) . ", se ha actualizado el estado de tu solicitud de cotización.</p>
                        
                        <div class='info-row'>
                            <span class='label'>Folio:</span>
                            <span>" . htmlspecialchars($folio_formateado) . "</span>
                        </div>
                        
                        <div class='info-row'>
                            <span class='label'>Nuevo Estatus:</span>
                            <strong style='color:" . ($nuevoEstatus == 'Rechazada' ? '#d9534f' : '#f0ad4e') . ";'>" . htmlspecialchars($nuevoEstatus) . "</strong>
                        </div>
                        
                        <div class='motivo-box'>
                            <span class='label'>Motivo:</span>
                            <p>" . nl2br(htmlspecialchars($motivo)) . "</p>
                        </div>
                        
                        <hr>
                        <p style='text-align:center;'><strong>Por favor, ingrese al portal para revisar los detalles.</strong></p>
            
                    </div>
                </body>
                </html>
            ";

            // Enviamos la notificación
            enviarNotificacion($email_destinatario, $asunto, $cuerpo_html);
        }
    } else {
        $response['message'] = "Error al actualizar la base de datos.";
    }
    $stmt->close();
} catch (Exception $e) {
    $response['message'] = "Error de base de datos: " . $e->getMessage();
}

$conn->close();

// 4. DEVOLVER LA RESPUESTA
echo json_encode($response);
?>