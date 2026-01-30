<?php
session_start();
if (!isset($_SESSION['loggedin']) || !$_SESSION['loggedin']) {
    header("Location: index.php");
    exit();
}

$usuario_id = $_SESSION['usuario_id'];
include("src/templates/adminheader.php");
?>

<div class="container-fluid mt-4">
    <form id="formNuevaAuditoria">
        <div class="card shadow mb-4">
            <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bi bi-file-earmark-check"></i> Formato de Auditoría</h5>
                <h4 class="mb-0 text-warning" id="txtFolio">FOLIO: Cargando...</h4>
                <input type="hidden" id="folio_final" name="folio" value="">
            </div>
            <div class="card-body bg-light">
                <div class="row">
                    <div class="col-md-4">
                        <label class="fw-bold">Seleccionar Unidad (Serie)</label>
                        <select id="selectVehiculo" name="vehiculo_id" class="form-select select2" required onchange="cargarDatosVehiculo(this.value)">
                            <option value="">Seleccione una serie activa...</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="fw-bold">Fecha de Auditoría</label>
                        <input type="date" class="form-control" name="fecha_auditoria" value="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div class="col-md-3">
                        <label class="fw-bold">Kilometraje Actual</label>
                        <div class="input-group">
                            <input type="number" class="form-control" name="kilometraje" placeholder="00000" required>
                            <span class="input-group-text">KM</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <ul class="nav nav-pills nav-fill mb-3 shadow-sm" id="auditTabs" role="tablist">
            <li class="nav-item">
                <a class="nav-link active" data-bs-toggle="tab" href="#seg1">1. Datos Generales</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-bs-toggle="tab" href="#seg2">2. Documentos</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-bs-toggle="tab" href="#seg3">3. Herramientas</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-bs-toggle="tab" href="#seg4">4. Estado Físico</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-bs-toggle="tab" href="#seg5">5. Mantenimiento</a>
            </li>
        </ul>

        <div class="tab-content border p-4 bg-white shadow-sm rounded">
            <div class="tab-pane fade show active" id="seg1">
                <div id="infoVehiculo" class="row text-center py-4">
                    <div class="col-12 text-muted">
                        <i class="bi bi-car-front fs-1"></i>
                        <p>Seleccione un vehículo arriba para cargar la información técnica.</p>
                    </div>
                </div>
                <div id="alertaIncidenciasPendientes" class="mt-3" style="display: none;"></div>
            </div>
            
            <div class="tab-pane fade" id="seg2"> <div class="checklist-container"></div> </div>
            <div class="tab-pane fade" id="seg3"> <div class="checklist-container"></div> </div>
            <div class="tab-pane fade" id="seg4"> <div class="checklist-container"></div> </div>
            
            <div class="tab-pane fade" id="seg5">
                <h6 class="text-primary mb-3">Últimos dos servicios de mantenimiento</h6>
                <table class="table table-bordered align-middle" id="tablaMantenimiento">
                    <thead class="table-secondary">
                        <tr>
                            <th>Fecha</th><th>Kilometraje</th><th>Tipo de Servicio</th><th>Taller</th><th>Observaciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php for($i=1; $i<=2; $i++): ?>
                        <tr>
                            <td><input type="date" class="form-control form-control-sm m_fecha"></td>
                            <td><input type="number" class="form-control form-control-sm m_km"></td>
                            <td><input type="text" class="form-control form-control-sm m_servicio"></td>
                            <td><input type="text" class="form-control form-control-sm m_taller"></td>
                            <td><input type="text" class="form-control form-control-sm m_obs"></td>
                        </tr>
                        <?php endfor; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="mt-4 mb-3 p-3 bg-white rounded border shadow-sm">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h6 class="text-danger mb-0 fw-bold"><i class="bi bi-exclamation-octagon"></i> Gestión de Tareas (Incidentes) Pendientes</h6>
                <button type="button" class="btn btn-outline-danger btn-sm" onclick="agregarFilaIncidencia()">
                    <i class="bi bi-plus-circle"></i> Agregar Incidencia
                </button>
            </div>
            <div class="table-responsive">
                <table class="table table-sm table-bordered" id="tablaIncidencias">
                    <thead class="table-danger text-white">
                        <tr>
                            <th style="width: 85%;">Descripción del Incidente / Tarea Pendiente</th>
                            <th class="text-center">Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr id="sinIncidencias">
                            <td colspan="2" class="text-center text-muted small">No hay incidentes registrados. Haz clic en "Agregar" si detectaste algo pendiente.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="mt-4 mb-5 p-3 bg-light rounded border">
            <label class="fw-bold">Observaciones del Auditor</label>
            <textarea name="observaciones" class="form-control" rows="2" placeholder="Escriba aquí hallazgos generales..."></textarea>
            <hr>
            <div class="d-flex justify-content-between align-items-center">
                <div class="h5 mb-0 text-primary">Puntuación Total Estimada: <span id="puntosTotales">0</span> pts</div>
                <button type="submit" class="btn btn-success btn-lg">
                    <i class="bi bi-cloud-arrow-up"></i> Finalizar y Guardar Auditoría
                </button>
            </div>
        </div>
    </form>
