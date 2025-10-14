<?php
session_start();
require "config/db.php";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['recibo']) && isset($_POST['id'])) {
    $id = $_POST['id'];
    $file = $_FILES['recibo'];

    $target_dir = "uploads/recibos/";
    $fileType = strtolower(pathinfo($file["name"], PATHINFO_EXTENSION));
    $maxFileSize = 2 * 1024 * 1024; // 2MB

    // Validación del tipo de archivo
    if ($fileType !== 'pdf' && $fileType !== 'jpg' && $fileType !== 'jpeg' && $fileType !== 'png') {
        echo json_encode(['status' => 'error', 'message' => 'Solo se permiten archivos PDF, JPG, JPEG o PNG.']);
        exit();
    }

    // Validación del tamaño del archivo
    if ($file["size"] > $maxFileSize) {
        echo json_encode(['status' => 'error', 'message' => 'El archivo es demasiado grande. El tamaño máximo es de 2MB.']);
        exit();
    }

    // Generar un nombre de archivo único
    $newFileName = uniqid('recibo_', true) . '.' . $fileType;
    $target_file = $target_dir . $newFileName;

    // Subir el archivo
    if (move_uploaded_file($file["tmp_name"], $target_file)) {
        // Actualizar la base de datos con la ruta del recibo
        $sql = "UPDATE transferencias SET recibo = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('si', $target_file, $id);
        if ($stmt->execute()) {
            echo json_encode(['status' => 'success']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Error al actualizar la base de datos.']);
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Error al subir el archivo.']);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'No se ha recibido ningún archivo o ID.']);
}
?>
