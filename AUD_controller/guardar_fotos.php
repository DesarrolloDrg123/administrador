<?php
// 1. Conexión a la base de datos
require("../config/db.php"); 

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // 2. Recolección de datos
    $auditoria_id = isset($_POST['auditoria_id']) ? (int)$_POST['auditoria_id'] : 0;
    $folio = isset($_POST['folio']) ? $_POST['folio'] : 'sin_folio';
    $token = isset($_POST['token']) ? $_POST['token'] : '';

    if ($auditoria_id <= 0) {
        die("Error: No se recibió un ID de auditoría válido.");
    }

    // 3. Configuración de directorio
    $upload_dir = "../uploads/evidencias_aud/{$folio}/";
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }

    $archivos_guardados = 0;

    // 4. Procesar los archivos enviados (fotos o pdfs)
    if (!empty($_FILES['fotos']['name'][0])) {
        foreach ($_FILES['fotos']['name'] as $key => $name) {
            if ($_FILES['fotos']['error'][$key] == 0) {
                
                $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                $mime = $_FILES['fotos']['type'][$key];
                
                // Determinamos el tipo de archivo
                $tipo_evidencia = 'foto'; 
                if ($ext === 'pdf' || $mime === 'application/pdf') {
                    $tipo_evidencia = 'pdf';
                }

                // Generamos un nombre único
                $prefix = ($tipo_evidencia === 'pdf') ? "doc_" : "foto_";
                $nuevo_nombre = $prefix . uniqid() . "." . $ext;
                $ruta_destino = $upload_dir . $nuevo_nombre;

                if (move_uploaded_file($_FILES['fotos']['tmp_name'][$key], $ruta_destino)) {
                    // Ruta relativa para la base de datos
                    $ruta_db = "uploads/evidencias_aud/{$folio}/{$nuevo_nombre}";
                    
                    $stmt = $conn->prepare("INSERT INTO auditorias_evidencias_aud (auditoria_id, tipo_archivo, ruta_archivo) VALUES (?, ?, ?)");
                    $stmt->bind_param("iss", $auditoria_id, $tipo_evidencia, $ruta_db);
                    $stmt->execute();
                    $archivos_guardados++;
                }
            }
        }
    }

    // 5. Redirección estratégica
    // Enviamos el conteo de archivos y el status para que el formulario principal muestre el SweetAlert
    if ($archivos_guardados > 0) {
        header("Location: ../AUD_subir_evidencia.php?status=success&folio=$folio&t=$token&count=$archivos_guardados");
        exit();
    } else {
        // Si falló, regresamos con un error
        header("Location: ../AUD_subir_evidencia.php?status=error&t=$token");
        exit();
    }
}
?>