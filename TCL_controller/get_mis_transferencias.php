<?php
// Inicia o reanuda la sesión
session_start();
require '../config/db.php'; 
header('Content-Type: application/json');
if (empty($_SESSION['usuario_id'])) {
    http_response_code(401); // No autorizado
    echo json_encode(['success' => false, 'message' => 'Acceso denegado. Por favor, inicie sesión.']);
    exit();
}
$usuario_id = $_SESSION['usuario_id'];
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
            t.estado, -- Asumimos que tienes una columna para el estado de la solicitud (e.g., Pendiente, Autorizada)
            t.observaciones
        FROM transferencias t
        JOIN sucursales s ON t.sucursal_id = s.id
        JOIN beneficiarios b ON t.beneficiario_id = b.id
        JOIN departamentos d ON t.departamento_id = d.id
        JOIN categorias c ON t.categoria_id = c.id
        JOIN usuarios u_autoriza ON t.autorizacion_id = u_autoriza.id
        WHERE t.usuario_id = ?
        ORDER BY t.fecha_solicitud DESC, t.folio DESC
    ";
    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        throw new Exception("Error al preparar la consulta: " . $conn->error);
    }
    $stmt->bind_param("i", $usuario_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $transferencias[] = $row;
    }
    $stmt->close();
    http_response_code(200); // OK
    echo json_encode([
        'success' => true,
        'transferencias' => $transferencias,
        'message' => 'Transferencias obtenidas correctamente.'
    ]);
} catch (Exception $e) {
    // Manejo de errores de la base de datos o de la ejecución
    http_response_code(500); // Error interno del servidor
    echo json_encode(['success' => false, 'message' => 'Error al obtener las transferencias: ' . $e->getMessage()]);
}
if (isset($conn) && $conn) {
    $conn->close();
}
exit();
?>