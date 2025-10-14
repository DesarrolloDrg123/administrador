<?php
require("config/db.php");
include("src/templates/adminheader.php");

// Variable para almacenar el mensaje de la notificación
$mensaje_notificacion = null;

// --- VALIDACIÓN DE SESIÓN ---
if (!isset($_SESSION['loggedin']) || !$_SESSION['loggedin']) {
    header("Location: index.php");
    exit();
}
/*
if (!isset($_SESSION['permisos'][4])) { //Permiso de Solicitar vacaciones
    header("Location: inicio.php");
    exit();
}*/

if (!isset($_SESSION['nombre'])) {
    header("Location: index.php");
    exit();
}

$usuario = $_SESSION['nombre'];

// --- FUNCIÓN PARA CALCULAR DÍAS HÁBILES ---
// Es mejor definirla al inicio para que esté disponible en todo el script.
function calcular_dias_habiles($fecha_inicio, $fecha_fin, $feriados = []) {
    $inicio = new DateTime($fecha_inicio);
    $fin = new DateTime($fecha_fin);
    $fin->modify('+1 day'); // Incluir el último día en el rango

    $periodo = new DatePeriod($inicio, new DateInterval('P1D'), $fin);
    $dias_habiles = 0;

    foreach ($periodo as $dia) {
        $dia_semana = $dia->format('N'); // 1 (Lunes) a 7 (Domingo)
        $fecha_actual_str = $dia->format('Y-m-d');

        // Contar solo si no es Sábado (6), Domingo (7) y no está en la lista de feriados
        if ($dia_semana < 6 && !in_array($fecha_actual_str, $feriados)) {
            $dias_habiles++;
        }
    }
    return $dias_habiles;
}


// --- LÓGICA DE PROCESAMIENTO DE FORMULARIOS (POST) ---

// Proceso para cancelar una solicitud
if (isset($_POST['eliminar_id'])) {
    $solicitud_id = $_POST['eliminar_id'];
    $sql_update_cancelar = "UPDATE solicitudes_vacaciones SET estatus = 'cancelada' WHERE id = ?";
    $stmt = $conn->prepare($sql_update_cancelar);

    if ($stmt) {
        $stmt->bind_param("i", $solicitud_id);
        if ($stmt->execute()) {
            $mensaje_notificacion = "La solicitud ha sido cancelada exitosamente.";
        } else {
            $mensaje_notificacion = "Error al intentar cancelar la solicitud.";
        }
        $stmt->close();
    } else {
        $mensaje_notificacion = "Error en la consulta para cancelar la solicitud.";
    }
}

