<?php
session_start();
require '../config/db.php'; // Ajusta la ruta a tu conexión

// Verificar sesión
if (!isset($_SESSION['loggedin']) || !$_SESSION['loggedin']) {
    die("Acceso no autorizado.");
}

// --- 1. Recibir y construir los filtros de la URL ---
$where = [];
$params = [];
$types = '';
// (Tu código para recibir filtros con $_GET se mantiene igual)
if (!empty($_GET['departamento'])) { $where[] = "t.departamento_id = ?"; $params[] = intval($_GET['departamento']); $types .= 'i'; }
if (!empty($_GET['sucursal'])) { $where[] = "t.sucursal_id = ?"; $params[] = intval($_GET['sucursal']); $types .= 'i'; }
if (!empty($_GET['estado'])) { $where[] = "t.estado = ?"; $params[] = $_GET['estado']; $types .= 's'; }
if (!empty($_GET['fecha_inicio'])) { $where[] = "t.fecha_solicitud >= ?"; $params[] = date('Y-m-d', strtotime($_GET['fecha_inicio'])); $types .= 's'; }
if (!empty($_GET['fecha_fin'])) { $where[] = "t.fecha_solicitud <= ?"; $params[] = date('Y-m-d', strtotime($_GET['fecha_fin'])); $types .= 's'; }
if (!empty($_GET['usuario_id'])) { $usuario_filtrar_id = intval($_GET['usuario_id']);  $where[] = "(t.usuario_id = ? OR t.autorizacion_id = ?)"; $params[] = $usuario_filtrar_id; $params[] = $usuario_filtrar_id; $types .= 'ii'; 
}

$where_sql = count($where) > 0 ? " WHERE " . implode(" AND ", $where) : "";

// --- 2. Construir y ejecutar la consulta SQL detallada ---
// Se añaden todos los campos necesarios para el reporte
$sql = "
    SELECT 
        t.folio,
        b.beneficiario,
        t.descripcion,
        s.sucursal,
        d.departamento,
        c.categoria,
        u_elabora.nombre AS usuario_elabora,
        u_autoriza.nombre AS usuario_autoriza,
        t.importe,
        t.importedls,
        t.tipo_cambio,
        COALESCE(fs.total_facturas, 0) AS total_facturas,
        t.fecha_solicitud,
        t.estado
    FROM 
        transferencias_clara_tcl t
    JOIN beneficiarios b ON t.beneficiario_id = b.id
    JOIN sucursales s ON t.sucursal_id = s.id
    JOIN departamentos d ON t.departamento_id = d.id
    JOIN categorias c ON t.categoria_id = c.id
    JOIN usuarios u_autoriza ON t.autorizacion_id = u_autoriza.id
    JOIN usuarios u_elabora ON t.usuario_id = u_elabora.id
    LEFT JOIN (
        SELECT NO_ORDEN_COMPRA, SUM(TOTAL) as total_facturas
        FROM facturas_tcl
        GROUP BY NO_ORDEN_COMPRA
    ) fs ON t.folio = fs.NO_ORDEN_COMPRA
    $where_sql
    ORDER BY 
        t.folio ASC, t.id ASC";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

// --- 3. Preparación de Datos (Agrupar en PHP) ---
$transferencias_por_folio = [];
if ($result->num_rows > 0) {
    while ($fila = $result->fetch_assoc()) {
        $transferencias_por_folio[$fila['folio']][] = $fila;
    }
}
$stmt->close();

// --- 4. Generar y enviar el archivo CSV ---
$filename = "Reporte_Transferencias_" . date('Y-m-d') . ".csv";
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
echo "\xEF\xBB\xBF"; // UTF-8 BOM para compatibilidad con Excel

$output = fopen('php://output', 'w');

// Escribir la fila de encabezados en el orden solicitado
fputcsv($output, [
    'Folio', 
    'Beneficiario', 
    'Descripción', 
    'Sucursal', 
    'Departamento', 
    'Categoría', 
    'Elabora', 
    'Autoriza',
    'Importe',
    'Moneda',
    'Tipo de Cambio',
    'Facturas Aplicadas',
    'Pendiente',
    'Fecha Solicitud',
    'Estado'
]);

// Recorrer los datos AGRUPADOS para aplicar la lógica de distribución
foreach ($transferencias_por_folio as $folio => $grupo_transferencias) {
    
    // El total de facturas es el mismo para todo el grupo, lo obtenemos de la primera fila
    $total_facturas_folio = $grupo_transferencias[0]['total_facturas'] ?? 0;
    $facturas_restantes_a_distribuir = $total_facturas_folio;

    // Recorrer cada transferencia DENTRO del grupo
    foreach ($grupo_transferencias as $row) {
        
        $importe_actual = (!empty($row['importedls']) && $row['importedls'] > 0) ? $row['importedls'] : $row['importe'];
        $moneda = (!empty($row['importedls']) && $row['importedls'] > 0) ? 'USD' : 'MXN';
        
        // Lógica de distribución
        $facturas_aplicadas = 0;
        $pendiente = floatval($importe_actual);
        if ($facturas_restantes_a_distribuir > 0 && !in_array($row['estado'], ["Cancelada", "Rechazado"])) {
            $facturas_aplicadas = min(floatval($importe_actual), $facturas_restantes_a_distribuir);
            $pendiente = floatval($importe_actual) - $facturas_aplicadas;
            $facturas_restantes_a_distribuir -= $facturas_aplicadas;
        }

        // Crear la línea del CSV en el orden correcto
        $linea_csv = [
            $row['folio'],
            $row['beneficiario'],
            $row['descripcion'],
            $row['sucursal'],
            $row['departamento'],
            $row['categoria'],
            $row['usuario_elabora'],
            $row['usuario_autoriza'],
            number_format($importe_actual, 2, '.', ''),
            $moneda,
            number_format($row['tipo_cambio'], 2, '.', ''),
            number_format($facturas_aplicadas, 2, '.', ''),
            number_format($pendiente, 2, '.', ''),
            $row['fecha_solicitud'],
            $row['estado']
        ];
        fputcsv($output, $linea_csv);
    }
}

fclose($output);
$conn->close();
exit();
?>