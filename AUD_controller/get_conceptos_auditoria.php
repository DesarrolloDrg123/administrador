<?php
require("../config/db.php");
header('Content-Type: application/json');

try {
    // Consulta para traer los conceptos y el estatus
    // Usamos un CASE para que en el front se vea "Activo" o "Inactivo"
    $sql = "SELECT id, tipo, descripcion, c1, c2, c3, activo,
            (SELECT SUM(c1) FROM cat_items_auditoria c2 WHERE c2.tipo = cat_items_auditoria.tipo AND c2.activo = 'S') as suma_tipo
            FROM cat_items_auditoria_aud 
            ORDER BY tipo ASC, id ASC";

    $result = $conn->query($sql);
    $conceptos = [];

    while ($row = $result->fetch_assoc()) {
        $conceptos[] = $row;
    }

    echo json_encode($conceptos);

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}

$conn->close();