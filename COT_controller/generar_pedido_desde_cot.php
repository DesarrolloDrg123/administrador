<?php
session_start();

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require '../vendor/autoload.php';
require("../config/db.php"); // Asegúrate que la ruta a la BD es correcta (../)

// =================================================================
// === ESTE SCRIPT ES UNA COPIA ADAPTADA DE TU CÓDIGO ORIGINAL ===
// =================================================================

// --- 1. VALIDACIONES Y CAPTURA DE DATOS DEL FORMULARIO ---

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método de solicitud no válido.']);
    exit();
}
if (!isset($_SESSION['loggedin']) || !$_SESSION['loggedin']) {
    echo json_encode(['success' => false, 'message' => 'Sesión no válida.']);
    exit();
}

// Datos generales que vienen del formulario
$solicitante = $_SESSION['nombre'];
$no_cliente = $_POST['no_cliente'];
$nombre_cliente = $_POST['nombre_cliente'];
$uso = $_POST['uso'];
$sucursal = $_POST['sucursal'];
$observaciones = $_POST['observaciones'];
$folio_cotizacion_origen = $_POST['folio_cotizacion_origen'];

// --- 2. "TRADUCCIÓN" DE DATOS: CONVERTIR LA SELECCIÓN AL FORMATO ANTIGUO ---

// Obtenemos la selección de proveedores [id_producto => proveedor_num]
$proveedores_seleccionados = $_POST['proveedor_seleccionado'] ?? [];
if (empty($proveedores_seleccionados)) {
    echo json_encode(['success' => false, 'message' => 'No se seleccionaron productos para el pedido.']);
    exit();
}

$ids_productos = array_keys($proveedores_seleccionados);

// Buscamos los detalles completos de los productos seleccionados en la BD de cotizaciones
$in_clause = implode(',', array_fill(0, count($ids_productos), '?'));
$sql_productos = "SELECT * FROM productos_co WHERE id IN ($in_clause)";
$stmt_productos = $conn->prepare($sql_productos);
$stmt_productos->bind_param(str_repeat('i', count($ids_productos)), ...$ids_productos);
$stmt_productos->execute();
$result_productos = $stmt_productos->get_result();
$productos_data = [];
while ($row = $result_productos->fetch_assoc()) {
    $productos_data[$row['id']] = $row;
}
$stmt_productos->close();

// Construimos las listas de productos en el formato que espera la función GuardarPedido
$sku_list = [];
$descripcion_list = [];
$cantidad_list = [];
$nota_list = [];

foreach ($proveedores_seleccionados as $producto_id => $proveedor_num) {
    $producto = $productos_data[$producto_id];
    $proveedor_elegido = $producto["proveedor{$proveedor_num}"];
    $costo_elegido = $producto["costo{$proveedor_num}"];
    
    $tiempo_entrega_elegido = $producto["tiempo_entrega{$proveedor_num}"];
    
    $nota_final = "Proveedor: {$proveedor_elegido}, Costo: $" . number_format($costo_elegido, 2) . ", T. Entrega: {$tiempo_entrega_elegido}";
    if (!empty($producto['notas'])) {
        $nota_final .= ". Nota Original: " . $producto['notas'];
    }

    $sku_list[] = $producto['sku'];
    $descripcion_list[] = $producto['descripcion'];
    $cantidad_list[] = $producto['cantidad'];
    $nota_list[] = $nota_final;
}
// Convertimos las listas a los strings con punto y coma (;)
$sku_str = implode(';', $sku_list);
$descripcion_str = implode(';', $descripcion_list);
$cantidad_str = implode(';', $cantidad_list);
$nota_str = implode(';', $nota_list);

// --- 3. EJECUCIÓN DEL PROCESO DE GUARDADO ORIGINAL ---

// Ahora llamamos a tu función `GuardarPedido` con los datos ya "traducidos"
$result = GuardarPedido($conn, $solicitante, $no_cliente, $nombre_cliente, $uso, $sucursal, $observaciones, $sku_str, $descripcion_str, $cantidad_str, $nota_str);

