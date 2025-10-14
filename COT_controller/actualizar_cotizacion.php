<?php
session_start();
require("../config/db.php");
require '../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Preparamos la respuesta para AJAX
$response = ['success' => false, 'message' => ''];
header('Content-Type: application/json');

// =================================================================
// === FUNCIONES PARA NOTIFICACIONES
// =================================================================

function obtenerCorreosCotizadores($conn, $es_rfid) {
    // ID del programa al que deben tener acceso los usuarios
    $id_programa_cotizaciones = 30;
    
    $sql = "";

    // Si $es_rfid es 1 (verdadero), se busca al líder con el puesto específico
    if ($es_rfid == 1) {
        $sql = "SELECT u.email FROM usuarios u 
                JOIN permisos p ON u.id = p.id_usuario 
                WHERE p.id_programa = ? AND p.acceso = 1 AND (u.puesto = 'Líder de Etiquetado y Codificado' OR u.puesto = 'Ingeniero de Preventa')";
    } else {
        // Si no, se busca a todos los que tengan acceso EXCEPTO al líder de RFID
        $sql = "SELECT u.email FROM usuarios u 
                JOIN permisos p ON u.id = p.id_usuario 
                WHERE p.id_programa = ? AND p.acceso = 1 AND (u.puesto != 'Líder de Etiquetado y Codificado' OR u.puesto != 'Ingeniero de Preventa')";
    }
        
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id_programa_cotizaciones);
    $stmt->execute();
    $result = $stmt->get_result();

    $emails = [];
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $emails[] = $row['email'];
        }
    }
    
    $stmt->close();
    return $emails;
}

function enviarNotificacion($destinatarios, $asunto, $cuerpo_html) {
    if (empty($destinatarios)) {
        error_log("No se encontraron destinatarios para el correo.");
        return;
    }
    $mail = new PHPMailer(true);
    try {
        // Configuración del servidor
        $mail->isSMTP();
        $mail->Host = 'mail.intranetdrg.com.mx';
        $mail->SMTPAuth = true;
        $mail->Username = 'notification@intranetdrg.com.mx';
        $mail->Password = 'r-eHQi64a7!3QT9';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port = 465;
        $mail->CharSet = 'UTF-8';
        $mail->setFrom('notification@intranetdrg.com.mx', 'DRG Notification');
        foreach ($destinatarios as $email) {
            $mail->addAddress($email);
        }
        $mail->isHTML(true);
        $mail->Subject = $asunto;
        $mail->Body = $cuerpo_html;
        $mail->send();
    } catch (Exception $e) {
        error_log("Error al enviar correo: " . $mail->ErrorInfo);
    }
}

// 1. Validaciones (sin cambios)
if ($_SERVER["REQUEST_METHOD"] !== "POST") { /* ... */ exit(); }
if (!isset($_SESSION['loggedin']) || !$_SESSION['loggedin']) { /* ... */ exit(); }
if (empty($_POST['folio'])) { /* ... */ exit(); }

$folio = $_POST['folio'];
$user_solicitante = $_SESSION['nombre']; // El usuario que está editando

// 2. Iniciar transacción
$conn->begin_transaction();

try {
    // --- PASO A: Actualizar la tabla `datos_generales_co` ---
    // Se cambia el estatus a 'En Proceso' para que vuelva a la bandeja de pendientes
    $sql_general = "UPDATE datos_generales_co SET 
                        empresa=?, cliente=?, telefono=?, celular=?, correo=?, 
                        observaciones=?, rfid=?, estatus='En Proceso', motivo=NULL 
                    WHERE folio=?";
    
    $stmt_general = $conn->prepare($sql_general);
    $rfid = isset($_POST['is_rfid']) ? 1 : 0;
    $stmt_general->bind_param("ssssssis",
        $_POST['empresa'], $_POST['nombre_cliente'], $_POST['telefono'],
        $_POST['celular'], $_POST['correo_cliente'], $_POST['observaciones'],
        $rfid, $folio
    );

    if (!$stmt_general->execute()) {
        throw new Exception("Error al actualizar datos generales: " . $stmt_general->error);
    }
    $stmt_general->close();

    // --- PASO B: Eliminar los productos antiguos ---
    $sql_delete = "DELETE FROM productos_co WHERE folio = ?";
    $stmt_delete = $conn->prepare($sql_delete);
    $stmt_delete->bind_param("s", $folio);
    if (!$stmt_delete->execute()) {
        throw new Exception("Error al eliminar productos antiguos: " . $stmt_delete->error);
    }
    $stmt_delete->close();

    // --- PASO C: Insertar los nuevos productos ---
    $sql_insert = "INSERT INTO productos_co (folio, sku, descripcion, cantidad, notas, estatus) VALUES (?, ?, ?, ?, ?, 'Nuevo')";
    $stmt_insert = $conn->prepare($sql_insert);

    $skus = $_POST['sku'] ?? [];
    for ($i = 0; $i < count($skus); $i++) {
        if (empty($_POST['descripcion'][$i]) && empty($_POST['cantidad'][$i])) {
            continue;
        }
        $stmt_insert->bind_param("sssis",
            $folio, $_POST['sku'][$i], $_POST['descripcion'][$i],
            $_POST['cantidad'][$i], $_POST['nota'][$i]
        );
        if (!$stmt_insert->execute()) {
            throw new Exception("Error al insertar nuevo producto: " . $stmt_insert->error);
        }
    }
    $stmt_insert->close();

    // Si todo fue exitoso, confirmar la transacción
    $conn->commit();
    
    // =================================================================
    // === NUEVO: Envío de notificación por correo
    // =================================================================
    
    $correos_cotizadores = obtenerCorreosCotizadores($conn, $rfid);
    $folio_formateado = sprintf('%09d', $folio);
    $asunto = "Cotización Actualizada y Lista para Revisión - Folio " . $folio_formateado;
    $cuerpo_html = "
        <html><body>
            <div style='font-family: Arial, sans-serif; max-width: 600px; margin: auto; border: 1px solid #ddd; padding: 20px;'>
                <h1 style='color: #2c3e50;'>Cotización Actualizada</h1>
                <p>La cotización con folio <strong>{$folio_formateado}</strong> que había sido devuelta, ha sido actualizada por el solicitante y requiere su atención nuevamente.</p>
                <p><strong>Actualizada por:</strong> " . htmlspecialchars($user_solicitante) . "</p>
                <hr>
                <p style='text-align:center;'><strong>Por favor, ingrese al portal para procesarla.</strong></p>
            </div>
        </body></html>";
        
    enviarNotificacion($correos_cotizadores, $asunto, $cuerpo_html);

    $response['success'] = true;
    $response['message'] = 'Cotización actualizada correctamente.';

} catch (Exception $e) {
    $conn->rollback();
    $response['message'] = "Error: " . $e->getMessage();
}

$conn->close();
echo json_encode($response);
?>