<?php
// ---------------- CONFIGURACIÓN INICIAL ----------------
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require __DIR__ . '/vendor/autoload.php'; // Ajusta la ruta si es necesario

$mensaje = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $correo_destino = $_POST['destino'] ?? '';

    if (!empty($correo_destino)) {
        try {
            $mail = new PHPMailer(true);

            // Configuración del servidor SMTP
            $mail->isSMTP();
            $mail->Host       = 'mail.intranetdrg.com.mx'; // Cambia según tu servidor
            $mail->SMTPAuth   = true;
            $mail->Username   = 'administrador@intranetdrg.com.mx'; // Usuario SMTP
            $mail->Password   = 'WbrE5%7p'; // ⚠️ Mejor usar variable de entorno
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            $mail->Port       = 465;
            $mail->CharSet    = 'UTF-8';

            // Remitente y destinatario
            $mail->setFrom('administrador@intranetdrg.com.mx', 'Pruebas de correo');
            $mail->addAddress($correo_destino);

            // Contenido del correo
            $mail->isHTML(true);
            $mail->Subject = 'Correo de prueba con diseño';
            
            // 🔹 Aquí modificas el diseño del correo
            $mail->Body = '
            <!DOCTYPE html>
            <html>
            <head>
              <meta charset="UTF-8">
              <style>
                body { font-family: Arial, sans-serif; background:#f4f4f4; margin:0; padding:20px; }
                .contenedor { background:#fff; padding:20px; border-radius:8px; }
                h1 { color:#2c3e50; }
                p { color:#555; }
                .boton { display:inline-block; padding:10px 20px; background:#3498db; color:#fff; text-decoration:none; border-radius:5px; }
              </style>
            </head>
            <body>
              <div class="contenedor">
                <h1>¡Hola!</h1>
                <p>Este es un <b>correo de prueba</b> para que ajustes el diseño.</p>
                <a href="https://tusitio.com" class="boton">Visitar sitio</a>
              </div>
            </body>
            </html>
            ';

            $mail->AltBody = 'Este es un correo de prueba en texto plano.';

            $mail->send();
            $mensaje = '<p style="color:green;">✅ Correo enviado con éxito a ' . htmlspecialchars($correo_destino) . '</p>';
        } catch (Exception $e) {
            $mensaje = '<p style="color:red;">❌ Error al enviar: ' . $mail->ErrorInfo . '</p>';
        }
    } else {
        $mensaje = '<p style="color:red;">❌ Ingresa un correo válido.</p>';
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Prueba de correo</title>
</head>
<body style="font-family:Arial, sans-serif; margin:30px;">
  <h2>Enviar correo de prueba</h2>
  <?= $mensaje ?>
  <form method="post">
    <label>Correo de destino:</label><br>
    <input type="email" name="destino" required style="padding:8px; width:300px;">
    <br><br>
    <button type="submit" style="padding:10px 20px;">Enviar correo</button>
  </form>
</body>
</html>