// Después de guardar el pedido, actualizamos el estatus de la cotización original
if ($result['success']) {
    $estatus_final_co = 'Pedido Generado';
    $sql_update_co = "UPDATE datos_generales_co SET estatus = ? WHERE folio = ?";
    $stmt_update_co = $conn->prepare($sql_update_co);
    $stmt_update_co->bind_param("ss", $estatus_final_co, $folio_cotizacion_origen);
    $stmt_update_co->execute();
    $stmt_update_co->close();

    // Enviar correos solo si todo fue exitoso
    enviarCorreos($conn, $result['folio'], date('Y-m-d'), $solicitante, $no_cliente, $nombre_cliente, $uso, $sucursal);
}

// Devolvemos la respuesta JSON
header('Content-Type: application/json');
echo json_encode($result);


// =================================================================
// === TUS FUNCIONES ORIGINALES (SE REUTILIZAN CASI SIN CAMBIOS) ===
// =================================================================

// CÓDIGO NUEVO Y CORREGIDO
function GuardarPedido($conn, $solicitante, $no_cliente, $nombre_cliente, $uso, $sucursal, $observaciones, $sku, $descripcion, $cantidad, $nota) {
    $conn->begin_transaction();
    try {
        // Traer y actualizar el folio
        $sql_folio = "SELECT folio FROM control_folios_pe WHERE id = 1 FOR UPDATE";
        $resultado_folio = $conn->query($sql_folio);
        $fila_folio = $resultado_folio->fetch_assoc();
        $nuevoFolio = ($fila_folio['folio'] ?? 0) + 1;

        // CAMBIO 1: Formateamos el folio a 9 dígitos con ceros a la izquierda
        $folio_formateado = sprintf('%09d', $nuevoFolio);

        $stmt_update_folio = $conn->prepare("UPDATE control_folios_pe SET folio = ? WHERE id = 1");
        $stmt_update_folio->bind_param("i", $nuevoFolio); // Aquí se sigue guardando el número entero
        $stmt_update_folio->execute();
        $stmt_update_folio->close();

        // Inserción del pedido
        $sql_insert = "INSERT INTO pedidos_especiales
                         (folio, fecha, solicitante, numero_cliente, nombre_cliente, uso, sucursal, observaciones, estatus, sku, descripcion, cantidad, nota) 
                       VALUES (?, NOW(), ?, ?, ?, ?, ?, ?, 'Nuevo', ?, ?, ?, ?)";
        
        $stmt_insert = $conn->prepare($sql_insert);
        
        // CAMBIO 2: Usamos el folio formateado para guardarlo en la tabla de pedidos
        $stmt_insert->bind_param('sssssssssss',
            $folio_formateado, $solicitante, $no_cliente, $nombre_cliente, $uso, $sucursal, $observaciones,
            $sku, $descripcion, $cantidad, $nota
        );

        if (!$stmt_insert->execute()) {
            throw new Exception("Error al guardar el Pedido: " . $stmt_insert->error);
        }
        $stmt_insert->close();
        
        $conn->commit();
        
        // CAMBIO 3: Devolvemos el folio ya formateado para que se use en el correo y el mensaje de éxito
        return ['success' => true, 'message' => 'Pedido guardado exitosamente.', 'folio' => $folio_formateado];

    } catch (Exception $e) {
        $conn->rollback();
        return ['success' => false, 'message' => 'Error en la transacción: ' . $e->getMessage()];
    }
}

//Funcion para traer el Id de las Sucursales
function SucursalID($conn, $sucursal) {
    $sql = "SELECT sucursal FROM sucursales WHERE id = '$sucursal'";
    $result = $conn->query($sql);
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        return $row['sucursal']; // Retorna solo el valor del campo 'sucursal'
    } else {
        return null; // Retorna null si no se encontró ningún registro
    }
}

