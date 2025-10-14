<?php
include("src/templates/adminheader.php");
require("config/db.php");

if (!isset($_SESSION['loggedin']) || !$_SESSION['loggedin']) {
    header("Location: index.php");
    exit();
}

// Obtener el nombre del usuario de sesión
$usuario = $_SESSION['nombre'];

// Consulta para obtener las transferencias
$sql = "SELECT t.folio, t.autorizacion_id, t.usuario_id, t.departamento_id, t.categoria_id, t.descripcion, t.estado, u.id AS autorizador_id, u.nombre AS autorizador_nombre, d.id, d.departamento, c.id, c.categoria, u2.nombre AS solicitante
        FROM transferencias t
        JOIN usuarios u ON t.autorizacion_id = u.id
        JOIN departamentos d ON t.departamento_id = d.id
        JOIN categorias c ON t.categoria_id = c.id
        JOIN usuarios u2 ON t.usuario_id = u2.id
        ORDER BY folio DESC";

$result = mysqli_query($conn, $sql);

// Consulta para obtener los autorizadores únicos en las transferencias
$sql_autorizadores = "SELECT DISTINCT u.id, u.nombre 
                      FROM transferencias t
                      JOIN usuarios u ON t.autorizacion_id = u.id";

$autorizadores_result = mysqli_query($conn, $sql_autorizadores);
$autorizadores = mysqli_fetch_all($autorizadores_result, MYSQLI_ASSOC);
?>

<!doctype html>
<html lang="en">

<head>
    <title>Reasignar Transferencia</title>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-T3c6CoIi6iLrA9TneNEoa7RxnatzjcDSCmG1MXxSR1GAsXEV/Dwwykc2MPK8M2HN" crossorigin="anonymous" />
</head>

<body>
    <header>
        <!-- place navbar here -->
    </header>
    <main>
        <div class="container">
            <?php if ($result): ?>
                <table class="table table-dark table-striped">
                    <thead>
                        <tr>
                            <th scope="col">Folio</th>
                            <th scope="col">Autoriza</th>
                            <th scope="col">Departamento</th>
                            <th scope="col">Solicita</th>
                            <th scope="col">Reasignar</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = mysqli_fetch_assoc($result)): ?>
                            <?php if($row['estado'] == 'Pendiente' ):?>

                            
                            <tr>
                                <td><?php echo htmlspecialchars($row['folio']); ?></td>
                                <td><?php echo htmlspecialchars($row['autorizador_nombre']); ?></td>
                                <td><?php echo htmlspecialchars($row['departamento']); ?></td>
                                <td><?php echo htmlspecialchars($row['solicitante']); ?></td>
                                <td>
                                    <form method="post" action="TR_controller/reasignar_transferencia.php" onsubmit="return confirmReassign();">
                                        <input type="hidden" name="folio" value="<?php echo $row['folio']; ?>">
                                        <select name="nuevo_autorizador" class="form-select">
                                            <?php foreach ($autorizadores as $autorizador): ?>
                                                <option value="<?php echo $autorizador['id']; ?>" <?php echo $autorizador['id'] == $row['autorizacion_id'] ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($autorizador['nombre']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <button type="submit" class="btn btn-success mt-2">Reasignar</button>
                                    </form>
                                </td>
                            </tr>
                            <?php endif ?>

                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php endif; ?>
            <a
                name="volver"
                id="volver"
                class="btn btn-dark"
                href="inicio.php"
                role="button"
                >Volver</a
            >
            
        </div>
    </main>
    <footer>
        <!-- place footer here -->
    </footer>
    <script>
        function confirmReassign() {
            return confirm('¿Está seguro de que desea reasignar esta transferencia al autorizador seleccionado?');
        }
    </script>
    
</body>

</html>

<?php
$conn->close();
include("src/templates/adminfooter.php");
?>
