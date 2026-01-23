<?php
session_start();
require("../config/db.php");
header('Content-Type: application/json');

$id = (int)$_POST['id'];
$usuario_sesion = $_SESSION['usuario_id'] ?? 0;

$sql_current = "SELECT * FROM vehiculos_aud WHERE id = ?";
$stmt_curr = $conn->prepare($sql_current);
$stmt_curr->bind_param("i", $id);
$stmt_curr->execute();
$current_data = $stmt_curr->get_result()->fetch_assoc();

// Mapeo para que el historial sea legible
$nombres_amigables = [
    'sucursal_id' => 'Sucursal',
    'responsable_id' => 'Responsable',
    'gerente_reportar_id' => 'Gerente a Reportar',
    'no_licencia' => 'No. Licencia',
    'fecha_vencimiento_licencia' => 'Vigencia Licencia',
    'placas' => 'Placas',
    'tarjeta_circulacion' => 'Tarjeta Circulación',
    'aseguradora' => 'Aseguradora',
    'no_poliza' => 'No. Póliza',
    'vigencia_poliza' => 'Vigencia Póliza',
    'telefono_siniestro' => 'Tel. Siniestro'
];

$campos_a_editar = array_keys($nombres_amigables);
$cambios_realizados = 0;

foreach ($campos_a_editar as $campo) {
    $nuevo_valor = $_POST[$campo] ?? '';
    $valor_viejo = $current_data[$campo];

    if ($nuevo_valor != $valor_viejo) {
        $upd = "UPDATE vehiculos_aud SET $campo = ? WHERE id = ?";
        $st_upd = $conn->prepare($upd);
        $st_upd->bind_param("si", $nuevo_valor, $id);
        
        if ($st_upd->execute()) {
            // Usamos el nombre amigable (ej: 'Sucursal' en vez de 'sucursal_id')
            $campo_historial = $nombres_amigables[$campo];
            
            $sql_h = "INSERT INTO vehiculos_historial_aud (vehiculo_id, usuario_id, campo_modificado, valor_nuevo) 
                      VALUES (?, ?, ?, ?)";
            $st_h = $conn->prepare($sql_h);
            
            // Aquí guardamos el valor crudo, el JOIN del siguiente archivo se encargará de mostrar nombres
            $detalle_historial = "Cambio de: " . ($valor_viejo ?: 'Vacío') . " a: " . $nuevo_valor;
            $st_h->bind_param("iiss", $id, $usuario_sesion, $campo_historial, $detalle_historial);
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