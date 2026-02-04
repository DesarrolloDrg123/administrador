<?php
session_start();
if (!isset($_SESSION['loggedin']) || !$_SESSION['loggedin']) {
    header("Location: index.php");
    exit();
}

$usuario_id = $_SESSION['usuario_id'];
include("src/templates/adminheader.php");
require("config/db.php");
require 'vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$autorizacion_id = $usuario_id;
$alertMessage = '';
$alertType = '';
$folio_formateado = '';

try {
    $conn->begin_transaction();
    $stmt = $conn->prepare("SELECT folio FROM control_folios_rec WHERE id = 1 FOR UPDATE");
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $ultimo_folio = $row['folio'];
    
    $nuevo_folio = $ultimo_folio + 1;
    $folio_formateado = sprintf("%09d", $nuevo_folio);

    $_SESSION['folio_formateado'] = $folio_formateado;

    $conn->commit();
} catch (Exception $e) {
    $conn->rollback();
    die("Error al generar el folio: " . $e->getMessage());
}

?>
<style>
    h1 {
        color: #2c3e50;
        border-bottom: 2px solid #3498db;
        padding-bottom: 10px;
    }
    .validation-feedback {
        display: none; /* Oculto por defecto */
        width: 100%;
        margin-top: 0.25rem;
        font-size: 0.875em; /* 14px si la fuente base es 16px */
        color: #dc3545; /* Color de peligro de Bootstrap */
    }
    
    /* Clase para hacer visible el mensaje de error */
    .validation-feedback.visible {
        display: block;
    }
    /* Botón personalizado con tu color #299dbf */
    .btn-pdf-custom {
        color: #299dbf;
        border-color: #299dbf;
        background-color: transparent;
        transition: all 0.3s ease;
    }

    .btn-pdf-custom:hover {
        background-color: #299dbf;
        color: #ffffff;
        border-color: #299dbf;
    }

    /* Ajuste para que el icono y el texto se vean bien alineados */
    .btn-pdf-custom i {
        margin-right: 5px;
    }
</style>
<div class="container mt-4">
    <div class="card shadow-lg border-0">
        <div class="card-body p-4">
            <form method="POST" id="form-reclutamiento">
                <h2 class="text-center mb-4">Solicitud de Reclutamiento de Personal</h2>

                <div class="row mb-4 align-items-center">
                    <div class="col-md-6 text-center">
                        <img src="img/logo-drg.png" alt="Logo de la Empresa" class="img-fluid" style="max-height: 80px;">
                    </div>
                    <div class="col-md-6 text-center">
                        <h5>Folio: <span class="text-danger fw-bold">
                            <?= isset($_SESSION['folio_formateado']) ? htmlspecialchars($_SESSION['folio_formateado']) : '' ?>
                        </span></h5>
                    </div>
                </div>

                <h5 class="text-primary">1. Información del Puesto</h5>
                <hr>
                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label for="puesto_id" class="form-label">Puesto Solicitado*</label>
                        <div class="input-group">
                            <select class="form-select select-puesto-pdf" name="puesto_id" id="puesto_id" required>
                                <option value="" disabled selected>Selecciona un puesto</option>
                                <?php
                                try {
                                    // MODIFICADO: Agregamos 'documento' a la consulta
                                    $stmt = $conn->prepare("SELECT id, puesto, documento FROM puestos ORDER BY puesto ASC");
                                    $stmt->execute();
                                    $result = $stmt->get_result();
                                    while ($row = $result->fetch_assoc()) {
                                        // MODIFICADO: Guardamos el documento en el atributo data-doc
                                        echo '<option value="' . htmlspecialchars($row['id']) . '" data-doc="' . htmlspecialchars($row['documento'] ?? '') . '">' 
                                            . htmlspecialchars($row['puesto']) . 
                                            '</option>';
                                    }
                                } catch (Exception $e) {
                                    echo '<option value="" disabled>Error al cargar puestos</option>';
                                }
                                ?>
                            </select>
                            <a id="btn-ver-pdf" href="#" target="_blank" class="btn btn-pdf-custom btn-outline-info" style="display: none;">
                                <i class="bi bi-file-earmark-pdf"></i> Ver Descripción de Puesto
                            </a>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label for="fecha_solicitud" class="form-label">Fecha de Solicitud</label>
                        <input type="date" name="fecha_solicitud" id="fecha_solicitud" class="form-control" value="<?php echo date('Y-m-d'); ?>" readonly>
                    </div>
                </div>

                <h5 class="text-primary mt-4">2. Detalles de la Vacante</h5>
                <hr>
                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label for="tipo_vacante" class="form-label">Tipo de Vacante *</label>
                        <select class="form-select" name="tipo_vacante" id="tipo_vacante" required>
                            <option value="" disabled selected>Selecciona el tipo</option>
                            <option value="Nueva">Nueva</option>
                            <option value="Reemplazo">Reemplazo</option>
                        </select>
                    </div>
                    <div class="col-md-6" id="campo_reemplazo" style="display: none;">
                        <label for="reemplaza_a" class="form-label">Reemplaza a: *</label>
                        <input type="text" name="reemplaza_a" id="reemplaza_a" class="form-control" placeholder="Nombre del colaborador a reemplazar">
                    </div>
                </div>

                <div class="mb-3">
                    <label for="justificacion" class="form-label">Justificación de la Vacante *</label>
                    <textarea class="form-control" name="justificacion" id="justificacion" rows="3" required placeholder="Describe brevemente por qué se necesita esta posición..." required></textarea>
                </div>

                <h5 class="text-primary mt-4">3. Perfil del Candidato</h5>
                <hr>
                <div class="mb-3">
                    <label for="descripcion" class="form-label">Principales Responsabilidades y Requisitos *</label>
                    <textarea class="form-control" name="descripcion" id="descripcion" rows="4" placeholder="Ej: Experiencia en ventas, manejo de software X, disponibilidad para viajar..." required></textarea>
                </div>

                <div class="text-center mt-4">
                    <button type="submit" class="btn btn-success px-4">
                        <i class="bi bi-send-fill"></i> Enviar Solicitud
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<br>

