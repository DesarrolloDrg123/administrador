<?php

session_start();
header('Content-Type: text/html; charset=utf-8');
// Asegúrate de que las rutas a los includes sean correctas
include("src/templates/adminheader.php");
require("config/db.php");

// Configuración de reportes para MySQLi (buena práctica)
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

if (!isset($_SESSION['loggedin']) || !$_SESSION['loggedin']) {
    header("Location: index.php");
    exit();
}

if (!isset($_GET['id'])) {
    // Usar un mensaje más amigable o redirigir
    $_SESSION['error_message'] = "ID de solicitud no proporcionado.";
    header("Location: TCL_todas_transferencias.php");
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
        throw new Exception("No se encontró la solicitud con el ID proporcionado.");
    }
    
    $folio_a_buscar = $result_folio->fetch_assoc()['folio'];
    $stmt_folio->close();

    // PASO 2: Obtener TODOS los registros que comparten ese folio
    $sql = 'SELECT t.id, t.folio, s.sucursal AS sucursal, b.nombre AS nombre_beneficiario, t.fecha_solicitud, t.fecha_vencimiento, t.importe, t.importe_letra, t.importedls, t.importedls_letra, t.descripcion, t.estado, t.documento_adjunto, t.no_cuenta,
    t.observaciones, t.categoria_id, t.departamento_id, t.usuario_solicitante_id, t.autorizacion_id AS autoriza, t.motivo, u1.nombre AS nombre_usuario, u2.nombre AS nombre_autoriza, d.departamento, c.categoria
    FROM transferencias_clara_tcl t 
    JOIN categorias c ON t.categoria_id = c.id
    JOIN departamentos d ON t.departamento_id = d.id
    JOIN usuarios u1 ON t.usuario_solicitante_id = u1.id
    JOIN sucursales s ON t.sucursal_id = s.id
    JOIN usuarios b ON t.beneficiario_id = b.id
    LEFT JOIN usuarios u2 ON t.autorizacion_id = u2.id -- Usar LEFT JOIN ya que autorizacion_id podría ser NULL
    WHERE t.folio = ?';
    
    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        throw new Exception("Error en la preparación de la consulta: " . $conn->error);
    }
    $stmt->bind_param('s', $folio_a_buscar);
    $stmt->execute();
    $result = $stmt->get_result();
    
    // Guardamos todos los resultados en un array
    $transferencias_del_folio = $result->fetch_all(MYSQLI_ASSOC);

    if (empty($transferencias_del_folio)) {
        throw new Exception("No se encontró la solicitud o no tienes permiso para verla.");
    }
    
    // Usamos la primera fila para los datos comunes
    $solicitud = $transferencias_del_folio[0];
    $stmt->close();
} catch (Exception $e) {
    echo '<div class="alert alert-danger container mt-5" role="alert">Error al cargar la solicitud: ' . $e->getMessage() . '</div>';
    include("src/templates/adminfooter.php");
    exit();
}

// --- Lógica de cálculo y formato ---

// Extracción de fechas y formato
$fecha = new DateTime($solicitud['fecha_solicitud']);
$fecha1 = new DateTime($solicitud['fecha_vencimiento']);

// Meses en español abreviados
$meses_espanol = ['ENE', 'FEB', 'MAR', 'ABR', 'MAY', 'JUN', 'JUL', 'AGO', 'SEP', 'OCT', 'NOV', 'DIC'];

// Obtener el día, el mes (como índice) y el año
$dia = $fecha->format('j');
$mes = $meses_espanol[(int)$fecha->format('n') - 1];
$año = $fecha->format('Y');

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
    // Asegurar que los valores son numéricos para la suma
    $importe_total_pesos += floatval($trans['importe']);
    $importe_total_dolares += floatval($trans['importedls']);
}

// Determinar si la transferencia es en USD o MXN para el importe principal (usando el total)
$es_dolares = $importe_total_dolares > 0;
$importe_total_transferencia = $es_dolares ? $importe_total_dolares : $importe_total_pesos;

// Determinar el beneficiario para la tabla de detalles (si es un folio multi-ítem)
$beneficiarios_unicos = array_unique(array_column($transferencias_del_folio, 'nombre_beneficiario'));
$beneficiario_display = count($beneficiarios_unicos) === 1 ? $beneficiarios_unicos[0] : 'Varios Beneficiarios';


// Consulta para obtener la suma de los totales de las facturas
$sql_total_facturas = "SELECT SUM(TOTAL) AS total_facturas FROM facturas_tcl WHERE NO_ORDEN_COMPRA = ?";
$stmt3 = $conn->prepare($sql_total_facturas);
$stmt3->bind_param('s', $folio);
$stmt3->execute();
$result_total_facturas = $stmt3->get_result();

// Obtener el total de las facturas
$row_total_facturas = $result_total_facturas->fetch_assoc();
$total_facturas = $row_total_facturas['total_facturas'] ?? 0; // Si no hay resultados, asigna 0
$stmt3->close();

