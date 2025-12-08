<?php
session_start();
require("config/db.php");
include("src/templates/adminheader.php");

if (!isset($_SESSION['loggedin']) || !$_SESSION['loggedin']) {
    header("Location: index.php");
    exit();
}

$usuario_id = $_SESSION['usuario_id'];

$where = [];
$where[] = "(t.estado = 'Pagado' OR t.estado = 'Subido a pago')";
$where_sql = " WHERE " . implode(" AND ", $where);

try {

$sql = "SELECT 
            MIN(t.id) AS id, 
            t.folio,

            CASE 
                WHEN COUNT(t.folio) > 1 THEN 'Corporativo' 
                ELSE MAX(s.sucursal) 
            END AS sucursal,

            SUM(COALESCE(t.importe, 0)) AS importe, 
            SUM(COALESCE(t.importedls, 0)) AS importedls,

            MAX(b.nombre) AS beneficiario,
            MAX(d.departamento) AS departamento,
            MAX(c.categoria) AS categoria,

            MAX(u2.nombre) AS nombre_solicita,
            MAX(u.nombre) AS autorizacion_nombre,

            MAX(t.fecha_solicitud) AS fecha_solicitud,
            MAX(t.fecha_autorizacion) AS fecha_autorizacion,
            MAX(t.no_cuenta) AS no_cuenta,
            MAX(t.importe_letra) AS importe_letra,
            MAX(t.importedls_letra) AS importedls_letra,
            MAX(t.tipo_cambio) AS tipo_cambio,
            MAX(t.descripcion) AS descripcion,
            MAX(t.observaciones) AS observaciones,
            MAX(t.estado) AS estado,
            MAX(t.documento_adjunto) AS documento_adjunto,
            MAX(t.recibo) AS recibo,

        FROM transferencias_clara_tcl t
        JOIN usuarios b ON t.beneficiario_id = b.id
        JOIN sucursales s ON t.sucursal_id = s.id
        JOIN departamentos d ON t.departamento_id = d.id
        JOIN categorias c ON t.categoria_id = c.id
        JOIN usuarios u ON t.autorizacion_id = u.id
        JOIN usuarios u2 ON t.usuario_solicitante_id = u2.id
        $where_sql
        GROUP BY t.folio
        ORDER BY MAX(t.fecha_autorizacion) ASC";

    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $result = $stmt->get_result();

} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
    exit();
}
?>

<style>
.table thead { background-color: #343a40; color: #fff; }
.table { background-color: #ffffff; border-radius: 10px; overflow: hidden; }
.table th { background-color: #333; color: #ffffff; padding: 10px; border-bottom: 1px solid #3498db; }
.table td { padding: 10px; border-bottom: 1px solid #dddddd; }
h1 { color: #2c3e50; border-bottom: 2px solid #3498db; padding-bottom: 10px; }
</style>

<div class="container-fluid mt-5">
    <h1 class="mb-4">Transferencias Pendientes por Pagar</h1>

    <!-- Filtro Estado -->
    <div class="col-md-2 mb-2">
        <label for="filtro_estado">Estatus:</label>
        <select id="filtro_estado" name="estado" class="form-control">
            <option value="">Todas</option>
            <?php
                $sql_estado = "SELECT DISTINCT estado FROM transferencias_clara_tcl 
                               WHERE estado NOT IN ('Pagado', 'Pendiente', 'Rechazado', 'Cancelada')";
                $result_estado = $conn->query($sql_estado);
                while ($row = $result_estado->fetch_assoc()) {
                    echo '<option value="'.$row['estado'].'">'.$row['estado'].'</option>';
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
                    <th>Fecha Solicitud</th>
                    <th>Fecha Autorización</th>
                    <th>Solicita</th>
                    <th>Beneficiario</th>
                    <th>Importe</th>
                    <th>Importe con letra</th>
                    <th>Descripción</th>
                    <th>No. Cuenta</th>
                    <th>Estatus</th>
                    <th>Archivo</th>
                    <th>Recibo</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($fila = $result->fetch_assoc()):
                    $fecha_solicitud = (new DateTime($fila['fecha_solicitud']))->format('d/m/Y');

                    $fecha_autorizacion = (!empty($fila['fecha_autorizacion']) && $fila['fecha_autorizacion'] != '0000-00-00')
                        ? (new DateTime($fila['fecha_autorizacion']))->format('d/m/Y')
                        : 'N/A';

                    $importe = ($fila['importedls'] > 0) ? $fila['importedls'] : $fila['importe'];
                    $importe_letra = ($fila['importedls'] > 0) ? 'Importe en USD' : $fila['importe_letra'];
                ?>
                <tr class="text-center align-middle">
                    <td>
                        <a href="TR_edit_transferencia.php?id=<?= htmlspecialchars($fila['id']) ?>&PT=true" class="<?= $folio_class ?>">
                            <?= htmlspecialchars($fila['folio']) ?>
                        </a>
                    </td>
                    <td><?= $fecha_solicitud ?></td>
                    <td><?= $fecha_autorizacion ?></td>
                    <td><?= htmlspecialchars($fila['nombre_solicita']) ?></td>
                    <td><?= htmlspecialchars($fila['beneficiario']) ?></td>
                    <td>$<?= number_format($importe, 2, '.', ',') ?></td>
                    <td><?= htmlspecialchars($importe_letra) ?></td>
                    <td><?= htmlspecialchars($fila['descripcion']) ?></td>
                    <td><?= !empty($fila['no_cuenta']) ? htmlspecialchars($fila['no_cuenta']) : 'N/A' ?></td>
                    <td><?= htmlspecialchars($fila['estado']) ?></td>

                    <td>
                        <?php if (!empty($fila['documento_adjunto'])): ?>
                            <a href="<?= htmlspecialchars($fila['documento_adjunto']) ?>" target="_blank" class="btn btn-outline-primary btn-sm">
                                <i class="fas fa-file-alt fa-2x"></i>
                            </a>
                        <?php else: ?>
                            <span class="text-muted">N/A</span>
                        <?php endif; ?>
                    </td>

                    <td>
                        <?php if (!empty($fila['recibo'])): ?>
                            <a href="<?= htmlspecialchars($fila['recibo']) ?>" target="_blank" class="btn btn-outline-secondary btn-sm">
                                <i class="fas fa-file-download fa-2x"></i>
                            </a>
                        <?php else: ?>
                            <a href="#" onclick="openUploadReciboModal(<?= $fila['id'] ?>);" class="btn btn-outline-primary btn-sm">
                                <i class="fas fa-file-upload fa-2x"></i>
                            </a>
                        <?php endif; ?>
                    </td>

                    <td>
                        <?php if ($fila['estado'] === 'Aprobado'): ?>
                            <a href="TR_controller/subir_pago.php?id=<?= htmlspecialchars($fila['folio']) ?>" class="btn btn-primary btn-sm">Subir a Pago</a>
                        <?php elseif ($fila['estado'] === 'Subido a pago'): ?>
                            <a href="TR_controller/pagar.php?id=<?= htmlspecialchars($fila['id']) ?>" class="btn btn-success btn-sm">Pagar</a>
                        <?php else: ?>
                            <span class="text-muted">Sin acciones</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
        <p class="text-center text-muted">No hay solicitudes.</p>
    <?php endif; ?>
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
            "columnDefs": [
                { "orderable": false, "targets": [10 ,11, 12] } // Ajustar según la posición real de las columnas
            ]
        });
        // 🔹 Filtrar por Estado
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

<?php include("src/templates/adminfooter.php"); ?>