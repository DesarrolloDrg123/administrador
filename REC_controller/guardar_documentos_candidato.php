<?php
header('Content-Type: application/json');
require("../config/db.php");

$response = ['success' => false, 'message' => ''];

// Verificar que se recibió el token y candidato_id
$token = $_POST['token'] ?? null;
$candidato_id = $_POST['candidato_id'] ?? null;

if (!$token || !$candidato_id) {
    $response['message'] = 'Acceso denegado. Token o ID de candidato no válidos.';
    echo json_encode($response);
    exit();
}

// Verificar que el token es válido
$stmt_verify = $conn->prepare("
    SELECT candidato_id 
    FROM solicitudes_vacantes_candidatos 
    WHERE candidato_id = ? 
    AND token_documentos = ?
    AND fecha_token_documentos >= DATE_SUB(NOW(), INTERVAL 30 DAY)
");
$stmt_verify->bind_param("is", $candidato_id, $token);
$stmt_verify->execute();
$result_verify = $stmt_verify->get_result();

if ($result_verify->num_rows === 0) {
    $response['message'] = 'Token inválido o expirado.';
    echo json_encode($response);
    exit();
}

// Configuración de carpeta de uploads
$upload_dir = "../uploads/documentos_candidatos/" . $candidato_id . "/";

// Crear el directorio si no existe
if (!file_exists($upload_dir)) {
    if (!mkdir($upload_dir, 0755, true)) {
        $response['message'] = 'Error al crear el directorio de documentos.';
        echo json_encode($response);
        exit();
    }
}

// Array con los nombres de los campos de archivos y sus nombres descriptivos
$archivos_campos = [
    'ine_frente' => 'Identificación Oficial (Frente)',
    'ine_reverso' => 'Identificación Oficial (Reverso)',
    'curp_documento' => 'CURP',
    'rfc_documento' => 'RFC',
    'comprobante_domicilio' => 'Comprobante de Domicilio',
    'nss_documento' => 'Número de Seguridad Social',
    'comprobante_estudios' => 'Comprobante de Estudios',
    'examenes_medicos' => 'Exámenes Médicos'
];

// Extensiones permitidas
$extensiones_permitidas = ['pdf', 'jpg', 'jpeg', 'png'];
$tamano_maximo = 5 * 1024 * 1024; // 5MB

// Validación y procesamiento de archivos
$archivos_guardados = [];
$errores = [];

$conn->begin_transaction();

try {
    // Primero, eliminar documentos anteriores si existen (para permitir reenvío)
    $stmt_delete = $conn->prepare("DELETE FROM solicitudes_vacantes_candidatos_documentos WHERE candidato_id = ?");
    $stmt_delete->bind_param("i", $candidato_id);
    $stmt_delete->execute();

    foreach ($archivos_campos as $campo => $nombre_descriptivo) {
        if (isset($_FILES[$campo]) && $_FILES[$campo]['error'] === UPLOAD_ERR_OK) {
            $archivo = $_FILES[$campo];
            
            // Validar tamaño
            if ($archivo['size'] > $tamano_maximo) {
                $errores[] = "$nombre_descriptivo excede el tamaño máximo permitido (5MB)";
                continue;
            }
            
            // Obtener extensión
            $extension = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));
            
            // Validar extensión
            if (!in_array($extension, $extensiones_permitidas)) {
                $errores[] = "$nombre_descriptivo tiene un formato no permitido";
                continue;
            }
            
            // Generar nombre único para el archivo
            $nombre_archivo = $campo . "_" . $candidato_id . "_" . time() . "." . $extension;
            $ruta_completa = $upload_dir . $nombre_archivo;
            
            // Mover el archivo
            if (move_uploaded_file($archivo['tmp_name'], $ruta_completa)) {
                // Guardar en la base de datos
                $stmt_insert = $conn->prepare("
                    INSERT INTO solicitudes_vacantes_candidatos_documentos 
                    (candidato_id, nombre_documento, ruta_documento) 
                    VALUES (?, ?, ?)
                ");
                
                $ruta_relativa = "uploads/documentos_candidatos/" . $candidato_id . "/" . $nombre_archivo;
                $stmt_insert->bind_param("iss", $candidato_id, $nombre_descriptivo, $ruta_relativa);
                
                if ($stmt_insert->execute()) {
                    $archivos_guardados[] = $nombre_descriptivo;
                } else {
                    $errores[] = "Error al registrar $nombre_descriptivo en la base de datos";
                    // Eliminar el archivo físico si falló la inserción
                    unlink($ruta_completa);
                }
            } else {
                $errores[] = "Error al guardar $nombre_descriptivo en el servidor";
            }
            
        } else if (isset($_FILES[$campo]) && $_FILES[$campo]['error'] !== UPLOAD_ERR_NO_FILE) {
            // Manejar otros tipos de errores de upload
            $errores[] = "Error al cargar $nombre_descriptivo";
        }
    }
    
    // Verificar que se guardó al menos un documento
    if (empty($archivos_guardados)) {
        throw new Exception("No se guardó ningún documento. " . implode(", ", $errores));
    }
    
    // Si hay algunos errores pero también archivos guardados
    if (!empty($errores)) {
        $conn->rollback();
        throw new Exception("Algunos documentos no se pudieron guardar: " . implode(", ", $errores));
    }
    
    // Si todo salió bien, actualizar el estatus del candidato
    $stmt_update = $conn->prepare("
        UPDATE solicitudes_vacantes_candidatos 
        SET estatus = 'Documentos Recibidos'
        WHERE candidato_id = ?
    ");
    $stmt_update->bind_param("i", $candidato_id);
    $stmt_update->execute();
    
    // Invalidar el token para que no se pueda usar de nuevo
    $stmt_token = $conn->prepare("
        UPDATE solicitudes_vacantes_candidatos 
        SET token_documentos = NULL 
        WHERE candidato_id = ?
    ");
    $stmt_token->bind_param("i", $candidato_id);
    $stmt_token->execute();
    
    $conn->commit();
    
    $response['success'] = true;
    $response['message'] = "Documentos guardados exitosamente: " . implode(", ", $archivos_guardados);
    $response['total_documentos'] = count($archivos_guardados);
    
} catch (Exception $e) {
    $conn->rollback();
    
    // Eliminar archivos físicos si hubo error en la transacción
    foreach (glob($upload_dir . "*") as $archivo) {
        if (is_file($archivo) && time() - filemtime($archivo) < 60) { // Solo archivos recientes
            unlink($archivo);
        }
    }
    
    $response['message'] = $e->getMessage();
}

$conn->close();

echo json_encode($response);
?>