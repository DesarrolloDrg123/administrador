<?php

session_start();
require("../config/db.php");
require('../vendor/autoload.php');

if (!isset($_SESSION['loggedin']) || !$_SESSION['loggedin']) {
    header("Location: ../index.php");
    exit();
}

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Verifica si hay una acción (aprobar o rechazar)
if (isset($_POST['accion'])) {
    $accion = $_POST['accion'];
    $solicitud_id = $_POST['solicitud_id'];
    $usuario_id = $_POST['usuario_id'];

    try {
        if ($accion === 'aprobar') {
            // Actualizar el estatus a "aprobada"
            $sql = "UPDATE solicitudes_vacaciones SET estatus = 'aprobada' WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $solicitud_id);
            $stmt->execute();

            if ($stmt->affected_rows > 0) {
                // Obtener información del solicitante para el correo
                $sql_user = "SELECT nombre, email FROM usuarios WHERE id = ?";
                $stmt_user = $conn->prepare($sql_user);
                $stmt_user->bind_param("i", $usuario_id);
                $stmt_user->execute();
                $result_user = $stmt_user->get_result();

                if ($result_user->num_rows > 0) {
                    $user_data = $result_user->fetch_assoc();
                    $nombre_solicitante = $user_data['nombre'];
                    $email_solicitante = $user_data['email'];

                    // Enviar correo de aprobación
                    enviarCorreo($nombre_solicitante, $email_solicitante, 'aprobada');
                    $_SESSION['success'] = "La solicitud fue aprobada exitosamente y el correo fue enviado.";
                }
            } else {
                $_SESSION['error'] = "No se pudo aprobar la solicitud. Por favor, inténtalo de nuevo.";
            }
        } elseif ($accion === 'rechazar') {
            // Manejar la lógica de rechazo
            $razon_cancelacion = $_POST['razon_cancelacion'] ?? 'Sin motivo especificado';
            $sql = "UPDATE solicitudes_vacaciones SET estatus = 'rechazada', razon_cancelacion = ? WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("si", $razon_cancelacion, $solicitud_id);
            $stmt->execute();

            if ($stmt->affected_rows > 0) {
                // Obtener información del solicitante para el correo
                $sql_user = "SELECT nombre, email FROM usuarios WHERE id = ?";
                $stmt_user = $conn->prepare($sql_user);
                $stmt_user->bind_param("i", $usuario_id);
                $stmt_user->execute();
                $result_user = $stmt_user->get_result();

                if ($result_user->num_rows > 0) {
                    $user_data = $result_user->fetch_assoc();
                    $nombre_solicitante = $user_data['nombre'];
                    $email_solicitante = $user_data['email'];

                    // Enviar correo de rechazo
                    enviarCorreo($nombre_solicitante, $email_solicitante, 'rechazada', $razon_cancelacion);
                    $_SESSION['success'] = "La solicitud fue rechazada exitosamente y el correo fue enviado.";
                }
            } else {
                $_SESSION['error'] = "No se pudo rechazar la solicitud. Por favor, inténtalo de nuevo.";
            }
        }
        $stmt->close();
    } catch (Exception $e) {
        error_log("Error al procesar la solicitud: " . $e->getMessage());
        $_SESSION['error'] = "Ocurrió un error al procesar la solicitud.";
    }
}

// Redirige de vuelta al panel de administración
header("Location: ../VAC_autorizar_vacaciones.php");
exit();

/**
 * Función para enviar correo electrónico
 */
function enviarCorreo($nombre_solicitante, $email_solicitante, $accion, $razon_cancelacion = null) {
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = 'mail.intranetdrg.com.mx';
        $mail->SMTPAuth = true;
        $mail->Username = 'administrador@intranetdrg.com.mx';
        $mail->Password = 'WbrE5%7p';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port = 465;

        $mail->setFrom('administrador@intranetdrg.com.mx', 'Sistema de Vacaciones');
        $mail->addAddress($email_solicitante);

        $mail->isHTML(true);
        $mail->Subject = ucfirst($accion) . " de Solicitud de Vacaciones";
        $mail->Body = $accion === 'aprobada'
            ? "
            <html>
            <body>
                <h1>¡Tu solicitud de vacaciones ha sido aprobada!</h1>
                <p>Hola <strong>{$nombre_solicitante}</strong>,</p>
                <p>Nos complace informarte que tu solicitud de vacaciones ha sido <strong>aprobada</strong>.</p>
                <p>Consulta tu portal para más detalles.</p>
            </body>
            </html>"
            : "
            <html>
            <body>
                <h1>Tu solicitud de vacaciones ha sido rechazada</h1>
                <p>Hola <strong>{$nombre_solicitante}</strong>,</p>
                <p>Lamentamos informarte que tu solicitud de vacaciones ha sido <strong>rechazada</strong>.</p>
                <p><strong>Motivo:</strong> {$razon_cancelacion}</p>
            </body>
            </html>";

        $mail->send();
    } catch (Exception $e) {
        error_log("Error al enviar el correo: " . $mail->ErrorInfo);
        $_SESSION['error'] = "Ocurrió un error al enviar el correo: {$mail->ErrorInfo}";
    }
}
?>
