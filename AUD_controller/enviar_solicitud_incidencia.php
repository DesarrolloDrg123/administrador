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

// CONSULTA CORREGIDA: Eliminamos 'pregunta' y usamos 'descripcion'
$query = "SELECT i.id, i.descripcion, v.placas, u.email, u.nombre as responsable 
          FROM auditorias_detalle_aud i
          JOIN vehiculos_aud v ON i.vehiculo_id = v.id
          JOIN usuarios u ON v.responsable_id = u.id
          WHERE i.id = ?";

$stmt = $conn->prepare($query);
if (!$stmt) {
    echo json_encode(['status' => 'error', 'message' => $conn->error]);
    exit;
}

$stmt->bind_param("i", $id_incidencia);
$stmt->execute();
$res = $stmt->get_result()->fetch_assoc();

if ($res) {
    $link = "https://administrador2.intranetdrg.com.mx/AUD_subir_evidencia_incidencia.php?id=" . $id_incidencia;
    
    $asunto = "⚠️ Acción Requerida: Evidencia de Incidencia - " . $res['placas'];
    $html = "
        <p>Estimado <strong>{$res['responsable']}</strong>,</p>
        <p>Se requiere su apoyo para subir la evidencia de corrección de la siguiente incidencia detectada en la unidad con placas <strong>{$res['placas']}</strong>:</p>
        <div style='background: #f2f9e9; border-left: 4px solid #80bf1f; padding: 15px; margin: 20px 0;'>
            <strong>Detalle de la Incidencia:</strong> {$res['descripcion']}<br>
            <strong>Estatus:</strong> Pendiente de Evidencia
        </div>
        <div style='text-align: center; margin: 30px 0;'>
            <a href='$link' style='background-color: #80bf1f; color: white; padding: 12px 25px; text-decoration: none; border-radius: 5px; font-weight: bold; display: inline-block;'>
                SUBIR FOTO DE EVIDENCIA
            </a>
        </div>
        <p style='font-size: 11px;'>Si el botón no funciona, copie este link: $link</p>";

    // Llamada a la función de correo
    $envio = enviarCorreoDRG($res['email'], $asunto, $html);

    echo json_encode(['status' => ($envio ? 'success' : 'error')]);
} else {
    echo json_encode(['status' => 'error', 'message' => 'No se encontró la incidencia']);
}