<?php
// AUD_controller/enviar_correos_auditoria.php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Asegúrate de tener instalada la librería vía Composer o incluir los archivos manualmente
require '../vendor/autoload.php'; 

function enviarNotificacionAuditoria($infoReporte) {
    $mail = new PHPMailer(true);

    try {
        // --- CONFIGURACIÓN DEL SERVIDOR SMTP ---
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com'; // Cambiar por tu servidor (ej: smtp.office365.com)
        $mail->SMTPAuth   = true;
        $mail->Username   = 'tu-correo@empresa.com'; // Tu correo
        $mail->Password   = 'tu-contraseña-o-token'; // Tu contraseña
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        $mail->CharSet    = 'UTF-8';

        // --- DESTINATARIOS ---
        $mail->setFrom('auditoria@empresa.com', 'Sistema de Auditoría DRG');
        
        // 1. Enviado al responsable de la unidad
        $mail->addAddress($infoReporte['correo_responsable']); 
        
        // 2. Enviado al auditor (puedes usar addCC para copia o addAddress para directo)
        $mail->addAddress($infoReporte['correo_auditor'], 'Auditor Interno'); 

        // --- ADJUNTO ---
        // Adjuntamos el PDF que se acaba de crear 
        if (file_exists($infoReporte['ruta'])) {
            $mail->addAttachment($infoReporte['ruta'], "Reporte_Auditoria_{$infoReporte['folio']}.pdf");
        }

        // --- CONTENIDO DEL CORREO ---
        $mail->isHTML(true);
        $mail->Subject = "Finalización de Auditoría Vehicular - Folio: " . $infoReporte['folio'];
        
        // Usamos el color corporativo #80bf1f en el diseño del correo
        $mail->Body = "
        <div style='font-family: sans-serif; border: 1px solid #eee; padding: 20px;'>
            <h2 style='color: #80bf1f;'>Auditoría Vehicular Finalizada</h2>
            <p>Se ha generado un nuevo reporte de control vehicular para la unidad con folio <strong>{$infoReporte['folio']}</strong>.</p>
            <p>Adjunto encontrará el documento PDF con el detalle de:</p>
            <ul>
                <li>Información general de la unidad.</li>
                <li>Checklist de inventario y estado físico.</li>
                <li>Resultados de puntos obtenidos.</li>
                <li>Fotografías de evidencia tomadas durante la revisión. [cite: 4]</li>
            </ul>
            <br>
            <p style='font-size: 12px; color: #777;'>Este es un correo automático, por favor no responda a este mensaje.</p>
        </div>";

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Error al enviar correo: {$mail->ErrorInfo}");
        return false;
    }
}