<?php
require_once __DIR__ . '/../config/db.php';

if (!isset($_GET['id'])) {
    die("ID no proporcionado.");
}

$id = (int)$_GET['id'];

$sql = "SELECT evidencia FROM comprobantes_tcl WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$data = $result->fetch_assoc();

if (!$data || empty($data['evidencia'])) {
    die("Archivo no encontrado.");
}

$ruta = $data['evidencia'];

if (!file_exists($ruta)) {
    die("El archivo ya no existe en el servidor.");
}

// Forzar descarga del ZIP
header('Content-Description: File Transfer');
header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="' . basename($ruta) . '"');
header('Content-Length: ' . filesize($ruta));
readfile($ruta);
exit;
?>
