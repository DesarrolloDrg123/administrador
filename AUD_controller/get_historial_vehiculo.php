<?php
session_start();
require("../config/db.php");
header('Content-Type: application/json');

$id = (int)($_GET['id'] ?? 0);

// Esta consulta es más avanzada: busca nombres reales si detecta que son IDs
$sql = "SELECT 
            h.fecha_cambio, 
            u_admin.nombre as nombre_usuario, 
            h.campo_modificado,
            -- Lógica para traducir IDs a Nombres reales en el historial
            CASE 
                WHEN h.campo_modificado = 'Sucursal' THEN 
                    (SELECT CONCAT('Cambio a: ', s.sucursal) FROM sucursales s WHERE s.id = SUBSTRING_INDEX(h.valor_nuevo, ' a: ', -1))
                WHEN h.campo_modificado IN ('Responsable', 'Gerente a Reportar') THEN 
                    (SELECT CONCAT('Cambio a: ', usr.nombre) FROM usuarios usr WHERE usr.id = SUBSTRING_INDEX(h.valor_nuevo, ' a: ', -1))
                ELSE h.valor_nuevo 
            END as valor_nuevo
        FROM vehiculos_historial_aud h
        LEFT JOIN usuarios u_admin ON h.usuario_id = u_admin.id 
        WHERE h.vehiculo_id = ? 
        ORDER BY h.fecha_cambio DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$historial = $result->fetch_all(MYSQLI_ASSOC);

echo json_encode($historial);
?>