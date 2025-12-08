<?php
include("src/templates/adminheader.php");
require("config/db.php");
// Se asume que estos controladores están definidos en otro lugar
include('TCL_controller/upload_files.php');
include('TCL_controller/upload_comprobantes.php');


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
$usuario_id = $_SESSION['usuario_id'];

try {
    $sql = 'SELECT t.id, t.folio, s.sucursal AS sucursal, b.nombre AS nombre_beneficiario, t.beneficiario_id, t.fecha_solicitud, t.fecha_vencimiento, t.importe,t.importe_letra, t.importedls, t.importedls_letra, t.descripcion, t.estado, t.documento_adjunto, t.no_cuenta,
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
        echo "No se encontró la solicitud o no tienes permiso para verla.";
        exit();
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
    exit();
}

// Extraer la fecha de la base de datos
$fecha = new DateTime($solicitud['fecha_solicitud']);
$fecha1 = new DateTime($solicitud['fecha_vencimiento']);

// Meses en español abreviados
$meses_espanol = ['ENE', 'FEB', 'MAR', 'ABR', 'MAY', 'JUN', 'JUL', 'AGO', 'SEP', 'OCT', 'NOV', 'DIC'];

// Obtener el día, el mes (como índice) y el año
$dia = $fecha->format('j');
$mes = $meses_espanol[(int)$fecha->format('n') - 1];
$anio = $fecha->format('Y');

// Obtener el día, el mes (como índice) y el año
$dia1 = $fecha1->format('j');
$mes1 = $meses_espanol[(int)$fecha1->format('n') - 1];
$anio1 = $fecha1->format('Y');

// Concatenar en el formato deseado
$fecha_formateada = "{$dia}/{$mes}/{$anio}";
$fecha_formateada1 = "{$dia1}/{$mes1}/{$anio1}";
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

// =================================================================
// 1. NUEVA CONSULTA: Suma de los totales de los comprobantes (recibos)
// =================================================================
$sql_total_comprobantes = "SELECT SUM(importe) AS total_comprobantes FROM comprobantes_tcl WHERE folio = ?";
$stmt_comp_total = $conn->prepare($sql_total_comprobantes);
$stmt_comp_total->bind_param('s', $folio);
$stmt_comp_total->execute();
$result_total_comprobantes = $stmt_comp_total->get_result();

// Obtener el total de los comprobantes
$row_total_comprobantes = $result_total_comprobantes->fetch_assoc();
$total_comprobantes = $row_total_comprobantes['total_comprobantes'] ?? 0; // Si no hay resultados, asigna 0

// Cerrar el statement de la suma de comprobantes
$stmt_comp_total->close();

// Calcular el total COMPROBADO (Facturas + Comprobantes)
$total_comprobado = $total_facturas + $total_comprobantes;

// Define el importe de la transferencia (Pesos o Dólares)
if ($solicitud['importe'] == '0.00' || $solicitud['importe'] == null || $solicitud['importe'] == '') {
    $importe_transferencia = $solicitud['importedls'];
} else {
    $importe_transferencia = $solicitud['importe'];
}

// 2. CÁLCULO DEL PENDIENTE ACTUALIZADO
// Restamos el total comprobado (Facturas + Comprobantes) al importe original.
$pendiente = $importe_transferencia - $total_comprobado;

$sql_comprobantes = "SELECT id, importe, descripcion FROM comprobantes_tcl WHERE folio = ?";
$stmt_comp = $conn->prepare($sql_comprobantes);

