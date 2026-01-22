<?php
session_start();
if (!isset($_SESSION['loggedin']) || !$_SESSION['loggedin']) {
    header("Location: index.php");
    exit();
}

$usuario_id = $_SESSION['usuario_id'];
include("src/templates/adminheader.php");
require("config/db.php");

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
            <li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#seg1">1. Datos Generales</a></li>
            <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#seg2" onclick="cargarChecklist('Documento', 'seg2')">2. Documentos</a></li>
            <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#seg3" onclick="cargarChecklist('Inventario', 'seg3')">3. Herramientas</a></li>
            <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#seg4" onclick="cargarChecklist('Estado', 'seg4')">4. Estado Físico</a></li>
            <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#seg5">5. Mantenimiento</a></li>
        </ul>

        <div class="tab-content border p-4 bg-white shadow-sm rounded">
            <div class="tab-pane fade show active" id="seg1">
                <div id="infoVehiculo" class="row text-center py-4">
                    <div class="col-12 text-muted">
                        <i class="bi bi-car-front fs-1"></i>
                        <p>Seleccione un vehículo arriba para cargar la información técnica.</p>
                    </div>
                </div>
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

        <div class="mt-4 mb-5 p-3 bg-light rounded border">
            <label class="fw-bold">Observaciones del Auditor (Opcional)</label>
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
// UN SOLO LISTENER PARA TODO
document.addEventListener('DOMContentLoaded', () => {
    initForm();
    consultarFolio();
    
    // Inicializar Select2 si existe
    if (typeof $.fn.select2 !== 'undefined') {
        $('.select2').select2({ theme: 'bootstrap-5' });
    }
});

