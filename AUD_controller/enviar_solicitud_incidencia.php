<?php
// AUD_controller/enviar_solicitud_incidencia.php
require("../config/db.php");
require_once "enviar_correos_auditoria.php"; 

header('Content-Type: application/json');
$data = json_decode(file_get_contents("php://input"), true);

if (!isset($data['id'])) {
    echo json_encode(['status' => 'error', 'message' => 'ID no recibido']);
    exit;
}

$id_incidencia = $data['id'];

// Consulta limpia sin folio
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

    // DEFINIMOS LA VARIABLE QUE USAREMOS EN EL JSON
    $email_destino = $res['email']; 
    $link = "https://administrador2.intranetdrg.com.mx/AUD_subir_evidencia_incidencia.php?id=" . $id_incidencia;
    $asunto = "Acción Requerida: Evidencia de Incidencia - " . $res['placas'];
    
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

    $envio = enviarCorreoDRG($email_destino, $asunto, $html);

    if ($envio) {
        echo json_encode([
            'status' => 'success', 
            'email' => $email_destino // Ahora sí tiene valor
        ]);
    } else {
        echo json_encode([
            'status' => 'error', 
            'message' => 'PHPMailer no pudo enviar el correo',
            'email' => $email_destino
        ]);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Incidencia no encontrada en la base de datos']);
}
?>