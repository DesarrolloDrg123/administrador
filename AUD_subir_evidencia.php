<?php
require("config/db.php"); 

$token = isset($_GET['t']) ? $_GET['t'] : '';

if (empty($token)) {
    die("Acceso denegado: Token no proporcionado.");
}

$sql = "SELECT a.id, a.folio, v.no_serie, v.placas 
        FROM auditorias_vehiculos_aud a 
        JOIN vehiculos_aud v ON a.vehiculo_id = v.id 
        WHERE a.token_evidencia = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $token);
$stmt->execute();
$resultado = $stmt->get_result();
$auditoria = $resultado->fetch_assoc();

if (!$auditoria) {
    die("Enlace no válido, expirado o auditoría no encontrada.");
}

// Detectar si venimos de un envío exitoso
$status = isset($_GET['status']) ? $_GET['status'] : '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Subir Evidencias de Auditoría</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root { --primary-color: #80bf1f; --bg-body: #f8f9fa; }
        body { background-color: var(--bg-body); font-family: 'Segoe UI', sans-serif; }
        .container-custom { padding: 20px 10px; }
        .card { border-radius: 20px; border: none; box-shadow: 0 10px 40px rgba(0,0,0,0.1); }
        .card-header { background: linear-gradient(135deg, #80bf1f 0%, #6da31a 100%) !important; padding: 30px 15px !important; border-radius: 20px 20px 0 0 !important; }
        .upload-zone { border: 2px dashed #80bf1f; border-radius: 15px; padding: 40px 20px; background: #fff; cursor: pointer; display: flex; flex-direction: column; align-items: center; }
        .btn-primary { background-color: var(--primary-color); border: none; border-radius: 12px; font-weight: 600; padding: 15px; }
        .preview-item { width: 100px; height: 100px; object-fit: cover; border-radius: 10px; border: 3px solid white; box-shadow: 0 4px 10px rgba(0,0,0,0.1); background: #fff; display: flex; flex-direction: column; align-items: center; justify-content: center; overflow: hidden; }
        .preview-item i { font-size: 2.5rem; color: #dc3545; }
        .info-box { background-color: #f2f9e9; border-left: 5px solid #80bf1f; padding: 15px; border-radius: 8px; margin-bottom: 20px; }
    </style>
</head>
<body>

<div class="container-custom">
    <div class="row justify-content-center g-0">
        <div class="col-12 col-md-10 col-lg-6">
            <div class="card">
                <div class="card-header text-white text-center">
                    <i class="bi bi-cloud-arrow-up-fill fs-1 mb-2 d-block"></i>
                    <h4 class="mb-1 fw-bold">Evidencias de Auditoría</h4>
                    <span class="badge bg-white text-dark py-2 px-3">Folio: <?= $auditoria['folio'] ?></span>
                </div>
                
                <div class="card-body p-4">
                    <?php if ($status !== 'success'): ?>
                    <div class="info-box">
                        <small class="text-muted d-block text-uppercase fw-bold" style="font-size: 0.7rem;">Unidad a Revisar:</small>
                        <span class="text-dark fw-bold fs-5"><?= $auditoria['no_serie'] ?></span>
                        <p class="mb-0 mt-1 text-muted small">Placas: <?= $auditoria['placas'] ?></p>
                    </div>

                    <form id="formEvidencias" action="AUD_controller/guardar_fotos.php" method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="auditoria_id" value="<?= $auditoria['id'] ?>">
                        <input type="hidden" name="folio" value="<?= $auditoria['folio'] ?>">
                        <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>"> 
                        
                        <div class="mb-4">
                            <label class="form-label fw-bold mb-3"><i class="bi bi-file-earmark-plus me-2 text-success"></i>Seleccione Fotos o PDF</label>
                            <label for="inputFotos" class="upload-zone w-100 text-center">
                                <i class="bi bi-images text-success fs-1 mb-2"></i>
                                <span class="fw-bold text-secondary">Tocar para seleccionar</span>
                                <input type="file" name="fotos[]" id="inputFotos" class="d-none" multiple accept="image/*,.pdf" required>
                            </label>
                            <div id="previewFotos" class="mt-3 d-flex flex-wrap gap-2 justify-content-center"></div>
                        </div>

                        <button type="submit" id="btnEnviar" class="btn btn-primary w-100 shadow">
                            <i class="bi bi-check-circle-fill me-2"></i>Subir y Finalizar
                        </button>
                    </form>
                    <?php else: ?>
                        <div class="text-center py-5">
                            <i class="bi bi-check-circle-fill text-success" style="font-size: 5rem;"></i>
                            <h3 class="mt-3 fw-bold">¡Todo listo!</h3>
                            <p class="text-muted">Las evidencias se enviaron correctamente.</p>
                            
                            <div class="mt-4 d-grid gap-2">
                                <a href="?t=<?= htmlspecialchars($token) ?>" class="btn btn-outline-success border-2 fw-bold py-3">
                                    <i class="bi bi-plus-circle me-2"></i>Cargar más documentos
                                </a>
                                <p class="small text-muted mt-2">O puede cerrar esta ventana si ha terminado.</p>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// --- Lógica del SweetAlert al cargar la página ---
<?php if ($status === 'success'): ?>
    Swal.fire({
        icon: 'success',
        title: '¡Envío Exitoso!',
        text: 'Las evidencias del folio <?= $_GET['folio'] ?> se guardaron correctamente.',
        showCancelButton: true,
        confirmButtonColor: '#80bf1f',
        cancelButtonColor: '#6c757d',
        confirmButtonText: '<i class="bi bi-plus-circle me-1"></i> Subir más documentos',
        cancelButtonText: 'Finalizar',
        reverseButtons: true
    }).then((result) => {
        if (result.isConfirmed) {
            // Recarga la página sin el parámetro status para mostrar el formulario limpio
            window.location.href = '?t=<?= htmlspecialchars($token) ?>';
        }
    });
<?php endif; ?>

// --- Previsualización ---
document.getElementById('inputFotos')?.addEventListener('change', function() {
    const preview = document.getElementById('previewFotos');
    preview.innerHTML = '';
    if (this.files) {
        [...this.files].forEach(file => {
            const container = document.createElement('div');
            container.className = 'preview-item';
            if (file.type === "application/pdf") {
                container.innerHTML = `<i class="bi bi-file-pdf"></i><span class="text-truncate w-100 px-1 text-center" style="font-size: 0.6rem;">${file.name}</span>`;
            } else {
                const reader = new FileReader();
                reader.onload = (e) => {
                    const img = document.createElement('img');
                    img.src = e.target.result;
                    img.className = 'w-100 h-100 object-fit-cover';
                    container.appendChild(img);
                }
                reader.readAsDataURL(file);
            }
            preview.appendChild(container);
        });
    }
});

document.getElementById('formEvidencias')?.addEventListener('submit', function() {
    document.getElementById('btnEnviar').disabled = true;
    document.getElementById('btnEnviar').innerHTML = '<span class="spinner-border spinner-border-sm"></span> Subiendo...';
    Swal.fire({
        title: 'Subiendo...',
        text: 'Espere un momento.',
        allowOutsideClick: false,
        didOpen: () => { Swal.showLoading(); }
    });
});
</script>
</body>
</html>