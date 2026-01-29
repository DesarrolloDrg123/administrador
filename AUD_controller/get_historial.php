<?php
require("../config/db.php");
header('Content-Type: application/json');

// Corregido: cat_items_auditoria_aud y vehiculos_aud
$sql = "SELECT 
            a.id, 
            a.folio, 
            a.fecha_auditoria, 
            a.calif_total,
            a.estatus, 
            (SELECT MAX(fecha_subida) FROM auditorias_evidencias_aud WHERE auditoria_id = a.id) as fecha_subida_evidencia,
            v.no_serie, 
            v.marca, 
            v.modelo,
            u.nombre as auditor_nombre
        FROM auditorias_vehiculos_aud a
        JOIN vehiculos_aud v ON a.vehiculo_id = v.id
        JOIN usuarios u ON a.usuario_id = u.id
        ORDER BY a.fecha_auditoria DESC";

$res = $conn->query($sql);
$data = [];

if ($res) {
    while($row = $res->fetch_assoc()) {
        $data[] = $row;
    }
} else {
    echo json_encode(['error' => $conn->error]);
    exit;
}

echo json_encode($data);