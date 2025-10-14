<?php
require("config/db.php");
include("src/templates/adminheader.php");

// 1. --- VALIDACIÓN DE SESIÓN ---
if (!isset($_SESSION['loggedin']) || !$_SESSION['loggedin']) {
    header("Location: index.php");
    exit();
}

// 2. --- OBTENER TODAS LAS SUCURSALES PARA LA TABLA ---
$result_sucursales = $conn->query("SELECT * FROM sucursales ORDER BY sucursal ASC");
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
            <h2 class="mb-0 h5" id="form-title">Agregar Nueva Sucursal</h2>
        </div>
        <div class="card-body">
            <form id="form-sucursal">
                <input type="hidden" name="id" id="sucursal-id" value="0">
                <div class="mb-3">
                    <label for="sucursal-nombre" class="form-label">Nombre de la Sucursal:*</label>
                    <input type="text" id="sucursal-nombre" name="sucursal" class="form-control" 
                           placeholder="Ej. Sucursal Centro" required>
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
            <h2 class="mb-0 h5">Lista de Sucursales</h2>
        </div>
        <div class="card-body">
            <table class="table table-striped table-hover" id="tabla-sucursales">
                <thead class="table-dark">
                    <tr>
                        <th>Sucursal</th>
                        <th class="text-center">Acciones</th>
                    </tr>
                </thead>
                <tbody id="tabla-sucursales-body">
                    <?php while ($row = $result_sucursales->fetch_assoc()): ?>
                        <tr>
                            <td><?= htmlspecialchars($row['sucursal']) ?></td>
                            <td class="text-center">
                                <div class="d-flex justify-content-center gap-2">
                                    <button class="btn btn-sm btn-warning btn-editar" 
                                            data-id="<?= $row['id'] ?>" 
                                            data-sucursal="<?= htmlspecialchars($row['sucursal']) ?>">
                                        Editar
                                    </button>
                                    <button class="btn btn-sm btn-danger btn-eliminar" 
                                            data-id="<?= $row['id'] ?>"
                                            data-sucursal="<?= htmlspecialchars($row['sucursal']) ?>">
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
    $('#tabla-sucursales').DataTable({
        "language": { "url": "//cdn.datatables.net/plug-ins/1.10.16/i18n/Spanish.json" },
        "pageLength": 10
    });

    const form = $('#form-sucursal');
    const formTitle = $('#form-title');
    const sucursalIdInput = $('#sucursal-id');
    const sucursalNombreInput = $('#sucursal-nombre');
    const btnGuardar = $('#btn-guardar');
    const btnCancelar = $('#btn-cancelar');

    // --- 2. LÓGICA PARA AGREGAR Y EDITAR (SUBMIT DEL FORMULARIO) ---
    form.on('submit', function(e) {
        e.preventDefault();

        const id = sucursalIdInput.val();
        const sucursal = sucursalNombreInput.val();
        const accion = (id > 0) ? 'editar' : 'agregar';

        $.ajax({
            url: 'UT_controller/sucursales_ajax.php',
            type: 'POST',
            dataType: 'json',
            data: { id: id, sucursal: sucursal, accion: accion },
            success: function(response) {
                if (response.success) {
                    Swal.fire({
                        icon: 'success',
                        title: '¡Éxito!',
                        text: response.message,
                        timer: 2000,
                        showConfirmButton: false
                    }).then(() => location.reload());
                } else {
                    Swal.fire('Error', response.message, 'error');
                }
            },
            error: function() {
                Swal.fire('Error', 'No se pudo comunicar con el servidor.', 'error');
            }
        });
    });

    // --- 3. LÓGICA PARA EL BOTÓN EDITAR ---
    $('#tabla-sucursales-body').on('click', '.btn-editar', function() {
        const id = $(this).data('id');
        const sucursal = $(this).data('sucursal');

        formTitle.text('Editar Sucursal');
        sucursalIdInput.val(id);
        sucursalNombreInput.val(sucursal);
        btnGuardar.text('Actualizar');
        btnCancelar.show();
        
        $('html, body').animate({ scrollTop: 0 }, 'slow');
    });

    // --- 4. LÓGICA PARA EL BOTÓN ELIMINAR ---
    $('#tabla-sucursales-body').on('click', '.btn-eliminar', function() {
        const id = $(this).data('id');
        const sucursal = $(this).data('sucursal');

        Swal.fire({
            title: '¿Estás seguro?',
            text: `No podrás revertir la eliminación de "${sucursal}".`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Sí, ¡eliminar!',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: 'UT_controller/sucursales_ajax.php',
                    type: 'POST',
                    dataType: 'json',
                    data: { id: id, accion: 'eliminar' },
                    success: function(response) {
                        if (response.success) {
                            Swal.fire('¡Eliminado!', response.message, 'success').then(() => location.reload());
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
        formTitle.text('Agregar Nueva Sucursal');
        sucursalIdInput.val(0);
        form[0].reset();
        btnGuardar.text('Guardar');
        btnCancelar.hide();
    });
});
</script>

<?php
include("src/templates/adminfooter.php");
?>