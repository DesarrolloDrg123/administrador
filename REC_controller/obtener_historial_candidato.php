<?php
session_start();
header('Content-Type: application/json');
require("../config/db.php");

// El JavaScript espera la clave 'historial', no 'data'
$response = ['success' => false, 'historial' => []]; 

if (!isset($_SESSION['loggedin']) || !$_SESSION['loggedin']) {
    $response['message'] = 'Acceso denegado.';
    echo json_encode($response);
    exit();
}

// === CAMBIO 1: La variable que recibes es el ID del candidato, no de la solicitud ===
$candidato_id = $_GET['id'] ?? 0;
if (!is_numeric($candidato_id) || $candidato_id <= 0) {
    $response['message'] = 'ID de candidato no válido.';
    echo json_encode($response);
    exit();
}

try {
    // Se consulta la tabla correcta: 'candidatos_historial'
    $sql = "SELECT * FROM solicitudes_vacantes_candidatos_historial 
            WHERE candidato_id = ? 
            ORDER BY fecha_accion DESC";

    $stmt = $conn->prepare($sql);
    
    // === CAMBIO 2: Usa la variable correcta en el bind_param ===
    $stmt->bind_param("i", $candidato_id);
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    $historial = $result->fetch_all(MYSQLI_ASSOC);

    $response['success'] = true;
    $response['historial'] = $historial; // Se usa la clave 'historial'
    
    $stmt->close();

} catch (Exception $e) {
    error_log("Error al obtener historial de candidato: " . $e->getMessage());
    $response['message'] = "Error al consultar la base de datos.";
}

$conn->close();
echo json_encode($response);
?>