<?php
session_start();

$session_lifetime = 300;
$_SESSION['login_time'] = time();
$_SESSION['expire_time'] = $_SESSION['login_time'] + $session_lifetime;

require "config/db.php"; // Incluye el archivo de conexión a la base de datos

// Obtener datos del formulario
$email = isset($_POST['email']) ? $_POST['email'] : '';
$pass = isset($_POST['password']) ? $_POST['password'] : '';

// Verificar que los campos no estén vacíos
if (empty($email) || empty($pass)) {
    echo "<script>alert('Por favor, complete todos los campos.'); window.location.href='index.php';</script>";
    exit();
}

try {
    // Preparar y ejecutar la consulta SQL
    $sql = "SELECT id, nombre, email, password, rol
            FROM usuarios 
            WHERE email = ? AND password = ?";
    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        die("Error al preparar la consulta: " . $conn->error);
    }

    $stmt->bind_param("ss", $email, $pass);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        // Solo se necesita una fila
        $row = $result->fetch_assoc();

        // Verificar contraseña (sin hash)
        if ($pass === $row['password']) {
            // Iniciar sesión exitosa, guardar información en la sesión
            $_SESSION['usuario_id'] = $row['id']; // Corregir índice a 'id'
            $_SESSION['nombre'] = $row['nombre'];
            $_SESSION['email'] = $row['email'];
            $_SESSION['rol'] = $row['rol'];
            $_SESSION['loggedin'] = true;

            // Registrar acción
            $sql = "INSERT INTO acciones (email, accion, fecha) VALUES (?, ?, NOW())";
            $stmt = $conn->prepare($sql);
            $accion = "Inicio de sesión";
            $stmt->bind_param("ss", $email, $accion);
            $stmt->execute();

            // Redirigir a transferencias.php
            header("Location: transferencias.php");
            exit();
        } else {
            echo "<script>alert('Contraseña incorrecta.'); window.location.href='index.php';</script>";
        }
    } else {
        echo "<script>alert('Usuario no encontrado.'); window.location.href='index.php';</script>";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
    exit();
}

// Cerrar la conexión
$conn->close();
?>
