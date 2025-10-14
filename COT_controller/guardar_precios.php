<?php
session_start();
require("../config/db.php");
require '../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use Mpdf\Mpdf;

// Preparamos la respuesta para AJAX
$response = ['success' => false, 'message' => ''];
header('Content-Type: application/json');

function obtenerCotizacionCompleta($conn, $folio) {
    $sql_general = "SELECT * FROM datos_generales_co WHERE folio = ?";
    $stmt_general = $conn->prepare($sql_general);
    $stmt_general->bind_param("s", $folio);
    $stmt_general->execute();
    $cotizacion = $stmt_general->get_result()->fetch_assoc();
    $stmt_general->close();
    return $cotizacion;
}

function obtenerProductosCompletos($conn, $folio) {
    $sql_productos = "SELECT * FROM productos_co WHERE folio = ?";
    $stmt_productos = $conn->prepare($sql_productos);
    $stmt_productos->bind_param("s", $folio);
    $stmt_productos->execute();
    $result = $stmt_productos->get_result();
    $productos = [];
    while ($row = $result->fetch_assoc()) {
        $productos[] = $row;
    }
    $stmt_productos->close();
    return $productos;
}

function generarHtmlParaPdf($cotizacion, $productos) {
    $folio_formateado = sprintf('%09d', $cotizacion['folio']);
    $fecha_formateada = date('d/m/Y', strtotime($cotizacion['fecha_cotizacion']));
    $cliente_info = "<strong>Empresa:</strong> " . htmlspecialchars($cotizacion['empresa']) . "<br><strong>Cliente:</strong> " . htmlspecialchars($cotizacion['cliente']);
    $tabla_productos = '';
    foreach ($productos as $index => $producto) {
        $op1_style = $producto['recomendacion1'] ? 'style="background-color: #d4edda; font-weight: bold;"' : '';
        $op2_style = $producto['recomendacion2'] ? 'style="background-color: #d4edda; font-weight: bold;"' : '';
        $op3_style = $producto['recomendacion3'] ? 'style="background-color: #d4edda; font-weight: bold;"' : '';
        $tabla_productos .= "
            <tr>
                <td style='text-align: center;'>" . ($index + 1) . "</td>
                <td>" . htmlspecialchars($producto['sku']) . "<br><small>" . htmlspecialchars($producto['descripcion']) . "</small></td>
                <td style='text-align: center;'>" . htmlspecialchars($producto['cantidad']) . "</td>
                <td style='font-size: 0.9em; color: #555;'>
                    <span {$op1_style}>Op 1: " . htmlspecialchars($producto['proveedor1']) . " - $" . number_format($producto['costo1'], 2) . " - Disp: " . htmlspecialchars($producto['disponibilidad1']) . " - T.E: " . htmlspecialchars($producto['tiempo_entrega1']) . "</span><br>
                    <span {$op2_style}>Op 2: " . htmlspecialchars($producto['proveedor2']) . " - $" . number_format($producto['costo2'], 2) . " - Disp: " . htmlspecialchars($producto['disponibilidad2']) . " - T.E: " . htmlspecialchars($producto['tiempo_entrega2']) . "</span><br>
                    <span {$op3_style}>Op 3: " . htmlspecialchars($producto['proveedor3']) . " - $" . number_format($producto['costo3'], 2) . " - Disp: " . htmlspecialchars($producto['tiempo_entrega3']) . " - T.E: " . htmlspecialchars($producto['tiempo_entrega3']) . "</span><br>
                </td>
            </tr>";
    }
    return "<html><head><style>body{font-family:sans-serif;}.header h1, .header p{margin:0;}.info{margin-top:30px;}table{width:100%;border-collapse:collapse;margin-top:20px;}th,td{border:1px solid #ddd;padding:8px;}th{background-color:#f2f2f2;}</style></head><body>
        <div class='header'><h1>Cotización</h1><p><strong>Folio:</strong> {$folio_formateado}</p><p><strong>Fecha:</strong> {$fecha_formateada}</p></div>
        <div class='info'>{$cliente_info}</div>
        <table><thead><tr><th>#</th><th>Producto</th><th>Cantidad</th><th>Opciones Cotizadas</th></tr></thead><tbody>{$tabla_productos}</tbody></table>
        <div class='info'><strong>Observaciones:</strong><p>" . nl2br(htmlspecialchars($cotizacion['observaciones'])) . "</p></div>
        </body></html>";
}

