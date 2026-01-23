<?php
// 1. Conexión a la base de datos (ajusta la ruta si es necesario)
require("../config/db.php"); 

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // 2. Recolección de datos con validación simple
    $auditoria_id = isset($_POST['auditoria_id']) ? (int)$_POST['auditoria_id'] : 0;
    $folio = isset($_POST['folio']) ? $_POST['folio'] : 'sin_folio';
    $token = isset($_POST['token']) ? $_POST['token'] : '';

    // Si el ID llega en 0, detenemos para no causar el error de base de datos
    if ($auditoria_id <= 0) {
        die("Error: No se recibió un ID de auditoría válido. (ID: $auditoria_id)");
    }

    // 3. Configuración de directorio
    $upload_dir = "../uploads/evidencias/{$folio}/";
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }

    $archivos_guardados = 0;

    // 4. Procesar las fotos enviadas
    if (!empty($_FILES['fotos']['name'][0])) {
        foreach ($_FILES['fotos']['name'] as $key => $name) {
            if ($_FILES['fotos']['error'][$key] == 0) {
                
                $ext = pathinfo($name, PATHINFO_EXTENSION);
                $nuevo_nombre = "foto_" . uniqid() . "." . $ext;
                $ruta_destino = $upload_dir . $nuevo_nombre;

                if (move_uploaded_file($_FILES['fotos']['tmp_name'][$key], $ruta_destino)) {
                    // Guardar en la tabla de evidencias
                    $ruta_db = "uploads/evidencias/{$folio}/{$nuevo_nombre}";
                    $stmt = $conn->prepare("INSERT INTO auditorias_evidencias_aud (auditoria_id, tipo_archivo, ruta_archivo) VALUES (?, 'foto', ?)");
                    $stmt->bind_param("is", $auditoria_id, $ruta_db);
                    $stmt->execute();
                    $archivos_guardados++;
                }
            }
        }
    }

    // 5. SI SE SUBIERON ARCHIVOS, ACTUALIZAR EL ESTATUS
    if ($archivos_guardados > 0) {
        // Redireccionar al usuario de vuelta con éxito
        header("Location: ../AUD_subir_evidencia.php?status=success&folio=$folio&t=$token");
    } else {
        echo "No se pudieron procesar las imágenes. Revisa el tamaño de los archivos.";
    }
}
?>