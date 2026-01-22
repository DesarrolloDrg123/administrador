<?php
session_start();
require("../config/db.php");
header('Content-Type: application/json');

$input = file_get_contents("php://input");
$data = json_decode($input, true);

if (!$data) {
    echo json_encode(['status' => 'error', 'message' => 'No se recibieron datos válidos']);
    exit;
}

$conn->begin_transaction();

try {
    // --- NUEVO: CALCULAR TOTALES ANTES DE INSERTAR ---
    $puntos_doc = 0;
    $puntos_inv = 0;
    $puntos_est = 0;

    // Recorremos las respuestas para sumar por separado (esto asume que envías el tipo o lo calculamos aquí)
    // Para simplificar y asegurar que se guarde el total general:
    $total_general = 0;
    foreach ($data['respuestas'] as $resp) {
        $puntos = floatval($resp['puntos']);
        $total_general += $puntos;
        
        // Si necesitas desglosar por categoría, podrías hacerlo aquí si el JS enviara el 'tipo'
    }

    // 1. INSERTAR CABECERA (Campos agregados: calif_total)
    // Nota: Agregué calif_total al INSERT
    $stmt = $conn->prepare("INSERT INTO auditorias_vehiculos_aud (folio, vehiculo_id, usuario_id, fecha_auditoria, kilometraje, observaciones, calif_total) VALUES (?, ?, ?, ?, ?, ?, ?)");
    
    $stmt->bind_param("siisssd", 
        $data['folio'], 
        $data['vehiculo_id'], 
        $_SESSION['usuario_id'], 
        $data['fecha'], 
        $data['kilometraje'], 
        $data['observaciones'],
        $total_general // Se guarda el total calculado
    );
    $stmt->execute();
    $auditoria_id = $conn->insert_id;

    // 2. INSERTAR DETALLES
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

    // 3. INSERTAR MANTENIMIENTOS
    if (!empty($data['mantenimiento'])) {
        $stmt_mante = $conn->prepare("INSERT INTO auditorias_mantenimiento_aud (auditoria_id, fecha, km, servicio, taller, observaciones) VALUES (?, ?, ?, ?, ?, ?)");
        foreach ($data['mantenimiento'] as $m) {
            if (!empty($m['servicio'])) {
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
    }

    // INSERTAR INCIDENCIAS (Si existen)
    if (!empty($data['incidencias'])) {
        $stmt_inc = $conn->prepare("INSERT INTO auditorias_incidencias_aud (auditoria_id, vehiculo_id, descripcion, fecha_incidencia, estatus) VALUES (?, ?, ?, ?, 'Pendiente')");
        foreach ($data['incidencias'] as $inc) {
            $stmt_inc->bind_param("iiss", 
                $auditoria_id, 
                $data['vehiculo_id'], 
                $inc['descripcion'], 
                $data['fecha']
            );
            $stmt_inc->execute();
        }
    }

    // 4. ACTUALIZAR FOLIO
    $nuevo_folio_num = intval($data['folio']);
    $update_folio = $conn->prepare("UPDATE control_folios_aud SET folio = ? WHERE id = 1");
    $update_folio->bind_param("i", $nuevo_folio_num);
    $update_folio->execute();

    $conn->commit();
    echo json_encode(['status' => 'success', 'message' => 'Auditoría guardada correctamente con folio: ' . $data['folio']]);

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['status' => 'error', 'message' => 'Error: ' . $e->getMessage()]);
}

$conn->close();