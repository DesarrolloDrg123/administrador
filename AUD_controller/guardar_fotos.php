<?php
require("../config/db.php");

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $auditoria_id = $_POST['auditoria_id'];
    
    // 1. Obtener el folio para nombrar la carpeta
    $stmt_folio = $conn->prepare("SELECT folio FROM auditorias_vehiculos_aud WHERE id = ?");
    $stmt_folio->bind_param("i", $auditoria_id);
    $stmt_folio->execute();
    $res_folio = $stmt_folio->get_result()->fetch_assoc();
    $folio = $res_folio['folio'];

    // 2. Definir y crear ruta de almacenamiento
    $directorio_destino = "/uploads/evidencias/" . $folio . "/";
    if (!file_exists($directorio_destino)) {
        mkdir($directorio_destino, 0777, true);
    }

    $errores = 0;
    $subidos = 0;

    // --- PROCESAR FOTOS ---
    if (!empty($_FILES['fotos']['name'][0])) {
        foreach ($_FILES['fotos']['tmp_name'] as $key => $tmp_name) {
            $nombre_original = $_FILES['fotos']['name'][$key];
            $extension = pathinfo($nombre_original, PATHINFO_EXTENSION);
            $nuevo_nombre = "FOTO_" . uniqid() . "." . $extension;
            $ruta_final = $directorio_destino . $nuevo_nombre;

            if (move_uploaded_file($tmp_name, $ruta_final)) {
                $stmt = $conn->prepare("INSERT INTO auditorias_evidencias_aud (auditoria_id, tipo_archivo, ruta_archivo, nombre_original) VALUES (?, 'Foto', ?, ?)");
                $ruta_db = "uploads/evidencias/" . $folio . "/" . $nuevo_nombre;
                $stmt->bind_param("iss", $auditoria_id, $ruta_db, $nombre_original);
                $stmt->execute();
                $subidos++;
            } else { $errores++; }
        }
    }

    // --- PROCESAR DOCUMENTOS ---
    if (!empty($_FILES['documentos']['name'][0])) {
        foreach ($_FILES['documentos']['tmp_name'] as $key => $tmp_name) {
            $nombre_original = $_FILES['documentos']['name'][$key];
            $extension = pathinfo($nombre_original, PATHINFO_EXTENSION);
            $nuevo_nombre = "DOC_" . uniqid() . "." . $extension;
            $ruta_final = $directorio_destino . $nuevo_nombre;

            if (move_uploaded_file($tmp_name, $ruta_final)) {
                $stmt = $conn->prepare("INSERT INTO auditorias_evidencias_aud (auditoria_id, tipo_archivo, ruta_archivo, nombre_original) VALUES (?, 'Documento', ?, ?)");
                $ruta_db = "uploads/evidencias/" . $folio . "/" . $nuevo_nombre;
                $stmt->bind_param("iss", $auditoria_id, $ruta_db, $nombre_original);
                $stmt->execute();
                $subidos++;
            } else { $errores++; }
        }
    }

    // 3. Registrar la fecha y hora de subida en la auditoría principal (como pide tu diagrama)
    $stmt_update = $conn->prepare("UPDATE auditorias_vehiculos_aud SET fecha_subida_evidencia = NOW(), token_evidencia = NULL WHERE id = ?");
    $stmt_update->bind_param("i", $auditoria_id);
    $stmt_update->execute();

    // Redireccionar con mensaje de éxito
    if ($errores == 0) {
        header("Location: ../AUD_subir_evidencia.php?status=success&folio=" . $folio);
    } else {
        header("Location: ../AUD_subir_evidencia.php?status=error");
    }
}