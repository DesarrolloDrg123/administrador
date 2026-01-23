<?php
require("../config/db.php");
$id = $_GET['id'];

// 1. Cabecera
$sql = "SELECT a.*, v.no_serie, v.marca, v.modelo, u.nombre as auditor 
        FROM auditorias_vehiculos_aud a 
        JOIN vehiculos_aud v ON a.vehiculo_id = v.id 
        JOIN usuarios u ON a.usuario_id = u.id 
        WHERE a.id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id);
$stmt->execute();
$auditoria = $stmt->get_result()->fetch_assoc();

// 2. Checklist
$sql_det = "SELECT d.*, c.pregunta FROM auditorias_detalle_aud d 
            JOIN cat_items_auditoria_aud c ON d.concepto_id = c.id 
            WHERE d.auditoria_id = ?";
$stmt_det = $conn->prepare($sql_det);
$stmt_det->bind_param("i", $id);
$stmt_det->execute();
$detalles = $stmt_det->get_result()->fetch_all(MYSQLI_ASSOC);

// 3. Fotos
$sql_fot = "SELECT * FROM auditorias_evidencias_aud WHERE auditoria_id = ? AND tipo_archivo = 'Foto'";
$stmt_fot = $conn->prepare($sql_fot);
$stmt_fot->bind_param("i", $id);
$stmt_fot->execute();
$fotos = $stmt_fot->get_result()->fetch_all(MYSQLI_ASSOC);

echo json_encode([
    'cabecera' => $auditoria,
    'detalles' => $detalles,
    'fotos' => $fotos
]);