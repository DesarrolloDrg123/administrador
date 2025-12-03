<?php
session_start();
require '../config/db.php';

header('Content-Type: application/json');

if (empty($_SESSION['usuario_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Acceso denegado.']);
    exit();
}

$usuario_id = $_SESSION['usuario_id'];

$departamento = $_GET['departamento'] ?? '';
$sucursal     = $_GET['sucursal'] ?? '';
$fecha_ini    = $_GET['fecha_ini'] ?? '';
$fecha_fin    = $_GET['fecha_fin'] ?? '';

$transferencias = [];

try {

    $sql = "
        SELECT
            t.id, 
            t.folio, 
            t.fecha_solicitud, 
            t.fecha_vencimiento,
            t.no_cuenta,
            t.importe, 
            t.importedls,
            s.sucursal,
            b.beneficiario,
            d.departamento,
            c.categoria,
            u_autoriza.nombre AS autorizador_nombre,
            t.documento_adjunto,
            t.estado,
            t.observaciones
        FROM transferencias t
        JOIN sucursales s     ON t.sucursal_id = s.id
        JOIN beneficiarios b  ON t.beneficiario_id = b.id
        JOIN departamentos d  ON t.departamento_id = d.id
        JOIN categorias c     ON t.categoria_id = c.id
        JOIN usuarios u_autoriza ON t.autorizacion_id = u_autoriza.id
        WHERE t.usuario_id = ?
    ";

    // --- filtros dinámicos ---
    if (!empty($departamento)) $sql .= " AND d.id = ? ";
    if (!empty($sucursal))     $sql .= " AND s.id = ? ";
    if (!empty($fecha_ini))    $sql .= " AND t.fecha_solicitud >= ? ";
    if (!empty($fecha_fin))    $sql .= " AND t.fecha_solicitud <= ? ";

    $sql .= " ORDER BY t.fecha_solicitud DESC, t.folio DESC ";

    $stmt = $conn->prepare($sql);

    if ($stmt === false) {
        throw new Exception("Error preparar consulta: " . $conn->error);
    }

    // --- binding dinámico ---
    $bind_types = "i"; 
    $bind_values = [$usuario_id];

    if (!empty($departamento)) { $bind_types .= "i"; $bind_values[] = $departamento; }
    if (!empty($sucursal))     { $bind_types .= "i"; $bind_values[] = $sucursal; }
    if (!empty($fecha_ini))    { $bind_types .= "s"; $bind_values[] = $fecha_ini; }
    if (!empty($fecha_fin))    { $bind_types .= "s"; $bind_values[] = $fecha_fin; }

    $stmt->bind_param($bind_types, ...$bind_values);

    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $transferencias[] = $row;
    }

    echo json_encode([
        'success' => true,
        'transferencias' => $transferencias
    ]);

} catch (Exception $e) {

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error al obtener transferencias: ' . $e->getMessage()
    ]);

}

$stmt->close();
$conn->close();
exit();
