<?php
// Archivo: api_transferencias.php
require("../config/db.php"); // Asegúrate de que la ruta a tu conexión sea correcta

// Parámetros que envía DataTables
$draw = $_POST['draw'] ?? 0;
$start = $_POST['start'] ?? 0;
$length = $_POST['length'] ?? 10;
$searchValue = $_POST['search']['value'] ?? '';
$orderColumnIndex = $_POST['order'][0]['column'] ?? 0;
$orderColumnDir = $_POST['order'][0]['dir'] ?? 'desc';

// Mapeo de columnas para la ordenación segura
$columns = [
    't.folio', 'b.beneficiario', 't.descripcion', 's.sucursal', 'd.departamento', 'c.categoria',
    'u2.nombre', 'u.nombre', 't.importe', 't.importe', 't.tipo_cambio', 'total_facturas',
    'total_facturas', 't.fecha_solicitud', 't.estado'
];
$orderColumn = $columns[$orderColumnIndex] ?? 't.folio';

// ---- Construcción de la consulta ----
$params = [];
$types = '';
$where_conditions = [];

// Filtros personalizados del formulario
if (!empty($_POST['departamento'])) {
    $where_conditions[] = "t.departamento_id = ?";
    $params[] = intval($_POST['departamento']);
    $types .= 'i';
}
if (!empty($_POST['sucursal'])) {
    $where_conditions[] = "t.sucursal_id = ?";
    $params[] = intval($_POST['sucursal']);
    $types .= 'i';
}
if (!empty($_POST['estado'])) {
    $where_conditions[] = "t.estado = ?";
    $params[] = $_POST['estado'];
    $types .= 's';
}
if (!empty($_POST['fecha_inicio'])) {
    $where_conditions[] = "t.fecha_solicitud >= ?";
    $params[] = $_POST['fecha_inicio'];
    $types .= 's';
}
if (!empty($_POST['fecha_fin'])) {
    $where_conditions[] = "t.fecha_solicitud <= ?";
    $params[] = $_POST['fecha_fin'];
    $types .= 's';
}

// Filtro de búsqueda general de DataTables
if (!empty($searchValue)) {
    $search_cols = ['t.folio', 'b.beneficiario', 't.descripcion'];
    $search_parts = [];
    foreach ($search_cols as $col) {
        $search_parts[] = "$col LIKE ?";
        $params[] = "%" . $searchValue . "%";
        $types .= 's';
    }
    $where_conditions[] = "(" . implode(" OR ", $search_parts) . ")";
}

$where_sql = count($where_conditions) > 0 ? " WHERE " . implode(" AND ", $where_conditions) : "";

// Consulta base (FROM y JOINS)
$sql_base = "
    FROM transferencias t
    JOIN beneficiarios b ON t.beneficiario_id = b.id
    JOIN sucursales s ON t.sucursal_id = s.id
    JOIN departamentos d ON t.departamento_id = d.id
    JOIN categorias c ON t.categoria_id = c.id
    JOIN usuarios u ON t.autorizacion_id = u.id
    JOIN usuarios u2 ON t.usuario_id = u2.id
    LEFT JOIN (
        SELECT NO_ORDEN_COMPRA, SUM(TOTAL) AS total_facturas
        FROM facturas GROUP BY NO_ORDEN_COMPRA
    ) AS facturas_info ON t.folio = facturas_info.NO_ORDEN_COMPRA
";

// Obtener total de registros (sin filtros)
$totalRecordsResult = $conn->query("SELECT COUNT(t.id) AS total " . $sql_base);
$totalRecords = $totalRecordsResult->fetch_assoc()['total'];

// Obtener total de registros filtrados
$sqlFiltered = "SELECT COUNT(t.id) as total " . $sql_base . $where_sql;
$stmtFiltered = $conn->prepare($sqlFiltered);
if (!empty($params)) {
    $stmtFiltered->bind_param($types, ...$params);
}
$stmtFiltered->execute();
$totalFiltered = $stmtFiltered->get_result()->fetch_assoc()['total'];


// Consulta para obtener los datos paginados
$sqlData = "
    SELECT
        t.id, t.folio, b.beneficiario, t.descripcion, s.sucursal, d.departamento, c.categoria,
        u2.nombre AS usuario, u.nombre AS autorizacion_id, t.importe, t.importedls, t.tipo_cambio,
        t.fecha_solicitud, t.estado, t.documento_adjunto, t.recibo,
        COALESCE(facturas_info.total_facturas, 0) AS total_facturas,
        (CASE WHEN facturas_info.total_facturas IS NOT NULL THEN 1 ELSE 0 END) AS tiene_facturas
    " . $sql_base . $where_sql . " ORDER BY $orderColumn $orderColumnDir LIMIT ? OFFSET ?";

// Añadir parámetros de LIMIT y OFFSET al final
$limit_params = array_merge($params, [$length, $start]);
$limit_types = $types . 'ii';
$stmtData = $conn->prepare($sqlData);
$stmtData->bind_param($limit_types, ...$limit_params);
$stmtData->execute();
$result = $stmtData->get_result();

$data = [];
while ($row = $result->fetch_assoc()) {
    $importe_num = ($row['importedls'] && $row['importedls'] != "0.00") ? $row['importedls'] : $row['importe'];
    $moneda = ($row['importedls'] && $row['importedls'] != "0.00") ? 'USD' : 'MXN';
    $pendiente = $row['importe'] - $row['total_facturas'];
    $folio_class = $row['tiene_facturas'] ? 'text-success fw-bold' : 'text-danger fw-bold';
    $doc_link = !empty($row['documento_adjunto']) ? '<a href="'.htmlspecialchars($row['documento_adjunto']).'" target="_blank" class="btn btn-outline-primary btn-sm" title="Documento Adjunto"><i class="fas fa-file-alt fa-3x"></i></a>' : '<span class="text-muted">N/A</span>';
    $recibo_link = !empty($row['recibo']) ? '<a href="'.htmlspecialchars($row['recibo']).'" download class="btn btn-outline-secondary btn-sm" title="Descargar Recibo"><i class="fas fa-file-download fa-3x"></i></a>' : '<span class="text-muted">N/A</span>';
    
    $data[] = [
        "folio" => '<a href="TR_edit_transferencia.php?id='.$row['id'].'&TT=true" class="'.$folio_class.'">'.htmlspecialchars($row['folio']).'</a>',
        "beneficiario" => htmlspecialchars($row['beneficiario']),
        "descripcion" => htmlspecialchars($row['descripcion']),
        "sucursal" => htmlspecialchars($row['sucursal']),
        "departamento" => htmlspecialchars($row['departamento']),
        "categoria" => htmlspecialchars($row['categoria']),
        "usuario" => htmlspecialchars($row['usuario']),
        "autorizacion_id" => htmlspecialchars($row['autorizacion_id']),
        "importe" => '$'.number_format($importe_num, 2),
        "moneda" => $moneda,
        "tipo_cambio" => '$'.number_format($row['tipo_cambio'], 2),
        "total_facturas" => '$'.number_format($row['total_facturas'], 2),
        "pendiente" => '$'.number_format($pendiente, 2),
        "fecha_solicitud" => date('d/m/Y', strtotime($row['fecha_solicitud'])),
        "estado" => htmlspecialchars($row['estado']),
        "documento_adjunto" => $doc_link,
        "recibo" => $recibo_link,
    ];
}

// Respuesta JSON que DataTables entiende
header('Content-Type: application/json');
echo json_encode([
    "draw" => intval($draw),
    "recordsTotal" => intval($totalRecords),
    "recordsFiltered" => intval($totalFiltered),
    "data" => $data
]);
?>