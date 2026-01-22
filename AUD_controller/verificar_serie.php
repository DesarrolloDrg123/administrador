<?php
require("../config/db.php");

$no_serie = $_GET['no_serie'] ?? '';
$response = ['exists' => false];

if ($no_serie !== '') {
    $stmt = $conn->prepare("SELECT id FROM vehiculos_aud WHERE no_serie = ? LIMIT 1");
    $stmt->bind_param("s", $no_serie);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        $response['exists'] = true;
    }
}

echo json_encode($response);