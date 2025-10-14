<?php
session_start();

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require '../vendor/autoload.php';
require("../config/db.php");

$nombre = $_SESSION['nombre'];

if (!isset($_SESSION['loggedin']) || !$_SESSION['loggedin']) {
    header("Location: ../index.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Captura los datos del formulario
    
    // Datos básicos
    $fecha = date('Y-m-d');
    $solicitante = $nombre;
    $no_cliente = $_POST['no_cliente'];
    $folio = $_POST['folio'];
    $nombre_cliente = $_POST['nombre_cliente'];
    $uso = $_POST['uso'];
    $sucursal = $_POST['sucursal'];
    $observaciones = $_POST['observaciones'];
    
    // Datos de la tabla dinámica (arrays)
    $sku = $_POST['sku'];
    $descripcion = $_POST['descripcion'];
    $cantidad = $_POST['cantidad'];
    $nota = $_POST['nota'];
    
    //Separarlos por ";"
    $sku_str = implode(';', $sku);
    $descripcion_str = implode(';', $descripcion);
    $cantidad_str = implode(';', $cantidad);
    $nota_str = implode(';', $nota);
    
    $result = GuardarPedido($conn,$fecha,$solicitante,$no_cliente,$nombre_cliente,$uso,$sucursal,$observaciones,$sku_str,$descripcion_str,$cantidad_str,$nota_str);

    header('Content-Type: application/json');
    echo json_encode($result);
    
    // Solo enviar correos si la respuesta es exitosa
    if ($result['success']) {
        enviarCorreos($conn,$folio,$fecha,$solicitante,$no_cliente,$nombre_cliente,$uso,$sucursal);
    }
        
} else {
    echo json_encode(['success' => false, 'message' => 'Método de solicitud no válido.']);
}

//Funcion para traer el Id de las Sucursales
function SucursalID($conn, $sucursal) {
    $sql = "SELECT sucursal FROM sucursales WHERE id = '$sucursal'";
    $result = $conn->query($sql);
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        return $row['sucursal']; // Retorna solo el valor del campo 'sucursal'
    } else {
        return null; // Retorna null si no se encontró ningún registro
    }
}

//Funcion para traer el Id del Uso
function UsoID($conn, $uso) {
    $sql = "SELECT nombre FROM uso WHERE id = '$uso'";
    $result = $conn->query($sql);
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        return $row['nombre']; // Retorna solo el valor del campo 'nombre'
    } else {
        return null; // Retorna null si no se encontró ningún registro
    }
}

//Funcion para guardar el Nuevo Pedido
function GuardarPedido($conn, $fecha, $solicitante, $no_cliente, $nombre_cliente, $uso, $sucursal, $observaciones, $sku, $descripcion, $cantidad, $nota) {
    
    // Verificar si existen usuarios autorizados para el programa
    $sqlAuto = "SELECT * FROM permisos WHERE id_programa = 14 AND acceso = 1";
    $result = $conn->query($sqlAuto);
    
    if ($result->num_rows > 0) {
        // Si hay usuarios autorizados, continuar con el proceso de guardar el pedido

        // Traer el folio disponible
        $folio = obtenerFolio($conn);
        $estatus = "Nuevo";
        $nuevoFolio = $folio;
        
        // Consulta de inserción
        $sql = "INSERT INTO pedidos_especiales
                (folio, fecha, solicitante, numero_cliente, nombre_cliente, uso, sucursal, observaciones, estatus, sku, descripcion, cantidad, nota) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        // Actualizar el folio en el control de folios
        $stmt = $conn->prepare("UPDATE control_folios_pe SET folio = ? WHERE id = 1");
        $stmt->bind_param("i", $nuevoFolio);
        $stmt->execute();
        
        // Preparar la consulta de inserción
        if ($stmt = $conn->prepare($sql)) {
            // Bind de parámetros
            $stmt->bind_param('sssssssssssss', $folio, $fecha, $solicitante, $no_cliente, $nombre_cliente, $uso, $sucursal, $observaciones, $estatus, $sku, $descripcion, $cantidad, $nota);
            
            // Ejecutar la consulta
            if ($stmt->execute()) {
                $stmt->close(); // Cierra la declaración
                return ['success' => true, 'message' => 'Pedido guardado exitosamente.'];
            } else {
                $stmt->close(); // Cierra la declaración
                return ['success' => false, 'message' => 'Error al guardar el Pedido: ' . $stmt->error];
            }
        } else {
            return ['success' => false, 'message' => 'Error en la preparación de la consulta: ' . $conn->error];
        }
    } else {
        // Si no hay usuarios autorizados, regresar un mensaje de error
        return ['success' => false, 'message' => 'Error al Guardar el Pedido, no hay Autorizadores Disponibles, favor de contactar al area de TI'];
    }
}


