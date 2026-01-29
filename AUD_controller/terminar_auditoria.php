<?php
require("../config/db.php");
header('Content-Type: application/json');

// Limpiar cualquier salida previa para evitar errores de JSON
if (ob_get_length()) ob_clean();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

    if ($id > 0) {
        // Importante: Verifica que las columnas 'token_evidencia' y 'estatus' existan
        $query = "UPDATE auditorias_vehiculos_aud SET token_evidencia = NULL, estatus = 'Finalizado' WHERE id = ?";
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
            echo json_encode(['success' => false, 'error' => 'Error en preparación: ' . $conn->error]);
        }
    } else {
        echo json_encode(['success' => false, 'error' => 'ID de auditoría no válido recibo: ' . $_POST['id']]);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
}
?>