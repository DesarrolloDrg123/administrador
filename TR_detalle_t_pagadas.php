<?php
session_start();

require("config/db.php");
include("src/templates/adminheader.php");

if (!isset($_SESSION['loggedin']) || !$_SESSION['loggedin']) {
    header("Location: index.php");
    exit();
}

$solicitud_folio = $_GET['folio'];

$TPParam = isset($_GET['TP']) ? $_GET['TP'] : null;


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
            $sql_fetch = 'SELECT notas FROM transferencias WHERE folio = ?';
            $stmt_fetch = $conn->prepare($sql_fetch);
            if ($stmt_fetch === false) {
                throw new Exception($conn->error);
            }

            $stmt_fetch->bind_param('i', $solicitud_folio);
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

            // Bind the parameters (updated notes, solicitud_folio)
            $stmt_update->bind_param('si', $notas_completa, $solicitud_folio);
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
                   b.beneficiario AS beneficiario, t.fecha_solicitud, t.importe, t.importe_letra, t.importedls, t.importedls_letra, t.departamento_id, d.id AS departamento_id, d.departamento AS nombre_departamento,
                   t.descripcion, t.estado, t.documento_adjunto, t.usuario_id, t.importedls, u.nombre, t.categoria_id AS nombre_categoria, c.id AS id_categoria,
                   c.categoria AS nombre_categoria
            FROM transferencias t
            JOIN categorias c ON t.categoria_id = c.id
            JOIN departamentos d ON t.departamento_id = d.id
            JOIN usuarios u ON t.usuario_id = u.id
            JOIN sucursales s ON t.sucursal_id = s.id
            JOIN beneficiarios b ON t.beneficiario_id = b.id
            WHERE t.folio = ?';

    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        throw new Exception($conn->error);
    }

    $stmt->bind_param('i', $solicitud_folio);
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
$folio = $solicitud['folio'];

// Consulta para obtener todos los registros de las facturas
$sql_facturas = "SELECT * FROM facturas WHERE NO_ORDEN_COMPRA = ?";
$stmt2 = $conn->prepare($sql_facturas);
$stmt2->bind_param('s', $folio);
$stmt2->execute();
$result_facturas = $stmt2->get_result();

// Consulta para obtener la suma de los totales de las facturas
$sql_total_facturas = "SELECT SUM(TOTAL) AS total_facturas FROM facturas WHERE NO_ORDEN_COMPRA = ?";
$stmt3 = $conn->prepare($sql_total_facturas);
$stmt3->bind_param('s', $folio);
$stmt3->execute();
$result_total_facturas = $stmt3->get_result();

// Obtener el total de las facturas
$row_total_facturas = $result_total_facturas->fetch_assoc();
$total_facturas = $row_total_facturas['total_facturas'] ?? 0; // Si no hay resultados, asigna 0

if ($solicitud['importe'] == '0.00' || $solicitud['importe'] == null || $solicitud['importe'] == '') {
    $importe_transferencia = $solicitud['importedls'];
} else {
    $importe_transferencia = $solicitud['importe'];
}

$pendiente = $importe_transferencia - $total_facturas;

?>

