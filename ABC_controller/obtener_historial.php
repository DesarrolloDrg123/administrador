<?php
session_start();
require("../config/db.php");

// Preparar la respuesta en formato JSON
header('Content-Type: application/json');

// Validar que se haya recibido un ID numérico
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    http_response_code(400); // Bad Request
    echo json_encode(['success' => false, 'message' => 'ID de solicitud no válido o faltante.']);
    exit;
}

$solicitud_id = intval($_GET['id']);
$historial = [];
$response = ['success' => false, 'data' => []];

try {
    // Consulta preparada para buscar de forma segura todos los registros del historial
    $stmt = $conn->prepare(
        "SELECT usuario_nombre, estatus_cambio, observacion, fecha_hora 
         FROM solicitudes_movimientos_personal_historial 
         WHERE solicitud_id = ? 
         ORDER BY fecha_hora DESC" // Ordenar del más reciente al más antiguo
    );
    
    if ($stmt) {
        $stmt->bind_param("i", $solicitud_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            // Limpiar los datos antes de enviarlos para evitar problemas
            foreach ($row as $key => $value) {
                $row[$key] = htmlspecialchars($value ?? '');
            }
            $historial[] = $row;
        }
        
        $response['success'] = true;
        $response['data'] = $historial;
        
        $stmt->close();
    } else {
        throw new Exception("Error al preparar la consulta a la base de datos.");
    }

} catch (Exception $e) {
    http_response_code(500); // Internal Server Error
    $response['message'] = 'Error en el servidor: ' . $e->getMessage();
}

$conn->close();

// Enviar la respuesta final en formato JSON
echo json_encode($response);
?>

