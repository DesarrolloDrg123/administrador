<?php
session_start();
include("src/templates/adminheader.php");
?>

<div class="container-fluid mt-4">
    <div class="card shadow">
        <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="bi bi-journal-check"></i> Historial de Auditorías Realizadas</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover align-middle" id="tablaHistorial">
                    <thead class="table-secondary">
                        <tr>
                            <th>Folio</th>
                            <th>Fecha</th>
                            <th>Vehículo (Serie)</th>
                            <th>Auditor</th>
                            <th>Puntuación</th>
                            <th>Evidencias</th>
                            <th>Estatus</th>
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
                <input type="hidden" id="det_id_auditoria">

                <button type="button" id="btnTerminarAuditoria" class="btn btn-danger me-auto" onclick="confirmarTerminarAuditoria()">
                    <i class="bi bi-lock-fill"></i> Terminar y Bloquear
                </button>

                <button type="button" id="btnSolicitarMasFotos" class="btn btn-warning" onclick="solicitarMasFotos()">
                    <i class="bi bi-envelope-plus"></i> Solicitar más Evidencias
                </button>
                
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
            let colorPuntos = a.calif_total < 70 ? 'danger' : (a.calif_total < 90 ? 'warning text-dark' : 'success');
            let statusEvidencia = a.fecha_subida_evidencia 
                ? `<span class="badge bg-success p-1 fs-6" title="${a.fecha_subida_evidencia}"><i class="fas fa-camera"></i> Subidas</span>` 
                : `<span class="badge bg-secondary p-1 fs-6"><i class="fas fa-clock"></i> Pendientes</span>`;

            html += `
                <tr>
                    <td><strong>${a.folio}</strong></td>
                    <td>${a.fecha_auditoria}</td>
                    <td>${a.no_serie} <br><small class="text-muted">${a.marca} ${a.modelo}</small></td>
                    <td>${a.auditor_nombre}</td>
                    <td><span class="badge bg-${colorPuntos} fs-6">${a.calif_total} pts</span></td>
                    <td>${statusEvidencia}</td>
                    <td>${a.estatus}</td>
                    <td class="text-center">
                        <button class="btn btn-sm btn-outline-primary" onclick="verReporte(${a.id})" title="Ver Detalles">
                            Detalles
                        </button>
                    </td>
                </tr>`;
        });
        document.getElementById('historialBody').innerHTML = html;
        
        $('#tablaHistorial').DataTable({
            "language": { "url": "https://cdn.datatables.net/plug-ins/1.10.24/i18n/Spanish.json" },
            "order": [[1, "desc"]],
            "destroy": true // Para evitar error al recargar
        });
    } catch (e) { console.error(e); }
}

