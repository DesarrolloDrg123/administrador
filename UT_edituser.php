<?php
require("config/db.php");
include("src/templates/adminheader.php");

// --- 1. VALIDACIÓN DE SESIÓN Y OBTENCIÓN DE ID ---
if (!isset($_SESSION['loggedin']) || !$_SESSION['loggedin']) {
    header("Location: index.php");
    exit();
}

$userId = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($userId === 0) {
    die("Error: ID de usuario no válido.");
}

// --- 2. INICIALIZACIÓN DE VARIABLES ---
$user = [];
$message = '';

// --- 3. CONSULTAS PARA LLENAR LOS SELECTS ---
$resultSucu = $conn->query("SELECT * FROM sucursales ORDER BY sucursal ASC");
$resultDepartamentos = $conn->query("SELECT * FROM departamentos ORDER BY departamento ASC");
$resultPuestos = $conn->query("SELECT * FROM puestos ORDER BY puesto ASC");
$resultJefes = $conn->query("SELECT * FROM usuarios ORDER BY nombre ASC");

// --- 4. LÓGICA DE ACTUALIZACIÓN DEL FORMULARIO (POST) ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['guardar'])) {
    // Recolección de datos
    $nombre = $_POST['nombre'];
    $email = $_POST['email'];
    //$rol = $_POST['rol'];
    $departamento = $_POST['departamento'];
    $puesto = $_POST['puesto'];
    $no_empleado = $_POST['noempleado'];
    $estatus = intval($_POST['estatus']);
    $password = $_POST['contra'] ?? '';
    $password_repeat = $_POST['password_repeat'] ?? '';
    $fechaingreso = $_POST['fechaingreso'];
    $sucursal = $_POST['sucursal'];
    $jefe = $_POST['jefe'];
    $tarjeta_clara = $_POST['tarjeta'] ?? '';

    if (!empty($password) && $password !== $password_repeat) {
        $message = "Las contraseñas no coinciden.";
    } else {
        // CORRECCIÓN: Reordenamos los campos para que coincidan con los tipos
        $fields = [
            // --- Campos de tipo String (s) ---
            'nombre' => $nombre, 
            'email' => $email, 
            //'rol' => $rol, 
            'departamento' => $departamento, 
            'puesto' => $puesto,
            'fecha_ingreso' => $fechaingreso,
            // --- Campos de tipo Integer (i) ---
            'num_empleado' => $no_empleado,
            'sucursal' => $sucursal, 
            'jefe_directo' => $jefe,
            'estatus' => $estatus,
            'tarjeta_clara' => $tarjeta_clara
        ];
        // CORRECCIÓN: La cadena de tipos ahora corresponde al nuevo orden
        $types = 'sssssiiiii';

        if (!empty($password)) {
            $fields['password'] = password_hash($password, PASSWORD_DEFAULT);
            $types .= 's';
        }

        $set_clause = [];
        foreach ($fields as $key => $value) {
            $set_clause[] = "$key = ?";
        }
        $sql = "UPDATE usuarios SET " . implode(', ', $set_clause) . " WHERE id = ?";
        $types .= 'i';
        $params = array_values($fields);
        $params[] = $userId;

        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);

        if ($stmt->execute()) {
            $message = "Usuario actualizado correctamente.";
        } else {
            $message = "Error al actualizar el usuario: " . $stmt->error;
        }
        $stmt->close();
    }
}

// --- 5. OBTENER DATOS DEL USUARIO PARA MOSTRAR EN EL FORMULARIO ---
$sql_user = "SELECT * FROM usuarios WHERE id = ?";
$stmt_user = $conn->prepare($sql_user);
$stmt_user->bind_param("i", $userId);
$stmt_user->execute();
$result_user = $stmt_user->get_result();
if ($result_user->num_rows > 0) {
    $user = $result_user->fetch_assoc();
} else {
    $message = "Usuario no encontrado.";
}
$stmt_user->close();
?>

