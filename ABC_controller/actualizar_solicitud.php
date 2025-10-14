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

// Obtener el ID de la solicitud a actualizar.
$id_solicitud = $_POST['id_solicitud'] ?? null;
if (empty($id_solicitud)) {
    echo json_encode(['success' => false, 'message' => 'Error: No se proporcionó el ID de la solicitud.']);
    exit;
}

// Iniciar transacción para asegurar la integridad de los datos
$conn->begin_transaction();

try {
    // --- OBTENER DATOS EXISTENTES ---
    $stmt_old_data = $conn->prepare("SELECT folio, archivo_evidencia_path FROM solicitudes_movimientos_personal WHERE id = ?");
    $stmt_old_data->bind_param("i", $id_solicitud);
    $stmt_old_data->execute();
    $old_data = $stmt_old_data->get_result()->fetch_assoc();
    if (!$old_data) {
        throw new Exception("La solicitud que intentas actualizar no existe.");
    }
    $folio_formateado = $old_data['folio'];
    $archivo_evidencia_anterior = $old_data['archivo_evidencia_path'];

    // --- RECOLECCIÓN DE DATOS DEL FORMULARIO ---
    $solicitante = $_SESSION['nombre'];
    $tipo_solicitud_map = [
        'alta' => 'Alta de usuario', 'cambio_puesto' => 'Alta por cambio de puesto',
        'remplazo' => 'Alta por remplazo de usuario', 'practicante' => 'Practicante',
        'baja' => 'Baja de usuario'
    ];
    $tipo_solicitud_key = $_POST['tipo_solicitud'] ?? '';
    $tipo_solicitud_texto = $tipo_solicitud_map[$tipo_solicitud_key] ?? 'No definido';

    // Campos de Alta / Remplazo / Practicante
    $usuario_remplazo_id = $_POST['usuario_remplazo_id'] ?? null;
    $sucursal_id = $_POST['sucursal'] ?? null;
    $numero_empleado = $_POST['numero_empleado'] ?? null;
    $nombres = $_POST['nombres'] ?? null;
    $apellido_paterno = $_POST['apellido_paterno'] ?? null;
    $apellido_materno = $_POST['apellido_materno'] ?? null;
    $nombre_predilecto = $_POST['nombre_predilecto'] ?? null;
    $puesto_id = $_POST['puesto_alta'] ?? null;
    $telefono = $_POST['telefono'] ?? null;
    $fecha_nacimiento = !empty($_POST['fecha_nacimiento']) ? $_POST['fecha_nacimiento'] : null;
    $fecha_ingreso = !empty($_POST['fecha_ingreso']) ? $_POST['fecha_ingreso'] : null;
    $direccion = $_POST['direccion'] ?? null;
    $actividades_practicante = $_POST['actividades_practicante'] ?? null;

    // Generar info del usuario a reemplazar (si aplica)
    $usuario_a_reemplazar_info = null;
    if ($tipo_solicitud_key === 'remplazo' && !empty($usuario_remplazo_id)) {
        $stmt_check = $conn->prepare("SELECT u.nombre, p.puesto FROM usuarios u LEFT JOIN puestos p ON u.puesto_id = p.id WHERE u.id = ?");
        $stmt_check->bind_param("i", $usuario_remplazo_id);
        $stmt_check->execute();
        $result_check = $stmt_check->get_result()->fetch_assoc();
        if ($result_check) {
            $usuario_a_reemplazar_info = "Reemplaza a: " . htmlspecialchars($result_check['nombre']) . " (Puesto: " . htmlspecialchars($result_check['puesto']) . ")";
        }
    }

    // Campos de Cambio de puesto
    $usuario_cambio_id = $_POST['usuario_cambio_id'] ?? null;
    $puesto_anterior_id = $_POST['puesto_anterior'] ?? null;
    $puesto_nuevo_id = $_POST['puesto_nuevo'] ?? null;
    $justificacion = $_POST['justificacion_cambio'] ?? null;

    // Campos de Baja
    $usuario_baja_id = $_POST['usuario_baja_id'] ?? null;
    $es_foraneo = isset($_POST['es_foraneo']) && $_POST['es_foraneo'] == '1' ? 1 : 0;
    $colaborador_respaldo_id = $_POST['colaborador_respaldo_id'] ?? null;
    $colaborador_recoge_productos = $_POST['colaborador_recoge_productos'] ?? null;
    $es_baja_por_reemplazo = isset($_POST['es_baja_por_reemplazo']) && $_POST['es_baja_por_reemplazo'] == '1' ? 1 : 0;

    // Campo común
    $observaciones = $_POST['observaciones'] ?? null;

    // --- LÓGICA PARA LIMPIAR DATOS NO RELEVANTES ---
    // Esto previene que datos de una sección anterior permanezcan si se cambia el tipo de solicitud.
    if (!in_array($tipo_solicitud_key, ['alta', 'remplazo', 'practicante'])) {
        $sucursal_id = null; $numero_empleado = null; $nombres = null; $apellido_paterno = null; $apellido_materno = null; $nombre_predilecto = null; $puesto_id = null; $telefono = null; $fecha_nacimiento = null; $fecha_ingreso = null; $direccion = null; $actividades_practicante = null; $usuario_remplazo_id = null; $usuario_a_reemplazar_info = null;
    }
    if ($tipo_solicitud_key !== 'cambio_puesto') {
        $usuario_cambio_id = null; $puesto_anterior_id = null; $puesto_nuevo_id = null; $justificacion = null;
    }
    if ($tipo_solicitud_key !== 'baja') {
        $usuario_baja_id = null; $es_foraneo = 0; $colaborador_respaldo_id = null; $colaborador_recoge_productos = null; $es_baja_por_reemplazo = 0;
    }
    
    // --- GESTIÓN DE ARCHIVO ADJUNTO ---
    $archivo_evidencia_path = $archivo_evidencia_anterior;
    if (isset($_FILES['archivo_evidencia_path']) && $_FILES['archivo_evidencia_path']['error'] == UPLOAD_ERR_OK) {
        $uploadDir = '../uploads/evidencias/';
        if (!is_dir($uploadDir)) { mkdir($uploadDir, 0755, true); }
        $nombre_base = preg_replace("/[^a-zA-Z0-9\._-]/", "", basename($_FILES['archivo_evidencia_path']['name']));
        $fileName = $folio_formateado . '_' . $nombre_base;
        $uploadFilePath = $uploadDir . $fileName;
        
        if (move_uploaded_file($_FILES['archivo_evidencia_path']['tmp_name'], $uploadFilePath)) {
            $archivo_evidencia_path = $uploadFilePath;
            if (!empty($archivo_evidencia_anterior) && $archivo_evidencia_anterior !== $archivo_evidencia_path && file_exists($archivo_evidencia_anterior)) {
                unlink($archivo_evidencia_anterior);
            }
        } else {
            throw new Exception('Error al mover el nuevo archivo subido.');
        }
    }
    
    // --- CONSTRUCCIÓN DEL UPDATE ---
    $sql_update = "UPDATE solicitudes_movimientos_personal SET 
        tipo_solicitud = ?, sucursal_id = ?, fecha_solicitud = CURDATE(),  numero_empleado = ?, nombres = ?, apellido_paterno = ?, 
        apellido_materno = ?, nombre_predilecto = ?, puesto_id = ?, telefono = ?, fecha_nacimiento = ?, 
        fecha_ingreso = ?, direccion = ?, usuario_remplazo_id = ?, usuario_a_reemplazar_info = ?, 
        actividades_practicante = ?, usuario_cambio_id = ?, puesto_anterior_id = ?, puesto_nuevo_id = ?, 
        justificacion = ?, usuario_baja_id = ?, es_foraneo = ?, colaborador_respaldo_id = ?, 
        colaborador_recoge_productos = ?, es_baja_por_reemplazo = ?, observaciones = ?, 
        archivo_evidencia_path = ?, estatus = ?, motivo = NULL
        WHERE id = ?";
    
    $stmt_update = $conn->prepare($sql_update);
    
    $nuevo_estatus = 'Nueva Solicitud';
    // La cadena de tipos debe coincidir exactamente con los ? en la consulta
    $types = 'sisssssissssissiiisiiisisssi';
    
    $stmt_update->bind_param(
        $types,
        $tipo_solicitud_texto,     // 1 s
        $sucursal_id,              // 2 i
        $numero_empleado,          // 3 s
        $nombres,                  // 4 s
        $apellido_paterno,         // 5 s
        $apellido_materno,         // 6 s
        $nombre_predilecto,        // 7 s
        $puesto_id,                // 8 i
        $telefono,                 // 9 s
        $fecha_nacimiento,         //10 s
        $fecha_ingreso,            //11 s
        $direccion,                //12 s
        $usuario_remplazo_id,      //13 i
        $usuario_a_reemplazar_info,//14 s
        $actividades_practicante,  //15 s
        $usuario_cambio_id,        //16 i
        $puesto_anterior_id,       //17 i
        $puesto_nuevo_id,          //18 i
        $justificacion,            //19 s
        $usuario_baja_id,          //20 i
        $es_foraneo,               //21 i
        $colaborador_respaldo_id,  //22 i
        $colaborador_recoge_productos, //23 s
        $es_baja_por_reemplazo,    //24 i
        $observaciones,            //25 s
        $archivo_evidencia_path,   //26 s
        $nuevo_estatus,            //27 s
        $id_solicitud              //28 i
    );

    
    $stmt_update->execute();

    // --- REGISTRO EN EL HISTORIAL ---
    $historial_sql = "INSERT INTO solicitudes_movimientos_personal_historial (solicitud_id, usuario_nombre, estatus_cambio, observacion) VALUES (?, ?, ?, ?)";
    $stmt_historial = $conn->prepare($historial_sql);
    $observacion_historial = 'Solicitud actualizada y reenviada por el solicitante.';
    $stmt_historial->bind_param("isss", $id_solicitud, $solicitante, $nuevo_estatus, $observacion_historial);
    $stmt_historial->execute();

    $conn->commit();

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
            $link_solicitud = "https://tusistema.com/solicitudes/folio/$folio_formateado";
            
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
                        <tr><th>Sucursal</th><td>$sucursal_id</td></tr>
                        <tr><th>Puesto</th><td>$puesto_id</td></tr>
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
                        <tr><th>Usuario</th><td>$usuario_cambio_id</td></tr>
                        <tr><th>Puesto Anterior</th><td>$puesto_anterior_id</td></tr>
                        <tr><th>Puesto Nuevo</th><td>$puesto_nuevo_id</td></tr>
                        <tr><th>Justificación</th><td>$justificacion</td></tr>
                    </table>
                ";
            }
            
            if ($tipo_solicitud_key === "baja") {
                $body .= "
                    <h3>Información de la Baja</h3>
                    <table>
                        <tr><th>Usuario</th><td>$usuario_baja_id</td></tr>
                        <tr><th>Foráneo</th><td>" . ($es_foraneo ? "Sí" : "No") . "</td></tr>
                        <tr><th>Colaborador de Respaldo</th><td>$colaborador_respaldo_id</td></tr>
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
                <p><a class='btn' href='$link_solicitud'>🔗 Ver solicitud en el sistema</a></p>
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
    
    echo json_encode(['success' => true, 'message' => 'Solicitud actualizada con éxito.', 'folio' => $folio_formateado]);

} catch (Exception $e) {
    $conn->rollback();
    error_log("Error en actualizar_solicitud.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Ocurrió un error inesperado al actualizar la solicitud.']);
}

$conn->close();
?>

