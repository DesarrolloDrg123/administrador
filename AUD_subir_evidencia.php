<?php
require("config/db.php"); // Asegúrate de que la ruta sea correcta

// 1. Obtenemos el token de la URL
$token = isset($_GET['t']) ? $_GET['t'] : '';

if (empty($token)) {
    die("Acceso denegado: Token no proporcionado.");
}

// 2. Consulta corregida: Pedimos explícitamente el 'id'
$sql = "SELECT a.id, a.folio, v.no_serie 
        FROM auditorias_vehiculos_aud a 
        JOIN vehiculos v ON a.vehiculo_id = v.id 
        WHERE a.token_evidencia = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $token);
$stmt->execute();
$resultado = $stmt->get_result();
$auditoria = $resultado->fetch_assoc();

// 3. Verificación de seguridad
if (!$auditoria) {
    die("Enlace no válido, expirado o auditoría no encontrada.");
}

// Opcional: Descomenta la siguiente línea para debuguear si sigue fallando
// echo "ID de Auditoría cargado: " . $auditoria['id']; 
?>
<style>
    :root {
        --primary-color: #4361ee;
        --success-color: #2ec4b6;
        --bg-body: #f0f2f5;
    }
    
    body { 
        background-color: var(--bg-body); 
        font-family: 'Segoe UI', system-ui, -apple-system, sans-serif; 
    }

    /* Ajustes de espaciado responsivo */
    .container-custom {
        padding: 10px; /* Espacio mínimo en celulares */
    }
    
    @media (min-width: 768px) {
        .container-custom { padding: 40px 20px; }
    }

    .card { 
        border-radius: 20px; 
        border: none; 
        box-shadow: 0 8px 30px rgba(0,0,0,0.08); 
        overflow: hidden; 
    }

    /* Header elegante y compacto en móvil */
    .card-header { 
        background: linear-gradient(135deg, #198754 0%, #2ec4b6 100%) !important; 
        border: none;
        padding: 20px 15px !important;
    }

    /* Área de carga optimizada para "Taps" */
    .upload-zone {
        border: 2px dashed #cbd5e0;
        border-radius: 15px;
        padding: 35px 20px;
        transition: all 0.3s ease;
        background: #fdfdfd;
        cursor: pointer;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
    }
    
    .upload-zone:active { /* Efecto al tocar en celular */
        transform: scale(0.98);
        background: #f0f3ff;
    }

    .btn-primary {
        background-color: var(--primary-color);
        border: none;
        border-radius: 12px;
        font-weight: 600;
        padding: 15px; /* Más alto para facilitar el toque */
        font-size: 1.1rem;
    }

    /* Previsualización responsiva */
    .preview-img {
        width: 75px; /* Un poco más pequeñas en móvil */
        height: 75px;
        object-fit: cover;
        border-radius: 10px;
        border: 2px solid white;
        box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    }

    @media (min-width: 576px) {
        .preview-img { width: 90px; height: 90px; }
    }

    .step-number {
        background: var(--primary-color);
        color: white;
        width: 24px;
        height: 24px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
        font-size: 0.75rem;
        margin-right: 10px;
        vertical-align: middle;
    }
</style>

<div class="container-custom">
    <div class="row justify-content-center g-0">
        <div class="col-12 col-md-10 col-lg-7">
            <div class="card">
                <div class="card-header text-white text-center">
                    <i class="bi bi-camera-fill fs-2 mb-2 d-block"></i>
                    <h5 class="mb-1 fw-bold">Evidencias de Auditoría</h5>
                    <div class="d-flex justify-content-center gap-2 mt-2">
                        <span class="badge bg-white text-dark py-2 px-3">Folio: <?= $auditoria['folio'] ?></span>
                    </div>
                </div>
                
                <div class="card-body p-4">
                    <div class="alert alert-light border-0 py-3 mb-4" style="background-color: #f8faff; border-left: 4px solid var(--primary-color) !important;">
                        <small class="text-muted d-block text-uppercase fw-bold" style="font-size: 0.7rem;">Unidad a Revisar</small>
                        <span class="text-dark fw-bold"><?= $auditoria['no_serie'] ?></span>
                    </div>

                    <form id="formEvidencias" action="AUD_controller/guardar_fotos.php" method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="auditoria_id" value="<?= $auditoria['id'] ?>">
                        <input type="hidden" name="folio" value="<?= $auditoria['folio'] ?>">
                        <input type="hidden" name="token" value="<?= $_GET['t'] ?>"> <div class="mb-4">
                            <label class="form-label fw-bold h6 mb-3">
                                <span class="step-number">1</span>Tomar o Seleccionar Fotos
                            </label>
                            
                            <label for="inputFotos" class="upload-zone w-100 text-center">
                                <i class="bi bi-images fs-1 text-primary mb-2"></i>
                                <span class="fw-bold text-secondary text-center">Presiona aquí para subir fotos</span>
                                <small class="text-muted">Puedes subir varias a la vez</small>
                                <input type="file" name="fotos[]" id="inputFotos" class="d-none" multiple accept="image/*" required>
                            </label>
                            
                            <div id="previewFotos" class="mt-3 d-flex flex-wrap gap-2 justify-content-center"></div>
                        </div>

                        <div id="progressContainer" class="d-none mb-4 text-center">
                            <div class="spinner-border text-primary mb-2" role="status"></div>
                            <p class="small text-muted mb-1">Subiendo archivos...</p>
                            <div class="progress" style="height: 6px;">
                                <div id="progressBar" class="progress-bar progress-bar-striped progress-bar-animated bg-primary" style="width: 100%;"></div>
                            </div>
                        </div>

                        <button type="submit" id="btnEnviar" class="btn btn-primary w-100 shadow">
                            <i class="bi bi-cloud-check-fill me-2"></i>Enviar Evidencias
                        </button>
                        
                        </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// El JS de previsualización ahora es más limpio y detecta el cambio de inmediato
document.getElementById('inputFotos').addEventListener('change', function() {
    const preview = document.getElementById('previewFotos');
    preview.innerHTML = '';
    
    if (this.files) {
        [...this.files].forEach(file => {
            const reader = new FileReader();
            reader.onload = (e) => {
                const img = document.createElement('img');
                img.src = e.target.result;
                img.className = 'preview-img';
                preview.appendChild(img);
            }
            reader.readAsDataURL(file);
        });
    }
});

// Al enviar, mostramos SweetAlert y bloqueamos el botón
document.getElementById('formEvidencias').addEventListener('submit', function(e) {
    // No usamos preventDefault para que el formulario se envíe normalmente a PHP
    document.getElementById('btnEnviar').disabled = true;
    document.getElementById('btnEnviar').innerHTML = '<span class="spinner-border spinner-border-sm"></span> Subiendo...';
    
    Swal.fire({
        title: 'Subiendo Archivos',
        text: 'Esto puede tardar dependiendo de tu internet.',
        allowOutsideClick: false,
        didOpen: () => { Swal.showLoading(); }
    });
});
</script>