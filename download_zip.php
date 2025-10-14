<?php

if (isset($_GET['RFC']) && isset($_GET['UUID'])) {
    $rfc = $_GET['RFC'];
    $uuid = $_GET['UUID'];
    $path = "facturas/$rfc/";

    // Obtener todos los archivos que coincidan con el UUID
    $files = glob($path . $uuid . "*");

    if (empty($files)) {
        echo "<script>alert('No se encontraron archivos que coincidan con el UUID proporcionado.'); window.history.back();</script>";
        exit;
    }

    $zip = new ZipArchive();
    $zipFile = sys_get_temp_dir() . '/' . $uuid . '.zip';

    // Borrar cualquier archivo existente con el mismo nombre
    if (file_exists($zipFile)) {
        unlink($zipFile);
    }

    if ($zip->open($zipFile, ZipArchive::CREATE) !== TRUE) {
        echo "<script>alert('Error creando archivo ZIP'); window.history.back();</script>";
        exit;
    }

    // Añadir todos los archivos que coincidan con el UUID al ZIP
    foreach ($files as $file) {
        $zip->addFile($file, basename($file));
    }

    // Cerrar el archivo ZIP después de agregar todos los archivos
    $zip->close();

    // Verificar que el archivo ZIP se creó correctamente
    if (!file_exists($zipFile)) {
        echo "<script>alert('Error: el archivo ZIP no se creó correctamente.'); window.history.back();</script>";
        exit;
    }

    // Limpiar cualquier salida previa
    if (ob_get_length()) {
        ob_end_clean();
    }
    
    flush();

    // Establecer las cabeceras para la descarga del archivo ZIP
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . $uuid . '.zip"');
    header('Content-Length: ' . filesize($zipFile));

    // Leer el archivo y enviarlo al navegador
    readfile($zipFile);

    // Eliminar el archivo ZIP temporal después de enviarlo
    unlink($zipFile);
    exit;
} else {
    echo "<script>alert('No se proporcionaron RFC y UUID.'); window.history.back();</script>";
    exit;
}
