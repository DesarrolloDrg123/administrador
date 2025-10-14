<?php
$rfc = $_GET['RFC'];
$uuid = $_GET['UUID'];

// Construir la ruta del archivo PDF
$file_path = "facturas/$rfc/$uuid.pdf";

// Verificar si el archivo existe
if (file_exists($file_path)) {
    // Establecer encabezados para mostrar el PDF en el navegador
    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="' . basename($file_path) . '"');
    header('Content-Transfer-Encoding: binary');
    header('Accept-Ranges: bytes');

    // Leer el archivo y enviarlo al navegador
    @readfile($file_path);
    exit;
} else {
    echo "El archivo no existe.";
}
?>


