<?php
require("config/db.php"); 

$id_incidencia = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id_incidencia <= 0) {
    die("Acceso denegado: ID de incidencia no válido.");
}

// Mantenemos la consulta que ya te funcionaba
$sql = "SELECT i.id, i.descripcion as incidencia, v.placas, v.no_serie
        FROM auditorias_incidencias_aud i
        JOIN vehiculos_aud v ON i.vehiculo_id = v.id
        WHERE i.id = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id_incidencia);
$stmt->execute();
$resultado = $stmt->get_result();
$datos = $resultado->fetch_assoc();

if (!$datos) {
    die("Incidencia no encontrada o ya ha sido gestionada.");
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Subir Evidencia</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root { --primary-color: #80bf1f; --bg-body: #f8f9fa; }
        body { background-color: var(--bg-body); font-family: 'Segoe UI', sans-serif; }
        .container-custom { padding: 20px 10px; }
        .card { border-radius: 20px; border: none; box-shadow: 0 10px 40px rgba(0,0,0,0.1); }
        .card-header { background: linear-gradient(135deg, #80bf1f 0%, #6da31a 100%) !important; padding: 30px 15px !important; border-radius: 20px 20px 0 0 !important; }
        .upload-zone { border: 2px dashed #80bf1f; border-radius: 15px; padding: 40px 20px; transition: all 0.3s ease; background: #fff; cursor: pointer; display: flex; flex-direction: column; align-items: center; }
        .upload-zone:hover { background: #f2f9e9; }
        .btn-primary { background-color: var(--primary-color); border: none; border-radius: 12px; font-weight: 600; padding: 15px; }
        
        /* Estilos para la previsualización */
        .preview-item { width: 100px; height: 100px; object-fit: cover; border-radius: 10px; border: 2px solid #ddd; }
        .preview-pdf { width: 100px; height: 100px; display: flex; align-items: center; justify-content: center; background: #f8d7da; color: #dc3545; border-radius: 10px; font-size: 2rem; border: 2px solid #f5c2c7; }
        .incidencia-info { background-color: #fff3cd; border-left: 5px solid #ffc107; padding: 15px; border-radius: 8px; margin-bottom: 20px; }
    </style>
</head>
<body>

<div class="container-custom">
    <div class="row justify-content-center g-0">
        <div class="col-12 col-md-10 col-lg-6">
            <div class="card">
                <div class="card-header text-white text-center">
                    <i class="bi bi-file-earmark-arrow-up fs-1 mb-2 d-block"></i>
                    <h4 class="mb-1 fw-bold">Subir Evidencia</h4>
                    <span class="badge bg-white text-dark py-2 px-3">Placas: <?= $datos['placas'] ?></span>
                </div>
                
                <div class="card-body p-4">
                    <div class="incidencia-info">
                        <small class="text-muted d-block text-uppercase fw-bold" style="font-size: 0.7rem;">Tarea:</small>
                        <span class="text-dark fw-bold fs-5"><?= $datos['incidencia'] ?></span>
                    </div>

                    <form id="formEvidencias" action="AUD_controller/guardar_evidencia_incidencia.php" method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="incidencia_id" value="<?= $datos['id'] ?>">
                        
                        <div class="mb-4">
                            <label class="form-label fw-bold mb-3">
                                <i class="bi bi-paperclip me-2 text-success"></i>Seleccione fotos o documentos PDF
                            </label>
                            
                            <label for="inputFotos" class="upload-zone w-100 text-center">
                                <i class="bi bi-cloud-upload text-success fs-1 mb-2"></i>
                                <span class="fw-bold text-secondary">Tocar para subir Imagen o PDF</span>
                                <input type="file" name="fotos[]" id="inputFotos" class="d-none" multiple accept="image/*,.pdf" required>
                            </label>
                            
                            <div id="previewFotos" class="mt-3 d-flex flex-wrap gap-2 justify-content-center"></div>
                        </div>

                        <div class="mb-4">
                            <label class="form-label fw-bold small">Comentarios</label>
                            <textarea name="comentarios" class="form-control" rows="2" placeholder="Notas sobre la reparación..."></textarea>
                        </div>

                        <button type="submit" id="btnEnviar" class="btn btn-primary w-100 shadow">
                            <i class="bi bi-send-fill me-2"></i>Enviar Información
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('formEvidencias').addEventListener('submit', async function(e) {
    e.preventDefault(); // Evita que la página se recargue

    const btn = document.getElementById('btnEnviar');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Enviando...';
    
    // Mostrar Loading
    Swal.fire({
        title: 'Subiendo archivos',
        text: 'Por favor espere...',
        allowOutsideClick: false,
        didOpen: () => { Swal.showLoading(); }
    });

    try {
        const formData = new FormData(this);
        const response = await fetch(this.action, {
            method: 'POST',
            body: formData
        });

        const res = await response.json();

        if (res.status === 'success') {
            Swal.fire({
                icon: 'success',
                title: '¡Enviado!',
                text: 'La evidencia se ha recibido correctamente.',
                confirmButtonColor: '#80bf1f',
                confirmButtonText: 'Entendido'
            }).then(() => {
                // Opcional: Redirigir o limpiar el formulario
                window.location.reload(); 
            });
        } else {
            throw new Error(res.message);
        }

    } catch (error) {
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: error.message || 'No se pudo enviar la información.',
            confirmButtonColor: '#d33'
        });
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-send-fill me-2"></i>Enviar Información';
    }
});
</script>
</body>
</html>