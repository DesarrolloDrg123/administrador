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

$candidato_id = $_POST['id'] ?? null;
$nuevo_estatus = $_POST['nuevo_estatus'] ?? null;
$observaciones = $_POST['observaciones'] ?? '';
$usuario_accion = $_SESSION['nombre'];

if (empty($candidato_id) || empty($nuevo_estatus)) {
    $response['message'] = 'Faltan datos para la actualización.';
    echo json_encode($response);
    exit();
}

$conn->begin_transaction();

try {
    // 1. Obtener el estatus actual y datos del candidato
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

    // 2. Actualizar el estatus en la tabla principal de candidatos
    $stmt_update = $conn->prepare("UPDATE solicitudes_vacantes_candidatos SET estatus = ? WHERE candidato_id = ?");
    $stmt_update->bind_param("si", $nuevo_estatus, $candidato_id);
    $stmt_update->execute();

    // 3. Insertar el cambio en la tabla de historial de candidatos
    $stmt_historial = $conn->prepare(
        "INSERT INTO solicitudes_vacantes_candidatos_historial (candidato_id, usuario_accion, fecha_accion, estatus_anterior, estatus_nuevo, comentarios) 
         VALUES (?, ?, NOW(), ?, ?, ?)"
    );
    $stmt_historial->bind_param("issss", $candidato_id, $usuario_accion, $estatus_anterior, $nuevo_estatus, $observaciones);
    $stmt_historial->execute();

    // 4. SI EL NUEVO ESTATUS ES "APROBADO", GENERAR TOKEN Y ENVIAR CORREO
    if ($nuevo_estatus === 'Espera de Documentos') {
        // Generar un token único y seguro
        $token = bin2hex(random_bytes(32)); // Token de 64 caracteres
        
        // Guardar el token en la base de datos
        $stmt_token = $conn->prepare("
            UPDATE solicitudes_vacantes_candidatos 
            SET token_documentos = ?, fecha_token_documentos = NOW() 
            WHERE candidato_id = ?
        ");
        $stmt_token->bind_param("si", $token, $candidato_id);
        $stmt_token->execute();

        // Construir el enlace para el formulario de documentos
        $base_url = "https://administrador2.intranetdrg.com.mx";
        $enlace_formulario = $base_url . "/REC_documentos_candidatos.php?token=" . $token;

        // Configurar PHPMailer
        $mail = new PHPMailer(true);

        try {
            // Configuración del servidor SMTP
            $mail->isSMTP();
            $mail->Host = 'mail.intranetdrg.com.mx';
            $mail->SMTPAuth = true;
            $mail->Username = 'notification@intranetdrg.com.mx';
            $mail->Password = 'r-eHQi64a7!3QT9';
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            $mail->Port = 465;
            $mail->CharSet = 'UTF-8';

            // Configuración del remitente y destinatario
            $mail->setFrom('notification@intranetdrg.com.mx', 'DRG Notification');
            $mail->addAddress($correo_candidato, $nombre_candidato);

            // Contenido del correo
            $mail->isHTML(true);
            $mail->Subject = 'Documentos Requeridos para Contratación';
            
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
                        <div class='congratulations'>🎉</div>
                        <h1>¡Felicidades " . htmlspecialchars($nombre_candidato) . "!</h1>
                    </div>
                    <div class='content'>
                        <p style='font-size: 18px;'><strong>¡Excelentes noticias!</strong></p>
                        
                        <p>Nos complace informarte que <strong>has sido aprobado</strong> para continuar con el proceso de contratación.</p>
                        
                        <div class='important'>
                            <strong>📋 Siguiente Paso Importante:</strong><br><br>
                            Para completar tu proceso, necesitamos que nos proporciones los siguientes documentos de forma digital:
                        </div>
                        
                        <div class='document-list'>
                            <ul>
                                <li>✅ <strong>Acta de Nacimiento</strong></li>
                                <li>✅ <strong>Credencial del Elector Vigente (INE)</strong> — Frente y reverso</li>
                                <li>✅ <strong>CURP</strong> (Clave Única de Registro de Población)</li>
                                <li>✅ <strong>Constancia de Situación Fiscal (CSF)</strong> — Actualizada, régimen: sueldos y salarios</li>
                                <li>✅ <strong>Número de Seguridad Social (NSS)</strong> — Hoja oficial del IMSS</li>
                                <li>✅ <strong>Hoja de Retención de Infonavit</strong> (en caso de tener crédito)</li>
                                <li>✅ <strong>Comprobante de Domicilio Actual</strong> — No mayor a 3 meses</li>
                                <li>✅ <strong>Comprobante de Último Grado de Estudios</strong> — Certificado, título o constancia</li>
                                <li>✅ <strong>Licencia Vigente</strong> (en caso de aplicar)</li>
                            </ul>
                        </div>
                        
                        <p style='text-align: center; margin: 30px 0;'>
                            <a href='" . $enlace_formulario . "' class='button'>📤 Subir Mis Documentos Ahora</a>
                        </p>
                        
                        <p style='font-size: 13px; color: #666; background-color: #e7f3ff; padding: 15px; border-radius: 5px;'>
                            <strong> Importante:</strong><br>
                            • Este enlace es personal e intransferible<br>
                            • Válido por <strong>30 días</strong><br>
                            • Los archivos deben ser en formato PDF, JPG o PNG<br>
                            • Tamaño máximo por archivo: 5MB<br>
                            • Una vez que envíes tus documentos, no podrás volver a usar este enlace
                        </p>
                        
                        <p style='margin-top: 30px;'>Si tienes alguna duda o problema para subir tus documentos, no dudes en contactarnos.</p>
                        
                        <p style='margin-top: 30px; font-weight: bold; color: #667eea;'>¡Bienvenido al equipo! 🎊</p>
                    </div>
                    <div class='footer'>
                        <p>Este es un correo automático del sistema de Recursos Humanos.</p>
                        <p>Por favor, no respondas a este correo.</p>
                    </div>
                </div>
            </body>
            </html>
            ";

            // Versión de texto plano (alternativa)
            $mail->AltBody = "¡Felicidades {$nombre_candidato}!\n\n" .
                           "Has sido aprobado para continuar con el proceso de contratación.\n\n" .
                           "Por favor, ingresa al siguiente enlace para subir tus documentos:\n" .
                           "{$enlace_formulario}\n\n" .
                           "Documentos requeridos:\n" .
                           "- Identificación Oficial (INE/IFE)\n" .
                           "- CURP\n" .
                           "- RFC\n" .
                           "- Comprobante de Domicilio\n" .
                           "- Número de Seguridad Social\n" .
                           "- Comprobante de Estudios\n" .
                           "- Exámenes Médicos\n\n" .
                           "Saludos,\nRecursos Humanos";

            // Enviar el correo
            $mail->send();
            
            $response['correo_enviado'] = true;
            $response['message'] = "El estatus del candidato ha sido actualizado a 'Aprobado'. Se ha enviado un correo electrónico con las instrucciones para cargar documentos.";
            
        } catch (Exception $e) {
            // Si falla el envío con PHPMailer
            error_log("Error al enviar correo con PHPMailer: " . $mail->ErrorInfo);
            $response['correo_enviado'] = false;
            $response['message'] = "El estatus del candidato ha sido actualizado a 'Aprobado'. ADVERTENCIA: No se pudo enviar el correo de notificación. Error: " . $mail->ErrorInfo;
            $response['enlace_manual'] = $enlace_formulario;
        }
        
    } else {
        $response['message'] = "El estatus del candidato ha sido actualizado a '$nuevo_estatus'.";
    }

    $conn->commit();
    $response['success'] = true;

} catch (Exception $e) {
    $conn->rollback();
    $response['message'] = "Error al actualizar: " . $e->getMessage();
}

$conn->close();

echo json_encode($response);
?>