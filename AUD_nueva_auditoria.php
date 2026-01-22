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

<style>
    /* Hace los Radio Buttons un 50% más grandes */
    .radio-touch {
        transform: scale(1.5); 
        margin: 5px;
        cursor: pointer;
    }
    
    /* Aumenta la zona de clic en las celdas de la tabla */
    .table-touch tbody td {
        vertical-align: middle;
        padding-top: 15px;
        padding-bottom: 15px;
    }

    /* Mejora la lectura en pantallas medianas */
    .concepto-texto {
        font-size: 1.1rem; /* Un poco más grande que el estándar */
        line-height: 1.4;
    }
</style>

<div class="container-fluid mt-4">
    <form id="formNuevaAuditoria">
        <div class="card shadow mb-4">
            <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center flex-wrap">
                <h5 class="mb-2 mb-md-0"><i class="bi bi-file-earmark-check"></i> Formato de Auditoría</h5>
                <h4 class="mb-0 text-warning" id="txtFolio">FOLIO: Cargando...</h4>
                <input type="hidden" id="folio_final" name="folio" value="">
            </div>
            <div class="card-body bg-light">
                <div class="row g-3"> <div class="col-md-4 col-12">
                        <label class="fw-bold">Seleccionar Unidad (Serie)</label>
                        <select id="selectVehiculo" name="vehiculo_id" class="form-select select2" required onchange="cargarDatosVehiculo(this.value)">
                            <option value="">Seleccione una serie activa...</option>
                        </select>
                    </div>
                    <div class="col-md-4 col-6">
                        <label class="fw-bold">Fecha</label>
                        <input type="date" class="form-control" name="fecha_auditoria" value="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div class="col-md-4 col-6">
                        <label class="fw-bold">Kilometraje</label>
                        <div class="input-group">
                            <input type="number" class="form-control" name="kilometraje" placeholder="00000" required>
                            <span class="input-group-text">KM</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <ul class="nav nav-pills nav-fill mb-3 shadow-sm flex-column flex-sm-row" id="auditTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active py-3" id="tab-seg1" data-bs-toggle="tab" data-bs-target="#seg1" type="button" role="tab">
                    1. Datos
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link py-3" id="tab-seg2" data-bs-toggle="tab" data-bs-target="#seg2" type="button" role="tab" onclick="cargarChecklist('Documento', 'seg2')">
                    2. Documentos
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link py-3" id="tab-seg3" data-bs-toggle="tab" data-bs-target="#seg3" type="button" role="tab" onclick="cargarChecklist('Inventario', 'seg3')">
                    3. Herramientas
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link py-3" id="tab-seg4" data-bs-toggle="tab" data-bs-target="#seg4" type="button" role="tab" onclick="cargarChecklist('Estado', 'seg4')">
                    4. Estado Físico
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link py-3" id="tab-seg5" data-bs-toggle="tab" data-bs-target="#seg5" type="button" role="tab">
                    5. Mtto.
                </button>
            </li>
        </ul>

        <div class="tab-content border p-3 p-md-4 bg-white shadow-sm rounded">
            <div class="tab-pane fade show active" id="seg1">
                <div id="infoVehiculo" class="row text-center py-4">
                    <div class="col-12 text-muted">
                        <i class="bi bi-car-front fs-1"></i>
                        <p>Seleccione un vehículo arriba para cargar la información.</p>
                    </div>
                </div>
            </div>
            
            <div class="tab-pane fade" id="seg2"> <div class="checklist-container"></div> </div>
            <div class="tab-pane fade" id="seg3"> <div class="checklist-container"></div> </div>
            <div class="tab-pane fade" id="seg4"> <div class="checklist-container"></div> </div>
            
            <div class="tab-pane fade" id="seg5">
                <h6 class="text-primary mb-3">Últimos dos servicios</h6>
                <div class="table-responsive">
                    <table class="table table-bordered align-middle" id="tablaMantenimiento" style="min-width: 800px;">
                        <thead class="table-secondary">
                            <tr>
                                <th style="width:15%">Fecha</th>
                                <th style="width:15%">Km</th>
                                <th>Servicio</th>
                                <th>Taller</th>
                                <th>Notas</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php for($i=1; $i<=2; $i++): ?>
                            <tr>
                                <td><input type="date" class="form-control m_fecha"></td>
                                <td><input type="number" class="form-control m_km" placeholder="KM"></td>
                                <td><input type="text" class="form-control m_servicio" placeholder="Aceite, frenos..."></td>
                                <td><input type="text" class="form-control m_taller"></td>
                                <td><input type="text" class="form-control m_obs"></td>
                            </tr>
                            <?php endfor; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="mt-4 mb-5 p-3 bg-light rounded border sticky-bottom shadow-lg" style="bottom: 0; z-index: 100;">
            <label class="fw-bold">Observaciones Generales</label>
            <textarea name="observaciones" class="form-control mb-3" rows="2" placeholder="Escriba aquí hallazgos..."></textarea>
            
            <div class="d-grid gap-2 d-md-flex justify-content-between align-items-center">
                <div class="h4 mb-0 text-primary fw-bold text-center text-md-start">
                    Total: <span id="puntosTotales">0</span> pts
                </div>
                <button type="submit" class="btn btn-success btn-lg py-3 px-5">
                    <i class="bi bi-check-circle-fill"></i> GUARDAR AUDITORÍA
                </button>
            </div>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    initForm();
    consultarFolio();
    if (typeof $.fn.select2 !== 'undefined') {
        $('.select2').select2({ theme: 'bootstrap-5', width: '100%' });
    }
});

