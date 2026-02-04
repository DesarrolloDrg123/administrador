<?php
require("config/db.php");
include("src/templates/adminheader.php");

if (!isset($_SESSION['loggedin']) || !$_SESSION['loggedin']) {
    header("Location: index.php");
    exit();
}

$result_puestos = $conn->query("SELECT * FROM puestos ORDER BY puesto ASC");
?>

<style>
    .main-container { max-width: 900px; margin: 40px auto; }
    .btn-editar, .btn-eliminar { cursor: pointer; }
    .doc-link { text-decoration: none; color: #0d6efd; font-weight: bold; }
</style>

<div class="main-container">
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-dark text-white">
            <h2 class="mb-0 h5" id="form-title">Agregar Nuevo Puesto</h2>
        </div>
        <div class="card-body">
            <form id="form-puesto" enctype="multipart/form-data">
                <input type="hidden" name="id" id="puesto-id" value="0">
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="puesto-nombre" class="form-label">Nombre del Puesto:*</label>
                        <input type="text" id="puesto-nombre" name="puesto" class="form-control" placeholder="Ej. Gerente" required>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label for="puesto-documento" class="form-label">Documento (PDF):</label>
                        <input type="file" id="puesto-documento" name="documento" class="form-control" accept=".pdf">
                        <small class="text-info d-block mt-1" id="doc-actual-info"></small>
                    </div>
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
                        <th class="text-center">Documento</th>
                        <th class="text-center">Acciones</th>
                    </tr>
                </thead>
                <tbody id="tabla-puestos-body">
                    <?php while ($row = $result_puestos->fetch_assoc()): ?>
                        <tr>
                            <td><?= htmlspecialchars($row['puesto']) ?></td>
                            <td class="text-center">
                                <?php if (!empty($row['documento'])): ?>
                                    <a href="uploads/documentos_puestos/<?= $row['documento'] ?>" target="_blank" class="btn btn-sm btn-outline-info">
                                        Ver PDF
                                    </a>
                                <?php else: ?>
                                    <span class="text-muted small">Sin archivo</span>
                                <?php endif; ?> </td>
                            <td class="text-center">
                                <div class="d-flex justify-content-center gap-2">
                                    <button class="btn btn-sm btn-warning btn-editar" 
                                            data-id="<?= $row['id'] ?>" 
                                            data-puesto="<?= htmlspecialchars($row['puesto']) ?>"
                                            data-doc="<?= htmlspecialchars($row['documento']) ?>">
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
    // Inicializar DataTable solo si no está inicializado
    if ( ! $.fn.DataTable.isDataTable( '#tabla-puestos' ) ) {
        $('#tabla-puestos').DataTable({
            "language": { "url": "//cdn.datatables.net/plug-ins/1.10.16/i18n/Spanish.json" }
        });
    }

    const form = $('#form-puesto');
    const formTitle = $('#form-title');
    const puestoIdInput = $('#puesto-id');
    const puestoNombreInput = $('#puesto-nombre');
    const btnGuardar = $('#btn-guardar');
    const btnCancelar = $('#btn-cancelar');
    const docInfo = $('#doc-actual-info');

    form.on('submit', function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        formData.append('accion', (puestoIdInput.val() > 0) ? 'editar' : 'agregar');

        $.ajax({
            url: 'UT_controller/puestos_ajax.php',
            type: 'POST',
            data: formData,
            processData: false, 
            contentType: false, 
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    Swal.fire('¡Éxito!', response.message, 'success').then(() => { location.reload(); });
                } else {
                    Swal.fire('Error', response.message, 'error');
                }
            },
            error: function() {
                Swal.fire('Error', 'Error en el servidor', 'error');
            }
        });
    });

    $('#tabla-puestos-body').on('click', '.btn-editar', function() {
        const id = $(this).data('id');
        const puesto = $(this).data('puesto');
        const doc = $(this).data('doc');

        formTitle.text('Editar Puesto');
        puestoIdInput.val(id);
        puestoNombreInput.val(puesto);
        
        docInfo.text(doc ? 'Archivo actual: ' + doc : 'Sin archivo previo');

        btnGuardar.text('Actualizar');
        btnCancelar.show();
        $('html, body').animate({ scrollTop: 0 }, 'slow');
    });

    $('#tabla-puestos-body').on('click', '.btn-eliminar', function() {
        const id = $(this).data('id');
        const puesto = $(this).data('puesto');

        Swal.fire({
            title: '¿Estás seguro?',
            text: `Se eliminará "${puesto}" y su documento.`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Sí, eliminar'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: 'UT_controller/puestos_ajax.php',
                    type: 'POST',
                    dataType: 'json',
                    data: { id: id, accion: 'eliminar' },
                    success: function(response) {
                        if (response.success) {
                            location.reload();
                        } else {
                            Swal.fire('Error', response.message, 'error');
                        }
                    }
                });
            }
        });
    });

    btnCancelar.on('click', function() {
        formTitle.text('Agregar Nuevo Puesto');
        puestoIdInput.val(0);
        form[0].reset();
        docInfo.text('');
        btnGuardar.text('Guardar');
        btnCancelar.hide();
    });
});
</script>