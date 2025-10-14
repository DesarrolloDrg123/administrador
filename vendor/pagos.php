<?php
session_start();
require "config/db.php";
include "src/templates/header.php";

if (!isset($_SESSION['loggedin']) || !$_SESSION['loggedin']) {
    header("Location: index.php");
    exit();
}

$usuario_id = $_SESSION['usuario_id'];

try {
    // Número de resultados por página
    $limit = 10;

    // Número de página actual
    $page = isset($_GET['page']) ? intval($_GET['page']) : 1;

    // Calcular el offset para la consulta SQL
    $offset = ($page - 1) * $limit;

    // Obtener la cadena de búsqueda si está presente
    $search = isset($_GET['search']) ? $_GET['search'] : '';

    // Construir la consulta SQL para contar los resultados totales
    $sql_count = 'SELECT COUNT(*) AS total FROM transferencias t
                  JOIN usuarios u ON t.usuario_id = u.id
                  JOIN sucursales s ON t.sucursal_id = s.id
                  JOIN beneficiarios b ON t.beneficiario_id = b.id
                  WHERE (t.folio LIKE ? 
                  OR u.nombre LIKE ?
                  OR b.beneficiario LIKE ?
                  OR t.descripcion LIKE ?
                  OR t.importe_letra LIKE ?
                  OR s.sucursal LIKE ?
                  OR t.estado LIKE ?)
                  AND t.estado != "Pagado"';

    $stmt_count = $conn->prepare($sql_count);
    $search_param = '%' . $search . '%';
    $stmt_count->bind_param('sssssss', $search_param, $search_param, $search_param, $search_param, $search_param, $search_param, $search_param);
    $stmt_count->execute();
    $result_count = $stmt_count->get_result();
    $total_solicitudes = $result_count->fetch_assoc()['total'];

    // Obtener todas las solicitudes con limit y offset para la paginación
    $sql = 'SELECT t.id, t.folio, t.no_cuenta, t.importe_letra, t.documento_adjunto, t.recibo, t.importedls, t.importedls_letra, s.sucursal, b.beneficiario, t.fecha_solicitud, t.importe, t.descripcion, t.estado, t.usuario_id, u.nombre
    FROM transferencias t
    JOIN usuarios u ON t.usuario_id = u.id
    JOIN sucursales s ON t.sucursal_id = s.id
    JOIN beneficiarios b ON t.beneficiario_id = b.id
    WHERE (t.folio LIKE ? 
    OR u.nombre LIKE ?
    OR b.beneficiario LIKE ?
    OR t.descripcion LIKE ?
    OR t.importe_letra LIKE ?
    OR s.sucursal LIKE ?
    OR t.estado LIKE ?)
    AND t.estado NOT IN ("Pagado", "Pendiente")
    ORDER BY t.folio DESC
    LIMIT ? OFFSET ?';

    $stmt = $conn->prepare($sql);
    $stmt->bind_param('sssssssii', $search_param, $search_param, $search_param, $search_param, $search_param, $search_param, $search_param, $limit, $offset);
    $stmt->execute();
    $result = $stmt->get_result();
    $solicitudes = $result->fetch_all(MYSQLI_ASSOC);

    // Formatear las fechas
    $fmt = new IntlDateFormatter('es_ES', IntlDateFormatter::LONG, IntlDateFormatter::NONE);
    foreach ($solicitudes as &$solicitud) {
        $fecha = new DateTime($solicitud['fecha_solicitud']);
        $solicitud['fecha_solicitud'] = $fmt->format($fecha);
    }
    unset($solicitud);
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
    exit();
}
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Solicitudes</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <style>
        .table {
            font-size: 14px;
        }

        .table th,
        .table td {
            padding: 10px;
            text-align: center;
        }
    </style>
</head>

