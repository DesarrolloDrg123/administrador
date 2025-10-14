<?php
session_start();
header('Content-Type: application/json');
require("../config/db.php");

$response = ['success' => false, 'message' => ''];

if (!isset($_SESSION['loggedin']) || !$_SESSION['loggedin']) {
    $response['message'] = 'Acceso denegado.';
    echo json_encode($response);
    exit();
}

$candidato_id = $_POST['id'] ?? null;
$nuevo_estatus = $_POST['nuevo_estatus'] ?? null;
$observaciones = $_POST['observaciones'] ?? '';
$usuario_accion = $_SESSION['nombre'];

if (empty($candidato_id) || empty($nuevo_estatus)) {
    $response['message'] = 'Faltan datos para la actualización.';
    echo json_encode($response);
    exit();
}

$conn->begin_transaction();

try {
    // 1. Obtener el estatus actual del candidato
    $stmt_actual = $conn->prepare("SELECT estatus FROM solicitudes_vacantes_candidatos WHERE candidato_id = ?");
    $stmt_actual->bind_param("i", $candidato_id);
    $stmt_actual->execute();
    $result = $stmt_actual->get_result()->fetch_assoc();
    
    if (!$result) {
        throw new Exception("El candidato no existe.");
    }
    $estatus_anterior = $result['estatus'];

    // 2. Actualizar el estatus en la tabla principal de candidatos
    $stmt_update = $conn->prepare("UPDATE solicitudes_vacantes_candidatos SET estatus = ? WHERE candidato_id = ?");
    $stmt_update->bind_param("si", $nuevo_estatus, $candidato_id);
    $stmt_update->execute();

    // 3. Insertar el cambio en la nueva tabla de historial de candidatos
    $stmt_historial = $conn->prepare(
        "INSERT INTO solicitudes_vacantes_candidatos_historial (candidato_id, usuario_accion, fecha_accion, estatus_anterior, estatus_nuevo, comentarios) 
         VALUES (?, ?, NOW(), ?, ?, ?)"
    );
    $stmt_historial->bind_param("issss", $candidato_id, $usuario_accion, $estatus_anterior, $nuevo_estatus, $observaciones);
    $stmt_historial->execute();

    $conn->commit();
    
    $response['success'] = true;
    $response['message'] = "El estatus del candidato ha sido actualizado a '$nuevo_estatus'.";

} catch (Exception $e) {
    $conn->rollback();
    $response['message'] = "Error al actualizar: " . $e->getMessage();
}

$conn->close();

echo json_encode($response);
?>