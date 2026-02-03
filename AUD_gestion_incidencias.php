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
    <div class="card shadow mb-4">
        <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="bi bi-gear-fill"></i> Gestión de Tareas (Incidencias) Pendientes</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" id="tablaIncidencias">
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
</div>

<div class="modal fade" id="modalGestion" tabindex="-1">
    <div class="modal-dialog modal-lg"> <div class="modal-content">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title"><i class="bi bi-tools"></i> Gestión de Incidencia</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="modal_id_incidencia">
                
                <div class="row">
                    <div class="col-md-6 border-end">
                        <div class="mb-3">
                            <label class="fw-bold">Nuevo Estatus</label>
                            <select id="modal_estatus" class="form-select border-primary">
                                <option value="Pendiente">Pendiente</option>
                                <option value="Proceso">Proceso</option>
                                <option value="Terminada">Terminada</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="fw-bold">Observaciones</label>
                            <textarea id="modal_obs" class="form-control" rows="4" placeholder="Describa el avance..."></textarea>
                        </div>
                        <hr>
                        <div class="d-grid">
                            <button type="button" class="btn btn-primary" onclick="solicitarEvidenciaIncidencia()">
                                <i class="bi bi-envelope-at"></i> Solicitar Evidencia por Correo
                            </button>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <label class="fw-bold d-block mb-2">Evidencias de la Incidencia</label>
                        <div id="contenedorEvidenciasIncidencia" class="bg-light p-2 rounded border" style="min-height: 200px; max-height: 300px; overflow-y: auto;">
                            <p class="text-muted text-center small mt-4">Cargando evidencias...</p>
                        </div>
                    </div>
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
let tablaIncidenciasDT;

document.addEventListener('DOMContentLoaded', () => {
    cargarIncidencias();
});

async function cargarIncidencias() {
    try {
        if ($.fn.DataTable.isDataTable('#tablaIncidencias')) {
            $('#tablaIncidencias').DataTable().destroy();
        }

        const res = await fetch('AUD_controller/get_incidencias.php', {
            method: 'POST',
            body: JSON.stringify({})
        });
        const incidencias = await res.json();
        let html = '';

        incidencias.forEach(i => {
            let badge = '';
            if(i.estatus === 'Pendiente') badge = '<span class="badge bg-danger p-1 fs-6">Pendiente</span>';
            if(i.estatus === 'Proceso') badge = '<span class="badge bg-warning text-dark p-1 fs-6">Proceso</span>';
            if(i.estatus === 'Terminada') badge = '<span class="badge bg-success p-1 fs-6">Terminada</span>';

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
                        `<button class="btn btn-sm btn-outline-primary" onclick="abrirModal(${i.id}, '${i.estatus}', '${i.observaciones}')">
                            <i class="bi bi-pencil-square"></i> Gestionar
                        </button>` : 
                        `<button class="btn btn-sm btn-outline-success" onclick="abrirModal(${i.id}, '${i.estatus}', '${i.observaciones}')">
                            <i class="bi bi-eye-fill"></i> Ver Detalles
                        </button>`
                        }
                    </td>
                </tr>`;
        });
        
        document.getElementById('tablaIncidenciasBody').innerHTML = html;
        inicializarDataTable();
    } catch (e) { console.error(e); }
}

function inicializarDataTable() {
    tablaIncidenciasDT = $('#tablaIncidencias').DataTable({
        "pageLength": 10,
        "order": [[2, "desc"]],
        "language": { "url": "https://cdn.datatables.net/plug-ins/1.10.24/i18n/Spanish.json" },
        "columnDefs": [{ "orderable": false, "targets": 6 }],
        "responsive": true
    });
}

// --- FUNCIÓN CORREGIDA (SOLO UNA VEZ) ---
async function abrirModal(id, estatus, observaciones) {
    document.getElementById('modal_id_incidencia').value = id;
    document.getElementById('modal_estatus').value = estatus;
    document.getElementById('modal_obs').value = observaciones;
    
    const esTerminada = (estatus === 'Terminada');
    document.getElementById('modal_estatus').disabled = esTerminada;
    document.getElementById('modal_obs').disabled = esTerminada;
    document.querySelector('.modal-footer .btn-success').style.display = esTerminada ? 'none' : 'block';
    document.querySelector('.btn-primary[onclick="solicitarEvidenciaIncidencia()"]').style.display = esTerminada ? 'none' : 'block';


    const contenedor = document.getElementById('contenedorEvidenciasIncidencia');
    contenedor.innerHTML = '<div class="text-center mt-4"><div class="spinner-border spinner-border-sm text-primary"></div></div>';
    
    const myModal = new bootstrap.Modal(document.getElementById('modalGestion'));
    myModal.show();
    
    try {
        const res = await fetch(`AUD_controller/get_evidencias_incidencia.php?id=${id}`);
        const fotos = await res.json();
        
        if(fotos.length === 0) {
            contenedor.innerHTML = '<p class="text-center text-muted small mt-4">No hay evidencias cargadas aún.</p>';
        } else {
            let htmlFotos = '<div class="row g-2">';
            fotos.forEach(f => {
                // Verificar si es PDF por la extensión
                const esPdf = f.ruta.toLowerCase().endsWith('.pdf');
                
                htmlFotos += `
                    <div class="col-6 mb-2">
                        <div class="card h-100 shadow-sm border-0">
                            <a href="${f.ruta}" target="_blank" class="text-decoration-none text-center p-1">
                                ${esPdf ? 
                                    // Diseño para PDF
                                    `<div class="bg-light d-flex flex-column align-items-center justify-content-center rounded" style="height:80px;">
                                        <i class="bi bi-file-earmark-pdf-fill text-danger fs-2"></i>
                                        <span class="text-dark d-block small mt-1" style="font-size: 8px;">VER DOCUMENTO</span>
                                    </div>` 
                                    : 
                                    // Diseño para Imagen
                                    `<img src="${f.ruta}" class="img-thumbnail w-100" style="height:80px; object-fit:cover;">`
                                }
                            </a>
                            <div class="p-1">
                                <small class="d-block text-muted text-center" style="font-size:9px;">${f.fecha}</small>
                            </div>
                        </div>
                    </div>`;
            });
            htmlFotos += '</div>';
            contenedor.innerHTML = htmlFotos;
        }
    } catch (e) {
        contenedor.innerHTML = '<p class="text-danger small">Error al conectar con el servidor.</p>';
    }
}

async function solicitarEvidenciaIncidencia() {
    const id = document.getElementById('modal_id_incidencia').value;
    const btn = event.target.closest('button'); // Captura el botón clicado

    const result = await Swal.fire({
        title: '¿Solicitar evidencia?',
        text: "Se enviará un correo al responsable.",
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#80bf1f',
        confirmButtonText: 'Sí, enviar'
    });

    if (result.isConfirmed) {
        // Bloquear botón y mostrar carga
        btn.disabled = true;
        Swal.fire({ title: 'Enviando...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });

        try {
            const res = await fetch('AUD_controller/enviar_solicitud_incidencia.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({ id: id })
            });
            const data = await res.json();
            
            if(data.status === 'success') {
                Swal.fire('¡Enviado!', `El correo ha sido enviado a: <b>${data.email}</b>`, 'success');
            } else {
                Swal.fire('Error', data.message, 'error');
            }
        } catch (e) {
            Swal.fire('Error', 'No se pudo conectar con el servidor de correos.', 'error');
        } finally {
            btn.disabled = false;
        }
    }
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
<?php 
include("src/templates/adminfooter.php");
?>