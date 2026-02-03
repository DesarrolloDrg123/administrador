<?php
// AUD_controller/enviar_correos_auditoria.php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Intentamos una ruta más robusta para encontrar el vendor
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require __DIR__ . '/../vendor/autoload.php';
} else {
    // Si tu vendor está un nivel más arriba
    require __DIR__ . '/../../vendor/autoload.php';
}

function enviarCorreoDRG($destinatario, $asunto, $cuerpoHTML, $adjunto = null) {
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

        // ESTO ES CLAVE: Si el servidor tiene problemas de certificados SSL
        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );

        $mail->setFrom('notification@intranetdrg.com.mx', 'Sistema de Auditoría DRG');
        $mail->addAddress($destinatario);

        // Adjuntos
        if ($adjunto) {
            if (is_array($adjunto)) {
                foreach ($adjunto as $ruta) {
                    if (is_string($ruta) && file_exists($ruta)) $mail->addAttachment($ruta);
                }
            } elseif (is_string($adjunto) && file_exists($adjunto)) {
                $mail->addAttachment($adjunto);
            }
        }

        $mail->isHTML(true);
        $mail->Subject = $asunto;
        $mail->Body = "
        <div style='font-family: Arial, sans-serif; border: 1px solid #eee; border-radius: 10px; overflow: hidden; max-width: 600px;'>
            <div style='background-color: #80bf1f; padding: 20px; text-align: center; color: white;'>
                <h2 style='margin: 0;'>Sistema de Gestión DRG</h2>
            </div>
            <div style='padding: 30px; color: #333; line-height: 1.6;'>
                $cuerpoHTML
            </div>
            <div style='background-color: #f8f9fa; padding: 15px; text-align: center; font-size: 12px; color: #777;'>
                &copy; " . date('Y') . " Grupo DRG.
            </div>
        </div>";

        return $mail->send();
    } catch (Exception $e) {
        error_log("Error PHPMailer: {$mail->ErrorInfo}");
        return false;
    }
}

/**
 * Mantenemos tu función original pero ahora llama a la genérica
 */
function enviarNotificacionAuditoria($infoReporte) {
    // 1. Agregamos el prefijo '../' si el archivo se guardó en una carpeta relativa 
    // y estamos dentro de AUD_controller.
    $rutaPrincipal = '../' . $infoReporte['ruta'];

    $listaAdjuntos = [];
    
    // Validamos que el reporte principal exista antes de añadirlo
    if (file_exists($rutaPrincipal)) {
        $listaAdjuntos[] = $rutaPrincipal;
    }

    // 2. Sumamos los PDFs adicionales que ya vienen con la ruta correcta
    if (isset($infoReporte['adjuntos_pdf']) && is_array($infoReporte['adjuntos_pdf'])) {
        foreach ($infoReporte['adjuntos_pdf'] as $adjunto) {
            // Verificamos que sea un string y no un array para evitar el Fatal Error
            if (is_string($adjunto) && file_exists($adjunto)) {
                $listaAdjuntos[] = $adjunto;
            }
        }
    }

    $cuerpo = "
        <h3 style='color: #80bf1f;'>Auditoría Vehicular Finalizada</h3>
        <p>Se ha generado un nuevo reporte de control vehicular para la unidad con folio <strong>{$infoReporte['folio']}</strong>.</p>
        <p>Adjunto encontrará el documento PDF con el detalle técnico de la revisión y anexos.</p>";
    
    $asunto = "Finalización de Auditoría Vehicular - Folio: " . $infoReporte['folio'];
    
    // Enviamos la lista depurada de archivos
    return enviarCorreoDRG($infoReporte['correo_responsable'], $asunto, $cuerpo, $listaAdjuntos);
}
?>