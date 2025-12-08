<?php
session_start();
require("../config/db.php");

if (!isset($_SESSION['loggedin']) || !$_SESSION['loggedin']) {
    echo "sin_sesion";
    exit;
}

if (!isset($_POST['folio']) || empty($_POST['folio'])) {
    echo "folio_invalido";
    exit;
}

if (!isset($_POST['motivo']) || empty($_POST['motivo'])) {
    echo "motivo_vacio";
    exit;
}

$folio  = $_POST['folio'];
$motivo = $_POST['motivo'];

// Actualizar estatus y motivo
$stmt = $conn->prepare("
    UPDATE transferencias_clara_tcl
    SET estado = 'Cancelada',
        motivo = ?
    WHERE folio = ?
");

$stmt->bind_param("ss", $motivo, $folio);

if ($stmt->execute()) {
    echo "success";
} else {
    echo "error_db";
}
