<?php
// 1. Conexión a la base de datos
require("../config/db.php"); 

?>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<style>
    body { font-family: 'Segoe UI', sans-serif; background-color: #f8f9fa; }
</style>

<?php
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
                    $ruta_db = "uploads/evidencias_aud/{$folio}/{$nuevo_nombre}";
                    
                    $stmt = $conn->prepare("INSERT INTO auditorias_evidencias_aud (auditoria_id, tipo_archivo, ruta_archivo) VALUES (?, ?, ?)");
                    $stmt->bind_param("iss", $auditoria_id, $tipo_evidencia, $ruta_db);
                    $stmt->execute();
                    $archivos_guardados++;
                }
            }
        }
    }

    // 5. Finalización con Alerta Visual
    if ($archivos_guardados > 0) {
        echo "<script>
            Swal.fire({
                icon: 'success',
                title: '¡Evidencias Enviadas!',
                text: 'Se guardaron $archivos_guardados archivo(s) correctamente.',
                confirmButtonColor: '#80bf1f',
                confirmButtonText: 'Finalizar'
            }).then((result) => {
                // Redirigir a una página de éxito o al mismo formulario limpio
                window.location.href = '../AUD_subir_evidencia.php?status=success&folio=$folio&t=$token';
            });
        </script>";
    } else {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Error al subir',
                text: 'No se pudieron procesar los archivos. Verifica el tamaño y formato.',
                confirmButtonColor: '#d33'
            }).then(() => {
                window.history.back();
            });
        </script>";
    }
}
?>