async function verReporte(id) {
    try {
        const res = await fetch(`AUD_controller/get_detalle_auditoria.php?id=${id}`);
        const data = await res.json();
        const a = data.cabecera;

        document.getElementById('det_id_auditoria').value = id;
        document.getElementById('det_folio').innerText = a.folio;
        document.getElementById('det_vehiculo').innerText = `${a.marca} ${a.modelo}`;
        document.getElementById('det_serie').innerText = `Serie: ${a.no_serie}`;
        document.getElementById('det_puntos').innerText = `${a.calif_total}/100`;
        document.getElementById('det_auditor').innerText = a.auditor;
        document.getElementById('det_fecha').innerText = a.fecha_auditoria;
        document.getElementById('det_obs').innerHTML = a.observaciones || 'Sin observaciones.';

        let tablaHtml = '';
        data.detalles.forEach(d => {
            const badge = (d.valor_seleccionado === 'Bueno' || d.valor_seleccionado === 'SI') ? 'bg-success' : 'bg-danger';
            tablaHtml += `<tr><td>${d.pregunta}</td><td><span class="badge ${badge}">${d.valor_seleccionado}</span></td><td class="text-center fw-bold">${d.puntos_obtenidos}</td></tr>`;
        });
        document.getElementById('det_tabla_body').innerHTML = tablaHtml;

        // --- SECCIÓN DE EVIDENCIAS (FOTOS Y PDF) ---
        let fotosHtml = '';
        if(data.fotos.length > 0) {
            data.fotos.forEach(f => {
                // Verificamos si la ruta o el campo tipo indica que es PDF
                const esPdf = f.ruta_archivo.toLowerCase().endsWith('.pdf') || f.tipo_archivo === 'pdf';
                
                if(esPdf) {
                    // Diseño para el cuadro de PDF
                    fotosHtml += `
                    <div class="col-md-3">
                        <div class="card h-100 border-danger shadow-sm" style="cursor:pointer; min-height:120px;" onclick="window.open('${f.ruta_archivo}', '_blank')">
                            <div class="card-body d-flex flex-column align-items-center justify-content-center text-danger">
                                <i class="bi bi-file-earmark-pdf-fill fs-1"></i>
                                <small class="text-dark fw-bold mt-2 text-truncate w-100">Ver Documento</small>
                            </div>
                        </div>
                    </div>`;
                } else {
                    // Diseño para Fotos
                    fotosHtml += `
                    <div class="col-md-3">
                        <img src="${f.ruta_archivo}" class="img-thumbnail shadow-sm" 
                             style="height:120px; width:100%; object-fit:cover; cursor:pointer" 
                             onclick="window.open('${f.ruta_archivo}', '_blank')"
                             title="Click para ampliar">
                    </div>`;
                }
            });
        } else { 
            fotosHtml = '<p class="text-muted w-100 text-center">No hay evidencias disponibles.</p>'; 
        }
        document.getElementById('det_fotos').innerHTML = fotosHtml;
        // -------------------------------------------

        const btnTerminar = document.getElementById('btnTerminarAuditoria');
        const btnSolicitar = document.getElementById('btnSolicitarMasFotos');
        
        if (!a.token_evidencia || a.estatus === 'Finalizado') { 
            btnTerminar.style.display = 'none'; 
            btnSolicitar.style.display = 'none';
        } else {
            btnTerminar.style.display = 'block';
            btnSolicitar.style.display = 'block';
        }

        new bootstrap.Modal(document.getElementById('modalDetalleAuditoria')).show();
    } catch (e) { console.error(e); }
}

function confirmarTerminarAuditoria() {
    Swal.fire({
        title: '¿Terminar Auditoría?',
        text: "Se bloqueará la subida de evidencias.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        confirmButtonText: 'Sí, terminar',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            ejecutarTerminar();
        }
    });
}

async function ejecutarTerminar() {
    const id = document.getElementById('det_id_auditoria').value;
    if (!id) return;

    try {
        const response = await fetch('AUD_controller/terminar_auditoria.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `id=${id}`
        });
        const res = await response.json();
        if (res.success) {
            Swal.fire('¡Listo!', 'Auditoría bloqueada.', 'success').then(() => {
                location.reload();
            });
        } else {
            Swal.fire('Error', res.error, 'error');
        }
    } catch (e) { console.error(e); }
}

async function solicitarMasFotos() {
    const id = document.getElementById('det_id_auditoria').value;
    Swal.fire({
        title: '¿Enviar notificación?',
        text: "Se enviará un correo para solicitar más fotos.",
        icon: 'info',
        showCancelButton: true,
        confirmButtonText: 'Enviar'
    }).then(async (result) => {
        if (result.isConfirmed) {
            Swal.fire({ title: 'Enviando...', didOpen: () => Swal.showLoading() });
            try {
                const response = await fetch('AUD_controller/notificar_mas_fotos.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `id=${id}`
                });
                const res = await response.json();
                Swal.fire(res.status === 'success' ? 'Enviado' : 'Error', res.message, res.status);
            } catch (e) { Swal.fire('Error', 'No se pudo conectar', 'error'); }
        }
    });
}

function imprimirDesdeModal() { window.print(); }
</script>
<?php 
include("src/templates/adminfooter.php");
?>