<?php
session_start();
include $_SERVER['DOCUMENT_ROOT']."/config/config.php"; // Asegúrate de incluir tu conexión a la base de datos

$user = $_SESSION['nombre'];
function getNotificationCount($conn) {
    $sql = "SELECT COUNT(*) as count FROM pedidos_especiales WHERE estatus = 'Nuevo' OR estatus = 'Por Revisar'";
    $result = $conn->query($sql);
    
    if ($result) {
        $row = $result->fetch_assoc();
        // Verificar si el conteo supera los 99
        if ($row['count'] > 99) {
            return '+99';
        }
        return $row['count'];
    }

    // Agregar mensaje de error a la consola
    error_log("Error en la consulta: " . $conn->error);
    return 0;
}

header('Content-Type: application/json');
echo json_encode(['count' => getNotificationCount($conn)]);

?>
