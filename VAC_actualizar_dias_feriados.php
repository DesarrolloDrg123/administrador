<?php
session_start();
ob_start(); // Inicia el buffer de salida para evitar errores de "headers already sent"
require("config/db.php");
include("src/templates/adminheader.php");

if (!isset($_SESSION['loggedin']) || !$_SESSION['loggedin']) {
    header("Location: index.php");
    exit();
}
/*
if (!isset($_SESSION['permisos'][6])) { //Permiso de Solicitar vacaciones
    header("Location: inicio.php");
    exit();
}*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Acción para agregar un nuevo día feriado
    if (isset($_POST['accion']) && $_POST['accion'] === 'agregar') {
        $fecha = $_POST['fecha'];
        $descripcion = $_POST['descripcion'];

        if (!empty($fecha) && !empty($descripcion)) {
            $sql = "INSERT INTO dias_feriados (fecha, descripcion) VALUES (?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ss", $fecha, $descripcion);
            if ($stmt->execute()) {
                $_SESSION['success'] = "Día feriado agregado correctamente.";
            } else {
                $_SESSION['error'] = "Error al agregar el día feriado.";
            }
        }
    }

    // Acción para eliminar un día feriado
    if (isset($_POST['accion']) && $_POST['accion'] === 'eliminar') {
        $id = $_POST['id'];

        if (!empty($id)) {
            $sql = "DELETE FROM dias_feriados WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $id);
            if ($stmt->execute()) {
                $_SESSION['success'] = "Día feriado eliminado correctamente.";
            } else {
                $_SESSION['error'] = "Error al eliminar el día feriado.";
            }
        }
    }

    // Redirige después de procesar el formulario
    header("Location: VAC_actualizar_dias_feriados.php");
    exit();
}

// Obtener todos los días feriados
$sql = "SELECT * FROM dias_feriados ORDER BY fecha ASC";
$result = $conn->query($sql);
$dias_feriados = $result->fetch_all(MYSQLI_ASSOC);
ob_end_flush(); // Finaliza el buffer de salida
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administrar Días Feriados</title>
</head>
<body>
<div class="container mt-5">
    <h1 class="mb-4">Administrar Días Feriados</h1>

    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
    <?php endif; ?>
    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
    <?php endif; ?>

    <form action="VAC_actualizar_dias_feriados.php" method="POST" class="mb-4">
        <input type="hidden" name="accion" value="agregar">
        <div class="mb-3">
            <label for="fecha" class="form-label">Fecha</label>
            <input type="date" id="fecha" name="fecha" class="form-control" required>
        </div>
        <div class="mb-3">
            <label for="descripcion" class="form-label">Descripción</label>
            <input type="text" id="descripcion" name="descripcion" class="form-control" required>
        </div>
        <button type="submit" class="btn btn-primary">Agregar Día Feriado</button>
    </form>

    <h2 class="mb-3">Lista de Días Feriados</h2>
    <table class="table table-bordered">
        <thead>
        <tr>
            <th>Fecha</th>
            <th>Descripción</th>
            <th>Acciones</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($dias_feriados as $feriado): ?>
            <tr>
                <td><?php echo htmlspecialchars($feriado['fecha']); ?></td>
                <td><?php echo htmlspecialchars($feriado['descripcion']); ?></td>
                <td>
                    <form action="VAC_actualizar_dias_feriados.php" method="POST" class="d-inline">
                        <input type="hidden" name="accion" value="eliminar">
                        <input type="hidden" name="id" value="<?php echo $feriado['id']; ?>">
                        <button type="submit" class="btn btn-danger btn-sm">Eliminar</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
</body>
</html>
<?php 
$conn->close();
require("src/templates/adminfooter.php");
?>
