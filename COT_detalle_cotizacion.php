<?php
session_start();
header('Content-Type: text/html; charset=utf-8');

require("config/db.php");
include("src/templates/adminheader.php");

// 1. Validaciones iniciales
if (!isset($_SESSION['loggedin']) || !$_SESSION['loggedin']) {
    header("Location: index.php");
    exit();
}

if (!isset($_GET['folio'])) {
    echo "Folio no proporcionado.";
    exit();
}

$folio = $_GET['folio'];

// =================================================================
// === 2. FUNCIONES PARA OBTENER DATOS DE LA BASE DE DATOS
// =================================================================

/**
 * Obtiene los datos generales de una cotización por su folio.
 */
function ObtenerDatosGenerales($conn, $folio) {
    $sql = "SELECT * FROM datos_generales_co WHERE folio = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $folio);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        return $result->fetch_assoc();
    } else {
        return null;
    }
}

/**
 * Obtiene todos los productos asociados a una cotización por su folio.
 */
function ObtenerProductos($conn, $folio) {
    $sql = "SELECT * FROM productos_co WHERE folio = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $folio);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $productos = [];
    while ($row = $result->fetch_assoc()) {
        $productos[] = $row;
    }
    return $productos;
}

// =================================================================
// === 3. OBTENCIÓN Y PREPARACIÓN DE DATOS
// =================================================================

$cotizacion = ObtenerDatosGenerales($conn, $folio);
$productos = ObtenerProductos($conn, $folio);

// Si no se encuentra la cotización, muestra un mensaje y termina
if ($cotizacion === null) {
    echo "<div class='container mt-5'><div class='alert alert-danger'>No se encontró ninguna cotización con el folio proporcionado.</div></div>";
    include("src/templates/adminfooter.php");
    exit();
}

// Alternativa para formatear la fecha sin la extensión intl
$timestamp = strtotime($cotizacion['fecha_solicitud']);
$fecha_solicitud_formateada = date('d/m/Y', $timestamp); // Formato: 25 / Ago / 2025

