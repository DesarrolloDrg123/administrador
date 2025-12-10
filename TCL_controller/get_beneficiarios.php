<?php
require("../config/db.php");

header('Content-Type: application/json');

try {
    $stmt = $conn->prepare("SELECT id, nombre, tarjeta_clara FROM usuarios WHERE estatus = '1' ORDER BY nombre ASC");
    $stmt->execute();
    $result = $stmt->get_result();

    $usuarios = [];
    while ($row = $result->fetch_assoc()) {
        $usuarios[] = $row;
    }

    echo json_encode($usuarios);
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>
