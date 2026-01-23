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

<div class="container mt-4">
    <div class="card shadow">
        <div class="card-header bg-primary text-white">
            <h4 class="mb-0">Registro de Nueva Unidad (Flotilla)</h4>
        </div>
        <div class="card-body">
            <form id="formVehiculo" class="needs-validation" novalidate>
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">No. de Serie *</label>
                        <input type="text" name="no_serie" class="form-control" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Fecha de Alta *</label>
                        <input type="date" name="fecha_alta" class="form-control" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Marca *</label>
                        <input type="text" name="marca" class="form-control" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Modelo *</label>
                        <input type="text" name="modelo" class="form-control" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Año *</label>
                        <input type="number" name="anio" class="form-control" min="1900" max="2099" required>
                    </div>
                    
                    <hr class="my-3">
                    
                    <div class="col-md-4">
                        <label class="form-label">Sucursal *</label>
                        <select name="sucursal_id" class="form-select" required>
                            <option value="">Seleccione sucursal...</option>
                            </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Responsable *</label>
                        <select name="responsable_id" class="form-select" required>
                            <option value="">Seleccione usuario activo...</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Gerente a Reportar *</label>
                        <select name="gerente_reportar_id" class="form-select" required>
                            <option value="">Seleccione usuario activo...</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">No. de Licencia *</label>
                        <input type="text" name="no_licencia" class="form-control" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Fecha de Vencimiento de Licencia</label>
                        <input type="date" name="vigencia_licencia" class="form-control" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Placas *</label>
                        <input type="text" name="placas" class="form-control" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Tarjeta de Circulación *</label>
                        <input type="text" name="tarjeta_circulacion" class="form-control" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Aseguradora *</label>
                        <input type="text" name="aseguradora" class="form-control" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">No. de Poliza</label>
                        <input type="text" name="no_poliza" class="form-control" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Vigencia Póliza *</label>
                        <input type="date" name="vigencia_poliza" class="form-control" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Telefono Reporte Siniestro *</label>
                        <input type="number" name="telefono_siniestro" class="form-control" min="0000000001" max="9999999999" required>
                    </div>
                    <div class="col-12 mt-4 text-end">
                        <button type="button" class="btn btn-info" onclick="window.location.href='AUD_catalogo_vehiculos.php'">Regresar</button>
                        <button type="button" class="btn btn-secondary" onclick="this.form.reset()">Limpiar</button>
                        <button type="submit" class="btn btn-success px-5">Guardar Vehículo</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    
    // 1. CARGA DE LISTAS (Selects)
    // ---------------------------------------------------------
    
    // Cargar sucursales
    fetch('AUD_controller/get_sucursales.php')
        .then(response => response.json())
        .then(data => {
            const sucursalSelect = document.querySelector('select[name="sucursal_id"]');
            if (sucursalSelect) {
                data.forEach(sucursal => {
                    const option = document.createElement('option');
                    option.value = sucursal.id;
                    option.textContent = sucursal.sucursal;
                    sucursalSelect.appendChild(option);
                });
            }
        })
        .catch(error => console.error('Error al cargar sucursales:', error));

    // Cargar usuarios (Responsable y Gerente)
    fetch('AUD_controller/get_usuarios_activos.php')
        .then(response => response.json())
        .then(data => {
            const responsableSelect = document.querySelector('select[name="responsable_id"]');
            const gerenteSelect = document.querySelector('select[name="gerente_reportar_id"]');
            
            data.forEach(usuario => {
                const optionStr = `<option value="${usuario.id}">${usuario.nombre}</option>`;
                if (responsableSelect) responsableSelect.innerHTML += optionStr;
                if (gerenteSelect) gerenteSelect.innerHTML += optionStr;
            });
        })
        .catch(error => console.error('Error al cargar usuarios:', error));


    // 2. VALIDACIÓN DE SERIE DUPLICADA
    // ---------------------------------------------------------
    const inputSerie = document.getElementById('no_serie');
    if (inputSerie) {
        inputSerie.addEventListener('blur', async function() {
            const serie = this.value.trim();
            if (serie === '') return;

            try {
                const response = await fetch(`AUD_controller/verificar_serie.php?no_serie=${encodeURIComponent(serie)}`);
                const data = await response.json();
                const btnGuardar = document.getElementById('btnGuardarVehiculo');

                if (data.exists) {
                    this.classList.add('is-invalid');
                    Swal.fire({
                        title: 'Serie Duplicada',
                        text: `El número de serie ${serie} ya está registrado.`,
                        icon: 'warning',
                        toast: true,
                        position: 'top-end',
                        showConfirmButton: false,
                        timer: 4000
                    });
                    if (btnGuardar) btnGuardar.disabled = true;
                } else {
                    this.classList.remove('is-invalid');
                    this.classList.add('is-valid');
                    if (btnGuardar) btnGuardar.disabled = false;
                }
            } catch (error) {
                console.error("Error validando serie:", error);
            }
        });
    }


    // 3. ENVÍO DEL FORMULARIO (AJAX)
    // ---------------------------------------------------------
    const formVehiculo = document.getElementById('formVehiculo');
    if (formVehiculo) {
        formVehiculo.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            Swal.fire({
                title: 'Procesando...',
                text: 'Guardando información de la unidad',
                allowOutsideClick: false,
                didOpen: () => { Swal.showLoading(); }
            });

            const formData = new FormData(this);

            try {
                const response = await fetch('AUD_controller/VehiculoController.php?action=store', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();

                if (result.status === 'success') {
                    Swal.fire('¡Éxito!', result.message, 'success').then(() => {
                        window.location.href = 'AUD_catalogo_vehiculos.php';
                    });
                } else {
                    Swal.fire('Error', result.message, 'error');
                }
            } catch (error) {
                Swal.fire('Error de Red', 'No se pudo conectar con el servidor', 'error');
            }
        });
    }
});
</script>

<?php 
include("src/templates/adminfooter.php");
?>