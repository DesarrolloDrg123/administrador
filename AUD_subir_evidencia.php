<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<div class="container mt-5">
    <div class="card shadow border-0">
        <div class="card-header bg-success text-white py-3">
            <h5 class="mb-0"><i class="bi bi-camera-fill me-2"></i>Subir Evidencia - Folio: <?= $auditoria['folio'] ?></h5>
            <small class="opacity-75">Unidad Serie: <?= $auditoria['no_serie'] ?></small>
        </div>
        <div class="card-body p-4">
            <form id="formEvidencias" action="AUD_controller/guardar_fotos.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="auditoria_id" value="<?= $auditoria['id'] ?>">
                <input type="hidden" name="folio" value="<?= $auditoria['folio'] ?>">

                <div class="mb-4">
                    <label class="form-label fw-bold text-secondary">Fotos de la Unidad</label>
                    <div class="input-group">
                        <input type="file" name="fotos[]" class="form-control" multiple accept="image/*" required id="inputFotos">
                        <span class="input-group-text"><i class="bi bi-images"></i></span>
                    </div>
                    <div id="previewFotos" class="mt-2 d-flex flex-wrap gap-2"></div>
                </div>

                <div class="mb-4">
                    <label class="form-label fw-bold text-secondary">Documentos (PDF/Word)</label>
                    <input type="file" name="documentos[]" class="form-control" multiple accept=".pdf,.doc,.docx">
                </div>

                <div class="progress mb-3 d-none" style="height: 25px;">
                    <div id="progressBar" class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 0%;">0%</div>
                </div>

                <button type="submit" id="btnEnviar" class="btn btn-primary btn-lg w-100 shadow-sm">
                    <i class="bi bi-cloud-arrow-up-fill me-2"></i>Enviar Evidencias
                </button>
            </form>
        </div>
    </div>
</div>

<script>
// Previsualización de imágenes rápido
document.getElementById('inputFotos').onchange = function(e) {
    const preview = document.getElementById('previewFotos');
    preview.innerHTML = '';
    Array.from(e.target.files).forEach(file => {
        const reader = new FileReader();
        reader.onload = function(r) {
            const img = document.createElement('img');
            img.src = r.target.result;
            img.style.width = '80px';
            img.style.height = '80px';
            img.style.objectFit = 'cover';
            img.className = 'rounded border';
            preview.appendChild(img);
        }
        reader.readAsDataURL(file);
    });
};
</script>