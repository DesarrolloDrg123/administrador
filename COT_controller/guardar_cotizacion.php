<?php
session_start();

header('Content-Type: application/json');

// Incluye tus archivos de configuración y librerías
require("../config/db.php");
require '../vendor/autoload.php'; 

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Array para la respuesta JSON
$response = ['success' => false, 'message' => ''];

function obtenerCorreosCotizadores($conn, $es_rfid) {
    // ID del programa al que deben tener acceso los usuarios
    $id_programa_cotizaciones = 30;
    
    $sql = "";

    // Si $es_rfid es 1 (verdadero), se busca al líder con el puesto específico
    if ($es_rfid == 1) {
        $sql = "SELECT u.email FROM usuarios u 
                JOIN permisos p ON u.id = p.id_usuario 
                WHERE p.id_programa = ? AND p.acceso = 1 AND (u.puesto = 'Líder de Etiquetado y Codificado' OR u.puesto = 'Ingeniero de Preventa')  AND u.estatus = 1";
    } else {
        // Si no, se busca a todos los que tengan acceso EXCEPTO al líder de RFID
        $sql = "SELECT u.email FROM usuarios u 
                JOIN permisos p ON u.id = p.id_usuario 
                WHERE p.id_programa = ? AND p.acceso = 1 AND (u.puesto != 'Líder de Etiquetado y Codificado' OR u.puesto != 'Ingeniero de Preventa') AND u.estatus = 1";
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

function enviarNotificacionCotizacion($destinatarios, $asunto, $cuerpo_html) {
    if (empty($destinatarios)) {
        error_log("No se encontraron destinatarios para el correo de cotización.");
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
        error_log("Error al enviar correo de cotización: " . $mail->ErrorInfo);
    }
}

// 1. Verificar que la solicitud sea por método POST
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    $response['message'] = 'Error: Método de solicitud no válido.';
    echo json_encode($response);
    exit();
}

// 2. Verificar que el usuario esté logueado
if (!isset($_SESSION['nombre'])) {
    $response['message'] = 'Error: Sesión no iniciada. Por favor, inicie sesión.';
    echo json_encode($response);
    exit();
}

// Iniciar una transacción para asegurar la integridad de los datos
$conn->begin_transaction();

function obtenerFolio($conn) {

    // Consulta SQL para obtener el folio basado en el ID del pedido
    $sql = "SELECT folio FROM control_folios_co WHERE id = 1 FOR UPDATE";
    $resultado = $conn->query($sql);

    // Verificar si hay resultados
    if ($resultado->num_rows > 0) {
        $fila = $resultado->fetch_assoc();
        $ultimo_folio = $fila['folio'];

        // Incrementar el folio
        if ($ultimo_folio !== null) {
            $ultimo_folio++;
        } else {
            $ultimo_folio = 1; // Si no hay registros, el folio comienza en 1
        }

        // Formatear el número con ceros a la izquierda
        $folio_formateado = sprintf('%09d', $ultimo_folio);
        return $folio_formateado;
    } else {
        return sprintf('%09d', 1); // Si no se encontró ningún registro, comenzamos en 1 y lo formateamos
    }
}

try {
    // =================================================================
    // === 3. GUARDAR DATOS GENERALES EN LA TABLA `datos_generales_co`
    // =================================================================

    // Prepara la consulta SQL para la tabla principal usando marcadores de posición (?)
    $sql_general = "INSERT INTO datos_generales_co 
                        (folio, rfid, empresa, cliente, telefono, celular, correo, observaciones, estatus, user_solicitante, fecha_solicitud) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";

    $stmt_general = $conn->prepare($sql_general);

    if ($stmt_general === false) {
        throw new Exception("Error al preparar la consulta de datos generales: " . $conn->error);
    }
    
    $folio = obtenerFolio($conn);
    $nuevoFolio = $folio;
    
    // Recolectar y sanitizar los datos del formulario
    $empresa = $_POST['empresa'];
    $cliente = $_POST['nombre_cliente'];
    $telefono = $_POST['telefono'];
    $celular = $_POST['celular'];
    $correo = $_POST['correo_cliente'];
    $observaciones = $_POST['observaciones'] ?? ''; // Campo opcional
    $rfid = isset($_POST['is_rfid']) ? 1 : 0; // Si el checkbox está marcado, es 1, si no, 0
    $user_solicitante = $_SESSION['nombre'];
    $estatus = 'Nuevo'; // Estatus inicial por defecto

    // Vincular los parámetros a la consulta preparada
    $stmt_general->bind_param(
        "iissiissss",
        $nuevoFolio,
        $rfid,
        $empresa,
        $cliente,
        $telefono,
        $celular,
        $correo,
        $observaciones,
        $estatus,
        $user_solicitante
    );

    // Ejecutar la consulta y verificar si fue exitosa
    if (!$stmt_general->execute()) {
        throw new Exception("Error al guardar los datos generales: " . $stmt_general->error);
    }
    $stmt_general->close();
    
    // Actualizar el folio en el control de folios
    $stmt = $conn->prepare("UPDATE control_folios_co SET folio = ? WHERE id = 1");
    $stmt->bind_param("i", $nuevoFolio);
    $stmt->execute();


    // =================================================================
    // === 4. GUARDAR LOS PRODUCTOS EN LA TABLA `productos_co`
    // =================================================================

    // Prepara la consulta SQL para los productos
    $sql_productos = "INSERT INTO productos_co (folio, sku, descripcion, cantidad, notas) VALUES (?, ?, ?, ?, ?)";
    $stmt_productos = $conn->prepare($sql_productos);

    if ($stmt_productos === false) {
        throw new Exception("Error al preparar la consulta de productos: " . $conn->error);
    }

    // Recolectar los arrays de productos del formulario
    $skus = $_POST['sku'] ?? [];
    $descripciones = $_POST['descripcion'] ?? [];
    $cantidades = $_POST['cantidad'] ?? [];
    $notas = $_POST['nota'] ?? [];

    // Iterar sobre cada producto y guardarlo
    for ($i = 0; $i < count($skus); $i++) {
        // Ignorar filas vacías que el usuario pudo haber dejado
        if (empty($skus[$i]) && empty($descripciones[$i]) && empty($cantidades[$i])) {
            continue; // Salta a la siguiente iteración del bucle
        }

        // Vincular los parámetros para cada producto
        $stmt_productos->bind_param(
            "sssis",
            $nuevoFolio,
            $skus[$i],
            $descripciones[$i],
            $cantidades[$i],
            $notas[$i]
        );

        // Ejecutar la consulta para este producto
        if (!$stmt_productos->execute()) {
            throw new Exception("Error al guardar el producto con SKU " . htmlspecialchars($skus[$i]) . ": " . $stmt_productos->error);
        }
    }
    $stmt_productos->close();

    // Si todo salió bien, confirma los cambios en la base de datos
    $conn->commit();

    $correos_cotizadores = obtenerCorreosCotizadores($conn, $rfid);
    $asunto = "Nueva Solicitud de Cotización - Folio " . $folio;
    $cuerpo_html = "
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
                <h1>Nueva Solicitud de Cotización</h1>
                <p>Se ha registrado una nueva solicitud de cotización que requiere su atención.</p>
                
                <div class='info-row'>
                    <span class='label'>Folio:</span>
                    <span>" . htmlspecialchars($folio) . "</span>
                </div>
                
                <div class='info-row'>
                    <span class='label'>Fecha de Solicitud:</span>
                    <span>" . date('d/m/Y') . "</span>
                </div>
                
                <div class='info-row'>
                    <span class='label'>Solicitante:</span>
                    <span>" . htmlspecialchars($user_solicitante) . "</span>
                </div>
                
                <hr>
                <p style='text-align:center;'><strong>Por favor, ingrese al portal para procesarla.</strong></p>
    
            </div>
        </body>
        </html>
    ";
    enviarNotificacionCotizacion($correos_cotizadores, $asunto, $cuerpo_html);

    // Preparar la respuesta de éxito para AJAX
    $response['success'] = true;
    $response['message'] = "Cotización con folio " . htmlspecialchars($folio_formateado) . " guardada correctamente.";

} catch (Exception $e) {
    // Si algo falló, revierte todos los cambios
    $conn->rollback();

    // Preparar la respuesta de error
    $response['message'] = "Error: " . $e->getMessage();
}

// Cierra la conexión a la base de datos
$conn->close();

// Envía la respuesta final en formato JSON al frontend
echo json_encode($response);
?>