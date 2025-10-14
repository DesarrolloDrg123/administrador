<?php
session_start();
require("config/db.php");
include("src/templates/adminheader.php");

if (!isset($_SESSION['loggedin']) || !$_SESSION['loggedin']) {
    header("Location: index.php");
    exit();
}

// Se obtienen los datos de las 3 noticias
$sql = "SELECT id, titulo, descripcion, ruta_imagen FROM noticias_inicio WHERE id IN (1, 2, 3) ORDER BY id ASC";
$result = $conn->query($sql);

$noticias_data = [
    1 => ['id' => 1, 'titulo' => '', 'descripcion' => '', 'ruta_imagen' => ''],
    2 => ['id' => 2, 'titulo' => '', 'descripcion' => '', 'ruta_imagen' => ''],
    3 => ['id' => 3, 'titulo' => '', 'descripcion' => '', 'ruta_imagen' => '']
];

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $noticias_data[$row['id']] = $row;
    }
}
?>

<style>
    .img-preview {
        max-width: 100%;
        max-height: 150px;
        object-fit: cover;
        border-radius: 0.25rem;
        border: 1px solid #dee2e6;
        margin-top: 10px;
        background-color: #f8f9fa;
    }
    .img-placeholder {
        display: flex;
        align-items: center;
        justify-content: center;
        color: #6c757d;
        font-style: italic;
        height: 150px;
    }
    .tab-content {
        border: 1px solid #dee2e6;
        border-top: 0;
        padding: 1.5rem;
        border-radius: 0 0 0.375rem 0.375rem;
    }
    .nav-tabs .nav-link {
        font-weight: 500;
    }
</style>

<div class="container mt-4 mb-5">
    <div class="card shadow-sm">
        <div class="card-header bg-dark text-white">
            <h2 class="mb-0 h5">Gestionar Noticias de la Página Principal</h2>
        </div>
        <div class="card-body p-4">
            <form id="form-noticias" action="UT_controller/guardar_noticias.php" method="POST" enctype="multipart/form-data">

                <ul class="nav nav-tabs" id="noticiasTab" role="tablist">
                    <?php for ($i = 1; $i <= 3; $i++): ?>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link <?= ($i == 1) ? 'active' : '' ?>" id="tab-noticia-<?= $i ?>" data-bs-toggle="tab" data-bs-target="#panel-noticia-<?= $i ?>" type="button" role="tab">
                                Noticia #<?= $i ?>
                            </button>
                        </li>
                    <?php endfor; ?>
                </ul>

                <div class="tab-content" id="noticiasTabContent">
                    <?php for ($i = 1; $i <= 3; $i++): 
                        $noticia = $noticias_data[$i];
                    ?>
                        <div class="tab-pane fade <?= ($i == 1) ? 'show active' : '' ?>" id="panel-noticia-<?= $i ?>" role="tabpanel">
                            <input type="hidden" name="noticias[<?= $i-1 ?>][id]" value="<?= htmlspecialchars($noticia['id']) ?>">
                            
                            <div class="row g-4">
                                <div class="col-md-8">
                                    <div class="mb-3">
                                        <label class="form-label">Título*</label>
                                        <input type="text" name="noticias[<?= $i-1 ?>][titulo]" class="form-control" value="<?= htmlspecialchars($noticia['titulo']) ?>" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Descripción*</label>
                                        <textarea name="noticias[<?= $i-1 ?>][descripcion]" class="form-control"><?= htmlspecialchars($noticia['descripcion']) ?></textarea>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label class="form-label">Cambiar Imagen</label>
                                        <input type="file" name="noticias[<?= $i-1 ?>][imagen]" class="form-control" accept="image/png, image/jpeg" 
                                               data-preview-target="#preview-img-<?= $i ?>">
                                    </div>
                                    <label class="form-label small text-muted">Vista Previa:</label>
                                    <?php if (!empty($noticia['ruta_imagen'])): ?>
                                        <img id="preview-img-<?= $i ?>" src="<?= htmlspecialchars($noticia['ruta_imagen']) ?>" alt="Vista previa" class="img-preview">
                                    <?php else: ?>
                                        <div id="preview-img-<?= $i ?>" class="img-preview img-placeholder">Sin imagen</div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endfor; ?>
                </div>

                <div class="text-center mt-4">
                    <button type="submit" class="btn btn-primary px-5">Guardar Cambios</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // --- 1. INICIALIZACIÓN DE TINYMCE ---
    tinymce.init({
        selector: 'textarea[name$="[descripcion]"]',
        menubar: false,
        plugins: 'link lists wordcount',
        toolbar: 'undo redo | blocks | bold italic underline | link | numlist bullist | removeformat',
        height: 200
    });

    // --- 2. LÓGICA PARA LA VISTA PREVIA DE IMÁGENES ---
    document.querySelectorAll('input[type="file"]').forEach(input => {
        input.addEventListener('change', function(event) {
            const file = event.target.files[0];
            const previewTargetSelector = event.target.dataset.previewTarget;
            const previewElement = document.querySelector(previewTargetSelector);

            if (file && previewElement) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    // Si el elemento es un div (placeholder), lo reemplazamos por una img
                    if (previewElement.tagName === 'DIV') {
                        const newImg = document.createElement('img');
                        newImg.id = previewElement.id;
                        newImg.className = 'img-preview';
                        previewElement.replaceWith(newImg);
                        newImg.src = e.target.result;
                    } else {
                        previewElement.src = e.target.result;
                    }
                }
                reader.readAsDataURL(file);
            }
        });
    });

    // --- 3. LÓGICA PARA ENVIAR EL FORMULARIO (tu código existente) ---
    const formNoticias = document.querySelector('#form-noticias');
    formNoticias.addEventListener('submit', function(e) {
        e.preventDefault();
        tinymce.triggerSave(); // Actualizar textareas antes de enviar

        const formData = new FormData(formNoticias);
        const botonSubmit = formNoticias.querySelector('button[type="submit"]');
        
        botonSubmit.disabled = true;
        botonSubmit.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Guardando...';

        Swal.fire({
            title: 'Guardando Noticias',
            html: 'Por favor, espera...',
            allowOutsideClick: false,
            didOpen: () => Swal.showLoading()
        });

        fetch('UT_controller/guardar_noticias.php', {
            method: 'POST',
            body: formData 
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                Swal.fire({
                    icon: 'success',
                    title: '¡Éxito!',
                    text: data.message,
                }).then(() => location.reload());
            } else {
                Swal.fire({ icon: 'error', title: 'Error', text: data.message });
            }
        })
        .catch(error => {
            console.error('Error de red:', error);
            Swal.fire({ icon: 'error', title: 'Error de Conexión', text: 'No se pudo comunicar con el servidor.' });
        })
        .finally(() => {
            botonSubmit.disabled = false;
            botonSubmit.innerHTML = 'Guardar Cambios';
        });
    });
});
</script>


<?php
include("src/templates/adminfooter.php");
?>