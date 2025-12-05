<?php
// Incluye tu conexión a la base de datos (Ejemplo: $conn)
require("config/db.php"); 

// 1. Validar el ID del comprobante
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    http_response_code(400); // Bad Request
    die("ID de comprobante no proporcionado o inválido.");
}

$comprobante_id = $_GET['id'];

try {
    // 2. Consultar la ruta del archivo en la base de datos
    $sql = "SELECT evidencia FROM comprobantes_tcl WHERE id = ?";
    
    // Asumimos que $conn es tu objeto de conexión mysqli
    $stmt = $conn->prepare($sql); 

    if ($stmt === false) {
        throw new Exception("Error de preparación de la consulta: " . $conn->error);
    }

    $stmt->bind_param('i', $comprobante_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $comprobante = $result->fetch_assoc();
    $stmt->close();
    
    if (!$comprobante || empty($comprobante['evidencia'])) {
        http_response_code(404); // Not Found
        die("Evidencia no encontrada en la base de datos.");
    }

    $file_path = $comprobante['evidencia'];

    // 3. Verificar si el archivo existe en el servidor
    if (!file_exists($file_path)) {
        http_response_code(404); // Not Found
        die("El archivo de evidencia no existe en el servidor: " . $file_path);
    }
    
    // 4. Determinar el tipo MIME (Content-Type)
    // Esto es crucial para que el navegador sepa cómo mostrar el archivo (PDF, JPG, PNG, etc.)
    $mime_type = mime_content_type($file_path);
    
    if ($mime_type === false) {
        $mime_type = 'application/octet-stream'; // Tipo genérico si no se puede determinar
    }

    // 5. Configurar encabezados HTTP y enviar el archivo

    // Limpiar cualquier salida anterior para evitar corrupción de archivos
    if (ob_get_level()) {
        ob_end_clean();
    }
    
    // Encabezados para el navegador
    header('Content-Type: ' . $mime_type);
    header('Content-Length: ' . filesize($file_path));
    
    // Muestra el archivo en línea (inline) o fuerza la descarga (attachment)
    // Usamos 'inline' para PDFs e imágenes para que se muestren en el navegador
    header('Content-Disposition: inline; filename="' . basename($file_path) . '"');
    header('Expires: 0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');

    // Enviar el contenido del archivo
    readfile($file_path);
    exit;

} catch (Exception $e) {
    // Registro de error interno
    error_log("Error al mostrar evidencia (ID: $comprobante_id): " . $e->getMessage());
    http_response_code(500); // Internal Server Error
    die("Error interno del servidor al procesar el archivo.");
}
?>