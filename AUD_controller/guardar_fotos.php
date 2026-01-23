<?php
require("../../config/db.php"); // Ajusta la ruta según tu estructura

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $auditoria_id = (int)$_POST['auditoria_id'];
    $folio = $_POST['folio'];
    $token = $_POST['token'];
    
    // Ruta física para guardar archivos
    $upload_dir = "../../uploads/evidencias/{$folio}/"; 

    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }

    $subidos = 0;

    // Función interna para procesar
    function procesar($files, $tipo, $dir, $aud_id, $conn, $f) {
        $c = 0;
        foreach ($files['name'] as $key => $name) {
            if ($files['error'][$key] == 0) {
                $ext = pathinfo($name, PATHINFO_EXTENSION);
                $nuevo_nombre = $tipo . "_" . uniqid() . "." . $ext;
                if (move_uploaded_file($files['tmp_name'][$key], $dir . $nuevo_nombre)) {
                    $stmt = $conn->prepare("INSERT INTO auditorias_evidencias_aud (auditoria_id, tipo_archivo, ruta_archivo) VALUES (?, ?, ?)");
                    $ruta_db = "uploads/evidencias/{$f}/{$nuevo_nombre}";
                    $stmt->bind_param("iss", $aud_id, $tipo, $ruta_db);
                    $stmt->execute();
                    $c++;
                }
            }
        }
        return $c;
    }

    if (!empty($_FILES['fotos']['name'][0])) {
        $subidos += procesar($_FILES['fotos'], 'foto', $upload_dir, $auditoria_id, $conn, $folio);
    }

    if ($subidos > 0) {
        // --- CAMBIO DE ESTATUS AQUÍ ---
        $update = $conn->prepare("UPDATE auditorias_vehiculos_aud SET estatus = 'En Revisión' WHERE id = ?");
        $update->bind_param("i", $auditoria_id);
        $update->execute();

        // Redirigir con éxito
        header("Location: ../AUD_subir_evidencias.php?status=success&folio=$folio&t=$token");
    } else {
        die("Error: No se subieron archivos. Verifica el tamaño permitido en el servidor.");
    }
}