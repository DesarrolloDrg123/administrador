<?php
require("../config/db.php");
$id = $_GET['id'] ?? 0;

// 1. Obtener Cabecera
$stmt = $conn->prepare("SELECT a.*, v.no_serie, v.marca, v.modelo, u.nombre as auditor 
                        FROM auditorias_vehiculos_aud a 
                        JOIN vehiculos_aud v ON a.vehiculo_id = v.id 
                        JOIN usuarios u ON a.usuario_id = u.id 
                        WHERE a.id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$auditoria = $stmt->get_result()->fetch_assoc();

if (!$auditoria) die("Auditoría no encontrada");

// 2. Obtener Detalles (Respuestas)
$detalles = $conn->query("SELECT d.*, c.pregunta 
                          FROM auditorias_detalle_aud d 
                          JOIN auditorias_conceptos_aud c ON d.concepto_id = c.id 
                          WHERE d.auditoria_id = $id");
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte - <?= $auditoria['folio'] ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        @media print { .no-print { display: none; } }
        .table-custom th { background-color: #f8f9fa; }
    </style>
</head>
<body class="p-4">
    <div class="container border p-4 bg-white">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="mb-0">REPORTE DE AUDITORÍA</h2>
                <span class="badge bg-primary">Folio: <?= $auditoria['folio'] ?></span>
            </div>
            <button class="btn btn-danger no-print" onclick="window.print()">
                <i class="fas fa-file-pdf"></i> Imprimir / Guardar PDF
            </button>
        </div>

        <div class="row mb-4">
            <div class="col-6">
                <table class="table table-sm table-bordered">
                    <tr><th>Fecha:</th><td><?= $auditoria['fecha_auditoria'] ?></td></tr>
                    <tr><th>Vehículo:</th><td><?= $auditoria['marca'] ?> <?= $auditoria['modelo'] ?></td></tr>
                    <tr><th>Serie:</th><td><?= $auditoria['no_serie'] ?></td></tr>
                </table>
            </div>
            <div class="col-6">
                <div class="card text-center bg-light">
                    <div class="card-body">
                        <h6 class="card-title text-muted">CALIFICACIÓN TOTAL</h6>
                        <h1 class="display-4 fw-bold text-success"><?= $auditoria['calif_total'] ?>/100</h1>
                    </div>
                </div>
            </div>
        </div>

        <h5>Resultado del Checklist</h5>
        <table class="table table-striped table-sm border">
            <thead>
                <tr>
                    <th>Concepto</th>
                    <th>Valor</th>
                    <th class="text-center">Puntos</th>
                </tr>
            </thead>
            <tbody>
                <?php while($row = $detalles->fetch_assoc()): ?>
                <tr>
                    <td><?= $row['pregunta'] ?></td>
                    <td><?= $row['valor_seleccionado'] ?></td>
                    <td class="text-center"><?= $row['puntos_obtenidos'] ?></td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
        
        <div class="mt-4">
            <h6>Observaciones del Auditor:</h6>
            <p class="border p-2 bg-light"><?= $auditoria['observaciones'] ?: 'Sin observaciones.' ?></p>
        </div>
    </div>
</body>
</html>