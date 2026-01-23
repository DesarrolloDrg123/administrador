<?php
require("../config/db.php");
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

    if ($id > 0) {
        // Borramos el token y actualizamos el estatus a 'Finalizado' (opcional)
        $stmt = $conn->prepare("UPDATE auditorias_vehiculos_aud SET token_evidencia = NULL, estatus = 'Finalizado' WHERE id = ?");
        $stmt->bind_param("i", $id);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => $conn->error]);
        }
    } else {
        echo json_encode(['success' => false, 'error' => 'ID no válido']);
    }
}