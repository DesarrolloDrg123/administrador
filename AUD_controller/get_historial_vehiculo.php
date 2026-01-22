<?php
session_start();
require("../config/db.php");
header('Content-Type: application/json');

$id = (int)($_GET['id'] ?? 0);

$sql = "SELECT h.*, u.nombre as nombre_usuario 
        FROM vehiculos_historial_aud h
        LEFT JOIN usuarios u ON h.usuario_id = u.id 
        WHERE h.vehiculo_id = ? 
        ORDER BY h.fecha_cambio DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$historial = $result->fetch_all(MYSQLI_ASSOC);

echo json_encode($historial);