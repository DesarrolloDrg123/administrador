<?php

session_start();

require("../config/db.php");
require('../vendor/autoload.php');

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// --- 1. VALIDACIÓN DE SESIÓN Y DATOS INICIALES ---
if (!isset($_SESSION['loggedin']) || !$_SESSION['loggedin']) {
    header("Location: ../index.php");
    exit();
}

// Datos de la sesión
$usuario_id = $_SESSION['usuario_id'];
$autoriza = $_SESSION['jefe'];
$departamento = $_SESSION['departamento'];
$nombre_solicitante = $_SESSION['nombre'];
$correo_jefe = null;

// --- 2. OBTENER CORREO DEL JEFE ---
try {
    $query = "SELECT email FROM usuarios WHERE id = ?";
    $stmt_jefe = $conn->prepare($query);
    $stmt_jefe->bind_param('i', $autoriza);
    $stmt_jefe->execute();
    $resultado = $stmt_jefe->get_result()->fetch_assoc();
    $correo_jefe = $resultado['email'] ?? null;
    $stmt_jefe->close();
} catch (Exception $e) {
    error_log("Error al obtener correo del jefe: " . $e->getMessage());
}

// --- 3. VALIDAR FECHAS RECIBIDAS DEL FORMULARIO ---
$fecha_inicio_str = $_POST['fecha_inicio'] ?? '';
$fecha_fin_str = $_POST['fecha_fin'] ?? '';

if (empty($fecha_inicio_str) || empty($fecha_fin_str)) {
    $_SESSION['error'] = "Debes seleccionar las fechas de inicio y fin.";
    header("Location: ../solicitar_vacaciones.php");
    exit();
}

$fecha_inicio_obj = DateTime::createFromFormat('d/m/Y', $fecha_inicio_str);
$fecha_fin_obj = DateTime::createFromFormat('d/m/Y', $fecha_fin_str);

if (!$fecha_inicio_obj || !$fecha_fin_obj || $fecha_fin_obj < $fecha_inicio_obj) {
    $_SESSION['error'] = "Las fechas no son válidas o la fecha de fin es anterior a la de inicio.";
    header("Location: ../solicitar_vacaciones.php");
    exit();
}

$fecha_inicio_mysql = $fecha_inicio_obj->format('Y-m-d');
$fecha_fin_mysql = $fecha_fin_obj->format('Y-m-d');

// --- 4. OBTENER DÍAS FERIADOS DE LA BASE DE DATOS ---
try {
    $sql_feriados = "SELECT fecha FROM dias_feriados";
    $result_feriados = $conn->query($sql_feriados);
    $dias_feriados = [];
    while ($row = $result_feriados->fetch_assoc()) {
        $dias_feriados[] = $row['fecha']; // Guardar en formato Y-m-d
    }
} catch (Exception $e) {
    error_log("Error al obtener días feriados: " . $e->getMessage());
    $dias_feriados = [];
}

/**
 * Función mejorada para calcular días hábiles.
 * Ahora excluye fines de semana Y días feriados.
 */
function calcular_dias_habiles($fecha_inicio, $fecha_fin, $feriados = []) {
    $inicio = new DateTime($fecha_inicio);
    $fin = new DateTime($fecha_fin);
    $fin->modify('+1 day'); // Incluir el último día en el rango

    $periodo = new DatePeriod($inicio, new DateInterval('P1D'), $fin);
    $dias_habiles = 0;

    foreach ($periodo as $dia) {
        $dia_semana = $dia->format('N'); // 1 (Lunes) a 7 (Domingo)
        $fecha_actual_str = $dia->format('Y-m-d');

        // Contar solo si no es Sábado (6), Domingo (7) y no está en la lista de feriados
        if ($dia_semana < 6 && !in_array($fecha_actual_str, $feriados)) {
            $dias_habiles++;
        }
    }
    return $dias_habiles;
}

// --- 5. CALCULAR DÍAS SOLICITADOS (EL CÁLCULO CORRECTO) ---
$dias_solicitados = calcular_dias_habiles($fecha_inicio_mysql, $fecha_fin_mysql, $dias_feriados);

// --- 6. GUARDAR LA SOLICITUD EN LA BASE DE DATOS ---
try {
    // Se agrega la columna 'dias_solicitados' a la consulta
    $sql = "INSERT INTO solicitudes_vacaciones (usuario_id, fecha_inicio, fecha_fin, dias_solicitados, departamento_solicitante) VALUES (?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("Error al preparar la consulta: " . $conn->error);
    }
    // Se agrega el nuevo parámetro (i para integer)
    $stmt->bind_param("issis", $usuario_id, $fecha_inicio_mysql, $fecha_fin_mysql, $dias_solicitados, $departamento);
    $stmt->execute();

    if ($stmt->affected_rows > 0) {
        $_SESSION['success'] = "Solicitud de vacaciones registrada exitosamente.";
    } else {
        throw new Exception("No se pudo registrar la solicitud.");
    }
    $stmt->close();
} catch (Exception $e) {
    error_log("Error al registrar la solicitud: " . $e->getMessage());
    $_SESSION['error'] = "Ocurrió un error al procesar tu solicitud.";
    header("Location: ../solicitar_vacaciones.php");
    exit();
}

// --- 7. ENVIAR CORREO DE NOTIFICACIÓN ---
$mail = new PHPMailer(true);

function formatear_fecha_espanol($fecha) {
    $date = DateTime::createFromFormat('Y-m-d', $fecha);
    $formatter = new IntlDateFormatter('es_ES', IntlDateFormatter::LONG, IntlDateFormatter::NONE, null, null, 'd \'de\' MMMM \'de\' yyyy');
    return $formatter->format($date);
}

$fecha_inicio_formateada = formatear_fecha_espanol($fecha_inicio_mysql);
$fecha_fin_formateada = formatear_fecha_espanol($fecha_fin_mysql);

try {
    $mail->isSMTP();
    $mail->Host = 'mail.intranetdrg.com.mx';
    $mail->SMTPAuth = true;
    $mail->Username = 'administrador@intranetdrg.com.mx';
    $mail->Password = 'WbrE5%7p';
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
    $mail->Port = 465;
    $mail->setFrom('administrador@intranetdrg.com.mx', 'Solicitud De Vacaciones');
    
    if($correo_jefe == 'javier@drg.mx') {
        $mail->addAddress($correo_jefe);
        $mail->addAddress('mgarcia@drg.mx');
    } else {
        $mail->addAddress($correo_jefe);
    }
    
    $mail->isHTML(true);
    $mail->CharSet = 'UTF-8';
    $mail->Subject = 'Solicitud de Vacaciones para: ' . $nombre_solicitante;
    $mail->Body = "
    <html><body>
        <div class='container'>
            <h1>Nueva Solicitud de Vacaciones</h1>
            <p>Se ha recibido una nueva solicitud de vacaciones para su revisión y aprobación.</p>
            <hr>
            <p><strong>Solicitante:</strong> {$nombre_solicitante}</p>
            <p><strong>Fecha de Inicio:</strong> {$fecha_inicio_formateada}</p>
            <p><strong>Fecha de Fin:</strong> {$fecha_fin_formateada}</p>
            <p><strong>Total de días hábiles solicitados:</strong> {$dias_solicitados}</p>
            <hr>
            <p>Por favor, ingrese al portal para gestionarla.</p>
        </div>
    </body></html>
    ";
    $mail->send();
} catch (Exception $e) {
    error_log("No se pudo enviar el correo. Error: {$mail->ErrorInfo}");
}

// --- 8. REDIRIGIR AL USUARIO ---
header("Location: ../inicio.php");
exit();
?>