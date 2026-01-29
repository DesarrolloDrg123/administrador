<?php
include("../config/db.php"); // Tu conexión a la base de datos

$vehiculo_id = $_GET['vehiculo_id'] ?? null;

if (!$vehiculo_id) {
    echo json_encode([]);
    exit;
}

$query = "SELECT i.descripcion, a.folio as folio_original 
          FROM auditorias_incidencias_aud i
          JOIN auditorias_vehiculos_aud a ON i.auditoria_id = a.id 
          WHERE a.vehiculo_id = ? AND i.estatus = 'Pendiente'
          ORDER BY i.fecha_incidencia DESC";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $vehiculo_id);
$stmt->execute();
$result = $stmt->get_result();

$pendientes = [];
while ($row = $result->fetch_assoc()) {
    $pendientes[] = $row;
}

echo json_encode($pendientes);
?>