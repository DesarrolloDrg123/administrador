<?php
header('Content-Type: application/json');
require("../config/db.php");

$response = ['success' => false, 'message' => ''];

// Verificar que se recibió token y candidato_id
$token = $_POST['token'] ?? null;
$candidato_id = $_POST['candidato_id'] ?? null;

if (!$token || !$candidato_id) {
    $response['message'] = 'Acceso denegado. Token o ID de candidato no válidos.';
    echo json_encode($response);
    exit();
}

// 1️⃣ Verificar token válido y obtener datos de la solicitud
$stmt_verify = $conn->prepare("
    SELECT solicitud_id, estatus, cv_adjunto_path
    FROM solicitudes_vacantes_candidatos 
    WHERE candidato_id = ? 
    AND token_documentos = ? 
    AND fecha_token_documentos >= DATE_SUB(NOW(), INTERVAL 30 DAY)
");
$stmt_verify->bind_param("is", $candidato_id, $token);
$stmt_verify->execute();
$result = $stmt_verify->get_result();
$solicitud = $result->fetch_assoc();

if (!$solicitud) {
    $response['message'] = 'Token inválido o expirado.';
    echo json_encode($response);
    exit();
}

$solicitud_id = $solicitud['solicitud_id'];
$estatus_anterior = $solicitud['estatus'];
$nuevo_estatus = 'Documentos Recibidos';
$usuario_accion = 'Candidato (auto)';
$observaciones = 'Carga completa de documentos desde enlace seguro.';
$directorio_candidato = $solicitud['cv_adjunto_path'];

// Si la ruta no existe o está vacía, crear carpeta predeterminada
if (empty($directorio_candidato)) {
    $directorio_candidato = "../uploads/documentos_candidatos/" . $candidato_id . "/";
}
if (!is_dir($directorio_candidato)) {
    mkdir($directorio_candidato, 0775, true);
}

// 2️⃣ Campos esperados (9 documentos)
$archivos_campos = [
    'acta_nacimiento' => 'Acta de Nacimiento',
    'ine_frente' => 'Credencial del Elector (Frente)',
    'ine_reverso' => 'Credencial del Elector (Reverso)',
    'curp_documento' => 'CURP',
    'csf' => 'Constancia de Situación Fiscal (CSF)',
    'nss' => 'Número de Seguridad Social (NSS)',
    'infonavit' => 'Hoja de Retención INFONAVIT',
    'domicilio' => 'Comprobante de Domicilio Actual',
    'estudios' => 'Comprobante de Último Grado de Estudios',
    'licencia' => 'Licencia Vigente'
];

// 3️⃣ Validaciones
$extensiones_permitidas = ['pdf', 'jpg', 'jpeg', 'png'];
$tamano_maximo = 5 * 1024 * 1024; // 5MB
$archivos_guardados = [];
$errores = [];

$conn->begin_transaction();

try {
    // Procesar cada archivo
    foreach ($archivos_campos as $campo => $nombre_doc) {
        if (isset($_FILES[$campo]) && $_FILES[$campo]['error'] === UPLOAD_ERR_OK) {
            $archivo = $_FILES[$campo];
            $extension = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));

            // Validar tamaño y tipo
            if ($archivo['size'] > $tamano_maximo) {
                $errores[] = "$nombre_doc excede los 5MB permitidos";
                continue;
            }
            if (!in_array($extension, $extensiones_permitidas)) {
                $errores[] = "$nombre_doc tiene formato no permitido ($extension)";
                continue;
            }

            // Generar nombre único
            $nombre_archivo = $campo . "_" . $candidato_id . "_" . time() . "." . $extension;
            $ruta_destino = rtrim($directorio_candidato, "/") . "/" . $nombre_archivo;

            if (move_uploaded_file($archivo['tmp_name'], $ruta_destino)) {

                // 🔍 Verificar si el documento ya existe
                $stmt_check = $conn->prepare("
                    SELECT id, ruta_documento 
                    FROM solicitudes_vacantes_candidatos_documentos
                    WHERE candidato_id = ? AND nombre_documento = ?
                ");
                $stmt_check->bind_param("is", $candidato_id, $nombre_doc);
                $stmt_check->execute();
                $resultado = $stmt_check->get_result();
                $documento_existente = $resultado->fetch_assoc();
                $stmt_check->close();

                if ($documento_existente) {
                    // 🧹 Eliminar archivo anterior si existe
                    if (file_exists($documento_existente['ruta_documento'])) {
                        unlink($documento_existente['ruta_documento']);
                    }

                    // 🔄 Actualizar registro existente
                    $stmt_update_doc = $conn->prepare("
                        UPDATE solicitudes_vacantes_candidatos_documentos
                        SET ruta_documento = ?
                        WHERE id = ?
                    ");
                    $stmt_update_doc->bind_param("si", $ruta_destino, $documento_existente['documento_id']);
                    $stmt_update_doc->execute();
                    $stmt_update_doc->close();
                } else {
                    // ➕ Insertar nuevo documento
                    $stmt_insert_doc = $conn->prepare("
                        INSERT INTO solicitudes_vacantes_candidatos_documentos 
                        (candidato_id, nombre_documento, ruta_documento)
                        VALUES (?, ?, ?)
                    ");
                    $stmt_insert_doc->bind_param("iss", $candidato_id, $nombre_doc, $ruta_destino);
                    $stmt_insert_doc->execute();
                    $stmt_insert_doc->close();
                }

                $archivos_guardados[] = $nombre_doc;
            } else {
                $errores[] = "Error al guardar $nombre_doc";
            }
        }
    }


    if (empty($archivos_guardados)) {
        throw new Exception("No se cargó ningún documento válido. " . implode(", ", $errores));
    }

    // 4️⃣ Actualizar estatus
    $stmt_update = $conn->prepare("
        UPDATE solicitudes_vacantes_candidatos
        SET estatus = ?, token_documentos = NULL, estatus_documentos = 1
        WHERE candidato_id = ?
    ");
    $stmt_update->bind_param("si", $nuevo_estatus, $candidato_id);
    $stmt_update->execute();

    // 5️⃣ Insertar en historial
    $comentario_historial = "Se subieron los documentos: " . implode(", ", $archivos_guardados);
    $stmt_historial = $conn->prepare("
        INSERT INTO solicitudes_vacantes_candidatos_historial
        (candidato_id, usuario_accion, fecha_accion, estatus_anterior, estatus_nuevo, comentarios)
        VALUES (?, ?, NOW(), ?, ?, ?)
    ");
    $stmt_historial->bind_param("issss",
        $candidato_id,
        $usuario_accion,
        $estatus_anterior,
        $nuevo_estatus,
        $comentario_historial
    );
    $stmt_historial->execute();

    $conn->commit();

    $response['success'] = true;
    $response['message'] = "Documentos cargados correctamente.";
    $response['total_documentos'] = count($archivos_guardados);
    $response['documentos'] = $archivos_guardados;

} catch (Exception $e) {
    $conn->rollback();
    $response['message'] = $e->getMessage();
}

$conn->close();
echo json_encode($response);
?>
