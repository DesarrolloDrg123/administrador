<?php
// AUD_controller/enviar_solicitud_incidencia.php
require("../config/db.php");
require_once __DIR__ . "/enviar_correos_auditoria.php"; 

header('Content-Type: application/json');
$data = json_decode(file_get_contents("php://input"), true);

if (!isset($data['id'])) {
    echo json_encode(['status' => 'error', 'message' => 'ID no recibido']);
    exit;
}

$id_incidencia = $data['id'];

/**
 * CONSULTA AJUSTADA
 * i.descripcion -> Es la columna donde guardas la incidencia
 * v.placas      -> Viene de vehiculos_aud
 * u.email       -> Viene de usuarios (responsable)
 */
$query = "SELECT i.id, i.descripcion, v.placas, u.email, u.nombre as responsable 
          FROM auditorias_incidencias_aud i
          JOIN vehiculos_aud v ON i.vehiculo_id = v.id
          JOIN usuarios u ON v.responsable_id = u.id
          WHERE i.id = ?";

$stmt = $conn->prepare($query);
if (!$stmt) {
    echo json_encode(['status' => 'error', 'message' => 'Error en SQL: ' . $conn->error]);
    exit;
}

$stmt->bind_param("i", $id_incidencia);
$stmt->execute();
$res = $stmt->get_result()->fetch_assoc();

if ($res) {
    // URL para que el responsable suba la foto
    $link = "https://administrador2.intranetdrg.com.mx/AUD_subir_evidencia_incidencia.php?id=" . $id_incidencia;
    
    $asunto = "⚠️ Acción Requerida: Evidencia de Incidencia - " . $res['placas'];
    
    $html = "
        <div style='font-family: Arial, sans-serif; line-height: 1.6;'>
            <p>Estimado <strong>{$res['responsable']}</strong>,</p>
            <p>Se ha solicitado cargar la evidencia de seguimiento para la incidencia detectada en la unidad con placas <strong>{$res['placas']}</strong>.</p>
            
            <div style='background: #f2f9e9; border-left: 4px solid #80bf1f; padding: 15px; margin: 20px 0;'>
                <strong style='color: #333;'>Incidencia reportada:</strong><br>
                <span style='color: #555;'>{$res['descripcion']}</span>
            </div>
            
            <p>Por favor, capture o suba la fotografía que demuestre la atención a este reporte haciendo clic en el siguiente botón:</p>
            
            <div style='text-align: center; margin: 30px 0;'>
                <a href='$link' style='background-color: #80bf1f; color: white; padding: 12px 25px; text-decoration: none; border-radius: 5px; font-weight: bold; display: inline-block;'>
                    SUBIR EVIDENCIA AQUÍ
                </a>
            </div>
            
            <p style='font-size: 11px; color: #888;'>Si el botón no funciona, copie y pegue el siguiente enlace en su navegador: $link</p>
        </div>";

    // Llamada a la función genérica definida en enviar_correos_auditoria.php
    $envio = enviarCorreoDRG($res['email'], $asunto, $html);

    if ($envio) {
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'La función de correo falló']);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'No se encontró la incidencia en la base de datos']);
}