<?php
session_start();
require("../config/db.php");

header('Content-Type: application/json');

$id         = (int)($_POST['id'] ?? 0);
$motivo     = $_POST['motivo'] ?? '';
$usuario_id = $_SESSION['usuario_id'] ?? 0; 

if ($id > 0 && !empty($motivo)) {
    // 1. Actualizar estatus en la tabla principal
    // Asegúrate de que las columnas motivo_baja, fecha_baja y usuario_baja existan en tu tabla
    $sql = "UPDATE vehiculos_aud SET estatus = 'Baja', motivo_baja = ?, fecha_baja = NOW(), usuario_baja = ? WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sii", $motivo, $usuario_id, $id);

    if ($stmt->execute()) {
        // 2. Registrar en el historial (CORREGIDO)
        $campo = "Estatus";
        $detalle = "Baja del vehículo. Motivo: " . $motivo;
        
        // Ajustamos la consulta para que coincida con los 4 parámetros del bind_param
        $sql_h = "INSERT INTO vehiculos_historial_aud (vehiculo_id, usuario_id, campo_modificado, valor_nuevo) 
                  VALUES (?, ?, ?, ?)";
        $stmt_h = $conn->prepare($sql_h);
        
        // i = integer (id), i = integer (usuario), s = string (campo), s = string (detalle)
        $stmt_h->bind_param("iiss", $id, $usuario_id, $campo, $detalle);
        $stmt_h->execute();

        echo json_encode(['status' => 'success', 'message' => 'Unidad dada de baja correctamente.']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Error al actualizar: ' . $conn->error]);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Datos insuficientes para procesar la baja.']);
}
$conn->close();