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
                <h5 class="modal-title"><i class="bi bi-truck"></i> Detalle Completo de la Unidad</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-12"><h6 class="text-muted border-bottom">Datos de Identificación</h6></div>
                    <div class="col-md-4"><strong>No. Serie:</strong><br><span id="det_serie"></span></div>
                    <div class="col-md-4"><strong>Fecha Alta:</strong><br><span id="det_alta"></span></div>
                    <div class="col-md-4"><strong>Estatus:</strong><br><span id="det_estatus"></span></div>
                    <div class="col-md-4"><strong>Marca:</strong><br><span id="det_marca"></span></div>
                    <div class="col-md-4"><strong>Modelo:</strong><br><span id="det_modelo"></span></div>
                    <div class="col-md-4"><strong>Año:</strong><br><span id="det_anio"></span></div>

                    <div class="col-12 mt-3"><h6 class="text-muted border-bottom">Asignación y Responsables</h6></div>
                    <div class="col-md-4"><strong>Sucursal:</strong><br><span id="det_sucursal"></span></div>
                    <div class="col-md-4"><strong>Responsable:</strong><br><span id="det_responsable"></span></div>
                    <div class="col-md-4"><strong>Gerente a Reportar:</strong><br><span id="det_gerente"></span></div>

                    <div class="col-12 mt-3"><h6 class="text-muted border-bottom">Documentación y Seguro</h6></div>
                    <div class="col-md-4"><strong>Placas:</strong><br><span id="det_placas"></span></div>
                    <div class="col-md-4"><strong>Tarjeta Circulación:</strong><br><span id="det_tarjeta"></span></div>
                    <div class="col-md-4"><strong>No. Licencia:</strong><br><span id="det_licencia"></span></div>
                    <div class="col-md-4"><strong>Vigencia Licencia:</strong><br><span id="det_vig_licencia"></span></div>
                    <div class="col-md-4"><strong>Aseguradora:</strong><br><span id="det_aseguradora"></span></div>
                    <div class="col-md-4"><strong>No. Póliza:</strong><br><span id="det_poliza"></span></div>
                    <div class="col-md-4"><strong>Vigencia Póliza:</strong><br><span id="det_vig_poliza"></span></div>
                    <div class="col-md-4"><strong>Tel. Siniestro:</strong><br><span id="det_tel_siniestro"></span></div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalEditar" tabindex="-1" aria-labelledby="modalEditarLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <form id="formEditarVehiculo">
            <div class="modal-content">
                <div class="modal-header bg-primary text-dark">
                    <h5 class="modal-title" id="modalEditarLabel"><i class="bi bi-pencil-square"></i> Editar Información de Unidad</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="id" id="edit_id">

                    <div class="row g-3">
                        <div class="col-12 mt-3">
                            <h6 class="text-primary border-bottom pb-2 small fw-bold text-uppercase">
                                <i class="bi bi-info-circle-fill me-2"></i>Datos Generales (No Editables)
                            </h6>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label small text-muted mb-1">No. de Serie</label>
                            <input type="text" id="edit_no_serie" name="no_serie" class="form-control form-control-sm bg-light fw-bold" readonly>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label small text-muted mb-1">Fecha de Alta</label>
                            <input type="text" id="edit_fecha_alta" class="form-control form-control-sm bg-light" readonly>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label small text-muted mb-1">Vehículo (Marca / Modelo / Año)</label>
                            <div class="d-flex gap-2">
                                <input type="text" id="edit_marca" class="form-control form-control-sm bg-light flex-grow-1" readonly>
                                <input type="text" id="edit_modelo" class="form-control form-control-sm bg-light flex-grow-1" readonly>
                                <input type="text" id="edit_anio" class="form-control form-control-sm bg-light" style="width: 80px;" readonly>
                            </div>
                        </div>

                        <div class="col-12 mt-4"><h6 class="text-primary border-bottom small fw-bold">DATOS MODIFICABLES (GENERAN HISTORIAL)</h6></div>

                        <div class="col-md-4">
                            <label class="form-label small">Sucursal Actual</label>
                            <select id="edit_sucursal_id" name="sucursal_id" class="form-select form-select-sm" required>
                                <option value="">Cargando...</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small">Responsable</label>
                            <select id="edit_responsable_id" name="responsable_id" class="form-select form-select-sm" required>
                                <option value="">Cargando...</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small">Gerente a Reportar</label>
                            <select id="edit_gerente_reportar_id" name="gerente_reportar_id" class="form-select form-select-sm" required>
                                <option value="">Cargando...</option>
                            </select>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label small">Placas</label>
                            <input type="text" id="edit_placas" name="placas" class="form-control form-control-sm">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small">No. Licencia</label>
                            <input type="text" id="edit_no_licencia" name="no_licencia" class="form-control form-control-sm">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small">Vigencia Licencia</label>
                            <input type="date" id="edit_vigencia_licencia" name="fecha_vencimiento_licencia" class="form-control form-control-sm">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small">Tarjeta Circulación</label>
                            <input type="text" id="edit_tarjeta_circulacion" name="tarjeta_circulacion" class="form-control form-control-sm">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label small">Aseguradora</label>
                            <input type="text" id="edit_aseguradora" name="aseguradora" class="form-control form-control-sm">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small">No. Póliza</label>
                            <input type="text" id="edit_no_poliza" name="no_poliza" class="form-control form-control-sm">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small">Vigencia Póliza</label>
                            <input type="date" id="edit_vigencia_poliza" name="vigencia_poliza" class="form-control form-control-sm">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small">Tel. Siniestro</label>
                            <input type="text" id="edit_telefono_siniestro" name="telefono_siniestro" class="form-control form-control-sm">
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-sm btn-primary">
                        <i class="bi bi-save"></i> Guardar Cambios
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>


