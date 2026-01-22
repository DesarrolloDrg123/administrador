<?php
session_start();
require("../config/db.php");

header('Content-Type: application/json');

// Validar que el ID sea un número
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'ID de vehículo no válido']);
    exit;
}

// Consulta preparada para obtener todos los detalles
$sql = "SELECT v.*, 
               s.sucursal AS sucursal_nombre, 
               u1.nombre AS responsable_nombre, 
               u2.nombre AS gerente_nombre
        FROM vehiculos_aud v
        LEFT JOIN sucursales s ON v.sucursal_id = s.id
        LEFT JOIN usuarios u1 ON v.responsable_id = u1.id
        LEFT JOIN usuarios u2 ON v.gerente_reportar_id = u2.id
        WHERE v.id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $vehiculo = $result->fetch_assoc();
    // No necesitamos enviar el objeto completo si hay datos sensibles, 
    // pero para la ficha técnica enviamos todo el registro.
    echo json_encode($vehiculo);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Vehículo no encontrado']);
}

$stmt->close();
$conn->close();