<?php
require("config/db.php");
include("src/templates/adminheader.php");

// --- 1. VALIDACIÓN DE SESIÓN ---
if (!isset($_SESSION['loggedin']) || !$_SESSION['loggedin']) {
    header("Location: index.php");
    exit();
}

// --- 2. INICIALIZACIÓN DE VARIABLES ---
$message = '';
$id_programa = isset($_GET['id']) ? intval($_GET['id']) : 0;
$programa_actual = [
    'nombre_programa' => '',
    'descripcion' => '',
    'categoria' => ''
];

// --- 3. MANEJO DEL FORMULARIO (INSERT O UPDATE) ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $nombre = $_POST['nombre_programa'];
    $descripcion = $_POST['descripcion'];
    $categoria = $_POST['categoria'];
    $id_programa_post = isset($_POST['id_programa']) ? intval($_POST['id_programa']) : 0;

    if ($id_programa_post > 0) { // Actualizar
        $sql = "UPDATE programas SET nombre_programa = ?, descripcion = ?, categoria = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('sssi', $nombre, $descripcion, $categoria, $id_programa_post);
        if ($stmt->execute()) {
            $message = "Registro actualizado correctamente.";
        } else {
            $message = "Error al actualizar: " . $stmt->error;
        }
    } else { // Insertar
        $sql = "INSERT INTO programas (nombre_programa, descripcion, categoria, estatus) VALUES (?, ?, ?, 1)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('sss', $nombre, $descripcion, $categoria);
        if ($stmt->execute()) {
            $message = "Nuevo registro agregado correctamente.";
        } else {
            $message = "Error al agregar: " . $stmt->error;
        }
    }
    $stmt->close();
    // Para refrescar los datos después de guardar
    echo "<script>setTimeout(() => window.location.href = 'UT_programas.php', 2000);</script>";
}

// --- 4. OBTENER DATOS SI ESTAMOS EN MODO EDICIÓN ---
if ($id_programa > 0) {
    $sql_edit = "SELECT * FROM programas WHERE id = ?";
    $stmt_edit = $conn->prepare($sql_edit);
    $stmt_edit->bind_param("i", $id_programa);
    $stmt_edit->execute();
    $result = $stmt_edit->get_result();
    if ($result->num_rows > 0) {
        $programa_actual = $result->fetch_assoc();
    }
    $stmt_edit->close();
}

// --- 5. OBTENER TODOS LOS PROGRAMAS PARA LA TABLA ---
$queryProgramas = $conn->query("SELECT * FROM programas ORDER BY id DESC");
?>

<div class="container mt-4">

    <div class="card shadow-sm mb-4">
        <div class="card-header bg-dark text-white">
            <h2 class="mb-0"><?= $id_programa ? 'Editar Programa' : 'Agregar Nuevo Programa' ?></h2>
        </div>
        <div class="card-body">
            <?php if ($message): ?>
                <div class="alert alert-info"><?= htmlspecialchars($message) ?></div>
            <?php endif; ?>

            <form action="UT_programas.php" method="POST">
                <?php if ($id_programa): ?>
                    <input type="hidden" name="id_programa" value="<?= $id_programa ?>">
                <?php endif; ?>
                
                <div class="row g-3">
                    <div class="col-md-4">
                        <label for="nombre_programa" class="form-label">Nombre del Programa:*</label>
                        <input type="text" id="nombre_programa" name="nombre_programa" class="form-control" placeholder="Ej. UT_programa.php" value="<?= htmlspecialchars($programa_actual['nombre_programa']) ?>" required>
                    </div>
                    <div class="col-md-4">
                        <label for="descripcion" class="form-label">Descripción:*</label>
                        <input type="text" id="descripcion" name="descripcion" class="form-control" placeholder="Ej. Gestión de Usuarios" value="<?= htmlspecialchars($programa_actual['descripcion']) ?>" required>
                    </div>
                    <div class="col-md-4">
                        <label for="categoria" class="form-label">Categoría:*</label>
                        <input type="text" id="categoria" name="categoria" class="form-control" placeholder="Ej. Utilidades" value="<?= htmlspecialchars($programa_actual['categoria']) ?>" required>
                    </div>
                </div>
                
                <div class=" text-end mt-3 ">
                    <a href="UT_programas.php" class="btn btn-secondary">Cancelar</a>
                    <button type="submit" class="btn btn-primary">
                        <?= $id_programa ? "Actualizar Programa" : "Guardar Programa" ?>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header bg-dark text-white">
            <h2 class="mb-0">Todos los Programas</h2>
        </div>
        <div class="card-body">
            <table class="table table-bordered table-striped" id="programas">
                <thead>
                    <tr class="text-center">
                        <th>Id</th>
                        <th>Nombre del programa</th>
                        <th>Descripción</th>
                        <th>Categoría</th>
                        <th>Acción</th>
                        <th>Activo</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($programa = $queryProgramas->fetch_assoc()): ?>
                        <tr>
                            <td class="text-center"><?= htmlspecialchars($programa['id']) ?></td>
                            <td><?= htmlspecialchars($programa['nombre_programa']) ?></td>
                            <td><?= htmlspecialchars($programa['descripcion']) ?></td>
                            <td><?= htmlspecialchars($programa['categoria']) ?></td>
                            <td class="text-center">
                                <a href="?id=<?= urlencode($programa['id']) ?>" class="btn btn-sm btn-warning">Editar</a>
                            </td>
                            <td class="text-center">
                                <div class="form-check form-switch d-flex justify-content-center">
                                    <input type="checkbox" class="form-check-input programa-checkbox" 
                                           id="programa_<?= $programa['id'] ?>" 
                                           data-id-programa="<?= $programa['id'] ?>" 
                                           <?= ($programa['estatus'] == 1) ? 'checked' : '' ?>>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Inicialización de DataTable
    $('#programas').DataTable({
        "language": { "url": "//cdn.datatables.net/plug-ins/1.10.16/i18n/Spanish.json" },
        "pageLength": 5,
        "lengthMenu": [5, 10, 20],
        "order": [[0, "desc"]],
        "columnDefs": [
            { "orderable": false, "targets": [4, 5] }
        ]
    });

    // AJAX para activar/desactivar programas
    $(".programa-checkbox").on("change", function () {
        let idPrograma = $(this).data("id-programa");
        let estatus = $(this).is(":checked") ? 1 : 0;

        $.ajax({
            url: "UT_controller/act_desact_programa.php",
            type: "POST",
            data: { id_programa: idPrograma, estatus: estatus },
            success: function (response) {
                console.log("Estatus actualizado:", response);
            },
            error: function () {
                alert("Error al cambiar el estatus.");
            }
        });
    });
});
</script>

<?php
include("src/templates/adminfooter.php");
?>