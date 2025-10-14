<?php
header('Content-Type: application/json');
require("../config/db.php"); // Ajusta la ruta si es necesario

$response = ['success' => false, 'preguntas' => []];

// Validar que se recibió un ID
$solicitud_id = $_GET['id'] ?? 0;
if (!is_numeric($solicitud_id) || $solicitud_id <= 0) {
    echo json_encode($response);
    exit();
}

try {
    // Consulta segura para obtener las preguntas de la vacante específica
    $sql = "SELECT pregunta_id, pregunta_texto 
            FROM solicitudes_vacantes_preguntas 
            WHERE solicitud_id = ? 
            ORDER BY pregunta_id ASC";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $solicitud_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $preguntas = $result->fetch_all(MYSQLI_ASSOC);

    // Si se encontraron preguntas, la respuesta es exitosa
    if (!empty($preguntas)) {
        $response['success'] = true;
        $response['preguntas'] = $preguntas;
    }
    
    $stmt->close();
    $conn->close();

} catch (Exception $e) {
    // No se envía mensaje de error al público, solo se registra
    error_log("Error al obtener preguntas: " . $e->getMessage());
}

echo json_encode($response);
?>