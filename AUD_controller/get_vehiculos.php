<?php
session_start();
require("../config/db.php");

header('Content-Type: application/json');

// Consulta para traer los datos del catálogo
// Se recomienda un JOIN si tienes tablas de sucursales y usuarios
$sql = "SELECT id, no_serie, marca, modelo, anio, placas, sucursal_id, responsable_id, estatus,
                sucursales.sucursal AS sucursal_nombre,
                usuarios.nombre AS responsable_nombre
        FROM vehiculos_aud 
        INNER JOIN sucursales ON vehiculos_aud.sucursal_id = sucursales.id
        INNER JOIN usuarios ON vehiculos_aud.responsable_id = usuarios.id
        ORDER BY id DESC";

$result = $conn->query($sql);
$vehiculos = [];

if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $vehiculos[] = $row;
    }
}

echo json_encode($vehiculos);
$conn->close();