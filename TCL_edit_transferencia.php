<?php

session_start();
header('Content-Type: text/html; charset=utf-8');
// Se asume que estos archivos existen y manejan la conexión ($conn)
include("src/templates/adminheader.php");
require("config/db.php");


// -----------------------------------------------------------
// 1. Verificación de Sesión y Parámetros
// -----------------------------------------------------------

if (!isset($_SESSION['loggedin']) || !$_SESSION['loggedin']) {
    header("Location: index.php");
    exit();
}

$usuario_ses = $_SESSION['nombre'];
$usuario_id = $_SESSION['usuario_id'];

if (!isset($_GET['id'])) {
    // Manejo de error si no se proporciona el ID
    echo "ID de solicitud no proporcionado.";
    exit();
}

$solicitud_id = $_GET['id'];
$MTParam = isset($_GET['MT']) ? $_GET['MT'] : null; // Mis Transferencias
$ATParam = isset($_GET['AT']) ? $_GET['AT'] : null; // Por Autorizar

// Parámetros de retorno
$CTParam = isset($_GET['CT']) ? $_GET['CT'] : null;
$TTParam = isset($_GET['TT']) ? $_GET['TT'] : null;
$PTParam = isset($_GET['PT']) ? $_GET['PT'] : null;


// -----------------------------------------------------------
// 2. Consulta Principal de la Solicitud
// -----------------------------------------------------------

try {
    // Consulta optimizada para seleccionar todos los detalles necesarios de la transferencia
    $sql = 'SELECT t.id, t.folio, s.sucursal AS sucursal, b.nombre AS nombre_beneficiario, t.beneficiario_id, t.fecha_solicitud, t.fecha_vencimiento, t.importe, t.importe_letra, t.importedls, t.importedls_letra, t.descripcion, t.estado, t.documento_adjunto, t.no_cuenta,
    t.observaciones, t.categoria_id, t.departamento_id, t.usuario_solicitante_id, t.autorizacion_id AS autoriza, t.motivo, u1.nombre AS nombre_usuario, u2.nombre AS nombre_autoriza, d.departamento, c.categoria
    FROM transferencias_clara_tcl t 
    JOIN categorias c ON t.categoria_id = c.id
    JOIN departamentos d ON t.departamento_id = d.id
    JOIN usuarios u1 ON t.usuario_solicitante_id = u1.id
    JOIN usuarios b ON t.beneficiario_id = b.id
    JOIN sucursales s ON t.sucursal_id = s.id
    LEFT JOIN usuarios u2 ON t.autorizacion_id = u2.id -- LEFT JOIN por si aún no está autorizada/asignada
    WHERE t.id = ?';

    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        throw new Exception($conn->error);
    }

    $stmt->bind_param('i', $solicitud_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $solicitud = $result->fetch_assoc();
    $stmt->close(); // Cerrar el statement principal

    if (!$solicitud) {
        echo "No se encontró la solicitud o no tienes permiso para verla.";
        exit();
    }
} catch (Exception $e) {
    echo "Error en la consulta: " . $e->getMessage();
    exit();
}

// -----------------------------------------------------------
// 3. Formateo de Fechas
// -----------------------------------------------------------

$fecha = new DateTime($solicitud['fecha_solicitud']);
$fecha1 = new DateTime($solicitud['fecha_vencimiento']);

// Meses en español abreviados
$meses_espanol = ['ENE', 'FEB', 'MAR', 'ABR', 'MAY', 'JUN', 'JUL', 'AGO', 'SEP', 'OCT', 'NOV', 'DIC'];

// Fecha de Solicitud
$dia = $fecha->format('j');
$mes = $meses_espanol[(int)$fecha->format('n') - 1];
$anio = $fecha->format('Y');
$fecha_formateada = "{$dia}/{$mes}/{$anio}";

// Fecha de Vencimiento
$dia1 = $fecha1->format('j');
$mes1 = $meses_espanol[(int)$fecha1->format('n') - 1];
$anio1 = $fecha1->format('Y');
$fecha_formateada1 = "{$dia1}/{$mes1}/{$anio1}";


// -----------------------------------------------------------
// 4. Consulta y Cálculo de Totales y Pendiente
// -----------------------------------------------------------

$folio = $solicitud['folio'];

// A. Obtener todas las facturas
$sql_facturas = "SELECT ID, UUID, RFC_EMISOR, TOTAL, FECHA_FACTURA FROM facturas_tcl WHERE NO_ORDEN_COMPRA = ?";
$stmt_facturas = $conn->prepare($sql_facturas);
$stmt_facturas->bind_param('s', $folio);
$stmt_facturas->execute();
$result_facturas = $stmt_facturas->get_result();
$facturas_array = $result_facturas->fetch_all(MYSQLI_ASSOC);
$stmt_facturas->close();

