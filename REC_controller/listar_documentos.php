<?php
session_start();
header('Content-Type: application/json');
require("../config/db.php");

$response = ['success' => false, 'archivos' => []];

if (!isset($_SESSION['loggedin']) || !$_SESSION['loggedin']) {
    $response['message'] = 'Acceso denegado.';
    echo json_encode($response);
    exit();
}

$candidato_id = $_GET['id'] ?? 0;
if (!is_numeric($candidato_id) || $candidato_id <= 0) {
    $response['message'] = 'ID de candidato no válido.';
    echo json_encode($response);
    exit();
}

try {
    // --- CAMBIO CLAVE: Ahora solo consultamos la tabla de documentos ---
    // Esta tabla es el registro maestro de todos los archivos del candidato.
    $stmt_docs = $conn->prepare("SELECT nombre_documento, ruta_documento FROM solicitudes_vacantes_candidatos_documentos WHERE candidato_id = ? ORDER BY fecha_carga DESC");
    $stmt_docs->bind_param("i", $candidato_id);
    $stmt_docs->execute();
    $result_docs = $stmt_docs->get_result();

    while ($row = $result_docs->fetch_assoc()) {
        $response['archivos'][] = [
            'nombre' => $row['nombre_documento'],
            'ruta' => $row['ruta_documento']
        ];
    }
    
    $response['success'] = true;

} catch (Exception $e) {
    $response['message'] = "Error al obtener los documentos.";
    error_log($e->getMessage());
}

$conn->close();
echo json_encode($response);
?>