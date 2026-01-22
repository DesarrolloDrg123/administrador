<?php
session_start();
require("../config/db.php");
header('Content-Type: application/json');

// Recibir datos (soportamos POST estándar y JSON para el delete)
$data = json_decode(file_get_contents("php://input"), true) ?: $_POST;

$action      = $data['action'] ?? 'save';
$id          = (int)($data['id'] ?? 0);
$tipo        = $data['tipo'] ?? '';
$descripcion = $data['descripcion'] ?? '';
$c1          = (int)($data['c1'] ?? 0);
$c2          = (int)($data['c2'] ?? 0);
$c3          = (int)($data['c3'] ?? 0);
$nuevo_status = $data['nuevo_status'] ?? 'N';

// --- LOGICA PARA CAMBIAR ESTATUS (BORRADO LÓGICO) ---
if ($action === 'delete') {
    $sql = "UPDATE cat_items_auditoria_aud SET activo = ? WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("si", $nuevo_status, $id);
    
    if ($stmt->execute()) {
        echo json_encode(['status' => 'success', 'message' => 'Estatus actualizado correctamente.']);
    } else {
        echo json_encode(['status' => 'error', 'message' => $conn->error]);
    }
    exit;
}

// --- LOGICA PARA GUARDAR / EDITAR ---
if ($action === 'save') {
    
    // 1. Validar que la suma de C1 no exceda 100 para ese TIPO
    // Excluimos el ID actual si es una edición
    $sql_suma = "SELECT SUM(c1) as total FROM cat_items_auditoria_aud WHERE tipo = ? AND activo = 'S' AND id != ?";
    $stmt_s = $conn->prepare($sql_suma);
    $stmt_s->bind_param("si", $tipo, $id);
    $stmt_s->execute();
    $res_suma = $stmt_s->get_result()->fetch_assoc();
    $suma_actual = (int)$res_suma['total'];

    if (($suma_actual + $c1) > 100) {
        echo json_encode([
            'status' => 'error', 
            'message' => "La suma de puntos para '$tipo' excedería el 100% (Actual: $suma_actual, Nuevo: $c1). Ajuste los valores."
        ]);
        exit;
    }

    if ($id > 0) {
        // UPDATE
        $sql = "UPDATE cat_items_auditoria_aud SET tipo=?, descripcion=?, c1=?, c2=?, c3=? WHERE id=?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssiiii", $tipo, $descripcion, $c1, $c2, $c3, $id);
    } else {
        // INSERT
        $sql = "INSERT INTO cat_items_auditoria_aud (tipo, descripcion, c1, c2, c3, activo) VALUES (?, ?, ?, ?, ?, 'S')";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssiii", $tipo, $descripcion, $c1, $c2, $c3);
    }

    if ($stmt->execute()) {
        echo json_encode(['status' => 'success', 'message' => 'Concepto guardado con éxito.']);
    } else {
        echo json_encode(['status' => 'error', 'message' => $conn->error]);
    }
}

$conn->close();