// Formatear el folio con ceros a la izquierda
$folio_formateado = str_pad($cotizacion['folio'], 9, "0", STR_PAD_LEFT);
?>
<br>
<div class="col-md-8 mx-auto">
    <h2 class="section-title"><i class="fas fa-file-invoice"></i> Detalles de la Cotización</h2>
    <div class="card mb-4">
        <div class="card-body">
            <table class="table table-bordered table-hover">
                <tbody>
                    <tr><th style="width: 25%;">Folio</th><td class="text-danger fw-bold"><?= htmlspecialchars($folio_formateado) ?></td></tr>
                    <tr><th>Empresa</th><td><?= htmlspecialchars($cotizacion['empresa']) ?></td></tr>
                    <tr><th>Nombre del Cliente</th><td><?= htmlspecialchars($cotizacion['cliente']) ?></td></tr>
                    <tr><th>Teléfono</th><td><?= htmlspecialchars($cotizacion['telefono']) ?></td></tr>
                    <tr><th>Celular</th><td><?= htmlspecialchars($cotizacion['celular']) ?></td></tr>
                    <tr><th>Correo</th><td><?= htmlspecialchars($cotizacion['correo']) ?></td></tr>
                    <tr><th>Solicitante</th><td><?= htmlspecialchars($cotizacion['user_solicitante']) ?></td></tr>
                    <tr><th>Fecha de Solicitud</th><td><?= htmlspecialchars($fecha_solicitud_formateada) ?></td></tr>
                    <tr>
                        <th>Etiquetado y Codificado</th>
                        <td>
                            <?php 
                                $esRfid = ($cotizacion['rfid'] == 1) ? "Sí" : "No";
                                echo htmlspecialchars($esRfid);
                            ?>
                        </td>
                    </tr>
                    <tr><th>Observaciones</th><td><?= !empty($cotizacion['observaciones']) ? nl2br(htmlspecialchars($cotizacion['observaciones'])) : 'N/A' ?></td></tr>
                    <tr><th>Estado</th><td><?= htmlspecialchars($cotizacion['estatus']) ?></td></tr>
                    
                    <?php if($cotizacion['estatus'] == "Devuelta" || $cotizacion['estatus'] == "Rechazada") { ?>
                        <tr><th>Motivo</th><td><?= htmlspecialchars($cotizacion['motivo']) ?></td></tr>
                    <?php } ?>
                    
                </tbody>
            </table>

            <h5 class="mt-4"><i class="fas fa-cubes"></i> 
                <?php 
                    $titulo = ($cotizacion['estatus'] == 'Cotizado' || $cotizacion['estatus'] == 'Pedido Generado') ? 'Productos Cotizados' : 'Productos para Cotizar';
                    echo $titulo;
                ?>
            </h5>
            <div class="table-responsive">
                <table class="table table-sm table-striped table-bordered mt-2">
                    <thead class="table-light">
                        <?php if ($cotizacion['estatus'] == 'Cotizado' || $cotizacion['estatus'] == 'Pedido Generado'): ?>
                            <tr>
                                <th rowspan="2" class="text-center align-middle">#</th>
                                <th rowspan="2" class="text-center align-middle">Producto</th>
                                <th colspan="5" class="text-center">Proveedor 1</th>
                                <th colspan="5" class="text-center">Proveedor 2</th>
                                <th colspan="5" class="text-center">Proveedor 3</th>
                            </tr>
                            <tr>
                                <th>Nombre</th><th>Costo</th><th>Disponibilidad</th><th>T. Entrega</th><th>Rec.</th>
                                <th>Nombre</th><th>Costo</th><th>Disponibilidad</th><th>T. Entrega</th><th>Rec.</th>
                                <th>Nombre</th><th>Costo</th><th>Disponibilidad</th><th>T. Entrega</th><th>Rec.</th>
                            </tr>
                        <?php else: ?>
                            <tr>
                                <th style="width: 5%;">#</th>
                                <th>N° de Parte (SKU)</th>
                                <th>Descripción</th>
                                <th style="width: 10%;">Cantidad</th>
                                <th>Notas</th>
                            </tr>
                        <?php endif; ?>
                    </thead>
                    <tbody>
                        <?php if (!empty($productos)):
                            foreach ($productos as $index => $producto): ?>
                                <tr>
                                    <?php if ($cotizacion['estatus'] == 'Cotizado' || $cotizacion['estatus'] == 'Pedido Generado'): ?>
                                        <td class="text-center"><?= $index + 1 ?></td>
                                        <td>
                                            <strong><?= htmlspecialchars($producto['sku']) ?></strong><br>
                                            <small><?= htmlspecialchars($producto['descripcion']) ?></small><br>
                                            <strong>Cant: <?= htmlspecialchars($producto['cantidad']) ?></strong>
                                        </td>
                                        <td><?= htmlspecialchars($producto['proveedor1']) ?></td>
                                        <td class="text-end">$<?= number_format($producto['costo1'], 2) ?></td>
                                        <td><?= htmlspecialchars($producto['disponibilidad1']) ?></td>
                                        <td><?= htmlspecialchars($producto['tiempo_entrega1']) ?></td>
                                        <td class="text-center"><?= ($producto['recomendacion1'] == 1) ? '<i class="fas fa-check-circle text-success"></i>' : '' ?></td>
                                        <td><?= htmlspecialchars($producto['proveedor2']) ?></td>
                                        <td class="text-end">$<?= number_format($producto['costo2'], 2) ?></td>
                                        <td><?= htmlspecialchars($producto['disponibilidad2']) ?></td>
                                        <td><?= htmlspecialchars($producto['tiempo_entrega2']) ?></td>
                                        <td class="text-center"><?= ($producto['recomendacion2'] == 1) ? '<i class="fas fa-check-circle text-success"></i>' : '' ?></td>
                                        <td><?= htmlspecialchars($producto['proveedor3']) ?></td>
                                        <td class="text-end">$<?= number_format($producto['costo3'], 2) ?></td>
                                        <td><?= htmlspecialchars($producto['disponibilidad3']) ?></td>
                                        <td><?= htmlspecialchars($producto['tiempo_entrega3']) ?></td>
                                        <td class="text-center"><?= ($producto['recomendacion3'] == 1) ? '<i class="fas fa-check-circle text-success"></i>' : '' ?></td>
                                    <?php else: ?>
                                        <td><?= $index + 1 ?></td>
                                        <td><?= htmlspecialchars($producto['sku']) ?></td>
                                        <td><?= htmlspecialchars($producto['descripcion']) ?></td>
                                        <td class="text-center"><?= htmlspecialchars($producto['cantidad']) ?></td>
                                        <td><?= htmlspecialchars($producto['notas']) ?></td>
                                    <?php endif; ?>
                                </tr>
                            <?php endforeach;
                        else: ?>
                            <tr>
                                <td colspan="17" class="text-center">No se encontraron productos para esta cotización.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="d-flex gap-2 mt-3">
                <a href="javascript:history.back()" class="btn btn-secondary">Volver</a>
                </div>
        </div>
    </div>
</div>

<?php
include("src/templates/adminfooter.php");
?>