// Consulta para obtener todos los registros de las facturas
$sql_facturas = "SELECT * FROM facturas_tcl WHERE NO_ORDEN_COMPRA = ?";
$stmt2 = $conn->prepare($sql_facturas);
$stmt2->bind_param('s', $folio);
$stmt2->execute();
$result_facturas = $stmt2->get_result();
$stmt2->close();


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
                            <!-- CAMBIO: Muestra 'Varios Beneficiarios' si aplica -->
                            <tr><th>Beneficiario</th><td><?= htmlspecialchars($beneficiario_display) ?></td></tr>
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
                                    <th>Motivo</th>
                                    <td><?= htmlspecialchars($solicitud['motivo']) ?></td>
                                </tr>
                            <?php endif; ?>
                            <tr><th>Autoriza</th><td><?= htmlspecialchars($solicitud['nombre_autoriza'] ?? 'N/A') ?></td></tr>
                            <?php if ($es_dolares): ?>
                                <tr><th>Importe Total en Dólares</th><td>US$<?= number_format($importe_total_dolares, 2, ".", ",") ?></td></tr>
                                <tr><th>Importe en Letra</th><td><?= htmlspecialchars($solicitud['importedls_letra']) ?></td></tr>
                            <?php else: ?>
                                <tr><th>Importe Total en Pesos</th><td>$<?= number_format($importe_total_pesos, 2, ".", ",") ?></td></tr>
                                <tr><th>Importe en Letra</th><td><?= htmlspecialchars($solicitud['importe_letra']) ?></td></tr>
                            <?php endif; ?>
                            
                            <?php 
                            $estado_valido = ($solicitud['estado'] != "Cancelada" && $solicitud['estado'] != "Rechazado");
                            $importe_factura = $estado_valido ? '$' . number_format($total_facturas, 2, ".", ",") : '$0.00';
                            $importe_pendiente = $estado_valido ? '$' . number_format($pendiente, 2, ".", ",") : '$0.00';
                            ?>
                            
                            <tr><th>Total de Facturas</th><td><?= $importe_factura ?></td></tr>
                            <tr><th>Pendiente por Subir</th><td><?= $importe_pendiente ?></td></tr>
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
            <div class="d-flex gap-2 mb-4">
                <?php if ($CTParam === 'true'): ?>
                    <a href="TCL_cancelar_transferencias.php" class="btn btn-secondary">Volver</a>
                <?php endif; ?>
                <?php if ($TTParam === 'true'): ?>
                    <a href="TCL_todas_transferencias.php" class="btn btn-secondary">Volver</a>
                <?php endif; ?>
                <?php if ($PTParam === 'true'): ?>
                    <a href="TCL_pendiente_pago.php" class="btn btn-secondary">Volver</a>
                <?php endif; ?>
                <?php if ($CTParam !== 'true' && $TTParam !== 'true' && $PTParam !== 'true'): ?>
                    <!-- Fallback o botón por defecto si no viene de una lista específica -->
                    <a href="javascript:history.back()" class="btn btn-secondary">Volver</a>
                <?php endif; ?>
            </div>
        </div>

        <!-- CAMBIO: Columna de Detalle de Ítems (Solo si es corporativo) -->
        <?php if ($es_corporativo): ?>
            <div class="col-md-6">
                <h2 class="section-title"><i class="fas fa-list-ul"></i> Ítems de la Transferencia (Corporativo)</h2>
                <div class="card mb-4">
                    <div class="card-body">
                        <table class="table table-sm table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>Beneficiario</th>
                                    <th>Importe</th>
                                    <th>Descripción</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($transferencias_del_folio as $trans): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($trans['nombre_beneficiario']) ?></td>
                                        <td>
                                            <?php if (floatval($trans['importedls']) > 0): ?>
                                                US$<?= number_format(floatval($trans['importedls']), 2, ".", ",") ?>
                                            <?php else: ?>
                                                $<?= number_format(floatval($trans['importe']), 2, ".", ",") ?>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= htmlspecialchars($trans['descripcion']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php endif; ?>


    </div>

    <!-- Columna de Facturas - Se mueve abajo si solo hay una columna de detalle (no corporativo) -->
    <div class="row">
        <?php if ($result_facturas->num_rows > 0): ?>
            <div class="col-md-12">
                <h2 class="section-title"><i class="fas fa-receipt"></i> Facturas Asociadas (Total: <?= '$' . number_format($total_facturas, 2, ".", ",") ?>)</h2>
                <div class="card">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-sm table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>Fecha</th>
                                        <th>RFC</th>
                                        <th>UUID</th>
                                        <th>Total Factura</th>
                                        <th>Ver PDF</th>
                                        <th>Descargar XML</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    // Crear el formateador una sola vez
                                    $fmt = new IntlDateFormatter('es_ES', IntlDateFormatter::LONG, IntlDateFormatter::NONE);
                                    $result_facturas->data_seek(0); // Volver al inicio para iterar
                                    while ($row_factura = $result_facturas->fetch_assoc()):
                                        $fecha_factura = new DateTime($row_factura['FECHA_FACTURA']);
                                        $fecha_factura_formateada = $fmt->format($fecha_factura);
                                    ?>
                                        <tr>
                                            <td><?= htmlspecialchars($fecha_factura_formateada) ?></td>
                                            <td><?= htmlspecialchars($row_factura['RFC_EMISOR']) ?></td>
                                            <td><?= htmlspecialchars($row_factura['UUID']) ?></td>
                                            <td>$<?= number_format($row_factura['TOTAL'], 2, ".", ",") ?></td>
                                            <td><a href="view_pdf.php?RFC=<?= urlencode($row_factura["RFC_EMISOR"]) ?>&UUID=<?= urlencode($row_factura["UUID"]) ?>" target="_blank" class="text-primary"><i class="fas fa-file-invoice fa-2x"></i></a></td>
                                            <td><a href="download_zip.php?RFC=<?= urlencode($row_factura["RFC_EMISOR"]) ?>&UUID=<?= urlencode($row_factura["UUID"]) ?>" target="_blank" class="text-success"><i class="fas fa-file-archive fa-2x"></i></a></td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>


<?php
include("src/templates/adminfooter.php");
?>