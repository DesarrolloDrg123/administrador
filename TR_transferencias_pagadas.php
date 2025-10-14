<?php
session_start();
require("config/db.php");
include("src/templates/adminheader.php");

if (!isset($_SESSION['loggedin']) || !$_SESSION['loggedin']) {
    header("Location: index.php");
    exit();
}

$usuario_id = $_SESSION['usuario_id'];

try {
    // Consulta SQL para obtener todos los registros sin filtros
    $sql = 'SELECT t.id AS id_transferencia, t.folio AS folio_transferencia, t.no_cuenta, t.importe_letra, t.documento_adjunto, 
            t.recibo, t.sucursal_id, s.sucursal, b.beneficiario, t.fecha_solicitud, t.importe, t.descripcion, t.estado, t.usuario_id, u.nombre
            FROM transferencias t
            JOIN usuarios u ON t.usuario_id = u.id
            JOIN sucursales s ON t.sucursal_id = s.id
            JOIN beneficiarios b ON t.beneficiario_id = b.id
            WHERE t.estado IN ("Pagado")
            ORDER BY t.folio DESC';

    $stmt = $conn->prepare($sql);
        
    $stmt->execute();
    $result = $stmt->get_result();
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
    exit();
}
?>

<style>
    .table thead {
        background-color: #343a40;
        color: #fff;
    }
    .table {
        background-color: #ffffff;
        border-radius: 10px;
        overflow: hidden;
    }
    
    .table th {
        background-color: #333;
        color: #ffffff;
        padding: 10px;
        border-bottom: 1px solid #3498db;
    }
    
    .table td {
        padding: 10px;
        border-bottom: 1px solid #dddddd;
    }
    h1 {
        color: #2c3e50;
        border-bottom: 2px solid #3498db;
        padding-bottom: 10px;
    }
</style>

<div class="container-fluid mt-5">
    <h1 class="mb-4">Transferencias Pagadas</h1>
    <h6 class="mb-4 text-muted">
        <span>
            <span class="badge bg-danger">&nbsp;</span> Folios en <strong>rojo</strong> sin facturas adjuntas
        </span>
    </h6>
    
    <?php if ($result->num_rows > 0): ?>
        <div class="table-responsive">
            <table class="table table-striped table-hover align-middle" id="solicitudesTable">
                <thead class="table-dark text-center">
                    <tr>
                        <th>Folio</th>
                        <th>Fecha</th>
                        <th>Solicita</th>
                        <th>Beneficiario</th>
                        <th>Importe</th>
                        <th>Importe Con Letra</th>
                        <th>Descripción</th>
                        <th>Sucursal</th>
                        <th>Archivo Adjunto</th>
                        <th>Recibo</th>
                        <th>Cambiar Recibo</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($solicitud = $result->fetch_assoc()):
                        $fecha_formateada = (new DateTime($solicitud['fecha_solicitud']))->format('d/m/Y');
    
                        // Verificar si hay facturas relacionadas
                        $sql_factura_relacionada = "SELECT 1 FROM facturas WHERE NO_ORDEN_COMPRA = ? LIMIT 1";
                        $stmt_factura = $conn->prepare($sql_factura_relacionada);
                        $stmt_factura->bind_param('s', $solicitud['folio_transferencia']);
                        $stmt_factura->execute();
                        $result_factura = $stmt_factura->get_result();
                        $stmt_factura->close();
    
                        $folio_class = ($result_factura->num_rows > 0) ? 'text-success fw-bold' : 'text-danger fw-bold';
                    ?>
                        <tr class="text-center align-middle">
                            <td>
                                <a href="TR_detalle_t_pagadas.php?folio=<?= htmlspecialchars($solicitud['folio_transferencia']) ?>&TP=true" class="<?= $folio_class ?>">
                                    <?= htmlspecialchars($solicitud['folio_transferencia']) ?>
                                </a>
                            </td>
                            <td><?= $fecha_formateada ?></td>
                            <td><?= htmlspecialchars($solicitud['nombre']) ?></td>
                            <td><?= htmlspecialchars($solicitud['beneficiario']) ?></td>
                            <td>$<?= number_format($solicitud['importe'], 2, ".", ",") ?></td>
                            <td><?= htmlspecialchars($solicitud['importe_letra']) ?></td>
                            <td style="max-width: 200px;"><?= htmlspecialchars($solicitud['descripcion']) ?></td>
                            <td><?= htmlspecialchars($solicitud['sucursal']) ?></td>
    
                            <!-- Documento Adjunto -->
                            <td>
                                <?php if (!empty($solicitud['documento_adjunto'])): ?>
                                    <a href="<?= htmlspecialchars($solicitud['documento_adjunto']) ?>" target="_blank" class="btn btn-outline-primary btn-sm" title="Documento Adjunto">
                                        <i class="fas fa-file-alt fa-3x"></i>
                                    </a>
                                <?php else: ?>
                                    <span class="text-muted">N/A</span>
                                <?php endif; ?>
                            </td>
    
                            <!-- Recibo -->
                            <td>
                                <?php if (!empty($solicitud['recibo'])): ?>
                                    <a href="<?= htmlspecialchars($solicitud['recibo']) ?>" target="_blank" class="btn btn-outline-secondary btn-sm" title="Ver Recibo">
                                        <i class="fas fa-file-download fa-3x"></i>
                                    </a>
                                <?php else: ?>
                                    <a href="#" onclick="openUploadReciboModal(<?= $solicitud['id_transferencia'] ?>);" class="btn btn-outline-primary btn-sm" title="Subir Recibo">
                                        <i class="fas fa-file-upload fa-3x"></i>
                                    </a>

                                <?php endif; ?>
                            </td>
    
                            <!-- Cambiar Recibo -->
                            <td>
                                <?php if (!empty($solicitud['recibo'])): ?>
                                    <a href="#" onclick="openUploadReciboModal(<?= $solicitud['id_transferencia'] ?>);" class="btn btn-outline-primary btn-sm" title="Cambiar Recibo">
                                        <i class="fas fa-file-upload fa-3x"></i>
                                    </a>

                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <p class="text-center text-muted">No hay solicitudes aprobadas pendientes.</p>
    <?php endif; ?>


