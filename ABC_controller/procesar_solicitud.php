<?php
session_start();

// --- Dependencias y Configuración Inicial ---
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Cargar el autoloader de Composer para PHPMailer
require '../vendor/autoload.php';

// Conexión a la base de datos
require("../config/db.php");

// Establecer la cabecera para respuestas JSON
header('Content-Type: application/json');

// --- Validaciones de Seguridad ---
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido.']);
    exit;
}
if (!isset($_SESSION['loggedin']) || !$_SESSION['loggedin']) {
    echo json_encode(['success' => false, 'message' => 'No tienes permiso para realizar esta acción.']);
    exit;
}

function enviarNotificacionDeBaja($destinatarios, $nombreUsuarioBaja, $folio, $solicitante) {
    // Si no hay a quién notificar, no hace nada.
    if (empty($destinatarios)) {
        return;
    }

    $mail = new PHPMailer(true);

    try {
        // --- Configuración del servidor (la misma que ya usas) ---
        $mail->isSMTP();
        $mail->Host       = 'mail.intranetdrg.com.mx';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'administrador@intranetdrg.com.mx';
        $mail->Password   = 'WbrE5%7p';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port       = 465;
        $mail->CharSet    = 'UTF-8';

        // --- Remitente y Destinatarios ---c
        $mail->setFrom('administrador@intranetdrg.com.mx', 'Sistema de Bajas');
        foreach ($destinatarios as $email) {
            $mail->addAddress($email);
        }

        // --- Contenido del Correo ---
        $mail->isHTML(true);
        $mail->Subject = "Aviso de Proceso de Baja: " . htmlspecialchars($nombreUsuarioBaja);
        
        $cuerpo_html = "
            <html>
            <body style='font-family: Arial, sans-serif; color: #333;'>
                <div style='max-width: 600px; margin: auto; padding: 20px; border: 1px solid #ddd; border-radius: 8px;'>
                    <h2 style='color: #d9534f;'>Notificación de Proceso de Baja</h2>
                    <p>Hola,</p>
                    <p>Este es un aviso para informarte que se ha iniciado un proceso de baja para el colaborador <strong>" . htmlspecialchars($nombreUsuarioBaja) . "</strong>.</p>
                    <hr>
                    <p><strong>Detalles de la Solicitud:</strong></p>
                    <ul>
                        <li><strong>Folio de Referencia:</strong> " . htmlspecialchars($folio) . "</li>
                        <li><strong>Solicitado por:</strong> " . htmlspecialchars($solicitante) . "</li>
                        <li><strong>Fecha:</strong> " . date('d/m/Y H:i') . "</li>
                    </ul>
                    <p>Se te ha incluido en esta notificación para tu conocimiento y seguimiento.</p>
                    <p style='font-size: 12px; color: #777;'>Este es un correo generado automáticamente.</p>
                </div>
            </body>
            </html>";
        $mail->Body = $cuerpo_html;

        $mail->send();

    } catch (Exception $e) {
        // Si el correo falla, lo registra en el log de errores de PHP sin detener el script
        error_log("Error al enviar correo de notificación de baja para folio $folio: " . $mail->ErrorInfo);
    }
}

// Iniciar transacción para asegurar la integridad de los datos
$conn->begin_transaction();

