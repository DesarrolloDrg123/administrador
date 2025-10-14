<?php
session_start();
if (!isset($_SESSION['loggedin']) || !$_SESSION['loggedin']) {
    header("Location: index.php");
    exit();
}

require("../config/db.php");
require ("../vendor/autoload.php"); // Asegúrate de que PHPMailer esté instalado y autoloaded

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if (!isset($_GET['id'])) {
    die('ID de transferencia no especificado.');
}


$solicitud_id_inicial = intval($_GET['id']);

$conn->begin_transaction();
try {
    // --- PASO 1: OBTENER EL FOLIO USANDO EL ID INICIAL ---
    $stmt_folio = $conn->prepare("SELECT folio FROM transferencias WHERE id = ?");
    if (!$stmt_folio) throw new Exception('Error al preparar consulta de folio: ' . $conn->error);
    $stmt_folio->bind_param("i", $solicitud_id_inicial);
    $stmt_folio->execute();
    $result_folio = $stmt_folio->get_result();
    if ($result_folio->num_rows === 0) {
        throw new Exception('Solicitud no encontrada.');
    }
    $folio_a_pagar = $result_folio->fetch_assoc()['folio'];
    $stmt_folio->close();

    // --- PASO 2: OBTENER TODAS LAS TRANSFERENCIAS DE ESE FOLIO ---
    $sql_transferencias = 'SELECT
            t.*, s.sucursal, b.beneficiario,
            u_sol.nombre AS nombre_solicitante, u_sol.email AS email_solicitante
        FROM transferencias t
        LEFT JOIN sucursales s ON t.sucursal_id = s.id
        LEFT JOIN beneficiarios b ON t.beneficiario_id = b.id
        LEFT JOIN usuarios u_sol ON t.usuario_id = u_sol.id
        WHERE t.folio = ? FOR UPDATE';
        
    $stmt = $conn->prepare($sql_transferencias);
    if (!$stmt) throw new Exception('Error al preparar consulta de transferencias: ' . $conn->error);
    $stmt->bind_param("s", $folio_a_pagar);
    $stmt->execute();
    $transferencias_a_pagar = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    if (empty($transferencias_a_pagar)) {
        throw new Exception('No se encontraron transferencias para el folio ' . htmlspecialchars($folio_a_pagar));
    }
    
    // --- PASO 3: VERIFICAR ESTADOS ---
    foreach ($transferencias_a_pagar as $solicitud) {
        if ($solicitud['estado'] !== 'Subido a Pago') {
            throw new Exception("Una de las solicitudes del folio {$folio_a_pagar} no tiene el estado correcto para ser pagada.");
        }
    }

    // --- PASO 4: ACTUALIZAR EL ESTADO A "PAGADO" ---
    $sql_update = 'UPDATE transferencias SET estado = "Pagado" WHERE folio = ?';
    $stmt_update = $conn->prepare($sql_update);
    if (!$stmt_update) throw new Exception('Error al preparar la actualización de estado: ' . $conn->error);
    $stmt_update->bind_param("s", $folio_a_pagar); // Se cambia el tipo a "s" para el folio
    $stmt_update->execute();
    $stmt_update->close();

    // --- PASO 5: ENVIAR CORREO DE NOTIFICACIÓN ---
    $info_para_correo = $transferencias_a_pagar[0]; 

        // Configurar y enviar el correo
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host = 'mail.intranetdrg.com.mx'; // Reemplaza con tu dominio
            $mail->SMTPAuth = true;
            $mail->Username = 'administrador@intranetdrg.com.mx'; // Reemplaza con tu correo
            $mail->Password = 'WbrE5%7p'; // Reemplaza con tu contraseña
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; // Usa ENCRYPTION_STARTTLS si el puerto es 587
            $mail->Port = 465; // Usa 587 si usas TLS

            $mail->setFrom('administrador@intranetdrg.com.mx', 'Transferencia Electronica Pagada');
            //$mail->addAddress('ebetancourt@drg.mx');
            $mail->addAddress($info_para_correo["usuario_email"]);

            //$mail->addAddress('jpauda@drg.mx');
            //$mail->addAddress('msalas@drg.mx');

            $fechaSolicitud = new DateTime($info_para_correo['fecha_solicitud']);
            $fechaSolicitudFormateada = $fechaSolicitud->format('d M Y');

            $mail->isHTML(true);
            $mail->Subject = 'Solicitud de Transferencia Electronica Pagada';
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
                    <h1>Transferencia Electrónica Pagada.</h1>
                    <h2><span style='color: red;'>Le recordamos no olvidar subir las facturas correspondientes</span></h2>
                    <h2>Solicitante: <strong>{$info_para_correo['usuario_solicitante']}</strong></h2>
                    
                    <div class='info-row'>
                        <span class='label'>Sucursal:</span>
                        <span class='value'>{$info_para_correo['sucursal']}</span>
                    </div>
                    
                    <div class='info-row'>
                        <span class='label'>Beneficiario:</span>
                        <span class='value'>{$info_para_correo['beneficiario']}</span>
                    </div>
                    
                    <div class='info-row'>
                        <span class='label'>Fecha de Solicitud:</span>
                        <span class='value'>$fechaSolicitudFormateada</span>
                    </div>
                    
                    <div class='info-row'>
                        <span class='label'>No. de Cuenta:</span>
                        <span class='value'>{$info_para_correo['no_cuenta']}</span>
                    </div>
                    
                    <div class='info-row'>
                        <span class='label'>Importe Pesos:</span>
                        <span class='value'>$ {$info_para_correo['importe']}</span>
                    </div>
                    
                    <div class='info-row'>
                        <span class='label'>Descripción:</span>
                        <span class='value'>{$info_para_correo['descripcion']}</span>
                    </div>
                    
                    <div class='info-row'>
                        <span class='label'>Observaciones:</span>
                        <span class='value'>{$info_para_correo['observaciones']}</span>
                    </div>
                    
                    <div class='info-row'>
                        <span class='label'>Autoriza:</span>
                        <span class='value'>{$info_para_correo['usuario_autoriza']}</span>
                    </div>

                </div>
            </body>
            </html>
            ";
            $mail->send();
            $_SESSION['alert_message'] = "Transferencia pagada con éxito y correo enviado.";
            $_SESSION['alert_type'] = "success";
        } catch (Exception $e) {
            $_SESSION['alert_message'] = "Transferencia pagada con éxito, pero no se pudo enviar el correo. Error: {$mail->ErrorInfo}";
            $_SESSION['alert_type'] = "warning";
        }

    $conn->commit();
} catch (Exception $e) {
    $conn->rollback();
    $_SESSION['alert_message'] = "Error al procesar la solicitud: " . $e->getMessage();
    $_SESSION['alert_type'] = "danger";
}

header("Location: ../TR_pendiente_pago.php");
exit();
?>
