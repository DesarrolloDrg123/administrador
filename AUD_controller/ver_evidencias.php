<?php
require("config/db.php");
$folio = $_GET['folio'] ?? '';

// Obtener las fotos de la tabla de evidencias
$stmt = $conn->prepare("SELECT e.* FROM auditorias_evidencias_aud e 
                        JOIN auditorias_vehiculos_aud a ON e.auditoria_id = a.id 
                        WHERE a.folio = ? AND e.tipo_archivo = 'Foto'");
$stmt->bind_param("s", $folio);
$stmt->execute();
$fotos = $stmt->get_result();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Evidencias - <?= $folio ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light p-4">
    <div class="container">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h3><i class="fas fa-images"></i> Evidencias Fotográficas: <?= $folio ?></h3>
            <a href="historial_auditorias.php" class="btn btn-secondary">Volver al Historial</a>
        </div>

        <div class="row g-3">
            <?php if ($fotos->num_rows > 0): ?>
                <?php while($f = $fotos->fetch_assoc()): ?>
                <div class="col-md-3">
                    <div class="card shadow-sm">
                        <img src="<?= $f['ruta_archivo'] ?>" class="card-img-top" alt="Evidencia" style="height: 200px; object-fit: cover;">
                        <div class="card-footer small text-muted text-center">
                            Subida el: <?= $f['fecha_subida'] ?>
                        </div>
                    </div>
                </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="col-12 text-center py-5">
                    <div class="alert alert-warning">Aún no se han subido evidencias para esta auditoría.</div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>