<style>
    :root {
        --primary-color: #4361ee;
        --success-color: #2ec4b6;
    }
    body { background-color: #f8f9fa; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
    
    .card { border-radius: 15px; overflow: hidden; }
    .card-header { background: linear-gradient(135deg, #198754 0%, #2ec4b6 100%) !important; border: none; }
    
    .upload-zone {
        border: 2px dashed #dee2e6;
        border-radius: 10px;
        padding: 20px;
        transition: all 0.3s ease;
        background: #fdfdfd;
        cursor: pointer;
    }
    .upload-zone:hover { border-color: var(--primary-color); background: #f8f9ff; }
    
    .btn-primary {
        background-color: var(--primary-color);
        border: none;
        border-radius: 10px;
        font-weight: 600;
        padding: 12px;
        transition: transform 0.2s;
    }
    .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(67, 97, 238, 0.3); }
    
    .preview-img {
        width: 85px;
        height: 85px;
        object-fit: cover;
        border-radius: 12px;
        box-shadow: 0 3px 6px rgba(0,0,0,0.1);
        border: 2px solid white;
    }
    
    .step-number {
        background: #e9ecef;
        color: #6c757d;
        width: 25px;
        height: 25px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
        font-size: 0.8rem;
        margin-right: 8px;
    }
</style>
<div class="container mt-5 mb-5">
    <div class="row justify-content-center">
        <div class="col-lg-7">
            <div class="card shadow-lg border-0">
                <div class="card-header p-4 text-white text-center">
                    <i class="bi bi-shield-check fs-1 mb-2"></i>
                    <h4 class="mb-1 fw-bold">Portal de Evidencias</h4>
                    <p class="mb-0 opacity-75">Folio: <span class="badge bg-white text-dark"><?= $auditoria['folio'] ?></span></p>
                </div>
                
                <div class="card-body p-4 p-md-5">
                    <div class="alert alert-info border-0 shadow-sm rounded-4 mb-4" style="background-color: #eef2ff; color: #4361ee;">
                        <i class="bi bi-info-circle-fill me-2"></i>
                        Unidad asignada: <strong><?= $auditoria['no_serie'] ?></strong>
                    </div>

                    <form id="formEvidencias" action="AUD_controller/guardar_fotos.php" method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="auditoria_id" value="<?= $auditoria['id'] ?>">
                        <input type="hidden" name="folio" value="<?= $auditoria['folio'] ?>">

                        <div class="mb-4">
                            <label class="form-label fw-bold d-flex align-items-center">
                                <span class="step-number">1</span> Fotos de la Unidad
                            </label>
                            <div class="upload-zone text-center" onclick="document.getElementById('inputFotos').click()">
                                <i class="bi bi-cloud-arrow-up fs-1 text-muted"></i>
                                <p class="text-muted small mt-2">Haz clic para seleccionar fotos o arrastra aquí</p>
                                <input type="file" name="fotos[]" id="inputFotos" class="d-none" multiple accept="image/*" required>
                            </div>
                            <div id="previewFotos" class="mt-3 d-flex flex-wrap gap-2 justify-content-center"></div>
                        </div>

                        <div class="mb-5">
                            <label class="form-label fw-bold d-flex align-items-center">
                                <span class="step-number">2</span> Documentación Digital
                            </label>
                            <div class="input-group">
                                <span class="input-group-text bg-white border-end-0"><i class="bi bi-file-earmark-pdf text-danger"></i></span>
                                <input type="file" name="documentos[]" class="form-control border-start-0" multiple accept=".pdf,.doc,.docx">
                            </div>
                            <div class="form-text">PDF, Word o imágenes de documentos.</div>
                        </div>

                        <div id="progressContainer" class="d-none mb-4">
                            <div class="d-flex justify-content-between mb-1">
                                <span class="text-primary fw-bold small">Subiendo archivos...</span>
                                <span id="progressPercent" class="small">0%</span>
                            </div>
                            <div class="progress" style="height: 10px; border-radius: 5px;">
                                <div id="progressBar" class="progress-bar progress-bar-striped progress-bar-animated bg-primary" role="progressbar" style="width: 0%;"></div>
                            </div>
                        </div>

                        <button type="submit" id="btnEnviar" class="btn btn-primary btn-lg w-100 mt-2">
                            <i class="bi bi-send-fill me-2"></i>Finalizar y Enviar Auditoría
                        </button>
                    </form>
                </div>
                
                <div class="card-footer bg-light text-center py-3 border-0">
                    <small class="text-muted">Asegúrate de que las fotos sean claras y tengan buena iluminación.</small>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Manejo de previsualización mejorado
document.getElementById('inputFotos').addEventListener('change', function(e) {
    const preview = document.getElementById('previewFotos');
    preview.innerHTML = '';
    
    if (this.files.length > 0) {
        Array.from(this.files).forEach(file => {
            const reader = new FileReader();
            reader.onload = function(r) {
                const div = document.createElement('div');
                div.innerHTML = `<img src="${r.target.result}" class="preview-img">`;
                preview.appendChild(div);
            }
            reader.readAsDataURL(file);
        });
    }
});

// Confirmación de envío
document.getElementById('formEvidencias').onsubmit = function(e) {
    let timerInterval;
    Swal.fire({
        title: 'Subiendo evidencias',
        html: 'Por favor, no cierres la ventana... <b></b>%',
        timerProgressBar: true,
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
            const b = Swal.getHtmlContainer().querySelector('b');
            // Aquí podrías integrar el progreso real vía AJAX si lo deseas
            document.getElementById('progressContainer').classList.remove('d-none');
        }
    });
};
</script>