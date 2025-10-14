<?php
session_start();
require("../config/db.php");

// Preparamos la respuesta para AJAX
$response = ['success' => false, 'message' => ''];
header('Content-Type: application/json');

// 1. Validaciones de seguridad
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    $response['message'] = 'Acceso no permitido.';
    echo json_encode($response);
    exit();
}
if (!isset($_SESSION['loggedin'], $_SESSION['nombre'])) {
    $response['message'] = 'Sesión no válida.';
    echo json_encode($response);
    exit();
}
if (empty($_POST['folio']) || empty($_POST['motivo'])) {
    $response['message'] = 'Faltan datos necesarios.';
    echo json_encode($response);
    exit();
}

// 2. Recopilar datos
$folio = $_POST['folio'];
$motivo = trim($_POST['motivo']);
$estatus_nuevo = 'Rechazada';
$usuario_actual = $_SESSION['nombre']; // El solicitante que está realizando la acción

// 3. Actualizar la base de datos de forma segura
try {
    // IMPORTANTE: Se añade "AND user_solicitante = ?" para asegurar que el usuario
    // solo pueda rechazar sus propias cotizaciones.
    $sql = "UPDATE datos_generales_co 
            SET estatus = ?, motivo = ? 
            WHERE folio = ? AND user_solicitante = ?";
            
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssss", $estatus_nuevo, $motivo, $folio, $usuario_actual);

    if ($stmt->execute()) {
        // Verificamos si alguna fila fue realmente actualizada
        if ($stmt->affected_rows > 0) {
            $response['success'] = true;
        } else {
            $response['message'] = "No se pudo actualizar la cotización. Es posible que no tengas permiso o el folio sea incorrecto.";
        }
    } else {
        $response['message'] = "Error al actualizar la base de datos.";
    }
    $stmt->close();
} catch (Exception $e) {
    $response['message'] = "Error de base de datos: " . $e->getMessage();
}

$conn->close();

// 4. Devolver la respuesta
echo json_encode($response);
?>