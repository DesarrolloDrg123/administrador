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
    // --- CASO: AGREGAR UNA NUEVA SUCURSAL ---
    case 'agregar':
        $sucursal = trim($_POST['sucursal'] ?? '');
        if (!empty($sucursal)) {
            $sql = "INSERT INTO sucursales (sucursal) VALUES (?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("s", $sucursal);
            if ($stmt->execute()) {
                $response = ['success' => true, 'message' => 'Sucursal agregada correctamente.'];
            } else {
                $response['message'] = 'Error al guardar la sucursal.';
            }
        } else {
            $response['message'] = 'El nombre de la sucursal no puede estar vacío.';
        }
        break;

    // --- CASO: EDITAR UNA SUCURSAL EXISTENTE ---
    case 'editar':
        $id = intval($_POST['id'] ?? 0);
        $sucursal = trim($_POST['sucursal'] ?? '');
        if ($id > 0 && !empty($sucursal)) {
            $sql = "UPDATE sucursales SET sucursal = ? WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("si", $sucursal, $id);
            if ($stmt->execute()) {
                $response = ['success' => true, 'message' => 'Sucursal actualizada correctamente.'];
            } else {
                $response['message'] = 'Error al actualizar la sucursal.';
            }
        } else {
            $response['message'] = 'Datos inválidos para la actualización.';
        }
        break;

    // --- CASO: ELIMINAR UNA SUCURSAL ---
    case 'eliminar':
        $id = intval($_POST['id'] ?? 0);
        if ($id > 0) {
            $sql = "DELETE FROM sucursales WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $id);
            if ($stmt->execute()) {
                $response = ['success' => true, 'message' => 'La sucursal ha sido eliminada.'];
            } else {
                // Manejar error de llave foránea (si la sucursal está en uso)
                $response['message'] = ($conn->errno == 1451) 
                    ? 'Error: No se puede eliminar porque está asignada a usuarios o registros.' 
                    : 'Error al eliminar la sucursal.';
            }
        } else {
            $response['message'] = 'ID de sucursal no válido.';
        }
        break;
}

// --- Devolver la respuesta final ---
echo json_encode($response);
$conn->close();
?>