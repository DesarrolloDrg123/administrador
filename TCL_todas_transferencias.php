<?php 
session_start();
require("config/db.php");
include("src/templates/adminheader.php");

if (!isset($_SESSION['loggedin']) || !$_SESSION['loggedin']) {
    header("Location: index.php");
    exit();
}

// Mensajes
if (isset($_GET['msg'])) {
    switch ($_GET['msg']) {
        case 'success':
            echo '<div class="alert alert-success">Transferencia eliminada exitosamente.</div>'; break;
        case 'error':
            echo '<div class="alert alert-danger">Error al eliminar la transferencia.</div>'; break;
        case 'sqlerror':
            echo '<div class="alert alert-danger">Error en la consulta SQL.</div>'; break;
        case 'invalidid':
            echo '<div class="alert alert-warning">ID de transferencia no válido.</div>'; break;
    }
}
?>

<style>
/* estilos iguales a tu versión */
</style>

<div class="mt-5 col-md-12">
<h2 class="mb-4">Todas las Transferencias</h2>

<?php

$where = [];
$params = [];
$types = "";

if (!empty($_GET['departamento'])) {
    $where[] = "t.departamento_id = ?";
    $params[] = intval($_GET['departamento']);
    $types .= "i";
}
if (!empty($_GET['sucursal'])) {
    $where[] = "t.sucursal_id = ?";
    $params[] = intval($_GET['sucursal']);
    $types .= "i";
}
if (!empty($_GET['estado'])) {
    $where[] = "t.estado = ?";
    $params[] = $_GET['estado'];
    $types .= "s";
}
if (!empty($_GET['fecha_inicio'])) {
    $where[] = "t.fecha_solicitud >= ?";
    $params[] = $_GET['fecha_inicio'];
    $types .= "s";
}
if (!empty($_GET['fecha_fin'])) {
    $where[] = "t.fecha_solicitud <= ?";
    $params[] = $_GET['fecha_fin'];
    $types .= "s";
}

$where_sql = count($where) ? " WHERE " . implode(" AND ", $where) : "";

$sql = "
SELECT 
    MIN(t.id) AS id,
    t.folio,
    CASE WHEN COUNT(t.folio)>1 THEN 'Corporativo' ELSE MAX(s.sucursal) END AS sucursal,
    SUM(IFNULL(t.importe,0)) AS importe,
    SUM(IFNULL(t.importedls,0)) AS importedls,
    MAX(b.beneficiario) AS beneficiario,
    MAX(d.departamento) AS departamento,
    MAX(c.categoria) AS categoria,
    MAX(u2.nombre) AS usuario,
    MAX(u.nombre) AS autorizacion_id,
    MAX(t.fecha_solicitud) AS fecha_solicitud,
    MAX(t.tipo_cambio) AS tipo_cambio,
    MAX(t.descripcion) AS descripcion,
    MAX(t.estado) AS estado,
    MAX(t.documento_adjunto) AS documento_adjunto,
    MAX(t.recibo) AS recibo
FROM transferencias_clara_tcl t
JOIN beneficiarios b ON t.beneficiario_id=b.id
JOIN sucursales s ON t.sucursal_id=s.id
JOIN departamentos d ON t.departamento_id=d.id
JOIN categorias c ON t.categoria_id=c.id
JOIN usuarios u ON t.autorizacion_id=u.id
JOIN usuarios u2 ON t.usuario_solicitante_id=u2.id
$where_sql
GROUP BY t.folio
ORDER BY t.folio DESC";

$stmt = $conn->prepare($sql);

if (!empty($params)) {
    $bind = [];
    $bind[] = &$types;
    foreach ($params as $k => $v) $bind[] = &$params[$k];
    call_user_func_array([$stmt,'bind_param'],$bind);
}

$stmt->execute();
$res = $stmt->get_result();
$transferencias = $res->fetch_all(MYSQLI_ASSOC);

$folios = array_unique(array_column($transferencias,'folio'));
$documentos_por_folio = [];

if ($folios) {
    $placeholders = implode(',', array_fill(0,count($folios),'?'));
    $tipoFolios = str_repeat('s',count($folios));

    // FACTURAS
    $sqlF = "SELECT NO_ORDEN_COMPRA, SUM(TOTAL) total, COUNT(id) cant FROM facturas_tcl WHERE NO_ORDEN_COMPRA IN ($placeholders) GROUP BY NO_ORDEN_COMPRA";
    $stmF = $conn->prepare($sqlF);
    $stmF->bind_param($tipoFolios, ...$folios);
    $stmF->execute();
    $rF = $stmF->get_result();
    while($f=$rF->fetch_assoc()){
        $documentos_por_folio[$f['NO_ORDEN_COMPRA']] = [
            'total_facturas_monetary'=>$f['total'],
            'count_facturas'=>$f['cant'],
            'total_comprobantes_monetary'=>0,
            'count_comprobantes'=>0
        ];
    }

    // COMPROBANTES
    $sqlC = "SELECT folio, SUM(importe) total, COUNT(id) cant FROM comprobantes_tcl WHERE folio IN ($placeholders) GROUP BY folio";
    $stmC = $conn->prepare($sqlC);
    $stmC->bind_param($tipoFolios, ...$folios);
    $stmC->execute();
    $rC = $stmC->get_result();
    while($c=$rC->fetch_assoc()){
        $folio = $c['folio'];

        if (!isset($documentos_por_folio[$folio])) {
            $documentos_por_folio[$folio] = [
                'total_facturas_monetary'=>0,
                'count_facturas'=>0,
                'total_comprobantes_monetary'=>0,
                'count_comprobantes'=>0
            ];
        }

        $documentos_por_folio[$folio]['total_comprobantes_monetary']=$c['total'];
        $documentos_por_folio[$folio]['count_comprobantes']=$c['cant'];
    }
}
?>

<table class="table table-striped table-hover" id="solicitudesTable">
<thead class="table-dark text-center">
<tr>
<th>Folio</th>
<th>Beneficiario</th>
<th>Descripcion</th>
<th>Sucursal</th>
<th>Importe</th>
<th>Total Facturas</th>
<th>Total Docs</th>
<th>Pendiente</th>
</tr>
</thead>
<tbody>
<?php foreach($transferencias as $f):
    $folio = $f['folio'];

    $doc = $documentos_por_folio[$folio] ?? [
        'total_facturas_monetary'=>0,
        'count_facturas'=>0,
        'total_comprobantes_monetary'=>0,
        'count_comprobantes'=>0
    ];

    $importe = ($f['importedls']!=0)?$f['importedls']:$f['importe'];

    $totalFact = $doc['total_facturas_monetary'];
    $totalComp = $doc['total_comprobantes_monetary'];
    $countDocs = $doc['count_facturas'] + $doc['count_comprobantes'];

    $pendiente = $importe - ($totalFact + $totalComp);
?>
<tr class="text-center">
<td><?= $folio ?></td>
<td><?= $f['beneficiario'] ?></td>
<td><?= $f['descripcion'] ?></td>
<td><?= $f['sucursal'] ?></td>
<td>$<?= number_format($importe,2) ?></td>
<td>$<?= number_format($totalFact,2) ?></td>
<td><?= $countDocs ?></td>
<td><strong>$<?= number_format($pendiente,2) ?></strong></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>

<?php include("src/templates/adminfooter.php"); ?>