if ($stmt_comp) {
    $stmt_comp->bind_param('s', $folio); // Usamos la variable $folio que ya tienes definida
    $stmt_comp->execute();
    $result_comprobantes = $stmt_comp->get_result(); // Esta es la variable que usa el HTML
    $stmt_comp->close();
} else {
    // En caso de error de preparación, inicializa la variable para evitar errores en el HTML
    $result_comprobantes = null;
    error_log("Error al preparar la consulta de comprobantes: " . $conn->error);
}


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
/* Estilos para el campo de archivo en el formulario de facturas */
.file-upload-row .col-md-6 {
    margin-bottom: 15px; /* Espacio entre los bloques de archivo */
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
                            <tr><th>Beneficiario</th><td><?= htmlspecialchars($solicitud['nombre_beneficiario']) ?></td></tr>
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
                                <tr><th>Importe en Dólares</th><td>US$<?= number_format($solicitud['importedls'], 2, ".", ",") ?></td></tr>
                                <tr><th>Importe en Letra</th><td><?= htmlspecialchars($solicitud['importedls_letra']) ?></td></tr>
                            <?php else: ?>
                                <tr><th>Importe en Pesos</th><td>$<?= number_format($solicitud['importe'], 2, ".", ",") ?></td></tr>
                                <tr><th>Importe en Letra</th><td><?= htmlspecialchars($solicitud['importe_letra']) ?></td></tr>
                            <?php endif; ?>
                            <tr><th>Total de Facturas</th><td>$<?= number_format($total_facturas, 2, ".", ",") ?></td></tr>
                            <tr><th>Total de Comprobantes</th><td>$<?= number_format($total_comprobantes, 2, ".", ",") ?></td></tr>
                            <tr><th>Total Comprobado</th><td>$<?= number_format($total_comprobado, 2, ".", ",") ?></td></tr>
                            <tr><th>Pendiente por Subir</th><td><?= ($solicitud['estado'] != "Cancelada" && $solicitud['estado'] != "Rechazado") ? '$' . number_format($pendiente, 2, ".", ",") : '$0.00'; ?></td></tr>
                            <?php if (!empty($solicitud['documento_adjunto'])): ?>
                                <tr>
                                    <th>Documento</th>
                                    <td><a href="<?= htmlspecialchars($solicitud['documento_adjunto']) ?>" target="_blank" class="btn btn-outline-primary btn-sm">Ver Documento</a></td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>

                    <!-- Botones de Acción -->
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
                      <!-- FORMULARIO DE FACTURAS (CFDI) -->
                      <form action="" method="POST" name="formularioFacturas" id="formularioFacturas" enctype="multipart/form-data">
                          <!-- Títulos de los campos -->
                          <div class='row g-2 mb-3'>
                              <div class='col-md-6'>
                                  <h5 class='mb-0'>Archivo PDF: </h5>
                              </div>
                              <div class='col-md-6'>
                                  <h5 class='mb-0'>Archivo XML: </h5>
                              </div>
                          </div>
                          <!-- Contenedor donde JS agregará los campos de archivo -->
                          <div class="nuevosCampos">
                          </div>
                          <button type="submit" name="submit_facturas" class="btn btn-warning mt-3">Cargar Factura(s)</button>
                      </form>
                  </div>
              </div>
              
              <?php if ($result_facturas->num_rows > 0): ?>
                  <h2 class="section-title"><i class="fas fa-file-alt"></i> Facturas Subidas</h2>
                  <!-- Aquí iría la tabla de facturas subidas -->
                  <div class="card mb-4">
                    <div class="card-body">
                      <p>Tabla de facturas subidas va aquí...</p>
                    </div>
                  </div>
              <?php endif; ?>
              
              <hr>
              
              <!-- FORMULARIO DE COMPROBANTES/RECIBOS -->
              <h2 class="section-title"><i class="fas fa-file-invoice-dollar"></i> Cargar Comprobantes/Recibos</h2>
              <div class="card mb-4">
                  <div class="card-body">
                      <form action="" method="POST" name="formularioComprobantes" id="formularioComprobantes" enctype="multipart/form-data">
                          
                          <div class="form-group mb-3">
                              <label for="importe_comprobante">Monto/Importe del Comprobante: *</label>
                              <input type="number" step="0.01" class="form-control" id="importe_comprobante" name="importe_comprobante" required>
                          </div>
                          
                          <div class="form-group mb-3">
                              <label for="descripcion_comprobante">Descripción del Recibo/Gasto: *</label>
                              <textarea class="form-control" id="descripcion_comprobante" name="descripcion_comprobante" rows="2" required></textarea>
                          </div>
                          
                          <div class="form-group mb-3">
                              <label for="evidencia_comprobante">Evidencia (Imagen, Foto, PDF): *</label>
                              <input type="file" class="form-control-file" id="evidencia_comprobante" name="evidencia_comprobante" accept="image/*,.pdf" required>
                          </div>
                          
                          <input type="hidden" name="folio_solicitud" value="<?= htmlspecialchars($solicitud['folio']) ?>">
                          <input type="hidden" name="submit_comprobante" value="1">
                          
                          <button type="submit" name="submit_comprobantes" class="btn btn-primary mt-3">Subir Comprobante</button>
                      </form>
                  </div>
              </div>
              
              <?php if (isset($result_comprobantes) && $result_comprobantes->num_rows > 0): ?>
                  <h2 class="section-title"><i class="fas fa-clipboard-list"></i> Comprobantes/Recibos Subidos</h2>
                  <div class="card mb-4">
                      <div class="card-body">
                          <table class="table table-sm table-striped table-hover">
                              <thead>
                                  <tr>
                                      <th>Importe</th>
                                      <th>Descripción</th>
                                      <th>Evidencia</th>
                                  </tr>
                              </thead>
                              <tbody>
                                  <?php while ($row_comprobante = $result_comprobantes->fetch_assoc()): ?>
                                  <tr>
                                      <td>$<?= number_format($row_comprobante['importe'], 2, ".", ",") ?></td>
                                      <td><?= htmlspecialchars($row_comprobante['descripcion']) ?></td>
                                      <td>
                                          <!-- Asumo que view_evidencia.php maneja la visualización del archivo -->
                                          <a href="view_evidencia.php?id=<?= $row_comprobante['id'] ?>" target="_blank"><i class="fas fa-image fa-2x"></i></a>
                                      </td>
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



<!-- Modal de Confirmación para Reset -->
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
  // NOTA: Tu código usa alert(), que debe evitarse. Usa modales o mensajes en el DOM.
  function abrirModalReset(uuid) {
    $('#uuidInput').val(uuid);
    $('#descripcionInput').val('');
    $('#respuestaReset').text('');
    // Asumo que tienes Bootstrap JS cargado
    var modalReset = new bootstrap.Modal(document.getElementById('modalResetFactura'));
    modalReset.show();
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
          // Usamos la función nativa de Bootstrap para ocultar el modal
          var modalReset = bootstrap.Modal.getInstance(document.getElementById('modalResetFactura'));
          if (modalReset) {
            modalReset.hide();
          }
          // Idealmente se usa un toast o mensaje de éxito, no alert
          alert('Factura reiniciada correctamente.'); 
          location.reload(); // Opcional: recarga tabla para ver cambios
        } else {
          $('#respuestaReset').text('Ocurrió un error al reiniciar. Respuesta: ' + respuesta);
        }
      },
      error: function () {
        $('#respuestaReset').text('Error de comunicación con el servidor.');
      }
    });
  }
