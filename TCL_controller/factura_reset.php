<?php
require_once '../config/db.php'; // Asegúrate de tener conexión aquí

$uuid = $_POST['UUID'] ?? '';
$descripcion = $_POST['descripcion'] ?? '';

if (empty($uuid) || empty($descripcion)) {
    echo "missing_data";
    exit;
}

try {
    // Resetear en control_facturas
    $sql = "UPDATE control_facturas_tcl SET 
            reseted = 1,
            descripcion = ?
            WHERE nombre = ?";

    $stmt = $conn->prepare($sql);
    if ($stmt->execute([$descripcion, $uuid])) {
        // Eliminar de facturas
        $sqlDel = "DELETE FROM facturas WHERE UUID = ?";
        $stmtDel = $conn->prepare($sqlDel);
        $stmtDel->execute([$uuid]);

        echo "success";
        exit;
    } else {
        echo "fail";
        exit;
    }
} catch (Exception $e) {
    echo "error";
    exit;
}
?>
