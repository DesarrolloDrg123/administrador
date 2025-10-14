<?php
session_start();
require "config/db.php";
include "src/templates/header.php";

if (!isset($_SESSION['loggedin']) || !$_SESSION['loggedin']) {
    header("Location: index.php");
    exit();
}

$usuario_id = $_SESSION['usuario_id'];

try {
    // Obtener solicitudes pendientes para el usuario
    $sql = 'SELECT t.id, s.sucursal, b.beneficiario, t.fecha_solicitud, t.importe, t.descripcion, t.estado, t.usuario_id
        FROM transferencias t
        JOIN sucursales s ON t.sucursal_id = s.id
        JOIN beneficiarios b ON t.beneficiario_id = b.id
        WHERE t.usuario_id = ?';
    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        throw new Exception($conn->error);
    }
    $stmt->bind_param("i", $usuario_id);
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


   
    
    //echo "Usuario ID: " . $usuario_id . "<br>";
    //echo "Número de solicitudes encontradas: " . count($solicitudes) . "<br>";

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
</head>

<body>
    <div class="container mt-5">
        <h1 class="mb-4">Mis Solicitudes</h1>

        <!-- Tabla de solicitudes pendientes -->
        <h2>Solicitudes Pendientes</h2>
        <?php if (count($solicitudes) > 0): ?>
            
            <?php
             
             ?>
    <table class="table table-striped">
        <thead>
            <tr>
                <th>Sucursal</th>
                <th>Beneficiario</th>
                <th>Fecha Solicitud</th>
                <th>Importe</th>
                <th>Descripción</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($solicitudes as $solicitud): ?>
                <tr>
                    <td><?php echo htmlspecialchars($solicitud['sucursal']); ?></td>
                    <td><?php echo htmlspecialchars($solicitud['beneficiario']); ?></td>
                    <td><?php echo htmlspecialchars($solicitud['fecha_solicitud']); ?></td>
                    <td><?php echo htmlspecialchars($solicitud['importe']); ?></td>
                    <td><?php echo htmlspecialchars($solicitud['descripcion']); ?></td>
                    <td><?php echo htmlspecialchars($solicitud['estado']); ?></td>
                   
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php else: ?>
    <p>No hay solicitudes pendientes.</p>
<?php endif; ?>

    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.3/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>

</html>

<?php
include 'src/templates/footer.php';
$conn->close();
?>