<?php
session_start();
require("../config/db.php");
header('Content-Type: application/json');

// Leer los datos enviados por Fetch
$input = file_get_contents("php://input");
$data = json_decode($input, true);

if (!$data) {
    echo json_encode(['status' => 'error', 'message' => 'No se recibieron datos válidos']);
    exit;
}

// Iniciar Transacción
$conn->begin_transaction();

try {
    // 1. INSERTAR CABECERA
    $stmt = $conn->prepare("INSERT INTO auditorias_vehiculos_aud (folio, vehiculo_id, usuario_id, fecha_auditoria, kilometraje, observaciones) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("siisis", 
        $data['folio'], 
        $data['vehiculo_id'], 
        $_SESSION['usuario_id'], 
        $data['fecha'], 
        $data['kilometraje'], 
        $data['observaciones']
    );
    $stmt->execute();
    $auditoria_id = $conn->insert_id;

    // 2. INSERTAR DETALLES (CHECKLIST)
    $stmt_det = $conn->prepare("INSERT INTO auditorias_detalle_aud (auditoria_id, concepto_id, valor_seleccionado, puntos_obtenidos) VALUES (?, ?, ?, ?)");
    foreach ($data['respuestas'] as $resp) {
        $stmt_det->bind_param("iisi", 
            $auditoria_id, 
            $resp['concepto_id'], 
            $resp['opcion'], 
            $resp['puntos']
        );
        $stmt_det->execute();
    }

    // 3. INSERTAR MANTENIMIENTOS (SI TIENEN DATOS)
    $stmt_mante = $conn->prepare("INSERT INTO auditorias_mantenimiento_aud (auditoria_id, fecha, km, servicio, taller, observaciones) VALUES (?, ?, ?, ?, ?, ?)");
    foreach ($data['mantenimiento'] as $m) {
        if (!empty($m['servicio'])) { // Solo si el campo servicio no está vacío
            $stmt_mante->bind_param("isisss", 
                $auditoria_id, 
                $m['fecha'], 
                $m['km'], 
                $m['servicio'], 
                $m['taller'], 
                $m['obs']
            );
            $stmt_mante->execute();
        }
    }

    // 4. ACTUALIZAR EL CONTROL DE FOLIOS (INCREMENTO)
    // Convertimos el folio de string a número para sumarle 1
    $nuevo_folio_num = intval($data['folio']);
    $update_folio = $conn->prepare("UPDATE control_folios_aud SET folio = ? WHERE id = 1");
    $update_folio->bind_param("i", $nuevo_folio_num);
    $update_folio->execute();

    // Si todo salió bien, confirmamos los cambios
    $conn->commit();
    echo json_encode(['status' => 'success', 'message' => 'Auditoría guardada correctamente con folio: ' . $data['folio']]);

} catch (Exception $e) {
    // Si algo falla, deshacemos todo lo anterior
    $conn->rollback();
    echo json_encode(['status' => 'error', 'message' => 'Error en el proceso: ' . $e->getMessage()]);
}

$conn->close();