<body>
    <div class="container-fluid mt-5">
        <h1 class="mb-4">Mis Transferencias</h1>

        <?php if (isset($_SESSION['alert_message'])): ?>
            <div class="alert alert-<?php echo $_SESSION['alert_type']; ?> alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($_SESSION['alert_message']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php
            unset($_SESSION['alert_message']);
            unset($_SESSION['alert_type']);
            ?>
        <?php endif; ?>

        <!-- Formulario de búsqueda -->
        <form method="GET" action="">
            <div class="form-group">
                <label for="search">Buscar transferencia:</label>
                <input type="text" name="search" class="form-control" id="search" value="<?php echo htmlspecialchars(isset($_GET['search']) ? $_GET['search'] : ''); ?>">
            </div>
            <button type="submit" class="btn btn-primary">Buscar</button>
        </form>

        <!-- Tabla de solicitudes aprobadas -->
        <h2>Solicitudes Aprobadas</h2>
        <?php if (count($solicitudes) > 0): ?>
            <table class="table table-striped table-bordered" id="solicitudesTable">
                <thead>
                    <tr>
                        <th>Folio</th>
                        <th>Fecha</th>
                        <th>Solicita</th>
                        <th>Beneficiario</th>
                        <th>Importe</th>
                        <th>Importe Con Letra</th>
                        <th>Descripción</th>
                        <th>Status</th>
                        <th>Archivo Adjunto</th>
                        <th>Recibo</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($solicitudes as $solicitud): ?>

                        <tr>
                            <td><?php echo htmlspecialchars($solicitud['folio']); ?></td>
                            <td><?php echo htmlspecialchars($solicitud['fecha_solicitud']); ?></td>
                            <td><?php echo htmlspecialchars($solicitud['nombre']); ?></td>
                            <td><?php echo htmlspecialchars($solicitud['beneficiario']); ?></td>
                            
                            <?php if ($solicitud['importedls'] == 0) : ?>
                            <td>$<?php echo number_format($solicitud['importe'], 2, ".", ","); ?></td>
                            <?php else: ?>
                            <td>$<?php echo number_format($solicitud['importedls']); ?></td>
                            <?php endif ?>


                            <?php if ($solicitud['importedls'] == 0) : ?>
                            <td><?php echo htmlspecialchars($solicitud['importe_letra']); ?></td>
                            <?php else: ?>
                            <td>$<?php echo htmlspecialchars($solicitud['importedls_letra']); ?></td>
                            <?php endif ?>



                            <td><?php echo htmlspecialchars($solicitud['descripcion']); ?></td>
                            <td class="p-3"><?php echo htmlspecialchars($solicitud['estado']); ?></td>
                            <td>
                                <?php if (!empty($solicitud['documento_adjunto'])): ?>
                                    <a href="<?php echo htmlspecialchars($solicitud['documento_adjunto']); ?>" class="btn btn-primary btn-sm" target="_blank">Ver Adjunto</a>
                                <?php else: ?>
                                    Sin Archivo
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($solicitud['recibo'])): ?>
                                    <a href="<?php echo htmlspecialchars($solicitud['recibo']); ?>" class="btn btn-info btn-sm" target="_blank">Ver Recibo</a>
                                <?php else: ?>
                                    <button class="btn btn-primary btn-sm" onclick="openUploadReciboModal(<?php echo $solicitud['id']; ?>);">Subir Recibo</button>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($solicitud['estado'] === 'Aprobado'): ?>
                                    <a href="subir_pago.php?id=<?php echo $solicitud['id']; ?>" class="btn btn-primary btn-sm">Subir a Pago</a>
                                <?php elseif ($solicitud['estado'] === 'Subido a Pago'): ?>
                                    <a href="pagar.php?id=<?php echo $solicitud['id']; ?>" class="btn btn-success btn-sm">Pagar</a>
                                <?php else: ?>
                                    Sin Acciones
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>

                </tbody>
            </table>

            <!-- Paginación -->
            <nav aria-label="Page navigation example">
                <ul class="pagination justify-content-center">
                    <?php
                    $total_pages = ceil($total_solicitudes / $limit);
                    for ($i = 1; $i <= $total_pages; $i++):
                        $active = $i == $page ? 'active' : '';
                    ?>
                        <li class="page-item <?php echo $active; ?>">
                            <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>"><?php echo $i; ?></a>
                        </li>
                    <?php endfor; ?>
                </ul>
            </nav>
        <?php else: ?>
            <p>No hay solicitudes aprobadas pendientes.</p>
        <?php endif; ?>
    </div>

    <!-- Modal para subir recibo -->
    <div class="modal fade" id="uploadReciboModal" tabindex="-1" aria-labelledby="uploadReciboModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="uploadReciboModalLabel">Subir Recibo</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <form id="uploadReciboForm">
                        <div class="form-group">
                            <label for="reciboFile">Seleccionar archivo PDF</label>
                            <input type="file" class="form-control-file" id="reciboFile" accept="application/pdf" required>
                        </div>
                        <div class="form-group">
                            <label for="pdfPreview">Vista previa del PDF</label>
                            <iframe id="pdfPreview" style="width: 100%; height: 400px;" frameborder="0"></iframe>
                        </div>
                        <button type="submit" class="btn btn-primary">Subir Recibo</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.3/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        var currentTransferenciaId = null;

        // Mostrar la vista previa del PDF cuando el usuario selecciona un archivo
        document.getElementById('reciboFile').addEventListener('change', function(event) {
            var file = event.target.files[0];
            if (file.type === "application/pdf") {
                var fileReader = new FileReader();
                fileReader.onload = function() {
                    document.getElementById('pdfPreview').src = fileReader.result;
                };
                fileReader.readAsDataURL(file);
            } else {
                alert("Por favor, selecciona un archivo PDF.");
                document.getElementById('reciboFile').value = ''; // Limpiar la selección si no es un PDF
            }
        });

        // Manejar el envío del formulario para subir el recibo
        document.getElementById('uploadReciboForm').addEventListener('submit', function(event) {
            event.preventDefault();
            var formData = new FormData();
            var fileInput = document.getElementById('reciboFile');
            var file = fileInput.files[0];

            if (file) {
                formData.append('recibo', file);
                formData.append('id', currentTransferenciaId); // Agrega el ID de la transferencia en el formulario

                var xhr = new XMLHttpRequest();
                xhr.open('POST', 'subir_recibo.php', true);
                xhr.onload = function() {
                    if (xhr.status === 200) {
                        alert('Recibo subido exitosamente.');
                        location.reload(); // Recargar la página para reflejar el cambio
                    } else {
                        alert('Error al subir el recibo.');
                    }
                };
                xhr.send(formData);
            }
        });

        // Función para abrir el modal de subir recibo con el ID de la transferencia
        function openUploadReciboModal(transferenciaId) {
            currentTransferenciaId = transferenciaId; // Almacena el ID de la transferencia para su uso posterior
            $('#uploadReciboModal').modal('show');
        }
    </script>
</body>

</html>

<?php
$conn->close();
include 'src/templates/footer.php';
?>
