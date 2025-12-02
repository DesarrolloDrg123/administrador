<?php
require("../config/db.php");

header('Content-Type: application/json');

try {
    $programa = 17;
    $acceso = 1;
    $stmt = $conn->prepare("SELECT u.id, u.nombre FROM usuarios u INNER JOIN permisos p ON u.id = p.id_usuarioWHERE p.id_programa = ? 
    AND p.acceso = ? ORDER BY u.nombre ASC ");
    $stmt->bind_param('ii', $programa, $acceso);
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
