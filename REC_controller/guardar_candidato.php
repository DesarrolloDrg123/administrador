<?php
header('Content-Type: application/json');
require("../config/db.php");

// Importar PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require '../vendor/autoload.php'; // Ajusta la ruta según donde tengas PHPMailer

$response = ['success' => false, 'message' => ''];

// Iniciar una transacción para asegurar que todo se guarde correctamente
$conn->begin_transaction();

try {
    // --- 1. VALIDACIÓN DE DATOS REQUERIDOS ---
    $required_fields = [
        'solicitud_id', 'nombre_completo', 'edad', 'correo_electronico', 'telefono', 
        'ciudad_residencia', 'vehiculo_propio', 'nivel_estudios', 'disponibilidad_viajar', 
        'disponibilidad_trabajar', 'idiomas'
    ];
    
    foreach ($required_fields as $field) {
        if (!isset($_POST[$field]) || $_POST[$field] === '') {
            throw new Exception("El campo '$field' es obligatorio.");
        }
    }
    
    if (!isset($_FILES['cv_adjunto']) || $_FILES['cv_adjunto']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('Es obligatorio adjuntar un CV.');
    }
    
    $nombre_completo = $_POST['nombre_completo'];
    
    // Función para limpiar el nombre: quita acentos, convierte a minúsculas y reemplaza espacios
    function sanitizarNombreCarpeta($nombre) {
        $nombre = strtolower($nombre);
        $nombre = iconv('UTF-8', 'ASCII//TRANSLIT', $nombre); // Quita acentos
        $nombre = preg_replace('/[^a-z0-9\s-]/', '', $nombre); // Quita caracteres especiales
        $nombre = preg_replace('/[\s-]+/', '_', $nombre); // Reemplaza espacios y guiones por guion bajo
        return trim($nombre, '_');
    }

    // --- 2. MANEJO DEL ARCHIVO ADJUNTO (CV) ---
    $cv_file = $_FILES['cv_adjunto'];

    // Sanitizar nombre para la carpeta del candidato
    $nombre_carpeta_limpio = sanitizarNombreCarpeta($nombre_completo);

    // Usar el ID de solicitud para diferenciar nombres iguales
    $solicitud_id = $_POST['solicitud_id'];
    $nombre_carpeta_unica = $solicitud_id . '_' . $nombre_carpeta_limpio;

    // Carpeta base del expediente
    $upload_dir_candidato = 'Expediente/' . $nombre_carpeta_unica . '/';

    // Crear carpeta si no existe
    if (!is_dir($upload_dir_candidato)) {
        if (!mkdir($upload_dir_candidato, 0775, true)) {
            throw new Exception('No se pudo crear la carpeta para el candidato.');
        }
    }

    // Validar tipo de archivo
    $allowed_types = [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
    ];
    if (!in_array($cv_file['type'], $allowed_types)) {
        throw new Exception('Formato de archivo no válido. Solo se aceptan PDF, DOC y DOCX.');
    }

    // Crear nombre limpio para el archivo CV
    $file_extension = pathinfo($cv_file['name'], PATHINFO_EXTENSION);
    $nombre_archivo = 'cv_' . $nombre_carpeta_limpio . '.' . $file_extension;
    $ruta_archivo = $upload_dir_candidato . $nombre_archivo; // Ruta completa del archivo PDF
    
    // Si ya existe un archivo anterior, se reemplaza
    if (file_exists($ruta_archivo)) {
        unlink($ruta_archivo);
    }
    
    // Mover el nuevo archivo
    if (!move_uploaded_file($cv_file['tmp_name'], $ruta_archivo)) {
        throw new Exception('Error al guardar el archivo CV.');
    }
    
    // Guardar solo la carpeta en la base de datos
    $upload_path = $upload_dir_candidato;
    
    // --- 3. OBTENER EL NOMBRE DEL PUESTO AL QUE SE APLICA ---
    $stmt_puesto = $conn->prepare("SELECT puesto_solicitado FROM solicitudes_vacantes WHERE solicitud_id = ?");
    $stmt_puesto->bind_param("i", $_POST['solicitud_id']);
    $stmt_puesto->execute();
    $result_puesto = $stmt_puesto->get_result()->fetch_assoc();
    $area_interes = $result_puesto ? $result_puesto['puesto_solicitado'] : 'No especificado';


    // --- 4. PREPARAR Y EJECUTAR LA INSERCIÓN DEL CANDIDATO ---
    $sql = "INSERT INTO solicitudes_vacantes_candidatos (
                solicitud_id,nombre_completo, edad, correo_electronico, telefono, ciudad_residencia, 
                vehiculo_propio, nivel_estudios, carrera, idiomas, disponibilidad_viajar, 
                disponibilidad_trabajar, area_interes, experiencia_laboral_anios, 
                habilidades_tecnicas, marcas_multifuncionales, conocimiento_ventas_tecnicas, 
                cv_adjunto_path, rango_salarial_deseado, fuente_vacante,
                ref1_nombre, ref1_contacto, ref1_empresa, ref1_puesto,
                ref2_nombre, ref2_contacto, ref2_empresa, ref2_puesto,
                ref3_nombre, ref3_contacto, ref3_empresa, ref3_puesto
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

    $ref1_nombre = $_POST['ref1_nombre'] ?? null;
    $ref1_contacto = $_POST['ref1_contacto'] ?? null;
    $ref1_empresa = $_POST['ref1_empresa'] ?? null;
    $ref1_puesto = $_POST['ref1_puesto'] ?? null;
    $ref2_nombre = $_POST['ref2_nombre'] ?? null;
    $ref2_contacto = $_POST['ref2_contacto'] ?? null;
    $ref2_empresa = $_POST['ref2_empresa'] ?? null;
    $ref2_puesto = $_POST['ref2_puesto'] ?? null;
    $ref3_nombre = $_POST['ref3_nombre'] ?? null;
    $ref3_contacto = $_POST['ref3_contacto'] ?? null;
    $ref3_empresa = $_POST['ref3_empresa'] ?? null;
    $ref3_puesto = $_POST['ref3_puesto'] ?? null;

    $stmt = $conn->prepare($sql);

    $rango_salarial = !empty($_POST['rango_salarial_deseado']) ? $_POST['rango_salarial_deseado'] : null;

    $stmt->bind_param("isisssisssisssiissssssssssssssss", 
        $_POST['solicitud_id'],$_POST['nombre_completo'], $_POST['edad'], $_POST['correo_electronico'], $_POST['telefono'], $_POST['ciudad_residencia'], 
        $_POST['vehiculo_propio'], $_POST['nivel_estudios'], $_POST['carrera'], $_POST['idiomas'], $_POST['disponibilidad_viajar'], 
        $_POST['disponibilidad_trabajar'], $area_interes, $_POST['experiencia_laboral_anios'], 
        $_POST['habilidades_tecnicas'], $_POST['marcas_multifuncionales'], $_POST['conocimiento_ventas_tecnicas'], 
        $upload_path, $rango_salarial, $_POST['fuente_vacante'],
        $ref1_nombre, $ref1_contacto, $ref1_empresa, $ref1_puesto,
        $ref2_nombre, $ref2_contacto, $ref2_empresa, $ref2_puesto,
        $ref3_nombre, $ref3_contacto, $ref3_empresa, $ref3_puesto
    );

    if (!$stmt->execute()) {
        throw new Exception('Error al registrar al candidato en la base de datos.');
    }

    // === INICIO: LÓGICA PARA GUARDAR RESPUESTAS ===
    
    $candidato_id = $conn->insert_id;
    $respuestas = $_POST['respuestas'] ?? [];

    if (!empty($respuestas) && is_array($respuestas)) {
        $sql_respuestas = "INSERT INTO solicitudes_vacantes_candidatos_respuestas (candidato_id, pregunta_id, respuesta_texto) VALUES (?, ?, ?)";
        $stmt_respuestas = $conn->prepare($sql_respuestas);
        
        foreach ($respuestas as $pregunta_id => $respuesta_texto) {
            if (!empty(trim($respuesta_texto))) {
                $stmt_respuestas->bind_param("iis", $candidato_id, $pregunta_id, $respuesta_texto);
                $stmt_respuestas->execute();
            }
        }
        $stmt_respuestas->close();
    }
    
    if (!empty($ruta_archivo)) {
        // $nombre_archivo contiene solo el nombre del archivo, ej: 'cv_claudia_rubio.pdf'
        $stmt_cv_doc = $conn->prepare("INSERT INTO solicitudes_vacantes_candidatos_documentos (candidato_id, nombre_documento, ruta_documento) VALUES (?, ?, ?)");
        $stmt_cv_doc->bind_param("iss", $candidato_id, $nombre_archivo, $ruta_archivo);
        $stmt_cv_doc->execute();
        $stmt_cv_doc->close();
    }

    $conn->commit();
    
    function obtenerCorreosEncargadosSistema($conn) {
        // ID del programa de reclutamiento (ajústalo si es distinto)
        $id_programa_reclutamiento = 40;

        $sql = "SELECT u.email 
                FROM usuarios u
                JOIN permisos p ON u.id = p.id_usuario
                WHERE p.id_programa = ? AND p.acceso = 1 ";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $id_programa_reclutamiento);
        $stmt->execute();
        $result = $stmt->get_result();

        $emails = [];
        while ($row = $result->fetch_assoc()) {
            $emails[] = $row['email'];
        }

        $stmt->close();
        return $emails;
    }
    
    function enviarNotificacionNuevoCandidato($destinatarios, $nombre_candidato, $puesto, $correo, $telefono) {
        if (empty($destinatarios)) {
            error_log("⚠️ No se encontraron destinatarios para la notificación de nuevo candidato.");
            return;
        }

        $mail = new PHPMailer(true);
        try {
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
            $mail->Subject = "Nuevo candidato registrado: $nombre_candidato";

            $mail->Body = "
            <html>
            <head>
                <style>
                    body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; background-color: #f9f9fb; }
                    .container { max-width: 600px; margin: 40px auto; background-color: #fff; border-radius: 10px; overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
                    .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; text-align: center; padding: 35px 25px; }
                    .header h1 { margin: 0; font-size: 26px; }
                    .content { padding: 30px; }
                    .info-table { width: 100%; border-collapse: collapse; margin-top: 10px; }
                    .info-table td { padding: 8px 5px; border-bottom: 1px solid #eee; }
                    .info-table td:first-child { font-weight: bold; width: 35%; color: #555; }
                    .button { display: inline-block; padding: 14px 30px; margin: 25px 0; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white !important; text-decoration: none; border-radius: 50px; font-weight: bold; box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4); }
                    .button:hover { box-shadow: 0 6px 20px rgba(102, 126, 234, 0.6); }
                    .footer { background-color: #f5f5f5; text-align: center; padding: 20px; font-size: 12px; color: #888; border-top: 1px solid #eee; }
                    .highlight { background-color: #f0f4ff; padding: 15px; border-radius: 5px; margin-top: 20px; border-left: 5px solid #667eea; }
                </style>
            </head>
            <body>
                <div class='container'>
                    <div class='header'>
                        <h1>📢 Nuevo Candidato Registrado</h1>
                    </div>
                    <div class='content'>
                        <p>Se ha registrado un nuevo candidato en el sistema de <strong>Reclutamiento DRG</strong>. A continuación los detalles del registro:</p>

                        <table class='info-table'>
                            <tr><td>👤 Nombre:</td><td>".htmlspecialchars($nombre_candidato)."</td></tr>
                            <tr><td>💼 Puesto Solicitado:</td><td>".htmlspecialchars($puesto)."</td></tr>
                            <tr><td>📧 Correo:</td><td>".htmlspecialchars($correo)."</td></tr>
                            <tr><td>📱 Teléfono:</td><td>".htmlspecialchars($telefono)."</td></tr>
                        </table>

                        <div class='highlight'>
                            <strong>🔔 Acción sugerida:</strong> Ingresa al panel de Reclutamiento para revisar el expediente del candidato y continuar con el proceso de evaluación.
                        </div>

                        <p style='text-align:center;'>
                            <a href='https://administrador.intranetdrg.com.mx/REC_gestion_vacantes.php' class='button'>Ver Candidatos</a>
                        </p>

                        <p style='font-size:13px; color:#666; text-align:center;'>
                            Este correo fue generado automáticamente por el sistema de Talento Humano DRG.<br>
                            No es necesario responder a este mensaje.
                        </p>
                    </div>
                    <div class='footer'>
                        <p>© ".date('Y')." DRG Talento Humano | Sistema Interno de Reclutamiento</p>
                    </div>
                </div>
            </body>
            </html>
            ";


            $mail->send();
        } catch (Exception $e) {
            error_log("❌ Error al enviar correo de nuevo candidato: " . $mail->ErrorInfo);
        }
    }

    // Ejecutar envío de notificación
    $destinatarios = obtenerCorreosEncargadosSistema($conn);
    enviarNotificacionNuevoCandidato($destinatarios, $nombre_completo, $area_interes, $_POST['correo_electronico'], $_POST['telefono']);
    
    $response['success'] = true;
    $response['message'] = 'Candidato registrado con éxito.';

} catch (Exception $e) {
    $conn->rollback();
    $response['message'] = $e->getMessage();
}

$conn->close();
echo json_encode($response);
?>
