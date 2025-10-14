<?php
session_start();
header('Content-Type: application/json');
require '../config/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'] ?? null;
    $usuario = $_SESSION['nombre'] ?? 'desconocido';
    
    function limpiarNumero($valor) {
        return floatval(str_replace([','], '', $valor));
    }
    
    $presupuesto = limpiarNumero($_POST['presupuesto']);
    $registrado  = limpiarNumero($_POST['registrado']);
    $restante    = limpiarNumero($_POST['restante']);
    $motivo    = $_POST['motivo'];


    if (!$id) {
        echo json_encode(['success' => false, 'message' => 'ID no recibido']);
        exit;
    }

    // Obtener valores anteriores antes del update
    $stmtPrev = $conn->prepare("SELECT sucursal_id, departamento_id, presupuesto, registrado, restante, periodo FROM presupuestos WHERE id=?");
    $stmtPrev->bind_param("i", $id);
    $stmtPrev->execute();
    $stmtPrev->bind_result($sucursal ,$departamento, $presupuestoAnt, $registradoAnt, $restanteAnt, $periodo);
    $stmtPrev->fetch();
    $stmtPrev->close();
    
    // VALIDACIÓN: nuevo presupuesto no debe ser menor al anterior
    if ($presupuesto < $presupuestoAnt) {
        echo json_encode([
            'success' => false,
            'message' => 'El nuevo presupuesto no puede ser menor al presupuesto anterior (' . number_format($presupuestoAnt, 2) . ')'
        ]);
        exit;
    }

    $restante = $presupuesto -  $registrado;
    // Ejecutar el UPDATE
    $stmt = $conn->prepare("UPDATE presupuestos SET presupuesto=?, restante=? WHERE id=?");
    $stmt->bind_param("ddi", $presupuesto, $restante, $id);
    $updateSuccess = $stmt->execute();
    $stmt->close();

    // Registrar en el historial si hubo cambios
    if ($updateSuccess) {
        
        $campos = [
            'presupuesto' => [$presupuestoAnt, $presupuesto],
            'registrado'  => [$registradoAnt, $registrado],
            'restante'    => [$restanteAnt, $restante]
        ];
        
        foreach ($campos as $campo => $valores) {
        
            // Desestructurar correctamente
            $anterior = strval($valores[0]);
            $nuevo    = strval($valores[1]);
        
            if ($anterior !== $nuevo) {
        
                $stmtHist = $conn->prepare(
                    "INSERT INTO historial_presupuestos (id_presupuesto, id_sucursal, id_departamento, campo_modificado, valor_anterior, valor_nuevo, usuario, periodo, motivo_edicion)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
                );
        
                // Todos los parámetros pasados como variables simples (no dentro de array)
                $stmtHist->bind_param("iiissssss", $id, $sucursal, $departamento, $campo, $anterior, $nuevo, $usuario, $periodo, $motivo);
                $stmtHist->execute();
                $stmtHist->close();
            }
        }

        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error al guardar']);
    }

    $conn->close();
} else {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
}
?>
