<?php
session_start();
require "config/db.php";
include "src/templates/header.php";

if (!isset($_SESSION['loggedin']) || !$_SESSION['loggedin']) {
    header("Location: index.php");
    exit();
}

$autorizacion_id = $_SESSION['usuario_id'];
$solicitudes = [];

try {
    // Obtener solicitudes pendientes para el usuario
    $sql = 'SELECT t.id, t.folio, s.sucursal, b.beneficiario, t.fecha_solicitud, t.importe, t.descripcion, t.estado, t.autorizacion_id
        FROM transferencias t
        JOIN sucursales s ON t.sucursal_id = s.id
        JOIN beneficiarios b ON t.beneficiario_id = b.id
        WHERE t.autorizacion_id = ?';
    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        throw new Exception($conn->error);
    }
    $stmt->bind_param("i", $autorizacion_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $solicitudes = $result->fetch_all(MYSQLI_ASSOC);

    $fmt = new IntlDateFormatter('es_ES', IntlDateFormatter::LONG, IntlDateFormatter::NONE);
    foreach ($solicitudes as &$solicitud) {
        // Convertir la fecha a un objeto DateTime
        $fecha = new DateTime($solicitud['fecha_solicitud']);
        // Formatear la fecha y reemplazar en el array
        $solicitud['fecha_solicitud'] = $fmt->format($fecha);
    }
    unset($solicitud); // Romper la referencia con el último elemento

} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
    exit();
}
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Solicitudes</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <style>
        .table {
            font-size: 14px;
        }
        .table th, .table td {
            padding: 10px;
        }
    </style>
</head>

<body>
    <div class="container mt-5">
        <h1 class="mb-4">Mis Transferencias</h1>

        <!-- Formulario de búsqueda -->
        <form>
            <div class="form-group">
                <label for="search">Buscar transferencia:</label>
                <input type="text" class="form-control search" id="search" onkeyup="filterTable()">
            </div>
        </form>

        <!-- Tabla de solicitudes pendientes -->
        <h2>Solicitudes Pendientes</h2>
        <?php if (count($solicitudes) > 0): ?>
            <table class="table table-striped table-bordered" id="solicitudesTable">
                <thead>
                    <tr>
                        <th>Folio</th>
                        <th>Sucursal</th>
                        <th>Beneficiario</th>
                        <th>Fecha Solicitud</th>
                        <th>Importe</th>
                        <th>Descripción</th>
                        <th>Status</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($solicitudes as $solicitud): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($solicitud['folio']); ?></td>
                            <td><?php echo htmlspecialchars($solicitud['sucursal']); ?></td>
                            <td><?php echo htmlspecialchars($solicitud['beneficiario']); ?></td>
                            <td><?php echo htmlspecialchars($solicitud['fecha_solicitud']); ?></td>
                            <td><?php echo htmlspecialchars('$' . $solicitud['importe']); ?></td>
                            <td><?php echo htmlspecialchars($solicitud['descripcion']); ?></td>
                            <td><?php echo htmlspecialchars($solicitud['estado']); ?></td>
                            <td>
                                <a href="ver_transferencias.php?id=<?php echo $solicitud['id']; ?>" class="btn btn-primary btn-sm">Ver</a>

                                <?php if ($solicitud['estado'] == 'Pendiente'): ?>
                                    <a href="aprobar.php?id=<?php echo $solicitud['id']; ?>" class="btn btn-success btn-sm btn-aprobar">Aprobar</a>
                                    <a href="rechazar.php?id=<?php echo $solicitud['id']; ?>" class="btn btn-danger btn-sm">Rechazar</a>  
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p>No hay solicitudes pendientes.</p>
        <?php endif; ?>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.3/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
    function filterTable() {
        var input, filter, table, tr, td, i, j, txtValue;
        input = document.getElementById("search");
        filter = input.value.toLowerCase();
        table = document.getElementById("solicitudesTable");
        tr = table.getElementsByTagName("tr");

        for (i = 1; i < tr.length; i++) {
            tr[i].style.display = "none"; // Initially hide the row
            td = tr[i].getElementsByTagName("td");
            for (j = 0; j < td.length; j++) {
                if (td[j]) {
                    txtValue = td[j].textContent || td[j].innerText;
                    if (txtValue.toLowerCase().indexOf(filter) > -1) {
                        tr[i].style.display = ""; // Show the row if any cell matches the filter
                        break; // Exit the loop once a match is found
                    }
                }
            }
        }
    }

    $(document).ready(function() {
        $('.btn-aprobar').on('click', function(e) {
            e.preventDefault();
            var url = $(this).attr('href');
            
            $.ajax({
                url: url,
                type: 'GET',
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        alert(response.message);
                        // Opcional: recargar la página o actualizar la tabla
                        location.reload();
                    } else {
                        alert(response.message);
                    }
                },
                error: function() {
                    alert('Error al procesar la solicitud');
                }
            });
        });
    });
    </script>
</body>

</html>

<?php
$conn->close();
?>

<?php
include('src/templates/footer.php');
?>
