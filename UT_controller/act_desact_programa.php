<?php
require("../config/db.php"); // Conexi贸n a la base de datos

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $id_programa = $_POST['id_programa'];
    $estatus = $_POST['estatus'];

    // Verificar si ya existe el permiso
    $sqlCheck = "SELECT * FROM programas WHERE id = ?";
    $stmtCheck = $conn->prepare($sqlCheck);
    $stmtCheck->bind_param("i", $id_programa);
    $stmtCheck->execute();
    $result = $stmtCheck->get_result();

    if ($result->num_rows > 0) {
        // Si existe, actualizar el estatus
        $sqlUpdate = "UPDATE programas SET estatus = ? WHERE id = ?";
        $stmtUpdate = $conn->prepare($sqlUpdate);
        $stmtUpdate->bind_param("ii", $estatus, $id_programa);
        $stmtUpdate->execute();
        echo "Permiso actualizado correctamente.";
    }
}
?>
