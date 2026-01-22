<?php
session_start();
require("../config/db.php");

header('Content-Type: application/json');

$id     = (int)($_POST['id'] ?? 0);
$motivo = $_POST['motivo'] ?? '';
$usuario_id = $_SESSION['usuario_id'] ?? 0; // Usuario que realiza la acción 

if ($id > 0 && !empty($motivo)) {
    // 1. Actualizar estatus y motivo en la tabla principal 
    $sql = "UPDATE vehiculos_aud SET estatus = 'Baja', motivo_baja = ?, fecha_baja = NOW(), usuario_baja = ? WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sii", $motivo, $usuario_id, $id);

    if ($stmt->execute()) {
        // 2. Registrar en el historial el cambio realizado [cite: 10, 28]
        $detalle = "Baja del vehículo. Motivo: " . $motivo;
        $sql_h = "INSERT INTO vehiculos_historial_aud (vehiculo_id, usuario_id, campo_modificado, valor_nuevo) 
                  VALUES (?, ?, 'Estatus', 'Baja')";
        $stmt_h = $conn->prepare($sql_h);
        $stmt_h->bind_param("iis", $id, $usuario_id, $detalle);
        $stmt_h->execute();

        echo json_encode(['status' => 'success', 'message' => 'Unidad dada de baja correctamente.']);
    } else {
        echo json_encode(['status' => 'error', 'message' => $conn->error]);
    }
}
$conn->close();