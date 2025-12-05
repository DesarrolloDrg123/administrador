<?php

session_start();
header('Content-Type: text/html; charset=utf-8');
include("src/templates/adminheader.php");
require("config/db.php");

if (!isset($_SESSION['loggedin']) || !$_SESSION['loggedin']) {
    header("Location: index.php");
    exit();
}

if (!isset($_GET['id'])) {
    echo "Id no proporcionado.";
    exit();
}

$solicitud_id = $_GET['id'];

$CTParam = isset($_GET['CT']) ? $_GET['CT'] : null;
$TTParam = isset($_GET['TT']) ? $_GET['TT'] : null;
$PTParam = isset($_GET['PT']) ? $_GET['PT'] : null;

try {
    // PASO 1: Obtener el folio usando el ID inicial
    $stmt_folio = $conn->prepare("SELECT folio FROM transferencias_clara_tcl WHERE id = ?");
    if (!$stmt_folio) throw new Exception($conn->error);
    $stmt_folio->bind_param('i', $solicitud_id);
    $stmt_folio->execute();
    $result_folio = $stmt_folio->get_result();
    if ($result_folio->num_rows === 0) {
        throw new Exception("No se encontró la solicitud.");
    }
    $folio_a_buscar = $result_folio->fetch_assoc()['folio'];
    $stmt_folio->close();

    // PASO 2: Obtener TODOS los registros que comparten ese folio
    $sql = 'SELECT t.id, t.folio, s.sucursal AS sucursal,  b.nombre AS nombre_beneficiario, t.fecha_solicitud, t.fecha_vencimiento, t.importe,t.importe_letra, t.importedls, t.importedls_letra, t.descripcion, t.estado, t.documento_adjunto, t.no_cuenta,
    t.observaciones, t.categoria_id, t.departamento_id, t.usuario_solicitante_id, t.autorizacion_id AS autoriza, t.motivo, u1.nombre AS nombre_usuario, u2.nombre AS nombre_autoriza, d.departamento, c.categoria
    FROM transferencias_clara_tcl t 
    JOIN categorias c ON t.categoria_id = c.id
    JOIN departamentos d ON t.departamento_id = d.id
    JOIN usuarios u1 ON t.usuario_solicitante_id = u1.id
    JOIN sucursales s ON t.sucursal_id = s.id
    JOIN usuarios b ON t.beneficiario_id = b.id
    JOIN usuarios u2 ON t.autorizacion_id = u2.id
    WHERE t.folio = ?';
    
    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        throw new Exception($conn->error);
    }
    $stmt->bind_param('s', $folio_a_buscar);
    $stmt->execute();
    $result = $stmt->get_result();
    
    // Guardamos todos los resultados en un array
    $transferencias_del_folio = $result->fetch_all(MYSQLI_ASSOC);

    if (empty($transferencias_del_folio)) {
        echo "No se encontró la solicitud o no tienes permiso para verla.";
        exit();
    }
    
    // Usamos la primera fila para los datos comunes
    $solicitud = $transferencias_del_folio[0];
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
    exit();
}

// Extraer la fecha de la base de datos
$fecha = new DateTime($solicitud['fecha_solicitud']);
$fecha1 = new DateTime($solicitud['fecha_vencimiento']);

// Meses en español abreviados
$meses_espanol = ['ENE', 'FEB', 'MAR', 'ABR', 'MAY', 'JUN', 'JUL', 'AGO', 'SEP', 'OCT', 'NOV', 'DIC'];

// Obtener el día, el mes (como índice) y el año
$dia = $fecha->format('j');
$mes = $meses_espanol[(int)$fecha->format('n') - 1];
$año = $fecha->format('Y');

// Obtener el día, el mes (como índice) y el año
$dia1 = $fecha1->format('j');
$mes1 = $meses_espanol[(int)$fecha1->format('n') - 1];
$año1 = $fecha1->format('Y');

// Concatenar en el formato deseado
$fecha_formateada = "{$dia}/{$mes}/{$año}";
$fecha_formateada1 = "{$dia1}/{$mes1}/{$año1}";

$folio = $solicitud['folio'];

$es_corporativo = count($transferencias_del_folio) > 1;
$importe_total_pesos = 0;
$importe_total_dolares = 0;

foreach ($transferencias_del_folio as $trans) {
    $importe_total_pesos += floatval($trans['importe']);
    $importe_total_dolares += floatval($trans['importedls']);
}


// Consulta para obtener todos los registros de las facturas
$sql_facturas = "SELECT * FROM facturas_tcl WHERE NO_ORDEN_COMPRA = ?";
$stmt2 = $conn->prepare($sql_facturas);
$stmt2->bind_param('s', $folio);
$stmt2->execute();
$result_facturas = $stmt2->get_result();

// Consulta para obtener la suma de los totales de las facturas
$sql_total_facturas = "SELECT SUM(TOTAL) AS total_facturas FROM facturas_tcl WHERE NO_ORDEN_COMPRA = ?";
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

