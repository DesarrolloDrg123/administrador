<?php
session_start();
header('Content-Type: application/json');
require("../config/db.php");

$response = ['success' => false, 'message' => 'Error desconocido.'];

if (!isset($_SESSION['loggedin']) || !$_SESSION['loggedin']) {
    $response['message'] = 'Acceso denegado.';
    echo json_encode($response);
    exit;
}

$candidato_id = $_POST['id'] ?? null;
$archivo = $_FILES['contrato_file'] ?? null;
$usuario_accion = $_SESSION['nombre']; // Necesitamos quién hace la acción para el historial

if (empty($candidato_id) || empty($archivo)) {
    $response['message'] = 'No se recibió el ID del candidato o el archivo del contrato.';
    echo json_encode($response);
    exit;
}

$conn->begin_transaction();

try {
    // 1. Obtener datos actuales del candidato (ruta y estatus anterior)
    $stmt_candidato = $conn->prepare("SELECT cv_adjunto_path, estatus FROM solicitudes_vacantes_candidatos WHERE candidato_id = ?");
    $stmt_candidato->bind_param("i", $candidato_id);
    $stmt_candidato->execute();
    $result = $stmt_candidato->get_result()->fetch_assoc();
    $stmt_candidato->close();

    if (!$result) {
        throw new Exception("No se encontró el expediente del candidato.");
    }
    $directorio_candidato = $result['cv_adjunto_path'];
    $estatus_anterior = $result['estatus']; // Guardamos el estatus actual para el historial

    // 2. Procesar y mover el archivo del contrato
    if ($archivo['error'] === UPLOAD_ERR_OK) {
        $nombre_original = basename($archivo['name']);
        $ruta_destino = $directorio_candidato . $nombre_original;

        if (move_uploaded_file($archivo['tmp_name'], $ruta_destino)) {
            
            // 3. Insertar el registro en la tabla de documentos (con el nombre que indicaste)
            $stmt_insert = $conn->prepare(
                "INSERT INTO solicitudes_vacantes_candidatos_documentos (candidato_id, nombre_documento, ruta_documento) VALUES (?, ?, ?)"
            );
            $stmt_insert->bind_param("iss", $candidato_id, $nombre_original, $ruta_destino);
            $stmt_insert->execute();
            $stmt_insert->close();

            // 4. Actualizar el estatus del candidato a "Contrato Firmado"
            $nuevo_estatus = 'Contrato Firmado';
            $stmt_update = $conn->prepare("UPDATE solicitudes_vacantes_candidatos SET estatus = ? WHERE candidato_id = ?");
            $stmt_update->bind_param("si", $nuevo_estatus, $candidato_id);
            $stmt_update->execute();
            $stmt_update->close();

            // 5. --- INICIO: REGISTRO EN EL HISTORIAL ---
            $comentario_historial = "Se cargó el contrato firmado: " . $nombre_original;
            $stmt_historial = $conn->prepare(
                "INSERT INTO solicitudes_vacantes_candidatos_historial (candidato_id, usuario_accion, fecha_accion, estatus_anterior, estatus_nuevo, comentarios) 
                 VALUES (?, ?, NOW(), ?, ?, ?)"
            );
            $stmt_historial->bind_param("issss", $candidato_id, $usuario_accion, $estatus_anterior, $nuevo_estatus, $comentario_historial);
            $stmt_historial->execute();
            $stmt_historial->close();
            // --- FIN: REGISTRO EN EL HISTORIAL ---

        } else {
            throw new Exception("Error al mover el archivo del contrato.");
        }
    } else {
        throw new Exception("Error en la subida del archivo.");
    }

    $conn->commit();
    $response['success'] = true;
    $response['message'] = 'Contrato subido y estatus actualizado con éxito.';

} catch (Exception $e) {
    $conn->rollback();
    $response['message'] = $e->getMessage();
}

$conn->close();
echo json_encode($response);
?>