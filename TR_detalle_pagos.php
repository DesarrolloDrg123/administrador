<?php
session_start();
header('Content-Type: text/html; charset=utf-8');
require("config/db.php");
include("src/templates/adminheader.php");

if (!isset($_SESSION['loggedin']) || !$_SESSION['loggedin']) {
    header("Location: index.php");
    exit();
}

$solicitud_id = $_GET['id'];

// Set the locale to Spanish (es_ES) for date formatting
$fmt = new IntlDateFormatter(
    'es_ES', // Locale: Spanish (Spain)
    IntlDateFormatter::LONG, // Date format (e.g., "9 de septiembre de 2024")
    IntlDateFormatter::SHORT // Time format (e.g., "14:30")
);


// Check if form is submitted and the notas field is set
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['notas'])) {
    $notas_nueva = trim($_POST['notas']); // Sanitize new input

    // Check if the new note is not empty
    if (!empty($notas_nueva)) {
        try {
            // Fetch the existing notes first
            $sql_fetch = 'SELECT notas FROM transferencias WHERE id = ?';
            $stmt_fetch = $conn->prepare($sql_fetch);
            if ($stmt_fetch === false) {
                throw new Exception($conn->error);
            }

            $stmt_fetch->bind_param('i', $solicitud_id);
            $stmt_fetch->execute();
            $result_fetch = $stmt_fetch->get_result();
            $solicitud = $result_fetch->fetch_assoc();

            // Get the existing notes
            $notas_existente = $solicitud['notas'];

            // Get the current timestamp and format it in Spanish
            $now = new DateTime();  // Current time
            $timestamp = $fmt->format($now);  // Format timestamp in Spanish (e.g., "9 de septiembre de 2024, 14:30")

            // Prepare the new note with a timestamp
            $notas_completa = $notas_existente . "\n[" . $timestamp . "]: " . $notas_nueva;

            $stmt_fetch->close();

            // Prepare an UPDATE statement to add the new note with the formatted timestamp
            $sql_update = 'UPDATE transferencias SET notas = ? WHERE id = ?';
            $stmt_update = $conn->prepare($sql_update);
            if ($stmt_update === false) {
                throw new Exception($conn->error);
            }

            // Bind the parameters (updated notes, solicitud_id)
            $stmt_update->bind_param('si', $notas_completa, $solicitud_id);
            $stmt_update->execute();

            $stmt_update->close();
            echo "Notas actualizadas con éxito.";
        } catch (Exception $e) {
            echo "Error: " . $e->getMessage();
        }
    } else {
        echo "Por favor, ingrese notas válidas.";
    }
}

try {
    // Query to retrieve transfer details

    $sql = 'SELECT t.id, t.folio, t.observaciones, t.notas, t.descripcion, t.no_cuenta, t.fecha_solicitud, s.sucursal AS sucursal,
                   b.beneficiario AS beneficiario, t.fecha_solicitud, t.importe, t.departamento_id, d.id AS departamento_id, d.departamento AS nombre_departamento,
                   t.descripcion, t.estado, t.documento_adjunto, t.usuario_id, t.importedls, u.nombre, t.categoria_id AS nombre_categoria, c.id AS id_categoria,
                   c.categoria AS nombre_categoria
            FROM transferencias t
            JOIN categorias c ON t.categoria_id = c.id
            JOIN departamentos d ON t.departamento_id = d.id
            JOIN usuarios u ON t.usuario_id = u.id
            JOIN sucursales s ON t.sucursal_id = s.id
            JOIN beneficiarios b ON t.beneficiario_id = b.id
            WHERE t.id = ?';

    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        throw new Exception($conn->error);
    }

    $stmt->bind_param('i', $solicitud_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $solicitud = $result->fetch_assoc();

   
    

    if (!$solicitud) {
        echo "No se encontró la solicitud o no tienes permiso para verla.";
        exit();
    }

    

    $stmt->close();
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
    exit();
}

$fmt = new IntlDateFormatter('es_ES', IntlDateFormatter::LONG, IntlDateFormatter::NONE);
$fecha = new DateTime($solicitud['fecha_solicitud']);
$fecha_formateada = $fmt->format($fecha);

?>
<?php

?>