function enviarNotificacion($destinatarios, $asunto, $cuerpo_html, $adjunto_contenido = null, $adjunto_nombre = null) {
    if (empty($destinatarios)) { return; }
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP(); $mail->Host = 'mail.intranetdrg.com.mx'; $mail->SMTPAuth = true; $mail->Username = 'notification@intranetdrg.com.mx'; $mail->Password = 'r-eHQi64a7!3QT9'; $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; $mail->Port = 465; $mail->CharSet = 'UTF-8';
        $mail->setFrom('notification@intranetdrg.com.mx', 'DRG Notification');
        foreach ($destinatarios as $email) { $mail->addAddress($email); }
        if ($adjunto_contenido && $adjunto_nombre) {
            $mail->addStringAttachment($adjunto_contenido, $adjunto_nombre);
        }
        $mail->isHTML(true); $mail->Subject = $asunto; $mail->Body = $cuerpo_html;
        $mail->send();
    } catch (Exception $e) {
        error_log("Error al enviar correo: " . $mail->ErrorInfo);
    }
}


// 1. VALIDACIONES Y RECOPILACIÓN DE DATOS
if ($_SERVER["REQUEST_METHOD"] !== "POST") { exit(); }
if (!isset($_POST['folio'], $_POST['id_producto'], $_POST['accion'])) { exit(); }

$folio = $_POST['folio'];
$user_cotizador = $_POST['user_cotizador'] ?? $_SESSION['nombre'];
$accion = $_POST['accion'];

// Lógica de estatus
$nuevo_estatus_general = ($accion == 'enviar_cotizacion') ? 'Cotizado' : 'En Proceso';
$nuevo_estatus_productos = ($accion == 'enviar_cotizacion') ? 'Enviado' : 'Borrador';

// 2. TRANSACCIÓN DE BASE DE DATOS
$conn->begin_transaction();

