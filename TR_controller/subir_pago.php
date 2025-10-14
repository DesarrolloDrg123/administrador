<?php
session_start();
if (!isset($_SESSION['loggedin']) || !$_SESSION['loggedin']) {
    header("Location: ../index.php");
    exit();
}

require("../config/db.php");

if (!isset($_GET['id'])) {
    die('ID de transferencia no especificado.');
}

$transferencia_id = $_GET['id'];

$conn->begin_transaction();
try {
    // Actualizar el estado de la transferencia a 'Subido a Pago'
    $sql = "UPDATE transferencias SET estado = 'Subido a Pago' WHERE folio = ?";
    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        throw new Exception($conn->error);
    }
    $stmt->bind_param("i", $transferencia_id);
    $stmt->execute();

    if ($stmt->affected_rows > 0) {
        $_SESSION['alert_message'] = "Estado de la transferencia actualizado a 'Subido a Pago'.";
        $_SESSION['alert_type'] = "success";
    } else {
        $_SESSION['alert_message'] = "No se pudo actualizar el estado de la transferencia.";
        $_SESSION['alert_type'] = "danger";
    }

    $conn->commit();
} catch (Exception $e) {
    $conn->rollback();
    $_SESSION['alert_message'] = "Error al procesar la solicitud: " . $e->getMessage();
    $_SESSION['alert_type'] = "danger";
}

header("Location: ../TR_pendiente_pago.php");
exit();
?>
