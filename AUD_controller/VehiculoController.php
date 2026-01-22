<?php
session_start();
require("../config/db.php");
require '../vendor/autoload.php';

header('Content-Type: application/json');

// 1. Recoger todos los datos del formulario (16 campos)
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

// 2. Validación de campos obligatorios
if (!$no_serie || !$marca || !$modelo || !$fecha_alta) {
    echo json_encode(['status' => 'error', 'message' => 'Faltan campos obligatorios marcados con (*).']);
    exit;
}

// 3. Sentencia Preparada para insertar en vehiculos_aud
// Total 16 campos. Tipos: sssssiii ssssssss (5 strings, 3 ints, 8 strings)
$sql = "INSERT INTO vehiculos_aud (
            no_serie, fecha_alta, marca, modelo, anio, 
            sucursal_id, responsable_id, gerente_reportar_id, 
            no_licencia, fecha_vencimiento_licencia, placas, 
            tarjeta_circulacion, aseguradora, no_poliza, 
            vigencia_poliza, telefono_siniestro, estatus
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    echo json_encode(['status' => 'error', 'message' => 'Error en la preparación de la consulta: ' . $conn->error]);
    exit;
}

// Vinculación de los 16 parámetros
$stmt->bind_param("sssssiiisssssssss", 
    $no_serie, $fecha_alta, $marca, $modelo, $anio, 
    $sucursal_id, $responsable_id, $gerente_reportar_id, 
    $no_licencia, $vigencia_licencia, $placas, 
    $tarjeta_circulacion, $aseguradora, $no_poliza, 
    $vigencia_poliza, $telefono_siniestro, 'Activo'
);

if ($stmt->execute()) {
    $nuevo_id = $conn->insert_id; 

    // 4. Registrar en el historial (vehiculos_historial_aud)
    // Usamos el ID del usuario en sesión si está disponible, sino el responsable_id
    $usuario_sesion = $_SESSION['usuario_id'] ?? $responsable_id;
    $detalle = "Alta inicial de unidad";
    $campo = "Registro";
    
    $sql_h = "INSERT INTO vehiculos_historial_aud (vehiculo_id, usuario_id, campo_modificado, valor_nuevo) 
              VALUES (?, ?, ?, ?)";
    $stmt_h = $conn->prepare($sql_h);
    $stmt_h->bind_param("iiss", $nuevo_id, $usuario_sesion, $campo, $detalle);
    $stmt_h->execute();

    echo json_encode([
        'status' => 'success', 
        'message' => 'La unidad ha sido dada de alta correctamente en la flotilla.'
    ]);
} else {
    // Manejo de errores específicos
    if ($conn->errno == 1062) {
        $mensaje = "El Número de Serie '$no_serie' ya se encuentra registrado.";
    } else {
        $mensaje = "Error al guardar: " . $conn->error;
    }
    echo json_encode(['status' => 'error', 'message' => $mensaje]);
}

$stmt->close();
$conn->close();