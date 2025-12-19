<?php
session_start();
require("../config/db.php");

// --- Preparamos la respuesta en formato JSON ---
header('Content-Type: application/json');
$response = ['success' => false, 'message' => 'Acción no reconocida.'];

// --- Validación de sesión ---
if (!isset($_SESSION['loggedin']) || !$_SESSION['loggedin']) {
    $response['message'] = 'Error: Sesión no iniciada.';
    echo json_encode($response);
    exit();
}

// --- Determinar la acción a realizar ---
$accion = $_POST['accion'] ?? '';

switch ($accion) {
    // --- CASO: AGREGAR UN NUEVO DEPARTAMENTO ---
    case 'agregar':
        $departamento = trim($_POST['departamento'] ?? '');
        if (!empty($departamento)) {
            $sql = "INSERT INTO departamentos (departamento) VALUES (?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("s", $departamento);
            if ($stmt->execute()) {
                $response = ['success' => true, 'message' => 'Departamento agregado correctamente.'];
            } else {
                $response['message'] = 'Error al guardar el departamento.';
            }
        } else {
            $response['message'] = 'El nombre del departamento no puede estar vacío.';
        }
        break;

    // --- CASO: EDITAR UN DEPARTAMENTO EXISTENTE ---
    case 'editar':
        $id = intval($_POST['id'] ?? 0);
        $departamento = trim($_POST['departamento'] ?? '');
        if ($id > 0 && !empty($departamento)) {
            $sql = "UPDATE departamentos SET departamento = ? WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("si", $departamento, $id);
            if ($stmt->execute()) {
                $response = ['success' => true, 'message' => 'Departamento actualizado correctamente.'];
            } else {
                $response['message'] = 'Error al actualizar el departamento.';
            }
        } else {
            $response['message'] = 'Datos inválidos para la actualización.';
        }
        break;

    // --- CASO: ELIMINAR UN DEPARTAMENTO ---
    case 'eliminar':
        $id = intval($_POST['id'] ?? 0);
        if ($id > 0) {
            $sql = "DELETE FROM departamentos WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $id);
            if ($stmt->execute()) {
                $response = ['success' => true, 'message' => 'El departamento ha sido eliminado.'];
            } else {
                // Manejar error de llave foránea (si el departamento está en uso)
                $response['message'] = ($conn->errno == 1451) 
                    ? 'Error: No se puede eliminar porque está asignado a usuarios.' 
                    : 'Error al eliminar el departamento.';
            }
        } else {
            $response['message'] = 'ID de departamento no válido.';
        }
        break;
}

// --- Devolver la respuesta final ---
echo json_encode($response);
$conn->close();
?>