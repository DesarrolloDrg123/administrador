<?php
session_start();
require("../config/db.php");
header('Content-Type: application/json');

$id = (int)($_GET['id'] ?? 0);

// Como ya guardamos la frase "Cambio de: ... a: ..." procesada,
// solo necesitamos traer el campo tal cual está en la tabla historial.
$sql = "SELECT 
            h.fecha_cambio, 
            u.nombre as nombre_usuario, 
            h.campo_modificado, 
            h.valor_nuevo 
        FROM vehiculos_historial_aud h
        LEFT JOIN usuarios u ON h.usuario_id = u.id 
        WHERE h.vehiculo_id = ? 
        ORDER BY h.fecha_cambio DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id);

if ($stmt->execute()) {
    $result = $stmt->get_result();
    $historial = $result->fetch_all(MYSQLI_ASSOC);
    echo json_encode($historial);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Error al consultar historial']);
}

$conn->close();
?>