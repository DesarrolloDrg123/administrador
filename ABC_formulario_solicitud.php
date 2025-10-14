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

// --- LÓGICA UNIFICADA: DETECTAR MODO (CREAR vs EDITAR) ---
$modo_edicion = false;
$solicitud_data = [];
$folio_a_mostrar = '';

// 1. Si se proporciona un ID en la URL, entramos en modo EDICIÓN
if (isset($_GET['id_solicitud']) && is_numeric($_GET['id_solicitud'])) {
    $modo_edicion = true;
    $id_solicitud = intval($_GET['id_solicitud']);

    $stmt_edit = $conn->prepare("SELECT * FROM solicitudes_movimientos_personal WHERE id = ?");
    $stmt_edit->bind_param("i", $id_solicitud);
    $stmt_edit->execute();
    $result_edit = $stmt_edit->get_result();
    
    if ($result_edit->num_rows === 1) {
        $solicitud_data = $result_edit->fetch_assoc();
        $folio_a_mostrar = $solicitud_data['folio']; // Usamos el folio existente
    } else {
        die("Error: La solicitud especificada no existe.");
    }
}

// 2. Si NO estamos en modo edición, es una NUEVA solicitud y calculamos el siguiente folio
if (!$modo_edicion) {
    try {
        $stmt_folio = $conn->prepare("SELECT folio FROM control_folios_mp WHERE id = 1");
        $stmt_folio->execute();
        $row_folio = $stmt_folio->get_result()->fetch_assoc();
        $siguiente_folio = ($row_folio ? $row_folio['folio'] : 0) + 1;
        $folio_a_mostrar = sprintf("%09d", $siguiente_folio);
    } catch (Exception $e) {
        $folio_a_mostrar = 'Error';
    }
}

