<?php
session_start();
include("config/db.php");
include("src/templates/header.php");

require 'vendor/autoload.php'; // Asegúrate de que la ruta a PHPMailer sea correcta

if (!isset($_SESSION['loggedin']) || !$_SESSION['loggedin']) {
    header("Location: index.php");
    exit();
}

if (!isset($_GET['id'])) {
    echo "ID de solicitud no proporcionado.";
    exit();
}

$solicitud_id = intval($_GET['id']);
$usuario_id = $_SESSION['usuario_id'];

try {
    $sql = 'SELECT t.id, s.sucursal AS sucursal, b.beneficiario AS beneficiario, t.fecha_solicitud, t.importe, t.descripcion, t.estado
            FROM transferencias t
            JOIN sucursales s ON t.sucursal_id = s.id
            JOIN beneficiarios b ON t.beneficiario_id = b.id
            WHERE t.id = ? AND t.autorizacion_id = ?';
    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        throw new Exception($conn->error);
    }
    $stmt->bind_param('ii', $solicitud_id, $usuario_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $solicitud = $result->fetch_assoc();

    // Verificar si la consulta no retornó resultados
    if (!$solicitud) {
        echo "No se encontró la solicitud o no tienes permiso para verla.";
        exit();
    }
} catch (Exception $e) {
    echo $e->getMessage();
    exit();
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Solicitud de Transferencia</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
</head>
<body>
    <div class="container mt-5">
        <h1 class="mb-4">Detalle de la Transferencia</h1>

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
                <tr>
                    <td><?php echo htmlspecialchars($solicitud['sucursal'] ?? 'N/A'); ?></td>
                    <td><?php echo htmlspecialchars($solicitud['beneficiario'] ?? 'N/A'); ?></td>
                    <td><?php echo isset($solicitud['fecha_solicitud']) ? date("d F Y", strtotime($solicitud['fecha_solicitud'])) : 'N/A'; ?></td>
                    <td><?php echo htmlspecialchars($solicitud['importe'] ?? 'N/A'); ?></td>
                    <td><?php echo htmlspecialchars($solicitud['descripcion'] ?? 'N/A'); ?></td>
                    <td><?php echo htmlspecialchars($solicitud['estado'] ?? 'N/A'); ?></td>
                </tr>
            </tbody>
        </table>

        <div>
            <?php if ($solicitud && $solicitud['estado'] == 'Pendiente'): ?>
                <a href="aprobar.php?id=<?php echo $solicitud['id']; ?>" class="btn btn-success btn-sm">Aprobar</a>
                <a href="rechazar.php?id=<?php echo $solicitud['id']; ?>" class="btn btn-danger btn-sm">Rechazar</a>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.3/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>

<?php
$conn->close();
?>
