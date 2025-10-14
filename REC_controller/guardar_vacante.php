<?php
session_start();

header('Content-Type: application/json');

require("../config/db.php"); // Solo necesitas la conexión a la BD

$response = ['success' => false, 'message' => ''];

function obtenerFolioReclutamiento($conn) {
    // Tu función de folio se mantiene igual...
    $sql = "SELECT folio FROM control_folios_rec WHERE id = 1 FOR UPDATE";
    $resultado = $conn->query($sql);
    if ($resultado->num_rows > 0) {
        $fila = $resultado->fetch_assoc();
        $ultimo_folio = $fila['folio'];
        if ($ultimo_folio !== null) {
            $ultimo_folio++;
        } else {
            $ultimo_folio = 1;
        }
        $folio_formateado = sprintf('%09d', $ultimo_folio);
        return $folio_formateado;
    } else {
        return sprintf('%09d', 1);
    }
}

// 1. Validaciones Iniciales
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    $response['message'] = 'Error: Método de solicitud no válido.';
    echo json_encode($response);
    exit();
}
if (!isset($_SESSION['usuario_id'], $_SESSION['nombre'])) {
    $response['message'] = 'Error: Sesión no iniciada. Por favor, inicie sesión.';
    echo json_encode($response);
    exit();
}

$conn->begin_transaction();

try {
    // 2. Recolección de datos del formulario
    $puesto_id = $_POST['puesto_id'] ?? null;
    $tipo_vacante = $_POST['tipo_vacante'] ?? '';
    $justificacion = $_POST['justificacion'] ?? '';
    $requisitos = $_POST['descripcion'] ?? '';
    $solicitante_nombre = $_SESSION['nombre'];
    $reemplaza_a = $_POST['reemplaza_a'] ?? "";

    if (empty($puesto_id) || empty($tipo_vacante) || empty($justificacion)) {
        throw new Exception("Los campos Puesto, Tipo de Vacante y Justificación son obligatorios.");
    }

    // 3. Obtener y actualizar el folio
    $folio = obtenerFolioReclutamiento($conn);
    $stmt_update_folio = $conn->prepare("UPDATE control_folios_rec SET folio = ? WHERE id = 1");
    $stmt_update_folio->bind_param("s", $folio);
    $stmt_update_folio->execute();
    $stmt_update_folio->close();
    
    // Obtener el nombre del puesto
    $stmt_puesto = $conn->prepare("SELECT puesto FROM puestos WHERE id = ?");
    $stmt_puesto->bind_param("i", $puesto_id);
    $stmt_puesto->execute();
    $puesto_nombre = $stmt_puesto->get_result()->fetch_assoc()['puesto'];
    $stmt_puesto->close();

    // 4. Insertar en la tabla principal `solicitudes_vacantes`
    $sql_solicitud = "INSERT INTO solicitudes_vacantes 
                        (folio, solicitante, puesto_solicitado, fecha_hora_solicitud, justificacion, requisitos, estatus, tipo_vacante, reemplaza_a) 
                      VALUES (?, ?, ?, NOW(), ?, ?, 'Nueva Solicitud', ?, ?)";
    $stmt_solicitud = $conn->prepare($sql_solicitud);
    if ($stmt_solicitud === false) {
        throw new Exception("Error al preparar la consulta de solicitud: " . $conn->error);
    }
    $stmt_solicitud->bind_param("sssssss", $folio, $solicitante_nombre, $puesto_nombre, $justificacion, $requisitos, $tipo_vacante, $reemplaza_a);
    if (!$stmt_solicitud->execute()) {
        throw new Exception("Error al guardar la solicitud: " . $stmt_solicitud->error);
    }

    // <-- NUEVO: Obtener el ID de la solicitud recién creada
    $nueva_solicitud_id = $conn->insert_id;
    $stmt_solicitud->close();

    // <-- NUEVO: Insertar el primer registro en la tabla de histórico
    $comentario_historico = "Creación de la solicitud.";
    $sql_historico = "INSERT INTO solicitudes_vacantes_historial 
                        (solicitud_id, folio_solicitud, usuario_accion, fecha_accion, estatus_nuevo, comentarios) 
                      VALUES (?, ?, ?, NOW(), ?, ?)";
    
    $stmt_historico = $conn->prepare($sql_historico);
    if ($stmt_historico === false) {
        throw new Exception("Error al preparar la consulta de histórico: " . $conn->error);
    }
    $estatus_nuevo = 'Nueva Solicitud';
    $stmt_historico->bind_param("issss", $nueva_solicitud_id, $folio, $solicitante_nombre, $estatus_nuevo, $comentario_historico);
    if (!$stmt_historico->execute()) {
        throw new Exception("Error al guardar el histórico: " . $stmt_historico->error);
    }
    $stmt_historico->close();


    // Si todo fue bien, se confirman ambas inserciones
    $conn->commit();

    // 5. Preparar respuesta de éxito
    $response['success'] = true;
    $response['message'] = "Solicitud con folio " . htmlspecialchars($folio) . " creada con éxito.";

} catch (Exception $e) {
    // Si algo falló, se revierten ambas inserciones
    $conn->rollback();
    $response['message'] = $e->getMessage();
}

$conn->close();

// 6. Enviar la respuesta final
echo json_encode($response);
?>