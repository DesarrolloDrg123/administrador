<?php
require("config/db.php");
include("src/templates/adminheader.php");


$sql = "SELECT * FROM beneficiarios";
$result = mysqli_query($conn, $sql);

if (!isset($_SESSION['loggedin']) || !$_SESSION['loggedin']) {
    header("Location: index.php");
    exit();
}

?>

<style>
    .btn-primary {
        background-color: #3498db;
        border-color: #3498db;
    }

    .btn-secondary {
        background-color: #7f8c8d;
        border-color: #7f8c8d;
    }

    .table thead {
        background-color: #343a40;
        color: #fff;
    }

    .table {
        background-color: #ffffff;
        border-radius: 10px;
        overflow: hidden;
    }

    .table th {
        background-color: #333;
        color: #ffffff;
        padding: 10px;
        border-bottom: 1px solid #3498db;
    }

    .table td {
        padding: 10px;
        border-bottom: 1px solid #dddddd;
    }
    #search{
        height: 47px;
    }
</style>

<div class="container mt-5">
    <h1>Lista de Beneficiarios</h1>

    <a name="" id="" class="btn btn-primary mb-4" href="TR_add_bene.php" role="button">Agregar Beneficiario</a>

    <!-- Mostrar mensajes de éxito o error -->
    <?php if (isset($_GET['msg'])): ?>
        <?php if ($_GET['msg'] == 'deleted'): ?>
            <div class="alert alert-success" role="alert">¡Beneficiario eliminado exitosamente!</div>
        <?php elseif ($_GET['msg'] == 'error'): ?>
            <div class="alert alert-danger" role="alert">Hubo un error al eliminar el beneficiario.</div>
        <?php elseif ($_GET['msg'] == 'invalid_id'): ?>
            <div class="alert alert-warning" role="alert">ID inválido</div>
        <?php endif; ?>
    <?php endif; ?>

    <?php
    if ($result && mysqli_num_rows($result) > 0) {
        echo '<table class="table table-striped" id="beneficiariosTable">';
        echo '<thead>';
        echo '<tr>';
        echo '<th>Beneficiario</th>';
        echo '<th>Acciones</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';

        while ($row = mysqli_fetch_assoc($result)) {
            echo '<tr>';
            echo '<td>' . htmlspecialchars($row['beneficiario']) . '</td>';
            echo '<td>';
            echo '<a name="editar" id="editar" class="btn btn-warning mr-3" href="TR_edit_bene.php?id=' . $row['id'] . '" role="button">Modificar</a>';
            echo '<a name="eliminar" id="eliminar" class="btn btn-danger" href="TR_controller/delete_bene.php?id=' . $row['id'] . '" onclick="return confirm(\'¿Estás seguro de que deseas eliminar este beneficiario?\');" role="button">Eliminar</a>';
            echo '</td>';
            echo '</tr>';
        }

        echo '</tbody>';
        echo '</table>';
    } else {
        echo '<p>No hay beneficiarios registrados.</p>';
    }
    ?>
</div>

<script>
        $(document).ready(function() {
            var table = $('#beneficiariosTable').DataTable({
                "language": {
                    "url": "//cdn.datatables.net/plug-ins/1.10.16/i18n/Spanish.json"
                },
                "pageLength": 10,
                "lengthMenu": [5, 10, 20],
                "responsive": true,  // Habilitar la respuesta en diferentes tamaños de pantalla
                "processing": true,
                "columnDefs": [
                    { "orderable": false, "targets": [0,1] }
                ]
            });
        });
    </script>

<?php
include("src/templates/adminfooter.php");
?>
