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
// Definir la ruta de subida (asegúrate de que la carpeta exista)
$uploadDir = "documentos_puestos/";
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

switch ($accion) {
    case 'agregar':
        $puesto = trim($_POST['puesto'] ?? '');
        $documentoNombre = null;

        // Validar si viene un archivo
        if (isset($_FILES['documento']) && $_FILES['documento']['error'] === UPLOAD_ERR_OK) {
            $ext = pathinfo($_FILES['documento']['name'], PATHINFO_EXTENSION);
            $documentoNombre = time() . "_" . bin2hex(random_bytes(5)) . "." . $ext;
            move_uploaded_file($_FILES['documento']['tmp_name'], $uploadDir . $documentoNombre);
        }

        if (!empty($puesto)) {
            $sql = "INSERT INTO puestos (puesto, documento) VALUES (?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ss", $puesto, $documentoNombre);
            if ($stmt->execute()) {
                $response = ['success' => true, 'message' => 'Puesto agregado correctamente.'];
            } else {
                $response['message'] = 'Error al guardar en la base de datos.';
            }
        }
        break;

    case 'editar':
        $id = intval($_POST['id'] ?? 0);
        $puesto = trim($_POST['puesto'] ?? '');
        
        if ($id > 0 && !empty($puesto)) {
            // 1. Ver si se subió un nuevo archivo
            if (isset($_FILES['documento']) && $_FILES['documento']['error'] === UPLOAD_ERR_OK) {
                
                // Borrar el archivo anterior físicamente
                $res = $conn->query("SELECT documento FROM puestos WHERE id = $id");
                $old = $res->fetch_assoc();
                if (!empty($old['documento']) && file_exists($uploadDir . $old['documento'])) {
                    unlink($uploadDir . $old['documento']);
                }

                // Subir el nuevo
                $ext = pathinfo($_FILES['documento']['name'], PATHINFO_EXTENSION);
                $nuevoNombre = time() . "_" . bin2hex(random_bytes(5)) . "." . $ext;
                move_uploaded_file($_FILES['documento']['tmp_name'], $uploadDir . $nuevoNombre);

                // Update con nuevo documento
                $sql = "UPDATE puestos SET puesto = ?, documento = ? WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ssi", $puesto, $nuevoNombre, $id);
            } else {
                // Update solo del nombre
                $sql = "UPDATE puestos SET puesto = ? WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("si", $puesto, $id);
            }

            if ($stmt->execute()) {
                $response = ['success' => true, 'message' => 'Puesto actualizado correctamente.'];
            }
        }
        break;

    case 'eliminar':
        $id = intval($_POST['id'] ?? 0);
        if ($id > 0) {
            // Primero buscamos el nombre del archivo para borrarlo del servidor
            $res = $conn->query("SELECT documento FROM puestos WHERE id = $id");
            $fileData = $res->fetch_assoc();
            
            $sql = "DELETE FROM puestos WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $id);
            
            if ($stmt->execute()) {
                // Si se borró de la BD, borrar el archivo físico
                if (!empty($fileData['documento']) && file_exists($uploadDir . $fileData['documento'])) {
                    unlink($uploadDir . $fileData['documento']);
                }
                $response = ['success' => true, 'message' => 'Puesto y archivo eliminados.'];
            } else {
                $response['message'] = ($conn->errno == 1451) 
                    ? 'No se puede eliminar: el puesto está en uso.' 
                    : 'Error al eliminar.';
            }
        }
        break;
}

echo json_encode($response);
$conn->close();