try {
    // --- PASO A: ACTUALIZAR LOS DATOS Y ESTATUS DE LOS PRODUCTOS ---
    $sql_update_producto = "UPDATE productos_co SET 
        estatus = ?, 
        proveedor1 = ?, costo1 = ?, disponibilidad1 = ?, tiempo_entrega1 = ?, recomendacion1 = ?, 
        proveedor2 = ?, costo2 = ?, disponibilidad2 = ?, tiempo_entrega2 = ?, recomendacion2 = ?, 
        proveedor3 = ?, costo3 = ?, disponibilidad3 = ?, tiempo_entrega3 = ?, recomendacion3 = ? 
        WHERE id = ?";
    
    $stmt_producto = $conn->prepare($sql_update_producto);

    $ids_producto = $_POST['id_producto'];
    $proveedores1 = $_POST['proveedor1'];
    $costos1 = $_POST['costo1'];
    $disponibilidades1 = $_POST['disponibilidad1'];
    $tiempos_entrega1 = $_POST['tiempo_entrega1'];
    $proveedores2 = $_POST['proveedor2'];
    $costos2 = $_POST['costo2'];
    $disponibilidades2 = $_POST['disponibilidad2'];
    $tiempos_entrega2 = $_POST['tiempo_entrega2'];
    $proveedores3 = $_POST['proveedor3'];
    $costos3 = $_POST['costo3'];
    $disponibilidades3 = $_POST['disponibilidad3'];
    $tiempos_entrega3 = $_POST['tiempo_entrega3'];
    $recomendaciones = $_POST['recomendacion'] ?? [];
    
    for ($i = 0; $i < count($ids_producto); $i++) {
        $id_actual = $ids_producto[$i];
    
        $costo1_val = !empty($costos1[$i]) ? (float)$costos1[$i] : 0.0;
        $costo2_val = !empty($costos2[$i]) ? (float)$costos2[$i] : 0.0;
        $costo3_val = !empty($costos3[$i]) ? (float)$costos3[$i] : 0.0;
        $disp1_val = !empty($disponibilidades1[$i]) ? (int)$disponibilidades1[$i] : 0;
        $disp2_val = !empty($disponibilidades2[$i]) ? (int)$disponibilidades2[$i] : 0;
        $disp3_val = !empty($disponibilidades3[$i]) ? (int)$disponibilidades3[$i] : 0;
        $rec1 = (isset($recomendaciones[$id_actual]) && $recomendaciones[$id_actual] == '1') ? 1 : 0;
        $rec2 = (isset($recomendaciones[$id_actual]) && $recomendaciones[$id_actual] == '2') ? 1 : 0;
        $rec3 = (isset($recomendaciones[$id_actual]) && $recomendaciones[$id_actual] == '3') ? 1 : 0;
        
        $stmt_producto->bind_param("ssdisisdisisdisii",
            $nuevo_estatus_productos, // s
            // --- Proveedor 1 ---
            $proveedores1[$i],        // s
            $costo1_val,              // d
            $disp1_val,               // i
            $tiempos_entrega1[$i],    // s
            $rec1,                    // i
            // --- Proveedor 2 ---
            $proveedores2[$i],        // s
            $costo2_val,              // d
            $disp2_val,               // i
            $tiempos_entrega2[$i],    // s
            $rec2,                    // i
            // --- Proveedor 3 ---
            $proveedores3[$i],        // s
            $costo3_val,              // d
            $disp3_val,               // i
            $tiempos_entrega3[$i],    // s
            $rec3,                    // i
            // --- ID del producto ---
            $id_actual                // i
        );
    
        if (!$stmt_producto->execute()) {
            throw new Exception("Error al actualizar el producto ID " . $id_actual . ": " . $stmt_producto->error);
        }
    }
    $stmt_producto->close();

    // --- PASO B: ACTUALIZAR EL ESTATUS GENERAL ---
    $sql_update_general = "UPDATE datos_generales_co SET estatus = ?, user_cotizador = ?, fecha_cotizacion = NOW() WHERE folio = ?";
    $stmt_general = $conn->prepare($sql_update_general);
    $stmt_general->bind_param("sss", $nuevo_estatus_general, $user_cotizador, $folio);
    
    if (!$stmt_general->execute()) {
        throw new Exception("Error al actualizar el estatus de la cotización: " . $stmt_general->error);
    }
    $stmt_general->close();

    // --- PASO C: GUARDAR LOS CAMBIOS ---
    $conn->commit();
    
    // --- PASO D: GENERAR Y ENVIAR PDF SI ES NECESARIO ---
    if ($accion == 'enviar_cotizacion') {
        $cotizacion_completa = obtenerCotizacionCompleta($conn, $folio);
        $productos_completos = obtenerProductosCompletos($conn, $folio);
        
        $nombre_solicitante = $cotizacion_completa['user_solicitante'];
        
        $sql_email = "SELECT email FROM usuarios WHERE nombre = ?";
        $stmt_email = $conn->prepare($sql_email);
        $stmt_email->bind_param("s", $cotizacion_completa['user_solicitante']);
        $stmt_email->execute();
        $usuario_data = $stmt_email->get_result()->fetch_assoc();
        $stmt_email->close();

        if ($usuario_data && !empty($usuario_data['email'])) {
            $email_destinatario = $usuario_data['email'];
            $folio_formateado = sprintf('%09d', $folio);
            
            $html_para_pdf = generarHtmlParaPdf($cotizacion_completa, $productos_completos);

            $mpdf = new Mpdf();
            $mpdf->WriteHTML($html_para_pdf);
            $pdf_contenido = $mpdf->Output('', 'S');

            $asunto = "Tu Cotización con Folio {$folio_formateado} está Lista";
            $nombre_cliente = htmlspecialchars($cotizacion_completa['cliente']);

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
                        <h1>Tu Cotización está Lista</h1>
                        <p>Hola " . htmlspecialchars($nombre_solicitante) . ", te informamos que la cotización que solicitaste ya ha sido procesada.</p>
                        
                        <div class='info-row'>
                            <span class='label'>Folio:</span>
                            <span>" . htmlspecialchars($folio_formateado) . "</span>
                        </div>
                        
                        <div class='info-row'>
                            <span class='label'>Cliente:</span>
                            <span>" . htmlspecialchars($nombre_cliente) . "</span>
                        </div>
            
                        <div class='info-row'>
                            <span class='label'>Estatus:</span>
                            <strong style='color:green;'>Cotizado</strong>
                        </div>
                        
                        <hr>
                        <p style='text-align:center;'><strong>Por favor, ingresa al portal para ver los detalles y generar el pedido.</strong></p>
            
                    </div>
                </body>
                </html>
            ";
            $nombre_pdf = "Cotizacion-{$folio_formateado}.pdf";

            enviarNotificacion([$email_destinatario], $asunto, $cuerpo_html, $pdf_contenido, $nombre_pdf);
        }
    }

    $response['success'] = true;
    $response['message'] = 'La información se ha guardado correctamente.';

} catch (Exception $e) {
    $conn->rollback();
    $response['message'] = "Hubo un error al guardar: " . $e->getMessage();
}

$conn->close();
echo json_encode($response);
?>