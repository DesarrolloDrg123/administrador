<?php
require("config/db.php");

// Obtener el token de la URL
$token = $_GET['token'] ?? null;

if (!$token) {
    die("<h2>Acceso Denegado</h2><p>No se proporcionó un token válido.</p>");
}

// Verificar el token y obtener datos del candidato
$candidato = null;
try {
    $stmt = $conn->prepare("
        SELECT candidato_id, nombre_completo, correo_electronico, estatus
        FROM solicitudes_vacantes_candidatos 
        WHERE token_documentos = ? 
        AND fecha_token_documentos >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    ");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();
    $candidato = $result->fetch_assoc();
    
    if (!$candidato) {
        die("<h2>Token Inválido o Expirado</h2><p>El enlace ha expirado o no es válido. Por favor, contacta con el departamento de Talento Huamano DRG.</p>");
    }
    
    // Verificar si ya subió documentos
    $stmt_docs = $conn->prepare("SELECT estatus_documentos FROM solicitudes_vacantes_candidatos WHERE candidato_id = ?");
    $stmt_docs->bind_param("i", $candidato['candidato_id']);
    $stmt_docs->execute();
    $docs_result = $stmt_docs->get_result();
    $doc_status = $docs_result->fetch_assoc();
    
    if ($doc_status && $doc_status['estatus_documentos'] === '1') {
        die("<h2>Documentos Ya Enviados</h2><p>Ya has completado la carga de documentos. Si necesitas actualizar algún documento, contacta con Recursos Humanos.</p>");
    }
    
} catch (Exception $e) {
    die("<h2>Error</h2><p>Ocurrió un error al validar tu acceso. Por favor, intenta más tarde.</p>" . $e);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Carga de Documentos para Contratación</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; }
        .card { border: none; box-shadow: 0 4px 8px rgba(0,0,0,0.1); }
        .doc-section { background-color: #f8f9fa; padding: 1.5rem; border-radius: 8px; margin-bottom: 1rem; }
        .file-info { font-size: 0.85rem; color: #6c757d; margin-top: 0.25rem; }
        .candidato-info { background-color: #e7f3ff; padding: 15px; border-radius: 5px; margin-bottom: 20px; }
    </style>
</head>
<body>

<div class="container my-5">
    <div class="row justify-content-center">
        <div class="col-lg-10">
            <div class="card">
                <div class="card-body p-4 p-md-5">
                    <div class="d-flex justify-content-center align-items-center mb-4">
                        <img src="img/logo-drg.png" alt="Logo de la Empresa" class="img-fluid me-4" style="max-height: 60px;">
                        <h2 class="card-title mb-0">Carga de Documentos para Contratación</h2>
                    </div>
                    
                    <!-- Información del candidato -->
                    <div class="candidato-info">
                        <strong>Candidato:</strong> <?= htmlspecialchars($candidato['nombre_completo']) ?><br>
                        <strong>Correo:</strong> <?= htmlspecialchars($candidato['correo_electronico']) ?>
                    </div>
                    
                    <p class="text-center text-muted mb-5">Por favor, sube los documentos requeridos para continuar con tu proceso de contratación. Todos los campos marcados con <span class="text-danger">*</span> son obligatorios.</p>
                    
                    <form id="formDocumentos" enctype="multipart/form-data">
                        <!-- Campo oculto con el token -->
                        <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
                        <input type="hidden" name="candidato_id" value="<?= $candidato['candidato_id'] ?>">

                        <h4 class="mb-4">Documentos Requeridos</h4>

                        <!-- 1. Identificación Oficial -->
                        <div class="doc-section">
                            <h5 class="text-primary mb-3">1. Identificación Oficial Vigente <span class="text-danger">*</span></h5>
                            <p class="text-muted small">INE/IFE, Pasaporte o Cédula Profesional (ambos lados si aplica)</p>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label for="ine_frente" class="form-label">INE/Identificación (Frente) <span class="text-danger">*</span></label>
                                    <input class="form-control" type="file" id="ine_frente" name="ine_frente" accept=".pdf,.jpg,.jpeg,.png" required>
                                    <div class="file-info">Formatos: PDF, JPG, PNG. Máx. 5MB</div>
                                </div>
                                <div class="col-md-6">
                                    <label for="ine_reverso" class="form-label">INE/Identificación (Reverso) <span class="text-danger">*</span></label>
                                    <input class="form-control" type="file" id="ine_reverso" name="ine_reverso" accept=".pdf,.jpg,.jpeg,.png" required>
                                    <div class="file-info">Formatos: PDF, JPG, PNG. Máx. 5MB</div>
                                </div>
                            </div>
                        </div>

                        <!-- 2. CURP -->
                        <div class="doc-section">
                            <h5 class="text-primary mb-3">2. CURP (Clave Única de Registro de Población) <span class="text-danger">*</span></h5>
                            <p class="text-muted small">Documento oficial emitido por RENAPO</p>
                            <div class="mb-3">
                                <label for="curp_documento" class="form-label">Documento CURP <span class="text-danger">*</span></label>
                                <input class="form-control" type="file" id="curp_documento" name="curp_documento" accept=".pdf,.jpg,.jpeg,.png" required>
                                <div class="file-info">Formatos: PDF, JPG, PNG. Máx. 5MB</div>
                            </div>
                        </div>

                        <!-- 3. RFC -->
                        <div class="doc-section">
                            <h5 class="text-primary mb-3">3. RFC (Registro Federal de Contribuyentes) <span class="text-danger">*</span></h5>
                            <p class="text-muted small">Cédula de identificación fiscal o constancia de situación fiscal</p>
                            <div class="mb-3">
                                <label for="rfc_documento" class="form-label">Documento RFC <span class="text-danger">*</span></label>
                                <input class="form-control" type="file" id="rfc_documento" name="rfc_documento" accept=".pdf,.jpg,.jpeg,.png" required>
                                <div class="file-info">Formatos: PDF, JPG, PNG. Máx. 5MB</div>
                            </div>
                        </div>

                        <!-- 4. Comprobante de Domicilio -->
                        <div class="doc-section">
                            <h5 class="text-primary mb-3">4. Comprobante de Domicilio Reciente <span class="text-danger">*</span></h5>
                            <p class="text-muted small">No mayor a 3 meses (luz, agua, teléfono, predial o estado de cuenta)</p>
                            <div class="mb-3">
                                <label for="comprobante_domicilio" class="form-label">Comprobante de Domicilio <span class="text-danger">*</span></label>
                                <input class="form-control" type="file" id="comprobante_domicilio" name="comprobante_domicilio" accept=".pdf,.jpg,.jpeg,.png" required>
                                <div class="file-info">Formatos: PDF, JPG, PNG. Máx. 5MB</div>
                            </div>
                        </div>

                        <!-- 6. NSS -->
                        <div class="doc-section">
                            <h5 class="text-primary mb-3">6. Número de Seguridad Social (NSS) <span class="text-danger">*</span></h5>
                            <p class="text-muted small">Hoja rosa del IMSS o comprobante de afiliación</p>
                            <div class="mb-3">
                                <label for="nss_documento" class="form-label">Documento NSS <span class="text-danger">*</span></label>
                                <input class="form-control" type="file" id="nss_documento" name="nss_documento" accept=".pdf,.jpg,.jpeg,.png" required>
                                <div class="file-info">Formatos: PDF, JPG, PNG. Máx. 5MB</div>
                            </div>
                        </div>

                        <!-- 9. Comprobante de Estudios -->
                        <div class="doc-section">
                            <h5 class="text-primary mb-3">9. Comprobante de Estudios <span class="text-danger">*</span></h5>
                            <p class="text-muted small">Certificado, título profesional o cédula profesional según corresponda</p>
                            <div class="mb-3">
                                <label for="comprobante_estudios" class="form-label">Documento de Estudios <span class="text-danger">*</span></label>
                                <input class="form-control" type="file" id="comprobante_estudios" name="comprobante_estudios" accept=".pdf,.jpg,.jpeg,.png" required>
                                <div class="file-info">Formatos: PDF, JPG, PNG. Máx. 5MB</div>
                            </div>
                        </div>

                        <!-- 15. Exámenes Médicos -->
                        <div class="doc-section">
                            <h5 class="text-primary mb-3">15. Exámenes Médicos <span class="text-danger">*</span></h5>
                            <p class="text-muted small">Resultados de exámenes médicos de contratación</p>
                            <div class="mb-3">
                                <label for="examenes_medicos" class="form-label">Resultados de Exámenes Médicos <span class="text-danger">*</span></label>
                                <input class="form-control" type="file" id="examenes_medicos" name="examenes_medicos" accept=".pdf,.jpg,.jpeg,.png" required>
                                <div class="file-info">Formatos: PDF, JPG, PNG. Máx. 5MB</div>
                            </div>
                        </div>

                        <!-- Notas Adicionales -->
                        <div class="alert alert-info mt-4" role="alert">
                            <h5 class="alert-heading">📋 Notas Importantes:</h5>
                            <ul class="mb-0">
                                <li>Todos los documentos deben ser legibles y estar vigentes</li>
                                <li>Los archivos no deben exceder los 5MB por documento</li>
                                <li>Formatos aceptados: PDF, JPG, PNG</li>
                                <li>Asegúrate de que tu identificación oficial esté vigente</li>
                                <li>El comprobante de domicilio debe tener máximo 3 meses de antigüedad</li>
                            </ul>
                        </div>

                        <div class="mt-5 text-center">
                            <button type="submit" class="btn btn-primary btn-lg px-5">Enviar Documentos</button>
                        </div>

                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('formDocumentos');
    const fileInputs = form.querySelectorAll('input[type="file"]');

    fileInputs.forEach(input => {
        input.addEventListener('change', function() {
            const maxSize = 5 * 1024 * 1024;
            if (this.files[0] && this.files[0].size > maxSize) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Archivo muy grande',
                    text: 'El archivo no debe exceder los 5MB. Por favor, selecciona un archivo más pequeño.'
                });
                this.value = '';
            }
        });
    });

    form.addEventListener('submit', function(event) {
        event.preventDefault();

        const formData = new FormData(form);

        let allFilesSelected = true;
        fileInputs.forEach(input => {
            if (input.required && !input.files[0]) {
                allFilesSelected = false;
            }
        });

        if (!allFilesSelected) {
            Swal.fire({
                icon: 'warning',
                title: 'Documentos incompletos',
                text: 'Por favor, adjunta todos los documentos requeridos.'
            });
            return;
        }

        Swal.fire({
            title: 'Enviando documentos...',
            text: 'Por favor, espera. Esto puede tomar unos momentos.',
            allowOutsideClick: false,
            didOpen: () => Swal.showLoading()
        });

        fetch('REC_controller/guardar_documentos_candidato.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                Swal.fire({
                    icon: 'success',
                    title: '¡Documentos Enviados!',
                    text: 'Tus documentos han sido recibidos correctamente. Gracias por completar tu expediente.',
                    confirmButtonText: 'Cerrar'
                }).then(() => {
                    // Deshabilitar el formulario después del envío exitoso
                    form.querySelectorAll('input, button').forEach(el => el.disabled = true);
                });
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Error al Enviar',
                    text: data.message || 'Ocurrió un error al procesar tus documentos. Por favor, intenta nuevamente.'
                });
            }
        })
        .catch(error => {
            console.error('Error:', error);
            Swal.fire({
                icon: 'error',
                title: 'Error de Conexión',
                text: 'No se pudo comunicar con el servidor. Por favor, verifica tu conexión e intenta nuevamente.'
            });
        });
    });
});
</script>

</body>
</html>