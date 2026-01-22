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
    <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Configuración de Conceptos de Auditoría</h5>
        <button class="btn btn-success btn-sm" onclick="abrirModalConcepto()">+ Nuevo Concepto</button>
    </div>
    <div class="card-body">
        <table class="table table-sm table-hover w-100" id="tablaConceptos">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Tipo</th>
                    <th>Descripción</th>
                    <th>C1</th>
                    <th>C2</th>
                    <th>C3</th>
                    <th>Estatus</th>
                    <th class="text-center">Acciones</th>
                </tr>
            </thead>
            <tbody id="bodyConceptos">
                </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="modalConcepto" tabindex="-1">
    <div class="modal-dialog">
        <form id="formConcepto">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="tituloModal">Agregar Concepto</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="id" id="id_concepto">
                    <div class="mb-3">
                        <label class="form-label">Tipo</label>
                        <select name="tipo" id="tipo" class="form-select" required>
                            <option value="Documento">Documento</option>
                            <option value="Inventario">Inventario</option>
                            <option value="Estado">Estado Vehículo</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Descripción</label>
                        <input type="text" name="descripcion" id="descripcion" class="form-control" required>
                    </div>
                    <div class="row">
                        <div class="col-4">
                            <label class="form-label">C1 (Pts)</label>
                            <input type="number" name="c1" id="c1" class="form-control" value="0">
                        </div>
                        <div class="col-4">
                            <label class="form-label">C2 (Pts)</label>
                            <input type="number" name="c2" id="c2" class="form-control" value="0">
                        </div>
                        <div class="col-4">
                            <label class="form-label">C3 (Pts)</label>
                            <input type="number" name="c3" id="c3" class="form-control" value="0">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary">Guardar</button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    cargarConceptos();
});


function eliminarConcepto(id) {
    Swal.fire({
        title: '¿Desactivar concepto?',
        text: "No se eliminará permanentemente para conservar el historial de auditorías.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sí, desactivar'
    }).then((result) => {
        if (result.isConfirmed) {
            // Enviamos estatus 'N' al controlador
            fetch('AUD_controller/gestion_conceptos.php', {
                method: 'POST',
                body: JSON.stringify({ action: 'delete', id: id })
            })
            .then(res => res.json())
            .then(data => {
                Swal.fire('Actualizado', data.message, 'success');
                cargarConceptos(); // Recarga la tabla
            });
        }
    });
}
// Variable global para la instancia de DataTable
let tablaConceptosDT;

async function cargarConceptos() {
    try {
        const response = await fetch('AUD_controller/get_conceptos_auditoria.php');
        const data = await response.json();
        
        // 1. Destruir instancia previa si existe para evitar errores de reinicialización
        if ($.fn.DataTable.isDataTable('#tablaConceptos')) {
            $('#tablaConceptos').DataTable().destroy();
        }

        const tbody = document.getElementById('bodyConceptos');
        tbody.innerHTML = '';

        data.forEach(c => {
            const badgeClass = c.activo === 'S' ? 'bg-success' : 'bg-secondary';
            const estatusTexto = c.activo === 'S' ? 'Activo' : 'Inactivo';
            const btnEstatus = c.activo === 'S' 
                ? `<button class="btn btn-sm btn-outline-danger" onclick="cambiarEstatusConcepto(${c.id}, 'N')" title="Desactivar"><i class="fas fa-trash fa-2x"></i></button>`
                : `<button class="btn btn-sm btn-outline-success" onclick="cambiarEstatusConcepto(${c.id}, 'S')" title="Activar"><i class="fas fa-check-circle fa-2x"></i></button>`;

            const conceptoData = btoa(JSON.stringify(c));

            tbody.innerHTML += `
                <tr>
                    <td>${c.id}</td>
                    <td><span class="badge bg-info text-dark">${c.tipo}</span></td>
                    <td>${c.descripcion}</td>
                    <td>${c.c1}</td>
                    <td>${c.c2}</td>
                    <td>${c.c3}</td>
                    <td><span class="badge ${badgeClass}">${estatusTexto}</span></td>
                    <td class="text-center">
                        <div class="btn-group">
                            <button class="btn btn-sm btn-outline-warning" onclick="prepararEdicionConcepto('${conceptoData}')" title="Editar">
                                <i class="fas fa-edit fa-2x"></i>
                            </button>
                            ${btnEstatus}
                        </div>
                    </td>
                </tr>`;
        });

        // 2. Inicializar DataTable después de llenar el HTML
        inicializarDataTableConceptos();

    } catch (error) {
        console.error("Error al cargar conceptos:", error);
    }
}

function inicializarDataTableConceptos() {
    tablaConceptosDT = $('#tablaConceptos').DataTable({
        "pageLength": 10,
        "order": [[0, "asc"]], // Ordenar por Tipo por defecto
        "language": {
            "url": "https://cdn.datatables.net/plug-ins/1.10.24/i18n/Spanish.json"
        },
        "columnDefs": [
            { "orderable": false, "targets": 6 } // Desactivar ordenamiento en la columna de Acciones
        ],
        "responsive": true,
        "dom": 'Bfrtip', // Botones de exportación (opcional)
        "buttons": ['excel', 'pdf', 'print']
    });
}

function prepararEdicionConcepto(base64Data) {
    const concepto = JSON.parse(atob(base64Data)); // Decodifica el string base64
    
    document.getElementById('tituloModal').innerText = 'Editar Concepto';
    document.getElementById('id_concepto').value = concepto.id;
    document.getElementById('tipo').value = concepto.tipo;
    document.getElementById('descripcion').value = concepto.descripcion;
    document.getElementById('c1').value = concepto.c1;
    document.getElementById('c2').value = concepto.c2;
    document.getElementById('c3').value = concepto.c3;
    
    const modal = new bootstrap.Modal(document.getElementById('modalConcepto'));
    modal.show();
}

function abrirModalConcepto() {
    document.getElementById('tituloModal').innerText = 'Agregar Concepto';
    document.getElementById('formConcepto').reset();
    document.getElementById('id_concepto').value = ''; // Limpiar ID oculto
    const modal = new bootstrap.Modal(document.getElementById('modalConcepto'));
    modal.show();
}

document.getElementById('formConcepto').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    formData.append('action', 'save'); // Indicamos que es guardar

    try {
        const response = await fetch('AUD_controller/gestion_conceptos.php', {
            method: 'POST',
            body: formData
        });
        const res = await response.json();

        if (res.status === 'success') {
            Swal.fire('¡Logrado!', res.message, 'success');
            bootstrap.Modal.getInstance(document.getElementById('modalConcepto')).hide();
            this.reset();
            document.getElementById('id_concepto').value = ''; // Limpiar ID oculto
            cargarConceptos(); // Refrescar tabla
        } else {
            Swal.fire('Atención', res.message, 'warning');
        }
    } catch (error) {
        Swal.fire('Error', 'No se pudo procesar la solicitud', 'error');
    }
});

// Función para el botón de activar/desactivar
function cambiarEstatusConcepto(id, status) {
    const texto = status === 'N' ? 'desactivar' : 'activar';
    Swal.fire({
        title: `¿Desea ${texto} este concepto?`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Sí, proceder'
    }).then((result) => {
        if (result.isConfirmed) {
            fetch('AUD_controller/gestion_conceptos.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'delete', id: id, nuevo_status: status })
            })
            .then(r => r.json())
            .then(data => {
                cargarConceptos();
            });
        }
    });
}
</script>


<?php 
include("src/templates/adminfooter.php");
?>