// --- Lógica para obtener catálogos para los <select> ---
try {
    $sucursales = $conn->query("SELECT id, sucursal FROM sucursales ORDER BY sucursal")->fetch_all(MYSQLI_ASSOC);
    $puestos = $conn->query("SELECT id, puesto FROM puestos ORDER BY puesto")->fetch_all(MYSQLI_ASSOC);
    
    // CORRECCIÓN: La consulta ahora une con la tabla de puestos usando puesto_id para mayor precisión.
    $resultado_usuarios = $conn->query("SELECT u.id, u.nombre as usuario, u.estatus, u.puesto, p.id AS puesto_id
                                        FROM usuarios u
                                        LEFT JOIN puestos p ON u.puesto = p.puesto
                                        ORDER BY u.nombre");
    if (!$resultado_usuarios) throw new Exception("Error en la consulta de usuarios.");
    $usuarios_all = $resultado_usuarios->fetch_all(MYSQLI_ASSOC);

    $usuarios_activos = array_filter($usuarios_all, function($usuario) {
        return $usuario['estatus'] == '1';
    });

} catch (Exception $e) {
    die("Error crítico al cargar datos del formulario: " . $e->getMessage());
}
?>
<!-- Estilos del formulario (sin cambios) -->
<style>
    .card { border-radius: 0.75rem; border: none; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
    .dynamic-form-section { display: none; }
    .form-section-title { font-size: 1.1rem; font-weight: 600; color: #495057; border-bottom: 2px solid #dee2e6; padding-bottom: 0.5rem; margin-top: 1.5rem; margin-bottom: 1rem; }
    .status-badge { font-size: 0.8rem; padding: 0.25rem 0.6rem; border-radius: 0.5rem; color: white; font-weight: bold; display: inline-block; margin-left: 10px; }
    .status-active { background-color: #28a745; }
    .status-inactive { background-color: #dc3545; }
    .motivo-devolucion { background-color: #fff3cd; border-left: 5px solid #ffc107; color: #856404; padding: 15px; border-radius: 8px; margin-bottom: 20px; }
    .motivo-devolucion h5 { margin-top: 0; font-weight: bold; }
</style>

<div class="container mt-4">
    <div class="card">
        <div class="card-body p-4 p-md-5">
            <form method="POST" enctype="multipart/form-data" id="solicitudForm" novalidate>
                
                <h2 class="text-center mb-4">FOR-TEI-001 Solicitud de Accesos, Bajas y Cambios de Usuarios</h2>
                <div class="row mb-4 align-items-center">
                    <div class="col-md-6 text-center"><img src="img/logo-drg.png" alt="Logo DRG" class="img-fluid" style="max-height: 80px;"></div>
                    <div class="col-md-6 text-center"><h5>Folio: <span class="text-danger fw-bold"><?= htmlspecialchars($folio_a_mostrar) ?></span></h5></div>
                </div>

                <?php if ($modo_edicion && !empty($solicitud_data['motivo'])): ?>
                    <div class="motivo-devolucion">
                        <h5>⚠️ Solicitud Devuelta para Corrección</h5>
                        <p><strong>Motivo:</strong> <?= htmlspecialchars($solicitud_data['motivo']); ?></p>
                        <p>Por favor, revisa la información, realiza los ajustes necesarios y vuelve a enviar el formulario.</p>
                    </div>
                <?php endif; ?>

                <?php if ($modo_edicion): ?>
                    <input type="hidden" name="id_solicitud" value="<?= htmlspecialchars($solicitud_data['id']); ?>">
                <?php endif; ?>

                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Solicitante</label><br>
                        <span class="fw-bold"><?php echo htmlspecialchars($_SESSION['nombre']) ?></span>
                    </div>
                    <div class="col-md-6">
                        <label for="tipo_solicitud" class="form-label">Tipo de Solicitud <span class="text-danger">*</span></label>
                        <select class="form-select" id="tipo_solicitud" name="tipo_solicitud" required>
                            <option selected disabled value="">Selecciona una opción...</option>
                            <?php
                                $tipo_map_reverse = [
                                    'Alta de usuario' => 'alta', 'Alta por cambio de puesto' => 'cambio_puesto',
                                    'Alta por remplazo de usuario' => 'remplazo', 'Practicante' => 'practicante',
                                    'Baja de usuario' => 'baja'
                                ];
                                $tipo_seleccionado = $modo_edicion ? ($tipo_map_reverse[$solicitud_data['tipo_solicitud']] ?? '') : '';
                            ?>
                            <option value="alta" <?= $tipo_seleccionado == 'alta' ? 'selected' : '' ?>>Alta de usuario</option>
                            <option value="cambio_puesto" <?= $tipo_seleccionado == 'cambio_puesto' ? 'selected' : '' ?>>Alta por cambio de puesto</option>
                            <option value="remplazo" <?= $tipo_seleccionado == 'remplazo' ? 'selected' : '' ?>>Alta por remplazo de usuario</option>
                            <option value="practicante" <?= $tipo_seleccionado == 'practicante' ? 'selected' : '' ?>>Practicante</option>
                            <option value="baja" <?= $tipo_seleccionado == 'baja' ? 'selected' : '' ?>>Baja de usuario</option>
                        </select>
                    </div>
                </div>

                <div id="dynamic-form-container">
                    <!-- SECCIÓN: ALTA / REMPLAZO / PRACTICANTE -->
                    <div id="form-alta-remplazo-practicante" class="dynamic-form-section">
                        <div class="mb-3 dynamic-field" data-type="remplazo">
                            <h3 class="form-section-title">Colaborador a Reemplazar</h3>
                            <div class="row g-3 align-items-end">
                                <div class="col-md-4">
                                    <label for="usuario_remplazo_id" class="form-label">Usuario a reemplazar <span class="text-danger">*</span></label>
                                    <select class="form-select" id="usuario_remplazo_id" name="usuario_remplazo_id" required>
                                        <option selected disabled value="">Elige un usuario...</option>
                                        <?php foreach ($usuarios_all as $usuario): ?>
                                            <option value="<?= $usuario['id'] ?>" data-puesto="<?= htmlspecialchars($usuario['puesto']) ?>" data-estatus="<?= $usuario['estatus'] ?>" <?= ($solicitud_data['usuario_remplazo_id'] ?? '') == $usuario['id'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($usuario['usuario']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-4"><label class="form-label">Estatus en el sistema</label><span id="estatus_remplazo" class="status-badge"></span></div>
                                <div class="col-md-4"><label for="puesto_remplazo" class="form-label">Puesto del usuario</label><input type="text" class="form-control" id="puesto_remplazo" name="puesto_remplazo" readonly></div>
                            </div>
                        </div>
                        <h3 class="form-section-title">Datos del Colaborador</h3>
                        <div class="row g-3">
                            <div class="col-md-6"><label for="sucursal_alta" class="form-label">Sucursal <span class="text-danger">*</span></label><select class="form-select" id="sucursal_alta" name="sucursal" required>
                                    <option selected disabled value="">Elige una sucursal...</option>
                                    <?php foreach ($sucursales as $sucursal): ?><option value="<?= $sucursal['id'] ?>" <?= ($solicitud_data['sucursal_id'] ?? '') == $sucursal['id'] ? 'selected' : '' ?>><?= htmlspecialchars($sucursal['sucursal']) ?></option><?php endforeach; ?>
                                </select></div>
                            <div class="col-md-6"><label for="numero_empleado" class="form-label">No. de Empleado <span class="text-danger">*</span></label><input type="text" class="form-control" id="numero_empleado" name="numero_empleado" value="<?= htmlspecialchars($solicitud_data['numero_empleado'] ?? '') ?>" required></div>
                        </div>
                        <div class="row g-3 mt-2">
                            <div class="col-md-4"><label for="nombres" class="form-label">Nombre(s) <span class="text-danger">*</span></label><input type="text" class="form-control" id="nombres" name="nombres" value="<?= htmlspecialchars($solicitud_data['nombres'] ?? '') ?>" required></div>
                            <div class="col-md-4"><label for="apellido_paterno" class="form-label">Apellido Paterno <span class="text-danger">*</span></label><input type="text" class="form-control" id="apellido_paterno" name="apellido_paterno" value="<?= htmlspecialchars($solicitud_data['apellido_paterno'] ?? '') ?>" required></div>
                            <div class="col-md-4"><label for="apellido_materno" class="form-label">Apellido Materno <span class="text-danger">*</span></label><input type="text" class="form-control" id="apellido_materno" name="apellido_materno" value="<?= htmlspecialchars($solicitud_data['apellido_materno'] ?? '') ?>" required></div>
                        </div>
                        <div class="row g-3 mt-2">
                            <div class="col-md-4"><label for="nombre_predilecto" class="form-label">Nombre Predilecto <span class="text-danger">*</span></label><input type="text" class="form-control" id="nombre_predilecto" name="nombre_predilecto" value="<?= htmlspecialchars($solicitud_data['nombre_predilecto'] ?? '') ?>" required></div>
                            <!-- CAMBIO: Se añade un ID al contenedor del campo Puesto para controlarlo con JS -->
                            <div class="col-md-8" id="puesto-container">
                                <label for="puesto_alta" class="form-label">Puesto <span class="text-danger">*</span></label>
                                <select class="form-select" id="puesto_alta" name="puesto_alta" required>
                                    <option selected disabled value="">Elige un puesto...</option>
                                    <?php foreach ($puestos as $puesto): ?><option value="<?= $puesto['id'] ?>" <?= ($solicitud_data['puesto_id'] ?? '') == $puesto['id'] ? 'selected' : '' ?>><?= htmlspecialchars($puesto['puesto']) ?></option><?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="row g-3 mt-2">
                            <div class="col-md-4"><label for="telefono" class="form-label">Teléfono <span class="text-danger">*</span></label><input type="tel" class="form-control" id="telefono" name="telefono" value="<?= htmlspecialchars($solicitud_data['telefono'] ?? '') ?>" required></div>
                            <div class="col-md-4"><label for="fecha_nacimiento" class="form-label">Fecha de Nacimiento <span class="text-danger">*</span></label><input type="date" class="form-control" id="fecha_nacimiento" name="fecha_nacimiento" value="<?= htmlspecialchars($solicitud_data['fecha_nacimiento'] ?? '') ?>" required></div>
                            <div class="col-md-4"><label for="fecha_ingreso" class="form-label">Fecha de Ingreso <span class="text-danger">*</span></label><input type="date" class="form-control" id="fecha_ingreso" name="fecha_ingreso" value="<?= htmlspecialchars($solicitud_data['fecha_ingreso'] ?? '') ?>" required></div>
                        </div>
                        <div class="mt-3"><label for="direccion" class="form-label">Dirección <span class="text-danger">*</span></label><textarea class="form-control" id="direccion" name="direccion" rows="2" required><?= htmlspecialchars($solicitud_data['direccion'] ?? '') ?></textarea></div>
                        <div class="mb-3 dynamic-field" data-type="practicante">
                            <div class="mt-3"><label for="actividades_practicante" class="form-label">Actividades del Practicante <span class="text-danger">*</span></label><textarea class="form-control" id="actividades_practicante" name="actividades_practicante" rows="2" required><?= htmlspecialchars($solicitud_data['actividades_practicante'] ?? '') ?></textarea></div>
                        </div>
                    </div>

                    <div id="form-cambio-puesto" class="dynamic-form-section">
                        <h3 class="form-section-title">Identificación del Colaborador</h3>
                        <div class="row g-3">
                            <div class="col-md-8"><label for="usuario_cambio_id" class="form-label">Usuario a cambiar de puesto <span class="text-danger">*</span></label><select class="form-select" id="usuario_cambio_id" name="usuario_cambio_id" required>
                                    <option selected disabled value="">Elige un usuario...</option>
                                    <?php foreach ($usuarios_all as $usuario): ?>
                                        <option value="<?= $usuario['id'] ?>" data-puesto-id="<?= $usuario['puesto_id'] ?>" data-puesto-nombre="<?= htmlspecialchars($usuario['puesto']) ?>" data-estatus="<?= $usuario['estatus'] ?>" <?= ($solicitud_data['usuario_cambio_id'] ?? '') == $usuario['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($usuario['usuario']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select></div>
                            <div class="col-md-4"><label class="form-label">Estatus</label><span id="estatus_cambio" class="status-badge"></span></div>
                        </div>
                        <h3 class="form-section-title">Detalles del Cambio</h3>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="puesto_anterior_display" class="form-label">Puesto Anterior</label>
                                <input type="text" class="form-control" id="puesto_anterior_display" readonly>
                                <input type="hidden" id="puesto_anterior" name="puesto_anterior" value="<?= htmlspecialchars($solicitud_data['puesto_anterior_id'] ?? '') ?>">
                            </div>
                            <div class="col-md-6"><label for="puesto_nuevo" class="form-label">Puesto Nuevo <span class="text-danger">*</span></label><select class="form-select" id="puesto_nuevo" name="puesto_nuevo" required>
                                    <option selected disabled value="">Elige un puesto...</option>
                                    <?php foreach ($puestos as $puesto): ?><option value="<?= $puesto['id'] ?>" <?= ($solicitud_data['puesto_nuevo_id'] ?? '') == $puesto['id'] ? 'selected' : '' ?>><?= htmlspecialchars($puesto['puesto']) ?></option><?php endforeach; ?>
                                </select></div>
                        </div>
                        <div class="mb-3 mt-3"><label for="justificacion_cambio" class="form-label">Justificación del Cambio <span class="text-danger">*</span></label><textarea class="form-control" id="justificacion_cambio" name="justificacion_cambio" rows="3" required><?= htmlspecialchars($solicitud_data['justificacion'] ?? '') ?></textarea></div>
                    </div>

                    <div id="form-baja" class="dynamic-form-section">
                        <h3 class="form-section-title">Datos del Colaborador a dar de Baja</h3>
                        <div class="row g-3 align-items-end">
                            <div class="col-md-8"><label for="usuario_baja_id" class="form-label">Usuario a dar de baja <span class="text-danger">*</span></label><select class="form-select" id="usuario_baja_id" name="usuario_baja_id" required>
                                    <option selected disabled value="">Elige un usuario...</option>
                                    <?php foreach ($usuarios_all as $usuario): ?><option value="<?= $usuario['id'] ?>" <?= ($solicitud_data['usuario_baja_id'] ?? '') == $usuario['id'] ? 'selected' : '' ?>><?= htmlspecialchars($usuario['usuario']) ?></option><?php endforeach; ?>
                                </select></div>
                            <div class="col-md-4">
                                <label class="form-label">¿Es foráneo? <span class="text-danger">*</span></label>
                                <div class="form-check"><input class="form-check-input" type="radio" name="es_foraneo" id="foraneo_si" value="1" <?= ($solicitud_data['es_foraneo'] ?? '0') == '1' ? 'checked' : '' ?> required><label class="form-check-label" for="foraneo_si">Sí</label></div>
                                <div class="form-check"><input class="form-check-input" type="radio" name="es_foraneo" id="foraneo_no" value="0" <?= ($solicitud_data['es_foraneo'] ?? '0') == '0' ? 'checked' : '' ?>><label class="form-check-label" for="foraneo_no">No</label></div>
                            </div>
                        </div>
                        <h3 class="form-section-title">Detalles de la Baja</h3>
                        <div class="mb-3"><label for="colaborador_respaldo_id" class="form-label">Asignar respaldo a <span class="text-danger">*</span></label><select class="form-select" id="colaborador_respaldo_id" name="colaborador_respaldo_id" required>
                                <option selected disabled value="">Elige un usuario activo...</option>
                                <?php foreach ($usuarios_activos as $usuario): ?><option value="<?= $usuario['id'] ?>" <?= ($solicitud_data['colaborador_respaldo_id'] ?? '') == $usuario['id'] ? 'selected' : '' ?>><?= htmlspecialchars($usuario['usuario']) ?></option><?php endforeach; ?>
                            </select></div>
                        <div class="mb-3" id="recoge_productos_container" style="display: none;"><label for="colaborador_recoge_productos" class="form-label">Colaborador que recogerá productos <span class="text-danger">*</span></label><input type="text" class="form-control" id="colaborador_recoge_productos" name="colaborador_recoge_productos" value="<?= htmlspecialchars($solicitud_data['colaborador_recoge_productos'] ?? '') ?>" required></div>
                        <div class="mb-3"><label class="form-label">¿Es Baja por Reemplazo? <span class="text-danger">*</span></label>
                            <div class="form-check"><input class="form-check-input" type="radio" name="es_baja_por_reemplazo" id="reemplazo_si" value="1" <?= ($solicitud_data['es_baja_por_reemplazo'] ?? '0') == '1' ? 'checked' : '' ?> required><label class="form-check-label" for="reemplazo_si">Sí</label></div>
                            <div class="form-check"><input class="form-check-input" type="radio" name="es_baja_por_reemplazo" id="reemplazo_no" value="0" <?= ($solicitud_data['es_baja_por_reemplazo'] ?? '0') == '0' ? 'checked' : '' ?>><label class="form-check-label" for="reemplazo_no">No</label></div>
                        </div>
                    </div>
                    
                    <div id="form-comun-final" class="dynamic-form-section mb-3">
                        <div class="row g-3"><div class="col-12"><label for="archivo_evidencia_path" class="form-label">Adjuntar Archivo (opcional)</label><input class="form-control" type="file" id="archivo_evidencia_path" name="archivo_evidencia_path"></div></div>
                        <div class="mt-3"><label for="observaciones" class="form-label">Observaciones (Opcional)</label><textarea class="form-control" id="observaciones" name="observaciones" rows="2"><?= htmlspecialchars($solicitud_data['observaciones'] ?? '') ?></textarea></div>
                    </div>
                </div>
                
                <div id="submit-button-container" class="mt-4 pt-4 border-top" style="display: none;">
                    <button type="submit" class="btn btn-primary w-100 py-2"><i class="fas fa-paper-plane me-2"></i> <?= $modo_edicion ? 'Actualizar Solicitud' : 'Enviar Solicitud' ?></button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('solicitudForm');
    const tipoSolicitudSelect = document.getElementById('tipo_solicitud');
    const submitButtonContainer = document.getElementById('submit-button-container');
    const isInEditMode = <?= $modo_edicion ? 'true' : 'false' ?>;
    
    const originalRequiredFields = new Map();
    form.querySelectorAll('[required]').forEach(el => originalRequiredFields.set(el, true));

    const formSections = { 'alta': ['form-alta-remplazo-practicante', 'form-comun-final'], 'remplazo': ['form-alta-remplazo-practicante', 'form-comun-final'], 'practicante': ['form-alta-remplazo-practicante', 'form-comun-final'], 'cambio_puesto': ['form-cambio-puesto', 'form-comun-final'], 'baja': ['form-baja', 'form-comun-final'] };

    function manageSectionValidation(section, isVisible) {
        section.querySelectorAll('input, select, textarea').forEach(input => {
            if (originalRequiredFields.has(input)) {
                input.required = isVisible;
            }
        });
    }

    function updateFormUI() {
        const selectedValue = tipoSolicitudSelect.value;
        document.querySelectorAll('.dynamic-form-section').forEach(section => {
            section.style.display = 'none';
            manageSectionValidation(section, false);
        });
        const sectionsToShow = formSections[selectedValue] || [];
        if (sectionsToShow.length > 0) {
            sectionsToShow.forEach(id => {
                const section = document.getElementById(id);
                if (section) {
                    section.style.display = 'block';
                    manageSectionValidation(section, true);
                }
            });
            handleSpecialFields(selectedValue);
            submitButtonContainer.style.display = 'block';
        } else {
            submitButtonContainer.style.display = 'none';
        }

        // CAMBIO: Lógica centralizada para manejar la visibilidad del campo "Puesto"
        const puestoContainer = document.getElementById('puesto-container');
        const puestoSelect = document.getElementById('puesto_alta');
        const altaSectionVisible = document.getElementById('form-alta-remplazo-practicante').style.display === 'block';

        if (altaSectionVisible) {
            const isPracticante = selectedValue === 'practicante';
            puestoContainer.style.display = isPracticante ? 'none' : 'block';
            puestoSelect.required = !isPracticante && originalRequiredFields.has(puestoSelect);
        }

        handleForaneoChange();
    }

    function handleSpecialFields(type) {
        const altaForm = document.getElementById('form-alta-remplazo-practicante');
        if (!altaForm) return;
        altaForm.querySelectorAll('.dynamic-field').forEach(field => {
            const isVisible = field.getAttribute('data-type') === type;
            field.style.display = isVisible ? 'block' : 'none';
            manageSectionValidation(field, isVisible);
        });
    }

    function handleForaneoChange() {
        const esForaneoSí = document.getElementById('foraneo_si').checked;
        const isBajaVisible = document.getElementById('form-baja').style.display === 'block';
        const recogeProductosContainer = document.getElementById('recoge_productos_container');
        const recogeProductosInput = document.getElementById('colaborador_recoge_productos');
        const shouldBeVisible = esForaneoSí && isBajaVisible;
        recogeProductosContainer.style.display = shouldBeVisible ? 'block' : 'none';
        if (originalRequiredFields.has(recogeProductosInput)) {
            recogeProductosInput.required = shouldBeVisible;
        }
    }

    function updateStatusBadge(spanElement, status) {
        if (!status) { spanElement.textContent = ''; spanElement.className = 'status-badge'; return; }
        spanElement.textContent = status === '1' ? 'Activo' : 'Inactivo';
        spanElement.className = `status-badge ${status === '1' ? 'status-active' : 'status-inactive'}`;
    }

    const usuarioRemplazoSelect = document.getElementById('usuario_remplazo_id');
    const puestoRemplazoInput = document.getElementById('puesto_remplazo');
    const estatusRemplazoSpan = document.getElementById('estatus_remplazo');
    usuarioRemplazoSelect.addEventListener('change', function() {
        const selectedOption = this.options[this.selectedIndex];
        if (!selectedOption.value) { puestoRemplazoInput.value = ''; updateStatusBadge(estatusRemplazoSpan, null); return; }
        puestoRemplazoInput.value = selectedOption.dataset.puesto || 'No definido';
        updateStatusBadge(estatusRemplazoSpan, selectedOption.dataset.estatus);
    });

    const usuarioCambioSelect = document.getElementById('usuario_cambio_id');
    const estatusCambioSpan = document.getElementById('estatus_cambio');
    const puestoAnteriorDisplayInput = document.getElementById('puesto_anterior_display');
    const puestoAnteriorHiddenInput = document.getElementById('puesto_anterior');
    usuarioCambioSelect.addEventListener('change', function() {
        const selectedOption = this.options[this.selectedIndex];
        if (!selectedOption.value) {
            updateStatusBadge(estatusCambioSpan, null);
            puestoAnteriorDisplayInput.value = '';
            puestoAnteriorHiddenInput.value = '';
            return;
        }
        updateStatusBadge(estatusCambioSpan, selectedOption.dataset.estatus);
        puestoAnteriorDisplayInput.value = selectedOption.dataset.puestoNombre || 'No definido';
        puestoAnteriorHiddenInput.value = selectedOption.dataset.puestoId || '';
    });

    document.querySelectorAll('input[name="es_foraneo"]').forEach(radio => radio.addEventListener('change', handleForaneoChange));
    tipoSolicitudSelect.addEventListener('change', updateFormUI);

    if (isInEditMode) {
        tipoSolicitudSelect.dispatchEvent(new Event('change'));
        if (usuarioRemplazoSelect.value) usuarioRemplazoSelect.dispatchEvent(new Event('change'));
        if (usuarioCambioSelect.value) usuarioCambioSelect.dispatchEvent(new Event('change'));
    } else {
        // Asegura que la UI esté correcta al cargar la página en modo NUEVO
        updateFormUI();
    }

    form.addEventListener('submit', function(event) {
        if (!form.checkValidity()) {
            event.preventDefault();
            event.stopPropagation();
            form.classList.add('was-validated'); // Bootstrap validation styles
            Swal.fire('Campos Incompletos', 'Por favor, rellena todos los campos obligatorios (*).', 'warning');
            return;
        }
        event.preventDefault();
        Swal.fire({ title: 'Procesando...', text: 'Por favor, espera.', allowOutsideClick: false, didOpen: () => Swal.showLoading() });

        const formData = new FormData(form);
        const endpoint = isInEditMode ? 'ABC_controller/actualizar_solicitud.php' : 'ABC_controller/procesar_solicitud.php';

        fetch(endpoint, { method: 'POST', body: formData })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({ icon: 'success', title: data.message, text: 'Folio: ' + data.folio }).then(() => {
                        window.location.href = 'ABC_mis_solicitudes.php';
                    });
                } else {
                    Swal.fire({ icon: 'error', title: 'Error', text: data.message });
                }
            })
            .catch(error => {
                Swal.fire({ icon: 'error', title: 'Error de Conexión', text: 'No se pudo comunicar con el servidor.' });
                console.error('Fetch Error:', error);
            });
    });
});
</script>

<?php
include("src/templates/adminfooter.php");
?>

