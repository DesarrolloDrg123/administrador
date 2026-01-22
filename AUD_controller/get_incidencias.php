<?php
session_start();
require("../config/db.php");

header('Content-Type: application/json');

$where = "WHERE 1=1 ";
if(!empty($data['serie'])) $where .= " AND v.no_serie LIKE '%".$data['serie']."%' ";
if(!empty($data['estatus'])) $where .= " AND i.estatus = '".$data['estatus']."' ";
if(!empty($data['f_inicio']) && !empty($data['f_fin'])) {
    $where .= " AND i.fecha_incidencia BETWEEN '".$data['f_inicio']."' AND '".$data['f_fin']."' ";
}

$sql = "SELECT i.*, v.no_serie, a.folio 
        FROM auditorias_incidencias_aud i
        JOIN auditorias_vehiculos_aud a ON i.auditoria_id = a.id
        JOIN vehiculos_aud v ON i.vehiculo_id = v.id
        $where ORDER BY i.fecha_incidencia DESC";

$res = $conn->query($sql);
$incidencias = [];
while($row = $res->fetch_assoc()) {
    $incidencias[] = $row;
}
echo json_encode($incidencias);