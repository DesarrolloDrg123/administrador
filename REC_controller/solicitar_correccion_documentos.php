<?php
session_start();
header('Content-Type: application/json');
require("../config/db.php");

// Importar PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require '../vendor/autoload.php'; // Ajusta la ruta según donde tengas PHPMailer

$response = ['success' => false, 'message' => ''];

if (!isset($_SESSION['loggedin']) || !$_SESSION['loggedin']) {
    $response['message'] = 'Acceso denegado.';
    echo json_encode($response);
    exit();
}

// 🔹 Leer cuerpo JSON crudo
$raw = file_get_contents("php://input");
$input = json_decode($raw, true);

// Depuración opcional — quítalo cuando funcione:
if ($input === null) {
    error_log("⚠️ JSON no recibido o inválido: " . $raw);
}

$candidato_id = $input['candidato_id'] ?? null;
$observaciones = $input['observaciones'] ?? '';
$documentos_corregir = $input['documentos'] ?? [];

if (!is_array($documentos_corregir) || empty($documentos_corregir)) {
    $response['message'] = 'No se recibieron documentos para corregir.';
    echo json_encode($response);
    exit();
}

$usuario_accion = $_SESSION['nombre'] ?? 'Sistema';

if (empty($candidato_id)) {
    $response['message'] = 'Faltan datos para solicitar corrección de documentos.';
    echo json_encode($response);
    exit();
}

$conn->begin_transaction();

try {
    // 1️⃣ Obtener datos del candidato
    $stmt_actual = $conn->prepare("
        SELECT estatus, nombre_completo, correo_electronico 
        FROM solicitudes_vacantes_candidatos 
        WHERE candidato_id = ?
    ");
    $stmt_actual->bind_param("i", $candidato_id);
    $stmt_actual->execute();
    $result = $stmt_actual->get_result()->fetch_assoc();

    if (!$result) {
        throw new Exception("El candidato no existe.");
    }

    $estatus_anterior = $result['estatus'];
    $nombre_candidato = $result['nombre_completo'];
    $correo_candidato = $result['correo_electronico'];

    // 2️⃣ Actualizar estatus y generar token
    $nuevo_estatus = "Espera de Documentos";
    $token = bin2hex(random_bytes(32));
    $estatus_documentos = 0;

    $stmt_update = $conn->prepare("
        UPDATE solicitudes_vacantes_candidatos 
        SET estatus = ?, token_documentos = ?, fecha_token_documentos = NOW(), estatus_documentos = ?
        WHERE candidato_id = ?
    ");
    $stmt_update->bind_param("ssii", $nuevo_estatus, $token, $estatus_documentos, $candidato_id);
    $stmt_update->execute();

    // 3️⃣ Insertar en historial
    $stmt_historial = $conn->prepare("
        INSERT INTO solicitudes_vacantes_candidatos_historial
        (candidato_id, usuario_accion, fecha_accion, estatus_anterior, estatus_nuevo, comentarios)
        VALUES (?, ?, NOW(), ?, ?, ?)
    ");
    $stmt_historial->bind_param("issss", $candidato_id, $usuario_accion, $estatus_anterior, $nuevo_estatus, $observaciones);
    $stmt_historial->execute();

    // 4️⃣ Construir enlace para formulario
    $base_url = "https://administrador2.intranetdrg.com.mx";
    $enlace_formulario = $base_url . "/REC_documentos_candidatos.php?token=" . $token;

    // 5️⃣ Configurar y enviar correo
    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host = 'mail.intranetdrg.com.mx';
        $mail->SMTPAuth = true;
        $mail->Username = 'notification@intranetdrg.com.mx';
        $mail->Password = 'r-eHQi64a7!3QT9';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port = 465;
        $mail->CharSet = 'UTF-8';

        $mail->setFrom('notification@intranetdrg.com.mx', 'DRG Notification');
        $mail->addAddress($correo_candidato, $nombre_candidato);

        $mail->isHTML(true);
        $mail->Subject = 'Corrección de Documentos Requeridos';

        $documentos_html = '';
        foreach ($documentos_corregir as $item) {
            $doc = htmlspecialchars($item['documento']);
            $motivo = !empty($item['motivo']) ? htmlspecialchars($item['motivo']) : 'No especificado';
            $documentos_html .= "<li>✅ <strong>$doc</strong> — Motivo: $motivo</li>";
        }

        $mail->Body = "
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px 20px; text-align: center; border-radius: 10px 10px 0 0; }
                .header h1 { margin: 0; font-size: 28px; }
                .content { background-color: #ffffff; padding: 30px; border: 1px solid #e0e0e0; border-top: none; }
                .button { display: inline-block; padding: 15px 35px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white !important; text-decoration: none; border-radius: 50px; margin: 20px 0; font-weight: bold; box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4); }
                .button:hover { box-shadow: 0 6px 20px rgba(102, 126, 234, 0.6); }
                .footer { text-align: center; padding: 20px; color: #999; font-size: 12px; background-color: #f5f5f5; border-radius: 0 0 10px 10px; }
                .important { background-color: #fff3cd; padding: 20px; border-left: 5px solid #ffc107; margin: 20px 0; border-radius: 5px; }
                .important strong { color: #856404; }
                .document-list { background-color: #f8f9fa; padding: 15px; border-radius: 5px; margin: 15px 0; }
                .document-list li { margin: 8px 0; }
                .congratulations { text-align: center; font-size: 48px; margin: 20px 0; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>Hola ".htmlspecialchars($nombre_candidato)." 👋</h1>
                </div>
                <div class='content'>
                    <p>Se ha solicitado que vuelvas a subir algunos documentos para completar tu proceso de contratación.</p>

                    <div class='important'>
                        <strong>📋 Documentos que requieren corrección:</strong>
                        <ul class='document-list'>
                            $documentos_html
                        </ul>
                    </div>

                    <p style='text-align:center;'>
                        <a href='{$enlace_formulario}' class='button'>📤 Subir Mis Documentos Ahora</a>
                    </p>

                    <p style='font-size:13px; color:#666;'>
                        Este enlace es personal, válido por 30 días. Los archivos deben ser PDF, JPG o PNG (máximo 5MB cada uno).<br>
                        Gracias por tu atención,<br>Recursos Humanos
                    </p>
                </div>
                <div class='footer'>
                    <p>Este es un correo automático del sistema de Talento Humano. Por favor, no responder.</p>
                </div>
            </div>
        </body>
        </html>
        ";

        $mail->send();
        $correo_enviado = true;
    } catch (Exception $e) {
        error_log("Error al enviar correo: " . $mail->ErrorInfo);
        $correo_enviado = false;
    }

    $conn->commit();

    $response['success'] = true;
    $response['message'] = "Solicitud de corrección de documentos registrada correctamente.";
    $response['correo_enviado'] = $correo_enviado;
    if (!$correo_enviado) {
        $response['enlace_manual'] = $enlace_formulario;
    }

} catch (Exception $e) {
    $conn->rollback();
    $response['message'] = "Error: " . $e->getMessage();
}

$conn->close();
echo json_encode($response);
?>
