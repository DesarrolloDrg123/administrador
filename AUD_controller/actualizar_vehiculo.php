<?php
session_start();
require("../config/db.php");
header('Content-Type: application/json');

$id = (int)$_POST['id'];
$usuario_sesion = $_SESSION['usuario_id'] ?? 0;

// 1. Obtener datos actuales con JOIN para tener los nombres reales antes del cambio
$sql_current = "SELECT v.*, s.sucursal, u1.nombre as responsable, u2.nombre as gerente 
                FROM vehiculos_aud v
                LEFT JOIN sucursales s ON v.sucursal_id = s.id
                LEFT JOIN usuarios u1 ON v.responsable_id = u1.id
                LEFT JOIN usuarios u2 ON v.gerente_reportar_id = u2.id
                WHERE v.id = ?";
$stmt_curr = $conn->prepare($sql_current);
$stmt_curr->bind_param("i", $id);
$stmt_curr->execute();
$current_data = $stmt_curr->get_result()->fetch_assoc();

// Mapeo de campos y sus nombres de tabla para traer el nombre nuevo
$config_campos = [
    'sucursal_id' => ['label' => 'Sucursal', 'tabla' => 'sucursales', 'col' => 'sucursal', 'valor_viejo' => $current_data['sucursal']],
    'responsable_id' => ['label' => 'Responsable', 'tabla' => 'usuarios', 'col' => 'nombre', 'valor_viejo' => $current_data['responsable']],
    'gerente_reportar_id' => ['label' => 'Gerente a Reportar', 'tabla' => 'usuarios', 'col' => 'nombre', 'valor_viejo' => $current_data['gerente']],
    'placas' => ['label' => 'Placas'],
    'no_licencia' => ['label' => 'No. Licencia'],
    'fecha_vencimiento_licencia' => ['label' => 'Vigencia Licencia'],
    'tarjeta_circulacion' => ['label' => 'Tarjeta Circulación'],
    'aseguradora' => ['label' => 'Aseguradora'],
    'no_poliza' => ['label' => 'No. Póliza'],
    'vigencia_poliza' => ['label' => 'Vigencia Póliza'],
    'telefono_siniestro' => ['label' => 'Tel. Siniestro'],
    'estatus' => ['label' => 'Estatus'],
    'observaciones' => ['label' => 'Observaciones'],
];

$cambios_realizados = 0;

foreach ($config_campos as $campo_db => $info) {
    $nuevo_valor_raw = $_POST[$campo_db] ?? '';
    $valor_viejo_db = $current_data[$campo_db];

    if ($nuevo_valor_raw != $valor_viejo_db) {
        // Actualizamos la tabla principal
        $upd = "UPDATE vehiculos_aud SET $campo_db = ? WHERE id = ?";
        $st_upd = $conn->prepare($upd);
        $st_upd->bind_param("si", $nuevo_valor_raw, $id);
        
        if ($st_upd->execute()) {
            $label = $info['label'];
            $txt_viejo = "";
            $txt_nuevo = "";

            // Lógica para obtener nombres reales si es un ID
            if (isset($info['tabla'])) {
                $txt_viejo = $info['valor_viejo'] ?: 'Sin asignar';
                
                // Buscar el nombre del NUEVO ID
                $sql_nom = "SELECT {$info['col']} as nombre FROM {$info['tabla']} WHERE id = ?";
                $st_nom = $conn->prepare($sql_nom);
                $st_nom->bind_param("i", $nuevo_valor_raw);
                $st_nom->execute();
                $res_nom = $st_nom->get_result()->fetch_assoc();
                $txt_nuevo = $res_nom['nombre'] ?? 'Desconocido';
            } else {
                // Es un campo de texto normal (placas, póliza, etc)
                $txt_viejo = $valor_viejo_db ?: 'Vacío';
                $txt_nuevo = $nuevo_valor_raw;
            }

            // Guardamos la frase completa que te gusta
            $frase_historial = "Cambio de: $txt_viejo a: $txt_nuevo";

            $sql_h = "INSERT INTO vehiculos_historial_aud (vehiculo_id, usuario_id, campo_modificado, valor_nuevo) 
                      VALUES (?, ?, ?, ?)";
            $st_h = $conn->prepare($sql_h);
            $st_h->bind_param("iiss", $id, $usuario_sesion, $label, $frase_historial);
            $st_h->execute();
            
            $cambios_realizados++;
        }
    }
}

if ($cambios_realizados > 0) {
    echo json_encode(['status' => 'success', 'message' => "Se registraron $cambios_realizados cambios con éxito."]);
} else {
    echo json_encode(['status' => 'info', 'message' => 'No se detectaron cambios que guardar.']);
}

$conn->close();