// --- FUNCIONES ---

async function initForm() {
    try {
        const res = await fetch('AUD_controller/get_vehiculos.php');
        const vehiculos = await res.json();
        const select = document.getElementById('selectVehiculo');
        select.innerHTML = '<option value="">Seleccione vehículo...</option>';
        vehiculos.filter(v => v.estatus !== 'Baja').forEach(v => {
            select.innerHTML += `<option value="${v.id}">${v.no_serie} - ${v.marca} (${v.placas})</option>`;
        });
    } catch (e) { console.error(e); }
}

async function consultarFolio() {
    try {
        const res = await fetch('AUD_controller/obtener_folio.php');
        const data = await res.json();
        if (data.status === 'success') {
            document.getElementById('txtFolio').innerText = `FOLIO: ${data.folio}`;
            document.getElementById('folio_final').value = data.folio;
        }
    } catch (e) { console.error(e); }
}

async function cargarDatosVehiculo(id) {
    if(!id) return;
    try {
        const res = await fetch(`AUD_controller/get_detalle_vehiculo.php?id=${id}`);
        const v = await res.json();
        
        // Layout adaptado a móvil/tablet (stacked)
        document.getElementById('infoVehiculo').innerHTML = `
            <div class="col-md-3 col-6 mb-3 border-end"><strong>Sucursal</strong><br><span class="text-primary">${v.sucursal_nombre || '-'}</span></div>
            <div class="col-md-3 col-6 mb-3 border-end"><strong>Responsable</strong><br><span class="text-primary">${v.responsable_nombre || '-'}</span></div>
            <div class="col-md-3 col-6 mb-3 border-end"><strong>Licencia</strong><br><span class="text-primary">${v.no_licencia || '-'}</span></div>
            <div class="col-md-3 col-6 mb-3"><strong>Placas</strong><br><span class="text-primary">${v.placas}</span></div>
        `;
    } catch (e) { console.error(e); }
}

