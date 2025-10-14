<?php 
include ("src/templates/adminheader.php");
require_once ("config/db.php");

$success_message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['guardar'])) {
    $nombre = mysqli_real_escape_string($conn, $_POST['nombre']);
    $correo = mysqli_real_escape_string($conn, $_POST['correo']);

    // Aquí se realiza la inserción del nuevo registro
    $sql = "INSERT INTO beneficiarios (beneficiario, email) VALUES ('$nombre', '$correo')";

    if (mysqli_query($conn, $sql)) {
        $success_message = "¡El nuevo beneficiario ha sido agregado exitosamente!";
    } else {
        echo "Error: " . $sql . "<br>" . mysqli_error($conn);
    }
}

if (!isset($_SESSION['loggedin']) || !$_SESSION['loggedin']) {
    header("Location: index.php");
    exit();
}

mysqli_close($conn);
?>

<div class="container col-md-4">
    
<h1 class="text-center mb-3">Agregar Beneficiario</h1>

<!-- Mostrar el mensaje de éxito si existe -->
<?php if (!empty($success_message)): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <?php echo $success_message; ?>
        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
            <span aria-hidden="true">&times;</span>
        </button>
    </div>
<?php endif; ?>

<form action="" method="POST">
    <div class="row mb-1">
        <div class="col">
            <label class="form-label">Nombre</label>
            <input type="text" name="nombre" class="form-control" placeholder="Beneficiario"
                aria-label="Nombre" required>
        </div>
    </div>
    <div class="row mb-1">
        <div class="col">
            <label class="form-label">Correo Electrónico</label>
            <input type="email" name="correo" class="form-control" placeholder="alguien@ejemplo.com"
                aria-label="Correo" required>
        </div>
    </div>
 
    <button type="submit" name="guardar" class="btn btn-success mt-2">Agregar</button>
    <button type="button" class="btn btn-danger mt-2" onclick="window.location.href='TR_beneficiarios.php';">Cancelar</button>
</form>
</div>

<?php
include ('src/templates/adminfooter.php');
?>