// B. Suma de los totales de las facturas
$sql_total_facturas = "SELECT SUM(TOTAL) AS total_facturas FROM facturas_tcl WHERE NO_ORDEN_COMPRA = ?";
$stmt_total_facturas = $conn->prepare($sql_total_facturas);
$stmt_total_facturas->bind_param('s', $folio);
$stmt_total_facturas->execute();
$row_total_facturas = $stmt_total_facturas->get_result()->fetch_assoc();
$total_facturas = $row_total_facturas['total_facturas'] ?? 0;
$stmt_total_facturas->close();

// Determinar el importe base de la transferencia (consolidado)
if (empty($solicitud['importe']) || $solicitud['importe'] == '0.00') {
    $importe_transferencia = (float)$solicitud['importedls'];
    $moneda_simbolo = 'US$';
} else {
    $importe_transferencia = (float)$solicitud['importe'];
    $moneda_simbolo = '$';
}

// C. Suma de los totales de los comprobantes (recibos)
$sql_total_comprobantes = "SELECT SUM(importe) AS total_comprobantes FROM comprobantes_tcl WHERE folio = ?";
$stmt_comp_total = $conn->prepare($sql_total_comprobantes);
$stmt_comp_total->bind_param('s', $folio);
$stmt_comp_total->execute();
$row_total_comprobantes = $stmt_comp_total->get_result()->fetch_assoc();
$total_comprobantes = $row_total_comprobantes['total_comprobantes'] ?? 0;
$stmt_comp_total->close();

// D. Calcular el total COMPROBADO (Facturas + Comprobantes)
$total_comprobado = (float)$total_facturas + (float)$total_comprobantes;

// E. CÁLCULO DEL PENDIENTE
$pendiente = $importe_transferencia - $total_comprobado;

// F. Consulta para obtener los comprobantes detallados (para la tabla)
$sql_comprobantes = "SELECT id, importe, descripcion, evidencia FROM comprobantes_tcl WHERE folio = ?";
$stmt_comp = $conn->prepare($sql_comprobantes);
if ($stmt_comp) {
    $stmt_comp->bind_param('s', $folio);
    $stmt_comp->execute();
    $result_comprobantes = $stmt_comp->get_result();
    $comprobantes_array = $result_comprobantes->fetch_all(MYSQLI_ASSOC);
    $stmt_comp->close();
} else {
    $comprobantes_array = [];
    error_log("Error al preparar la consulta de comprobantes: " . $conn->error);
}

// Función para formatear moneda
function format_currency($amount, $symbol = '$') {
    $final_symbol = (strpos($symbol, 'US$') !== false) ? 'US$' : '$';
    return $final_symbol . number_format((float)$amount, 2, ".", ",");
}

?>