</div>

<script>
let memoriaRespuestas = {};

document.addEventListener('DOMContentLoaded', () => {
    initForm();
    consultarFolio();
    
    if (typeof $.fn.select2 !== 'undefined') {
        $('.select2').select2({ theme: 'bootstrap-5' });
    }

    // Listener para cambio de pestañas
    const tabLinks = document.querySelectorAll('a[data-bs-toggle="tab"]');
    tabLinks.forEach(link => {
        link.addEventListener('shown.bs.tab', function (e) {
            const targetId = e.target.getAttribute('href').replace('#', '');
            ejecutarCargaPorTab(targetId);
        });
    });
});

async function initForm() {
    try {
        const res = await fetch('AUD_controller/get_vehiculos.php');
        const vehiculos = await res.json();
        const select = document.getElementById('selectVehiculo');
        select.innerHTML = '<option value="">Seleccione una serie activa...</option>';
        vehiculos.filter(v => v.estatus !== 'Baja').forEach(v => {
            select.innerHTML += `<option value="${v.id}">${v.no_serie} - ${v.marca} (${v.placas})</option>`;
        });
    } catch (e) { console.error("Error en initForm:", e); }
}

async function consultarFolio() {
    try {
        const response = await fetch('AUD_controller/obtener_folio.php');
        const data = await response.json();
        if (data.status === 'success') {
            document.getElementById('txtFolio').innerText = `FOLIO: ${data.folio}`;
            document.getElementById('folio_final').value = data.folio;
        }
    } catch (error) { console.error("Error en consultarFolio:", error); }
}

function ejecutarCargaPorTab(id) {
    if (id === 'seg2') cargarChecklist('Documento', 'seg2');
    if (id === 'seg3') cargarChecklist('Inventario', 'seg3');
    if (id === 'seg4') cargarChecklist('Estado', 'seg4');
}

