<?php
require("../config/db.php");
header('Content-Type: application/json');

// Revisamos si en la URL viene ?solo_activos=1
$solo_activos = isset($_GET['solo_activos']) && $_GET['solo_activos'] == '1';

try {
    $sql = "SELECT id, tipo, descripcion, c1, c2, c3, activo FROM cat_items_auditoria_aud";
    
    // Si se solicita, filtramos
    if ($solo_activos) {
        $sql .= " WHERE activo = 'S'";
    }
    
    $sql .= " ORDER BY tipo ASC, id ASC";

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