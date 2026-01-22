<?php
session_start();
require("../config/db.php");
require '../vendor/autoload.php';

header('Content-Type: application/json');

// 1. Recoger datos
$no_serie            = $_POST['no_serie'] ?? null;
$fecha_alta          = $_POST['fecha_alta'] ?? null;
$marca               = $_POST['marca'] ?? null;
$modelo              = $_POST['modelo'] ?? null;
$anio                = $_POST['anio'] ?? null;
$sucursal_id         = (int)($_POST['sucursal_id'] ?? 0);
$responsable_id      = (int)($_POST['responsable_id'] ?? 0);
$gerente_reportar_id = (int)($_POST['gerente_reportar_id'] ?? 0);
$no_licencia         = $_POST['no_licencia'] ?? '';
$vigencia_licencia    = $_POST['vigencia_licencia'] ?? null;
$placas              = $_POST['placas'] ?? '';
$tarjeta_circulacion  = $_POST['tarjeta_circulacion'] ?? '';
$aseguradora         = $_POST['aseguradora'] ?? '';
$no_poliza           = $_POST['no_poliza'] ?? '';
$vigencia_poliza     = $_POST['vigencia_poliza'] ?? null;
$telefono_siniestro  = $_POST['telefono_siniestro'] ?? '';
$estatus             = 'Activo';

if (!$no_serie || !$marca || !$modelo || !$fecha_alta) {
    echo json_encode(['status' => 'error', 'message' => 'Faltan campos obligatorios.']);
    exit;
}

// INICIAR TRANSACCIÓN
$conn->begin_transaction();

try {
    $sql = "INSERT INTO vehiculos_aud (
                no_serie, fecha_alta, marca, modelo, anio, 
                sucursal_id, responsable_id, gerente_reportar_id, 
                no_licencia, fecha_vencimiento_licencia, placas, 
                tarjeta_circulacion, aseguradora, no_poliza, 
                vigencia_poliza, telefono_siniestro, estatus
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

    $stmt = $conn->prepare($sql);
    
    // 17 parámetros: 5 s, 3 i, 9 s
    $stmt->bind_param("sssssiiisssssssss", 
        $no_serie, $fecha_alta, $marca, $modelo, $anio, 
        $sucursal_id, $responsable_id, $gerente_reportar_id, 
        $no_licencia, $vigencia_licencia, $placas, 
        $tarjeta_circulacion, $aseguradora, $no_poliza, 
        $vigencia_poliza, $telefono_siniestro, $estatus
    );

    if (!$stmt->execute()) {
        throw new Exception($conn->error, $conn->errno);
    }

    $nuevo_id = $conn->insert_id; 

    // Historial
    $usuario_sesion = $_SESSION['usuario_id'] ?? $responsable_id;
    $detalle = "Alta inicial de unidad";
    $campo = "Registro";
    
    $sql_h = "INSERT INTO vehiculos_historial_aud (vehiculo_id, usuario_id, campo_modificado, valor_nuevo) 
              VALUES (?, ?, ?, ?)";
    $stmt_h = $conn->prepare($sql_h);
    $stmt_h->bind_param("iiss", $nuevo_id, $usuario_sesion, $campo, $detalle);
    $stmt_h->execute();

    // CONFIRMAR TODO
    $conn->commit();

    echo json_encode([
        'status' => 'success', 
        'message' => 'La unidad ha sido dada de alta correctamente.'
    ]);

} catch (Exception $e) {
    $conn->rollback(); // Cancelar cambios si algo falla
    
    if ($e->getCode() == 1062) {
        $mensaje = "El Número de Serie '$no_serie' ya existe.";
    } else {
        $mensaje = "Error al guardar: " . $e->getMessage();
    }
    
    echo json_encode(['status' => 'error', 'message' => $mensaje]);
}

$conn->close();