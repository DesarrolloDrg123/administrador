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
$estatus             = $_POST['estatus'] ?? 'Activo';
$observaciones       = $_POST['observaciones'] ?? '';

if (!$no_serie || !$marca || !$modelo || !$fecha_alta) {
    echo json_encode(['status' => 'error', 'message' => 'Faltan campos obligatorios.']);
    exit;
}

// INICIAR TRANSACCIÓN
$conn->begin_transaction();

try {
    // --- NUEVO: VERIFICADOR DE EXISTENCIA PREVIA ---
    $check_sql = "SELECT id FROM vehiculos_aud WHERE no_serie = ? LIMIT 1";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("s", $no_serie);
    $check_stmt->execute();
    $res_check = $check_stmt->get_result();

    if ($res_check->num_rows > 0) {
        // Si ya existe, lanzamos una excepción personalizada
        throw new Exception("El Número de Serie '$no_serie' ya se encuentra registrado en el catálogo.", 999);
    }
    $check_stmt->close();
    // -----------------------------------------------

    $sql = "INSERT INTO vehiculos_aud (
                no_serie, fecha_alta, marca, modelo, anio, 
                sucursal_id, responsable_id, gerente_reportar_id, 
                no_licencia, fecha_vencimiento_licencia, placas, 
                tarjeta_circulacion, aseguradora, no_poliza, 
                vigencia_poliza, telefono_siniestro, estatus, observaciones
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ? )";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sssssiiissssssssss", 
        $no_serie, $fecha_alta, $marca, $modelo, $anio, 
        $sucursal_id, $responsable_id, $gerente_reportar_id, 
        $no_licencia, $vigencia_licencia, $placas, 
        $tarjeta_circulacion, $aseguradora, $no_poliza, 
        $vigencia_poliza, $telefono_siniestro, $estatus, $observaciones
    );

    if (!$stmt->execute()) {
        throw new Exception($conn->error, $conn->errno);
    }

    $nuevo_id = $conn->insert_id; 

    // Historial
    $usuario_sesion = $_SESSION['usuario_id'] ?? $responsable_id;
    $detalle = "Alta de Unidad";
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
    $conn->rollback(); 
    
    // Si es nuestro código de error personalizado (999) o el de SQL (1062)
    if ($e->getCode() == 999 || $e->getCode() == 1062) {
        $mensaje = $e->getMessage();
        // Limpiamos el mensaje por si viene de SQL directamente
        if($e->getCode() == 1062) $mensaje = "Error: El Número de Serie ya existe.";
    } else {
        $mensaje = "Error al guardar: " . $e->getMessage();
    }
    
    echo json_encode(['status' => 'error', 'message' => $mensaje]);
}

$conn->close();