// Proceso para "pagar" una solicitud y descontar días
// Proceso para "pagar" una solicitud y descontar días
if (isset($_POST['pagar_id'])) {
    $solicitud_id = $_POST['pagar_id'];
    $conn->begin_transaction();

    try {
        // --- OBTENER DATOS DE LA SOLICITUD (INCLUYENDO LOS DÍAS YA GUARDADOS) ---
        $sql_select = "SELECT usuario_id, dias_solicitados FROM solicitudes_vacaciones WHERE id = ?";
        $stmt_sol = $conn->prepare($sql_select);
        $stmt_sol->bind_param("i", $solicitud_id);
        $stmt_sol->execute();
        $solicitud = $stmt_sol->get_result()->fetch_assoc();
        
        if (!$solicitud) {
            throw new Exception("Error: La solicitud no fue encontrada.");
        }
        
        $usuario_id = $solicitud['usuario_id'];
        // --- USAR LOS DÍAS YA GUARDADOS EN LA BASE DE DATOS ---
        $dias_solicitados = $solicitud['dias_solicitados'];

        // --- OBTENER Y VALIDAR PERIODOS DEL USUARIO ---
        $sql_periodos = "SELECT id, dias_restantes FROM periodos WHERE usuario_id = ? AND dias_restantes > 0 ORDER BY vigencia ASC";
        $stmt_per = $conn->prepare($sql_periodos);
        $stmt_per->bind_param("i", $usuario_id);
        $stmt_per->execute();
        $result_periodos = $stmt_per->get_result();

        $periodos_disponibles = $result_periodos->fetch_all(MYSQLI_ASSOC);
        $dias_disponibles_total = array_sum(array_column($periodos_disponibles, 'dias_restantes'));

        if ($dias_disponibles_total < $dias_solicitados) {
            throw new Exception("Error: No hay suficientes días disponibles en los periodos del usuario.");
        }

        // --- DESCONTAR DÍAS DE LOS PERIODOS ---
        $dias_a_descontar = $dias_solicitados;
        foreach ($periodos_disponibles as $periodo) {
            if ($dias_a_descontar <= 0) break;
        
            $dias_a_tomar_de_este_periodo = min($periodo['dias_restantes'], $dias_a_descontar);
        
            // Como 'dias_restantes' es una columna generada, solo actualizamos 'dias_disfrutados'
            $sql_update_periodo = "UPDATE periodos SET dias_disfrutados = dias_disfrutados + ? WHERE id = ?";
            $stmt_update = $conn->prepare($sql_update_periodo);
            $stmt_update->bind_param("ii", $dias_a_tomar_de_este_periodo, $periodo['id']);
            $stmt_update->execute();
        
            $dias_a_descontar -= $dias_a_tomar_de_este_periodo;
        }

        // --- ACTUALIZAR ESTADO DE LA SOLICITUD A 'PAGADO' ---
        $sql_update_solicitud = "UPDATE solicitudes_vacaciones SET estatus = 'pagado' WHERE id = ?";
        $stmt_final = $conn->prepare($sql_update_solicitud);
        $stmt_final->bind_param("i", $solicitud_id);
        $stmt_final->execute();

        $conn->commit();
        $mensaje_notificacion = "Solicitud 'pagada' y días descontados de los periodos correctamente.";

    } catch (Exception $e) {
        $conn->rollback();
        $mensaje_notificacion = $e->getMessage();
    }
}

// --- LÓGICA PARA MOSTRAR LA TABLA ---
$sql = "
    SELECT 
        sv.id as solicitud_id,
        sv.usuario_id,
        sv.fecha_inicio,
        sv.fecha_fin,
        sv.dias_solicitados,
        sv.fecha_solicitud,
        sv.estatus, 
        u.nombre,
        u.num_empleado
    FROM 
        solicitudes_vacaciones sv
    JOIN 
        usuarios u ON sv.usuario_id = u.id
    WHERE
        sv.estatus IN ('aprobada', 'pagado')
    ORDER BY 
        sv.fecha_solicitud DESC";
$stmt = $conn->prepare($sql);
$stmt->execute();
$result = $stmt->get_result();
?>

<style>
    body {
        font-family: Arial, sans-serif;
        background-color: #f8f9fa;
        margin: 0;
        padding: 0;
        background-image: url('../img/drg3.png');
        background-size: cover;
        background-attachment: fixed;
        background-position: center;
        background-repeat: no-repeat;
    }
    .container {
        padding: 40px;
        max-width: 1000px;
        margin: auto;
    }
    .jumbotron {
        padding: 1rem 1rem;
        margin-bottom: 2rem;
        background-color: #e9ecef;
        border-radius: .9rem;
    }
    .jb1 {
        background-color: #343a40;
        color: white;
        width: 1000px;
    }
    p {
        font-size: 30px;
    }
    .btn {
        font-size: 20px;
    }
    table {
        width: 100%;
        border-collapse: collapse;
    }
    table, th, td {
        border: 1px solid black;
    }
    th, td {
        padding: 10px;
        text-align: center;
    }
    th {
        background-color: #343a40;
        color: white;
    }
    .acciones {
        display: flex;
        border: none;
        justify-content: center;
        gap: 10px;
    }
    .icon {
        padding-top: 0.2em;
    }
