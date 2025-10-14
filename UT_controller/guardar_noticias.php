<?php
session_start();
require("../config/db.php");

header('Content-Type: application/json');

function enviarRespuesta($success, $message) {
    echo json_encode(['success' => $success, 'message' => $message]);
    exit();
}

if (!isset($_SESSION['loggedin']) || !$_SESSION['loggedin']) {
    enviarRespuesta(false, 'Acceso no autorizado.');
}

$noticias_post = $_POST['noticias'] ?? [];

if (empty($noticias_post)) {
    enviarRespuesta(false, 'No se recibieron datos de noticias.');
}

// === INICIO DE LA CORRECCIÓN ===
// 1. Reestructurar el array $_FILES para que sea más fácil de usar.
$archivos_reestructurados = [];
if (!empty($_FILES['noticias'])) {
    foreach ($_FILES['noticias'] as $propiedad => $items) { // $propiedad es 'name', 'type', etc.
        foreach ($items as $key => $sub_items) { // $key es 0, 1, 2
            foreach ($sub_items as $input_name => $valor) { // $input_name es 'imagen'
                $archivos_reestructurados[$key][$input_name][$propiedad] = $valor;
            }
        }
    }
}

$conn->begin_transaction();
try {
    $sql = "INSERT INTO noticias_inicio (id, titulo, descripcion, ruta_imagen) VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE 
                titulo = VALUES(titulo), 
                descripcion = VALUES(descripcion), 
                ruta_imagen = VALUES(ruta_imagen)";
    
    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        throw new Exception('Error al preparar la consulta: ' . $conn->error);
    }
    
    foreach ($noticias_post as $key => $noticia) {
        $id = intval($noticia['id']);
        $titulo = trim($noticia['titulo']);
        $descripcion = trim($noticia['descripcion']);

        if (empty($titulo)) {
            continue; // Si no hay título, saltamos esta noticia.
        }

        // Usamos nuestro array reestructurado para verificar la subida.
        $archivo_subido = $archivos_reestructurados[$key]['imagen'] ?? null;
        $se_subio_imagen_nueva = ($archivo_subido && $archivo_subido['error'] === UPLOAD_ERR_OK);

        // Obtenemos la ruta de la imagen actual para usarla si no se sube una nueva.
        $stmt_get_img = $conn->prepare("SELECT ruta_imagen FROM noticias_inicio WHERE id = ?");
        $stmt_get_img->bind_param('i', $id);
        $stmt_get_img->execute();
        $ruta_imagen_actual = $stmt_get_img->get_result()->fetch_assoc()['ruta_imagen'] ?? null;
        $stmt_get_img->close();

        $ruta_imagen_final = $ruta_imagen_actual; // Por defecto, mantenemos la imagen actual.

        if ($se_subio_imagen_nueva) {
            // Define una ruta más robusta usando el DOCUMENT_ROOT
            $upload_dir_base = $_SERVER['DOCUMENT_ROOT'] . '/uploads/noticias/';
            $ruta_relativa_db = 'uploads/noticias/'; // La ruta que se guarda en la DB

            if (!is_dir($upload_dir_base)) {
                mkdir($upload_dir_base, 0775, true);
            }
            
            // Borramos la imagen antigua si existe
            if ($ruta_imagen_actual && file_exists($_SERVER['DOCUMENT_ROOT'] . '/' . $ruta_imagen_actual)) {
                unlink($_SERVER['DOCUMENT_ROOT'] . '/' . $ruta_imagen_actual);
            }
            
            $nombre_archivo = time() . '_' . basename($archivo_subido['name']);
            $ruta_completa_servidor = $upload_dir_base . $nombre_archivo;

            if (move_uploaded_file($archivo_subido['tmp_name'], $ruta_completa_servidor)) {
                $ruta_imagen_final = $ruta_relativa_db . $nombre_archivo;
            } else {
                throw new Exception("Error al mover la imagen para la Noticia #" . ($key + 1));
            }
        }

        // Ejecutamos la consulta con los datos finales
        $stmt->bind_param("isss", $id, $titulo, $descripcion, $ruta_imagen_final);
        $stmt->execute();
    }
    
    $stmt->close();
    $conn->commit();
    enviarRespuesta(true, '¡Noticias guardadas con éxito!');

} catch (Exception $e) {
    $conn->rollback();
    // Para depuración, puedes loggear el error completo: error_log($e->getMessage());
    enviarRespuesta(false, $e->getMessage());
}
?>