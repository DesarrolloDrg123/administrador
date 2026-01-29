<?php
require("../config/db.php");
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id = (int)$_POST['id'];

    // 1. Obtener el correo del responsable y el token actual
    $query = "SELECT a.token_evidencia, a.folio, u.correo, u.nombre 
              FROM auditorias_vehiculos_aud a
              JOIN vehiculos v ON a.vehiculo_id = v.id
              JOIN usuarios u ON v.responsable_id = u.id 
              WHERE a.id = ?";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $res = $stmt->get_result();
    $data = $res->fetch_assoc();

    if ($data && $data['token_evidencia']) {
        $enlace = "https://administrador2.intranetdrg.com.mx/AUD_subir_evidencia.php?token=" . $data['token_evidencia'];
        
        // 2. Enviar el correo (Ejemplo básico con mail de PHP, lo ideal es PHPMailer)
        $to = $data['correo'];
        $subject = "Acción Requerida: Evidencias Pendientes - Folio " . $data['folio'];
        $message = "Hola " . $data['nombre'] . ",\n\nSe han solicitado evidencias fotográficas adicionales para la auditoría con folio " . $data['folio'] . ".\n\nPor favor, sube las fotos en el siguiente enlace:\n" . $enlace;
        $headers = "From: administrador@intranetdrg.com.mx";

        if (mail($to, $subject, $message, $headers)) {
            echo json_encode(['status' => 'success', 'message' => 'Correo enviado a ' . $to]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'No se pudo enviar el correo electrónico.']);
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => 'No se encontró un token activo o correo para esta auditoría.']);
    }
}
?>