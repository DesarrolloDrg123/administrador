<?php
require("config/db.php");
include("src/templates/adminheader.php");

if (!isset($_SESSION['loggedin']) || !$_SESSION['loggedin']) {
    header("Location: index.php");
    exit();
}

$userId = isset($_GET['id']) ? intval($_GET['id']) : 0;

$beneficiario = '';
$email = '';
$message = '';

if ($userId > 0) {
    $sql = "SELECT * FROM beneficiarios WHERE id = $userId";
    $result = mysqli_query($conn, $sql);

    if ($result && mysqli_num_rows($result) > 0) {
        $user = mysqli_fetch_assoc($result);
        $beneficiario = $user['beneficiario'];
        $email = $user['email'];
    } else {
        $message = "Beneficiario no encontrado.";
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['guardar'])) {
    $beneficiario = mysqli_real_escape_string($conn, $_POST['beneficiario']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);

    if ($userId > 0) {
        $update_sql = "UPDATE beneficiarios SET beneficiario = '$beneficiario', email = '$email' WHERE id = $userId";

        if (mysqli_query($conn, $update_sql)) {
            if (mysqli_affected_rows($conn) > 0) {
                $message = "Beneficiario actualizado correctamente.";
            } else {
                $message = "No se realizaron cambios en el Beneficiario.";
            }
        } else {
            $message = "Error al actualizar el Beneficiario: " . mysqli_error($conn);
        }
    }
}

?>

<div class="container mt-5 d-flex justify-content-center">
    <form id="editUserForm" method="POST">
        <h2 class="form-group">Editar Beneficiario</h2>

        <?php if ($message): ?>
            <div class="alert alert-info"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>

        <div class="form-group">
            <label for="beneficiario">Nombre:</label>
            <input type="text" class="form-control" id="beneficiario" name="beneficiario" value="<?php echo htmlspecialchars($beneficiario); ?>" required>
        </div>
        <div class="form-group">
            <label for="email">Correo Electrónico:</label>
            <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($email); ?>">
        </div>

        <button type="submit" class="btn btn-primary" name="guardar">Guardar cambios</button>
        <button type="button" class="btn btn-danger" onclick="cancelEdit()">Cancelar</button>
    </form>
</div>

<script>
    function cancelEdit() {
        if (confirm('¿Está seguro de que desea cancelar los cambios?')) {
            window.location.href = 'TR_beneficiarios.php';
        }
    }
</script>

<?php
include("src/templates/adminfooter.php");
?>
