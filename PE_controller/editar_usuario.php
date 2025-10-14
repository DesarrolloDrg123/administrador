<?php
session_start();

$nombre = $_SESSION['nombre'];

include $_SERVER['DOCUMENT_ROOT']."/PE_controller/helpers.php";

if (!isset($_SESSION['loggedin']) || !$_SESSION['loggedin']) {
    header("Location: ../index.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $opcion = $_POST['opcion'];
    if($opcion == '1'){
        // Captura los datos del formulario
        $id = $_POST['id'];
        $nombre = $_POST['nombre'];
        //$apellidos = $_POST['apellido'];
        $correo = $_POST['correo'];
        $rol = $_POST['rol'];
        $contrasena1 = $_POST['password1'];
        $contrasena2 = $_POST['password2'];
    
        // Validar que las contraseñas coincidan
        if ($contrasena1 !== $contrasena2) {
            echo json_encode(['success' => false, 'message' => 'Las contraseñas no coinciden.']);
            exit; // Detiene el script
        }
    
        // Hashear la contraseña
        $passwordHash = password_hash($contrasena1, PASSWORD_DEFAULT);
    
        // Preparar consulta para actualizar el usuario
        $sql = "UPDATE usuarios SET 
                nombre = ?, 
                email = ?, 
                rol = ?, 
                password = ? 
                WHERE id = ?";
    
        // Preparar la consulta
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            // Vincular los parámetros
            $stmt->bind_param('ssssi', $nombre, $correo, $rol, $passwordHash, $id);
    
            // Ejecutar la consulta
            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Usuario actualizado correctamente.']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error al actualizar el usuario.']);
            }
    
            // Cerrar la consulta
            $stmt->close();
        } else {
            echo json_encode(['success' => false, 'message' => 'Error al preparar la consulta.']);
        }
    
        // Cerrar la conexión
        $conn->close();
    } else {
        // Captura los datos del formulario
        $nombre = $_POST['nombre'];
        $correo = $_POST['correo'];
        $rol = $_POST['rol'];
        $contrasena1 = $_POST['password1'];
        $contrasena2 = $_POST['password2'];
    
        // Validación de las contraseñas
        if ($contrasena1 !== $contrasena2) {
            echo json_encode(['success' => false, 'message' => 'Las contraseñas no coinciden.']);
            exit;
        }
    
        // Hashear la contraseña
        $hashed_password = password_hash($contrasena1, PASSWORD_DEFAULT);
    
        // Preparar la consulta SQL para insertar el nuevo usuario
        $sql = "INSERT INTO usuarios (nombre, email, rol, password) 
                VALUES (?, ?, ?, ?)";
    
        if ($stmt = $conn->prepare($sql)) {
            // Vincular los parámetros
            $stmt->bind_param("ssss", $nombre, $correo, $rol, $hashed_password);
    
            // Ejecutar la consulta
            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Usuario creado con éxito.']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error al insertar el usuario.']);
            }
    
            // Cerrar la declaración
            $stmt->close();
        } else {
            echo json_encode(['success' => false, 'message' => 'Error en la preparación de la consulta.']);
        }
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Método de solicitud no válido.']);
}

?>