$importe_total_transferencia = ($importe_total_dolares > 0) ? $importe_total_dolares : $importe_total_pesos;
$pendiente = $importe_total_transferencia - $total_facturas;

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
        <!-- Columna de Detalle -->
        <div class="col-md-6 mx-auto">
            <h2 class="section-title"><i class="fas fa-file-invoice-dollar"></i> Detalle de Transferencia</h2>
            <div class="card mb-4">
                <div class="card-body">
                    <table class="table table-bordered table-hover">
                        <tbody>
                            <tr><th>Folio</th><td class="text-danger fw-bold"><?= htmlspecialchars($solicitud['folio']) ?></td></tr>
                            <tr><th>Sucursal</th><td><?= htmlspecialchars($solicitud['sucursal']) ?></td></tr>
                            <tr><th>Solicitante</th><td><?= htmlspecialchars($solicitud['nombre_usuario']) ?></td></tr>
                            <tr><th>Beneficiario</th><td><?= htmlspecialchars($solicitud['nombre_beneficiario']) ?></td></tr>
                            <tr><th>No. de Cuenta</th><td><?= !empty($solicitud['no_cuenta']) ? htmlspecialchars($solicitud['no_cuenta']) : 'N/A' ?></td></tr>
                            <tr><th>Departamento</th><td><?= htmlspecialchars($solicitud['departamento']) ?></td></tr>
                            <tr><th>Categoría</th><td><?= htmlspecialchars($solicitud['categoria']) ?></td></tr>
                            <tr><th>Fecha de Solicitud</th><td><?= htmlspecialchars($fecha_formateada) ?></td></tr>
                            <tr><th>Fecha de Vencimiento</th><td><?= htmlspecialchars($fecha_formateada1) ?></td></tr>
                            <tr><th>Descripción</th><td><?= htmlspecialchars($solicitud['descripcion']) ?></td></tr>
                            <tr><th>Observaciones</th><td><?= !empty($solicitud['observaciones']) ? htmlspecialchars($solicitud['observaciones']) : 'N/A' ?></td></tr>
                            <tr><th>Estado</th><td><?= htmlspecialchars($solicitud['estado']) ?></td></tr>
                            <?php if ($solicitud['estado'] == "Cancelada" || $solicitud['estado'] == "Rechazado"): ?>
                                <tr>
                                    <tr><th>Motivo</th>
                                    <td><?= htmlspecialchars($solicitud['motivo']) ?></td></tr>
                                </tr>
                            <?php endif; ?>
                            <tr><th>Autoriza</th><td><?= htmlspecialchars($solicitud['nombre_autoriza']) ?></td></tr>
                            <?php if (empty($solicitud['importe']) || $solicitud['importe'] == '0.00'): ?>
                                <tr><th>Importe en Dólares</th><td>US$<?= number_format($importe_total_dolares, 2, ".", ",") ?></td></tr>
                                <tr><th>Importe en Letra</th><td><?= htmlspecialchars($solicitud['importedls_letra']) ?></td></tr>
                            <?php else: ?>
                                <tr><th>Importe en Pesos</th><td>$<?= number_format($importe_total_pesos, 2, ".", ",") ?></td></tr>
                                <tr><th>Importe en Letra</th><td><?= htmlspecialchars($solicitud['importe_letra']) ?></td></tr>
                            <?php endif; ?>
                            <tr><th>Total de Facturas</th><td><?= $importe_factura = ($solicitud['estado'] != "Cancelada" && $solicitud['estado'] != "Rechazado") ? '$' . number_format($total_facturas, 2, ".", ",") : '$0.00'; ?></td></tr>
                            <tr><th>Pendiente por Subir</th><td><?= $importe_pendiente = ($solicitud['estado'] != "Cancelada" && $solicitud['estado'] != "Rechazado") ? '$' . number_format($pendiente, 2, ".", ",") : '$0.00'; ?></td></tr>
                            <?php if (!empty($solicitud['documento_adjunto'])): ?>
                                <tr>
                                    <th>Documento</th>
                                    <td><a href="<?= htmlspecialchars($solicitud['documento_adjunto']) ?>" target="_blank" class="btn btn-outline-primary btn-sm">Ver Documento</a></td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="d-flex gap-2">
                <?php if ($CTParam === 'true'): ?>
                    <a href="TCL_cancelar_transferencias.php" class="btn btn-secondary">Volver</a>
                <?php endif; ?>
                <?php if ($TTParam === 'true'): ?>
                    <a href="TCL_todas_transferencias.php" class="btn btn-secondary">Volver</a>
                <?php endif; ?>
                <?php if ($PTParam === 'true'): ?>
                    <a href="TCL_pendiente_pago.php" class="btn btn-secondary">Volver</a>
                <?php endif; ?>
            </div>
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
                                    <th>Descargar XML</th>
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