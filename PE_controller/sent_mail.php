<?php 

try{
    // Configuración del correo electrónico
    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host = 'mail.intranetdrg.com.mx';
    $mail->SMTPAuth = true;
    $mail->Username = 'notification@intranetdrg.com.mx'; // Cambia esto por tu dirección de correo electrónico que se encargará de enviar los correos
    $mail->Password = 'r-eHQi64a7!3QT9'; // Cambia esto por la contraseña del correo electrónico
    $mail->SMTPSecure = 'ssl';
    $mail->IsHTML(true);  
    $mail->CharSet = 'UTF-8';
    $mail->Port = 465; // Cambia esto al puerto SMTP correspondiente

    // Generar un código de 5 caracteres
    $codigo = generarCodigo(5);
    $_SESSION['codigo'] = $codigo;

    // Redaccion del correo
    $mail->setFrom('notification@intranetdrg.com.mx', 'DRG Notification');//Quien envia el Correo
    $mail->addAddress($usuario);//a quien se le manda el correo
    $mail->Subject = "Restablecimiento de Contraseña - Power BI";//Asunto del correo
    $mail->Body = '
            <html lang="es">
            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <title>Código de Verificación</title>
                <style>
                    body {
                        font-family: Arial, sans-serif;
                        line-height: 1.6;
                        color: #333;
                    }
                    .container {
                        max-width: 600px;
                        margin: 0 auto;
                        padding: 20px;
                        background-color: #f9f9f9;
                        border: 1px solid #ddd;
                        border-radius: 5px;
                    }
                    .header {
                        font-size: 24px;
                        margin-bottom: 20px;
                        color: #007bff;
                    }
                    .code {
                        font-size: 24px;
                        font-weight: bold;
                        color: #4CAF50;
                        margin: 20px 0;
                    }
                    .footer {
                        margin-top: 30px;
                        font-size: 14px;
                        color: #555;
                    }
                </style>
            </head>
            <body>
                <div class="container">
                    <div class="header">
                        Estimado/a '. $result->nombre.' '. $result->apellidos.',
                    </div>
                    <p>Recibimos tu solicitud para cambiar la contraseña de tu cuenta. Para completar este proceso, por favor utiliza el siguiente código de verificación:</p>
                    <div class="code">
                        '.$codigo.'
                    </div>
                    <p>Si no reconoces esta operación, por favor haz caso omiso o envía un correo a recepcionfacturas@drg.mx.</p>
                    <p>Para proceder, ingresa el código en el campo correspondiente.</p>
                </div>
            </body>
            </html>
        ';

    if(!$mail->send()) {
        //si no se mando
        echo json_encode(array('result' => 3));
        //echo 'Error al enviar el correo electrónico: ', $mail->ErrorInfo;

    } else {
        //si se mando
        echo json_encode(array('result' => 1));
        //echo 'El correo electrónico se envió correctamente.';
        
    }
} catch (Exception $e) {
    echo 'Error al enviar el correo electrónico: ', $mail->ErrorInfo;
}



























?>