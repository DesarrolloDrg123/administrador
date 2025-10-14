<?php
session_start();
header('Content-Type: application/json');

// La ruta usa '../' para subir un nivel desde la carpeta REC_controller
require("../config/db.php"); 

$response = ['success' => false, 'data' => [], 'message' => ''];

// 1. Verificar que el usuario tenga sesión activa (seguridad)
if (!isset($_SESSION['loggedin']) || !$_SESSION['loggedin']) {
    $response['message'] = 'Acceso denegado. Por favor, inicie sesión.';
    echo json_encode($response);
    exit();
}

// 2. Validar que se recibió un ID válido
$solicitud_id = $_GET['id'] ?? 0;
if (!is_numeric($solicitud_id) || $solicitud_id <= 0) {
    $response['message'] = 'ID de solicitud no válido.';
    echo json_encode($response);
    exit();
}

try {
    // 3. Preparar la consulta para evitar inyección SQL
    // Se usa el nombre de tabla que definiste: 'solicitudes_vacantes_historial'
    $sql = "SELECT 
                fecha_accion, 
                usuario_accion, 
                estatus_nuevo, 
                comentarios 
            FROM solicitudes_vacantes_historial 
            WHERE solicitud_id = ? 
            ORDER BY fecha_accion DESC"; // DESC para mostrar lo más reciente primero

    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        throw new Exception("Error al preparar la consulta: " . $conn->error);
    }

    $stmt->bind_param("i", $solicitud_id);
    
    // 4. Ejecutar la consulta y obtener los resultados
    $stmt->execute();
    $result = $stmt->get_result();

    // 5. Guardar los datos en el array de respuesta
    $response['data'] = $result->fetch_all(MYSQLI_ASSOC);
    $response['success'] = true;

    $stmt->close();

} catch (Exception $e) {
    // En caso de un error en la base de datos
    $response['message'] = "Error al consultar la base de datos: " . $e->getMessage();
}

$conn->close();

// 6. Enviar la respuesta final en formato JSON
echo json_encode($response);
?>