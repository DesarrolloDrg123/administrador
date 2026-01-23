<?php
require("../config/db.php");

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $auditoria_id = (int)$_POST['auditoria_id'];
    $folio = $_POST['folio'];
    $upload_dir = "../uploads/evidencias/{$folio}/";

    // Crear directorio si no existe
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }

    $errores = 0;
    $subidos = 0;

    // Función para procesar archivos
    function procesarArchivos($files, $tipo, $dir, $aud_id, $conn) {
        $count = 0;
        foreach ($files['name'] as $key => $name) {
            if ($files['error'][$key] == 0) {
                $ext = pathinfo($name, PATHINFO_EXTENSION);
                $nuevo_nombre = $tipo . "_" . uniqid() . "." . $ext;
                $ruta_destino = $dir . $nuevo_nombre;

                if (move_uploaded_file($files['tmp_name'][$key], $ruta_destino)) {
                    // Guardar ruta en la base de datos
                    $stmt = $conn->prepare("INSERT INTO auditorias_evidencias_aud (auditoria_id, tipo_archivo, ruta_archivo) VALUES (?, ?, ?)");
                    $ruta_db = "uploads/evidencias/{$GLOBALS['folio']}/{$nuevo_nombre}";
                    $stmt->bind_param("iss", $aud_id, $tipo, $ruta_db);
                    $stmt->execute();
                    $count++;
                }
            }
        }
        return $count;
    }

    // Procesar Fotos
    if (!empty($_FILES['fotos']['name'][0])) {
        $subidos += procesarArchivos($_FILES['fotos'], 'foto', $upload_dir, $auditoria_id, $conn);
    }

    // Procesar Documentos
    if (!empty($_FILES['documentos']['name'][0])) {
        $subidos += procesarArchivos($_FILES['documentos'], 'documento', $upload_dir, $auditoria_id, $conn);
    }

    if ($subidos > 0) {
        header("Location: ../AUD_subir_evidencias.php?status=success&folio=" . $folio);
    } else {
        die("Error al subir archivos. Verifique el tamaño y formato.");
    }
}