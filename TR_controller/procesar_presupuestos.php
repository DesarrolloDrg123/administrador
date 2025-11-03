<?php 
session_start();
require '../config/db.php';
require '../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

if (isset($_FILES['excel_periodos']) && $_FILES['excel_periodos']['error'] == 0) {
    $archivoTmp = $_FILES['excel_periodos']['tmp_name'];

    // Cargar el archivo
    try {
        $spreadsheet = IOFactory::load($archivoTmp);
        $hoja = $spreadsheet->getActiveSheet();
        $filas = $hoja->toArray();
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error al leer el archivo: ' . $e->getMessage()]);
        exit;
    }

    // Validar que hay al menos una fila además del encabezado
    if (count($filas) <= 1) {
        echo json_encode(['success' => false, 'message' => 'El archivo no contiene datos.']);
        exit;
    }

    // Obtener el periodo del archivo (primer dato de la columna de periodo)
    $periodo = $filas[1][3]; // Asegúrate de que esté en la columna 4 (índice 3)

    // Contar cuántos registros hay ya cargados en la base de datos para ese periodo
    $stmt = $conn->prepare("SELECT COUNT(*) AS total FROM presupuestos WHERE periodo = ?");
    $stmt->bind_param("s", $periodo);
    $stmt->execute();
    $stmt->bind_result($presupuestosExistentes);
    $stmt->fetch();
    $stmt->close();

    // Obtener total de combinaciones sucursal x departamento
    $sql = "SELECT COUNT(*) AS total FROM sucursales s CROSS JOIN departamentos d";
    $resultado = $conn->query($sql);
    if (!$resultado) {
        echo json_encode(['success' => false, 'message' => 'Error al consultar las combinaciones de sucursal y departamento.']);
        exit;
    }
    $fila = $resultado->fetch_assoc();
    $totalCombinaciones = $fila['total'];

    if ($presupuestosExistentes >= $totalCombinaciones) {
        echo json_encode(['success' => false, 'message' => "Ya se han cargado los presupuestos completos para el periodo $periodo."]);
        exit;
    }

    // Procesar archivo e insertar
    $errores = [];
    foreach (array_slice($filas, 1) as $fila) {
        $sucursal_id = $fila[0];
        $departamento_id = $fila[1];
        $presupuesto = $fila[2];
        $restante = $fila[2];
        $periodo = $fila[3];
        
        // Si el presupuesto está vacío o es null, se asigna 0
        if (empty($presupuesto) && $presupuesto !== "0") {
            $presupuesto = 0;
        }
    
        // Aseguramos que restante también refleje el valor corregido
        if (empty($restante) && $restante !== "0") {
            $restante = 0;
        }

        // Validar que los valores sean correctos antes de insertar
        if (empty($sucursal_id) || empty($departamento_id)) {
            $errores[] = "Faltan datos en la fila: Sucursal $sucursal_id, Departamento $departamento_id.";
            continue;
        }

        $stmt = $conn->prepare("INSERT INTO presupuestos (periodo, sucursal_id, departamento_id, presupuesto, restante) VALUES (?, ?, ?, ?, ?)");
        if ($stmt) {
            $stmt->bind_param("siiii", $periodo, $sucursal_id, $departamento_id, $presupuesto, $restante);
            $stmt->execute();
            $stmt->close();
        } else {
            $errores[] = "Error al preparar la consulta para insertar datos.";
        }
    }

    if (count($errores) > 0) {
        echo json_encode(['success' => false, 'message' => implode('<br>', $errores)]);
    } else {
        echo json_encode(['success' => true, 'message' => "Presupuestos cargados correctamente para el periodo $periodo."]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Error al subir el archivo.']);
}

?>