try {
    // --- RECOLECCIÓN Y NORMALIZACIÓN DE DATOS DEL FORMULARIO ---
    
    $solicitante = $_SESSION['nombre'];

    $solicitudes = [
        'alta' => [
            'texto' => 'Alta de usuario',
            'codigo' => 'FOR-TEI-001'
        ],
        'cambio_puesto' => [
            'texto' => 'Alta por cambio de puesto',
            'codigo' => 'FOR-TEI-002'
        ],
        'remplazo' => [
            'texto' => 'Alta por remplazo de usuario',
            'codigo' => 'FOR-TEI-006'
        ],
        'practicante' => [
            'texto' => 'Practicante',
            'codigo' => 'FOR-TEI-007'
        ],
        'baja' => [
            'texto' => 'Baja de usuario',
            'codigo' => 'FOR-TEI-008'
        ],
    ];

    $tipo_solicitud_key = $_POST['tipo_solicitud'] ?? '';
    $tipo_solicitud_texto = $solicitudes[$tipo_solicitud_key]['texto'] ?? 'No definido';
    $codigo_form = $solicitudes[$tipo_solicitud_key]['codigo'] ?? 'NO_DEFINIDO';

    // Recolectar todos los posibles campos del formulario
    $nombres = $_POST['nombres'] ?? null;
    $apellido_paterno = $_POST['apellido_paterno'] ?? null;
    $apellido_materno = $_POST['apellido_materno'] ?? null;
    $nombre_predilecto = $_POST['nombre_predilecto'] ?? null;
    $numero_empleado = $_POST['numero_empleado'] ?? null;
    $fecha_ingreso = !empty($_POST['fecha_ingreso']) ? $_POST['fecha_ingreso'] : null;
    $fecha_nacimiento = !empty($_POST['fecha_nacimiento']) ? $_POST['fecha_nacimiento'] : null;
    $direccion = $_POST['direccion'] ?? null;
    $telefono = $_POST['telefono'] ?? null;
    $sucursal_id = $_POST['sucursal'] ?? null;
    $puesto_id = $_POST['puesto_alta'] ?? null;
    $actividades_practicante = $_POST['actividades_practicante'] ?? null;
    $usuario_remplazo_id = $_POST['usuario_remplazo_id'] ?? null;
    $usuario_a_reemplazar_info = null;
    $usuario_cambio_id = $_POST['usuario_cambio_id'] ?? null;
    $puesto_anterior_id = $_POST['puesto_anterior'] ?? null;
    $puesto_nuevo_id = $_POST['puesto_nuevo'] ?? null;
    $usuario_baja_id = $_POST['usuario_baja_id'] ?? null;
    $es_foraneo = isset($_POST['es_foraneo']) ? (int)$_POST['es_foraneo'] : 0;
    $colaborador_respaldo_id = $_POST['colaborador_respaldo_id'] ?? null;
    $colaborador_recoge_productos = $_POST['colaborador_recoge_productos'] ?? null;
    $es_baja_por_reemplazo = isset($_POST['es_baja_por_reemplazo']) ? (int)$_POST['es_baja_por_reemplazo'] : 0;
    $justificacion = $_POST['justificacion_cambio'] ?? null;
    $observaciones = $_POST['observaciones'] ?? null;
    $notificar_ids = $_POST['notificar_usuarios'] ?? [];

    if ($tipo_solicitud_key === 'remplazo' && !empty($usuario_remplazo_id)) {
        $stmt_check = $conn->prepare("SELECT u.nombre, p.puesto FROM usuarios u LEFT JOIN puestos p ON u.puesto_id = p.id WHERE u.id = ?");
        $stmt_check->bind_param("i", $usuario_remplazo_id);
        $stmt_check->execute();
        $result_check = $stmt_check->get_result()->fetch_assoc();
        if ($result_check) {
            $usuario_a_reemplazar_info = "Reemplaza a: " . htmlspecialchars($result_check['nombre']) . " (Puesto: " . htmlspecialchars($result_check['puesto']) . ")";
        }
    }

    $stmt_folio = $conn->prepare("SELECT folio FROM control_folios_mp WHERE id = 1 FOR UPDATE");
    $stmt_folio->execute();
    $siguiente_folio_num = ($stmt_folio->get_result()->fetch_assoc()['folio'] ?? 0) + 1;
    $folio_formateado = sprintf("%09d", $siguiente_folio_num);

    $archivo_evidencia_path = null;
    if (isset($_FILES['archivo_evidencia_path']) && $_FILES['archivo_evidencia_path']['error'] == UPLOAD_ERR_OK) {
        $uploadDir = '../uploads/evidencias/';
        if (!is_dir($uploadDir)) { mkdir($uploadDir, 0777, true); }
        $nombre_base = preg_replace("/[^a-zA-Z0-9\._-]/", "", basename($_FILES['archivo_evidencia_path']['name']));
        $fileName = $folio_formateado . '_' . $nombre_base;
        $uploadFilePath = $uploadDir . $fileName;
        
        if (move_uploaded_file($_FILES['archivo_evidencia_path']['tmp_name'], $uploadFilePath)) {
            $archivo_evidencia_path = $uploadFilePath;
        } else {
            throw new Exception('Error al mover el archivo subido.');
        }
    }
    
    $sql_insert = "INSERT INTO solicitudes_movimientos_personal (
        folio, codigo_form, solicitante, tipo_solicitud, fecha_solicitud,
        nombres, apellido_paterno, apellido_materno, nombre_predilecto, numero_empleado,
        fecha_ingreso, fecha_nacimiento, direccion, telefono, sucursal_id, puesto_id,
        usuario_remplazo_id, usuario_a_reemplazar_info,
        usuario_cambio_id, puesto_anterior_id, puesto_nuevo_id,
        usuario_baja_id, es_foraneo, colaborador_respaldo_id, colaborador_recoge_productos, es_baja_por_reemplazo,
        actividades_practicante,
        justificacion, observaciones, archivo_evidencia_path
    ) VALUES (?, ?, ?, ?, CURDATE(), ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt_insert = $conn->prepare($sql_insert);
    
    $stmt_insert->bind_param(
        "sssssssssssssiiisiiiisissssss",
        $folio_formateado,
        $codigo_form,
        $solicitante,
        $tipo_solicitud_texto,
        $nombres,
        $apellido_paterno,
        $apellido_materno,
        $nombre_predilecto,
        $numero_empleado,
        $fecha_ingreso,
        $fecha_nacimiento,
        $direccion,
        $telefono,
        $sucursal_id,
        $puesto_id,
        $usuario_remplazo_id,
        $usuario_a_reemplazar_info,
        $usuario_cambio_id,
        $puesto_anterior_id,
        $puesto_nuevo_id,
        $usuario_baja_id,
        $es_foraneo,
        $colaborador_respaldo_id,
        $colaborador_recoge_productos,
        $es_baja_por_reemplazo,
        $actividades_practicante,
        $justificacion,
        $observaciones,
        $archivo_evidencia_path
    );

    
    $stmt_insert->execute();

    // Obtener el ID de la solicitud recién creada
    $id_solicitud = $conn->insert_id;

    // --- NUEVO: REGISTRO EN EL HISTORIAL ---
    $historial_sql = "INSERT INTO solicitudes_movimientos_personal_historial (solicitud_id, usuario_nombre, estatus_cambio, observacion) VALUES (?, ?, ?, ?)";
    $stmt_historial = $conn->prepare($historial_sql);
    $estatus_nuevo = 'Nueva Solicitud';
    $observacion_historial = 'Creación de la solicitud.';
    $stmt_historial->bind_param("isss", $id_solicitud, $solicitante, $estatus_nuevo, $observacion_historial);
    $stmt_historial->execute();

    // --- ACTUALIZACIÓN DE FOLIO ---
    $stmt_update_folio = $conn->prepare("UPDATE control_folios_mp SET folio = ? WHERE id = 1");
    $stmt_update_folio->bind_param("i", $siguiente_folio_num);
    $stmt_update_folio->execute();
    
    // Si todo fue exitoso hasta ahora, se confirma la transacción
    $conn->commit();
    
    if ($tipo_solicitud_key === 'baja' && !empty($notificar_ids)) {
        
        // 1. Obtener los correos de los IDs seleccionados
        $placeholders = implode(',', array_fill(0, count($notificar_ids), '?'));
        $sql_correos = "SELECT email FROM usuarios WHERE id IN ($placeholders)";
        $stmt_correos = $conn->prepare($sql_correos);
        $tipos = str_repeat('i', count($notificar_ids)); 
        $stmt_correos->bind_param($tipos, ...$notificar_ids);
        $stmt_correos->execute();
        $resultado_correos = $stmt_correos->get_result();
        
        $correos_a_notificar = [];
        while ($fila = $resultado_correos->fetch_assoc()) {
            if (!empty($fila['email'])) {
                $correos_a_notificar[] = $fila['email'];
            }
        }
        $stmt_correos->close();

        // 2. Obtener el nombre del usuario que se está dando de baja
        $nombre_usuario_baja = 'Desconocido';
        if (!empty($usuario_baja_id)) {
            $stmt_nombre = $conn->prepare("SELECT nombre FROM usuarios WHERE id = ?");
            $stmt_nombre->bind_param("i", $usuario_baja_id);
            $stmt_nombre->execute();
            $resultado_nombre = $stmt_nombre->get_result()->fetch_assoc();
            if ($resultado_nombre) {
                $nombre_usuario_baja = $resultado_nombre['nombre'];
            }
            $stmt_nombre->close();
        }

        // 3. Llamar a la nueva función de envío de correo
        enviarNotificacionDeBaja($correos_a_notificar, $nombre_usuario_baja, $folio_formateado, $solicitante);
    }

    // --- NUEVO: NOTIFICACIÓN POR CORREO (Se ejecuta después del commit) ---
    try {
        // Obtener correos de los usuarios a notificar (ej. usuarios activos)
        
        $id_programa = 36; //Id del programa para procesar pedidos
        $sql = "SELECT u.email FROM usuarios u JOIN permisos p ON u.id = p.id_usuario WHERE id_programa = $id_programa AND acceso = 1";
        $result = $conn->query($sql);
    
        $destinatarios = [];
        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $destinatarios[] = $row['email'];
            }
        }
        $sucursal_nombre = 'No aplica';
        if (!empty($sucursal_id)) {
            $stmt = $conn->prepare("SELECT sucursal FROM sucursales WHERE id = ?");
            $stmt->bind_param("i", $sucursal_id);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_assoc();
            if ($result) $sucursal_nombre = $result['sucursal'];
        }
        
        $puesto_nombre = 'No aplica';
        if (!empty($puesto_id)) {
            $stmt = $conn->prepare("SELECT puesto FROM puestos WHERE id = ?");
            $stmt->bind_param("i", $puesto_id);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_assoc();
            if ($result) $puesto_nombre = $result['puesto'];
        }
        
        $usuario_baja_nombre = 'No aplica';
        if (!empty($usuario_baja_id)) {
            $stmt = $conn->prepare("SELECT nombre FROM usuarios WHERE id = ?");
            $stmt->bind_param("i", $usuario_baja_id);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_assoc();
            if ($result) $usuario_baja_nombre = $result['nombre'];
        }
        
        $colaborador_respaldo_nombre = 'No aplica';
        if (!empty($colaborador_respaldo_id)) {
            $stmt = $conn->prepare("SELECT nombre FROM usuarios WHERE id = ?");
            $stmt->bind_param("i", $colaborador_respaldo_id);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_assoc();
            if ($result) $colaborador_respaldo_nombre = $result['nombre'];
        }
        
        // (Y así para cualquier otro ID que necesites convertir, como los de cambio de puesto)
        $usuario_cambio_nombre = 'No aplica';
        if (!empty($usuario_cambio_id)) {
            $stmt = $conn->prepare("SELECT nombre FROM usuarios WHERE id = ?");
            $stmt->bind_param("i", $usuario_cambio_id);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_assoc();
            if ($result) $usuario_cambio_nombre = $result['nombre'];
        }
        
        $puesto_anterior_nombre = 'No aplica';
        if (!empty($puesto_anterior_id)) {
            $stmt = $conn->prepare("SELECT puesto FROM puestos WHERE id = ?");
            $stmt->bind_param("i", $puesto_anterior_id);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_assoc();
            if ($result) $puesto_anterior_nombre = $result['puesto'];
        }
        
        $puesto_nuevo_nombre = 'No aplica';
        if (!empty($puesto_nuevo_id)) {
            $stmt = $conn->prepare("SELECT puesto FROM puestos WHERE id = ?");
            $stmt->bind_param("i", $puesto_nuevo_id);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_assoc();
            if ($result) $puesto_nuevo_nombre = $result['puesto'];
        }
        if (!empty($destinatarios)) {
            $mail = new PHPMailer(true);
        
            // --- Configuración del servidor de correo (SMTP) ---
            $mail->isSMTP();
            $mail->Host       = 'mail.intranetdrg.com.mx';
            $mail->SMTPAuth   = true;
            $mail->Username   = 'administrador@intranetdrg.com.mx';
            $mail->Password   = 'WbrE5%7p';
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            $mail->Port       = 465;
            $mail->CharSet    = 'UTF-8';
        
            // --- Remitente y destinatarios ---
            $mail->setFrom('administrador@intranetdrg.com.mx', 'Sistema de Altas Bajas y Cambios');
            foreach ($destinatarios as $email) {
                $mail->addAddress($email);
            }
        
            $mail->isHTML(true);
            $mail->Subject = "Nueva Solicitud | Folio: $folio_formateado";
            
            $fecha_envio = date("d/m/Y H:i");
            
            // --- Estilos básicos para tabla ---
            $styles = "
                <style>
                    body { font-family: Arial, sans-serif; background-color:#f7f7f7; }
                    .container { padding:20px; background:#fff; border-radius:8px; }
                    h2 { color:#299dbf; }
                    table { width:100%; border-collapse: collapse; margin-top:10px; }
                    th, td { padding:8px 12px; border:1px solid #ddd; text-align:left; }
                    th { background-color:#f0f0f0; }
                    .tag-alta { color:green; font-weight:bold; }
                    .tag-baja { color:red; font-weight:bold; }
                    .tag-cambio { color:#ff9800; font-weight:bold; }
                    .footer { margin-top:20px; font-size:12px; color:#666; }
                    a.btn { display:inline-block; padding:8px 12px; background:#299dbf; color:#fff; border-radius:5px; text-decoration:none; }
                </style>
            ";
            
            // --- Info general siempre visible ---
            $body = "
            <html>
            <head>$styles</head>
            <body>
                <div class='container'>
                    <h2>Nueva Solicitud Registrada</h2>
                    <p>Se ha registrado una nueva solicitud en el sistema de <strong>Altas, Bajas y Cambios</strong>.</p>
            
                    <table>
                        <tr><th>Folio</th><td>$folio_formateado</td></tr>
                        <tr><th>Tipo de Solicitud</th><td><span class='tag-{$tipo_solicitud_key}'>$tipo_solicitud_texto</span></td></tr>
                        <tr><th>Solicitante</th><td>$solicitante</td></tr>
                        <tr><th>Fecha de envío</th><td>$fecha_envio</td></tr>
                    </table>
            ";
            
            // --- Bloques condicionales según tipo ---
            if ($tipo_solicitud_key === "alta" || $tipo_solicitud_key === "remplazo" || $tipo_solicitud_key === "practicante") {
                $body .= "
                    <h3>Información del Colaborador</h3>
                    <table>
                        <tr><th>Nombre</th><td>$nombres $apellido_paterno $apellido_materno</td></tr>
                        <tr><th>Nombre Predilecto</th><td>$nombre_predilecto</td></tr>
                        <tr><th>No. Empleado</th><td>$numero_empleado</td></tr>
                        <tr><th>Sucursal</th><td>" . htmlspecialchars($sucursal_nombre) . "</td></tr>
                        <tr><th>Puesto</th><td>" . htmlspecialchars($puesto_nombre) . "</td></tr>
                        <tr><th>Fecha de Nacimiento</th><td>$fecha_nacimiento</td></tr>
                        <tr><th>Fecha de Ingreso</th><td>$fecha_ingreso</td></tr>
                        <tr><th>Teléfono</th><td>$telefono</td></tr>
                        <tr><th>Dirección</th><td>$direccion</td></tr>
                    </table>
                ";
            
                if ($tipo_solicitud_key === "remplazo" && $usuario_a_reemplazar_info) {
                    $body .= "<p><strong>Reemplaza a:</strong> $usuario_a_reemplazar_info</p>";
                }
            
                if ($tipo_solicitud_key === "practicante" && $actividades_practicante) {
                    $body .= "<p><strong>Actividades asignadas:</strong> $actividades_practicante</p>";
                }
            }
            
            if ($tipo_solicitud_key === "cambio_puesto") {
                $body .= "
                    <h3>Información del Cambio</h3>
                    <table>
                        <tr><th>Usuario</th><td>" . htmlspecialchars($usuario_cambio_nombre) . "</td></tr>
                        <tr><th>Puesto Anterior</th><td>" . htmlspecialchars($puesto_anterior_nombre) . "</td></tr>
                        <tr><th>Puesto Nuevo</th><td>" . htmlspecialchars($puesto_nuevo_nombre) . "</td></tr>
                        <tr><th>Justificación</th><td>$justificacion</td></tr>
                    </table>
                ";
            }
            
            if ($tipo_solicitud_key === "baja") {
                $body .= "
                    <h3>Información de la Baja</h3>
                    <table>
                        <tr><th>Usuario</th><td>" . htmlspecialchars($usuario_baja_nombre) . "</td></tr>
                        <tr><th>Foráneo</th><td>" . ($es_foraneo ? "Sí" : "No") . "</td></tr>
                        <tr><th>Colaborador de Respaldo</th><td>" . htmlspecialchars($colaborador_respaldo_nombre) . "</td></tr>
                        <tr><th>Recoge Productos</th><td>$colaborador_recoge_productos</td></tr>
                        <tr><th>Baja por Reemplazo</th><td>" . ($es_baja_por_reemplazo ? "Sí" : "No") . "</td></tr>
                    </table>
                ";
            }
            
            // --- Observaciones y evidencia ---
            if (!empty($observaciones)) {
                $body .= "<p><strong>Observaciones:</strong> $observaciones</p>";
            }
            if (!empty($archivo_evidencia_path)) {
                $body .= "<p><strong>Evidencia:</strong> Archivo adjunto en sistema.</p>";
            }
            
            // --- Footer con acceso al sistema ---
            $body .= "
                <div class='footer'>Este correo fue generado automáticamente por el sistema de Solicitudes ABC.</div>
                </div>
            </body>
            </html>
            ";
            
            $mail->Body = $body;

        
            $mail->send();
        }
    } catch (Exception $e) {
        // Si el envío de correo falla, no se revierte la transacción, pero se registra el error
        error_log("Error al enviar correo de notificación para folio $folio_formateado: " . $mail->ErrorInfo);
    }
    
    // Enviar respuesta de éxito al frontend
    echo json_encode(['success' => true, 'message' => 'Solicitud enviada con éxito.', 'folio' => $folio_formateado]);

} catch (Exception $e) {
    // Si algo sale mal en la base de datos, revertir todos los cambios
    $conn->rollback();
    
    // Registrar el error para depuración
    error_log("Error en procesar_solicitud_actualizado.php: " . $e->getMessage());
    
    // Enviar una respuesta de error genérica
    echo json_encode(['success' => false, 'message' => 'Ocurrió un error inesperado al procesar la solicitud. Detalles: ' . $e->getMessage()]);
}



$conn->close();
?>

