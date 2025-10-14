<?php
session_start();
header('Content-Type: application/json');
require("../config/db.php");

$response = ['success' => false, 'message' => ''];

if (!isset($_SESSION['loggedin']) || !$_SESSION['loggedin']) {
    $response['message'] = 'Acceso denegado.';
    echo json_encode($response);
    exit();
}

$candidato_id = $_POST['id'] ?? null;
$archivos = $_FILES['archivos'] ?? null;

if (empty($candidato_id) || empty($archivos)) {
    $response['message'] = 'No se recibió el ID del candidato o los archivos.';
    echo json_encode($response);
    exit();
}

$conn->begin_transaction();

try {
    // --- CAMBIO CLAVE: Obtener la ruta de la carpeta que ya existe ---
    $stmt_path = $conn->prepare("SELECT cv_adjunto_path FROM solicitudes_vacantes_candidatos WHERE candidato_id = ?");
    $stmt_path->bind_param("i", $candidato_id);
    $stmt_path->execute();
    $result_path = $stmt_path->get_result()->fetch_assoc();

    if (!$result_path || empty($result_path['cv_adjunto_path'])) {
        throw new Exception("No se encontró la carpeta (expediente) del candidato.");
    }
    // La variable ahora contiene la ruta de la carpeta, ej: 'CVs/juan_perez_1234/'
    $directorio_candidato = $result_path['cv_adjunto_path'];

    // Asegurarse de que la carpeta exista, si no, la crea
    if (!is_dir($directorio_candidato)) {
        mkdir($directorio_candidato, 0775, true);
    }

    // 2. Procesar y mover cada archivo subido
    $total_files = count($archivos['name']);
    for ($i = 0; $i < $total_files; $i++) {
        if ($archivos['error'][$i] === UPLOAD_ERR_OK) {
            $nombre_original = basename($archivos['name'][$i]);
            $ruta_destino = $directorio_candidato . $nombre_original;

            if (move_uploaded_file($archivos['tmp_name'][$i], $ruta_destino)) {
                // 3. Insertar el registro del documento en la tabla
                $stmt_insert = $conn->prepare("INSERT INTO solicitudes_vacantes_candidatos_documentos (candidato_id, nombre_documento, ruta_documento) VALUES (?, ?, ?)");
                $stmt_insert->bind_param("iss", $candidato_id, $nombre_original, $ruta_destino);
                $stmt_insert->execute();
                $stmt_insert->close();
            } else {
                throw new Exception("Error al mover el archivo: " . $nombre_original);
            }
        }
    }

    $conn->commit();
    $response['success'] = true;
    $response['message'] = 'Documentos subidos con éxito.';

} catch (Exception $e) {
    $conn->rollback();
    $response['message'] = $e->getMessage();
}

$conn->close();
echo json_encode($response);
?>