<?php
session_start();
include("src/templates/adminheader.php");
require("config/db.php");
?>

<div class="container-fluid mt-4">
    <div class="card shadow">
        <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="bi bi-journal-check"></i> Historial de Auditorías Realizadas</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover align-middle" id="tablaHistorial" style="width:100%">
                    <thead class="table-secondary">
                        <tr>
                            <th>Folio</th>
                            <th>Fecha</th>
                            <th>Vehículo (Serie)</th>
                            <th>Auditor</th>
                            <th>Puntuación</th>
                            <th>Evidencias</th>
                            <th class="text-center">Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="historialBody">
                        </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<div class="modal fade" id="modalDetalleAuditoria" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="bi bi-file-earmark-text"></i> Detalle de Auditoría: <span id="det_folio"></span></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body bg-light">
                <div class="row mb-3">
                    <div class="col-md-4">
                        <div class="card p-2 border-0 shadow-sm">
                            <small class="text-muted">Vehículo</small>
                            <span class="fw-bold" id="det_vehiculo"></span>
                            <small id="det_serie"></small>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card p-2 border-0 shadow-sm text-center">
                            <small class="text-muted">Calificación Total</small>
                            <h3 class="mb-0 text-success fw-bold" id="det_puntos"></h3>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card p-2 border-0 shadow-sm">
                            <small class="text-muted">Auditor / Fecha</small>
                            <span class="fw-bold" id="det_auditor"></span>
                            <small id="det_fecha"></small>
                        </div>
                    </div>
                </div>

                <div class="table-responsive bg-white rounded shadow-sm p-2">
                    <table class="table table-sm table-striped">
                        <thead class="table-dark">
                            <tr>
                                <th>Concepto</th>
                                <th>Resultado</th>
                                <th class="text-center">Puntos</th>
                            </tr>
                        </thead>
                        <tbody id="det_tabla_body">
                            </tbody>
                    </table>
                </div>

                <div class="mt-3">
                    <label class="fw-bold">Observaciones del Auditor:</label>
                    <div id="det_obs" class="p-2 bg-white border rounded"></div>
                </div>

                <div class="mt-3">
                    <label class="fw-bold text-primary"><i class="bi bi-images"></i> Evidencias Fotográficas:</label>
                    <div id="det_fotos" class="row g-2 mt-1 text-center">
                        </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                <button type="button" class="btn btn-primary" onclick="imprimirDesdeModal()"><i class="bi bi-printer"></i> Imprimir</button>
            </div>
        </div>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', cargarHistorial);

async function cargarHistorial() {
    try {
        const res = await fetch('AUD_controller/get_historial.php');
        const data = await res.json();
        let html = '';

        data.forEach(a => {
            // Lógica de colores para puntuación
            let colorPuntos = a.calif_total < 70 ? 'danger' : (a.calif_total < 90 ? 'warning text-dark' : 'success');
            
            // Estado de las evidencias con diseño más limpio
            let statusEvidencia = a.fecha_subida_evidencia 
                ? `<span class="badge rounded-pill bg-success px-3"><i class="fas fa-check-circle me-1"></i> Subidas</span>` 
                : `<span class="badge rounded-pill bg-light text-muted border px-3"><i class="fas fa-clock me-1"></i> Pendientes</span>`;

            html += `
                <tr>
                    <td><span class="fw-bold text-primary">${a.folio}</span></td>
                    <td>${a.fecha_auditoria}</td>
                    <td>
                        <div class="d-flex flex-column">
                            <span>${a.marca} ${a.modelo}</span>
                            <small class="text-muted">${a.no_serie}</small>
                        </div>
                    </td>
                    <td><small>${a.auditor_nombre}</small></td>
                    <td><span class="badge bg-${colorPuntos} fs-6 w-100">${a.calif_total} pts</span></td>
                    <td>${statusEvidencia}</td>
                    <td class="text-center">
                        <div class="btn-group shadow-sm" role="group">
                            <button class="btn btn-sm btn-outline-primary px-3" onclick="verReporte(${a.id})" title="Ver PDF del Reporte">
                                <i class="fas fa-file-pdf"></i>
                                <span class="d-block small" style="font-size: 0.65rem;">Reporte</span>
                            </button>
                            <button class="btn btn-sm btn-outline-info px-3" onclick="verGaleria('${a.folio}')" title="Ver Galería de Fotos">
                                <i class="fas fa-images"></i>
                                <span class="d-block small" style="font-size: 0.65rem;">Fotos</span>
                            </button>
                        </div>
                    </td>
                </tr>`;
        });
        
        document.getElementById('historialBody').innerHTML = html;
        
        // Inicializar DataTable con configuración en español
        $('#tablaHistorial').DataTable({
            "language": { "url": "https://cdn.datatables.net/plug-ins/1.10.24/i18n/Spanish.json" },
            "order": [[1, "desc"]],
            "responsive": true
        });

    } catch (e) { 
        console.error("Error al cargar el historial:", e); 
    }
}

async function verReporte(id) {
    try {
        const res = await fetch(`AUD_controller/get_detalle_auditoria.php?id=${id}`);
        const data = await res.json();
        const a = data.cabecera;

        // Llenar cabecera
        document.getElementById('det_folio').innerText = a.folio;
        document.getElementById('det_vehiculo').innerText = `${a.marca} ${a.modelo}`;
        document.getElementById('det_serie').innerText = `Serie: ${a.no_serie}`;
        document.getElementById('det_puntos').innerText = `${a.calif_total}/100`;
        document.getElementById('det_auditor').innerText = a.auditor;
        document.getElementById('det_fecha').innerText = a.fecha_auditoria;
        document.getElementById('det_obs').innerHTML = a.observaciones || 'Sin observaciones.';

        // Llenar tabla
        let tablaHtml = '';
        data.detalles.forEach(d => {
            const badge = (d.valor_seleccionado === 'SI' || d.valor_seleccionado === 'Bueno') ? 'bg-success' : 'bg-danger';
            tablaHtml += `
                <tr>
                    <td>${d.pregunta}</td>
                    <td><span class="badge ${badge}">${d.valor_seleccionado}</span></td>
                    <td class="text-center fw-bold">${d.puntos_obtenidos}</td>
                </tr>`;
        });
        document.getElementById('det_tabla_body').innerHTML = tablaHtml;

        // Llenar Fotos
        let fotosHtml = '';
        if(data.fotos.length > 0) {
            data.fotos.forEach(f => {
                fotosHtml += `
                    <div class="col-md-3">
                        <img src="${f.ruta_archivo}" class="img-thumbnail" style="height:120px; width:100%; object-fit:cover; cursor:pointer" onclick="window.open('${f.ruta_archivo}')">
                    </div>`;
            });
        } else {
            fotosHtml = '<p class="text-muted">No hay fotos disponibles.</p>';
        }
        document.getElementById('det_fotos').innerHTML = fotosHtml;

        // Mostrar Modal
        new bootstrap.Modal(document.getElementById('modalDetalleAuditoria')).show();

    } catch (e) {
        console.error(e);
        Swal.fire('Error', 'No se pudo cargar la información', 'error');
    }
}

function imprimirDesdeModal() {
    // Si aún quieres la opción de imprimir, puedes llamar al archivo que hicimos antes
    const folio = document.getElementById('det_folio').innerText;
    // O simplemente abrir el PDF que ya teníamos
    window.print(); 
}

function verGaleria(folio) {
    window.location.href = `AUD_controller/ver_evidencias.php?folio=${folio}`;
}
</script>