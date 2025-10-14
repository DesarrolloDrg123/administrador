<?php
session_start();

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require '../vendor/autoload.php';

// --- FIN: INCLUSIÓN DE LIBRERÍA DE PDF (simulada) ---

require("../config/db.php");

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido.']);
    exit;
}
if (!isset($_SESSION['loggedin']) || !$_SESSION['loggedin']) {
    echo json_encode(['success' => false, 'message' => 'No tienes permiso para realizar esta acción.']);
    exit;
}

$nombre_usuario = $_SESSION['nombre'];
$conn->begin_transaction();

try {
    $es_actualizacion = isset($_POST['id_solicitud']) && !empty($_POST['id_solicitud']);
    
    $tipo_solicitud_map = [
        'alta' => 'Alta de usuario', 'cambio_puesto' => 'Alta de usuario por cambio de puesto',
        'remplazo' => 'Alta por remplazo de usuario', 'practicante' => 'Practicante',
        'baja' => 'Baja de usuario'
    ];
    $tipo_solicitud_value = $_POST['tipo_solicitud'] ?? '';
    $tipo_solicitud_texto = $tipo_solicitud_map[$tipo_solicitud_value] ?? 'No definido';
    
    $usuario_remplazo_id = !empty($_POST['usuario_remplazo_id']) ? intval($_POST['usuario_remplazo_id']) : null;
    $puesto_remplazo = $_POST['puesto_remplazo'] ?? null;
    $nombre_predilecto = !empty($_POST['nombre_predilecto']) ? $_POST['nombre_predilecto'] : null;

    $puesto_anterior = !empty($_POST['puesto_anterior_id']) ? intval($_POST['puesto_anterior_id']) : null;
    $puesto_nuevo = !empty($_POST['puesto_nuevo_id']) ? intval($_POST['puesto_nuevo_id']) : null;
    $es_baja_reemplazo = isset($_POST['es_baja_por_reemplazo']) ? intval($_POST['es_baja_por_reemplazo']) : 0;
    
    if (!empty($_POST['sucursal_alta'])) { 
        $sucursal_id = intval($_POST['sucursal_alta']); 
    } elseif (!empty($_POST['nueva_sucursal_id'])) {
        $sucursal_id = intval($_POST['nueva_sucursal_id']);
    } else { 
        $sucursal_id = null; 
    }

    $numero_empleado = !empty($_POST['numero_empleado']) ? $_POST['numero_empleado'] : null;
    $fecha_ingreso = !empty($_POST['fecha_ingreso']) ? $_POST['fecha_ingreso'] : null;
    $direccion = !empty($_POST['direccion']) ? $_POST['direccion'] : null;
    $telefono = !empty($_POST['telefono']) ? $_POST['telefono'] : null;
    $fecha_nacimiento = !empty($_POST['fecha_nacimiento']) ? $_POST['fecha_nacimiento'] : null;
    $usuario_baja_id = !empty($_POST['usuario_baja_id']) ? intval($_POST['usuario_baja_id']) : null;
    $es_foraneo = isset($_POST['es_foraneo']) ? 1 : 0;
    $colaborador_respaldo_id = !empty($_POST['colaborador_respaldo_id']) ? intval($_POST['colaborador_respaldo_id']) : null;
    $colaborador_recoge_productos = !empty($_POST['colaborador_recoge_productos']) ? $_POST['colaborador_recoge_productos'] : null;
    $nombres = !empty($_POST['nombres']) ? $_POST['nombres'] : ($_POST['nombres_cambio'] ?? null);
    $apellido_paterno = !empty($_POST['apellido_paterno']) ? $_POST['apellido_paterno'] : ($_POST['apellido_paterno_cambio'] ?? null);
    $apellido_materno = !empty($_POST['apellido_materno']) ? $_POST['apellido_materno'] : ($_POST['apellido_materno_cambio'] ?? null);
    $justificacion = !empty($_POST['justificacion']) ? $_POST['justificacion'] : ($_POST['observaciones_baja'] ?? null);
    $observaciones = !empty($_POST['observaciones']) ? $_POST['observaciones'] : null;
    $motivo = $_POST['motivo'] ?? 'N/A'; // Asumiendo que el campo 'motivo' es para la baja

    if ($tipo_solicitud_value === 'remplazo' && $usuario_remplazo_id) {
        $stmt_check_status = $conn->prepare("SELECT estatus FROM usuarios WHERE id = ?");
        $stmt_check_status->bind_param("i", $usuario_remplazo_id);
        $stmt_check_status->execute();
        $result_status = $stmt_check_status->get_result()->fetch_assoc();

        if ($result_status && $result_status['estatus'] == 1) {
            $conn->rollback();
            echo json_encode(['success' => false, 'message' => 'El usuario seleccionado para reemplazo está actualmente activo. Debe estar inactivo para poder ser reemplazado.']);
            exit;
        }
    }
    
    $archivo_path = null;

    $usuario_a_reemplazar_info = null;
    if ($usuario_remplazo_id) {
        $stmt_user_name = $conn->prepare("SELECT nombre FROM usuarios WHERE id = ?");
        $stmt_user_name->bind_param("i", $usuario_remplazo_id);
        $stmt_user_name->execute();
        $result_user_name = $stmt_user_name->get_result()->fetch_assoc();
        if ($result_user_name) {
            $nombre_reemplazado = $result_user_name['nombre'];
            $usuario_a_reemplazar_info = htmlspecialchars($nombre_reemplazado) . " (Puesto: " . htmlspecialchars($puesto_remplazo) . ")";
        }
    }

    if ($es_actualizacion) {
        $id_solicitud = intval($_POST['id_solicitud']);
        
        $stmt_get_data = $conn->prepare("SELECT folio, archivo_evidencia_path FROM solicitudes_movimientos_personal WHERE id = ?");
        $stmt_get_data->bind_param("i", $id_solicitud);
        $stmt_get_data->execute();
        $result = $stmt_get_data->get_result()->fetch_assoc();
        $folio_actual = $result['folio'];
        $archivo_path = $result['archivo_evidencia_path'];

        if (isset($_FILES['archivo_evidencia_path']) && $_FILES['archivo_evidencia_path']['error'] == 0) {
            $uploadDir = '../uploads/evidencias/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
            $fileName = $folio_actual . '_' . basename($_FILES['archivo_evidencia_path']['name']);
            $uploadFilePath = $uploadDir . $fileName;
            if (move_uploaded_file($_FILES['archivo_evidencia_path']['tmp_name'], $uploadFilePath)) {
                $archivo_path = $uploadFilePath;
            } else { throw new Exception('Error al mover el archivo subido.'); }
        }

        $sql_update = "UPDATE solicitudes_movimientos_personal SET tipo_solicitud = ?, estatus = 'Nueva Solicitud', usuario_a_reemplazar_info = ?, puesto_anterior = ?, puesto_nuevo = ?, justificacion = ?, es_baja_por_reemplazo = ?, sucursal_id = ?, nombres = ?, apellido_paterno = ?, apellido_materno = ?, numero_empleado = ?, fecha_ingreso = ?, direccion = ?, telefono = ?, fecha_nacimiento = ?, usuario_baja_id = ?, es_foraneo = ?, colaborador_respaldo_id = ?, colaborador_recoge_productos = ?, archivo_evidencia_path = ?, usuario_remplazo_id = ?, observaciones = ?, nombre_predilecto = ?, motivo = ? WHERE id = ?";
        $stmt_update = $conn->prepare($sql_update);
        
        // CORRECCIÓN: Ajuste de la cadena de tipos y las variables para que coincidan con la consulta.
        // El tipo de dato para 'motivo' es 's'.
        $params_update = ["sssssiissssssssiississsi", &$tipo_solicitud_texto, &$usuario_a_reemplazar_info, &$puesto_anterior, &$puesto_nuevo, &$justificacion, &$es_baja_reemplazo, &$sucursal_id, &$nombres, &$apellido_paterno, &$apellido_materno, &$numero_empleado, &$fecha_ingreso, &$direccion, &$telefono, &$fecha_nacimiento, &$usuario_baja_id, &$es_foraneo, &$colaborador_respaldo_id, &$colaborador_recoge_productos, &$archivo_path, &$usuario_remplazo_id, &$observaciones, &$nombre_predilecto, &$motivo, &$id_solicitud];
        
        call_user_func_array([$stmt_update, 'bind_param'], $params_update);
        $stmt_update->execute();

        $observacion_historial = "Solicitud corregida y reenviada por el solicitante.";
        $estatus_cambio = "Nueva Solicitud";
        $stmt_historial = $conn->prepare("INSERT INTO solicitudes_movimientos_personal_historial (solicitud_id, usuario_nombre, estatus_cambio, observacion) VALUES (?, ?, ?, ?)");
        $stmt_historial->bind_param("isss", $id_solicitud, $nombre_usuario, $estatus_cambio, $observacion_historial);
        $stmt_historial->execute();

        $admin_emails = [];
        $sql_admins = "SELECT u.email AS correo FROM usuarios u JOIN permisos p ON u.id = p.id_usuario WHERE p.id_programa = 36 AND p.acceso = 1";
        $result_admins = $conn->query($sql_admins);
        if ($result_admins) { while ($row = $result_admins->fetch_assoc()) { $admin_emails[] = $row['correo']; } }
        if (!empty($admin_emails)) {
            $mail = new PHPMailer(true);
            try {
                $mail->isSMTP(); $mail->Host = 'mail.intranetdrg.com.mx'; $mail->SMTPAuth = true;
                $mail->Username = 'notification@intranetdrg.com.mx'; $mail->Password = 'r-eHQi64a7!3QT9';
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; $mail->Port = 465;
                $mail->setFrom('notification@intranetdrg.com.mx', 'Sistema de Solicitudes ABC');
                foreach ($admin_emails as $admin_email) { $mail->addAddress($admin_email); }
                $mail->isHTML(true); $mail->CharSet = 'UTF-8';
                $mail->Subject = 'Solicitud Corregida y Lista para Revisión - Folio: ' . $folio_actual;
                $mail->Body    = "<html><body><div style='font-family: Arial, sans-serif; padding: 20px;'><h2 style='color: #f39c12;'>Notificación de Solicitud Actualizada</h2><p>El usuario <strong>" . htmlspecialchars($nombre_usuario) . "</strong> ha corregido y reenviado la solicitud con folio <strong>" . $folio_actual . "</strong>.</p><p>La solicitud se encuentra nuevamente en el estatus 'Nueva Solicitud' y está lista para su revisión y aprobación en el panel de gestión.</p><p>Saludos,<br>Sistema de Solicitudes.</p></div></body></html>";
                $mail->send();
            } catch (Exception $e) { error_log("Correo de actualización no enviado para folio {$folio_actual}: {$mail->ErrorInfo}"); }
        }
        
        $message = "Solicitud actualizada correctamente.";
        $folio_respuesta = $folio_actual;

    } else {
        $stmt_folio = $conn->prepare("SELECT folio FROM control_folios_mp WHERE id = 1 FOR UPDATE");
        $stmt_folio->execute();
        $nuevo_folio_num = $stmt_folio->get_result()->fetch_assoc()['folio'] + 1;
        $nuevo_folio_str = sprintf("%09d", $nuevo_folio_num);
        $stmt_folio_update = $conn->prepare("UPDATE control_folios_mp SET folio = ? WHERE id = 1");
        $stmt_folio_update->bind_param("i", $nuevo_folio_num);
        $stmt_folio_update->execute();

        if (isset($_FILES['archivo_evidencia_path']) && $_FILES['archivo_evidencia_path']['error'] == 0) {
            $uploadDir = '../uploads/evidencias/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
            $fileName = $nuevo_folio_str . '_' . basename($_FILES['archivo_evidencia_path']['name']);
            $uploadFilePath = $uploadDir . $fileName;
            if (move_uploaded_file($_FILES['archivo_evidencia_path']['tmp_name'], $uploadFilePath)) {
                $archivo_path = $uploadFilePath;
            } else { throw new Exception('Error al mover el archivo subido.'); }
        }

        $sql_insert = "INSERT INTO solicitudes_movimientos_personal (folio, solicitante, tipo_solicitud, estatus, fecha_solicitud, usuario_a_reemplazar_info, puesto_anterior, puesto_nuevo, justificacion, es_baja_por_reemplazo, sucursal_id, nombres, apellido_paterno, apellido_materno, numero_empleado, fecha_ingreso, direccion, telefono, fecha_nacimiento, usuario_baja_id, es_foraneo, colaborador_respaldo_id, colaborador_recoge_productos, archivo_evidencia_path, usuario_remplazo_id, observaciones, nombre_predilecto, motivo) VALUES (?, ?, ?, 'Nueva Solicitud', CURDATE(), ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt_insert = $conn->prepare($sql_insert);
        
        // CORRECCIÓN: Ajuste de la cadena de tipos y las variables para que coincidan con la consulta.
        // El tipo de dato para 'motivo' es 's'.
        $params_insert = ["ssssssiississsssiississss", &$nuevo_folio_str, &$nombre_usuario, &$tipo_solicitud_texto, &$usuario_a_reemplazar_info, &$puesto_anterior, &$puesto_nuevo, &$justificacion, &$es_baja_reemplazo, &$sucursal_id, &$nombres, &$apellido_paterno, &$apellido_materno, &$numero_empleado, &$fecha_ingreso, &$direccion, &$telefono, &$fecha_nacimiento, &$usuario_baja_id, &$es_foraneo, &$colaborador_respaldo_id, &$colaborador_recoge_productos, &$archivo_path, &$usuario_remplazo_id, &$observaciones, &$nombre_predilecto, &$motivo];
        
        call_user_func_array([$stmt_insert, 'bind_param'], $params_insert);
        $stmt_insert->execute();
        $id_solicitud = $conn->insert_id;

        // Lógica de generación de PDF con sello usando mPDF
        try {
            $mpdf = new \Mpdf\Mpdf(['mode' => 'utf-8', 'format' => 'A4']);
            $mpdf->SetWatermarkText('FINALIZADO');
            $mpdf->showWatermarkText = true;
            $mpdf->watermark_font = 'DejaVuSansCondensed';
            $mpdf->watermarkTextAlpha = 0.2;

            $html = '
                <h1 style="text-align: center;">Formulario de Solicitud de Movimientos de Personal</h1>
                <hr>
                <p><strong>Folio:</strong> ' . htmlspecialchars($nuevo_folio_str) . '</p>
                <p><strong>Tipo de Solicitud:</strong> ' . htmlspecialchars($tipo_solicitud_texto) . '</p>
                <p><strong>Solicitante:</strong> ' . htmlspecialchars($nombre_usuario) . '</p>
                <p><strong>Fecha de Solicitud:</strong> ' . date('Y-m-d') . '</p>
                <p><strong>Estatus:</strong> Nueva Solicitud</p>
            ';

            $mpdf->WriteHTML($html);

            $pdf_filename = '../uploads/solicitudes_pdf/' . $nuevo_folio_str . '.pdf';
            if (!is_dir(dirname($pdf_filename))) {
                mkdir(dirname($pdf_filename), 0777, true);
            }
            $mpdf->Output($pdf_filename, \Mpdf\Output\Destination::FILE);

        } catch (Exception $e) {
            error_log("Error al generar PDF con sello para folio {$nuevo_folio_str}: " . $e->getMessage());
        }

        $admin_emails_new = [];
        $sql_admins_new = "SELECT u.email AS correo FROM usuarios u JOIN permisos p ON u.id = p.id_usuario WHERE p.id_programa = 36 AND p.acceso = 1";
        $result_admins_new = $conn->query($sql_admins_new);
        if ($result_admins_new) { while ($row = $result_admins_new->fetch_assoc()) { $admin_emails_new[] = $row['correo']; } }
        if (!empty($admin_emails_new)) {
            $mail_new = new PHPMailer(true);
            try {
                $mail_new->isSMTP(); $mail_new->Host = 'mail.intranetdrg.com.mx'; $mail_new->SMTPAuth = true;
                $mail_new->Username = 'notification@intranetdrg.com.mx'; $mail_new->Password = 'r-eHQi64a7!3QT9';
                $mail_new->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; $mail_new->Port = 465;
                $mail_new->setFrom('notification@intranetdrg.com.mx', 'Sistema de Solicitudes ABC');
                foreach ($admin_emails_new as $admin_email) { $mail_new->addAddress($admin_email); }
                $mail_new->isHTML(true); $mail_new->CharSet = 'UTF-8';
                $mail_new->Subject = 'Nueva Solicitud de Movimiento de Personal - Folio: ' . $nuevo_folio_str;
                $mail_new->Body    = "<html><body><div style='font-family: Arial, sans-serif; padding: 20px;'><h2 style='color: #3498db;'>Nueva Solicitud para Gestionar</h2><p>Se ha registrado una nueva solicitud (".htmlspecialchars($tipo_solicitud_texto).") con folio <strong>" . $nuevo_folio_str . "</strong> por parte de <strong>" . htmlspecialchars($nombre_usuario) . "</strong>.</p><p>Por favor, ingresa al panel de gestión para revisarla.</p><p>Saludos,<br>Sistema de Solicitudes.</p></div></body></html>";
                $mail_new->send();
            } catch (Exception $e) { error_log("Correo de nueva solicitud no enviado para folio {$nuevo_folio_str}: {$mail_new->ErrorInfo}"); }
        }

        $observacion_historial = "Creación de la solicitud.";
        $estatus_cambio = "Nueva Solicitud";
        $stmt_historial = $conn->prepare("INSERT INTO solicitudes_movimientos_personal_historial (solicitud_id, usuario_nombre, estatus_cambio, observacion) VALUES (?, ?, ?, ?)");
        $stmt_historial->bind_param("isss", $id_solicitud, $nombre_usuario, $estatus_cambio, $observacion_historial);
        $stmt_historial->execute();
        
        $message = "Solicitud enviada con éxito.";
        $folio_respuesta = $nuevo_folio_str;
    }

    $conn->commit();
    echo json_encode(['success' => true, 'message' => $message, 'folio' => $folio_respuesta]);

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => 'Error en la base de datos: ' . $e->getMessage()]);
}

$conn->close();
?>
