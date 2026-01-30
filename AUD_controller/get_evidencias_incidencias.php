<?php
// AUD_controller/get_evidencias_incidencia.php
require("../config/db.php");

header('Content-Type: application/json');

// Validar que se reciba el ID de la incidencia
if (!isset($_GET['id']) || empty($_GET['id'])) {
    echo json_encode([]);
    exit;
}

$id_incidencia = (int)$_GET['id'];

/**
 * Consultamos la tabla de evidencias de incidencias.
 * Asegúrate de que el nombre de la tabla sea el que creamos: 
 * auditorias_evidencias_incidencias
 */
$query = "SELECT ruta_archivo as ruta, fecha_subida as fecha, comentarios 
          FROM auditorias_evidencias_incidencias 
          WHERE incidencia_id = ? 
          ORDER BY fecha_subida DESC";

$stmt = $conn->prepare($query);

if (!$stmt) {
    // Si hay error en la consulta, devolvemos un array vacío
    echo json_encode([]);
    exit;
}

$stmt->bind_param("i", $id_incidencia);
$stmt->execute();
$resultado = $stmt->get_result();

$evidencias = [];

while ($row = $resultado->fetch_assoc()) {
    // Formateamos la fecha para que sea más legible (ej: 30 Jan 2024, 10:00 AM)
    $fecha_formateada = date("d/m/Y H:i", strtotime($row['fecha']));
    
    $evidencias[] = [
        'ruta' => $row['ruta'],
        'fecha' => $fecha_formateada,
        'comentarios' => $row['comentarios']
    ];
}

// Devolvemos el array en formato JSON para que el JavaScript lo procese
echo json_encode($evidencias);

$stmt->close();
$conn->close();
?>