<?php
session_start();

require '../vendor/autoload.php'; 

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require("../config/db.php");

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido.']);
    exit;
}
if (!isset($_SESSION['loggedin']) || !$_SESSION['loggedin']) {
    echo json_encode(['success' => false, 'message' => 'No tienes permiso para realizar esta acción.']);
    exit;
}

$id_solicitud = $_POST['id'] ?? null;
$nuevo_estatus = $_POST['nuevo_estatus'] ?? null;
$observaciones = trim($_POST['observaciones'] ?? ''); 
$nombre_usuario_accion = $_SESSION['nombre'];

if (!$id_solicitud || !$nuevo_estatus) {
    echo json_encode(['success' => false, 'message' => 'Error: Faltan datos requeridos.']);
    exit;
}

$conn->begin_transaction();

try {
    $sql_info = "SELECT s.folio, s.solicitante, u.email AS correo_solicitante 
                 FROM solicitudes_movimientos_personal s
                 JOIN usuarios u ON s.solicitante = u.nombre
                 WHERE s.id = ?";
    $stmt_info = $conn->prepare($sql_info);
    $stmt_info->bind_param("i", $id_solicitud);
    $stmt_info->execute();
    $info_solicitud = $stmt_info->get_result()->fetch_assoc();
    
    if (!$info_solicitud) {
        throw new Exception("No se encontró información de la solicitud para enviar el correo.");
    }
    $folio_solicitud = $info_solicitud['folio'];
    $correo_destinatario = $info_solicitud['correo_solicitante'];

    if ($nuevo_estatus === 'Pend. Revision Solicitante') {
        $stmt_update = $conn->prepare("UPDATE solicitudes_movimientos_personal SET estatus = ?, motivo = ? WHERE id = ?");
        $stmt_update->bind_param("ssi", $nuevo_estatus, $observaciones, $id_solicitud);
    } else {
        $stmt_update = $conn->prepare("UPDATE solicitudes_movimientos_personal SET estatus = ? WHERE id = ?");
        $stmt_update->bind_param("si", $nuevo_estatus, $id_solicitud);
    }
    $stmt_update->execute();

    $estatus_cambio = $nuevo_estatus;
    $stmt_historial = $conn->prepare("INSERT INTO solicitudes_movimientos_personal_historial (solicitud_id, usuario_nombre, estatus_cambio, observacion) VALUES (?, ?, ?, ?)");
    $stmt_historial->bind_param("isss", $id_solicitud, $nombre_usuario_accion, $estatus_cambio, $observaciones);
    $stmt_historial->execute();

    // --- INICIO: LÓGICA DE ENVÍO DE CORREOS CON PHPMailer ---
    if ($nuevo_estatus === 'Pend. Revision Solicitante' || $nuevo_estatus === 'Fin') {
        
        $mail = new PHPMailer(true);

        // Obtener los correos de los administradores con acceso al programa 36
        $admin_emails = [];
        $sql_admins = "SELECT u.email AS correo FROM usuarios u JOIN permisos p ON u.id = p.id_usuario WHERE p.id_programa = 36 AND p.acceso = 1";
        $result_admins = $conn->query($sql_admins);
        if ($result_admins) {
            while ($row = $result_admins->fetch_assoc()) {
                $admin_emails[] = $row['correo'];
            }
        }
        
        try {
            // Configuración del servidor (basada en tu ejemplo)
            $mail->isSMTP();
            $mail->Host = 'mail.intranetdrg.com.mx';
            $mail->SMTPAuth = true;
            $mail->Username = 'administrador@intranetdrg.com.mx';
            $mail->Password = 'WbrE5%7p';
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            $mail->Port = 465;

            // Remitente y Destinatarios
            $mail->setFrom('administrador@intranetdrg.com.mx', 'Sistema de Solicitudes ABC');
            $mail->addAddress($correo_destinatario); // Siempre se notifica al solicitante

            $mail->isHTML(true);
            $mail->CharSet = 'UTF-8';

            // Contenido del correo
            if ($nuevo_estatus === 'Pend. Revision Solicitante') {
                $mail->Subject = 'Solicitud Devuelta para Revisión - Folio: ' . $folio_solicitud;
                $mail->Body    = "
                    <html>
                    <head><style> body { font-family: Arial, sans-serif; } .container { padding: 20px; } h2 { color: #299dbf; } blockquote { border-left: 4px solid #f1c40f; padding-left: 15px; margin: 10px 0; } </style></head>
                    <body>
                        <div class='container'>
                            <h2>Hola " . htmlspecialchars($info_solicitud['solicitante']) . ",</h2>
                            <p>Tu solicitud de movimiento de personal con folio <strong>" . $folio_solicitud . "</strong> ha sido devuelta por el siguiente motivo:</p>
                            <blockquote><p><em>" . nl2br(htmlspecialchars($observaciones)) . "</em></p></blockquote>
                            <p>Por favor, ingresa al portal en la sección 'Mis Solicitudes' para editar y reenviar la información.</p>
                            <p>Saludos,<br>Sistema de Solicitudes.</p>
                        </div>
                    </body>
                    </html>";
            } else { // Si es 'Fin'
                // Agregar a los administradores si se encontraron correos
                if (!empty($admin_emails)) {
                    foreach ($admin_emails as $admin_email) {
                        $mail->addAddress($admin_email);
                    }
                }
                $mail->addAddress('sistemasdrg@drg.mx'); //Gerente Sistemas
                $mail->addAddress('gteth@drg.mx'); // Gerente de TH
                $mail->addAddress($correo_destinatario); //Solicitante
                $mail->Subject = 'Proceso Finalizado Exitosamente - Folio: ' . $folio_solicitud;
                
                $mail->Body    = "
                    <html>
                    <head><style> body { font-family: Arial, sans-serif; } .container { padding: 20px; } h2 { color: #299dbf; } </style></head>
                    <body>
                        <div class='container'>
                            <h2>Notificación de Cierre de Proceso</h2>
                            <p>Te informamos que el proceso para la solicitud de movimiento de personal con folio <strong>" . $folio_solicitud . "</strong> ha sido completado y cerrado exitosamente.</p>
                            <p>Puedes consultar los detalles finales en el portal.</p>
                            <p>Saludos,<br>Sistema de Solicitudes.</p>
                        </div>
                    </body>
                    </html>";
            }

            $mail->send();

        } catch (Exception $e) {
            throw new Exception("La operación de base de datos fue exitosa, pero el correo de notificación no pudo ser enviado. Error: {$mail->ErrorInfo}");
        }
    }
    // --- FIN: LÓGICA DE ENVÍO DE CORREOS ---

    $conn->commit();
    echo json_encode(['success' => true, 'message' => "Estatus actualizado a '$nuevo_estatus' correctamente."]);

} catch (Exception $e) {
    $conn->rollback();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error en el servidor: ' . $e->getMessage()]);
}

$conn->close();
?>
