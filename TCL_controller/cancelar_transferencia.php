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

// Iniciamos transacción para asegurar que si falla el presupuesto, no se cancele la transferencia
$conn->begin_transaction();

try {
    // =========================================================================
    // 1. OBTENER DATOS (Agregamos Sucursal, Depto y Fecha para el Presupuesto)
    // =========================================================================
    $stmt = $conn->prepare("
        SELECT 
            t.id,
            t.folio,
            t.descripcion,
            t.importe,
            t.importedls,
            t.estado,
            t.sucursal_id,      -- Necesario para presupuesto
            t.departamento_id,  -- Necesario para presupuesto
            t.fecha_solicitud,  -- Necesario para calcular el periodo
            u_sol.email   AS email_solicitante,
            u_sol.nombre  AS nombre_solicitante,
            u_sol.id      AS id_solicitante, -- ID del dueño del presupuesto
            u_ben.email   AS email_beneficiario,
            u_ben.nombre  AS nombre_beneficiario
        FROM transferencias_clara_tcl t
        LEFT JOIN usuarios u_sol ON t.usuario_solicitante_id = u_sol.id
        LEFT JOIN usuarios u_ben ON t.beneficiario_id = u_ben.id
        WHERE t.folio = ?
        LIMIT 1 FOR UPDATE
    ");

    $stmt->bind_param("s", $folio);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res->num_rows === 0) {
        throw new Exception("no_encontrado");
    }

    $info = $res->fetch_assoc();
    $stmt->close();

    // Verificamos si ya estaba cancelada para no devolver el dinero dos veces
    if ($info['estado'] === 'Cancelada') {
        $conn->rollback();
        echo "ya_cancelada";
        exit;
    }

    // Definir el importe a devolver (Prioridad Pesos como indicaste)
    // Limpiamos comas por si acaso vienen en la BD
    $importe_devolver = floatval(str_replace(',', '', $info['importe']));

    // =========================================================================
    // 2. DEVOLUCIÓN DE PRESUPUESTO
    // =========================================================================
    
    // Calculamos el periodo basado en la FECHA DE LA SOLICITUD (no la fecha actual)
    $meses = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
    $timestamp_solicitud = strtotime($info['fecha_solicitud']);
    $mes_num = date('n', $timestamp_solicitud) - 1; 
    $anio = date('y', $timestamp_solicitud);
    $periodoTarget = $meses[$mes_num] . '-' . $anio; // Ejemplo: Ene-25

    // Query para regresar el dinero: 
    // Aumentamos el 'restante' (+) y disminuimos lo 'registrado' (-)
    $stmtPres = $conn->prepare("
        UPDATE presupuestos 
        SET restante = restante + ?, 
            registrado = registrado - ? 
        WHERE periodo = ? 
          AND sucursal_id = ? 
          AND departamento_id = ?
    ");

    $stmtPres->bind_param("ddsii", $importe_devolver, $importe_devolver, $periodoTarget, $info['sucursal_id'], $info['departamento_id']);
    
    if (!$stmtPres->execute()) {
        throw new Exception("Error al actualizar presupuesto.");
    }
    $stmtPres->close();

    // =========================================================================
    // 3. ACTUALIZAR ESTATUS A CANCELADA
    // =========================================================================
    $stmtUpd = $conn->prepare("
        UPDATE transferencias_clara_tcl
        SET estado = 'Cancelada',
            motivo = ?
        WHERE folio = ?
    ");

    $stmtUpd->bind_param("ss", $motivo, $folio);

    if (!$stmtUpd->execute()) {
        throw new Exception("error_db");
    }
    $stmtUpd->close();

    // Si todo salió bien, guardamos cambios
    $conn->commit();

    // =========================================================================
    // 4. ENVIAR CORREOS (Fuera de la lógica crítica de BD)
    // =========================================================================
    
    // Calcular importe visual para el correo
    $importe_visual = (!empty($info['importedls']) && $info['importedls'] != '0.00') 
                ? $info['importedls'] 
                : $info['importe'];

    $asunto = "Transferencia cancelada - Folio {$folio}";
    
    // Preparamos HTMLs (Reutilizando tu estructura)
    $htmlSolicitante = generarHtml($info['nombre_solicitante'], $folio, $info['descripcion'], $importe_visual, $motivo, true);
    $htmlBeneficiario = generarHtml($info['nombre_beneficiario'], $folio, $info['descripcion'], $importe_visual, $motivo, false);

    enviarCorreo($info['email_solicitante'], $asunto, $htmlSolicitante);
    enviarCorreo($info['email_beneficiario'], $asunto, $htmlBeneficiario);

    echo "success";

} catch (Exception $e) {
    $conn->rollback();
    // Si el error es uno de los nuestros controlados, lo imprimimos, si no, error genérico
    $msg = $e->getMessage();
    echo ($msg == "no_encontrado") ? "no_encontrado" : "error_db";
}

// ==============================
// FUNCIONES AUXILIARES
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

function generarHtml($nombre, $folio, $descripcion, $importe, $motivo, $esSolicitante) {
    $mensajeExtra = $esSolicitante 
        ? "<p>Tu transferencia fue <strong>cancelada</strong> y el presupuesto ha sido devuelto.</p>" 
        : "<p>La transferencia asociada fue <strong>cancelada</strong>.</p>";
    
    $motivoBloque = $esSolicitante 
        ? "<p><b>Motivo de la cancelación:</b></p><p style='background:#f2f2f2;padding:10px;border-left:4px solid red;'>{$motivo}</p>" 
        : "";

    return "
    <html>
    <head>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        h2 { color: #2980b9; }
        strong { color: #2c3e50; }
        .logo { max-width: 200px; }
    </style>
    </head>
    <body>
        <img src='https://administrador.intranetdrg.com.mx/img/logo-drg.png' alt='Logo' class='logo'>
        <h2>Transferencia Cancelada</h2>
        <p>Hola <strong>{$nombre}</strong>,</p>
        {$mensajeExtra}
        <p><b>Folio:</b> {$folio}</p>
        <p><b>Descripción:</b> {$descripcion}</p>
        <p><b>Importe:</b> $" . number_format((float)$importe, 2) . "</p>
        {$motivoBloque}
    </body>
    </html>
    ";
}
?>