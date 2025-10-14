<?php
require('../config/db.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['guardar'])) {
    $periodo_id = intval($_POST['guardar']);
    $dias_disfrutados = isset($_POST['dias_disfrutados'][$periodo_id]) ? intval($_POST['dias_disfrutados'][$periodo_id]) : 0;

    // Actualizar los días disfrutados en la base de datos
    $sql_update = "UPDATE periodos SET dias_disfrutados = ? WHERE id = ?";
    if ($stmt = $conn->prepare($sql_update)) {
        $stmt->bind_param("ii", $dias_disfrutados, $periodo_id);
        if ($stmt->execute()) {
            // Redirigir con mensaje de éxito
            header("Location: ../VAC_modificar_periodos.php?estatus=success");
            exit;
        } else {
            // Redirigir con mensaje de error
            header("Location: ../VAC_modificar_periodos.php?estatus=error");
            exit;
        }
    } else {
        // Redirigir con mensaje de error si falla la consulta
        header("Location: ../VAC_modificar_periodos.php?estatus=error");
        exit;
    }
}
