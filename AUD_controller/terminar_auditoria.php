<?php
require("../config/db.php");
header('Content-Type: application/json');

if (ob_get_length()) ob_clean();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

    if ($id > 0) {
        // Ahora que la columna es normal, este UPDATE funcionará perfecto
        $query = "UPDATE auditorias_vehiculos_aud 
                  SET token_evidencia = NULL, 
                      estatus = 'Finalizada' 
                  WHERE id = ?";
        
        $stmt = $conn->prepare($query);
        
        if ($stmt) {
            $stmt->bind_param("i", $id);
            if ($stmt->execute()) {
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Error al ejecutar: ' . $stmt->error]);
            }
            $stmt->close();
        } else {
            echo json_encode(['success' => false, 'error' => 'Error en DB: ' . $conn->error]);
        }
    } else {
        echo json_encode(['success' => false, 'error' => 'ID no válido']);
    }
}
?>