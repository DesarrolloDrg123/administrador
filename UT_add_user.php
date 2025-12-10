<?php
require("config/db.php");
include("src/templates/adminheader.php");

// --- Validación de Sesión ---
if (!isset($_SESSION['loggedin']) || !$_SESSION['loggedin']) {
    header("Location: index.php");
    exit();
}

$message = '';
$rol = '';

// --- Consultas para llenar los <select> ---
$resultSucu = $conn->query("SELECT * FROM sucursales ORDER BY sucursal ASC");
$resultDepartamentos = $conn->query("SELECT * FROM departamentos ORDER BY departamento ASC");
$resultPuestos = $conn->query("SELECT * FROM puestos ORDER BY puesto ASC");
$resultJefes = $conn->query("SELECT * FROM usuarios ORDER BY nombre ASC");

// --- Lógica para guardar el formulario ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['guardar'])) {
    // Recolección de datos
    $nombre = $_POST['nombre'];
    $email = $_POST['email'];
    $password = $_POST['contraseña'];
    $repetir_password = $_POST['repetir_contraseña'];
    $rol = $_POST['rol'];
    $departamento = $_POST['departamento'];
    $noempleado = $_POST['noempleado'];
    $puesto = $_POST['puesto'];
    $fechaingreso = $_POST['fechaingreso'];
    $sucursal = $_POST['sucursal'];
    $jefe = $_POST['jefe'];
    $estatus = '1';
    $tarjeta_clara = $_POST['tarjeta_clara'] ?? '';

    // Validación de contraseñas
    if ($password !== $repetir_password) {
        $message = "Las contraseñas no coinciden.";
    } else {
        // Hash de la contraseña
        $passwordHash = password_hash($password, PASSWORD_BCRYPT);

        // Uso de Sentencias Preparadas (más seguro)
        $sql = "INSERT INTO usuarios (nombre, email, password, rol, num_empleado, departamento, fecha_ingreso, puesto, estatus, sucursal, jefe_directo, tarjeta_clara) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $conn->prepare($sql);
        // 'ssssisssiii' define los tipos de datos: s=string, i=integer
        $stmt->bind_param('ssssisssiiii', $nombre, $email, $passwordHash, $rol, $noempleado, $departamento, $fechaingreso, $puesto, $estatus, $sucursal, $jefe, $tarjeta_clara);

        if ($stmt->execute()) {
            $message = "Nuevo Registro Agregado Correctamente";
        } else {
            $message = "Error al agregar registro: " . $stmt->error;
        }
        $stmt->close();
    }
}
?>

<div class="container mt-5 mb-5">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card shadow-sm">
                <div class="card-header text-center bg-dark text-white">
                    <h2>Registrar Nuevo Usuario</h2>
                </div>
                <div class="card-body p-4">

                    <?php if ($message): ?>
                        <div class="alert alert-info"><?= htmlspecialchars($message); ?></div>
                    <?php endif; ?>

                    <form action="#" method="POST">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="nombre" class="form-label">Nombre Completo:*</label>
                                <input type="text" id="nombre" name="nombre" class="form-control" placeholder="Ej. Juan Pérez" required>
                            </div>
                            <div class="col-md-6">
                                <label for="noempleado" class="form-label">Número de Empleado:*</label>
                                <input type="text" id="noempleado" name="noempleado" class="form-control" placeholder="Ej. 1024" required>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="rol" class="form-label">Rol:*</label>
                                <select class="form-select" id="rol" name="rol" required>
                                    <option value="" selected disabled>Selecciona un Rol</option>
                                    <option value="admin">Admin</option>
                                    <option value="usuario">Usuario</option>
                                    <option value="autorizador">Autorizador</option>
                                    <option value="cuentas">Cuentas</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="departamento" class="form-label">Departamento:*</label>
                                <select class="form-select" id="departamento" name="departamento" required>
                                    <option value="" selected disabled>Selecciona un Departamento</option>
                                    <?php while ($row = $resultDepartamentos->fetch_assoc()): ?>
                                        <option value="<?= htmlspecialchars($row['departamento']) ?>"><?= htmlspecialchars($row['departamento']) ?></option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="puesto" class="form-label">Puesto:*</label>
                                <select class="form-select" id="puesto" name="puesto" required>
                                    <option value="" selected disabled>Selecciona un Puesto</option>
                                    <?php while ($row = $resultPuestos->fetch_assoc()): ?>
                                        <option value="<?= htmlspecialchars($row['puesto']) ?>"><?= htmlspecialchars($row['puesto']) ?></option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="sucursal" class="form-label">Sucursal:*</label>
                                <select class="form-select" id="sucursal" name="sucursal" required>
                                    <option value="" selected disabled>Selecciona una Sucursal</option>
                                    <?php while ($row = $resultSucu->fetch_assoc()): ?>
                                        <option value="<?= htmlspecialchars($row['id']) ?>"><?= htmlspecialchars($row['sucursal']) ?></option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                             <div class="col-md-6">
                                <label for="email" class="form-label">Correo Electrónico:*</label>
                                <input type="email" id="email" name="email" class="form-control" placeholder="usuario@ejemplo.com" required>
                            </div>
                            <div class="col-md-6">
                                <label for="fechaingreso" class="form-label">Fecha de Ingreso:*</label>
                                <input type="date" id="fechaingreso" name="fechaingreso" class="form-control" required>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="contraseña" class="form-label">Contraseña:*</label>
                                <input type="password" id="contraseña" name="contraseña" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label for="repetir_contraseña" class="form-label">Confirmar Contraseña:*</label>
                                <input type="password" id="repetir_contraseña" name="repetir_contraseña" class="form-control" required>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-12">
                                <label for="jefe" class="form-label">Jefe Directo:*</label>
                                <select class="form-select" id="jefe" name="jefe" required>
                                    <option value="" disabled selected>Selecciona un Jefe</option>
                                    <?php while ($row = $resultJefes->fetch_assoc()): ?>
                                        <option value="<?= htmlspecialchars($row['id']) ?>"><?= htmlspecialchars($row['nombre']) ?></option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="tarjeta" class="form-label">Numero de tarjeta:</label>
                                <input type="text" id="tarjeta" name="tarjeta" class="form-control" >
                            </div>
                        </div>

                        <div class="text-center mt-4">
                            <button type="submit" class="btn btn-primary" name="guardar">Registrar Usuario</button>
                            <a href="UT_gestion_usuarios.php" class="btn btn-secondary">Cancelar</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
include("src/templates/adminfooter.php");
?>