<?php
session_start();
if (!isset($_SESSION['loggedin']) || !$_SESSION['loggedin']) {
    header("Location: index.php");
    exit();
}

$usuario_id = $_SESSION['usuario_id'];
include("src/templates/adminheader.php");
require("config/db.php");

$folio_formateado = '';

try {
    // Prepara y ejecuta la consulta para obtener el último folio.
    $stmt = $conn->prepare("SELECT folio FROM control_folios_aud WHERE id = 1");
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    // Calcula el siguiente folio y lo formatea a 9 dígitos.
    $siguiente_folio = ($row ? $row['folio'] : 0) + 1;
    $folio_formateado = sprintf("%09d", $siguiente_folio);
} catch (Exception $e) {
    // En caso de error, muestra un mensaje.
    $folio_formateado = 'Error';
}
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
                        <label class="form-label">No. de Serie</label>
                        <input type="text" name="no_serie" class="form-control" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Fecha de Alta</label>
                        <input type="date" name="fecha_alta" class="form-control" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Marca</label>
                        <input type="text" name="marca" class="form-control" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Modelo</label>
                        <input type="text" name="modelo" class="form-control" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Año</label>
                        <input type="number" name="anio" class="form-control" min="1900" max="2099" required>
                    </div>
                    
                    <hr class="my-3">
                    
                    <div class="col-md-6">
                        <label class="form-label">Sucursal</label>
                        <select name="sucursal_id" class="form-select" required>
                            <option value="">Seleccione sucursal...</option>
                            </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Responsable</label>
                        <select name="responsable_id" class="form-select" required>
                            <option value="">Seleccione usuario activo...</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Placas</label>
                        <input type="text" name="placas" class="form-control">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Vigencia Póliza</label>
                        <input type="date" name="vigencia_poliza" class="form-control">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Aseguradora</label>
                        <input type="text" name="aseguradora" class="form-control">
                    </div>

                    <div class="col-12 mt-4 text-end">
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
    // Cargar sucursales
    fetch('AUD_controller/get_sucursales.php')
        .then(response => response.json())
        .then(data => {
            const sucursalSelect = document.querySelector('select[name="sucursal_id"]');
            data.forEach(sucursal => {
                const option = document.createElement('option');
                option.value = sucursal.id;
                option.textContent = sucursal.sucursal;
                sucursalSelect.appendChild(option);
            });
        })
        .catch(error => console.error('Error al cargar sucursales:', error));

    // Cargar usuarios activos
    fetch('AUD_controller/get_usuarios_activos.php')
        .then(response => response.json())
        .then(data => {
            const responsableSelect = document.querySelector('select[name="responsable_id"]');
            data.forEach(usuario => {
                const option = document.createElement('option');
                option.value = usuario.id;
                option.textContent = `${usuario.nombre}`;
                responsableSelect.appendChild(option);
            });
        })
        .catch(error => console.error('Error al cargar usuarios activos:', error));
});

document.getElementById('formVehiculo').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    // Animación de carga inicial
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
            Swal.fire('¡Éxito!', result.message, 'success');
            this.reset(); // Limpia el formulario
        } else {
            Swal.fire('Error', result.message, 'error');
        }
    } catch (error) {
        Swal.fire('Error de Red', 'No se pudo conectar con el servidor', 'error');
    }
});

</script>