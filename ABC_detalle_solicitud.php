<?php
session_start();
if (!isset($_SESSION['loggedin']) || !$_SESSION['loggedin']) {
    header("Location: index.php");
    exit();
}

require("config/db.php");
include("src/templates/adminheader.php");

// 1. Validar y obtener el ID de la solicitud desde la URL
if (!isset($_GET['doc']) || !is_numeric($_GET['doc'])) {
    die("<div class='container mt-4'><div class='alert alert-danger'>Error: No se proporcionó un ID de solicitud válido.</div></div>");
}
$solicitud_id = intval($_GET['doc']);

// 2. Consulta SQL mejorada para obtener todos los detalles
$sql = "SELECT 
            mp.*,
            suc.sucursal as nombre_sucursal,
            puesto_alta.puesto as nombre_puesto_alta,
            puesto_ant.puesto as nombre_puesto_anterior,
            puesto_nue.puesto as nombre_puesto_nuevo,
            usuario_c.nombre as nombre_usuario_cambio,
            usuario_b.nombre as nombre_usuario_baja,
            respaldo.nombre as nombre_usuario_respaldo
        FROM 
            solicitudes_movimientos_personal mp
        LEFT JOIN sucursales suc ON mp.sucursal_id = suc.id
        LEFT JOIN puestos puesto_alta ON mp.puesto_id = puesto_alta.id
        LEFT JOIN puestos puesto_ant ON mp.puesto_anterior_id = puesto_ant.id
        LEFT JOIN puestos puesto_nue ON mp.puesto_nuevo_id = puesto_nue.id
        LEFT JOIN usuarios usuario_c ON mp.usuario_cambio_id = usuario_c.id
        LEFT JOIN usuarios usuario_b ON mp.usuario_baja_id = usuario_b.id
        LEFT JOIN usuarios respaldo ON mp.colaborador_respaldo_id = respaldo.id
        WHERE 
            mp.id = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $solicitud_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("<div class='container mt-4'><div class='alert alert-danger'>Error: La solicitud con el ID proporcionado no fue encontrada.</div></div>");
}

$solicitud = $result->fetch_assoc();
?>

