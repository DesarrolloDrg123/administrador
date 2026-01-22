<?php
session_start();
require("../config/db.php");

header('Content-Type: application/json');

// Consulta para traer los datos del catálogo
// Se recomienda un JOIN si tienes tablas de sucursales y usuarios
$sql = "SELECT 
    v.id, 
    v.no_serie, 
    v.marca, 
    v.modelo, 
    v.anio, 
    v.placas, 
    v.sucursal_id, 
    v.responsable_id, 
    v.gerente_reportar_id, -- Importante para el modal
    v.estatus,
    s.sucursal AS sucursal_nombre,
    u.nombre AS responsable_nombre
FROM vehiculos_aud v
INNER JOIN sucursales s ON v.sucursal_id = s.id
INNER JOIN usuarios u ON v.responsable_id = u.id
ORDER BY v.id DESC";

$result = $conn->query($sql);
$vehiculos = [];

if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $vehiculos[] = $row;
    }
}

echo json_encode($vehiculos);
$conn->close();