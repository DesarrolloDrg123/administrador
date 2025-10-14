<?php
require("../config/db.php");

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require '../vendor/autoload.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $folio = $_POST['folio'];
    $operacion = $_POST['operacion'];
    
    if($operacion == "Autorizar") {
        $fecha = date('Y-m-d');
        $ordenCompra = $_POST['orden_compra'];
        $autorizador = $_POST['autorizador'];
        // Validar datos
        if (empty($ordenCompra)) {
            echo "Orden de Compra no proporcionado.";
            exit();
        }
        // Realizar la actualizaci贸n
        $sql = "UPDATE pedidos_especiales SET oc = ?, estatus = 'Procesado', autorizado_por = ?, fecha_autorizacion = ?, motivo_devolucion = '' WHERE folio = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssss", $ordenCompra, $autorizador, $fecha, $folio);
        
        if ($stmt->execute()) {
            echo "Registro actualizado correctamente.";
            $sinMotivo = "";
            enviarCorreo($conn,$operacion,$folio,$ordenCompra);
        } else {
            echo "Error al actualizar el registro: " . $stmt->error;
        }
        
    } else if($operacion == "Devolver") {
        $devuelto = $_POST['motivo_devolucion'];
        // Validar datos
        if (empty($devuelto)) {
            echo "Motivo no proporcionado.";
            exit();
        }
        // Realizar la actualizaci贸n
        $sql = "UPDATE pedidos_especiales SET motivo_devolucion = ?, estatus = 'Devuelto' WHERE folio = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ss", $devuelto, $folio);
        
        if ($stmt->execute()) {
            echo "Registro actualizado correctamente.";
            enviarCorreo($conn,$operacion,$folio,$devuelto);
        } else {
            echo "Error al actualizar el registro: " . $stmt->error;
        }
        
    } else if($operacion == "Rechazar") {
        $rechazo = $_POST['motivo_rechazo'];
        // Validar datos
        if (empty($rechazo)) {
            echo "Motivo no proporcionado.";
            exit();
        }
        // Realizar la actualizaci贸n
        $sql = "UPDATE pedidos_especiales SET motivo_rechazo = ?, estatus = 'Rechazado' WHERE folio = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ss", $rechazo, $folio);
        
        if ($stmt->execute()) {
            echo "Registro actualizado correctamente.";
            enviarCorreo($conn,$operacion,$folio,$rechazo);
        } else {
            echo "Error al actualizar el registro: " . $stmt->error;
        }
        
    } else if($operacion == "Actualizar") {
    $ordenCompra = $_POST['orden_compra'];

    // Validar que la orden de compra no esté vacía
    if (empty($ordenCompra)) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'La Orden de Compra no puede estar vacía.']);
        exit();
    }

    // Preparamos la consulta SQL para actualizar ÚNICAMENTE la OC
    $sql = "UPDATE pedidos_especiales SET oc = ? WHERE folio = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $ordenCompra, $folio);

    // Enviamos la respuesta en formato JSON
    header('Content-Type: application/json');

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Orden de Compra actualizada correctamente.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error al actualizar la Orden de Compra.']);
    }
    // Termina la ejecución para esta operación
    exit();
}
    
    $stmt->close();
    $conn->close();
}

function obtenerCorreo($conn, $folio) {
    $sql = "SELECT u.email FROM usuarios u JOIN pedidos_especiales pe ON u.nombre = pe.solicitante WHERE pe.folio = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $folio);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        return $row['correo_solicitante'];
    }
    return null; // Devuelve null si no se encuentra
}

function enviarCorreo($conn,$operacion,$folio,$motivo) {
    
    $correo = obtenerCorreo($conn,$folio);
    
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
        $mail->addAddress($correo); // Correo del destinatario

        // Contenido del correo
        switch($operacion){
            case "Autorizar":
                $mail->Subject = 'Pedido Procesado - Folio: '. $folio ;
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
                        <h1>Pedido Especial - $folio</h1>
                        <h2>Solicitud: <strong>Procesada</strong></h2>
                        
                        <div class='info-row'>
                            <span class='label'>Tu solicitud a sido procesada con la siguiente Orden de Compra</span>
                        </div>
                        
                        <div class='info-row'>
                            <span class='label'>Orden de Compra:</span>
                            <span class='value'>$motivo</span>
                        </div>
                        
                        <h1>Confirmar Orden en el Portal</h1>
    
                    </div>
                </body>
                </html>
                ";
                break;
            case "Rechazar":
                $mail->Subject = 'Pedido Rechazado - Folio: '. $folio ;
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
                        .value { margin-left: 10px; font-size: 18px;}
                        .logo { position: absolute; top: 20px; right: 100px; max-width: 300px; height: 250; width: 250px; }
    
                    </style>
                </head>
                <body>
                    <div class='container'>
                        <img src='https://administrador.intranetdrg.com.mx/img/logo-drg.png' alt='Logo' class='logo'>
                        <h1>Pedido Especial - $folio</h1>
                        <h2>Solicitud: <strong>Rechazada</strong></h2>
                        
                        <div class='info-row'>
                            <span class='label'>Tu solicitud a sido Rechazada por el siguiente motivo</span>
                        </div>
                        
                        <div class='info-row'>
                            <span class='label'>Motivo:</span>
                            <span class='value'>$motivo</span>
                        </div>
                        
                        <h1>Confirmar Pedido en el Portal</h1>
    
                    </div>
                </body>
                </html>
                ";
                break;
            case "Devolver":
                $mail->Subject = 'Pedido Devuelto - Folio: '. $folio ;
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
                        .label { font-weight: bold; color: #34495e; font-size: 18px;}
                        .value { margin-left: 10px; font-size: 18px;}
                        .logo { position: absolute; top: 20px; right: 100px; max-width: 300px; height: 250; width: 250px; }
    
                    </style>
                </head>
                <body>
                    <div class='container'>
                        <img src='https://i.ibb.co/drvS4yF/logo-drg.png' alt='Logo' class='logo'>
                        <h1>Pedido Especial - $folio</h1>
                        <h2>Solicitud: <strong>Devuelta</strong></h2>
                        
                        <div class='info-row'>
                            <span class='label'>Tu solicitud a sido Devuelta por el siguiente motivo</span>
                        </div>
                        
                        <div class='info-row'>
                            <span class='label'>Motivo:</span>
                            <span class='value'>$motivo</span>
                        </div>
                        
                        <h1>Confirmar Pedido en el Portal</h1>
    
                    </div>
                </body>
                </html>
                ";
                break;
        }
        
        // Enviar el correo
        $mail->send();
    } catch (Exception $e) {
        echo "Error al enviar correo a $email: {$mail->ErrorInfo}<br>";
    }
        
}

?>
