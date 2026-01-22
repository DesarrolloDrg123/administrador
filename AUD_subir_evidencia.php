<?php
require("config/db.php");
$token = $_GET['t'] ?? '';

// Validar que el token exista
$stmt = $conn->prepare("SELECT a.id, a.folio, v.no_serie FROM auditorias_vehiculos_aud a JOIN vehiculos v ON a.vehiculo_id = v.id WHERE a.token_evidencia = ?");
$stmt->bind_param("s", $token);
$stmt->execute();
$auditoria = $stmt->get_result()->fetch_assoc();

if (!$auditoria) {
    die("Enlace no válido o expirado.");
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Subir Evidencias</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container mt-5">
    <div class="card shadow">
        <div class="card-header bg-success text-white">
            <h5>Subir Evidencia - Folio: <?= $auditoria['folio'] ?> (Serie: <?= $auditoria['no_serie'] ?>)</h5>
        </div>
        <div class="card-body">
            <form action="AUD_controller/guardar_fotos.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="auditoria_id" value="<?= $auditoria['id'] ?>">
                
                <div class="mb-3">
                    <label class="form-label fw-bold">Fotos del Vehículo (Exterior/Interior)</label>
                    <input type="file" name="fotos[]" class="form-control" multiple accept="image/*" required>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-bold">Documentos Digitales (PDF/Escaneos)</label>
                    <input type="file" name="documentos[]" class="form-control" multiple accept=".pdf,.doc,.docx">
                </div>

                <button type="submit" class="btn btn-primary w-100">Enviar Evidencias al Auditor</button>
            </form>
        </div>
    </div>
</div>

<?php if (isset($_GET['status']) && $_GET['status'] == 'success'): ?>
    <div class="container mt-5">
        <div class="alert alert-success shadow text-center">
            <i class="bi bi-check-circle-fill fs-1"></i>
            <h4 class="mt-3">¡Evidencias Subidas con Éxito!</h4>
            <p>La información del folio <strong><?= $_GET['folio'] ?></strong> ha sido enviada al auditor.</p>
            <p class="mb-0">Ya puede cerrar esta ventana.</p>
        </div>
    </div>
<?php exit(); endif; ?>
</body>
</html>