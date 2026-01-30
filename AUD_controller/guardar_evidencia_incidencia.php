<?php
require("../config/db.php");

// Configuración de la carpeta de destino
$directorio_destino = "../assets/evidencias_incidencias/";

// Crear el directorio si no existe
if (!is_dir($directorio_destino)) {
    mkdir($directorio_destino, 0777, true);
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $incidencia_id = isset($_POST['incidencia_id']) ? (int)$_POST['incidencia_id'] : 0;
    $comentarios = isset($_POST['comentarios']) ? $_POST['comentarios'] : '';

    if ($incidencia_id > 0 && !empty($_FILES['fotos']['name'][0])) {
        $total_archivos = count($_FILES['fotos']['name']);
        $errores = 0;

        for ($i = 0; $i < $total_archivos; $i++) {
            $nombre_original = $_FILES['fotos']['name'][$i];
            $extension = pathinfo($nombre_original, PATHINFO_EXTENSION);
            
            // Generar nombre único para evitar sobreescritura
            $nuevo_nombre = "INC_" . $incidencia_id . "_" . time() . "_" . $i . "." . $extension;
            $ruta_completa = $directorio_destino . $nuevo_nombre;
            $ruta_db = "assets/evidencias_incidencias/" . $nuevo_nombre;

            if (move_uploaded_file($_FILES['fotos']['tmp_name'][$i], $ruta_completa)) {
                // Insertar registro en la base de datos
                $sql = "INSERT INTO auditorias_evidencias_incidencias_aud (incidencia_id, ruta_archivo, comentarios) VALUES (?, ?, ?)";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("iss", $incidencia_id, $ruta_db, $comentarios);
                
                if (!$stmt->execute()) {
                    $errores++;
                }
                $stmt->close();
            } else {
                $errores++;
            }
        }

        if ($errores == 0) {
            echo "Evidencia guardada correctamente.";
        } else {
            echo "Hubo errores al subir algunos archivos.";
        }
    } else {
        echo "Datos incompletos.";
    }
} else {
    echo "Método no permitido.";
}
?>