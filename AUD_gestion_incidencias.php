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
    <div class="card shadow mb-4">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0"><i class="bi bi-gear-fill"></i> Gestión de Tareas (Incidencias) Pendientes</h5>
        </div>
        <div class="card-body bg-light">
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="fw-bold">Serie / Vehículo</label>
                    <input type="text" id="filtroSerie" class="form-control" placeholder="Buscar por serie...">
                </div>
                <div class="col-md-3">
                    <label class="fw-bold">Estatus</label>
                    <select id="filtroEstatus" class="form-select">
                        <option value="">Todos</option>
                        <option value="Pendiente">Pendiente</option>
                        <option value="Proceso">Proceso</option>
                        <option value="Terminada">Terminada</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="fw-bold">Rango de Fechas (Incidencia)</label>
                    <div class="input-group">
                        <input type="date" id="fechaInicio" class="form-control">
                        <input type="date" id="fechaFin" class="form-control">
                    </div>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button class="btn btn-primary w-100" onclick="cargarIncidencias()">
                        <i class="bi bi-search"></i> Filtrar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-dark">
                    <tr>
                        <th>Serie</th>
                        <th>Folio</th>
                        <th>F. Incidencia</th>
                        <th>Descripción</th>
                        <th>F. Finalización</th>
                        <th>Estatus</th>
                        <th class="text-center">Acciones</th>
                    </tr>
                </thead>
                <tbody id="tablaIncidenciasBody">
                    </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="modalGestion" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title">Actualizar Estatus de Incidencia</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="modal_id_incidencia">
                <div class="mb-3">
                    <label class="fw-bold">Nuevo Estatus</label>
                    <select id="modal_estatus" class="form-select">
                        <option value="Pendiente">Pendiente</option>
                        <option value="Proceso">Proceso</option>
                        <option value="Terminada">Terminada</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="fw-bold">Observaciones / Historial</label>
                    <textarea id="modal_obs" class="form-control" rows="4" placeholder="Describa el avance o motivo del cambio..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-success" onclick="guardarCambioEstatus()">Guardar Cambios</button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', cargarIncidencias);

async function cargarIncidencias() {
    const filtros = {
        serie: document.getElementById('filtroSerie').value,
        estatus: document.getElementById('filtroEstatus').value,
        f_inicio: document.getElementById('fechaInicio').value,
        f_fin: document.getElementById('fechaFin').value
    };

    try {
        const res = await fetch('AUD_controller/get_incidencias.php', {
            method: 'POST',
            body: JSON.stringify(filtros)
        });
        const incidencias = await res.json();
        let html = '';

        incidencias.forEach(i => {
            let badge = '';
            if(i.estatus === 'Pendiente') badge = '<span class="badge bg-danger">Pendiente</span>';
            if(i.estatus === 'Proceso') badge = '<span class="badge bg-warning text-dark">Proceso</span>';
            if(i.estatus === 'Terminada') badge = '<span class="badge bg-success">Terminada</span>';

            html += `
                <tr>
                    <td><strong>${i.no_serie}</strong></td>
                    <td>${i.folio}</td>
                    <td>${i.fecha_incidencia}</td>
                    <td>${i.descripcion}</td>
                    <td>${i.fecha_finalizacion || '---'}</td>
                    <td>${badge}</td>
                    <td class="text-center">
                        ${i.estatus !== 'Terminada' ? 
                          `<button class="btn btn-sm btn-outline-primary" onclick="abrirModal(${i.id}, '${i.estatus}')">
                             <i class="bi bi-pencil-square"></i> Gestionar
                           </button>` : 
                          `<i class="bi bi-check-circle-fill text-success" title="Finalizada"></i>`
                        }
                    </td>
                </tr>`;
        });
        document.getElementById('tablaIncidenciasBody').innerHTML = html || '<tr><td colspan="7" class="text-center">No se encontraron incidencias</td></tr>';
    } catch (e) { console.error(e); }
}

function abrirModal(id, estatus) {
    document.getElementById('modal_id_incidencia').value = id;
    document.getElementById('modal_estatus').value = estatus;
    document.getElementById('modal_obs').value = '';
    new bootstrap.Modal(document.getElementById('modalGestion')).show();
}

async function guardarCambioEstatus() {
    const payload = {
        id: document.getElementById('modal_id_incidencia').value,
        estatus: document.getElementById('modal_estatus').value,
        obs: document.getElementById('modal_obs').value
    };

    if(!payload.obs) return Swal.fire('Atención', 'Debe agregar una observación', 'warning');

    const res = await fetch('AUD_controller/actualizar_incidencia.php', {
        method: 'POST',
        body: JSON.stringify(payload)
    });
    const data = await res.json();
    
    if(data.status === 'success') {
        Swal.fire('¡Éxito!', 'Estatus actualizado', 'success');
        bootstrap.Modal.getInstance(document.getElementById('modalGestion')).hide();
        cargarIncidencias();
    }
}
</script>