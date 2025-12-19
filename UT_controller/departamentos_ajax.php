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
    // --- CASO: AGREGAR UN NUEVO PUESTO ---
    case 'agregar':
        $puesto = trim($_POST['puesto'] ?? '');
        if (!empty($puesto)) {
            $sql = "INSERT INTO puestos (puesto) VALUES (?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("s", $puesto);
            if ($stmt->execute()) {
                $response = ['success' => true, 'message' => 'Puesto agregado correctamente.'];
            } else {
                $response['message'] = 'Error al guardar el puesto.';
            }
        } else {
            $response['message'] = 'El nombre del puesto no puede estar vacío.';
        }
        break;

    // --- CASO: EDITAR UN PUESTO EXISTENTE ---
    case 'editar':
        $id = intval($_POST['id'] ?? 0);
        $puesto = trim($_POST['puesto'] ?? '');
        if ($id > 0 && !empty($puesto)) {
            $sql = "UPDATE puestos SET puesto = ? WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("si", $puesto, $id);
            if ($stmt->execute()) {
                $response = ['success' => true, 'message' => 'Puesto actualizado correctamente.'];
            } else {
                $response['message'] = 'Error al actualizar el puesto.';
            }
        } else {
            $response['message'] = 'Datos inválidos para la actualización.';
        }
        break;

    // --- CASO: ELIMINAR UN PUESTO ---
    case 'eliminar':
        $id = intval($_POST['id'] ?? 0);
        if ($id > 0) {
            $sql = "DELETE FROM puestos WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $id);
            if ($stmt->execute()) {
                $response = ['success' => true, 'message' => 'El puesto ha sido eliminado.'];
            } else {
                // Manejar error de llave foránea (si el puesto está en uso)
                $response['message'] = ($conn->errno == 1451) 
                    ? 'Error: No se puede eliminar porque está asignado a usuarios.' 
                    : 'Error al eliminar el puesto.';
            }
        } else {
            $response['message'] = 'ID de puesto no válido.';
        }
        break;
}

// --- Devolver la respuesta final ---
echo json_encode($response);
$conn->close();
?>