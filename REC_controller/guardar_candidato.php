<?php
header('Content-Type: application/json');
require("../config/db.php");

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
    
    $response['success'] = true;
    $response['message'] = 'Candidato registrado con éxito.';

} catch (Exception $e) {
    $conn->rollback();
    $response['message'] = $e->getMessage();
}

$conn->close();
echo json_encode($response);
?>
