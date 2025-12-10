<?php
error_reporting(0); // Ocultar warnings en pantalla
ini_set('display_errors', 0);

require_once __DIR__ . '/../config/db.php';

if (!isset($_GET['id'])) {
    exit('ID no proporcionado.');
}

$id = (int)$_GET['id'];

// Consultar ruta
$sql = "SELECT evidencia FROM comprobantes_tcl WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$data = $result->fetch_assoc();

if (!$data || empty($data['evidencia'])) {
    exit('Archivo no encontrado en base de datos.');
}

// Limpiar salida previa
if (ob_get_length()) {
    ob_end_clean();
}

// Construir ruta absoluta
$ruta_relativa = $data['evidencia'];
$ruta_absoluta = realpath(__DIR__ . '/../' . $ruta_relativa);

if (!$ruta_absoluta || !file_exists($ruta_absoluta)) {
    exit('El archivo no existe en el servidor.');
}

// Forzar descarga
header('Content-Description: File Transfer');
header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="' . basename($ruta_absoluta) . '"');
header('Content-Transfer-Encoding: binary');
header('Content-Length: ' . filesize($ruta_absoluta));

flush();
readfile($ruta_absoluta);
exit;
?>
