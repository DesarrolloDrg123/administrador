<?php
require "config/db.php";

session_start();

// Obtener datos del formulario de registro
$email = isset($_POST['email']) ? trim($_POST['email']) : '';
$password = isset($_POST['password']) ? trim($_POST['password']) : '';
$firstname = isset($_POST['firstname']) ? trim($_POST['firstname']) : '';
$lastname = isset($_POST['lastname']) ? trim($_POST['lastname']) : '';

// Verificar que todos los campos están completos
if (empty($email) || empty($password) || empty($firstname) || empty($lastname)) {
    echo "<script>alert(\"Por favor, complete todos los campos.\"); window.location.href='register.php';</script>";
    exit();
}

// Hashear la contraseña
$hashedPassword = password_hash($password, PASSWORD_DEFAULT);

// Asumimos que la conexión mysqli está en $conn
if (!isset($conn)) {
    die("La conexión a la base de datos no está disponible.");
}

try {
    // Preparar y ejecutar la consulta de inserción
    $sql = 'INSERT INTO logins (email, password, firstname, lastname) VALUES (?, ?, ?, ?)';
    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        throw new Exception($conn->error);
    }
    $stmt->bind_param("ssss", $email, $hashedPassword, $firstname, $lastname);
    if (!$stmt->execute()) {
        throw new Exception($stmt->error);
    }
    $stmt->close();

    // Registrar acción
    $sql = 'INSERT INTO acciones (email, accion, fecha) VALUES (?, ?, NOW())';
    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        throw new Exception($conn->error);
    }
    $accion = "Registro de usuario";
    $stmt->bind_param("ss", $email, $accion);
    if (!$stmt->execute()) {
        throw new Exception($stmt->error);
    }
    $stmt->close();

    // Redirigir al usuario
    echo "<script>alert(\"Registro exitoso.\"); window.location.href='index.php';</script>";
} catch (Exception $e) {
    echo "<script>alert(\"Error: " . $e->getMessage() . "\"); window.location.href='register.php';</script>";
    exit();
}

// Cerrar conexión
$conn->close();
?>