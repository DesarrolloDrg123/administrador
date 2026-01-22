<?php
session_start();
// Si no hay sesión, devolvemos error
if (!isset($_SESSION['loggedin'])) {
    echo json_encode(['status' => 'error', 'message' => 'No autorizado']);
    exit;
}

require("../config/db.php");
header('Content-Type: application/json');

try {
    // Consulta el último folio
    $stmt = $conn->prepare("SELECT folio FROM control_folios_aud WHERE id = 1");
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();

    // Calcula el siguiente folio y lo formatea a 9 dígitos
    $siguiente_folio = ($row ? $row['folio'] : 0) + 1;
    $folio_formateado = sprintf("%09d", $siguiente_folio);

    echo json_encode([
        'status' => 'success',
        'folio' => $folio_formateado
    ]);

} catch (Exception $e) {
    echo json_encode([
        'status' => 'error', 
        'message' => $e->getMessage()
    ]);
}

$conn->close();