</script>

<script type="text/javascript">
    // Tu lógica de validación de formulario, corregida para evitar errores si los campos no existen
    var formularioFacturas = document.querySelector('#formularioFacturas');
    
    // Asumo que soloNumeros y soloLetras existen globalmente
    // if (formularioFacturas) {
    //     if(formularioFacturas.ordenCompra) { // Este campo no existe en el HTML actual, se envía por hidden input
    //         formularioFacturas.ordenCompra.addEventListener('keypress', function (e){
    //             if (!soloNumeros(e)){ e.preventDefault(); }
    //         });
    //     }
    //     if(formularioFacturas.folioFactura) { // Este campo no existe
    //         formularioFacturas.folioFactura.addEventListener('keypress', function (e){
    //             if (!soloLetras(e)){ e.preventDefault(); }
    //         });
    //     }
    // }

    // Bloque de timeouts para limpieza de mensajes
    setTimeout(function(){
        if ($('#mensajes_globales').length > 0) {
            $('#mensajes_globales').remove();
            $("#mensajes_globales").html('');
        }
    }, 5000);
    // Estos timeouts de PDF/XML no aplican a la nueva estructura de campos dinámicos:
    /*
    setTimeout(function(){
        if ($('#mensajes_pdf').length > 0 && $('#mensajes_pdf').text()!="Seleccione el archivo pdf:") {
            $("#mensajes_pdf").html('<h5>Seleccione el archivo pdf:</h5>');
        }
    }, 5000);
    setTimeout(function(){
        if ($('#mensajes_xml').length > 0 && $('#mensajes_xml').text()!="Seleccione el archivo xml:") {
            $("#mensajes_xml").html('<h5>Seleccione el archivo xml:</h5>');
        }
    }, 5000);
    */
