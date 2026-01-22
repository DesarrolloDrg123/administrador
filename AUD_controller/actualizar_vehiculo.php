<?php
session_start();
require("../config/db.php");
header('Content-Type: application/json');

$id = (int)$_POST['id'];
$usuario_sesion = $_SESSION['usuario_id'] ?? 0;

// 1. Obtener datos actuales antes de actualizar para comparar
$sql_current = "SELECT * FROM vehiculos_aud WHERE id = ?";
$stmt_curr = $conn->prepare($sql_current);
$stmt_curr->bind_param("i", $id);
$stmt_curr->execute();
$current_data = $stmt_curr->get_result()->fetch_assoc();

// 2. Lista de campos que se pueden editar (según tu documento)
$campos_a_editar = [
    'sucursal_id', 'responsable_id', 'gerente_reportar_id', 'no_licencia', 
    'fecha_vencimiento_licencia', 'placas', 'tarjeta_circulacion', 
    'aseguradora', 'no_poliza', 'vigencia_poliza', 'telefono_siniestro'
];

$cambios_realizados = 0;

foreach ($campos_a_editar as $campo) {
    $nuevo_valor = $_POST[$campo] ?? '';
    $valor_viejo = $current_data[$campo];

    // Si el valor cambió, actualizamos y registramos en historial
    if ($nuevo_valor != $valor_viejo) {
        
        // Actualizar el campo específico
        $upd = "UPDATE vehiculos_aud SET $campo = ? WHERE id = ?";
        $st_upd = $conn->prepare($upd);
        $st_upd->bind_param("si", $nuevo_valor, $id);
        
        if ($st_upd->execute()) {
            // REGISTRO EN HISTORIAL (Punto 1 del documento)
            $sql_h = "INSERT INTO vehiculos_historial_aud (vehiculo_id, usuario_id, campo_modificado, valor_nuevo) 
                      VALUES (?, ?, ?, ?)";
            $st_h = $conn->prepare($sql_h);
            $detalle_historial = "Cambio de: " . ($valor_viejo ?: 'Vacío') . " a: " . $nuevo_valor;
            $st_h->bind_param("iiss", $id, $usuario_sesion, $campo, $detalle_historial);
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