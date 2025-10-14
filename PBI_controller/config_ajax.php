<?php
session_start();
require("../config/db.php");

header('Content-Type: application/json');
$response = ['success' => false, 'message' => 'Acción no reconocida.'];

if (!isset($_SESSION['loggedin']) || !$_SESSION['loggedin']) {
    $response['message'] = 'Error: Sesión no iniciada.';
    echo json_encode($response);
    exit();
}

$accion = $_POST['accion'] ?? '';

switch ($accion) {
    case 'agregar':
    case 'editar':
        $nombre = trim($_POST['report_name'] ?? '');
        $iframe_code = trim($_POST['report_link'] ?? '');
        $id = intval($_POST['id'] ?? 0);
        $parent_id = intval($_POST['parent_id'] ?? 0);

        // --- CORRECCIÓN AQUÍ ---
        // Se elimina la validación que exigía el iframe (|| empty($iframe_code))
        if (empty($nombre)) {
            $response['message'] = 'El nombre del reporte es obligatorio.';
            break;
        }

        if ($parent_id === 0) {
            $parent_id = null;
        }

        $link = null;
        // Solo procesamos el iframe si el usuario ha pegado algo
        if (!empty($iframe_code)) {
            preg_match('/src="([^"]+)"/', $iframe_code, $matches);
            $link = $matches[1] ?? null;

            if (empty($link)) {
                $response['message'] = 'El código iframe no es válido o está mal formado.';
                break;
            }
        }

        if ($accion == 'agregar') {
            $sql = "INSERT INTO powerbi_reports (report_name, report_link, parent_id) VALUES (?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssi", $nombre, $link, $parent_id);
            $response['message'] = 'Reporte agregado correctamente.';

        } else { // Editar
            $sql = "UPDATE powerbi_reports SET report_name = ?, report_link = ?, parent_id = ? WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssii", $nombre, $link, $parent_id, $id);
            $response['message'] = 'Reporte actualizado correctamente.';
        }

        if ($stmt->execute()) {
            $response['success'] = true;
        } else {
            $response['message'] = 'Error al procesar la solicitud en la base de datos.';
        }
        break;

    case 'eliminar':
        // Tu lógica para eliminar (no necesita cambios)
        $id = intval($_POST['id'] ?? 0);
        if ($id > 0) {
            $check_sql = "SELECT COUNT(*) as count FROM powerbi_reports WHERE parent_id = ?";
            $check_stmt = $conn->prepare($check_sql);
            $check_stmt->bind_param("i", $id);
            $check_stmt->execute();
            $child_count = $check_stmt->get_result()->fetch_assoc()['count'];

            if ($child_count > 0) {
                $response['message'] = 'Error: No se puede eliminar porque otros reportes dependen de él.';
            } else {
                $sql = "DELETE FROM powerbi_reports WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("i", $id);
                if ($stmt->execute()) {
                    $response = ['success' => true, 'message' => 'El reporte ha sido eliminado.'];
                } else {
                    $response['message'] = 'Error al eliminar el reporte.';
                }
            }
        } else {
            $response['message'] = 'ID de reporte no válido.';
        }
        break;
}

echo json_encode($response);
$conn->close();
?>