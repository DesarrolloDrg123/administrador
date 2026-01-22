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
                <table class="table table-hover align-middle" id="tablaHistorial">
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

<script>
document.addEventListener('DOMContentLoaded', cargarHistorial);

async function cargarHistorial() {
    try {
        const res = await fetch('AUD_controller/get_historial.php');
        const data = await res.json();
        let html = '';

        data.forEach(a => {
            // Badge de puntuación (ejemplo: rojo si es baja, verde si es alta)
            let colorPuntos = a.calif_total < 70 ? 'danger' : (a.calif_total < 90 ? 'warning text-dark' : 'success');
            
            // Estado de las evidencias (fotos)
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
                    <td class="text-center">
                        <div class="btn-group">
                            <button class="btn btn-sm btn-outline-primary" onclick="verReporte(${a.id})" title="Ver PDF/Detalle">
                                <i class="fas fa-file-pdf"></i>
                            </button>
                            <button class="btn btn-sm btn-outline-info" onclick="verGaleria('${a.folio}')" title="Ver Fotos">
                                <i class="fas fa-images"></i>
                            </button>
                        </div>
                    </td>
                </tr>`;
        });
        document.getElementById('historialBody').innerHTML = html;
        
        $('#tablaHistorial').DataTable({
            "language": { "url": "https://cdn.datatables.net/plug-ins/1.10.24/i18n/Spanish.json" },
            "order": [[1, "desc"]]
        });
    } catch (e) { console.error(e); }
}

function verReporte(id) {
    // Aquí rediriges a la página que genera el reporte (que haremos después)
    window.open(`reporte_auditoria.php?id=${id}`, '_blank');
}

function verGaleria(folio) {
    // Abrir la carpeta de fotos o una vista de galería
    window.location.href = `AUD_ver_evidencias.php?folio=${folio}`;
}
</script>