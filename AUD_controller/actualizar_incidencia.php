<?php
require("../config/db.php");
header('Content-Type: application/json');
$data = json_decode(file_get_contents("php://input"), true);

$id = $data['id'];
$estatus = $data['estatus'];
$obs = $data['obs'];
$fecha_fin = ($estatus == 'Terminada') ? date('Y-m-d') : null;

// Concatenamos la nueva observación al historial previo
$stmt = $conn->prepare("UPDATE auditorias_incidencias_aud SET 
    estatus = ?, 
    observaciones = CONCAT(IFNULL(observaciones,''), '\n[', NOW(), ']: ', ?), 
    fecha_finalizacion = ? 
    WHERE id = ?");

$stmt->bind_param("sssi", $estatus, $obs, $fecha_fin, $id);

if($stmt->execute()) {
    echo json_encode(['status' => 'success']);
} else {
    echo json_encode(['status' => 'error', 'message' => $conn->error]);
}

$stmt->close();
$conn->close();
?>