<style>
    .card { border-radius: 0.75rem; border: none; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
    .detail-section { display: none; } /* Ocultar todas las secciones por defecto */
    .section-title { font-size: 1.2rem; font-weight: 600; color: #0056b3; border-bottom: 2px solid #0056b3; padding-bottom: 0.5rem; margin-top: 1.5rem; margin-bottom: 1rem; }
    .detail-group { margin-bottom: 1rem; }
    .detail-label { font-weight: bold; color: #495057; }
    .detail-value { color: #212529; }
</style>

<div class="container mt-4">
    <div class="card">
        <div class="card-body p-4 p-md-5">
            
            <!-- Encabezado -->
            <h2 class="text-center mb-4">Detalle de la Solicitud</h2>
            <div class="row mb-4 align-items-center">
                <div class="col-md-6">
                    <h5>Folio: <span class="text-danger fw-bold"><?= htmlspecialchars($solicitud['folio']) ?></span></h5>
                    <p class="mb-0"><strong>Fecha de Solicitud:</strong> <?= date('d/m/Y', strtotime($solicitud['fecha_solicitud'])) ?></p>
                </div>
                <div class="col-md-6 text-md-end">
                    <p class="mb-0"><strong>Solicitante:</strong> <?= htmlspecialchars($solicitud['solicitante']) ?></p>
                    <p class="mb-0"><strong>Estatus Actual:</strong> <span class="badge bg-primary"><?= htmlspecialchars($solicitud['estatus']) ?></span></p>
                </div>
            </div>

            <!-- Contenedor para las secciones dinámicas -->
            <div>
                <!-- SECCIÓN: ALTA / REMPLAZO / PRACTICANTE -->
                <div id="section-alta" class="detail-section">
                    <h3 class="section-title"><?= htmlspecialchars($solicitud['tipo_solicitud']) ?> - <?= htmlspecialchars($solicitud['codigo_form']) ?> </h3>
                    <div class="row">
                        <div class="col-md-12 detail-group" id="detalle-reemplazo" style="display:none;">
                            <div class="detail-label">Usuario a Reemplazar:</div>
                            <div class="detail-value"><?= htmlspecialchars($solicitud['usuario_a_reemplazar_info'] ?? 'N/A') ?></div>
                        </div>
                        <div class="col-md-6 detail-group"><div class="detail-label">Nombre Completo:</div><div class="detail-value"><?= htmlspecialchars(trim(($solicitud['nombres'] ?? '') . ' ' . ($solicitud['apellido_paterno'] ?? '') . ' ' . ($solicitud['apellido_materno'] ?? ''))) ?></div></div>
                        <div class="col-md-6 detail-group"><div class="detail-label">Nombre Predilecto:</div><div class="detail-value"><?= htmlspecialchars($solicitud['nombre_predilecto'] ?? 'N/A') ?></div></div>
                        <div class="col-md-6 detail-group"><div class="detail-label">Sucursal:</div><div class="detail-value"><?= htmlspecialchars($solicitud['nombre_sucursal'] ?? 'No asignada') ?></div></div>
                        <div class="col-md-6 detail-group"><div class="detail-label">Puesto Asignado:</div><div class="detail-value"><?= htmlspecialchars($solicitud['nombre_puesto_alta'] ?? 'N/A') ?></div></div>
                        <div class="col-md-6 detail-group"><div class="detail-label">No. de Empleado:</div><div class="detail-value"><?= htmlspecialchars($solicitud['numero_empleado'] ?? 'N/A') ?></div></div>
                        <div class="col-md-6 detail-group"><div class="detail-label">Fecha de Ingreso:</div><div class="detail-value"><?= !empty($solicitud['fecha_ingreso']) ? date('d/m/Y', strtotime($solicitud['fecha_ingreso'])) : 'N/A' ?></div></div>
                        <div class="col-md-6 detail-group"><div class="detail-label">Teléfono:</div><div class="detail-value"><?= htmlspecialchars($solicitud['telefono'] ?? 'N/A') ?></div></div>
                        <div class="col-md-6 detail-group"><div class="detail-label">Fecha de Nacimiento:</div><div class="detail-value"><?= !empty($solicitud['fecha_nacimiento']) ? date('d/m/Y', strtotime($solicitud['fecha_nacimiento'])) : 'N/A' ?></div></div>
                        <div class="col-md-12 detail-group"><div class="detail-label">Dirección:</div><div class="detail-value"><?= htmlspecialchars($solicitud['direccion'] ?? 'N/A') ?></div></div>
                        <div class="col-md-12 detail-group" id="detalle-practicante" style="display:none;">
                            <div class="detail-label">Actividades del Practicante:</div>
                            <div class="detail-value"><?= nl2br(htmlspecialchars($solicitud['actividades_practicante'] ?? 'N/A')) ?></div>
                        </div>
                    </div>
                </div>

                <!-- SECCIÓN: CAMBIO DE PUESTO -->
                <div id="section-cambio" class="detail-section">
                    <h3 class="section-title"><?= htmlspecialchars($solicitud['tipo_solicitud']) ?> - <?= htmlspecialchars($solicitud['codigo_form']) ?></h3>
                    <div class="row">
                        <div class="col-md-12 detail-group"><div class="detail-label">Colaborador:</div><div class="detail-value"><?= htmlspecialchars($solicitud['nombre_usuario_cambio'] ?? 'N/A') ?></div></div>
                        <div class="col-md-6 detail-group"><div class="detail-label">Puesto Anterior:</div><div class="detail-value"><?= htmlspecialchars($solicitud['nombre_puesto_anterior'] ?? 'N/A') ?></div></div>
                        <div class="col-md-6 detail-group"><div class="detail-label">Puesto Nuevo:</div><div class="detail-value"><?= htmlspecialchars($solicitud['nombre_puesto_nuevo'] ?? 'N/A') ?></div></div>
                        <div class="col-md-12 detail-group"><div class="detail-label">Justificación del Cambio:</div><div class="detail-value"><?= nl2br(htmlspecialchars($solicitud['justificacion'] ?? 'N/A')) ?></div></div>
                    </div>
                </div>

                <!-- SECCIÓN: BAJA -->
                <div id="section-baja" class="detail-section">
                    <h3 class="section-title"><?= htmlspecialchars($solicitud['tipo_solicitud']) ?> - <?= htmlspecialchars($solicitud['codigo_form']) ?></h3>
                     <div class="row">
                        <div class="col-md-8 detail-group"><div class="detail-label">Usuario a dar de Baja:</div><div class="detail-value"><?= htmlspecialchars($solicitud['nombre_usuario_baja'] ?? 'N/A') ?></div></div>
                        <div class="col-md-4 detail-group"><div class="detail-label">¿Es Foráneo?:</div><div class="detail-value"><?= ($solicitud['es_foraneo'] ?? 0) ? 'Sí' : 'No' ?></div></div>
                        <div class="col-md-12 detail-group"><div class="detail-label">Asignar Respaldo a:</div><div class="detail-value"><?= htmlspecialchars($solicitud['nombre_usuario_respaldo'] ?? 'N/A') ?></div></div>
                        <div class="col-md-12 detail-group"><div class="detail-label">Colaborador que Recoge Productos:</div><div class="detail-value"><?= htmlspecialchars($solicitud['colaborador_recoge_productos'] ?? 'N/A') ?></div></div>
                        <div class="col-md-12 detail-group"><div class="detail-label">¿Baja por Reemplazo?:</div><div class="detail-value"><?= ($solicitud['es_baja_por_reemplazo'] ?? 0) ? 'Sí' : 'No' ?></div></div>
                    </div>
                </div>

                <!-- Observaciones (si existen) -->
                <?php if (!empty($solicitud['observaciones'])): ?>
                    <h3 class="section-title">Observaciones Adicionales</h3>
                    <div class="detail-group">
                        <div class="detail-value"><?= nl2br(htmlspecialchars($solicitud['observaciones'])) ?></div>
                    </div>
                <?php endif; ?>

                <!-- Archivo Adjunto (si existe) -->
                <?php if (!empty($solicitud['archivo_evidencia_path'])): ?>
                    <h3 class="section-title">Archivo Adjunto</h3>
                    <a href="../<?= htmlspecialchars($solicitud['archivo_evidencia_path']) ?>" target="_blank" class="btn btn-info">
                        <i class="fas fa-file-download me-2"></i> Ver Archivo Adjunto
                    </a>
                <?php endif; ?>
            </div>
            
            <div class="mt-4 pt-4 border-top text-center">
                <button onclick="history.back()" class="btn btn-secondary">Regresar</button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Se usa una variable PHP para inyectar el tipo de solicitud de forma segura
    const tipoSolicitud = <?= json_encode($solicitud['tipo_solicitud']) ?>;
    
    // CORRECCIÓN: Se reordena la lógica para verificar los casos más específicos primero.
    if (tipoSolicitud.includes('cambio')) { // 'cambio' es más específico que 'Alta'
        document.getElementById('section-cambio').style.display = 'block';
    } else if (tipoSolicitud.includes('Baja')) {
        document.getElementById('section-baja').style.display = 'block';
    } else if (tipoSolicitud.includes('Alta') || tipoSolicitud.includes('Practicante') || tipoSolicitud.includes('remplazo')) {
        document.getElementById('section-alta').style.display = 'block';
        if (tipoSolicitud.includes('remplazo')) {
            document.getElementById('detalle-reemplazo').style.display = 'block';
        }
        if (tipoSolicitud.includes('Practicante')) {
            document.getElementById('detalle-practicante').style.display = 'block';
        }
    }
});
</script>

<?php
include("src/templates/adminfooter.php");
?>
</body>
</html>