//Funcion para traer el Id del Uso
function UsoID($conn, $uso) {
    $sql = "SELECT nombre FROM uso WHERE id = '$uso'";
    $result = $conn->query($sql);
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        return $row['nombre']; // Retorna solo el valor del campo 'nombre'
    } else {
        return null; // Retorna null si no se encontró ningún registro
    }
}
//Funcion para traer los Autorizadores de Pedidos
function UsuariosAutorizadores($conn) {
    $id_programa = 14; //Id del programa para procesar pedidos
    $sql = "SELECT u.email FROM usuarios u JOIN permisos p ON u.id = p.id_usuario WHERE id_programa = $id_programa AND acceso = 1";
    $result = $conn->query($sql);

    $emails = [];
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $emails[] = $row['email']; // Agrega cada correo al array
        }
    }
    return $emails; // Retorna un array (puede estar vacío si no hay resultados)
}

//Funcion para mandar correo de Nuevo Pedido
function enviarCorreos($conn,$folio,$fecha,$solicitante,$no_cliente,$nombre_cliente,$uso,$sucursal) {
    $autorizadores = UsuariosAutorizadores($conn);
    
    $sucursalF = SucursalID($conn,$sucursal);
    
    $usoF = UsoID($conn,$uso);
    
    $fechaSolicitud = new DateTime($fecha);
    $fmt = new IntlDateFormatter('es_ES', IntlDateFormatter::LONG, IntlDateFormatter::NONE);
    $fmt->setPattern('d MMMM yyyy');
    $fechaSolicitudFormateada = $fmt->format($fechaSolicitud);

    if (empty($autorizadores)) {
        echo "No se encontraron usuarios con rol 'Autorizador'.";
        return;
    }
    foreach ($autorizadores as $email) {
        $mail = new PHPMailer(true);
        try {
            //Configuracion del Servidor
            $mail->isSMTP();
            $mail->Host = 'mail.intranetdrg.com.mx';
            $mail->SMTPAuth = true;
            $mail->Username = 'notification@intranetdrg.com.mx'; // Cambia esto por tu dirección de correo electrónico que se encargará de enviar los correos
            $mail->Password = 'r-eHQi64a7!3QT9'; // Cambia esto por la contraseña del correo electrónico
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            $mail->IsHTML(true);  
            $mail->CharSet = 'UTF-8';
            $mail->Port = 465; // Cambia esto al puerto SMTP correspondiente

            // Configuración del remitente y destinatario
            $mail->setFrom('notification@intranetdrg.com.mx', 'DRG Notification');//Quien envia el Correo
            $mail->addAddress($email); // Correo del destinatario

            // Contenido del correo
            $mail->isHTML(true);
            $mail->Subject = 'Nuevo Pedido Especial' ;
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
                    <h1>Nuevo Pedido Especial</h1>
                    <h2>Solicitante: <strong>$solicitante</strong></h2>
                    
                    <div class='info-row'>
                        <span class='label'>Fecha de Solicitud:</span>
                        <span class='value'>$fechaSolicitudFormateada</span>
                    </div>
                    
                    <div class='info-row'>
                        <span class='label'>Numero de Cliente:</span>
                        <span class='value'>$no_cliente</span>
                    </div>
                    
                    <div class='info-row'>
                        <span class='label'>Nombre del Cliente:</span>
                        <span class='value'>$nombre_cliente</span>
                    </div>
                    
                    
                    <div class='info-row'>
                        <span class='label'>Uso:</span>
                        <span class='value'>$usoF</span>
                    </div>
                    
                    <div class='info-row'>
                        <span class='label'>Sucursal:</span>
                        <span class='value'>$sucursalF</span>
                    </div>
                    
                    <h1>Verificar Pedido en el Portal</h1>

                </div>
            </body>
            </html>
            ";

            // Enviar el correo
            $mail->send();
        } catch (Exception $e) {
            echo "Error al enviar correo a $email: {$mail->ErrorInfo}<br>";
        }
    }
}
?>