<style>
/* Estilos Bootstrap mejorados */
.container { max-width: 1600px; }
h2.section-title {
    color: #17202a;
    border-bottom: 2px solid #3498db;
    padding-bottom: 8px;
    margin-bottom: 20px;
    font-weight: 600;
}
.table th {
    background-color: #eaf2f8;
    color: #2c3e50;
}
.card {
    box-shadow: 0 0 15px rgba(0,0,0,0.05);
}
.card-header {
    background-color: #f0f8ff;
    font-weight: bold;
}
.text-danger-custom { color: #e74c3c; font-weight: bold; }

/* Estilo para UUID más pequeño */
.table-facturas td:nth-child(4) {
    font-size: 0.8rem;
    word-break: break-all;
}
.action-icon {
    font-size: 1.2rem;
}
</style>

<div class="container mt-4">
    <div class="row">
        <div class="col-md-6">
            <h2 class="section-title"><i class="fas fa-file-invoice-dollar"></i> Detalle de Transferencia</h2>
            <div class="card mb-4">
                <div class="card-body">
                    <table class="table table-striped table-hover table-sm">
                        <tbody>
                            <tr><th>Folio</th><td class="text-danger-custom"><?= htmlspecialchars($solicitud['folio']) ?></td></tr>
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
                                <tr><th>Motivo</th><td><?= htmlspecialchars($solicitud['motivo']) ?></td></tr>
                            <?php endif; ?>
                            <tr><th>Autoriza</th><td><?= htmlspecialchars($solicitud['nombre_autoriza'] ?? 'Pendiente') ?></td></tr>
                            
                            <?php if ($moneda_simbolo == 'US$'): ?>
                                <tr><th>Importe en Dólares</th><td><?= format_currency($solicitud['importedls'], 'US$') ?></td></tr>
                                <tr><th>Importe en Letra</th><td><?= htmlspecialchars($solicitud['importedls_letra']) ?></td></tr>
                            <?php else: ?>
                                <tr><th>Importe en Pesos</th><td><?= format_currency($solicitud['importe'], '$') ?></td></tr>
                                <tr><th>Importe en Letra</th><td><?= htmlspecialchars($solicitud['importe_letra']) ?></td></tr>
                            <?php endif; ?>
                            
                            <tr><th class="table-info">Total de Facturas</th><td class="table-info"><?= format_currency($total_facturas, $moneda_simbolo) ?></td></tr>
                            <tr><th class="table-info">Total de Recibos/Comprobantes</th><td class="table-info"><?= format_currency($total_comprobantes, $moneda_simbolo) ?></td></tr>
                            <tr><th class="table-success fw-bold">Total Comprobado</th><td class="table-success fw-bold"><?= format_currency($total_comprobado, $moneda_simbolo) ?></td></tr>
                            <tr><th class="table-danger">Pendiente por Comprobar</th>
                                <td class="table-danger fw-bold">
                                    <?= ($solicitud['estado'] != "Cancelada" && $solicitud['estado'] != "Rechazado") ? format_currency($pendiente, $moneda_simbolo) : format_currency(0, $moneda_simbolo); ?>
                                </td>
                            </tr>
                            
                            <?php if (!empty($solicitud['documento_adjunto'])): ?>
                                <tr>
                                    <th>Documento Adjunto (Solicitud)</th>
                                    <td><a href="<?= htmlspecialchars($solicitud['documento_adjunto']) ?>" target="_blank" class="btn btn-outline-primary btn-sm"><i class="fas fa-file-pdf"></i> Ver Documento</a></td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>

                    <div class="d-flex gap-2 mt-3 justify-content-end">
                        
                        <?php if ($MTParam === 'true'): ?>
                            <a href="TCL_mis_transferencias.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Volver a Mis Transferencias</a>
                        <?php endif; ?>
                        <?php if ($ATParam === 'true'): ?>
                            <a href="TCL_por_autorizar.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Volver a Autorizaciones</a>
                        <?php endif; ?>
                        <?php if ($CTParam === 'true'): ?>
                            <a href="TCL_cancelar_transferencias.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Volver a Canceladas</a>
                        <?php endif; ?>
                        <?php if ($TTParam === 'true'): ?>
                            <a href="TCL_todas_transferencias.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Volver a Todas</a>
                        <?php endif; ?>
                        <?php if ($PTParam === 'true'): ?>
                            <a href="TCL_pendiente_pago.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Volver a Pendientes de Pago</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <?php if ($solicitud['estado'] != "Pendiente" && $solicitud['estado'] != "Rechazado" && $solicitud['estado'] != "Cancelada"): ?>
        <div class="col-md-6">

            <?php if (!empty($facturas_array)): ?>
            <h2 class="section-title"><i class="fas fa-receipt"></i> Facturas Subidas</h2>
            <div class="card mb-4">
                <div class="card-body p-0">
                    <table class="table table-sm table-striped table-hover table-facturas">
                        <thead>
                            <tr>
                                <th>Fecha</th>
                                <th>RFC</th>
                                <th>Total</th>
                                <th>UUID</th>
                                <th>Ver PDF</th>
                                <th>Descargar XML</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($facturas_array as $row_factura): 
                                // Formato de fecha para la tabla
                                $fecha_factura_display = isset($row_factura['FECHA_FACTURA']) 
                                    ? (new DateTime($row_factura['FECHA_FACTURA']))->format('Y-m-d') 
                                    : 'N/A';
                            ?>
                            <tr>
                                <td><?= htmlspecialchars($fecha_factura_display) ?></td>
                                <td><?= htmlspecialchars($row_factura['RFC_EMISOR'] ?? 'N/A') ?></td>
                                <td><?= format_currency($row_factura['TOTAL'], $moneda_simbolo) ?></td>
                                <td><?= htmlspecialchars($row_factura['UUID']) ?></td>
                                <!-- Descargar (Asumo enlace para descargar el PDF/XML. Usaremos el XML por defecto) -->
                                <td><a href="view_pdf.php?RFC=<?= $row_factura["RFC_EMISOR"] ?>&UUID=<?= $row_factura["UUID"] ?>" target="_blank"><i class="fas fa-file-invoice fa-2x"></i></a></td>
                                    <td><a href="download_zip.php?RFC=<?= $row_factura["RFC_EMISOR"] ?>&UUID=<?= $row_factura["UUID"] ?>" target="_blank"><i class="fas fa-file-archive fa-2x"></i></a></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>
            
            <hr class="my-4">
            
            <?php if (!empty($comprobantes_array)): ?>
            <h2 class="section-title"><i class="fas fa-paperclip"></i> Comprobantes / Recibos Subidos</h2>
            <div class="card mb-4">
                <div class="card-body p-0">
                    <table class="table table-sm table-striped table-hover">
                        <thead>
                            <tr>
                                <th>Importe</th>
                                <th>Descripción</th>
                                <th>Ver Archivo</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($comprobantes_array as $row_comprobante): ?>
                            <tr>
                                <td><?= format_currency($row_comprobante['importe'], $moneda_simbolo) ?></td>
                                <td><?= htmlspecialchars($row_comprobante['descripcion']) ?></td>
                                <td>
                                    <?php if (!empty($row_comprobante['evidencia'])): ?>
                                        <a href="<?= htmlspecialchars($row_comprobante['evidencia']) ?>" target="_blank" class="text-success" title="Ver Comprobante">
                                            <i class="fas fa-file-alt fa-2x"></i>
                                        </a>
                                    <?php else: ?>
                                        <span class="text-muted">N/A</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>

        </div>
        <?php endif; ?>
    </div>
</div>


<?php
include("src/templates/adminfooter.php");
?>