</script>

<script>
// =================================================================
// FUNCIÓN CORREGIDA para agregar dinámicamente el bloque de HTML
// Se corrigió la estructura de Bootstrap (uso de div.col-md-6)
// =================================================================
function agregarBloqueHTML() {
    var fileSection = document.querySelector('.nuevosCampos');
    const folio = "<?php echo $solicitud['folio']; ?>";

    // 1. Verificar si ya existen campos de archivo PDF y XML vacíos
    var existingRows = fileSection.querySelectorAll('.file-upload-row');
    for (var i = 0; i < existingRows.length; i++) {
        var pdfInput = existingRows[i].querySelector('input[name="file_pdf[]"]');
        var xmlInput = existingRows[i].querySelector('input[name="file_xml[]"]');
        
        // Si no existe, es que la fila está mal estructurada.
        // Si existe y no tiene archivos seleccionados, detenemos la adición.
        if ((pdfInput && pdfInput.files.length === 0) || (xmlInput && xmlInput.files.length === 0)) {
            return;
        }
    }
    
    // 2. Crear el bloque de HTML con la estructura de Bootstrap correcta
    var div = document.createElement('div');
    // Usamos 'row g-2' para la cuadrícula y una clase única para la fila
    div.classList.add('row', 'g-2', 'file-upload-row');

    // 3. Agregar el contenido al bloque (usando col-md-6 para cada input)
    div.innerHTML = `
        <div class="col-md-6">
            <input type="file" name="file_pdf[]" class="form-control" accept=".pdf" required />
        </div>
        <div class="col-md-6">
            <input type="file" name="file_xml[]" class="form-control" accept=".xml" required />
            <!-- El input hidden va en una de las columnas o fuera del row, pero para array es mejor en la fila -->
            <input type="hidden" name="ordenCompra[]" value="${folio}">
        </div>
    `;

    // 4. Agregar el bloque al formulario
    fileSection.appendChild(div);

    // 5. Obtener el último bloque de formulario agregado para escuchar eventos
    var lastFormRow = fileSection.lastElementChild;
    var fileInputs = lastFormRow.querySelectorAll('input[type="file"]');

    // 6. Escuchar el evento change en los campos de archivo
    // Esto recrea tu lógica original de agregar dinámicamente si ambos están llenos
    fileInputs.forEach(function(input) {
        input.addEventListener('change', function() {
            var pdfInput = lastFormRow.querySelector('input[name="file_pdf[]"]');
            var xmlInput = lastFormRow.querySelector('input[name="file_xml[]"]');
            
            // Verificar si ambos campos de archivo tienen archivos seleccionados
            if (pdfInput.files.length > 0 && xmlInput.files.length > 0) {
                // Agregar otro bloque de formulario si no hay uno vacío ya
                agregarBloqueHTML();
            }
        });
    });
}

// Llamar a la función para agregar el bloque de HTML inicial
// Se llama solo si el formulario existe
var formFacturas = document.querySelector('#formularioFacturas');
if (formFacturas) {
    agregarBloqueHTML();
}


// Corrección de los timeouts de limpieza de campos (que no aplicaban a la estructura dinámica)
setTimeout(function(){
    if ($('#mensajes_globales').length > 0) {
        $('#mensajes_globales').remove();
        $("#mensajes_globales").html('');
        // Las siguientes líneas no son necesarias ya que los inputs se recrean dinámicamente
        // $('#file_pdf_cp').value = '';
        // $('#file_xml_cp').value = '';
    }
}, 5000);
</script>

<script>
    $(document).ready(function() {
        // Verificamos si PHP indicó que se debe mostrar el modal de errores o de éxito
        <?php 
        // Usamos isset para evitar errores si upload_files.php no definió las variables
        if ((isset($mostrar_modal_errores) && $mostrar_modal_errores) || 
            (isset($mostrar_modal_exito) && $mostrar_modal_exito)) { 
        ?>
            // Buscamos el modal por su ID (asumo que 'modal_resultados' está definido en upload_files.php) y lo mostramos
            var myModal = new bootstrap.Modal(document.getElementById('modal_resultados'), {
                keyboard: false
            });
            myModal.show();
            
        <?php } ?>
    });
</script>

<?php
include("src/templates/adminfooter.php");
?>