<style>
.container {
    max-width: 1400px;
    margin-top: 50px;
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


<div class="container mt-5">
    <div class="row">
        <!-- Columna de Detalle de Transferencia -->
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
                            <?php if (empty($solicitud['importe']) || $solicitud['importe'] == '0.00'): ?>
                                <tr><th>Importe en Dólares</th><td>US$<?= number_format($solicitud['importedls'], 2, ".", ",") ?></td></tr>
                                <tr><th>Importe en Letra</th><td><?= htmlspecialchars($solicitud['importedls_letra']) ?></td></tr>
                            <?php else: ?>
                                <tr><th>Importe en Pesos</th><td>$<?= number_format($solicitud['importe'], 2, ".", ",") ?></td></tr>
                                <tr><th>Importe en Letra</th><td><?= htmlspecialchars($solicitud['importe_letra']) ?></td></tr>
                            <?php endif; ?>
                            <tr><th>Total de Facturas</th><td>$<?= number_format($total_facturas, 2, ".", ",") ?></td></tr>
                            <tr><th>Pendiente por Subir</th><td>$<?= number_format($pendiente, 2, ".", ",") ?></td></tr>
                            <tr><th>Descripción</th><td><?= htmlspecialchars($solicitud['descripcion']) ?></td></tr>
                            <tr><th>Observaciones</th><td><?= !empty($solicitud['observaciones']) ? htmlspecialchars($solicitud['observaciones']) : 'N/A' ?></td></tr>
                            <tr><th>No. de Cuenta</th><td><?= !empty($solicitud['no_cuenta']) ? htmlspecialchars($solicitud['no_cuenta']) : 'N/A' ?></td></tr>
                            <tr><th>Departamento</th><td><?= htmlspecialchars($solicitud['nombre_departamento']) ?></td></tr>
                            <tr><th>Categoría</th><td><?= htmlspecialchars_decode($solicitud['nombre_categoria']) ?></td></tr>
                            <tr><th>Estado</th><td><?= htmlspecialchars($solicitud['estado']) ?></td></tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Notas -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-sticky-note"></i> Agregar Nota</h5>
                </div>
                <div class="card-body">
                    <form action="" method="POST">
                        <textarea id="notas" name="notas" class="form-control mb-2" rows="3" placeholder="Escribe tu nota aquí..."></textarea>
                        <button type="submit" class="btn btn-primary">Guardar Notas</button>
                    </form>
                </div>
            </div>

            <!-- Notas guardadas -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-comments"></i> Notas</h5>
                </div>
                <div class="card-body" style="max-height: 300px; overflow-y: auto;">
                    <?php
                    $notes = array_reverse(explode("\n", $solicitud['notas']));
                    foreach ($notes as $note) {
                        if (trim($note) !== "") {
                            echo '<div class="note-entry mb-2 p-2 border rounded bg-light">';
                            echo '<span class="text-muted">' . htmlspecialchars($note) . '</span>';
                            echo '</div>';
                        }
                    }
                    ?>
                </div>
            </div>

            <!-- Botón Volver -->
            <?php if ($TPParam === 'true') : ?>
                <a href="TR_transferencias_pagadas.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Volver</a>
            <?php endif; ?>
        </div>

        <!-- Columna de Facturas -->
        <?php if ($result_facturas->num_rows > 0): ?>
        <div class="col-md-6">
            <h2 class="section-title"><i class="fas fa-receipt"></i> Facturas</h2>
            <div class="card">
                <div class="card-body">
                    <table class="table table-sm table-striped table-hover">
                        <thead>
                            <tr>
                                <th>Fecha</th>
                                <th>RFC</th>
                                <th>Total</th>
                                <th>UUID</th>
                                <th>Ver</th>
                                <th>Descargar</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $fmt = new IntlDateFormatter('es_ES', IntlDateFormatter::LONG, IntlDateFormatter::NONE);
                            while ($row_factura = $result_facturas->fetch_assoc()):
                                $fecha_factura = new DateTime($row_factura['FECHA_FACTURA']);
                                $fecha_factura_formateada = $fmt->format($fecha_factura);
                            ?>
                            <tr>
                                <td><?= htmlspecialchars($fecha_factura_formateada) ?></td>
                                <td><?= htmlspecialchars($row_factura['RFC_EMISOR']) ?></td>
                                <td>$<?= number_format($row_factura['TOTAL'], 2, ".", ",") ?></td>
                                <td><?= htmlspecialchars($row_factura['UUID']) ?></td>
                                <td><a href="view_pdf.php?RFC=<?= $row_factura["RFC_EMISOR"] ?>&UUID=<?= $row_factura["UUID"] ?>" target="_blank"><i class="fas fa-file-invoice fa-2x"></i></a></td>
                                <td><a href="download_zip.php?RFC=<?= $row_factura["RFC_EMISOR"] ?>&UUID=<?= $row_factura["UUID"] ?>" target="_blank"><i class="fas fa-file-archive fa-2x"></i></a></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php
include("src/templates/adminfooter.php");
?>