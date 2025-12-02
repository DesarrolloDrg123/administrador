<?php
require("../config/db.php");

header('Content-Type: application/json');

try {
    $stmt = $conn->prepare("SELECT id, sucursal FROM sucursales ORDER BY sucursal ASC");
    $stmt->execute();
    $result = $stmt->get_result();

    $sucursales = [];
    while ($row = $result->fetch_assoc()) {
        $sucursales[] = $row;
    }

    echo json_encode($sucursales);
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>