<style>
.container {
    max-width: 1600px;
}
h2.section-title {
    color: #2c3e50;
    border-bottom: 2px solid #3498db;
    padding-bottom: 8px;
    margin-bottom: 20px;
}
td {
    vertical-align: middle;
}
.table th {
    background-color: #f8f9fa;
    color: #2c3e50;
}
.card {
    box-shadow: 0 0 15px rgba(0,0,0,0.05);
}
.card-header {
    background-color: #f0f8ff;
    font-weight: bold;
}
</style>

<div class="container">
    <?php if (isset($GLOBALS["mensaje_global"])) echo $GLOBALS["mensaje_global"]; ?>
    <div class="row">
        <div class="col-md-6 mx-auto">
            <h2 class="section-title"><i class="fas fa-file-invoice-dollar"></i> Detalle de Transferencia</h2>
            <div class="card mb-4">
                <div class="card-body">
                    <table class="table table-bordered table-hover">
                        <tbody>
                            <tr><th>Folio</th><td class="text-danger fw-bold"><?= htmlspecialchars($solicitud['folio']) ?></td></tr>
                            <tr><th>Fecha</th><td><?= htmlspecialchars($fecha_formateada) ?></td></tr>
                            <tr><th>Solicita</th><td><?= htmlspecialchars($solicitud['nombre']) ?></td></tr>
                            <tr><th>Sucursal</th><td><?= htmlspecialchars($solicitud['sucursal']) ?></td></tr>
                            <tr><th>Beneficiario</th><td><?= htmlspecialchars($solicitud['beneficiario']) ?></td></tr>
                            <tr>
                                <th>Importe</th>
                                <td>
                                    <?php if (!empty($solicitud['importe'])): ?>
                                        $<?= number_format($solicitud['importe'], 2, ".", ",") ?>
                                    <?php else: ?>
                                        <?= htmlspecialchars($solicitud['importedls']) ?> DLS
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr><th>Descripción</th><td><?= htmlspecialchars($solicitud['descripcion']) ?></td></tr>
                            <tr><th>Observaciones</th><td><?= !empty($solicitud['observaciones']) ? htmlspecialchars($solicitud['observaciones']) : 'N/A' ?></td></tr>
                            <tr><th>No. de Cuenta</th><td><?= !empty($solicitud['no_cuenta']) ? htmlspecialchars($solicitud['no_cuenta']) : 'N/A' ?></td></tr>
                            <tr><th>Departamento</th><td><?= htmlspecialchars($solicitud['nombre_departamento']) ?></td></tr>
                            <tr><th>Categoría</th><td><?= htmlspecialchars_decode($solicitud['nombre_categoria']) ?></td></tr>
                            <tr><th>Estado</th><td><?= htmlspecialchars($solicitud['estado']) ?></td></tr>
                            <tr>
                                <th>Agregar Nota</th>
                                <td>
                                    <form action="" method="POST">
                                        <textarea name="notas" class="form-control" rows="3" placeholder="Escribe tu nota aquí..."></textarea>
                                        <button type="submit" class="btn btn-success mt-2">
                                            <i class="fas fa-save me-1"></i>Guardar Nota
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            <tr>
                                <th>Notas</th>
                                <td>
                                    <div class="border rounded p-2 bg-light" style="max-height: 300px; overflow-y: auto;">
                                        <?php
                                        $notes = array_reverse(explode("\n", $solicitud['notas']));
                                        $hayNotas = false;
                                        foreach ($notes as $note) {
                                            if (trim($note) !== "") {
                                                echo '<div class="mb-2 p-2 border-start border-4 border-secondary bg-white rounded">';
                                                echo '<span class="text-muted small">' . htmlspecialchars($note) . '</span>';
                                                echo '</div>';
                                                $hayNotas = true;
                                            }
                                        }
                                        if (!$hayNotas) {
                                            echo '<p class="text-muted">No hay notas registradas aún.</p>';
                                        }
                                        ?>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <div class="d-flex gap-2 mt-3">
                        <a href="TR_pendiente_pago.php" class="btn btn-secondary">Volver</a>
                        <!-- Puedes agregar más botones aquí si lo deseas -->
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>


<?php
include("src/templates/adminfooter.php");
?>
