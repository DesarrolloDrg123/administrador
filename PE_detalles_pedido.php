<?php
    
    session_start();
    header('Content-Type: text/html; charset=utf-8');
    
    require("config/db.php");
    include("src/templates/adminheader.php");


    if (!isset($_SESSION['loggedin']) || !$_SESSION['loggedin']) {
        header("Location: index.php");
        exit();
        }

    if (!isset($_GET['doc'])) {
        echo "Folio no proporcionado.";
        exit();
    }
    
    $mParam = isset($_GET['M']) ? $_GET['M'] : null;
    $tParam = isset($_GET['T']) ? $_GET['T'] : null;
    $aParam = isset($_GET['A']) ? $_GET['A'] : null;
    
    $pedido = PedidoEspecial($conn, $_GET['doc']);
    
    function PedidoEspecial($conn, $id) {
        $id = $conn->real_escape_string($id);
        $sql = "SELECT p.*, u.nombre AS uso_nombre, s.sucursal AS sucursal_nombre
                FROM pedidos_especiales p
                JOIN uso u ON p.uso = u.id
                JOIN sucursales s ON p.sucursal = s.id
                WHERE p.id = '$id'";
        
        $result = $conn->query($sql);
        
        if ($result->num_rows > 0) {
            return $result->fetch_assoc(); // Retorna el primer registro encontrado
        } else {
            return null; // Retorna null si no se encontr車 ning迆n registro
        }
    }
    
    
    
    $fmt = new IntlDateFormatter('es_ES', IntlDateFormatter::LONG, IntlDateFormatter::NONE);
    $fecha = new DateTime($pedido['fecha']);
    $fecha2 = new DateTime($pedido['fecha_autorizacion']);
    $fecha_formateada = $fmt->format($fecha);
    $fecha_formateada2 = $fmt->format($fecha2);

?>
<br>
<div class="col-md-8 mx-auto">
    <h2 class="section-title"><i class="fas fa-box-open"></i> Información del Pedido</h2>
    <div class="card mb-4">
        <div class="card-body">
            <table class="table table-bordered table-hover">
                <tbody>
                    <tr><th>Folio</th><td class="text-danger fw-bold"><?= htmlspecialchars($pedido['folio']) ?></td></tr>
                    <?php if (!empty($pedido['oc'])): ?>
                    <tr><th>Orden de Compra</th><td><?= htmlspecialchars($pedido['oc']) ?></td></tr>
                    <?php endif; ?>
                    <tr><th>Sucursal</th><td><?= htmlspecialchars($pedido['sucursal_nombre']) ?></td></tr>
                    <tr><th>Solicitante</th><td><?= htmlspecialchars($pedido['solicitante']) ?></td></tr>
                    <tr><th>No. de Cuenta</th><td><?= htmlspecialchars($pedido['numero_cliente']) ?></td></tr>
                    <tr><th>Nombre del Cliente</th><td><?= htmlspecialchars($pedido['nombre_cliente']) ?></td></tr>
                    <tr><th>Categoría</th><td><?= htmlspecialchars($pedido['uso_nombre']) ?></td></tr>
                    <tr><th>Fecha de Solicitud</th><td><?= htmlspecialchars($fecha_formateada) ?></td></tr>
                    <?php if ($pedido['fecha_autorizacion'] != "0000-00-00"): ?>
                    <tr><th>Fecha de Autorización</th><td><?= htmlspecialchars($fecha_formateada2) ?></td></tr>
                    <?php endif; ?>
                    <tr><th>Observaciones</th><td><?= !empty($pedido['observaciones']) ? htmlspecialchars($pedido['observaciones']) : 'N/A' ?></td></tr>
                    <tr><th>Estado</th><td><?= htmlspecialchars($pedido['estatus']) ?></td></tr>
                    <?php if (!empty($pedido['autorizado_por'])): ?>
                    <tr><th>Autorizado por</th><td><?= htmlspecialchars($pedido['autorizado_por']) ?></td></tr>
                    <?php endif; ?>
                    <?php if (!empty($pedido['motivo_devolucion'])): ?>
                    <tr><th>Motivo de Devolución</th><td class="text-danger"><?= htmlspecialchars($pedido['motivo_devolucion']) ?></td></tr>
                    <?php endif; ?>
                    <?php if (!empty($pedido['motivo_rechazo'])): ?>
                    <tr><th>Motivo de Rechazo</th><td class="text-danger"><?= htmlspecialchars($pedido['motivo_rechazo']) ?></td></tr>
                    <?php endif; ?>
                </tbody>
            </table>

            <!-- Tabla de productos -->
            <h5 class="mt-4"><i class="fas fa-cubes"></i> Productos Solicitados</h5>
            <table class="table table-sm table-striped table-bordered mt-2">
                <thead class="table-light">
                    <tr>
                        <th>N° de Parte</th>
                        <th>Descripción</th>
                        <th>Cantidad</th>
                        <th>Notas</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $skus = explode(';', $pedido['sku']);
                    $descripciones = explode(';', $pedido['descripcion']);
                    $cantidades = explode(';', $pedido['cantidad']);
                    $notas = explode(';', $pedido['nota']);
                    $numItems = count($skus);
                    for ($i = 0; $i < $numItems; $i++): ?>
                    <tr>
                        <td><?= htmlspecialchars($skus[$i]) ?></td>
                        <td><?= htmlspecialchars($descripciones[$i]) ?></td>
                        <td><?= htmlspecialchars($cantidades[$i]) ?></td>
                        <td><?= htmlspecialchars($notas[$i]) ?></td>
                    </tr>
                    <?php endfor; ?>
                </tbody>
            </table>

            <!-- Botones -->
            <div class="d-flex gap-2 mt-3">
                <?php if ($mParam === 'true'): ?>
                    <a href="PE_mis_pedidos.php" class="btn btn-secondary">Volver</a>
                <?php endif; ?>
                <?php if ($tParam === 'true'): ?>
                    <a href="PE_todos_pedidos.php" class="btn btn-secondary">Volver</a>
                <?php endif; ?>
                <?php if ($aParam === 'true'): ?>
                    <a href="PE_por_procesar.php" class="btn btn-secondary">Volver</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>


<?php
include("src/templates/adminfooter.php");
?>