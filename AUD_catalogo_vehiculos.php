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
    <div class="card shadow">
        <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Catálogo de Flotilla</h5>
            <button class="btn btn-success btn-sm" onclick="nuevoVehiculo()">+ Agregar Unidad</button>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover align-middle" id="tablaVehiculos">
                    <thead class="table-light">
                        <tr>
                            <th>No. Serie</th> <th>Vehículo</th> <th>Placas</th> <th>Sucursal</th> <th>Responsable</th> <th>Estatus</th> <th class="text-center">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><strong>SN-987654321</strong></td>
                            <td>Toyota Hilux 2023</td>
                            <td><span class="badge bg-secondary">ABC-123-D</span></td>
                            <td>Sucursal Norte</td>
                            <td>Juan Pérez</td>
                            <td><span class="badge bg-success">Activo</span></td>
                            <td class="text-center">
                                <div class="btn-group" role="group">
                                    <button type="button" class="btn btn-sm btn-outline-primary" onclick="verDetalles(1)">
                                        <i class="bi bi-eye"></i> Detalles
                                    </button>
                                    
                                    <div class="btn-group" role="group">
                                        <button type="button" class="btn btn-sm btn-outline-primary dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false"></button>
                                        <ul class="dropdown-menu dropdown-menu-end shadow">
                                            <li>
                                                <a class="dropdown-item" href="#" onclick="verHistorial(1)">
                                                    <i class="bi bi-clock-history"></i> Ver Historial
                                                </a>
                                            </li>
                                            <li><hr class="dropdown-divider"></li>
                                            <li>
                                                <a class="dropdown-item text-danger" href="#" onclick="confirmarBaja(1, 'SN-987654321')">
                                                    <i class="bi bi-trash"></i> Dar de Baja
                                                </a>
                                            </li>
                                        </ul>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalDetalles" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">Ficha Técnica de la Unidad</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="contenidoDetalles">
                <div class="row">
                    <div class="col-6"><strong>No. Serie:</strong> <span id="det_serie"></span></div>
                    <div class="col-6"><strong>Fecha Alta:</strong> <span id="det_alta"></span></div>
                    <hr>
                    <div class="col-4"><strong>Marca:</strong> <p id="det_marca"></p></div>
                    <div class="col-4"><strong>Modelo:</strong> <p id="det_modelo"></p></div>
                    <div class="col-4"><strong>Año:</strong> <p id="det_anio"></p></div>
                    <hr>
                    <div class="col-6"><strong>Placas:</strong> <p id="det_placas"></p></div>
                    <div class="col-6"><strong>Aseguradora:</strong> <p id="det_seguro"></p></div>
                    <div class="col-12 mt-2">
                        <div class="alert alert-info">
                            <strong>Estatus Actual:</strong> <span id="det_estatus"></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>


<script>
// Variable global para la tabla
let tablaVehiculos;

async function cargarCatalogo() {
    try {
        const response = await fetch('AUD_controller/get_vehiculos.php');
        const vehiculos = await response.json();
        
        const tbody = document.querySelector('#tablaVehiculos tbody');
        
        // Destruir tabla si ya existía para reinicializarla
        if ($.fn.DataTable.isDataTable('#tablaVehiculos')) {
            $('#tablaVehiculos').DataTable().destroy();
        }

        tbody.innerHTML = ''; 

        vehiculos.forEach(v => {
            const btnBaja = v.estatus !== 'Baja' 
                ? `<li><a class="dropdown-item text-danger" href="#" onclick="confirmarBaja(${v.id}, '${v.no_serie}')"><i class="bi bi-trash"></i> Dar de Baja</a></li>`
                : `<li><span class="dropdown-item text-muted small">Unidad Inactiva</span></li>`;

            tbody.innerHTML += `
                <tr>
                    <td><strong>${v.no_serie}</strong></td>
                    <td>${v.marca} ${v.modelo} ${v.anio}</td>
                    <td><span class="badge bg-secondary">${v.placas}</span></td>
                    <td>${v.sucursal_id}</td> 
                    <td>${v.responsable_id}</td>
                    <td><span class="badge ${v.estatus === 'Baja' ? 'bg-danger' : 'bg-success'}">${v.estatus}</span></td>
                    <td class="text-center">
                        <div class="btn-group">
                            <button class="btn btn-sm btn-outline-primary" onclick="verDetalles(${v.id})">Detalles</button>
                            <button class="btn btn-sm btn-outline-primary dropdown-toggle" data-bs-toggle="dropdown"></button>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="#" onclick="verHistorial(${v.id})">Historial</a></li>
                                ${btnBaja}
                            </ul>
                        </div>
                    </td>
                </tr>`;
        });

        // Inicializar DataTable DESPUÉS de llenar el HTML
        inicializarDataTable();

    } catch (error) {
        console.error("Error al cargar catálogo:", error);
    }
}

