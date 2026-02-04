<?php
session_start();
// Si no hay una sesión activa, redirige al inicio.
if (!isset($_SESSION['loggedin']) || !$_SESSION['loggedin']) {
    header("Location: index.php");
    exit();
}

$usuario_id = $_SESSION['usuario_id'];
include("src/templates/adminheader.php");
require("config/db.php");

// --- Lógica para MOSTRAR el siguiente folio ---
$folio_formateado = '';
try {
    // Prepara y ejecuta la consulta para obtener el último folio.
    $stmt = $conn->prepare("SELECT folio FROM control_folios_mp WHERE id = 1");
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    // Calcula el siguiente folio y lo formatea a 9 dígitos.
    $siguiente_folio = ($row ? $row['folio'] : 0) + 1;
    $folio_formateado = sprintf("%09d", $siguiente_folio);
} catch (Exception $e) {
    // En caso de error, muestra un mensaje.
    $folio_formateado = 'Error';
}

// --- Lógica para obtener datos para los <select> desde la base de datos ---
try {
    // Obtener sucursales
    $resultado_sucursales = $conn->query("SELECT id, sucursal FROM sucursales ORDER BY sucursal");
    if (!$resultado_sucursales) throw new Exception("Error en la consulta de sucursales.");
    $sucursales = $resultado_sucursales->fetch_all(MYSQLI_ASSOC);

    // Obtener puestos
    $resultado_puestos = $conn->query("SELECT id, puesto, documento FROM puestos ORDER BY puesto");
    if (!$resultado_puestos) throw new Exception("Error en la consulta de puestos.");
    $puestos = $resultado_puestos->fetch_all(MYSQLI_ASSOC);

    // MEJORA: La consulta ahora une con la tabla de puestos para obtener el ID y el nombre del puesto.
    $resultado_usuarios = $conn->query("SELECT u.id, u.nombre as usuario, u.estatus, u.puesto, u.email, p.id AS puesto_id
                                        FROM usuarios u
                                        LEFT JOIN puestos p ON u.puesto = p.puesto
                                        ORDER BY u.nombre");
    if (!$resultado_usuarios) throw new Exception("Error en la consulta de usuarios.");
    $usuarios_all = $resultado_usuarios->fetch_all(MYSQLI_ASSOC);

    // Crear una lista separada solo con usuarios activos para asignaciones de respaldo.
    $usuarios_activos = array_filter($usuarios_all, function($usuario) {
        return $usuario['estatus'] == '1';
    });

} catch (Exception $e) {
    // Manejo de errores críticos al cargar datos.
    die("Error crítico al cargar datos del formulario: " . $e->getMessage() . "<br><b>Error de MySQL:</b> " . $conn->error);
}
?>
<!-- Estilos del formulario dinámico -->
<style>
    .card {
        border-radius: 0.75rem;
        border: none;
        box-shadow: 0 4px 6px rgba(0,0,0,0.1);
    }
    .dynamic-form-section {
        /* Se ocultan por defecto y se muestran con JS */
        display: none;
    }
    .form-section-title {
        font-size: 1.1rem;
        font-weight: 600;
        color: #495057;
        border-bottom: 2px solid #dee2e6;
        padding-bottom: 0.5rem;
        margin-top: 1.5rem;
        margin-bottom: 1rem;
    }
    .status-badge {
        font-size: 0.8rem;
        padding: 0.25rem 0.6rem;
        border-radius: 0.5rem;
        color: white;
        font-weight: bold;
        display: inline-block;
        margin-left: 10px;
    }
    .status-active {
        background-color: #28a745;
    }
    .status-inactive {
        background-color: #dc3545;
    }
    .select2-container .select2-selection--multiple {
        border: 1px solid #ced4da !important;
        min-height: 38px !important;
        padding: 0.375rem 0.75rem;
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
    <div class="card">
        <div class="card-body p-4 p-md-5">
            <form method="POST" enctype="multipart/form-data" id="solicitudForm">
                
                <!-- Encabezado con Título, Logo y Folio -->
                <h2 class="text-center mb-4">Solicitud de Accesos, Bajas y Cambios de Usuarios</h2>
                <div class="row mb-4 align-items-center">
                    <div class="col-md-6 text-center">
                        <img src="img/logo-drg.png" alt="Logo DRG" class="img-fluid" style="max-height: 80px;">
                    </div>
                    <div class="col-md-6 text-center">
                        <h5>Folio: <span class="text-danger fw-bold">
                            <?= htmlspecialchars($folio_formateado) ?>
                        </span></h5>
                    </div>
                </div>

                <!-- CAMPOS INICIALES (SIEMPRE VISIBLES) -->
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Solicitante</label><br>
                        <span class="fw-bold"><?php echo htmlspecialchars($_SESSION['nombre']) ?></span>
                    </div>
                    <div class="col-md-6">
                        <label for="tipo_solicitud" class="form-label">Tipo de Solicitud <span class="text-danger">*</span></label>
                        <select class="form-select" id="tipo_solicitud" name="tipo_solicitud" required>
                            <option selected disabled value="">Selecciona una opción...</option>
                            <option value="alta">Alta de usuario</option>
                            <option value="cambio_puesto">Alta por cambio de puesto</option>
                            <option value="remplazo">Alta por remplazo de usuario</option>
                            <option value="practicante">Practicante</option>
                            <option value="baja">Baja de usuario</option>
                        </select>
                        <!-- Contenedor para mostrar el código del formulario -->
                        <div id="codigo_form_container" class="mt-2 text-center" style="display:none;">
                            <span class="fw-bold"><span id="codigo_form_text"></span></span>
                        </div>
                    </div>
                </div>

                <!-- CONTENEDOR PARA LAS SECCIONES DINÁMICAS -->
                <div id="dynamic-form-container">
                    <!-- SECCIÓN: ALTA / REEMPLAZO / PRACTICANTE -->
                    <div id="form-alta-remplazo-practicante" class="dynamic-form-section">
                        <div class="mb-3 dynamic-field" data-type="remplazo">
                            <h3 class="form-section-title">Colaborador a Reemplazar</h3>
                            <div class="row g-3 align-items-end">
                                <div class="col-md-4">
                                    <label for="usuario_remplazo_id" class="form-label">Usuario a reemplazar <span class="text-danger">*</span></label>
                                    <select class="form-select" id="usuario_remplazo_id" name="usuario_remplazo_id">
                                        <option selected disabled value="">Elige un usuario...</option>
                                        <?php foreach ($usuarios_all as $usuario): ?>
                                            <option value="<?= $usuario['id'] ?>" data-puesto="<?= htmlspecialchars($usuario['puesto']) ?>" data-estatus="<?= $usuario['estatus'] ?>">
                                                <?= htmlspecialchars($usuario['usuario']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>    
                                <div class="col-md-4">
                                    <label class="form-label">Estatus en el sistema</label>
                                    <span id="estatus_remplazo" class="status-badge"></span>
                                </div> 
                                <div class="col-md-4">
                                     <label for="puesto_remplazo" class="form-label">Puesto del usuario a reemplazar</label>
                                     <input type="text" class="form-control" id="puesto_remplazo" name="puesto_remplazo" readonly>
                                </div> 
                            </div>
                        </div>
                        <h3 class="form-section-title">Datos del Colaborador</h3>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="sucursal_alta" class="form-label">Sucursal <span class="text-danger">*</span></label>
                                <select class="form-select" id="sucursal_alta" name="sucursal" required>
                                    <option selected disabled value="">Elige una sucursal...</option>
                                    <?php foreach ($sucursales as $sucursal): ?>
                                        <option value="<?= $sucursal['id'] ?>"><?= htmlspecialchars($sucursal['sucursal']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                             <div class="col-md-6">
                                <label for="numero_empleado" class="form-label">No. de Empleado <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="numero_empleado" name="numero_empleado" required>
                            </div>
                        </div>
                        <div class="row g-3 mt-2">
                            <div class="col-md-4"><label for="nombres" class="form-label">Nombre(s) <span class="text-danger">*</span></label><input type="text" class="form-control" id="nombres" name="nombres" required></div>
                            <div class="col-md-4"><label for="apellido_paterno" class="form-label">Apellido Paterno <span class="text-danger">*</span></label><input type="text" class="form-control" id="apellido_paterno" name="apellido_paterno" required></div>
                            <div class="col-md-4"><label for="apellido_materno" class="form-label">Apellido Materno <span class="text-danger">*</span></label><input type="text" class="form-control" id="apellido_materno" name="apellido_materno" required></div>
                        </div>
                        <div class="row g-3 mt-2">
                            <div class="col-md-4"><label for="nombre_predilecto" class="form-label">Nombre Predilecto <span class="text-danger">*</span></label><input type="text" class="form-control" id="nombre_predilecto" name="nombre_predilecto" required></div>
                            <!-- CAMBIO: Se añade un ID al contenedor del campo Puesto -->
                            <div class="col-md-8" id="puesto-container">
                                <label for="puesto_alta" class="form-label">Puesto <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <select class="form-select select-puesto-pdf" id="puesto_alta" name="puesto_alta" data-btn="btn-pdf-alta">
                                        <option selected disabled value="">Elige un puesto...</option>
                                        <?php foreach ($puestos as $puesto): ?>
                                            <option value="<?= $puesto['id'] ?>" data-doc="<?= htmlspecialchars($puesto['documento'] ?? '') ?>">
                                                <?= htmlspecialchars($puesto['puesto']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <a id="btn-pdf-alta" href="#" target="_blank" class="btn btn-pdf-custom btn-outline-info" style="display:none;">
                                        <i class="fas fa-file-pdf"></i> Ver descripción
                                    </a>
                                </div>
                            </div>
                        </div>
                        <div class="row g-3 mt-2">
                             <div class="col-md-4"><label for="telefono" class="form-label">Teléfono <span class="text-danger">*</span></label><input type="tel" class="form-control" id="telefono" name="telefono" required></div>
                            <div class="col-md-4"><label for="fecha_nacimiento" class="form-label">Fecha de Nacimiento <span class="text-danger">*</span></label><input type="date" class="form-control" id="fecha_nacimiento" name="fecha_nacimiento" required></div>
                             <div class="col-md-4"><label for="fecha_ingreso" class="form-label">Fecha de Ingreso <span class="text-danger">*</span></label><input type="date" class="form-control" id="fecha_ingreso" name="fecha_ingreso" required></div>
                        </div>
                        <div class="mt-3"><label for="direccion" class="form-label">Dirección <span class="text-danger">*</span></label><textarea class="form-control" id="direccion" name="direccion" rows="2" required></textarea></div>
                        <div class="mb-3 dynamic-field" data-type="practicante">
                            <div class="mt-3"><label for="actividades_practicante" class="form-label">Actividades del Practicante<span class="text-danger">*</span></label><textarea class="form-control" id="actividades_practicante" name="actividades_practicante" rows="2" required></textarea></div>
                        </div>
                    </div>

                    <!-- SECCIÓN: CAMBIO DE PUESTO -->
                    <div id="form-cambio-puesto" class="dynamic-form-section">
                        <h3 class="form-section-title">Identificación del Colaborador</h3>
                         <div class="row g-3">
                             <div class="col-md-8">
                                <label for="usuario_cambio_id" class="form-label">Usuario a dar cambio <span class="text-danger">*</span></label>
                                <select class="form-select" id="usuario_cambio_id" name="usuario_cambio_id" required>
                                    <option selected disabled value="">Elige un usuario...</option>
                                    <?php foreach ($usuarios_all as $usuario): ?>
                                        <option value="<?= $usuario['id'] ?>" data-puesto-id="<?= $usuario['puesto_id'] ?>" data-puesto-nombre="<?= htmlspecialchars($usuario['puesto']) ?>" data-estatus="<?= $usuario['estatus'] ?>">
                                            <?= htmlspecialchars($usuario['usuario']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Estatus en el sistema</label>
                                <span id="estatus_cambio" class="status-badge"></span>
                            </div> 
                        </div>
                        <h3 class="form-section-title">Detalles del Cambio</h3>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="puesto_anterior_display" class="form-label">Puesto Anterior</label>
                                <input type="text" class="form-control" id="puesto_anterior_display" readonly>
                                <input type="hidden" id="puesto_anterior" name="puesto_anterior">
                            </div>
                            <div class="col-md-6">
                                <label for="puesto_nuevo" class="form-label">Puesto Nuevo <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <select class="form-select select-puesto-pdf" id="puesto_nuevo" name="puesto_nuevo" data-btn="btn-pdf-nuevo" required>
                                        <option selected disabled value="">Elige un puesto...</option>
                                        <?php foreach ($puestos as $puesto): ?>
                                            <option value="<?= $puesto['id'] ?>" data-doc="<?= htmlspecialchars($puesto['documento'] ?? '') ?>">
                                                <?= htmlspecialchars($puesto['puesto']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <a id="btn-pdf-nuevo" href="#" target="_blank" class="btn btn-pdf-custom btn-outline-info" style="display:none;">
                                        <i class="fas fa-file-pdf"></i> Ver descripción
                                    </a>
                                </div>
                            </div>
                        </div>
                        <div class="mb-3 mt-3"><label for="justificacion_cambio" class="form-label">Justificación del Cambio <span class="text-danger">*</span></label><textarea class="form-control" id="justificacion_cambio" name="justificacion_cambio" rows="3" required></textarea></div>
                    </div>

                    <!-- SECCIÓN: BAJA DE USUARIO -->
                    <div id="form-baja" class="dynamic-form-section">
                        <h3 class="form-section-title">Datos del Colaborador a dar de Baja</h3>
                        <div class="row g-3 align-items-end">
                            <div class="col-md-8">
                                <label for="usuario_baja_id" class="form-label">Usuario a dar de baja <span class="text-danger">*</span></label>
                                <select class="form-select" id="usuario_baja_id" name="usuario_baja_id" required>
                                    <option selected disabled value="">Elige un usuario...</option>
                                    <?php foreach ($usuarios_all as $usuario): ?>
                                        <option value="<?= $usuario['id'] ?>">
                                            <?= htmlspecialchars($usuario['usuario']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">¿Es foráneo? <span class="text-danger">*</span></label>
                                <div class="form-check"><input class="form-check-input" type="radio" name="es_foraneo" id="foraneo_si" value="1" required><label class="form-check-label" for="foraneo_si">Sí</label></div>
                                <div class="form-check"><input class="form-check-input" type="radio" name="es_foraneo" id="foraneo_no" value="0" checked><label class="form-check-label" for="foraneo_no">No</label></div>
                            </div>
                        </div>
                        <h3 class="form-section-title">Detalles de la Baja</h3>
                        <div class="mb-3">
                            <label for="colaborador_respaldo_id" class="form-label">Asignar respaldo (Drive, Correo, PC, Documentos, etc.) a  <span class="text-danger">*</span></label>
                            <select class="form-select" id="colaborador_respaldo_id" name="colaborador_respaldo_id" required>
                                <option selected disabled value="">Elige un usuario activo...</option>
                                <?php foreach ($usuarios_activos as $usuario): ?>
                                    <option value="<?= $usuario['id'] ?>">
                                        <?= htmlspecialchars($usuario['usuario']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3" id="recoge_productos_container" style="display: none;">
                            <label for="colaborador_recoge_productos" class="form-label">Colaborador que recogerá productos <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="colaborador_recoge_productos" name="colaborador_recoge_productos" required>
                        </div>
                        <div class="mb-3"><label class="form-label">¿Es Baja por Reemplazo? <span class="text-danger">*</span></label>
                            <div class="form-check"><input class="form-check-input" type="radio" name="es_baja_por_reemplazo" id="reemplazo_si" value="1" required><label class="form-check-label" for="reemplazo_si">Sí</label></div>
                            <div class="form-check"><input class="form-check-input" type="radio" name="es_baja_por_reemplazo" id="reemplazo_no" value="0" checked><label class="form-check-label" for="reemplazo_no">No</label></div>
                        </div>
                        <div class="mb-3">
                            <label for="notificar_usuarios" class="form-label">Notificar de la baja a (opcional):</label>
                            <select class="form-select" id="notificar_usuarios" name="notificar_usuarios[]" multiple="multiple">
                                <?php foreach ($usuarios_activos as $usuario): ?>
                                    <option value="<?= $usuario['id'] ?>">
                                        <?= htmlspecialchars($usuario['usuario']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div id="form-comun-final" class="dynamic-form-section mb-3">
                        <div class="row g-3">
                            <div class="col-12"><label for="archivo_evidencia_path" class="form-label">Adjuntar Archivo (opcional)</label><input class="form-control" type="file" id="archivo_evidencia_path" name="archivo_evidencia_path"></div>
                        </div>
                        <div class="mt-3">
                            <label for="observaciones" class="form-label">Observaciones (Opcional)</label>
                            <textarea class="form-control" id="observaciones" name="observaciones" rows="2"></textarea>
                        </div>
                    </div>
                </div>
                
                <div id="submit-button-container" class="mt-4 pt-4 border-top" style="display: none;">
                    <button type="submit" class="btn btn-primary w-100 py-2"><i class="fas fa-paper-plane me-2"></i> Enviar Solicitud</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
$(document).ready(function () { // Se usa jQuery para asegurar que todo cargue primero
    
    // ==========================================================
    // INICIALIZACIÓN DE SELECT2 PARA EL CAMPO DE NOTIFICACIONES
    // ==========================================================
    $('#notificar_usuarios').select2({
        placeholder: "Busca y selecciona uno o más usuarios",
        allowClear: true, // Permite borrar la selección
        width: '100%'     // Se asegura que ocupe todo el ancho disponible
    });

    // ==========================================================
    // TU CÓDIGO ANTERIOR (SIN CAMBIOS, SOLO MOVIDO AQUÍ DENTRO)
    // ==========================================================
    const form = document.getElementById('solicitudForm');
    const tipoSolicitudSelect = document.getElementById('tipo_solicitud');
    const submitButtonContainer = document.getElementById('submit-button-container');
    const radiosForaneo = document.querySelectorAll('input[name="es_foraneo"]');
        radiosForaneo.forEach(radio => {
            radio.addEventListener('change', handleForaneoChange);
        });
    
    const formSections = {
        'alta': ['form-alta-remplazo-practicante', 'form-comun-final'],
        'remplazo': ['form-alta-remplazo-practicante', 'form-comun-final'],
        'practicante': ['form-alta-remplazo-practicante', 'form-comun-final'],
        'cambio_puesto': ['form-cambio-puesto', 'form-comun-final'],
        'baja': ['form-baja', 'form-comun-final']
    };
    
    // Función para marcar campos como requeridos originalmente
    form.querySelectorAll('[required]').forEach(el => el.setAttribute('data-original-required', 'true'));

    // Función para mostrar/ocultar secciones y gestionar 'required'
    function manageSection(sectionId, show) {
        const section = document.getElementById(sectionId);
        if (!section) return;
        section.style.display = show ? 'block' : 'none';
        section.querySelectorAll('[data-original-required]').forEach(input => {
            if (!input.closest('.dynamic-field')) {
                input.required = show;
            }
        });
    }

    // Función para campos especiales dentro de una sección
    function handleSpecialFields(type) {
        const altaForm = document.getElementById('form-alta-remplazo-practicante');
        if (!altaForm) return;
        altaForm.querySelectorAll('.dynamic-field').forEach(field => {
            const isVisible = field.getAttribute('data-type') === type;
            field.style.display = isVisible ? 'block' : 'none';
            field.querySelectorAll('[data-original-required]').forEach(input => input.required = isVisible);
        });
    }
    
    // Función principal que actualiza la interfaz del formulario
    function updateFormUI() {
        const selectedValue = tipoSolicitudSelect.value;
        document.querySelectorAll('.dynamic-form-section').forEach(section => manageSection(section.id, false));
        
        const sectionsToShow = formSections[selectedValue] || [];
        if (sectionsToShow.length > 0) {
            sectionsToShow.forEach(sectionId => manageSection(sectionId, true));
            handleSpecialFields(selectedValue);
            submitButtonContainer.style.display = 'block';
        } else {
            submitButtonContainer.style.display = 'none';
        }
        
        // Lógica para el campo 'Puesto' en caso de practicantes
        const puestoContainer = document.getElementById('puesto-container');
        const puestoSelect = document.getElementById('puesto_alta');
        const altaSection = document.getElementById('form-alta-remplazo-practicante');

        if (altaSection.style.display === 'block') {
            if (selectedValue === 'practicante') {
                puestoContainer.style.display = 'none';
                puestoSelect.required = false;
            } else {
                puestoContainer.style.display = 'block';
                if(puestoSelect.hasAttribute('data-original-required')) {
                   puestoSelect.required = true;
                }
            }
        }
    }
    
    function handleForaneoChange() {
        const foraneoSeleccionado = document.querySelector('input[name="es_foraneo"]:checked');
        const recogeProductosContainer = document.getElementById('recoge_productos_container');
        const recogeProductosInput = document.getElementById('colaborador_recoge_productos');
    
        if (foraneoSeleccionado && foraneoSeleccionado.value === '1') {
            // Si se selecciona "Sí" es foráneo
            recogeProductosContainer.style.display = 'block';
            recogeProductosInput.required = true; // Hace el campo obligatorio
        } else {
            // Si se selecciona "No" o no hay selección
            recogeProductosContainer.style.display = 'none';
            recogeProductosInput.required = false; // El campo deja de ser obligatorio
            recogeProductosInput.value = ''; // Limpia el valor si se oculta
        }
    }

    // Lógica para la sección 'Cambio de Puesto'
    const usuarioCambioSelect = document.getElementById('usuario_cambio_id');
    const puestoAnteriorDisplayInput = document.getElementById('puesto_anterior_display');
    const puestoAnteriorHiddenInput = document.getElementById('puesto_anterior');

    tipoSolicitudSelect.addEventListener('change', updateFormUI);

    const codigoFormContainer = document.getElementById('codigo_form_container');
    const codigoFormText = document.getElementById('codigo_form_text');

    const codigosForm = {
        'alta': 'FOR-TEI-001',
        'cambio_puesto': 'FOR-TEI-002',
        'remplazo': 'FOR-TEI-006',
        'practicante': 'FOR-TEI-007',
        'baja': 'FOR-TEI-008'
    };

    // Cuando cambia el tipo de solicitud
    tipoSolicitudSelect.addEventListener('change', function () {
        const tipo = this.value;
        if (codigosForm[tipo]) {
            codigoFormText.textContent = codigosForm[tipo];
            codigoFormContainer.style.display = 'block';
        } else {
            codigoFormContainer.style.display = 'none';
            codigoFormText.textContent = '';
        }
        updateFormUI();
    });

    
    usuarioCambioSelect.addEventListener('change', function() {
        const selectedOption = this.options[this.selectedIndex];
        const estatusCambioSpan = document.getElementById('estatus_cambio');
        
        if (!selectedOption.value) {
            estatusCambioSpan.textContent = '';
            estatusCambioSpan.className = 'status-badge';
            puestoAnteriorDisplayInput.value = '';
            puestoAnteriorHiddenInput.value = '';
            return;
        }
        
        const estatus = selectedOption.dataset.estatus;
        const puestoNombre = selectedOption.dataset.puestoNombre;
        const puestoId = selectedOption.dataset.puestoId;

        estatusCambioSpan.textContent = estatus === '1' ? 'Activo' : 'Inactivo';
        estatusCambioSpan.className = 'status-badge ' + (estatus === '1' ? 'status-active' : 'status-inactive');
        
        puestoAnteriorDisplayInput.value = puestoNombre || 'No definido';
        puestoAnteriorHiddenInput.value = puestoId || '';
    });
    
    // Lógica para el envío del formulario con Fetch y SweetAlert
    form.addEventListener('submit', function(event) {
        event.preventDefault();
        Swal.fire({
            title: 'Procesando Solicitud',
            text: 'Por favor, espera un momento...',
            allowOutsideClick: false,
            didOpen: () => Swal.showLoading()
        });

        const formData = new FormData(form);
        fetch('ABC_controller/procesar_solicitud.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                Swal.fire({
                    icon: 'success',
                    title: data.message,
                    text: 'Se ha asignado el folio: ' + data.folio,
                    confirmButtonText: 'Aceptar'
                }).then(() => location.reload());
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Error al enviar la solicitud',
                    text: data.message,
                    confirmButtonText: 'Cerrar'
                });
            }
        })
        .catch(error => {
            Swal.fire({
                icon: 'error',
                title: 'Error de Conexión',
                text: 'No se pudo comunicar con el servidor.',
                confirmButtonText: 'Cerrar'
            });
            console.error('Error en fetch:', error);
        });
    });
    
    // Llamadas iniciales para configurar la UI
    updateFormUI();
    handleForaneoChange(); // Si tienes esta función, asegúrate de que esté definida
});

$(document).ready(function() {
    $('#puesto_alta').on('change', function() {
        // Obtenemos la opción seleccionada y su atributo data-doc
        const selectedOption = $(this).find(':selected');
        const documento = selectedOption.data('doc');
        const btnPdf = $('#btn-ver-pdf-alta');
        const msgSinArchivo = $('#sin-archivo-msg');

        if (documento && documento.trim() !== "") {
            // Si hay documento, actualizamos el link y mostramos el botón
            btnPdf.attr('href', 'UT_controller/documentos_puestos/' + documento);
            btnPdf.fadeIn();
            msgSinArchivo.hide();
        } else {
            // Si no hay documento, ocultamos el botón y mostramos el aviso opcional
            btnPdf.fadeOut();
            msgSinArchivo.show();
        }
    });
    // --- 1. LÓGICA PARA RELLENAR DATOS EN "ALTA POR REEMPLAZO" ---
    $('#usuario_remplazo_id').on('change', function() {
        const selectedOption = $(this).find(':selected');
        const puestoNombre = selectedOption.data('puesto'); // Viene del data-puesto en el option
        const estatus = selectedOption.data('estatus');    // Viene del data-estatus en el option

        // Rellenamos el campo de texto del puesto
        $('#puesto_remplazo').val(puestoNombre || 'No definido');

        // Actualizamos el badge de estatus
        const badge = $('#estatus_remplazo');
        if (estatus == '1') {
            badge.text('Activo').removeClass('status-inactive').addClass('status-active');
        } else {
            badge.text('Inactivo').removeClass('status-active').addClass('status-inactive');
        }
    });

    // --- 2. LÓGICA UNIFICADA PARA MOSTRAR PDF DE PUESTOS ---
    $('.select-puesto-pdf').on('change', function() {
        const option = $(this).find(':selected');
        const documento = option.data('doc'); // Nombre del archivo PDF
        const btnId = $(this).data('btn');    // ID del botón vinculado (btn-pdf-alta o btn-pdf-nuevo)
        const btn = $('#' + btnId);

        if (documento && documento.trim() !== "") {
            // IMPORTANTE: Ajusta la ruta a tu carpeta real de documentos
            btn.attr('href', 'UT_controller/documentos_puestos/' + documento);
            btn.fadeIn();
        } else {
            btn.fadeOut();
        }
    });
});
</script>

<?php
include("src/templates/adminfooter.php");
?>