<script>
document.addEventListener('DOMContentLoaded', function() {
    
    // --- NUEVA LÓGICA PARA EL BOTÓN PDF ---
    const puestoSelect = document.getElementById('puesto_id');
    const btnPdf = document.getElementById('btn-ver-pdf');

    if (puestoSelect) {
        puestoSelect.addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            const documento = selectedOption.getAttribute('data-doc');

            if (documento && documento.trim() !== "") {
                // Ajusta la ruta si es necesario
                btnPdf.href = "UT_controller/documentos_puestos/" + documento;
                btnPdf.style.display = 'inline-block';
            } else {
                btnPdf.style.display = 'none';
                btnPdf.href = "#";
            }
        });
    }

    // --- (Mantén aquí tu lógica anterior de tipoVacante y Fetch) ---
    const miFormulario = document.getElementById('form-reclutamiento');
    const tipoVacanteSelect = document.getElementById('tipo_vacante');
    const campoReemplazoDiv = document.getElementById('campo_reemplazo');

    // --- LÓGICA DE VISUALIZACIÓN DEL FORMULARIO ---
    if (tipoVacanteSelect) {
        tipoVacanteSelect.addEventListener('change', function() {
            if (this.value === 'Reemplazo') {
                campoReemplazoDiv.style.display = 'block'; // Muestra el campo
            } else {
                campoReemplazoDiv.style.display = 'none'; // Oculta el campo
            }
        });
    }

    // --- LÓGICA DE ENVÍO CON AJAX Y SWEETALERT ---
    if (miFormulario) {
        miFormulario.addEventListener('submit', function(e) {
            e.preventDefault(); // Evita la recarga de la página

            const boton = miFormulario.querySelector('button[type="submit"]');
            const formData = new FormData(miFormulario);

            // 1. Mostrar alerta de "Cargando"
            Swal.fire({
                title: 'Guardando Solicitud',
                html: 'Por favor, espera un momento...',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
            
            boton.disabled = true;

            // 2. Enviar los datos con fetch (AJAX)
            fetch('REC_controller/guardar_vacante.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json()) // Esperamos una respuesta en formato JSON
            .then(data => {
                // 3. Procesar la respuesta del servidor
                if (data.success) {
                    // Si el guardado fue exitoso
                    Swal.fire({
                        icon: 'success',
                        title: '¡Guardado!',
                        text: data.message,
                        timer: 2000, // La alerta se cierra sola en 2 segundos
                        showConfirmButton: false
                    }).then(() => {
                        // Opcional: Recargar la página o limpiar el formulario
                        location.reload(); 
                    });
                } else {
                    // Si el servidor devolvió un error (ej. validación)
                    Swal.fire({
                        icon: 'error',
                        title: 'Error al Guardar',
                        text: data.message // Mostramos el mensaje de error del servidor
                    });
                    boton.disabled = false; // Habilitamos el botón para que pueda corregir y reintentar
                }
            })
            .catch(error => {
                // 4. Capturar errores de red o del servidor
                console.error('Error en la solicitud fetch:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Error de Conexión',
                    text: 'No se pudo comunicar con el servidor. Revisa tu conexión a internet.'
                });
                boton.disabled = false; // Habilitamos el botón
            });
        });
    }
});
</script>

<?php include("src/templates/adminfooter.php"); ?>
