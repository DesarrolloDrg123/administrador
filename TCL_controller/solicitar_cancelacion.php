<?php
session_start();
header('Content-Type: application/json');

require("../config/db.php");
require '../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$response = ['success' => false, 'message' => ''];

// ===============================
// Función para obtener correos de usuarios con permiso al programa
// ===============================
function obtenerCorreosCanceladores($conn) {
    // ID del programa de Cancelación de Transferencias (ajústalo)
    $id_programa_cancelacion = 46;

    $sql = "SELECT u.email 
            FROM usuarios u 
            JOIN permisos p ON u.id = p.id_usuario 
            WHERE p.id_programa = ? AND p.acceso = 1  AND u.estatus = 1";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id_programa_cancelacion);
    $stmt->execute();

    $result = $stmt->get_result();

    $emails = [];
    while ($row = $result->fetch_assoc()) {
        $emails[] = $row['email'];
    }

    $stmt->close();
    return $emails;
}

// ===============================
// Función para enviar correo
// ===============================
function enviarCorreo($destinatarios, $asunto, $cuerpo_html) {
    if (empty($destinatarios)) return;

    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = 'mail.intranetdrg.com.mx';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'notification@intranetdrg.com.mx';
        $mail->Password   = 'r-eHQi64a7!3QT9';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port       = 465;
        $mail->CharSet    = 'UTF-8';

        $mail->setFrom('notification@intranetdrg.com.mx', 'DRG Notification');

        foreach ($destinatarios as $email) {
            $mail->addAddress($email);
        }

        $mail->isHTML(true);
        $mail->Subject = $asunto;
        $mail->Body    = $cuerpo_html;
        $mail->send();
    } catch (Exception $e) {
        error_log("Error al enviar correo: " . $mail->ErrorInfo);
    }
}

// ===============================
// Validaciones básicas
// ===============================
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    $response['message'] = 'Método no permitido';
    echo json_encode($response);
    exit;
}

if (!isset($_SESSION['usuario_id'])) {
    $response['message'] = 'Sesión no válida';
    echo json_encode($response);
    exit;
}

if (empty($_POST['folio']) || empty($_POST['motivo'])) {
    $response['message'] = 'Datos incompletos';
    echo json_encode($response);
    exit;
}

$folio  = $_POST['folio'];
$motivo = trim($_POST['motivo']);
$usuario_solicita = $_SESSION['nombre'] ?? 'Usuario';

// ===============================
// 1. Buscar info de la transferencia
// ===============================
$stmt = $conn->prepare("
    SELECT 
        folio, 
        descripcion, 
        importe, 
        importedls, 
        estado 
    FROM transferencias_clara_tcl
    WHERE folio = ?
    LIMIT 1
");
$stmt->bind_param("s", $folio);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    $response['message'] = 'Transferencia no encontrada';
    echo json_encode($response);
    exit;
}

$transferencia = $result->fetch_assoc();
$stmt->close();

$stmt = $conn->prepare("
    UPDATE transferencias_clara_tcl 
    SET motivo = ?,
    estado = 'Cancelacion Solicitada'
    WHERE folio = ?
");
$stmt->bind_param("ss", $motivo, $folio);
$stmt->execute();
$stmt->close();

// ===============================
// 2. Obtener correos con permiso
// ===============================
$correos = obtenerCorreosCanceladores($conn);

// ===============================
// 3. Construir correo
// ===============================
$importe = ($transferencia['importedls'] && $transferencia['importedls'] != "0.00") 
            ? $transferencia['importedls'] 
            : $transferencia['importe'];

$asunto = "Solicitud de cancelación de transferencia - Folio $folio";

$cuerpo = "
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
<div class='box'>
    <img src='https://administrador.intranetdrg.com.mx/img/logo-drg.png' alt='Logo' class='logo'>
    <h2>Solicitud de Cancelación de Transferencia</h2>
    <p>Se ha solicitado la cancelación de una transferencia:</p>

    <p><span class='label'>Folio:</span> {$transferencia['folio']}</p>
    <p><span class='label'>Descripción:</span> {$transferencia['descripcion']}</p>
    <p><span class='label'>Importe:</span> $" . number_format($importe,2) . "</p>
    <p><span class='label'>Estado actual:</span> {$transferencia['estado']}</p>
    <p><span class='label'>Solicitante:</span> {$usuario_solicita}</p>
    <hr>
    <p><strong>Motivo de la cancelación:</strong></p>
    <p style='background:#f8f8f8; padding:10px; border-left:4px solid #b30000;'>
        {$motivo}
    </p>

    <p style='text-align:center;'>Favor de ingresar al sistema para procesar la cancelación.</p>
</div>
</body>
</html>
";

// ===============================
// 4. Enviar correo
// ===============================
enviarCorreo($correos, $asunto, $cuerpo);

// ===============================
// 5. Respuesta
// ===============================
$response['success'] = true;
$response['message'] = 'Solicitud enviada correctamente';

echo json_encode($response);