async function initForm() {
    try {
        const res = await fetch('AUD_controller/get_vehiculos.php');
        const vehiculos = await res.json();
        const select = document.getElementById('selectVehiculo');
        
        // Limpiar y cargar
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

// CARGAR DATOS DEL VEHÍCULO (SEGMENTO 1)
async function cargarDatosVehiculo(id) {
    if(!id) return;
    try {
        const res = await fetch(`AUD_controller/get_detalle_vehiculo.php?id=${id}`);
        const v = await res.json();
        
        document.getElementById('infoVehiculo').innerHTML = `
            <div class="col-md-3 border-end"><strong>Sucursal</strong><p class="text-primary">${v.sucursal_nombre || 'N/A'}</p></div>
            <div class="col-md-3 border-end"><strong>Responsable</strong><p class="text-primary">${v.responsable_nombre || 'N/A'}</p></div>
            <div class="col-md-3 border-end"><strong>Licencia</strong><p class="text-primary">${v.no_licencia || 'N/A'}<br><small>(Vence: ${v.fecha_vencimiento_licencia || 'N/A'})</small></p></div>
            <div class="col-md-3"><strong>Vehículo</strong><p class="text-primary">${v.marca} ${v.modelo} ${v.anio}<br>Placas: ${v.placas}</p></div>
        `;
    } catch (e) { console.error("Error en cargarDatosVehiculo:", e); }
}

// CARGAR CHECKLIST (SEGMENTOS 2, 3, 4)
async function cargarChecklist(tipo, containerId) {
    const container = document.querySelector(`#${containerId} .checklist-container`);
    if (!container) return;

    container.innerHTML = '<div class="text-center p-3">Cargando...</div>';

    try {
        const res = await fetch('AUD_controller/get_conceptos_auditoria.php');
        const conceptos = await res.json();
        
        // Filtramos y limpiamos espacios o diferencias de mayúsculas
        const items = conceptos.filter(c => 
            c.tipo.trim().toLowerCase() === tipo.trim().toLowerCase()
        );

        console.log(`Buscando: ${tipo}. Encontrados: ${items.length}`);

        if (items.length === 0) {
            container.innerHTML = `
                <div class="alert alert-info">
                    No se encontraron conceptos tipo "${tipo}" en la base de datos.<br>
                    <small>Tipos encontrados en BD: ${[...new Set(conceptos.map(c => c.tipo))].join(', ')}</small>
                </div>`;
            return;
        }

        let html = `
        <table class="table table-bordered table-hover align-middle shadow-sm">
            <thead class="table-dark">
                <tr>
                    <th style="width: 40%;">Descripción</th>
                    <th class="text-center">Bueno / Si</th>
                    <th class="text-center">Regular / No</th>
                    <th class="text-center">Malo / N.A</th>
                    <th class="text-center" style="width: 100px;">Calif.</th>
                </tr>
            </thead>
            <tbody>`;

        items.forEach(c => {
            html += `
                <tr>
                    <td class="fw-bold">${c.descripcion}</td>
                    <td class="text-center">
                        <input type="radio" name="check_${c.id}" value="C1" class="form-check-input" 
                        onclick="calcularPuntos(${c.id}, ${c.c1})" required>
                        <br><small class="text-muted">+${c.c1}</small>
                    </td>
                    <td class="text-center">
                        <input type="radio" name="check_${c.id}" value="C2" class="form-check-input" 
                        onclick="calcularPuntos(${c.id}, ${c.c2})">
                        <br><small class="text-muted">+${c.c2}</small>
                    </td>
                    <td class="text-center">
                        <input type="radio" name="check_${c.id}" value="C3" class="form-check-input" 
                        onclick="calcularPuntos(${c.id}, ${c.c3})">
                        <br><small class="text-muted">+${c.c3}</small>
                    </td>
                    <td class="text-center bg-light">
                        <span id="pts_${c.id}" class="h6 fw-bold text-primary">0</span>
                    </td>
                </tr>`;
        });

        html += `</tbody></table>`;
        container.innerHTML = html;

    } catch (error) {
        console.error("Error:", error);
        container.innerHTML = '<div class="alert alert-danger">Error de conexión al cargar conceptos.</div>';
    }
}

function calcularPuntos(id, puntos) {
    const span = document.getElementById(`pts_${id}`);
    if(span) span.innerText = puntos;
    
    let total = 0;
    document.querySelectorAll('[id^="pts_"]').forEach(s => {
        total += parseInt(s.innerText) || 0;
    });
    document.getElementById('puntosTotales').innerText = total;
}

// GUARDAR AUDITORÍA
document.getElementById('formNuevaAuditoria').addEventListener('submit', async function(e) {
    e.preventDefault();

    const confirmacion = await Swal.fire({
        title: '¿Finalizar Auditoría?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Sí, Guardar'
    });

    if (!confirmacion.isConfirmed) return;

    Swal.fire({ title: 'Procesando...', allowOutsideClick: false, didOpen: () => { Swal.showLoading(); }});

    const respuestas = [];
    document.querySelectorAll('input[type="radio"]:checked').forEach(input => {
        const id = input.name.replace('check_', '');
        respuestas.push({
            concepto_id: id,
            opcion: input.value,
            puntos: document.getElementById(`pts_${id}`).innerText
        });
    });

    const mantenimientos = [];
    document.querySelectorAll('#tablaMantenimiento tbody tr').forEach(tr => {
        const servicio = tr.querySelector('.m_servicio').value;
        if(servicio) {
            mantenimientos.push({
                fecha: tr.querySelector('.m_fecha').value,
                km: tr.querySelector('.m_km').value,
                servicio: servicio,
                taller: tr.querySelector('.m_taller').value,
                obs: tr.querySelector('.m_obs').value
            });
        }
    });

    const payload = {
        folio: document.getElementById('folio_final').value,
        vehiculo_id: document.getElementById('selectVehiculo').value,
        kilometraje: this.kilometraje.value,
        fecha: this.fecha_auditoria.value,
        observaciones: this.observaciones.value,
        respuestas: respuestas,
        mantenimiento: mantenimientos
    };

    try {
        const response = await fetch('AUD_controller/guardar_auditoria.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const result = await response.json();
        if (result.status === 'success') {
            Swal.fire('¡Éxito!', result.message, 'success').then(() => location.reload());
        } else {
            Swal.fire('Error', result.message, 'error');
        }
    } catch (error) { Swal.fire('Error', 'No se pudo conectar', 'error'); }
});
</script>