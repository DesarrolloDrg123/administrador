<?php
require("../config/db.php");

// Devolveremos una respuesta JSON para el AJAX
header('Content-Type: application/json');
$response = ['success' => false, 'message' => 'Datos invивlidos.'];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Recibimos los datos enviados por JavaScript
    $id_usuario = intval($_POST['id_usuario'] ?? 0);
    $id_permiso = intval($_POST['id_permiso'] ?? 0); // Este es el ID del programa o del reporte
    $acceso = intval($_POST['acceso'] ?? 0);
    $tipo = $_POST['tipo'] ?? ''; // El tipo nos dice en quиж tabla trabajar

    if ($id_usuario > 0 && $id_permiso > 0) {
        
        // --- Lиоgica para permisos de Reportes Power BI ---
        if ($tipo === 'pbi') {
            if ($acceso == 1) { // Si se marca el checkbox, se da el permiso
                // INSERT IGNORE evita errores si el permiso ya existe, simplemente no hace nada.
                $sql = "INSERT IGNORE INTO powerbi_permissions (user_id, report_id) VALUES (?, ?)";
            } else { // Si se desmarca, se quita el permiso
                $sql = "DELETE FROM powerbi_permissions WHERE user_id = ? AND report_id = ?";
            }
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ii", $id_usuario, $id_permiso);

        // --- Lиоgica para permisos de Programas del Sistema ---
        } elseif ($tipo === 'programa') {
            // Revisa si el permiso ya existe para decidir si hacer un UPDATE o un INSERT
            $sqlCheck = "SELECT id FROM permisos WHERE id_usuario = ? AND id_programa = ?";
            $stmtCheck = $conn->prepare($sqlCheck);
            $stmtCheck->bind_param("ii", $id_usuario, $id_permiso);
            $stmtCheck->execute();
            $result = $stmtCheck->get_result();

            if ($result->num_rows > 0) { // Si existe, se actualiza
                $sql = "UPDATE permisos SET acceso = ? WHERE id_usuario = ? AND id_programa = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("iii", $acceso, $id_usuario, $id_permiso);
            } else { // Si no existe, se inserta
                $sql = "INSERT INTO permisos (id_usuario, id_programa, acceso) VALUES (?, ?, ?)";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("iii", $id_usuario, $id_permiso, $acceso);
            }
        }

        // Ejecutar la consulta correspondiente
        if (isset($stmt) && $stmt->execute()) {
            $response = ['success' => true, 'message' => 'Permiso actualizado correctamente.'];
        } else {
            $response['message'] = 'Error al ejecutar la consulta.';
        }

    } // Fin de la validaciиоn de IDs

    echo json_encode($response);
    $conn->close();
}
?>