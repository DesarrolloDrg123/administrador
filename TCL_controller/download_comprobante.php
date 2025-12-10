<?php
require_once __DIR__ . '/../config/db.php';

if (!isset($_GET['id'])) {
    die("ID no proporcionado.");
}

$id = (int)$_GET['id'];

// Obtener ruta de BD
$sql = "SELECT evidencia FROM comprobantes_tcl WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$data = $result->fetch_assoc();

if (!$data || empty($data['evidencia'])) {
    die("Archivo no encontrado en base de datos.");
}

// Ruta relativa guardada
$ruta_relativa = $data['evidencia'];

// Convertir a ruta absoluta del servidor
$base_path = realpath(__DIR__ . '/../'); 
$ruta_absoluta = $base_path . '/' . $ruta_relativa;

if (!file_exists($ruta_absoluta)) {
    die("El archivo no existe en el servidor.<br>Ruta buscada:<br>" . $ruta_absoluta);
}

if (ob_get_length()) {
    ob_end_clean();
}

// Forzar descarga
header('Content-Description: File Transfer');
header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="' . basename($ruta_absoluta) . '"');
header('Content-Length: ' . filesize($ruta_absoluta));
header('Pragma: public');
header('Cache-Control: must-revalidate');

readfile($ruta_absoluta);
exit;
?>
