<?php
require("config/db.php");
include("src/templates/adminheader.php");

// 1. --- VALIDACIÓN DE SESIÓN ---
if (!isset($_SESSION['loggedin']) || !$_SESSION['loggedin']) {
    header("Location: index.php");
    exit();
}

// 2. --- OBTENER TODOS LOS PUESTOS PARA LA TABLA ---
$result_puestos = $conn->query("SELECT * FROM puestos ORDER BY puesto ASC");
?>

<style>
    /* Estilos para centrar el contenido y darle un ancho máximo */
    .main-container { max-width: 800px; margin: 40px auto; }
    /* Cursor de 'mano' para los botones de acción */
    .btn-editar, .btn-eliminar { cursor: pointer; }
</style>

<div class="main-container">

    <div class="card shadow-sm mb-4">
        <div class="card-header bg-dark text-white">
            <h2 class="mb-0 h5" id="form-title">Agregar Nuevo Puesto</h2>
        </div>
        <div class="card-body">
            <form id="form-puesto">
                <input type="hidden" name="id" id="puesto-id" value="0">
                
                <div class="mb-3">
                    <label for="puesto-nombre" class="form-label">Nombre del Puesto:*</label>
                    <input type="text" id="puesto-nombre" name="puesto" class="form-control" 
                           placeholder="Ej. Gerente de Ventas" required>
                </div>
                
                <div class="text-end">
                    <button type="button" class="btn btn-secondary" id="btn-cancelar" style="display: none;">Cancelar Edición</button>
                    <button type="submit" id="btn-guardar" class="btn btn-primary">Guardar</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header bg-dark text-white">
            <h2 class="mb-0 h5">Lista de Puestos</h2>
        </div>
        <div class="card-body">
            <table class="table table-striped table-hover" id="tabla-puestos">
                <thead class="table-dark">
                    <tr>
                        <th>Puesto</th>
                        <th class="text-center">Acciones</th>
                    </tr>
                </thead>
                <tbody id="tabla-puestos-body">
                    <?php while ($row = $result_puestos->fetch_assoc()): ?>
                        <tr>
                            <td><?= htmlspecialchars($row['puesto']) ?></td>
                            <td class="text-center">
                                <div class="d-flex justify-content-center gap-2">
                                    <button class="btn btn-sm btn-warning btn-editar" 
                                            data-id="<?= $row['id'] ?>" 
                                            data-puesto="<?= htmlspecialchars($row['puesto']) ?>">
                                        Editar
                                    </button>
                                    <button class="btn btn-sm btn-danger btn-eliminar" 
                                            data-id="<?= $row['id'] ?>"
                                            data-puesto="<?= htmlspecialchars($row['puesto']) ?>">
                                        Eliminar
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
$(document).ready(function() {
    // --- 1. INICIALIZACIÓN DE DATATABLE ---
    $('#tabla-puestos').DataTable({
        "language": { "url": "//cdn.datatables.net/plug-ins/1.10.16/i18n/Spanish.json" },
        "pageLength": 10
    });

    const form = $('#form-puesto');
    const formTitle = $('#form-title');
    const puestoIdInput = $('#puesto-id');
    const puestoNombreInput = $('#puesto-nombre');
    const btnGuardar = $('#btn-guardar');
    const btnCancelar = $('#btn-cancelar');

    // --- 2. LÓGICA PARA AGREGAR Y EDITAR (SUBMIT DEL FORMULARIO) ---
    form.on('submit', function(e) {
        e.preventDefault(); // Evitar que la página se recargue

        const id = puestoIdInput.val();
        const puesto = puestoNombreInput.val();
        const accion = (id > 0) ? 'editar' : 'agregar'; // Determinar si es una edición o un nuevo registro

        $.ajax({
            url: 'UT_controller/puestos_ajax.php',
            type: 'POST',
            dataType: 'json',
            data: {
                id: id,
                puesto: puesto,
                accion: accion
            },
            success: function(response) {
                if (response.success) {
                    Swal.fire({
                        icon: 'success',
                        title: '¡Éxito!',
                        text: response.message,
                        timer: 2000,
                        showConfirmButton: false
                    }).then(() => {
                        location.reload(); // Recargar la página para ver los cambios
                    });
                } else {
                    Swal.fire('Error', response.message, 'error');
                }
            },
            error: function() {
                Swal.fire('Error', 'No se pudo comunicar con el servidor.', 'error');
            }
        });
    });

    // --- 3. LÓGICA PARA EL BOTÓN EDITAR (POBLAR EL FORMULARIO) ---
    $('#tabla-puestos-body').on('click', '.btn-editar', function() {
        const id = $(this).data('id');
        const puesto = $(this).data('puesto');

        // Llenar el formulario con los datos
        formTitle.text('Editar Puesto');
        puestoIdInput.val(id);
        puestoNombreInput.val(puesto);
        btnGuardar.text('Actualizar');
        btnCancelar.show();
        
        // Mover la vista al formulario
        $('html, body').animate({ scrollTop: 0 }, 'slow');
    });

    // --- 4. LÓGICA PARA EL BOTÓN ELIMINAR ---
    $('#tabla-puestos-body').on('click', '.btn-eliminar', function() {
        const id = $(this).data('id');
        const puesto = $(this).data('puesto');

        Swal.fire({
            title: '¿Estás seguro?',
            text: `No podrás revertir la eliminación de "${puesto}".`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Sí, ¡eliminar!',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: 'UT_controller/puestos_ajax.php',
                    type: 'POST',
                    dataType: 'json',
                    data: {
                        id: id,
                        accion: 'eliminar'
                    },
                    success: function(response) {
                        if (response.success) {
                            Swal.fire('¡Eliminado!', response.message, 'success').then(() => {
                                location.reload();
                            });
                        } else {
                            Swal.fire('Error', response.message, 'error');
                        }
                    },
                    error: function() {
                        Swal.fire('Error', 'No se pudo comunicar con el servidor.', 'error');
                    }
                });
            }
        });
    });

    // --- 5. LÓGICA PARA EL BOTÓN CANCELAR EDICIÓN ---
    btnCancelar.on('click', function() {
        formTitle.text('Agregar Nuevo Puesto');
        puestoIdInput.val(0);
        form[0].reset(); // Limpia el formulario
        btnGuardar.text('Guardar');
        btnCancelar.hide();
    });
});
</script>

<?php
include("src/templates/adminfooter.php");
?>