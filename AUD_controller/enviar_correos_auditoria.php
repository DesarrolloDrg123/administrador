<?php
// AUD_controller/enviar_correos_auditoria.php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require __DIR__ . '/../vendor/autoload.php'; 

/**
 * Función genérica para enviar correos con diseño corporativo
 */
function enviarCorreoDRG($destinatario, $asunto, $cuerpoHTML, $adjunto = null) {
    $mail = new PHPMailer(true);

    try {
        // --- CONFIGURACIÓN DEL SERVIDOR SMTP ---
        $mail->isSMTP();
        $mail->Host = 'mail.intranetdrg.com.mx';
        $mail->SMTPAuth = true;
        $mail->Username = 'notification@intranetdrg.com.mx';
        $mail->Password = 'r-eHQi64a7!3QT9';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port = 465;
        $mail->CharSet = 'UTF-8';

        // --- DESTINATARIOS ---
        $mail->setFrom('notification@intranetdrg.com.mx', 'Sistema de Auditoría DRG');
        $mail->addAddress($destinatario);

        // --- MANEJO DE ADJUNTOS (Soporta archivo único o Array) ---
        if ($adjunto) {
            if (is_array($adjunto)) {
                // Si es un array, recorremos cada elemento
                foreach ($adjunto as $ruta) {
                    if (is_string($ruta) && file_exists($ruta)) {
                        $mail->addAttachment($ruta);
                    }
                }
            } elseif (is_string($adjunto) && file_exists($adjunto)) {
                // Si es un solo string
                $mail->addAttachment($adjunto);
            }
        }

        // --- CONTENIDO DEL CORREO CON DISEÑO CORPORATIVO ---
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
                Este es un mensaje automático, por favor no responda. <br> 
                &copy; " . date('Y') . " Grupo DRG.
            </div>
        </div>";

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Error al enviar correo: {$mail->ErrorInfo}");
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