async function cargarChecklist(tipo, containerId) {
    const container = document.querySelector(`#${containerId} .checklist-container`);
    if (!container || container.innerHTML.includes('table')) return; // Evita recargas

    container.innerHTML = `<div class="text-center p-5"><div class="spinner-border text-primary"></div><br>Cargando...</div>`;

    try {
        const res = await fetch('AUD_controller/get_conceptos_auditoria.php?solo_activos=1');
        const conceptos = await res.json();
        const items = conceptos.filter(c => c.tipo.toLowerCase().includes(tipo.toLowerCase()));

        if (items.length === 0) {
            container.innerHTML = `<div class="alert alert-warning">No se encontraron conceptos.</div>`;
            return;
        }

        // AQUÍ ESTÁ LA MAGIA PARA TABLETAS: table-responsive + estilos custom
        let html = `
        <div class="table-responsive">
            <table class="table table-bordered table-hover align-middle shadow-sm bg-white table-touch">
                <thead class="table-dark text-center">
                    <tr>
                        <th class="text-start" style="min-width: 200px;">Concepto a Revisar</th>
                        <th style="min-width: 80px;">Bueno<br>Si</th>
                        <th style="min-width: 80px;">Reg<br>No</th>
                        <th style="min-width: 80px;">Malo<br>N.A</th>
                        <th style="width: 60px;">Pts</th>
                    </tr>
                </thead>
                <tbody>`;

        items.forEach(c => {
            const p1 = parseFloat(c.c1) || 0;
            const p2 = parseFloat(c.c2) || 0;
            const p3 = parseFloat(c.c3) || 0;

            html += `
                <tr>
                    <td>
                        <span class="concepto-texto fw-bold text-secondary">${c.descripcion}</span>
                    </td>
                    <td class="text-center bg-light">
                        <input type="radio" name="check_${c.id}" value="C1" class="form-check-input border-success radio-touch" 
                        onclick="calcularPuntos(${c.id}, ${p1})">
                        <div class="text-success fw-bold small mt-1">+${p1}</div>
                    </td>
                    <td class="text-center">
                        <input type="radio" name="check_${c.id}" value="C2" class="form-check-input border-warning radio-touch" 
                        onclick="calcularPuntos(${c.id}, ${p2})">
                        <div class="text-warning fw-bold small mt-1">+${p2}</div>
                    </td>
                    <td class="text-center">
                        <input type="radio" name="check_${c.id}" value="C3" class="form-check-input border-danger radio-touch" 
                        onclick="calcularPuntos(${c.id}, ${p3})">
                        <div class="text-danger fw-bold small mt-1">+${p3}</div>
                    </td>
                    <td class="text-center bg-light">
                        <span id="pts_${c.id}" class="h4 fw-bold text-primary">0</span>
                    </td>
                </tr>`;
        });

        html += `</tbody></table></div>`;
        container.innerHTML = html;

    } catch (error) {
        console.error(error);
        container.innerHTML = '<div class="alert alert-danger">Error de conexión</div>';
    }
}

function calcularPuntos(id, puntos) {
    const span = document.getElementById(`pts_${id}`);
    if(span) span.innerText = puntos;
    
    let total = 0;
    document.querySelectorAll('[id^="pts_"]').forEach(s => total += parseFloat(s.innerText) || 0);
    document.getElementById('puntosTotales').innerText = total;
}

document.getElementById('formNuevaAuditoria').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    // Validación básica de radios vacíos (opcional)
    if(document.querySelectorAll('input[type="radio"]:checked').length === 0){
        Swal.fire('Atención', 'No has contestado ninguna pregunta.', 'warning');
        return;
    }

    Swal.fire({ title: 'Guardando...', allowOutsideClick: false, didOpen: () => { Swal.showLoading(); }});

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
        fecha: this.fecha_auditoria.value,
        kilometraje: this.kilometraje.value,
        observaciones: this.observaciones.value,
        respuestas: respuestas,
        mantenimiento: mantenimientos
    };

    try {
        const res = await fetch('AUD_controller/guardar_auditoria.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(payload)
        });
        const data = await res.json();
        
        if(data.status === 'success') {
            Swal.fire('Guardado', data.message, 'success').then(() => location.reload());
        } else {
            Swal.fire('Error', data.message, 'error');
        }
    } catch(e) {
        Swal.fire('Error', 'Error de red', 'error');
    }
});
</script>