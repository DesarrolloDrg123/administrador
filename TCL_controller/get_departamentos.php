<?php
require("../config/db.php");

header('Content-Type: application/json');

try {
    $stmt = $conn->prepare("SELECT id, departamento FROM departamentos ORDER BY departamento ASC");
    $stmt->execute();
    $result = $stmt->get_result();

    $departamentos = [];
    while ($row = $result->fetch_assoc()) {
        $departamentos[] = $row;
    }

    echo json_encode($departamentos);
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>
