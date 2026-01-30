<?php
// Tu archivo de "Finalizar" modificado
require("../config/db.php");
require("generar_reporte_pdf.php");
require("enviar_correos_auditoria.php"); // Debes crear este con PHPMailer

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

    if ($id > 0) {
        $query = "UPDATE auditorias_vehiculos_aud SET token_evidencia = NULL, estatus = 'Finalizada' WHERE id = ?";
        $stmt = $conn->prepare($query);
        
        if ($stmt->bind_param("i", $id) && $stmt->execute()) {
            
            // --- LOGICA NUEVA ---
            // 1. Crear el PDF
            $infoReporte = crearReportePDF($id, $conn);
            
            // 2. Enviar correos
            $envio = enviarNotificacionAuditoria($infoReporte);
            
            echo json_encode(['success' => true, 'pdf' => $infoReporte['ruta']]);
        } else {
            echo json_encode(['success' => false, 'error' => $stmt->error]);
        }
    }
}
?>