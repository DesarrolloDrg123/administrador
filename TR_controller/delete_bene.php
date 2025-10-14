<?php
require("../config/db.php");

try {
    if (isset($_GET['id']) && is_numeric($_GET['id'])) {
        $id = intval($_GET['id']);

        // Primero, verificar si el beneficiario tiene registros asociados
        $checkSql = "SELECT COUNT(*) FROM transferencias WHERE beneficiario_id = ?";
        $checkStmt = $conn->prepare($checkSql);
        $checkStmt->bind_param("i", $id);
        $checkStmt->execute();
        $checkStmt->bind_result($recordCount);
        $checkStmt->fetch();
        $checkStmt->close();

        // Si tiene registros, no permitir eliminarlo
        if ($recordCount > 0) {
            throw new Exception("No es posible borrarlo, ya cuenta con registros.");
        }

        // Ejecutar la consulta de eliminación
        $sql = "DELETE FROM beneficiarios WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $id);

        if ($stmt->execute()) {
            // Redirigir de vuelta a la lista de beneficiarios con un mensaje de éxito
            header("Location: ../TR_beneficiarios.php?msg=deleted");
        } else {
            // Redirigir de vuelta con un mensaje de error
            header("Location: ../TR_beneficiarios.php?msg=error");
        }

        $stmt->close();
    } else {
        // Si el ID no es válido, redirigir con un mensaje de error
        header("Location: ../TR_beneficiarios.php?msg=invalid_id");
    }
} catch (Exception $e) {
    // Si ocurre una excepción (por ejemplo, el beneficiario tiene registros asociados)
    header("Location: ../TR_beneficiarios.php?msg=" . urlencode($e->getMessage()));
} finally {
    // Cerrar la conexión a la base de datos
    if (isset($conn)) {
        $conn->close();
    }
}
exit();
?>

