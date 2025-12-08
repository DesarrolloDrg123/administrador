<?php
session_start();
require("../config/db.php");

// Validar sesión
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    echo "no_session";
    exit;
}

// Validar que venga el ID
if (!isset($_POST['id']) || empty($_POST['id'])) {
    echo "no_id";
    exit;
}

$id = (int)$_POST['id'];

// Buscar la ruta del archivo antes de borrar
$stmt = $conn->prepare("
    SELECT evidencia 
    FROM comprobantes_tcl 
    WHERE id = ?
");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if (!$result || $result->num_rows === 0) {
    echo "no_existe";
    exit;
}

$row = $result->fetch_assoc();
$ruta = $row['evidencia_ruta'];

// Eliminar registro en BD
$stmtDel = $conn->prepare("DELETE FROM comprobantes_tcl WHERE id = ?");
$stmtDel->bind_param("i", $id);

if ($stmtDel->execute()) {

    // Eliminar archivo físico si existe
    if (!empty($ruta) && file_exists($ruta)) {
        unlink($ruta);
    }

    echo "success";

} else {
    echo "error_db";
}
