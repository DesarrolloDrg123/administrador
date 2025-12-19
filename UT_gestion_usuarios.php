<?php
require("config/db.php");
include("src/templates/adminheader.php");

$alertMessage = '';
$alertType = '';

// --- VALIDACIÓN DE SESIÓN ---
if (!isset($_SESSION['loggedin']) || !$_SESSION['loggedin']) {
    header("Location: index.php");
    exit();
}

// --- LÓGICA DE ELIMINACIÓN DE USUARIOS ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['delete'])) {
    // Tu lógica de eliminación aquí... (la he dejado como estaba ya que funciona)
    $userid = isset($_POST['id']) ? intval($_POST['id']) : 0;
    if ($userid > 0) {
        $sql_check = "SELECT (SELECT COUNT(*) FROM transferencias WHERE usuario_id = ?) + (SELECT COUNT(*) FROM solicitudes_vacaciones WHERE usuario_id = ?) AS registros;";
        $stmt_check = $conn->prepare($sql_check);
        $stmt_check->bind_param("ii", $userid, $userid);
        $stmt_check->execute();
        $registros = $stmt_check->get_result()->fetch_assoc()['registros'];
        $stmt_check->close();

        if ($registros > 0) {
            $alertMessage = "El usuario no puede ser eliminado porque está vinculado a otros registros.";
            $alertType = "warning";
        } else {
            $delete_sql = "DELETE FROM usuarios WHERE id = ?";
            $delete_stmt = $conn->prepare($delete_sql);
            $delete_stmt->bind_param("i", $userid);
            if ($delete_stmt->execute()) {
                $alertMessage = "Usuario eliminado correctamente.";
                $alertType = "success";
            } else {
                $alertMessage = "Error al eliminar el usuario.";
                $alertType = "danger";
            }
            $delete_stmt->close();
        }
    }
}

// --- CONSULTAS PARA OBTENER DATOS ---
// Obtener roles y departamentos para los filtros
$result_roles = $conn->query("SELECT DISTINCT rol FROM usuarios WHERE rol IS NOT NULL AND rol != '' ORDER BY rol ASC");
$result_departamentos = $conn->query("SELECT DISTINCT departamento FROM usuarios WHERE departamento IS NOT NULL AND departamento != '' ORDER BY departamento ASC");

// Consulta principal para obtener los usuarios
$sql = "SELECT u.id AS user_id, u.nombre, u.email, u.rol, u.departamento, u.puesto, s.estado 
        FROM usuarios u
        LEFT JOIN status s ON u.estatus = s.id
        ORDER BY u.nombre ASC";
$result = $conn->query($sql);
?>

<style>
    /* Estilo para las insignias de estatus */
    .badge {
        font-size: 0.9em;
        padding: 0.5em 0.75em;
    }
    .container {
        max-width: 1200px;
    }
</style>

<div class="container mt-4 mb-5">
    <div class="card shadow-sm">
        <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
            <h2 class="mb-0 h5">Gestión de Usuarios</h2>
            <a href="UT_add_user.php" class="btn btn-success btn-sm"><i class="fas fa-user-plus me-2"></i>Agregar Usuario</a>
        </div>

        <div class="card-body">
            <div class="row mb-3">
                <div class="col-md-4">
                    <label for="filtroRol" class="form-label">Filtrar por Rol:</label>
                    <select id="filtroRol" class="form-select">
                        <option value="">Todos</option>
                        <?php while ($row = $result_roles->fetch_assoc()): ?>
                            <option value="<?= htmlspecialchars($row['rol']) ?>"><?= htmlspecialchars($row['rol']) ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label for="filtroDepartamento" class="form-label">Filtrar por Departamento:</label>
                    <select id="filtroDepartamento" class="form-select">
                        <option value="">Todos</option>
                        <?php while ($row = $result_departamentos->fetch_assoc()): ?>
                            <option value="<?= htmlspecialchars($row['departamento']) ?>"><?= htmlspecialchars($row['departamento']) ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
            </div>

            <hr>

            <?php if ($alertMessage): ?>
                <div class="alert alert-<?= $alertType ?> alert-dismissible fade show" role="alert">
                    <?= htmlspecialchars($alertMessage) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <div class="table-responsive">
                <table class="table table-striped table-hover" id="usuarios">
                    <thead class="table-dark">
                        <tr>
                            <th>Nombre</th>
                            <th>Email</th>
                            <th>Rol</th>
                            <th>Departamento</th>
                            <th>Puesto</th>
                            <th class="text-center">Estatus</th>
                            <th class="text-center">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result && $result->num_rows > 0): ?>
                            <?php while ($row = $result->fetch_assoc()): ?>
                                <tr>
                                    <td><?= htmlspecialchars($row['nombre']) ?></td>
                                    <td><?= htmlspecialchars($row['email']) ?></td>
                                    <td><?= htmlspecialchars($row['rol']) ?></td>
                                    <td><?= htmlspecialchars($row['departamento']) ?></td>
                                    <td><?= htmlspecialchars($row['puesto']) ?></td>
                                    <td class="text-center">
                                        <?php if ($row['estado'] == 'Activo'): ?>
                                            <span class="badge bg-success">Activo</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Inactivo</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <div class="d-flex justify-content-center gap-2">
                                            <a href="UT_edituser.php?id=<?= $row['user_id'] ?>" class="btn btn-warning btn-sm" title="Modificar Usuario">
                                                <i class="fas fa-user-edit"></i>
                                            </a>
                                            <?php if ($row['estado'] == 'Activo'): ?>
                                            <a href="UT_permisos.php?id=<?= $row['user_id'] ?>" class="btn btn-primary btn-sm" title="Asignar Permisos">
                                                <i class="fas fa-user-lock"></i>
                                            </a>
                                            <?php else: ?>
                                            <?php endif; ?>
                                            <form method="POST" action="" class="d-inline" onsubmit="return confirm('¿Estás seguro de que deseas eliminar este usuario?');">
                                                <input type="hidden" name="id" value="<?= $row['user_id'] ?>">
                                                <button type="submit" class="btn btn-danger btn-sm" name="delete" title="Eliminar Usuario">
                                                    <i class="fas fa-user-times"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    var table = $('#usuarios').DataTable({
        "language": { "url": "//cdn.datatables.net/plug-ins/1.10.16/i18n/Spanish.json" },
        "pageLength": 10,
        "lengthMenu": [10, 25, 50],
        "responsive": true,
        "columnDefs": [
            { "orderable": false, "targets": [6] } // Deshabilitar orden en la columna de acciones
        ]
    });

    // Filtrar por Rol (columna 2, índice base 0)
    $('#filtroRol').on('change', function() {
        table.column(2).search($(this).val()).draw();
    });

    // Filtrar por Departamento (columna 3)
    $('#filtroDepartamento').on('change', function() {
        table.column(3).search($(this).val()).draw();
    });
});
</script>

<?php
include("src/templates/adminfooter.php");
?>