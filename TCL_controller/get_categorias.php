<?php
require("../config/db.php");

header('Content-Type: application/json');

try {
    $stmt = $conn->prepare("SELECT id, categoria FROM categorias ORDER BY categoria ASC");
    $stmt->execute();
    $result = $stmt->get_result();

    $categorias = [];
    while ($row = $result->fetch_assoc()) {
        $categorias[] = $row;
    }

    echo json_encode($categorias);
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>