async function cargarDatosVehiculo(id) {
    if(!id) {
        document.getElementById('alertaIncidenciasPendientes').style.display = 'none';
        return;
    }
    try {
        const res = await fetch(`AUD_controller/get_detalle_vehiculo.php?id=${id}`);
        const v = await res.json();
        document.getElementById('infoVehiculo').innerHTML = `
            <div class="col-md-3 border-end"><strong>Sucursal</strong><p class="text-primary">${v.sucursal_nombre || 'N/A'}</p></div>
            <div class="col-md-3 border-end"><strong>Responsable</strong><p class="text-primary">${v.responsable_nombre || 'N/A'}</p></div>
            <div class="col-md-3 border-end"><strong>Licencia</strong><p class="text-primary">${v.no_licencia || 'N/A'}<br><small>(Vence: ${v.fecha_vencimiento_licencia || 'N/A'})</small></p></div>
            <div class="col-md-3"><strong>Vehículo</strong><p class="text-primary">${v.marca} ${v.modelo} ${v.anio}<br>Placas: ${v.placas}</p></div>
            <div class="col-12 mt-2"><strong>Observaciones</strong><p class="text-secondary small">${v.observaciones || 'N/A'}</p></div>
        `;

        const resInc = await fetch(`AUD_controller/get_incidencias_pendientes.php?vehiculo_id=${id}`);
        const incidencias = await resInc.json();
        const alertaDiv = document.getElementById('alertaIncidenciasPendientes');
        
        if (incidencias.length > 0) {
            let listaHtml = incidencias.map(i => `<li>${i.descripcion} <small class="text-muted">(Folio: ${i.folio_original})</small></li>`).join('');
            alertaDiv.innerHTML = `<div class="alert alert-danger shadow-sm border-start border-5 border-danger">
                <h5 class="alert-heading small fw-bold">Incidencias Pendientes</h5>
                <ul class="mb-0 small">${listaHtml}</ul>
            </div>`;
            alertaDiv.style.display = 'block';
        } else { alertaDiv.style.display = 'none'; }
    } catch (e) { console.error(e); }
}

async function cargarChecklist(tipo, containerId) {
    const container = document.querySelector(`#${containerId} .checklist-container`);
    if (!container) return;
    
    container.innerHTML = `
        <div class="text-center p-4">
            <div class="spinner-border text-primary" role="status"></div>
            <p class="mt-2">Cargando conceptos...</p>
        </div>`;

    try {
        const res = await fetch('AUD_controller/get_conceptos_auditoria.php?solo_activos=1');
        const conceptos = await res.json();
        const tipoBusqueda = tipo.trim().toLowerCase();
        const items = conceptos.filter(c => c.tipo.trim().toLowerCase().includes(tipoBusqueda));

        if (items.length === 0) {
            container.innerHTML = `<div class="alert alert-warning m-3">No hay conceptos para "${tipo}"</div>`;
            return;
        }

        let html = `
        <table class="table table-bordered table-hover align-middle shadow-sm bg-white">
            <thead class="table-dark">
                <tr>
                    <th style="width: 45%;">Descripción del Concepto</th>
                    <th class="text-center">Bueno</th>
                    <th class="text-center">Regular</th>
                    <th class="text-center">Malo</th>
                    <th class="text-center" style="width: 100px;">Subtotal</th>
                </tr>
            </thead>
            <tbody>`;

        items.forEach(c => {
            const p1 = parseFloat(c.c1) || 0;
            const p2 = parseFloat(c.c2) || 0;
            const p3 = parseFloat(c.c3) || 0;
            const previa = memoriaRespuestas[c.id] || null;
            const ptsVal = previa ? previa.puntos : 0;

            html += `
            <tr>
                <td class="fw-bold text-secondary small">${c.descripcion}</td>
                <td class="text-center">
                    <label class="d-flex align-items-center justify-content-center m-0 cursor-pointer">
                        <input type="radio" name="check_${c.id}" value="C1" class="form-check-input border-success me-2" 
                            ${previa?.opcion === 'C1' ? 'checked' : ''} 
                            onclick="calcularPuntos(${c.id}, ${p1}, 'C1')">
                        <span class="badge bg-success-subtle text-success">+${p1}</span>
                    </label>
                </td>
                <td class="text-center">
                    <label class="d-flex align-items-center justify-content-center m-0 cursor-pointer">
                        <input type="radio" name="check_${c.id}" value="C2" class="form-check-input border-warning me-2" 
                            ${previa?.opcion === 'C2' ? 'checked' : ''} 
                            onclick="calcularPuntos(${c.id}, ${p2}, 'C2')">
                        <span class="badge bg-warning-subtle text-warning">+${p2}</span>
                    </label>
                </td>
                <td class="text-center">
                    <label class="d-flex align-items-center justify-content-center m-0 cursor-pointer">
                        <input type="radio" name="check_${c.id}" value="C3" class="form-check-input border-danger me-2" 
                            ${previa?.opcion === 'C3' ? 'checked' : ''} 
                            onclick="calcularPuntos(${c.id}, ${p3}, 'C3')">
                        <span class="badge bg-danger-subtle text-danger">+${p3}</span>
                    </label>
                </td>
                <td class="text-center bg-light">
                    <span id="pts_${c.id}" class="h6 fw-bold text-primary">${ptsVal}</span>
                </td>
            </tr>`;
        });

        container.innerHTML = html + '</tbody></table>';
    } catch (e) { 
        console.error(e);
        container.innerHTML = '<div class="alert alert-danger">Error al cargar conceptos.</div>'; 
    }
}

