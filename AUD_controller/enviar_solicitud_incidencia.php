<?php
// AUD_controller/enviar_solicitud_incidencia.php
require("../config/db.php");
// Asegúrate de que esta ruta sea la correcta para llegar al archivo de funciones
require_once "enviar_correos_auditoria.php"; 

header('Content-Type: application/json');
$data = json_decode(file_get_contents("php://input"), true);

if (!isset($data['id'])) {
    echo json_encode(['status' => 'error', 'message' => 'ID no recibido']);
    exit;
}

$id_incidencia = $data['id'];

// Consulta para obtener datos del responsable y la unidad
$query = "SELECT 
            i.id, 
            i.descripcion, 
            v.placas, 
            u.email, 
            u.nombre as responsable 
        FROM auditorias_incidencias_aud i
        JOIN vehiculos_aud v ON i.vehiculo_id = v.id
        LEFT JOIN usuarios u ON v.responsable_id = u.id
        WHERE i.id = ?";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $id_incidencia);
$stmt->execute();
$res = $stmt->get_result()->fetch_assoc();

if ($res) {
    if (empty($res['email'])) {
        echo json_encode(['status' => 'error', 'message' => 'El responsable no tiene correo configurado']);
        exit;
    }

    $link = "https://administrador2.intranetdrg.com.mx/AUD_subir_evidencia_incidencia.php?id=" . $id_incidencia;
    $asunto = "⚠️ Acción Requerida: Evidencia de Incidencia - " . $res['placas'];
    
    // Cuerpo del correo (diseño limpio)
    $html = "
        <p>Estimado <strong>{$res['responsable']}</strong>,</p>
        <p>Se solicita cargar la evidencia de seguimiento para la incidencia en la unidad: <strong>{$res['placas']}</strong>.</p>
        <div style='background: #f2f9e9; border-left: 4px solid #80bf1f; padding: 15px; margin: 20px 0;'>
            <strong>Incidencia:</strong><br>
            {$res['descripcion']}
        </div>
        <p>Haga clic en el botón para subir la foto:</p>
        <div style='text-align: center; margin: 20px;'>
            <a href='$link' style='background-color: #80bf1f; color: white; padding: 12px 20px; text-decoration: none; border-radius: 5px; font-weight: bold; display: inline-block;'>
                SUBIR EVIDENCIA AQUÍ
            </a>
        </div>";

    // Llamamos a la función. No pasamos el 4to parámetro porque no hay adjuntos en esta solicitud
    $envio = enviarCorreoDRG($res['email'], $asunto, $html);

    if ($envio) {
        // Aquí devolvemos el correo en la respuesta exitosa
        echo json_encode([
            'status' => 'success', 
            'message' => 'Correo enviado correctamente',
            'email' => $email_destino
        ]);
    } else {
        echo json_encode([
            'status' => 'error', 
            'message' => 'Error al procesar el envío de correo a: ' . $email_destino
        ]);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Incidencia no encontrada']);
}