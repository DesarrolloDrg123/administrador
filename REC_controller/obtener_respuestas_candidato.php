<?php
header('Content-Type: application/json');
require("../config/db.php");

$response = [
    'success' => false, 
    'detalles' => null,  // <-- Nuevo: para los datos del candidato
    'respuestas' => []   // Para las preguntas y respuestas
];

$candidato_id = $_GET['id'] ?? 0;
if (!is_numeric($candidato_id) || $candidato_id <= 0) {
    echo json_encode($response);
    exit();
}

try {
    $conn->begin_transaction();

    // --- NUEVO: Obtener los detalles completos del candidato ---
    $stmt_detalles = $conn->prepare("SELECT * FROM solicitudes_vacantes_candidatos WHERE candidato_id = ?");
    $stmt_detalles->bind_param("i", $candidato_id);
    $stmt_detalles->execute();
    $detalles_candidato = $stmt_detalles->get_result()->fetch_assoc();

    if (!$detalles_candidato) {
        throw new Exception("Candidato no encontrado.");
    }
    $response['detalles'] = $detalles_candidato;
    $stmt_detalles->close();


    // --- Obtener las respuestas a preguntas específicas (código que ya tenías) ---
    $sql_respuestas = "SELECT vp.pregunta_texto, cr.respuesta_texto
                       FROM solicitudes_vacantes_candidatos_respuestas cr
                       JOIN solicitudes_vacantes_preguntas vp ON cr.pregunta_id = vp.pregunta_id
                       WHERE cr.candidato_id = ?
                       ORDER BY vp.pregunta_id ASC";
    
    $stmt_respuestas = $conn->prepare($sql_respuestas);
    $stmt_respuestas->bind_param("i", $candidato_id);
    $stmt_respuestas->execute();
    $respuestas = $stmt_respuestas->get_result()->fetch_all(MYSQLI_ASSOC);
    
    $response['respuestas'] = $respuestas;
    $stmt_respuestas->close();

    // Si todo fue exitoso
    $response['success'] = true;
    $conn->commit();

} catch (Exception $e) {
    $conn->rollback();
    error_log("Error al obtener detalles del candidato: " . $e->getMessage());
}

$conn->close();
echo json_encode($response);
?>