<script>
// Bandera para cargar los selects una sola vez
let selectsCargados = false;

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
                ?   `<li><a class="dropdown-item" href="#" onclick="prepararEdicion(${v.id})"><i class="bi bi-pencil"></i> Editar</a></li>
                    <li><a class="dropdown-item text-danger" href="#" onclick="confirmarBaja(${v.id}, '${v.no_serie}')"><i class="bi bi-trash"></i> Dar de Baja</a></li>`
                : `<li><span class="dropdown-item text-muted small">Unidad Inactiva</span></li>`;

            tbody.innerHTML += `
                <tr>
                    <td><strong>${v.no_serie}</strong></td>
                    <td>${v.marca} ${v.modelo} ${v.anio}</td>
                    <td><span class="badge bg-secondary p-1 fs-6">${v.placas}</span></td>
                    <td>${v.sucursal_nombre}</td> 
                    <td>${v.responsable_nombre}</td>
                    <td><span class="badge ${v.estatus === 'Baja' ? 'bg-danger' : 'bg-success'} p-1 fs-6">${v.estatus}</span></td>
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

        inicializarDataTable();

    } catch (error) {
        console.error("Error al cargar catálogo:", error);
    }
}

function nuevoVehiculo() {
    window.location.href = 'AUD_agregar_vehiculo.php';
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

// --- MODIFICACIÓN CLAVE: Cargar desde tus archivos existentes ---
async function cargarSelectsEdicion() {
    if (selectsCargados) return; 

    try {
        // Ejecutamos ambas peticiones en paralelo para mayor velocidad
        const [resSuc, resUsr] = await Promise.all([
            fetch('AUD_controller/get_sucursales.php'),      // Tu archivo existente
            fetch('AUD_controller/get_usuarios_activos.php') // Tu archivo existente
        ]);

        const sucursales = await resSuc.json();
        const usuarios = await resUsr.json();

        // 1. Llenar Sucursales
        let opcionesSucursal = '<option value="">Seleccione Sucursal...</option>';
        sucursales.forEach(s => {
            // Ajusta 's.id' y 's.nombre' si en tu BD se llaman diferente
            opcionesSucursal += `<option value="${s.id}">${s.sucursal}</option>`;
        });
        document.getElementById('edit_sucursal_id').innerHTML = opcionesSucursal;

        // 2. Llenar Usuarios (Responsable y Gerente)
        let opcionesUsuario = '<option value="">Seleccione Usuario...</option>';
        usuarios.forEach(u => {
            opcionesUsuario += `<option value="${u.id}">${u.nombre}</option>`;
        });

        document.getElementById('edit_responsable_id').innerHTML = opcionesUsuario;
        document.getElementById('edit_gerente_reportar_id').innerHTML = opcionesUsuario;

        selectsCargados = true; 

    } catch (error) {
        console.error("Error cargando selects (Revisa las rutas):", error);
    }
}

async function prepararEdicion(id) {
    try {
        Swal.fire({ title: 'Cargando...', allowOutsideClick: false, didOpen: () => { Swal.showLoading(); } });

        // 1. Cargar catálogos primero
        await cargarSelectsEdicion();

        // 2. Obtener datos del vehículo
        const response = await fetch(`AUD_controller/get_detalle_vehiculo.php?id=${id}`);
        const v = await response.json();

        if (!v || v.status === 'error') throw new Error(v.message || "Error al obtener datos");

        // 3. IDs y Datos Fijos (Cuidar que el ID exista en el HTML)
        if(document.getElementById('edit_id')) document.getElementById('edit_id').value = v.id;
        if(document.getElementById('edit_no_serie')) document.getElementById('edit_no_serie').value = v.no_serie;
        if(document.getElementById('edit_fecha_alta')) document.getElementById('edit_fecha_alta').value = v.fecha_alta;
        if(document.getElementById('edit_marca')) document.getElementById('edit_marca').value = v.marca;
        if(document.getElementById('edit_modelo')) document.getElementById('edit_modelo').value = v.modelo;
        if(document.getElementById('edit_anio')) document.getElementById('edit_anio').value = v.anio;

        // 4. Datos Modificables (SELECTS)
        // Importante: Verifica que 'sucursal_id' sea el nombre exacto que devuelve tu JSON
        document.getElementById('edit_sucursal_id').value = v.sucursal_id || "";
        document.getElementById('edit_responsable_id').value = v.responsable_id || "";
        document.getElementById('edit_gerente_reportar_id').value = v.gerente_reportar_id || "";
        
        // 5. Otros campos
        document.getElementById('edit_placas').value = v.placas || "";
        document.getElementById('edit_no_licencia').value = v.no_licencia || "";
        document.getElementById('edit_vigencia_licencia').value = v.fecha_vencimiento_licencia || "";
        document.getElementById('edit_tarjeta_circulacion').value = v.tarjeta_circulacion || "";
        document.getElementById('edit_aseguradora').value = v.aseguradora || "";
        document.getElementById('edit_no_poliza').value = v.no_poliza || "";
        document.getElementById('edit_vigencia_poliza').value = v.vigencia_poliza || "";
        document.getElementById('edit_telefono_siniestro').value = v.telefono_siniestro || "";

        Swal.close(); 

        const modalEdit = new bootstrap.Modal(document.getElementById('modalEditar'));
        modalEdit.show();

    } catch (error) {
        console.error("Error detallado:", error);
        Swal.fire('Error', 'No se pudo cargar la información: ' + error.message, 'error');
    }
}

document.getElementById('formEditarVehiculo').addEventListener('submit', async function(e) {
    e.preventDefault();

    const confirmacion = await Swal.fire({
        title: '¿Guardar cambios?',
        text: "Se registrarán los cambios en el historial de la unidad.",
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Sí, actualizar',
        cancelButtonText: 'Revisar'
    });

    if (!confirmacion.isConfirmed) return;

    Swal.showLoading();

    const formData = new FormData(this);

    try {
        const response = await fetch('AUD_controller/actualizar_vehiculo.php', {
            method: 'POST',
            body: formData
        });
        
        const res = await response.json();

        if (res.status === 'success' || res.status === 'info') {
            Swal.fire({
                title: '¡Actualizado!',
                text: res.message,
                icon: 'success',
                timer: 2000
            });
            
            const modalElement = document.getElementById('modalEditar');
            const modalInstance = bootstrap.Modal.getInstance(modalElement);
            modalInstance.hide();
            
            cargarCatalogo(); 
        } else {
            Swal.fire('Error', res.message, 'error');
        }
    } catch (error) {
        console.error("Error al actualizar:", error);
        Swal.fire('Error', 'No se pudo comunicar con el servidor', 'error');
    }
});

async function verHistorial(id) {
    try {
        const response = await fetch(`AUD_controller/get_historial_vehiculo.php?id=${id}`);
        const data = await response.json();

        if (data.length === 0) {
            Swal.fire('Sin registros', 'No hay historial para esta unidad aún.', 'info');
            return;
        }

        let tablaHtml = `
            <div class="table-responsive">
                <table class="table table-sm table-striped small">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Usuario</th>
                            <th>Campo</th>
                            <th>Valor Nuevo/Detalle</th>
                        </tr>
                    </thead>
                    <tbody>`;
        
        data.forEach(h => {
            tablaHtml += `
                <tr>
                    <td>${h.fecha_cambio}</td>
                    <td>${h.nombre_usuario || 'Sistema'}</td>
                    <td><span class="badge bg-info text-dark p-1 fs-6">${h.campo_modificado}</span></td>
                    <td>${h.valor_nuevo}</td>
                </tr>`;
        });

        tablaHtml += `</tbody></table></div>`;

        Swal.fire({
            title: 'Historial de Movimientos',
            html: tablaHtml,
            width: '800px',
            confirmButtonText: 'Cerrar'
        });

    } catch (error) {
        Swal.fire('Error', 'No se pudo cargar el historial', 'error');
    }
}

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
                    cargarCatalogo();
                } else {
                    Swal.fire('Error', data.message, 'error');
                }
            });
        }
    });
}

async function verDetalles(id) {
    try {
        const response = await fetch(`AUD_controller/get_detalle_vehiculo.php?id=${id}`);
        const v = await response.json();

        if (v.status === 'error') throw new Error(v.message);

        document.getElementById('det_serie').innerText = v.no_serie;
        document.getElementById('det_alta').innerText = v.fecha_alta;
        document.getElementById('det_marca').innerText = v.marca;
        document.getElementById('det_modelo').innerText = v.modelo;
        document.getElementById('det_anio').innerText = v.anio;
        document.getElementById('det_sucursal').innerText = v.sucursal_nombre; 
        document.getElementById('det_responsable').innerText = v.responsable_nombre;
        document.getElementById('det_gerente').innerText = v.gerente_nombre;
        document.getElementById('det_placas').innerText = v.placas;
        document.getElementById('det_tarjeta').innerText = v.tarjeta_circulacion;
        document.getElementById('det_licencia').innerText = v.no_licencia;
        document.getElementById('det_vig_licencia').innerText = v.fecha_vencimiento_licencia;
        document.getElementById('det_aseguradora').innerText = v.aseguradora;
        document.getElementById('det_poliza').innerText = v.no_poliza;
        document.getElementById('det_vig_poliza').innerText = v.vigencia_poliza;
        document.getElementById('det_tel_siniestro').innerText = v.telefono_siniestro;

        const badge = document.getElementById('det_estatus');
        badge.innerText = v.estatus;
        badge.className = v.estatus === 'Baja' ? 'badge bg-danger' : 'badge bg-success';

        new bootstrap.Modal(document.getElementById('modalDetalles')).show();
    } catch (error) {
        Swal.fire('Error', 'No se pudieron cargar los detalles', 'error');
    }
}

// Llamada inicial
document.addEventListener('DOMContentLoaded', cargarCatalogo);
</script>

<?php 
include("src/templates/adminfooter.php");
?>