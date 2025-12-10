<?php

if (isset($_POST['submit_comprobantes'])) {

    require_once(__DIR__ . "/../config/db.php");

    $folio = $_POST['folio_solicitud'] ?? '';

    if (empty($folio)) {
        $GLOBALS["mensaje_global"] = '<div class="alert alert-danger">Error: Folio vacío.</div>';
        return;
    }

    if (!isset($_FILES['archivo_comprobante'])) {
        $GLOBALS["mensaje_global"] = '<div class="alert alert-danger">No se recibieron archivos.</div>';
        return;
    }

    if (!isset($_POST['tipo_comprobante']) || 
        !isset($_POST['importe_comprobante'])) {

        $GLOBALS["mensaje_global"] = '<div class="alert alert-danger">Error: Faltan datos.</div>';
        return;
    }

    // === Datos
    $tipo    = trim($_POST['tipo_comprobante']);
    $importe = floatval($_POST['importe_comprobante']);
    $archivos = $_FILES['archivo_comprobante'];

    if (empty($tipo) || $importe <= 0) {
        $GLOBALS["mensaje_global"] = '<div class="alert alert-danger">Tipo o importe inválidos.</div>';
        return;
    }

    $target_dir = "uploads/comprobantes/" . $folio . "/";
    if (!is_dir($target_dir)) {
        mkdir($target_dir, 0777, true);
    }

    // === Crear ZIP
    $zip_name = "comprobantes_" . $folio . "_" . time() . ".zip";
    $zip_path = $target_dir . $zip_name;

    $zip = new ZipArchive();
    if ($zip->open($zip_path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== TRUE) {
        $GLOBALS["mensaje_global"] = '<div class="alert alert-danger">No se pudo crear el ZIP.</div>';
        return;
    }

    foreach ($archivos['tmp_name'] as $key => $tmp_name) {
        if ($archivos['error'][$key] === UPLOAD_ERR_OK) {
            $original_name = basename($archivos['name'][$key]);
            $zip->addFile($tmp_name, $original_name);
        }
    }

    $zip->close();

    // === Guardar en BD la ruta del ZIP
    $sql = "INSERT INTO comprobantes_tcl 
            (folio, tipo_comprobante, importe, evidencia, fecha) 
            VALUES (?, ?, ?, ?, NOW())";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssds", $folio, $tipo, $importe, $zip_path);

    if (!$stmt->execute()) {
        error_log("Error al insertar comprobante ZIP: " . $stmt->error);
    }

    $stmt->close();

    $comprobantes_exito = true;
}
?>