function calcularPuntos(id, puntos, opcion) {
    memoriaRespuestas[id] = { puntos, opcion };
    const span = document.getElementById(`pts_${id}`);
    if(span) span.innerText = puntos;
    actualizarTotalGlobal();
}

function actualizarTotalGlobal() {
    let total = Object.values(memoriaRespuestas).reduce((acc, r) => acc + (parseFloat(r.puntos) || 0), 0);
    document.getElementById('puntosTotales').innerText = total;
}

function agregarFilaIncidencia() {
    const tbody = document.querySelector('#tablaIncidencias tbody');
    const sin = document.getElementById('sinIncidencias');
    if (sin) sin.remove();
    const tr = document.createElement('tr');
    tr.innerHTML = `<td><input type="text" class="form-control form-control-sm row-incidencia" required></td>
                    <td class="text-center"><button type="button" class="btn btn-sm text-danger" onclick="this.closest('tr').remove()"><i class="bi bi-trash"></i></button></td>`;
    tbody.appendChild(tr);
}

document.getElementById('formNuevaAuditoria').addEventListener('submit', async function(e) {
    e.preventDefault();
    if (Object.keys(memoriaRespuestas).length === 0) {
        Swal.fire('Error', 'Debe evaluar conceptos antes de guardar.', 'warning');
        return;
    }

    const conf = await Swal.fire({ title: '¿Guardar Auditoría?', icon: 'question', showCancelButton: true });
    if (!conf.isConfirmed) return;

    const respuestas = Object.keys(memoriaRespuestas).map(id => ({
        concepto_id: id,
        opcion: memoriaRespuestas[id].opcion,
        puntos: memoriaRespuestas[id].puntos
    }));

    const mantenimientos = [];
    document.querySelectorAll('#tablaMantenimiento tbody tr').forEach(tr => {
        const serv = tr.querySelector('.m_servicio').value;
        if(serv.trim()) {
            mantenimientos.push({
                fecha: tr.querySelector('.m_fecha').value,
                km: tr.querySelector('.m_km').value,
                servicio: serv,
                taller: tr.querySelector('.m_taller').value,
                obs: tr.querySelector('.m_obs').value
            });
        }
    });

    const incidencias = Array.from(document.querySelectorAll('.row-incidencia')).map(i => ({ descripcion: i.value.trim() })).filter(i => i.descripcion);

    const payload = {
        folio: document.getElementById('folio_final').value,
        vehiculo_id: document.getElementById('selectVehiculo').value,
        kilometraje: this.kilometraje.value,
        fecha: this.fecha_auditoria.value,
        observaciones: this.observaciones.value,
        respuestas: respuestas,
        mantenimiento: mantenimientos,
        incidencias: incidencias
    };

    try {
        const res = await fetch('AUD_controller/guardar_auditoria.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const result = await res.json();
        if(result.status === 'success') Swal.fire('Éxito', result.message, 'success').then(() => location.reload());
        else Swal.fire('Error', result.message, 'error');
    } catch (e) { Swal.fire('Error', 'Conexión fallida', 'error'); }
});
</script>

<?php include("src/templates/adminfooter.php"); ?>