<?php
session_start();
include $_SERVER['DOCUMENT_ROOT'] . "/config/config.php"; // Asegúrate de incluir tu conexión a la base de datos

if (isset($_GET['id']) && isset($_GET['EL']) && $_GET['EL'] === 'true') {
    $id = intval($_GET['id']); // Convierte a entero para mayor seguridad

    // Consulta para eliminar el usuario
    $sql = "DELETE FROM usuarios WHERE id = ?";
    $stmt = $conn->prepare($sql);

    if ($stmt) {
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            // Redirigir con un mensaje de éxito
            header("Location: ../views/conf_usuarios.php?msg=Usuario eliminado correctamente");
            exit();
        } else {
            // Registrar error si la ejecución falla
            error_log("Error al eliminar usuario: " . $stmt->error);
            header("Location: ../views/conf_usuarios.php?msg=Error al eliminar usuario");
            exit();
        }
    } else {
        // Registrar error si la preparación de la consulta falla
        error_log("Error al preparar la consulta: " . $conn->error);
        header("Location: ../views/conf_usuarios.php?msg=Error en el sistema");
        exit();
    }
} else {
    // Si no se envían los parámetros requeridos, redirige
    header("Location: ../views/conf_usuarios.php?msg=Datos incompletos para la eliminación");
    exit();
}
?>
