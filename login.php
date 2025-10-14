<?php
session_start();
require "config/db.php";

// Obtener datos del formulario
$email = isset($_POST['email']) ? trim($_POST['email']) : '';
$pass = isset($_POST['contra']) ? trim($_POST['contra']) : '';

// Verificar que los campos no estén vacíos
if (empty($email) || empty($pass)) {
    $_SESSION['error'] = 'Por favor, complete todos los campos.';
    header("Location: index.php");
    exit();
}


// Validar que el email tenga un formato correcto
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $_SESSION['error'] = 'Por favor, introduzca un correo electrónico válido.';
    header("Location: index.php");
    exit();
}

try {
    // Preparar la consulta SQL
    $sql = "SELECT id, nombre, email, password, rol, departamento, fecha_ingreso, estatus, jefe_directo, puesto FROM usuarios WHERE email = ?";
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
            
            // Verificar el estatus del usuario
            if ($row['estatus'] == 1) {  // Usuario activo
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
                $_SESSION['jefe'] = $row['jefe_directo'];
                $_SESSION['puesto'] = $row['puesto'];
                
                //Traemos y asignamos los permisos de programas
                $sql1 = "SELECT 
                        pe.id AS id_permiso,
                        pe.id_usuario,
                        pe.acceso,
                        pr.id AS id_programa,
                        pr.nombre_programa,
                        pr.descripcion,
                        pr.estatus,
                        pr.categoria
                    FROM 
                        permisos pe
                    JOIN
                        programas pr
                    ON pe.id_programa = pr.id
                    WHERE pe.id_usuario = ? AND pe.acceso = 1 AND pr.estatus = 1
                    ORDER BY pr.categoria ASC, pr.id ASC"; //Checar los permisos por el id del usuario, ver si lo tiene activo y esta disponible
                    
                $stmt1 = $conn->prepare($sql1);
                $stmt1->bind_param("i", $_SESSION['usuario_id']);
                $stmt1->execute();
                $result1 = $stmt1->get_result();
                
                // Crear un array para almacenar los permisos
                $_SESSION['permisos'] = [];

                if ($result1->num_rows > 0) {
                    while ($row1 = $result1->fetch_assoc()) {
                        $categoria = $row1['categoria']; // Agrupar por categoría
                        
                        if (!isset($_SESSION['permisos'][$categoria])) {
                            $_SESSION['permisos'][$categoria] = []; // Crear categoría si no existe
                        }
                
                        $_SESSION['permisos'][$categoria][] = [
                            'nombre_programa' => $row1['descripcion'], // Nombre del programa
                            'url' => $row1['nombre_programa'] // URL del programa
                        ];
                    }
                }
                
                $fecha_ingreso = new DateTime($_SESSION['ingreso']);
                $fecha_actual = new DateTime(); // Fecha de hoy: 2025-10-06
                
                // Calcula la diferencia exacta entre las dos fechas
                $diferencia = $fecha_ingreso->diff($fecha_actual);
                
                $antiguedad_decimal = $diferencia->days / 365;
                
                // Guardamos este valor preciso en la sesión. ¡Este es el que usaremos!
                $_SESSION['antiguedad_decimal'] = $antiguedad_decimal;
                
                // (Opcional) Puedes conservar los valores antiguos si los usas en otro lugar
                $_SESSION['antiguedad_años'] = $diferencia->y; // Años enteros
                $_SESSION['antiguedad_dias'] = $diferencia->days; // Días totales

                // Asegurarse de que la sesión se guarde antes de redirigir
                session_write_close();

                // Redirigir al usuario según su rol o departamentos
                if ($row) {
                    header("Location: inicio.php");
                } else {
                    header("Location: inicio.php");
                }
                exit(); // Asegurarse de detener la ejecución después de la redirección
            } else {
                // Usuario inactivo, no permitir login
                $_SESSION['error'] = 'Tu cuenta está inactiva. Contacta con el administrador.';
                header("Location: index.php");
                exit();
            }
        } else {
            $_SESSION['error'] = 'Contraseña incorrecta.';
            header("Location: index.php");
            exit();
        }
    } else {
        $_SESSION['error'] = 'Usuario no encontrado.';
        header("Location: index.php");
        exit();
    }
} catch (Exception $e) {
    // Loguear el error en lugar de mostrarlo al usuario
    error_log("Error en el script de login: " . $e->getMessage());
    $_SESSION['error'] = 'Ocurrió un error. Por favor, inténtelo de nuevo más tarde.';
    header("Location: index.php");
    exit();
}

// Cerrar la conexión en caso de error
if (isset($stmt)) {
    $stmt->close();
}
$conn->close();
?>
