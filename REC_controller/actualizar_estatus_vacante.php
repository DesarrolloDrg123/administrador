<?php
session_start();
header('Content-Type: application/json');

require("../config/db.php");

$response = ['success' => false, 'message' => ''];

// --- Seguridad y Validaciones ---
if (!isset($_SESSION['loggedin']) || !$_SESSION['loggedin']) {
    $response['message'] = 'Acceso denegado. Inicia sesión.';
    echo json_encode($response);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['message'] = 'Método no permitido.';
    echo json_encode($response);
    exit();
}

$solicitud_id = $_POST['id'] ?? null;
$nuevo_estatus = $_POST['nuevo_estatus'] ?? null;
$observaciones = $_POST['observaciones'] ?? '';
$usuario_accion = $_SESSION['nombre'];
$preguntas = $_POST['preguntas'] ?? [];

if (empty($solicitud_id) || empty($nuevo_estatus)) {
    $response['message'] = 'Faltan datos para actualizar el estatus.';
    echo json_encode($response);
    exit();
}

$conn->begin_transaction();

try {
    // 1. Obtener el estatus actual y el folio para el historial
    $stmt_actual = $conn->prepare("SELECT estatus, folio FROM solicitudes_vacantes WHERE solicitud_id = ?");
    $stmt_actual->bind_param("i", $solicitud_id);
    $stmt_actual->execute();
    $result = $stmt_actual->get_result()->fetch_assoc();
    
    if (!$result) {
        throw new Exception("La solicitud no existe.");
    }
    $estatus_anterior = $result['estatus'];
    $folio_solicitud = $result['folio'];

    // 2. Actualizar el estatus en la tabla principal
    $stmt_update = $conn->prepare("UPDATE solicitudes_vacantes SET estatus = ? WHERE solicitud_id = ?");
    $stmt_update->bind_param("si", $nuevo_estatus, $solicitud_id);
    $stmt_update->execute();

    // 3. Insertar el cambio en la tabla de historial
    $stmt_historial = $conn->prepare(
        "INSERT INTO solicitudes_vacantes_historial (solicitud_id, folio_solicitud, usuario_accion, fecha_accion, estatus_anterior, estatus_nuevo, comentarios) 
         VALUES (?, ?, ?, NOW(), ?, ?, ?)"
    );
    $stmt_historial->bind_param("isssss", $solicitud_id, $folio_solicitud, $usuario_accion, $estatus_anterior, $nuevo_estatus, $observaciones);
    $stmt_historial->execute();
    
    // 4. Insertar las preguntas dinamicas
    if ($nuevo_estatus === 'Publicada' && !empty($preguntas)) {
        // Preparamos la consulta para insertar las preguntas
        $sql_preguntas = "INSERT INTO solicitudes_vacantes_preguntas (solicitud_id, pregunta_texto) VALUES (?, ?)";
        $stmt_preguntas = $conn->prepare($sql_preguntas);

        foreach ($preguntas as $pregunta_texto) {
            // Solo insertamos preguntas que no estén vacías
            if (!empty(trim($pregunta_texto))) {
                $stmt_preguntas->bind_param("is", $solicitud_id, $pregunta_texto);
                $stmt_preguntas->execute();
            }
        }
        $stmt_preguntas->close();
    }

    // Si todo funciona, confirma los cambios
    $conn->commit();
    
    $response['success'] = true;
    $response['message'] = "La solicitud #$folio_solicitud ha sido actualizada a '$nuevo_estatus'.";

} catch (Exception $e) {
    $conn->rollback();
    $response['message'] = "Error al actualizar: " . $e->getMessage();
}

$conn->close();

echo json_encode($response);
?>