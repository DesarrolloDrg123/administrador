<?php
// Configuración de la base de datos
session_start();
require("../config/db.php");
require '../vendor/autoload.php';

header('Content-Type: application/json');

// 1. Recoger datos
$no_serie       = $_POST['no_serie'] ?? null;
$fecha_alta     = $_POST['fecha_alta'] ?? null;
$marca          = $_POST['marca'] ?? null;
$modelo         = $_POST['modelo'] ?? null;
$anio           = $_POST['anio'] ?? null;
$sucursal_id    = (int)($_POST['sucursal_id'] ?? 0);
$responsable_id = (int)($_POST['responsable_id'] ?? 0);
$placas         = $_POST['placas'] ?? '';

// 2. Validación simple
if (!$no_serie || !$marca || !$modelo) {
    echo json_encode(['status' => 'error', 'message' => 'Faltan campos obligatorios.']);
    exit;
}

// 3. Sentencia Preparada para insertar Vehículo
// 'sssssiis' representa los tipos: string, string, string, string, string, int, int, string
$sql = "INSERT INTO vehiculos_aud (no_serie, fecha_alta, marca, modelo, anio, sucursal_id, responsable_id, placas) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)";

$stmt = $conn->prepare($sql);
$stmt->bind_param("sssssiis", $no_serie, $fecha_alta, $marca, $modelo, $anio, $sucursal_id, $responsable_id, $placas);

if ($stmt->execute()) {
    $nuevo_id = $conn->insert_id; // Obtenemos el ID generado

    // 4. Registrar en el historial
    $detalle = "Alta inicial de unidad";
    $sql_h = "INSERT INTO vehiculos_historial_aud (vehiculo_id, usuario_id, campo_modificado, valor_nuevo) VALUES (?, ?, ?, ?)";
    $stmt_h = $conn->prepare($sql_h);
    $campo = "Registro";
    $stmt_h->bind_param("iiss", $nuevo_id, $responsable_id, $campo, $detalle);
    $stmt_h->execute();

    echo json_encode(['status' => 'success', 'message' => 'Vehículo guardado con éxito.']);
} else {
    // Manejo de error (ej. No. de Serie duplicado)
    $mensaje = ($conn->errno == 1062) ? "El número de serie ya está registrado." : "Error: " . $conn->error;
    echo json_encode(['status' => 'error', 'message' => $mensaje]);
}

$stmt->close();
$conn->close();