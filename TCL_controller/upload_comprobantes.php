<?php

if (isset($_POST['submit_comprobantes'])) {

    require_once("../config/db.php");

    if (!file_exists(__DIR__ . '/../config/db.php')) {
        die('No se encontró db.php');
    }

    $folio = $_POST['folio_solicitud'] ?? '';

    if (empty($folio)) {
        $GLOBALS["mensaje_global"] = '<div class="alert alert-danger">Error: Folio vacío.</div>';
        return;
    }

    // Validar que existan los arrays
    if (!isset($_POST['tipo_comprobante']) || !isset($_POST['importe_comprobante'])) {
        $GLOBALS["mensaje_global"] = '<div class="alert alert-danger">Error: No se recibieron comprobantes.</div>';
        return;
    }

    $tipos   = $_POST['tipo_comprobante'];
    $importes = $_POST['importe_comprobante'];
    $archivos = $_FILES['archivo_comprobante'];

    $target_dir_base = "uploads/comprobantes/" . htmlspecialchars($folio) . "/";

    if (!is_dir($target_dir_base)) {
        mkdir($target_dir_base, 0777, true);
    }

    $total = count($importes);

    for ($i = 0; $i < $total; $i++) {

        $tipo   = trim($tipos[$i]);
        $importe = floatval($importes[$i]);

        // Validaciones
        if (empty($tipo) || $importe <= 0) {
            continue;
        }

        if (!isset($archivos['name'][$i]) || $archivos['error'][$i] != UPLOAD_ERR_OK) {
            continue;
        }

        $nombre_original = $archivos['name'][$i];
        $tmp_name        = $archivos['tmp_name'][$i];

        $extension = pathinfo($nombre_original, PATHINFO_EXTENSION);
        $nombre_archivo = time() . "_" . uniqid() . "." . $extension;
        $ruta_final = $target_dir_base . $nombre_archivo;

        if (move_uploaded_file($tmp_name, $ruta_final)) {

            $sql = "INSERT INTO comprobantes_tcl (folio, tipo_comprobante, importe, evidencia) 
                    VALUES (?, ?, ?, ?)";

            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssds", $folio, $tipo, $importe, $ruta_final);
            $stmt->execute();
            $stmt->close();
        }
    }

    $GLOBALS["mensaje_global"] = '<div class="alert alert-success">Comprobantes cargados correctamente.</div>';
}
?>
