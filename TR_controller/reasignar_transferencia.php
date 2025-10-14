<?php
require("../config/db.php");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $folio = $_POST['folio'];
    $nuevo_autorizador = $_POST['nuevo_autorizador'];

    $sql = "UPDATE transferencias SET autorizacion_id = ? WHERE folio = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'ii', $nuevo_autorizador, $folio);

    if (mysqli_stmt_execute($stmt)) {
        // Redirigir con un mensaje de éxito
        header("Location: ../TR_re-asignar.php");
    } else {
        // Redirigir con un mensaje de error
        header("Location: ../TR_re-asignar.php?error=1");
    }

    mysqli_stmt_close($stmt);
}

mysqli_close($conn);
