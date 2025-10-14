<?php
session_start();
include $_SERVER['DOCUMENT_ROOT']."/config/config.php";

// Mostrar errores para depuración (solo en desarrollo)
if (ini_get('display_errors')) {
    ini_set('display_errors', 0);
}

// Obtener datos del formulario
$email = isset($_POST['email']) ? trim($_POST['email']) : '';
$pass = isset($_POST['contra']) ? trim($_POST['contra']) : '';

// Verificar que los campos no estén vacíos
if (empty($email) || empty($pass)) {
    $_SESSION['error'] = 'Por favor, complete todos los campos.';
    header("Location: ../index.php");
    exit();
}

// Validar que el email tenga un formato correcto
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $_SESSION['error'] = 'Por favor, introduzca un correo electrónico válido.';
    header("Location: ../index.php");
    exit();
}

try {
    // Preparar la consulta SQL
    $sql = "SELECT id, nombre, email, password, rol, departamento, fecha_ingreso FROM usuarios WHERE email = ?";
    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        throw new Exception("Error al preparar la consulta: " . $conn->error);
    }

    // Vincular los parámetros y ejecutar la consulta
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $fecha_mysql = $row['fecha_ingreso'];
        
        $fecha = new DateTime($fecha_mysql);

        // Verificar la contraseña utilizando password_verify()
        if (password_verify($pass, $row['password'])) {
            // Regenerar ID de sesión para evitar secuestro de sesión
            session_regenerate_id(true);

            // Almacenar los detalles del usuario en la sesión
            
            $_SESSION['usuario_id'] = $row['id'];
            $_SESSION['nombre'] = $row['nombre'];
            $_SESSION['email'] = $row['email'];
            $_SESSION['rol'] = $row['rol'];
            $_SESSION['departamento'] = $row['departamento'];
            $_SESSION['loggedin'] = true;
            $_SESSION['ingreso'] = $row['fecha_ingreso'];
            
            // Convertir la fecha de ingreso a un objeto DateTime
            $fecha = new DateTime($_SESSION['ingreso']);
            $fecha_ingreso = new DateTime();
            
            // Usar el objeto DateTime en lugar del string
            $antiguedad = $fecha->diff($fecha_ingreso)->y;
            $_SESSION['antiguedad'] = $antiguedad;



            // Asegurarse de que la sesión se guarde antes de redirigir
            session_write_close();

            // Redirigir al usuario según su rol o departamentos
            if ($row['rol'] == 'admin') {
                header("Location: ../views/todos_PedidosE.php");

            }
            else{
                header("Location: ../views/mis_pedidos.php");
            }
            
            exit(); // Asegurarse de detener la ejecución después de la redirección
        } else {
            $_SESSION['error'] = 'Contraseña incorrecta.';
            header("Location: ../index.php");
            exit();
        }
    } else {

        $_SESSION['error'] = 'Usuario no encontrado.';
        header("Location: ../index.php");
        exit();
    }
} catch (Exception $e) {
    // Loguear el error en lugar de mostrarlo al usuario
    error_log("Error en el script de login: " . $e->getMessage());
    $_SESSION['error'] = 'Ocurrió un error. Por favor, inténtelo de nuevo más tarde.';
    header("Location: ../index.php");
    exit();
}

// Cerrar la conexión en caso de error
if (isset($stmt)) {
    $stmt->close();
}
$conn->close();
?>