function inicializarDataTable() {
    $('#tablaVehiculos').DataTable({
        "language": { "url": "//cdn.datatables.net/plug-ins/1.10.16/i18n/Spanish.json" },
        "responsive": true,
        "order": [[0, 'desc']],
        dom: 'lBfrtip',
        buttons: [
            {
                text: '<i class="fas fa-file-excel"></i> Reporte Excel',
                className: 'btn btn-success btn-sm m-2',
                action: function() { /* Tu lógica de Excel */ }
            },
            {
                extend: 'print',
                text: 'Imprimir',
                className: 'btn btn-info btn-sm m-2'
            }
        ]
    });
}

// Única llamada al inicio
document.addEventListener('DOMContentLoaded', cargarCatalogo);

function confirmarBaja(id, serie) {
    Swal.fire({
        title: '¿Dar de baja unidad?',
        text: `Se desactivará la serie ${serie}. Ingrese el motivo:`,
        input: 'textarea',
        inputPlaceholder: 'Motivo de la baja (venta, siniestro, etc.)...',
        showCancelButton: true,
        confirmButtonText: 'Confirmar Baja',
        confirmButtonColor: '#d33',
        preConfirm: (motivo) => {
            if (!motivo) { Swal.showValidationMessage('El motivo es obligatorio'); }
            return motivo;
        }
    }).then((result) => {
        if (result.isConfirmed) {
            // Enviamos al controlador que creamos antes
            const formData = new FormData();
            formData.append('id', id);
            formData.append('motivo', result.value);

            fetch('AUD_controller/procesar_baja.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if(data.status === 'success') {
                    Swal.fire('¡Baja exitosa!', data.message, 'success');
                    cargarCatalogo(); // Recargamos la tabla para ver el cambio de color
                } else {
                    Swal.fire('Error', data.message, 'error');
                }
            });
        }
    });
}
async function verDetalles(id) {
    try {
        const response = await fetch(`AUD_controller/get_vehiculo_detalle.php?id=${id}`);
        const v = await response.json();

        // Llenamos el modal con los IDs que pusiste en tu HTML
        document.getElementById('det_serie').textContent = v.no_serie;
        document.getElementById('det_alta').textContent = v.fecha_alta;
        document.getElementById('det_marca').textContent = v.marca;
        document.getElementById('det_modelo').textContent = v.modelo;
        document.getElementById('det_anio').textContent = v.anio;
        document.getElementById('det_placas').textContent = v.placas;
        document.getElementById('det_seguro').textContent = v.aseguradora;
        document.getElementById('det_estatus').textContent = v.estatus;

        // Mostramos el modal
        new bootstrap.Modal(document.getElementById('modalDetalles')).show();
    } catch (error) {
        Swal.fire('Error', 'No se pudo obtener el detalle.', 'error');
    }
}
</script>
<?php 
include("src/templates/adminfooter.php");
?>