<div class="container mt-5 mb-5">
    <div class="row justify-content-center">
        <div class="col-md-9">
            <div class="card shadow-sm">
                <div class="card-header text-center bg-dark text-white">
                    <h2>Editar Usuario</h2>
                </div>
                <div class="card-body p-4">

                    <?php if ($message): ?>
                        <div class="alert alert-info"><?= htmlspecialchars($message); ?></div>
                    <?php endif; ?>

                    <?php if (!empty($user)): ?>
                    <form id="editUserForm" method="POST">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="nombre" class="form-label">Nombre Completo:*</label>
                                <input type="text" id="nombre" name="nombre" class="form-control" value="<?= htmlspecialchars($user['nombre'] ?? ''); ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label for="noempleado" class="form-label">Número de Empleado:*</label>
                                <input type="text" id="noempleado" name="noempleado" class="form-control" value="<?= htmlspecialchars($user['num_empleado'] ?? ''); ?>" required>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="email" class="form-label">Correo Electrónico:*</label>
                                <input type="email" id="email" name="email" class="form-control" value="<?= htmlspecialchars($user['email'] ?? ''); ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label for="fechaingreso" class="form-label">Fecha de Ingreso:*</label>
                                <input type="date" id="fechaingreso" name="fechaingreso" class="form-control" value="<?= htmlspecialchars($user['fecha_ingreso'] ?? ''); ?>" required>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <!--<div class="col-md-6">
                                <label for="rol" class="form-label">Rol:*</label>
                                <select class="form-select" id="rol" name="rol" required>
                                    <option value="admin" <?/* = ($user['rol'] ?? '') == 'admin' ? 'selected' : ''; ?>>Admin</option>
                                    <option value="usuario" <?= ($user['rol'] ?? '') == 'usuario' ? 'selected' : ''; ?>>Usuario</option>
                                    <option value="autorizador" <?= ($user['rol'] ?? '') == 'autorizador' ? 'selected' : ''; ?>>Autorizador</option>
                                    <option value="cuentas" <?= ($user['rol'] ?? '') == 'cuentas' ? 'selected' : ''; */?>>Cuentas</option>
                                </select>
                            </div>-->
                            <div class="col-md-6">
                                <label for="departamento" class="form-label">Departamento:*</label>
                                <select class="form-select" id="departamento" name="departamento" required>
                                    <?php while ($row = $resultDepartamentos->fetch_assoc()): ?>
                                        <option value="<?= htmlspecialchars($row['departamento']) ?>" <?= ($user['departamento'] ?? '') == $row['departamento'] ? 'selected' : ''; ?>>
                                            <?= htmlspecialchars($row['departamento']) ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                        </div>

                        <div class="row mb-3">
                             <div class="col-md-6">
                                <label for="puesto" class="form-label">Puesto:*</label>
                                <select class="form-select" id="puesto" name="puesto" required>
                                    <option value="" disabled selected>Selecciona un Puesto</option>
                                    <?php mysqli_data_seek($resultPuestos, 0); // Reiniciar puntero ?>
                                    <?php while ($row = $resultPuestos->fetch_assoc()): ?>
                                        <option value="<?= htmlspecialchars($row['puesto']) ?>" <?= ($user['puesto'] ?? '') == $row['puesto'] ? 'selected' : ''; ?>>
                                            <?= htmlspecialchars($row['puesto']) ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="sucursal" class="form-label">Sucursal:*</label>
                                <select class="form-select" id="sucursal" name="sucursal" required>
                                    <option value="" disabled selected>Selecciona una Sucursal</option>
                                    <?php mysqli_data_seek($resultSucu, 0); // Reiniciar puntero ?>
                                    <?php while ($row = $resultSucu->fetch_assoc()): ?>
                                        <option value="<?= htmlspecialchars($row['id']) ?>" <?= ($user['sucursal'] ?? '') == $row['id'] ? 'selected' : ''; ?>>
                                            <?= htmlspecialchars($row['sucursal']) ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="jefe" class="form-label">Jefe Directo:*</label>
                                <select class="form-select" id="jefe" name="jefe" required>
                                    <option value="" disabled selected>Selecciona un Jefe</option>
                                    <?php mysqli_data_seek($resultJefes, 0); // Reiniciar puntero ?>
                                    <?php while ($row = $resultJefes->fetch_assoc()): ?>
                                        <option value="<?= htmlspecialchars($row['id']) ?>" <?= ($user['jefe_directo'] ?? '') == $row['id'] ? 'selected' : ''; ?>>
                                            <?= htmlspecialchars($row['nombre']) ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="estatus" class="form-label">Estatus:*</label>
                                <select class="form-select" id="estatus" name="estatus" required>
                                    <option value="1" <?= ($user['estatus'] ?? '') == '1' ? 'selected' : ''; ?>>Activo</option>
                                    <option value="2" <?= ($user['estatus'] ?? '') == '2' ? 'selected' : ''; ?>>Inactivo</option>
                                </select>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="tarjeta" class="form-label">Numero de tarjeta:</label>
                                <input type="number" id="tarjeta" name="tarjeta" class="form-control" value="<?= htmlspecialchars($user['tarjeta_clara'] ?? ''); ?>" >
                            </div>
                        </div>
                        
                        <hr>
                        <p class="text-center text-muted small">Dejar en blanco si no se desea cambiar la contraseña</p>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="contra" class="form-label">Nueva Contraseña:</label>
                                <input type="password" id="contra" name="contra" class="form-control">
                            </div>
                            <div class="col-md-6">
                                <label for="password_repeat" class="form-label">Confirmar Nueva Contraseña:</label>
                                <input type="password" id="password_repeat" name="password_repeat" class="form-control">
                            </div>
                        </div>

                        <div class="card-footer text-center">
                            <button type="submit" class="btn btn-primary" name="guardar">Guardar Cambios</button>
                            <a href="UT_gestion_usuarios.php" class="btn btn-secondary">Regresar</a>
                        </div>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('editUserForm').addEventListener('submit', function(event) {
    const contra = document.getElementById('contra').value;
    const passwordRepeat = document.getElementById('password_repeat').value;

    // Solo valida si el campo de nueva contraseña no está vacío
    if (contra && contra !== passwordRepeat) {
        event.preventDefault(); // Detiene el envío del formulario
        alert('Las contraseñas no coinciden.');
    }
});
</script>

<?php
include("src/templates/adminfooter.php");
?>