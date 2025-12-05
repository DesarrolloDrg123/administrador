<?php
include("src/templates/adminheader.php");
require("config/db.php");
include('TCL_controller/upload_files.php');


if (!isset($_SESSION['loggedin']) || !$_SESSION['loggedin']) {
    header("Location: index.php");
    exit();
}
$usuario_ses = $_SESSION['nombre'];
if (!isset($_GET['id'])) {
    echo "Id no proporcionado.";
    exit();
}

$MTParam = isset($_GET['MT']) ? $_GET['MT'] : null;
$ATParam = isset($_GET['AT']) ? $_GET['AT'] : null;

$solicitud_id = $_GET['id'];

try {
    $sql = 'SELECT t.id, t.folio, s.sucursal AS sucursal, b.nombre AS nombre_beneficiario, t.fecha_solicitud, t.fecha_vencimiento, t.importe,t.importe_letra, t.importedls, t.importedls_letra, t.descripcion, t.estado, t.documento_adjunto, t.no_cuenta,
    t.observaciones, t.categoria_id, t.departamento_id, t.usuario_solicitante_id, t.autorizacion_id AS autoriza, t.motivo, u1.nombre AS nombre_usuario, u2.nombre AS nombre_autoriza, d.departamento, c.categoria
    FROM transferencias_clara_tcl t 
    JOIN categorias c ON t.categoria_id = c.id
    JOIN departamentos d ON t.departamento_id = d.id
    JOIN usuarios u1 ON t.usuario_solicitante_id = u1.id
    JOIN usuarios b ON t.beneficiario_id = b.id
    JOIN sucursales s ON t.sucursal_id = s.id
    JOIN usuarios u2 ON t.autorizacion_id = u2.id
    WHERE t.id = ?';

    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        throw new Exception($conn->error);
    }

    $stmt->bind_param('i', $solicitud_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $solicitud = $result->fetch_assoc();

    if (!$solicitud) {
        echo "No se encontr�� la solicitud o no tienes permiso para verla.";
        exit();
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
    exit();
}

// Extraer la fecha de la base de datos
$fecha = new DateTime($solicitud['fecha_solicitud']);
$fecha1 = new DateTime($solicitud['fecha_vencimiento']);

// Meses en espa�0�9ol abreviados
$meses_espanol = ['ENE', 'FEB', 'MAR', 'ABR', 'MAY', 'JUN', 'JUL', 'AGO', 'SEP', 'OCT', 'NOV', 'DIC'];

// Obtener el d��a, el mes (como ��ndice) y el a�0�9o
$dia = $fecha->format('j');
$mes = $meses_espanol[(int)$fecha->format('n') - 1];
$a�0�9o = $fecha->format('Y');

// Obtener el d��a, el mes (como ��ndice) y el a�0�9o
$dia1 = $fecha1->format('j');
$mes1 = $meses_espanol[(int)$fecha1->format('n') - 1];
$a�0�9o1 = $fecha1->format('Y');

// Concatenar en el formato deseado
$fecha_formateada = "{$dia}/{$mes}/{$a�0�9o}";
$fecha_formateada1 = "{$dia1}/{$mes1}/{$a�0�9o1}";
?>

<?php
$folio = $solicitud['folio'];

// Consulta para obtener todos los registros de las facturas
$sql_facturas = "SELECT * FROM facturas WHERE NO_ORDEN_COMPRA = ?";
$stmt2 = $conn->prepare($sql_facturas);
$stmt2->bind_param('s', $folio);
$stmt2->execute();
$result_facturas = $stmt2->get_result();

// Consulta para obtener la suma de los totales de las facturas
$sql_total_facturas = "SELECT SUM(TOTAL) AS total_facturas FROM facturas_tcl WHERE NO_ORDEN_COMPRA = ?";
$stmt3 = $conn->prepare($sql_total_facturas);
$stmt3->bind_param('s', $folio);
$stmt3->execute();
$result_total_facturas = $stmt3->get_result();

// Obtener el total de las facturas
$row_total_facturas = $result_total_facturas->fetch_assoc();
$total_facturas = $row_total_facturas['total_facturas'] ?? 0; // Si no hay resultados, asigna 0

if ($solicitud['importe'] == '0.00' || $solicitud['importe'] == null || $solicitud['importe'] == '') {
    $importe_transferencia = $solicitud['importedls'];
} else {
    $importe_transferencia = $solicitud['importe'];
}

$pendiente = $importe_transferencia - $total_facturas;

?>
<style>
.container {
    max-width: 1600px;
}
h2.section-title {
    color: #2c3e50;
    border-bottom: 2px solid #3498db;
    padding-bottom: 8px;
    margin-bottom: 20px;
}
td {
    vertical-align: middle;
}
.table th {
    background-color: #f8f9fa;
    color: #2c3e50;
}
.card {
    box-shadow: 0 0 15px rgba(0,0,0,0.05);
}
.card-header {
    background-color: #f0f8ff;
    font-weight: bold;
}
</style>

<div class="container">
    <?php if (isset($GLOBALS["mensaje_global"])) echo $GLOBALS["mensaje_global"]; ?>
    <div class="row">
        <!-- DETALLE DE TRANSFERENCIA -->
        <div class="col-md-6 mx-auto">
            <h2 class="section-title"><i class="fas fa-file-invoice-dollar"></i> Detalle de Transferencia</h2>
            <div class="card mb-4">
                <div class="card-body">
                    <table class="table table-bordered table-hover">
                        <tbody>
                            <tr><th>Folio</th><td class="text-danger fw-bold"><?= htmlspecialchars($solicitud['folio']) ?></td></tr>
                            <tr><th>Sucursal</th><td><?= htmlspecialchars($solicitud['sucursal']) ?></td></tr>
                            <tr><th>Solicitante</th><td><?= htmlspecialchars($solicitud['nombre_usuario']) ?></td></tr>
                            <tr><th>Beneficiario</th><td><?= htmlspecialchars($solicitud['beneficiario']) ?></td></tr>
                            <tr><th>No. de Cuenta</th><td><?= !empty($solicitud['no_cuenta']) ? htmlspecialchars($solicitud['no_cuenta']) : 'N/A' ?></td></tr>
                            <tr><th>Departamento</th><td><?= htmlspecialchars($solicitud['departamento']) ?></td></tr>
                            <tr><th>Categoria</th><td><?= htmlspecialchars($solicitud['categoria']) ?></td></tr>
                            <tr><th>Fecha de Solicitud</th><td><?= htmlspecialchars($fecha_formateada) ?></td></tr>
                            <tr><th>Fecha de Vencimiento</th><td><?= htmlspecialchars($fecha_formateada1) ?></td></tr>
                            <tr><th>Descripcion</th><td><?= htmlspecialchars($solicitud['descripcion']) ?></td></tr>
                            <tr><th>Observaciones</th><td><?= !empty($solicitud['observaciones']) ? htmlspecialchars($solicitud['observaciones']) : 'N/A' ?></td></tr>
                            <tr><th>Estado</th><td><?= htmlspecialchars($solicitud['estado']) ?></td></tr>
                            <?php if ($solicitud['estado'] == "Cancelada" || $solicitud['estado'] == "Rechazado"): ?>
                                <tr>
                                    <tr><th>Motivo</th>
                                    <td><?= htmlspecialchars($solicitud['motivo']) ?></td></tr>
                                </tr>
                            <?php endif; ?>
                            <tr><th>Autoriza</th><td><?= htmlspecialchars($solicitud['nombre_autoriza']) ?></td></tr>
                            <?php if (empty($solicitud['importe']) || $solicitud['importe'] == '0.00'): ?>
                                <tr><th>Importe en D��lares</th><td>US$<?= number_format($solicitud['importedls'], 2, ".", ",") ?></td></tr>
                                <tr><th>Importe en Letra</th><td><?= htmlspecialchars($solicitud['importedls_letra']) ?></td></tr>
                            <?php else: ?>
                                <tr><th>Importe en Pesos</th><td>$<?= number_format($solicitud['importe'], 2, ".", ",") ?></td></tr>
                                <tr><th>Importe en Letra</th><td><?= htmlspecialchars($solicitud['importe_letra']) ?></td></tr>
                            <?php endif; ?>
                            <tr><th>Total de Facturas</th><td><?= $importe_factura = ($solicitud['estado'] != "Cancelada" && $solicitud['estado'] != "Rechazado") ? '$' . number_format($total_facturas, 2, ".", ",") : '$0.00'; ?></td></tr>
                            <tr><th>Pendiente por Subir</th><td><?= $importe_pendiente = ($solicitud['estado'] != "Cancelada" && $solicitud['estado'] != "Rechazado") ? '$' . number_format($pendiente, 2, ".", ",") : '$0.00'; ?></td></tr>
                            <?php if (!empty($solicitud['documento_adjunto'])): ?>
                                <tr>
                                    <th>Documento</th>
                                    <td><a href="<?= htmlspecialchars($solicitud['documento_adjunto']) ?>" target="_blank" class="btn btn-outline-primary btn-sm">Ver Documento</a></td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>

                    <!-- Botones de Acci��n -->
                    <div class="d-flex gap-2 mt-3">
                        <?php if ($solicitud['estado'] === 'Pendiente' && $usuario_ses === $solicitud['nombre_usuario']) : ?>
                            <a href="TCL_edit_transfer.php?id=<?= $solicitud_id ?>&MT=true" class="btn btn-warning">Editar Transferencia</a>
                        <?php endif; ?>
                        <?php if ($MTParam === 'true'): ?>
                            <a href="TCL_mis_transferencias.php" class="btn btn-secondary">Volver</a>
                        <?php endif; ?>
                        <?php if ($ATParam === 'true'): ?>
                            <a href="TCL_por_autorizar.php" class="btn btn-secondary">Volver</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- FACTURAS Y FORMULARIO DE CARGA -->
        <?php if ($solicitud['estado'] != "Pendiente" && $solicitud['estado'] != "Rechazado" && $solicitud['estado'] != "Cancelado"): ?>
        <div class="col-md-6">
            <h2 class="section-title"><i class="fas fa-receipt"></i> Cargar Facturas</h2>
            <div class="card mb-4">
                <div class="card-body">
                    <form action="" method="POST" name="formularioFacturas" id="formularioFacturas" enctype="multipart/form-data">
                        <div class='form-row'>
                            <h5 class='form-group col-md-6' >Archivo PDF: </h5>
                            <h5 class='form-group col-md-6' >Archivo XML: </h5>
                        </div>
                        <div class="nuevosCampos"></div>
                        <button type="submit" name="submit" class="btn btn-warning mt-3">Cargar Factura(s)</button>
                    </form>
                </div>
            </div>
            <?php if ($result_facturas->num_rows > 0): ?>
                <h2 class="section-title"><i class="fas fa-file-alt"></i> Facturas Subidas</h2>
                <div class="card">
                    <div class="card-body">
                        <table class="table table-sm table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>Fecha</th>
                                    <th>RFC</th>
                                    <th>Total</th>
                                    <th>UUID</th>
                                    <th>Ver</th>
                                    <th>Descargar</th>
                                    <th>Reiniciar</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $fmt = new IntlDateFormatter('es_ES', IntlDateFormatter::LONG, IntlDateFormatter::NONE);
                                while ($row_factura = $result_facturas->fetch_assoc()):
                                    $fecha_factura = new DateTime($row_factura['FECHA_FACTURA']);
                                    $fecha_factura_formateada = $fmt->format($fecha_factura);
                                ?>
                                <tr>
                                    <td><?= htmlspecialchars($fecha_factura_formateada) ?></td>
                                    <td><?= htmlspecialchars($row_factura['RFC_EMISOR']) ?></td>
                                    <td>$<?= number_format($row_factura['TOTAL'], 2, ".", ",") ?></td>
                                    <td><?= htmlspecialchars($row_factura['UUID']) ?></td>
                                    <td><a href="view_pdf.php?RFC=<?= $row_factura["RFC_EMISOR"] ?>&UUID=<?= $row_factura["UUID"] ?>" target="_blank"><i class="fas fa-file-invoice fa-2x"></i></a></td>
                                    <td><a href="download_zip.php?RFC=<?= $row_factura["RFC_EMISOR"] ?>&UUID=<?= $row_factura["UUID"] ?>" target="_blank"><i class="fas fa-file-archive fa-2x"></i></a></td>
                                    <td><button class="btn btn-link p-0" onclick="abrirModalReset('<?= $row_factura["UUID"] ?>')"><i class="fas fa-trash-restore fa-2x text-danger"></i></button></td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</div>



<!-- Modal de Confirmaci��n para Reset -->
<div class="modal fade" id="modalResetFactura" tabindex="-1" role="dialog" aria-labelledby="modalResetLabel" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Reiniciar Factura</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="uuidInput">
        <div class="form-group">
          <label for="descripcion">Motivo del reinicio</label>
          <textarea id="descripcionInput" class="form-control" rows="3" required placeholder="Escribe el motivo..."></textarea>
        </div>
        <div id="respuestaReset" class="text-danger mt-2"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
        <button type="button" class="btn btn-danger" onclick="confirmarReset()">Confirmar Reinicio</button>
      </div>
    </div>
  </div>
</div>

<script>
  function abrirModalReset(uuid) {
    $('#uuidInput').val(uuid);
    $('#descripcionInput').val('');
    $('#respuestaReset').text('');
    $('#modalResetFactura').modal('show');
  }

  function confirmarReset() {
    const uuid = $('#uuidInput').val();
    const descripcion = $('#descripcionInput').val();

    if (descripcion.trim() === '') {
      $('#respuestaReset').text('Por favor escribe un motivo.');
      return;
    }

    $.ajax({
      url: 'TCL_controller/factura_reset.php',
      method: 'POST',
      data: {
        UUID: uuid,
        descripcion: descripcion
      },
      success: function (respuesta) {
        if (respuesta.trim() === 'success') {
          $('#modalResetFactura').modal('hide');
          alert('Factura reiniciada correctamente.');
          location.reload(); // Opcional: recarga tabla para ver cambios
        } else {
          $('#respuestaReset').text('Ocurri�� un error al reiniciar.');
        }
      },
      error: function () {
        $('#respuestaReset').text('Error de comunicaci��n con el servidor.');
      }
    });
  }
</script>

<script type="text/javascript">
     formularioFacturas = document.querySelector('#formularioFacturas');
     formularioFacturas.ordenCompra.addEventListener('keypress', function (e){
        if (!soloNumeros(event)){
        e.preventDefault();

      }
    });
     formularioFacturas.folioFactura.addEventListener('keypress', function (e){
        if (!soloLetras(event)){
        e.preventDefault();

      }
    });
    setTimeout(function(){
	  if ($('#mensajes_globales').length > 0) {
	    $('#mensajes_globales').remove();
	    $("#mensajes_globales").html('');
	  }
	}, 5000);
	setTimeout(function(){
	  if ($('#mensajes_pdf').length > 0 && $('#mensajes_pdf').text()!="Seleccione el archivo pdf:") {
	    //$('#mensajes_pdf').remove();
	    $("#mensajes_pdf").html('<h5>Seleccione el archivo pdf:</h5>');
	  }
	}, 5000);
	setTimeout(function(){
	  if ($('#mensajes_xml').length > 0 && $('#mensajes_xml').text()!="Seleccione el archivo xml:") {
	    //$('#mensajes_xml').remove();
	    $("#mensajes_xml").html('<h5>Seleccione el archivo xml:</h5>');
	  }
	}, 5000);
    //mensaje_global = document.byid('#mensajes_globales');
    //alert($( "#mensajes_globales" ).text());
//          $('mensajes_globales').contentchanged() {
	//   alert('changed');
	// }
</script>

<script>
// Funci��n para agregar din��micamente el bloque de HTML
function agregarBloqueHTML() {
var fileSection = document.querySelector('.nuevosCampos');

// Verificar si ya existen campos vac��os
var existingRows = fileSection.querySelectorAll('.form-row');
for (var i = 0; i < existingRows.length; i++) {
    var pdfInput = existingRows[i].querySelector('input[name="file_pdf[]"]');
    var xmlInput = existingRows[i].querySelector('input[name="file_xml[]"]');
    if (pdfInput.files.length === 0 || xmlInput.files.length === 0) {
        // Ya existe un bloque con campos vac��os, no agregar otro
        return;
    }
}

// Crear el bloque de HTML
var div = document.createElement('div');
div.classList.add('form-row');

const folio = "<?php echo $solicitud['folio']; ?>";


// Agregar el contenido al bloque
div.innerHTML += `
  <input type="file" name="file_pdf[]" class=" form-control col-md-6" accept=".pdf" />
  <input type="file" name="file_xml[]" class=" form-control col-md-6" accept=".xml" />
  <input type="hidden" name="ordenCompra[]" value="${folio}">
`;


// Agregar el bloque al formulario
fileSection.appendChild(div);

// Obtener el ��ltimo bloque de formulario agregado
var lastFormRow = fileSection.lastElementChild;

// Obtener los campos de archivo dentro del ��ltimo bloque de formulario
var fileInputs = lastFormRow.querySelectorAll('input[type="file"]');

// Escuchar el evento change en los campos de archivo dentro del ��ltimo bloque de formulario
fileInputs.forEach(function(input) {
    input.addEventListener('change', function() {
        // Verificar si ambos campos de archivo tienen archivos seleccionados
        var pdfInput = lastFormRow.querySelector('input[name="file_pdf[]"]');
        var xmlInput = lastFormRow.querySelector('input[name="file_xml[]"]');
        if (pdfInput.files.length > 0 && xmlInput.files.length > 0) {
            // Agregar otro bloque de formulario
            agregarBloqueHTML();
        }
    });
});
}

// Llamar a la funci��n para agregar el bloque de HTML inicial
agregarBloqueHTML();
setTimeout(function(){
	  if ($('#mensajes_globales').length > 0) {
	    $('#mensajes_globales').remove();
	    $("#mensajes_globales").html('');
	    $('#file_pdf_cp').value = '';
	    $('#file_xml_cp').value = '';
	    // document.querySelector('#file_xml_cp').value = '';
	  }
	}, 5000);
</script>

<?php
include("src/templates/adminfooter.php");
?>