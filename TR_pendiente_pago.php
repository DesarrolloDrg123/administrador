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
    $sql = 'SELECT
        -- Usamos MIN(t.id) para obtener un ID estable para cualquier enlace al detalle
        MIN(t.id) AS id, 
        t.folio,
        
        -- Sumamos los importes de todas las transferencias que comparten el folio
        SUM(t.importe) AS importe, 
        SUM(t.importedls) AS importedls,
    
        -- Para los dem®¢s campos, tomamos el valor de una de las filas del grupo (usualmente el primero o el ®≤ltimo)
        MAX(b.beneficiario) AS beneficiario,
        MAX(u.nombre) AS nombre,
        MAX(t.fecha_solicitud) AS fecha_solicitud,
        MAX(t.fecha_autorizacion) AS fecha_autorizacion,
        MAX(t.no_cuenta) AS no_cuenta,
        MAX(t.descripcion) AS descripcion,
        MAX(t.importe_letra) AS importe_letra,
        MAX(t.importedls_letra) AS importedls_letra,
        MAX(s.sucursal) AS sucursal, -- OJO: Mostrar®¢ solo el nombre de una de las sucursales
        MAX(t.documento_adjunto) AS documento_adjunto,
        MAX(t.notas) AS notas,
        MAX(t.recibo) AS recibo,
        MAX(t.estado) AS estado -- Asumimos que el estado es el mismo para todo el folio
    FROM 
        transferencias t
    JOIN 
        usuarios u ON t.usuario_id = u.id
    JOIN 
        sucursales s ON t.sucursal_id = s.id
    JOIN 
        beneficiarios b ON t.beneficiario_id = b.id
    WHERE 
        t.estado NOT IN ("Pagado", "Pendiente", "Rechazado", "Cancelada")
    GROUP BY 
        t.folio -- La instrucci®Æn clave para agrupar
    ORDER BY 
        t.fecha_autorizacion ASC';
        
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
        <h1 class="mb-4">Transferencias Pendientes por Pagar</h1>
        
        

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
        
        <!-- Filtro Estado -->
        <div class="col-md-2 mb-2">
            <label for="filtro_estado">Estatus:</label>
            <select id="filtro_estado" name="estado" class="form-control">
                <option value="">Todas</option>
                <?php
                $sql_estado = "SELECT DISTINCT estado FROM transferencias WHERE estado NOT IN ('Pagado', 'Pendiente', 'Rechazado', 'Cancelada') ";
                $result_estado = $conn->query($sql_estado);
                while ($row = $result_estado->fetch_assoc()) {
                    echo '<option value="'.$row['estado'].'"'.(isset($_GET['estado']) && $_GET['estado'] == $row['estado'] ? ' selected' : '').'>'.$row['estado'].'</option>';
                }
                ?>
            </select>
        </div>

        <?php if ($result->num_rows > 0): ?>
            <div class="table-responsive">
                <table class="table table-striped table-hover align-middle" id="solicitudesTable">
                    <thead class="table-dark text-center">
                        <tr>
                            <th>Folio</th>
                            <th>Fecha de Solcitud</th>
                            <th>Fecha de Autorizacion</th>
                            <th>Solicita</th>
                            <th>Beneficiario</th>
                            <th>Importe</th>
                            <th>Importe Con Letra</th>
                            <th>Descripcion</th>
                            <th>No. de Cuenta</th>
                            <th>Estatus</th>
                            <th>Archivo Adjunto</th>
                            <th>Recibo</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($fila = $result->fetch_assoc()):
                            $fecha_formateada = (new DateTime($fila['fecha_solicitud']))->format('d/m/Y');
                            if ($fila['fecha_autorizacion'] == "0000-00-00" || $fila['fecha_autorizacion'] == "")
                            {
                                $fecha_formateada_auto = (new DateTime())->format('d/m/Y');
                            } else {
                                
                                $fecha_formateada_auto = (new DateTime($fila['fecha_autorizacion']))->format('d/m/Y');
                            }
                            
                            $folio_class = !empty($fila['notas']) ? 'text-danger fw-bold' : '';
                            $importe = $fila['importe'] ?: $fila['importedls'];
                            $importe_letra = $fila['importedls'] == 0 ? $fila['importe_letra'] : 'Importe en DLS';
                        ?>
                        <tr class="text-center align-middle">
                            <td><a href="TR_edit_transferencia.php?id=<?= htmlspecialchars($fila['id']) ?>&PT=true" class="<?= $folio_class ?>"><?= htmlspecialchars($fila['folio']) ?></a></td>
                            <td><?= $fecha_formateada ?></td>
                            <td><?= $fecha_formateada_auto ?></td>
                            <td><?= htmlspecialchars($fila['nombre']) ?></td>
                            <td><?= htmlspecialchars($fila['beneficiario']) ?></td>
                            <td>$<?= number_format($importe, 2, '.', ',') ?></td>
                            <td><?= htmlspecialchars($importe_letra) ?></td>
                            <td><?= htmlspecialchars($fila['descripcion']) ?></td>
                            <td><?= !empty($fila['no_cuenta']) ? htmlspecialchars($fila['no_cuenta']) : 'N/A' ?></td>
                            <td><?= htmlspecialchars($fila['estado']) ?></td>
        
                            <!-- Documento Adjunto -->
                            <td>
                                <?php if (!empty($fila['documento_adjunto'])): ?>
                                    <a href="<?= htmlspecialchars($fila['documento_adjunto']) ?>" target="_blank" class="btn btn-outline-primary btn-sm" title="Ver Adjunto">
                                        <i class="fas fa-file-alt fa-3x"></i>
                                    </a>
                                <?php else: ?>
                                    <span class="text-muted">N/A</span>
                                <?php endif; ?>
                            </td>
        
                            <!-- Recibo -->
                            <td>
                                <?php if (!empty($fila['recibo'])): ?>
                                    <a href="<?= htmlspecialchars($fila['recibo']) ?>" target="_blank" class="btn btn-outline-secondary btn-sm" title="Ver Recibo">
                                        <i class="fas fa-file-download fa-3x"></i>
                                    </a>
                                <?php else: ?>
                                    <a href="#" onclick="openUploadReciboModal(<?= $fila['id'] ?>);" class="btn btn-outline-primary btn-sm" title="Subir Recibo">
                                        <i class="fas fa-file-upload fa-3x"></i>
                                    </a>
                                <?php endif; ?>
                            </td>
        
                            <!-- Acciones -->
                            <td>
                                <?php if ($fila['estado'] === 'Aprobado'): ?>
                                    <a href="TR_controller/subir_pago.php?id=<?= $fila['folio'] ?>" class="btn btn-primary btn-sm">Subir a Pago</a>
                                <?php elseif ($fila['estado'] === 'Subido a Pago'): ?>
                                    <a href="TR_controller/pagar.php?id=<?= $fila['id'] ?>" class="btn btn-success btn-sm">Pagar</a>
                                <?php else: ?>
                                    <span class="text-muted">Sin Acciones</span>
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
            "responsive": true,  // Habilitar la respuesta en diferentes tama√±os de pantalla
            "processing": true,
            "columnDefs": [
                { "orderable": false, "targets": [10 ,11, 12] } // Ajustar seg√∫n la posici√≥n real de las columnas
            ]
        });
        // üîπ Filtrar por Estado
        $('#filtro_estado').on('change', function() {
            var filtroEstado = $(this).val();
            if (filtroEstado) {
                table.column(9).search($('#filtro_estado option:selected').text()).draw();
            } else {
                table.column(9).search('').draw();
            }
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
            document.getElementById('reciboFile').value = ''; // Limpiar la selecci√≥n si no es un PDF
        }
    });

    // Manejar el env√≠o del formulario para subir el recibo
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
                    location.reload(); // Recargar la p√°gina para reflejar el cambio
                } else {
                    alert('Error al subir el recibo.');
                }
            };
            xhr.send(formData);
        }
    });
        
    // Funci√≥n para abrir el modal de subir recibo con el ID de la transferencia
    function openUploadReciboModal(transferenciaId) {
        document.getElementById('id_trans').value = transferenciaId; // Almacena el ID de la transferencia para su uso posterior
        $('#uploadReciboModal').modal('show');
    }
</script>

<?php include("src/templates/adminfooter.php"); ?>