<?php
require("../config/db.php");

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $auditoria_id = (int)$_POST['auditoria_id'];
    $folio = $_POST['folio'];
    // Ajusta esta ruta según tu estructura de carpetas
    $upload_dir = "../../uploads/evidencias/{$folio}/"; 

    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }

    $subidos = 0;

    // Función de procesamiento local para evitar problemas de scope
    function guardarArchivo($file_post, $tipo, $dir, $aud_id, $conn, $folio_nombre) {
        $count = 0;
        foreach ($file_post['name'] as $key => $name) {
            if ($file_post['error'][$key] == 0) {
                $ext = pathinfo($name, PATHINFO_EXTENSION);
                $nuevo_nombre = $tipo . "_" . uniqid() . "." . $ext;
                $destino = $dir . $nuevo_nombre;

                if (move_uploaded_file($file_post['tmp_name'][$key], $destino)) {
                    $stmt = $conn->prepare("INSERT INTO auditorias_evidencias_aud (auditoria_id, tipo_archivo, ruta_archivo) VALUES (?, ?, ?)");
                    $ruta_db = "uploads/evidencias/{$folio_nombre}/{$nuevo_nombre}";
                    $stmt->bind_param("iss", $aud_id, $tipo, $ruta_db);
                    $stmt->execute();
                    $count++;
                }
            }
        }
        return $count;
    }

    if (!empty($_FILES['fotos']['name'][0])) {
        $subidos += guardarArchivo($_FILES['fotos'], 'foto', $upload_dir, $auditoria_id, $conn, $folio);
    }

    if (!empty($_FILES['documentos']['name'][0])) {
        $subidos += guardarArchivo($_FILES['documentos'], 'documento', $upload_dir, $auditoria_id, $conn, $folio);
    }

    if ($subidos > 0) {
        // ACTUALIZACIÓN DE ESTATUS: Aquí marcas que la auditoría ya recibió archivos
        // Cambia 'En Revisión' por el nombre o ID de estatus que manejes
        $sql_status = "UPDATE auditorias_vehiculos_aud SET estatus = 'En Revisión' WHERE id = ?";
        $st_status = $conn->prepare($sql_status);
        $st_status->bind_param("i", $auditoria_id);
        $st_status->execute();

        header("Location: ../../AUD_subir_evidencias.php?status=success&folio=" . $folio . "&t=" . $_POST['token']);
    } else {
        header("Location: ../../AUD_subir_evidencias.php?status=error&t=" . $_POST['token']);
    }
}