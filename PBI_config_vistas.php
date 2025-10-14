<?php
require("config/db.php");
include("src/templates/adminheader.php");

if (!isset($_SESSION['loggedin']) || !$_SESSION['loggedin']) {
    header("Location: index.php");
    exit();
}

// Obtener todos los reportes para la tabla y el dropdown
$result_reports = $conn->query("SELECT * FROM powerbi_reports ORDER BY report_name ASC");
?>

<style>
    .main-container { max-width: 900px; margin: 40px auto; }
</style>

<div class="main-container">
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-dark text-white">
            <h2 class="mb-0 h5" id="form-title">Agregar Nuevo Reporte de Power BI</h2>
        </div>
        <div class="card-body">
            <form id="form-reporte">
                <input type="hidden" name="id" id="reporte-id" value="0">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label for="reporte-nombre" class="form-label">Nombre del Reporte:*</label>
                        <input type="text" id="reporte-nombre" name="report_name" class="form-control" required>
                    </div>
                    <div class="col-md-5">
                        <label for="reporte-link" class="form-label">Código para Insertar (iframe):</label>
                        <textarea id="reporte-link" name="report_link" class="form-control" rows="2" placeholder="Pega el código iframe aquí..."></textarea>
                        <div class="form-text">Deja en blanco si es solo un menú contenedor.</div>
                    </div>
                    <div class="col-md-3">
                        <label for="parent-id" class="form-label">Reporte Principal:</label>
                        <select id="parent-id" name="parent_id" class="form-select">
                            <option value="0">-- Ninguno (Nivel Superior) --</option>
                            <?php
                                mysqli_data_seek($result_reports, 0);
                                while($row = $result_reports->fetch_assoc()) {
                                    echo '<option value="' . $row['id'] . '">' . htmlspecialchars($row['report_name']) . '</option>';
                                }
                            ?>
                        </select>
                    </div>
                </div>
                <div class="text-end mt-3">
                    <button type="button" class="btn btn-secondary" id="btn-cancelar" style="display: none;">Cancelar</button>
                    <button type="submit" id="btn-guardar" class="btn btn-primary">Guardar Reporte</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header"><h2 class="mb-0 h5">Reportes Configurados</h2></div>
        <div class="card-body">
            <table class="table table-striped table-hover" id="tabla-reportes">
                <thead class="table-dark">
                    <tr>
                        <th>Nombre del Reporte</th>
                        <th class="text-center">Acciones</th>
                    </tr>
                </thead>
                <tbody id="tabla-reportes-body">
                    <?php 
                        mysqli_data_seek($result_reports, 0);
                        while ($row = $result_reports->fetch_assoc()): 
                    ?>
                        <tr>
                            <td><?= htmlspecialchars($row['report_name']) ?></td>
                            <td class="text-center">
                                <div class="d-flex justify-content-center gap-2">
                                    <button class="btn btn-sm btn-warning btn-editar" 
                                            data-id="<?= $row['id'] ?>" 
                                            data-nombre="<?= htmlspecialchars($row['report_name']) ?>"
                                            data-link="<?= htmlspecialchars($row['report_link']) ?>"
                                            data-parent-id="<?= $row['parent_id'] ?? 0 ?>"> Editar
                                    </button>
                                    <button class="btn btn-sm btn-danger btn-eliminar" 
                                            data-id="<?= $row['id'] ?>"
                                            data-nombre="<?= htmlspecialchars($row['report_name']) ?>">
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
    $('#tabla-reportes').DataTable({ "language": { "url": "//cdn.datatables.net/plug-ins/1.10.16/i18n/Spanish.json" } });

    const form = $('#form-reporte');
    const formTitle = $('#form-title');
    const reporteId = $('#reporte-id');
    const reporteNombre = $('#reporte-nombre');
    const reporteLink = $('#reporte-link');
    const parentIdSelect = $('#parent-id');
    const btnGuardar = $('#btn-guardar');
    const btnCancelar = $('#btn-cancelar');

    // --- CÓDIGO CORREGIDO PARA GUARDAR/EDITAR ---
    form.on('submit', function(e) {
        e.preventDefault();
        const accion = (reporteId.val() > 0) ? 'editar' : 'agregar';
        let formData = $(this).serialize() + '&accion=' + accion;

        $.ajax({
            url: 'PBI_controller/config_ajax.php',
            type: 'POST',
            dataType: 'json',
            data: formData,
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

    // --- LÓGICA PARA EL BOTÓN EDITAR ---
    $('#tabla-reportes-body').on('click', '.btn-editar', function() {
        reporteId.val($(this).data('id'));
        reporteNombre.val($(this).data('nombre'));
        reporteLink.val($(this).data('link'));
        parentIdSelect.val($(this).data('parent-id'));
        
        formTitle.text('Editar Reporte');
        btnGuardar.text('Actualizar');
        btnCancelar.show();
        $('html, body').animate({ scrollTop: 0 }, 'slow');
    });

    // --- LÓGICA PARA EL BOTÓN ELIMINAR ---
    $('#tabla-reportes-body').on('click', '.btn-eliminar', function() {
        const id = $(this).data('id');
        const nombre = $(this).data('nombre');

        Swal.fire({
            title: '¿Estás seguro?',
            text: `Se eliminará el reporte "${nombre}".`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Sí, ¡eliminar!',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: 'PBI_controller/config_ajax.php',
                    type: 'POST',
                    dataType: 'json',
                    data: { id: id, accion: 'eliminar' },
                    success: function(response) {
                        if (response.success) {
                            Swal.fire('¡Eliminado!', response.message, 'success').then(() => location.reload());
                        } else {
                            Swal.fire('Error', response.message, 'error');
                        }
                    }
                });
            }
        });
    });

    // --- LÓGICA PARA EL BOTÓN CANCELAR ---
    btnCancelar.on('click', function() {
        form[0].reset();
        reporteId.val(0);
        parentIdSelect.val(0);
        formTitle.text('Agregar Nuevo Reporte de Power BI');
        btnGuardar.text('Guardar Reporte');
        btnCancelar.hide();
    });
});
</script>

<?php include("src/templates/adminfooter.php"); ?>