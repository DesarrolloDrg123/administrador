<?php
session_start();
require("../config/db.php");
require("../vendor/autoload.php");

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if (!isset($_SESSION['loggedin']) || !$_SESSION['loggedin']) {
    echo "sin_sesion";
    exit;
}

if (empty($_POST['folio']) || empty($_POST['motivo'])) {
    echo "datos_incompletos";
    exit;
}

$folio  = $_POST['folio'];
$motivo = $_POST['motivo'];

// ==============================
// 1. Obtener datos de la transferencia
// ==============================
$stmt = $conn->prepare("
    SELECT 
        t.folio,
        t.descripcion,
        t.importe,
        t.importedls,
        t.estado,
        u_sol.email   AS email_solicitante,
        u_sol.nombre  AS nombre_solicitante,
        u_ben.email   AS email_beneficiario,
        u_ben.nombre  AS nombre_beneficiario
    FROM transferencias_clara_tcl t
    LEFT JOIN usuarios u_sol ON t.usuario_solicitante_id = u_sol.id
    LEFT JOIN usuarios u_ben ON t.beneficiario_id = u_ben.id
    WHERE t.folio = ?
    LIMIT 1
");

$stmt->bind_param("s", $folio);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows === 0) {
    echo "no_encontrado";
    exit;
}

$info = $res->fetch_assoc();
$stmt->close();

// ==============================
// 2. Actualizar estatus y motivo
// ==============================
$stmtUpd = $conn->prepare("
    UPDATE transferencias_clara_tcl
    SET estado = 'Cancelada',
        motivo = ?
    WHERE folio = ?
");

$stmtUpd->bind_param("ss", $motivo, $folio);

if (!$stmtUpd->execute()) {
    echo "error_db";
    exit;
}
$stmtUpd->close();

// ==============================
// 3. Enviar correos
// ==============================
function enviarCorreo($para, $asunto, $html) {
    if (empty($para)) return;

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

        $mail->setFrom('notification@intranetdrg.com.mx', 'DRG - Transferencias');
        $mail->addAddress($para);

        $mail->isHTML(true);
        $mail->Subject = $asunto;
        $mail->Body    = $html;
        $mail->send();
    } catch (Exception $e) {
        error_log("Error correo: " . $mail->ErrorInfo);
    }
}

// Calcular importe correcto
$importe = (!empty($info['importedls']) && $info['importedls'] != '0.00') 
            ? $info['importedls'] 
            : $info['importe'];

$asunto = "Transferencia cancelada - Folio {$folio}";

// Correo para el solicitante
$htmlSolicitante = "
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
<html>
<body style='font-family:Arial;'>
    <img src='https://administrador.intranetdrg.com.mx/img/logo-drg.png' alt='Logo' class='logo'>
    <h2>Transferencia Cancelada</h2>
    <p>Hola <strong>{$info['nombre_solicitante']}</strong>,</p>
    <p>Tu transferencia fue <strong>cancelada</strong>.</p>

    <p><b>Folio:</b> {$folio}</p>
    <p><b>Descripción:</b> {$info['descripcion']}</p>
    <p><b>Importe:</b> $" . number_format($importe,2) . "</p>

    <p><b>Motivo de la cancelación:</b></p>
    <p style='background:#f2f2f2;padding:10px;border-left:4px solid red;'>{$motivo}</p>
</body>
</html>
";

// Correo para el beneficiario
$htmlBeneficiario = "
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
<html>
<body style='font-family:Arial;'>
    <img src='https://administrador.intranetdrg.com.mx/img/logo-drg.png' alt='Logo' class='logo'>
    <h2>Transferencia Cancelada</h2>
    <p>Hola <strong>{$info['nombre_beneficiario']}</strong>,</p>
    <p>La transferencia asociada fue <strong>cancelada</strong>.</p>

    <p><b>Folio:</b> {$folio}</p>
    <p><b>Descripción:</b> {$info['descripcion']}</p>
    <p><b>Importe:</b> $" . number_format($importe,2) . "</p>
</body>
</html>
";

// Envío de correos
enviarCorreo($info['email_solicitante'], $asunto, $htmlSolicitante);
enviarCorreo($info['email_beneficiario'], $asunto, $htmlBeneficiario);

echo "success";
?>