//Funcion para traer los Autorizadores de Pedidos
function UsuariosAutorizadores($conn) {
    $id_programa = 14; //Id del programa para procesar pedidos
    $sql = "SELECT u.email FROM usuarios u JOIN permisos p ON u.id = p.id_usuario WHERE id_programa = $id_programa AND acceso = 1";
    $result = $conn->query($sql);

    $emails = [];
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $emails[] = $row['email']; // Agrega cada correo al array
        }
    }
    return $emails; // Retorna un array (puede estar vacío si no hay resultados)
}

//Funcion para mandar correo de Nuevo Pedido
function enviarCorreos($conn,$folio,$fecha,$solicitante,$no_cliente,$nombre_cliente,$uso,$sucursal) {
    $autorizadores = UsuariosAutorizadores($conn);
    
    $sucursalF = SucursalID($conn,$sucursal);
    
    $usoF = UsoID($conn,$uso);
    
    $fechaSolicitud = new DateTime($fecha);
    $fmt = new IntlDateFormatter('es_ES', IntlDateFormatter::LONG, IntlDateFormatter::NONE);
    $fmt->setPattern('d MMMM yyyy');
    $fechaSolicitudFormateada = $fmt->format($fechaSolicitud);

    if (empty($autorizadores)) {
        echo "No se encontraron usuarios con rol 'Autorizador'.";
        return;
    }
    foreach ($autorizadores as $email) {
        $mail = new PHPMailer(true);
        try {
            //Configuracion del Servidor
            $mail->isSMTP();
            $mail->Host = 'mail.intranetdrg.com.mx';
            $mail->SMTPAuth = true;
            $mail->Username = 'notification@intranetdrg.com.mx'; // Cambia esto por tu dirección de correo electrónico que se encargará de enviar los correos
            $mail->Password = 'r-eHQi64a7!3QT9'; // Cambia esto por la contraseña del correo electrónico
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            $mail->IsHTML(true);  
            $mail->CharSet = 'UTF-8';
            $mail->Port = 465; // Cambia esto al puerto SMTP correspondiente

            // Configuración del remitente y destinatario
            $mail->setFrom('notification@intranetdrg.com.mx', 'DRG Notification');//Quien envia el Correo
            $mail->addAddress($email); // Correo del destinatario

            // Contenido del correo
            $mail->isHTML(true);
            $mail->Subject = 'Nuevo Pedido Especial' ;
            $mail->Body = "
            <html>
            <head>
                <style>
                    body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                    h1 { color: #2c3e50; border-bottom: 2px solid #3498db; padding-bottom: 10px; }
                    h2 { color: #2980b9; }
                    strong { color: #2c3e50; }
                    .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                    .info-row { margin-bottom: 10px; }
                    .label { font-weight: bold; color: #34495e; }
                    .value { margin-left: 10px; }
                    .logo { position: absolute; top: 20px; right: 100px; max-width: 300px; height: 250; width: 250px; }

                </style>
            </head>
            <body>
                <div class='container'>
                    <img src='https://administrador.intranetdrg.com.mx/img/logo-drg.png' alt='Logo' class='logo'>
                    <h1>Nuevo Pedido Especial</h1>
                    <h2>Solicitante: <strong>$solicitante</strong></h2>
                    
                    <div class='info-row'>
                        <span class='label'>Fecha de Solicitud:</span>
                        <span class='value'>$fechaSolicitudFormateada</span>
                    </div>
                    
                    <div class='info-row'>
                        <span class='label'>Numero de Cliente:</span>
                        <span class='value'>$no_cliente</span>
                    </div>
                    
                    <div class='info-row'>
                        <span class='label'>Nombre del Cliente:</span>
                        <span class='value'>$nombre_cliente</span>
                    </div>
                    
                    
                    <div class='info-row'>
                        <span class='label'>Uso:</span>
                        <span class='value'>$usoF</span>
                    </div>
                    
                    <div class='info-row'>
                        <span class='label'>Sucursal:</span>
                        <span class='value'>$sucursalF</span>
                    </div>
                    
                    <h1>Verificar Pedido en el Portal</h1>

                </div>
            </body>
            </html>
            ";

            // Enviar el correo
            $mail->send();
        } catch (Exception $e) {
            echo "Error al enviar correo a $email: {$mail->ErrorInfo}<br>";
        }
    }
}

//Function para obtener el folio
function obtenerFolio($conn) {

    // Consulta SQL para obtener el folio basado en el ID del pedido
    $sql = "SELECT folio FROM control_folios_pe WHERE id = 1 FOR UPDATE";
    $resultado = $conn->query($sql);

    // Verificar si hay resultados
    if ($resultado->num_rows > 0) {
        $fila = $resultado->fetch_assoc();
        $ultimo_folio = $fila['folio'];

        // Incrementar el folio
        if ($ultimo_folio !== null) {
            $ultimo_folio++;
        } else {
            $ultimo_folio = 1; // Si no hay registros, el folio comienza en 1
        }

        // Formatear el número con ceros a la izquierda
        $folio_formateado = sprintf('%09d', $ultimo_folio);
        return $folio_formateado;
    } else {
        return sprintf('%09d', 1); // Si no se encontró ningún registro, comenzamos en 1 y lo formateamos
    }
}

?>