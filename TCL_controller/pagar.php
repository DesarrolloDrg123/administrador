<?php
session_start();
if (!isset($_SESSION['loggedin']) || !$_SESSION['loggedin']) {
    header("Location: index.php");
    exit();
}

require("../config/db.php");
require("../vendor/autoload.php");

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if (!isset($_GET['id'])) {
    die('ID de transferencia no especificado.');
}

$solicitud_id_inicial = intval($_GET['id']);

$conn->begin_transaction();
try {
    // --- PASO 1: OBTENER EL FOLIO ---
    $stmt_folio = $conn->prepare("SELECT folio FROM transferencias_clara_tcl WHERE id = ?");
    $stmt_folio->bind_param("i", $solicitud_id_inicial);
    $stmt_folio->execute();
    $result_folio = $stmt_folio->get_result();

    if ($result_folio->num_rows === 0) {
        throw new Exception('Solicitud no encontrada.');
    }

    $folio_a_pagar = $result_folio->fetch_assoc()['folio'];
    $stmt_folio->close();

    // --- PASO 2: OBTENER TRANSFERENCIAS DEL FOLIO ---
    $sql_transferencias = "
        SELECT
            t.*,
            s.sucursal,
            b.nombre AS beneficiario,
            b.email AS email_beneficiario,
            u_sol.nombre AS nombre_solicitante,
            u_sol.email AS email_solicitante,
            u_aut.nombre AS nombre_autoriza
        FROM transferencias_clara_tcl t
        LEFT JOIN sucursales s ON t.sucursal_id = s.id
        LEFT JOIN usuarios b ON t.beneficiario_id = b.id
        LEFT JOIN usuarios u_sol ON t.usuario_solicitante_id = u_sol.id
        LEFT JOIN usuarios u_aut ON t.autorizacion_id = u_aut.id
        WHERE t.folio = ?
        FOR UPDATE
    ";

    $stmt = $conn->prepare($sql_transferencias);
    $stmt->bind_param("s", $folio_a_pagar);
    $stmt->execute();
    $transferencias_a_pagar = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    if (empty($transferencias_a_pagar)) {
        throw new Exception('No se encontraron transferencias para este folio.');
    }

    // --- PASO 3: VERIFICAR ESTADOS ---
    foreach ($transferencias_a_pagar as $solicitud) {
        if ($solicitud['estado'] !== 'Subido a Pago') {
            throw new Exception("El folio {$folio_a_pagar} no está listo para ser pagado.");
        }
    }

    // --- PASO 4: ACTUALIZAR ESTADO ---
    $stmt_update = $conn->prepare("UPDATE transferencias_clara_tcl SET estado = 'Pagado' WHERE folio = ?");
    $stmt_update->bind_param("s", $folio_a_pagar);
    $stmt_update->execute();
    $stmt_update->close();

    // --- PASO 5: ENVIAR CORREO ---
    $info = $transferencias_a_pagar[0];

    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = 'mail.intranetdrg.com.mx';
        $mail->SMTPAuth = true;
        $mail->Username = 'administrador@intranetdrg.com.mx';
        $mail->Password = 'WbrE5%7p';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port = 465;

        $mail->setFrom('administrador@intranetdrg.com.mx', 'Transferencia Pagada');
        $mail->addAddress($info['email_solicitante']); 
        $mail->addAddress($info['email_beneficiario']);

        $fechaSolicitud = new DateTime($info['fecha_solicitud']);
        $fechaSolicitudFormateada = $fechaSolicitud->format('d/m/Y');

        $mail->isHTML(true);
        $mail->Subject = 'Transferencia Electrónica Pagada';

        $mail->Body = "
        <html>
        <body style='font-family: Arial, sans-serif;'>
            <h2>Transferencia Electrónica Pagada</h2>
            <p><strong>Solicitante:</strong> {$info['nombre_solicitante']}</p>
            <p><strong>Sucursal:</strong> {$info['sucursal']}</p>
            <p><strong>Beneficiario:</strong> {$info['beneficiario']}</p>
            <p><strong>Fecha Solicitud:</strong> {$fechaSolicitudFormateada}</p>
            <p><strong>No. Cuenta:</strong> {$info['no_cuenta']}</p>
            <p><strong>Importe:</strong> $ {$info['importe']}</p>
            <p><strong>Descripción:</strong> {$info['descripcion']}</p>
            <p><strong>Autoriza:</strong> {$info['nombre_autoriza']}</p>
            <p style='color:red'><strong>No olvides subir tus evidencias correspondientes.</strong></p>
        </body>
        </html>
        ";

        $mail->send();
        $_SESSION['alert_message'] = "Transferencia pagada y correo enviado.";
        $_SESSION['alert_type'] = "success";

    } catch (Exception $e) {
        $_SESSION['alert_message'] = "Transferencia pagada, pero el correo no pudo enviarse.";
        $_SESSION['alert_type'] = "warning";
    }

    $conn->commit();

} catch (Exception $e) {
    $conn->rollback();
    $_SESSION['alert_message'] = "Error: " . $e->getMessage();
    $_SESSION['alert_type'] = "danger";
}

header("Location: ../TCL_pendiente_pago.php");
exit();
?>
