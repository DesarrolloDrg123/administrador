<?php
require "../config/db.php"; // Asegúrate que la ruta a tu conexión sea correcta

// Establecemos que la respuesta siempre será en formato JSON
header('Content-Type: application/json');

// Validamos los datos de entrada
if (!isset($_GET['sucursal_id']) || !isset($_GET['depto_id']) || !is_numeric($_GET['sucursal_id']) || !is_numeric($_GET['depto_id'])) {
    echo json_encode(['success' => false, 'presupuesto' => 'Datos inválidos.']);
    exit();
}

$sucursal_id = intval($_GET['sucursal_id']);
$depto_id = intval($_GET['depto_id']);

// Calculamos el periodo actual (ej. 'Jul-25')
$meses = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
$periodo = $meses[date('n') - 1] . '-' . date('y');

try {
    $stmt = $conn->prepare("SELECT restante FROM presupuestos WHERE sucursal_id = ? AND departamento_id = ? AND periodo = ? LIMIT 1");
    $stmt->bind_param("iis", $sucursal_id, $depto_id, $periodo);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        // Éxito: Devolvemos el presupuesto
        echo json_encode(['success' => true, 'presupuesto' => floatval($row['restante'])]);
    } else {
        // No se encontró presupuesto para esa combinación
        echo json_encode(['success' => false, 'presupuesto' => 'No disponible']);
    }
} catch (Exception $e) {
    // Error en la consulta a la base de datos
    echo json_encode(['success' => false, 'presupuesto' => 'Error']);
}

$conn->close();
?>