</div>

<!-- Modal para subir recibo -->
    <div class="modal fade" id="uploadReciboModal" tabindex="-1" aria-labelledby="uploadReciboModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content" style="width:700px; text-align:center;">
                <div class="modal-header">
                    <h5 class="modal-title" id="uploadReciboModalLabel">Subir Recibo</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="uploadReciboForm">
                        <div class="form-group">
                            <label for="reciboFile">Seleccionar archivo PDF</label>
                            <input type="file" class="form-control-file" id="reciboFile" accept="application/pdf" required>
                            
                        </div>
                        <input type="hidden" id="id_trans" name="id_trans">
                        <div class="form-group">
                            <iframe id="pdfPreview" style="width: 500px; height: 700px;" frameborder="0"></iframe>
                        </div>
                        <button type="submit" class="btn btn-primary">Subir Recibo</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

<script>
    $(document).ready(function() {
        var table = $('#solicitudesTable').DataTable({
            "language": {
                "url": "//cdn.datatables.net/plug-ins/1.10.16/i18n/Spanish.json"
            },
            "pageLength": 10,
            "lengthMenu": [5, 10, 20],
            "responsive": true,  // Habilitar la respuesta en diferentes tamaños de pantalla
            "processing": true,
            "order": [[0, "desc"]],
            "columnDefs": [
                { "orderable": false, "targets": [8, 9,10] } // Ajustar según la posición real de las columnas
            ]
        });
    });
</script>
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
        var id_trans = document.getElementById('id_trans').value; // Asegurar que se obtiene el valor correcto
    
        if (file) {
            formData.append('recibo', file);
            formData.append('id_trans', id_trans); // Nombre correcto en el formulario
    
            var xhr = new XMLHttpRequest();
            xhr.open('POST', 'TR_controller/subir_recibo.php', true);
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
        document.getElementById('id_trans').value = transferenciaId; // Almacena el ID de la transferencia para su uso posterior
        $('#uploadReciboModal').modal('show');
    }
</script>

<?php
$conn->close();
include("src/templates/adminfooter.php");
?>
