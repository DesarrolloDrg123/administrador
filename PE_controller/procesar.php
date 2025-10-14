<?php
session_start();
require("../config/db.php"); // Asegúrate de incluir tu conexión a la base de datos

if (isset($_GET['doc']) && isset($_GET['R']) && $_GET['R'] === 'true') {
    $id = intval($_GET['doc']); // Convierte a entero para mayor seguridad

    // Consulta para actualizar el estatus
    $sql = "UPDATE pedidos_especiales SET estatus = 'Recibido-Total'  WHERE id = ?";
    $stmt = $conn->prepare($sql);

    if ($stmt) {
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            // Redirigir con un mensaje de éxito
            header("Location: ../PE_mis_pedidos.php?status=success");
            exit();
        } else {
            // Registrar error si la ejecución falla
            error_log("Error al actualizar el estatus: " . $stmt->error);
            header("Location: ../PE_mis_pedidos.php?status=error");
            exit();
        }
    } else {
        // Registrar error si la preparación de la consulta falla
        error_log("Error al preparar la consulta: " . $conn->error);
        header("Location: ../PE_mis_pedidos.php?status=error");
        exit();
    }
} else if (isset($_GET['doc']) && isset($_GET['RP']) && $_GET['RP'] === 'true') {
    $id = intval($_GET['doc']); // Convierte a entero para mayor seguridad

    // Consulta para actualizar el estatus parcial
    $sql = "UPDATE pedidos_especiales SET estatus = 'Recibido-Parcial'  WHERE id = ?";
    $stmt = $conn->prepare($sql);

    if ($stmt) {
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            // Redirigir con un mensaje de éxito
            header("Location: ../PE_mis_pedidos.php?status=success");
            exit();
        } else {
            // Registrar error si la ejecución falla
            error_log("Error al actualizar el estatus parcial: " . $stmt->error);
            header("Location: ../PE_mis_pedidos.php?status=error");
            exit();
        }
    } else {
        // Registrar error si la preparación de la consulta falla
        error_log("Error al preparar la consulta: " . $conn->error);
        header("Location: ../PE_mis_pedidos.php?status=error");
        exit();
    }
} else {
    // Si no se envían los parámetros requeridos, redirige
    header("Location: ../PE_mis_pedidos.php?status=error");
    exit();
}
?>
