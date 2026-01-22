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
// Inicialización
document.addEventListener('DOMContentLoaded', () => {
    initForm();
    // Si usas Select2
    $('.select2').select2({ theme: 'bootstrap-5' });
});

async function initForm() {
    const res = await fetch('AUD_controller/get_vehiculos.php');
    const vehiculos = await res.json();
    const select = document.getElementById('selectVehiculo');
    vehiculos.filter(v => v.estatus !== 'Baja').forEach(v => {
        select.innerHTML += `<option value="${v.id}">${v.no_serie} - ${v.marca} (${v.placas})</option>`;
    });
}

// Actualiza los puntos en la fila y el total global
function calcularPuntos(id, puntos) {
    document.getElementById(`pts_${id}`).innerText = puntos;
    
    // Suma global de todos los spans de puntos
    let total = 0;
    document.querySelectorAll('[id^="pts_"]').forEach(span => {
        total += parseInt(span.innerText) || 0;
    });
    document.getElementById('puntosTotales').innerText = total;
}

async function cargarDatosVehiculo(id) {
    if(!id) return;
    const res = await fetch(`AUD_controller/get_detalle_vehiculo.php?id=${id}`);
    const v = await res.json();
    
    document.getElementById('infoVehiculo').innerHTML = `
        <div class="col-md-3 border-end"><strong>Sucursal</strong><p class="text-primary">${v.sucursal_nombre}</p></div>
        <div class="col-md-3 border-end"><strong>Responsable</strong><p class="text-primary">${v.responsable_nombre}</p></div>
        <div class="col-md-3 border-end"><strong>Licencia</strong><p class="text-primary">${v.no_licencia}<br><small>(Vence: ${v.fecha_vencimiento_licencia})</small></p></div>
        <div class="col-md-3"><strong>Vehículo</strong><p class="text-primary">${v.marca} ${v.modelo} ${v.anio}<br>Placas: ${v.placas}</p></div>
    `;
}

async function cargarChecklist(tipo, containerId) {
    const res = await fetch('AUD_controller/get_conceptos_auditoria.php');
    const conceptos = await res.json();
    const items = conceptos.filter(c => c.tipo === tipo && c.activo === 'S');
    
    let html = `<table class="table table-sm align-middle">
        <thead class="table-dark">
            <tr>
                <th>Descripción</th>
                <th class="text-center">Si / Bueno</th>
                <th class="text-center">No / Reg</th>
                <th class="text-center">N.A / Malo</th>
                <th class="text-center">Calif.</th>
            </tr>
        </thead>
        <tbody>`;
    
    items.forEach(c => {
        html += `
            <tr>
                <td>${c.descripcion}</td>
                <td class="text-center"><input type="radio" name="check_${c.id}" value="C1" class="form-check-input" onclick="calcularPuntos(${c.id}, ${c.c1})" required></td>
                <td class="text-center"><input type="radio" name="check_${c.id}" value="C2" class="form-check-input" onclick="calcularPuntos(${c.id}, ${c.c2})"></td>
                <td class="text-center"><input type="radio" name="check_${c.id}" value="C3" class="form-check-input" onclick="calcularPuntos(${c.id}, ${c.c3})"></td>
                <td id="pts_${c.id}" class="fw-bold text-center text-primary">0</td>
            </tr>`;
    });
    html += `</tbody></table>`;
    document.querySelector(`#${containerId} .checklist-container`).innerHTML = html;
}

// ENVÍO DE FORMULARIO
document.getElementById('formNuevaAuditoria').addEventListener('submit', async function(e) {
    e.preventDefault();

    // Confirmación inicial
    const confirmacion = await Swal.fire({
        title: '¿Finalizar Auditoría?',
        text: "Una vez guardada no podrá modificar los valores del folio.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sí, Guardar',
        cancelButtonText: 'Revisar'
    });

    if (!confirmacion.isConfirmed) return;

    // Mostrar cargando
    Swal.fire({
        title: 'Procesando...',
        text: 'Guardando datos y actualizando folio',
        allowOutsideClick: false,
        didOpen: () => { Swal.showLoading(); }
    });

    // 1. Recoger respuestas de checklist
    const respuestas = [];
    document.querySelectorAll('input[type="radio"]:checked').forEach(input => {
        const id = input.name.replace('check_', '');
        respuestas.push({
            concepto_id: id,
            opcion: input.value,
            puntos: document.getElementById(`pts_${id}`).innerText
        });
    });

    // 2. Recoger datos de mantenimiento
    const mantenimientos = [];
    document.querySelectorAll('#tablaMantenimiento tbody tr').forEach(tr => {
        mantenimientos.push({
            fecha: tr.querySelector('.m_fecha').value,
            km: tr.querySelector('.m_km').value,
            servicio: tr.querySelector('.m_servicio').value,
            taller: tr.querySelector('.m_taller').value,
            obs: tr.querySelector('.m_obs').value
        });
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
            await Swal.fire('¡Éxito!', result.message, 'success');
            // Redirigir o limpiar
            location.reload(); 
        } else {
            Swal.fire('Error', result.message, 'error');
        }
    } catch (error) {
        Swal.fire('Error crítico', 'No se pudo conectar con el servidor', 'error');
    }
});
// Agrega esta función a tu script existente
async function consultarFolio() {
    try {
        const response = await fetch('AUD_controller/obtener_folio.php');
        const data = await response.json();

        if (data.status === 'success') {
            // Actualizamos la vista y el campo oculto
            document.getElementById('txtFolio').innerText = `FOLIO: ${data.folio}`;
            document.getElementById('folio_final').value = data.folio;
        } else {
            console.error("Error al obtener folio:", data.message);
            document.getElementById('txtFolio').innerText = "FOLIO: Error";
        }
    } catch (error) {
        console.error("Error de conexión:", error);
    }
}

// Llama a la función cuando cargue el documento
document.addEventListener('DOMContentLoaded', () => {
    initForm();       // Tu función de vehículos
    consultarFolio(); // La nueva función de folio
});
</script>