</style>

<div class="container">
    <div class="jumbotron jb1">
        <h1>Registros de Vacaciones</h1>
        
        <div>
            <br>
            <?php if ($result->num_rows > 0) : ?>
                <table id="solicitudesTable">
                    <thead>
                        <tr style="text-align:center;">
                            <th>No. Empleado</th>
                            <th>Solicitante</th>
                            <th>Días Solicitados</th>
                            <th>Fecha de Inicio</th>
                            <th>Fecha de Fin</th>
                            <th>Estatus</th>
                            <th>Fecha de Solicitud</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = $result->fetch_assoc()) : ?>
                            <?php
                                $fecha_inicio = DateTime::createFromFormat('Y-m-d', $row['fecha_inicio'])->format('d/m/Y');
                                $fecha_fin = DateTime::createFromFormat('Y-m-d', $row['fecha_fin'])->format('d/m/Y');
                                $fecha_solicitud = DateTime::createFromFormat('Y-m-d H:i:s', $row['fecha_solicitud'])->format('d/m/Y');
                            ?>
                            <tr>
                                <td><?= htmlspecialchars($row['num_empleado']) ?></td>
                                <td><?= htmlspecialchars($row['nombre']) ?></td>
                                <td><?= htmlspecialchars($row['dias_solicitados']) ?></td>
                                <td><?= htmlspecialchars($fecha_inicio) ?></td>
                                <td><?= htmlspecialchars($fecha_fin) ?></td>
                                <td>
                                    <?php 
                                        if ($row['estatus'] === 'aprobada') {
                                            echo 'Autorizada';
                                        } elseif ($row['estatus'] === 'pagado') {
                                            echo 'Pagada';
                                        }
                                    ?>
                                </td>
                                <td><?= htmlspecialchars($fecha_solicitud) ?></td>
                                <td>
                                    <?php if ($row['estatus'] === 'pagado') : ?>
                                        N/A
                                    <?php else : ?>
                                        <div class="acciones">
                                            <form method="POST" style="display:inline-block;">
                                                <input type="hidden" name="pagar_id" value="<?= htmlspecialchars($row['solicitud_id']) ?>">
                                                <button type="submit" class="btn btn-info mr-4" title="Marcar como Pagada"><i class="fas fa-check-circle"></i></button>
                                            </form>
                                            <form method="POST" style="display:inline-block;" onsubmit="return confirm('¿Estás seguro de cancelar este registro?');">
                                                <input type="hidden" name="eliminar_id" value="<?= htmlspecialchars($row['solicitud_id']) ?>">
                                                <button type="submit" class="btn btn-danger icon" title="Cancelar Solicitud"><i class="fas fa-times-circle"></i></button>
                                            </form>
                                        </div>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else : ?>
                <p>No tienes solicitudes pendientes de revisar.</p>
            <?php endif; ?>
        </div>
        <br>
        <a href="inicio.php"><button class="btn btn-info">Volver</button></a>
    </div>
</div>

<div class="modal fade" id="notificationModal" tabindex="-1" aria-labelledby="notificationModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="notificationModalLabel">Notificación</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <?php if (isset($mensaje_notificacion) && $mensaje_notificacion): ?>
                    <?= htmlspecialchars($mensaje_notificacion) ?>
                <?php endif; ?>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    $('#solicitudesTable').DataTable({
        "language": {
            "url": "//cdn.datatables.net/plug-ins/1.10.16/i18n/Spanish.json"
        },
        "pageLength": 10,
        "responsive": true,
        "processing": true,
        "ordering": false
    });
});

<?php if (isset($mensaje_notificacion) && $mensaje_notificacion): ?>
document.addEventListener("DOMContentLoaded", function () {
    var myModal = new bootstrap.Modal(document.getElementById('notificationModal'));
    myModal.show();
});
<?php endif; ?>
</script>

<?php
$conn->close();
include("src/templates/adminfooter.php");
?>