<?php
session_start();

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require '../vendor/autoload.php';

$nombre = $_SESSION['nombre'];

require("../config/db.php");

if (!isset($_SESSION['loggedin']) || !$_SESSION['loggedin']) {
    header("Location: ../index.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Captura los datos del formulario
    
    // Datos básicos
    $fecha = date('Y-m-d');
    $folio = $_POST['folio'];
    $devolucion = $_POST['devolucion'];
    
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
    
    $result = EditarPedido($conn,$fecha,$folio,$sku_str,$descripcion_str,$cantidad_str,$nota_str);
    
    header('Content-Type: application/json');
    echo json_encode($result);
    
    // Solo enviar correos si la respuesta es exitosa
    if ($result['success']) {
        enviarCorreos($conn,$folio,$fecha,$solicitante,$no_cliente,$nombre_cliente,$uso,$sucursal);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Método de solicitud no válido.']);
}

function enviarCorreos($conn,$folio,$fecha,$devolucion) {
    $autorizadores = UsuariosAutorizadores($conn);
    
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
            $mail->Subject = 'Pedido para Revisión - folio: '. $folio ;
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
                    .label { font-weight: bold; color: #34495e; font-size: 18px; }
                    .value { margin-left: 10px; font-size: 18px; }
                    .logo { position: absolute; top: 20px; right: 100px; max-width: 300px; height: 250; width: 250px; }

                </style>
            </head>
            <body>
                <div class='container'>
                    <img src='https://i.ibb.co/drvS4yF/logo-drg.png' alt='Logo' class='logo'>
                    <h1>Revision de Pedido Especial</h1>
                    <h2>Solicitud: <strong>Devuelta</strong></h2>
                    
                    <div class='info-row'>
                        <span class='label'>Fecha de Edicion:</span>
                        <span class='value'>$fechaSolicitudFormateada</span>
                    </div>
                    
                    <div class='info-row'>
                        <span class='label'>El pedido a sido modificado, favor de Revisar</span>
                    </div>
                    
                    <div class='info-row'>
                        <span class='label'>El pedido fue devuelto con el motivo:</span>
                        <span class='value'>$devolucion</span>
                    </div>
                    
                    <h1>Confirmar Pedido en el Portal</h1>

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

function EditarPedido($conn, $fecha, $folio, $sku, $descripcion, $cantidad, $nota) {
    $estatus = "Por Revisar";
    $limpiarmotivo = "";
    
    // Verificar si existen usuarios autorizados para el programa
    $sqlAuto = "SELECT * FROM permisos WHERE id_programa = 14 AND acceso = 1";
    $result = $conn->query($sqlAuto);
    
    if ($result->num_rows > 0) {
        
    // Consulta de actualización
        $sql = "UPDATE pedidos_especiales 
                SET fecha = ?, sku = ?, descripcion = ?, cantidad = ?, nota = ?, estatus = ?, motivo_devolucion = ? 
                WHERE folio = ?";
    
        // Preparar la consulta
        if ($stmt = $conn->prepare($sql)) {
            // Bind de parámetros
            $stmt->bind_param('ssssssss', $fecha, $sku, $descripcion, $cantidad, $nota, $estatus, $limpiarmotivo, $folio);
            
            // Ejecutar la consulta
            if ($stmt->execute()) {
                $stmt->close(); // Cierra la declaración
                return ['success' => true, 'message' => 'Pedido actualizado exitosamente.'];
            } else {
                $stmt->close(); // Cierra la declaración
                return ['success' => false, 'message' => 'Error al actualizar el Pedido: ' . $stmt->error];
            }
        } else {
            return ['success' => false, 'message' => 'Error en la preparación de la consulta: ' . $conn->error];
        }
    } else {
        // Si no hay usuarios autorizados, regresar un mensaje de error
        return ['success' => false, 'message' => 'Error al Editar el Pedido, no hay Autorizadores Disponibles'];
    }
}

?>