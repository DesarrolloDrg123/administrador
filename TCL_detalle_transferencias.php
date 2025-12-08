// Incluye encabezado, configuración de DB y controladores de archivos.
// Se asume que estos archivos existen y manejan la conexión ($conn) y las funciones de upload.
include("src/templates/adminheader.php");
require("config/db.php");
include('TCL_controller/upload_files.php'); // Lógica de subida de facturas (PDF/XML)
include('TCL_controller/upload_comprobantes.php'); // Lógica de subida de recibos/comprobantes


// -----------------------------------------------------------
// 1. Verificación de Sesión y Parámetros
// -----------------------------------------------------------

if (!isset($_SESSION['loggedin']) || !$_SESSION['loggedin']) {
    header("Location: index.php");
    exit();
}

$usuario_ses = $_SESSION['nombre'];
$usuario_id = $_SESSION['usuario_id'];

if (!isset($_GET['id'])) {
    // Manejo de error si no se proporciona el ID
    echo "ID de solicitud no proporcionado.";
    exit();
}

$solicitud_id = $_GET['id'];
$MTParam = isset($_GET['MT']) ? $_GET['MT'] : null; // Mis Transferencias
$ATParam = isset($_GET['AT']) ? $_GET['AT'] : null; // Por Autorizar


// -----------------------------------------------------------
// 2. Consulta Principal de la Solicitud
// -----------------------------------------------------------

try {
    // Consulta optimizada para seleccionar todos los detalles necesarios de la transferencia
    $sql = 'SELECT t.id, t.folio, s.sucursal AS sucursal, b.nombre AS nombre_beneficiario, t.beneficiario_id, t.fecha_solicitud, t.fecha_vencimiento, t.importe, t.importe_letra, t.importedls, t.importedls_letra, t.descripcion, t.estado, t.documento_adjunto, t.no_cuenta,
    t.observaciones, t.categoria_id, t.departamento_id, t.usuario_solicitante_id, t.autorizacion_id AS autoriza, t.motivo, u1.nombre AS nombre_usuario, u2.nombre AS nombre_autoriza, d.departamento, c.categoria
    FROM transferencias_clara_tcl t 
    JOIN categorias c ON t.categoria_id = c.id
    JOIN departamentos d ON t.departamento_id = d.id
    JOIN usuarios u1 ON t.usuario_solicitante_id = u1.id
    JOIN usuarios b ON t.beneficiario_id = b.id
    JOIN sucursales s ON t.sucursal_id = s.id
    LEFT JOIN usuarios u2 ON t.autorizacion_id = u2.id -- LEFT JOIN por si aún no está autorizada/asignada
    WHERE t.id = ?';

    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        throw new Exception($conn->error);
    }

    $stmt->bind_param('i', $solicitud_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $solicitud = $result->fetch_assoc();
    $stmt->close(); // Cerrar el statement principal

    if (!$solicitud) {
        // Corrección de la codificación
        echo "No se encontró la solicitud o no tienes permiso para verla.";
        exit();
    }
} catch (Exception $e) {
    echo "Error en la consulta: " . $e->getMessage();
    exit();
}

// -----------------------------------------------------------
// 3. Formateo de Fechas
// -----------------------------------------------------------

// Extraer la fecha de la base de datos
$fecha = new DateTime($solicitud['fecha_solicitud']);
$fecha1 = new DateTime($solicitud['fecha_vencimiento']);

// Meses en español abreviados
$meses_espanol = ['ENE', 'FEB', 'MAR', 'ABR', 'MAY', 'JUN', 'JUL', 'AGO', 'SEP', 'OCT', 'NOV', 'DIC'];

// Fecha de Solicitud
$dia = $fecha->format('j');
$mes = $meses_espanol[(int)$fecha->format('n') - 1];
$anio = $fecha->format('Y');
$fecha_formateada = "{$dia}/{$mes}/{$anio}";

// Fecha de Vencimiento
$dia1 = $fecha1->format('j');
$mes1 = $meses_espanol[(int)$fecha1->format('n') - 1];
$anio1 = $fecha1->format('Y');
$fecha_formateada1 = "{$dia1}/{$mes1}/{$anio1}";


// -----------------------------------------------------------
// 4. Cálculo de Totales y Pendiente
// -----------------------------------------------------------

$folio = $solicitud['folio'];

// A. Obtener todas las facturas
// MODIFICACIÓN: Se incluyen FECHA y RFC, que son necesarios para la tabla de facturas
$sql_facturas = "SELECT ID, TOTAL, UUID, ESTATUS, FECHA, RFC FROM facturas_tcl WHERE NO_ORDEN_COMPRA = ?";
$stmt_facturas = $conn->prepare($sql_facturas);
$stmt_facturas->bind_param('s', $folio);
$stmt_facturas->execute();
$result_facturas = $stmt_facturas->get_result();
$facturas_array = $result_facturas->fetch_all(MYSQLI_ASSOC);
$stmt_facturas->close();

// B. Suma de los totales de las facturas (Solo las que NO están canceladas si el campo ESTATUS existe y es relevante)
// Se mantiene la consulta original que asume una tabla `facturas_tcl` para el total.
$sql_total_facturas = "SELECT SUM(TOTAL) AS total_facturas FROM facturas_tcl WHERE NO_ORDEN_COMPRA = ?";
$stmt_total_facturas = $conn->prepare($sql_total_facturas);
$stmt_total_facturas->bind_param('s', $folio);
$stmt_total_facturas->execute();
$row_total_facturas = $stmt_total_facturas->get_result()->fetch_assoc();
$total_facturas = $row_total_facturas['total_facturas'] ?? 0;
$stmt_total_facturas->close();

// Determinar el importe base de la transferencia (consolidado)
if (empty($solicitud['importe']) || $solicitud['importe'] == '0.00') {
    $importe_transferencia = (float)$solicitud['importedls'];
    $moneda_simbolo = 'US$';
} else {
    $importe_transferencia = (float)$solicitud['importe'];
    $moneda_simbolo = '$';
}

// C. Suma de los totales de los comprobantes (recibos)
$sql_total_comprobantes = "SELECT SUM(importe) AS total_comprobantes FROM comprobantes_tcl WHERE folio = ?";
$stmt_comp_total = $conn->prepare($sql_total_comprobantes);
$stmt_comp_total->bind_param('s', $folio);
$stmt_comp_total->execute();
$row_total_comprobantes = $stmt_comp_total->get_result()->fetch_assoc();
$total_comprobantes = $row_total_comprobantes['total_comprobantes'] ?? 0;
$stmt_comp_total->close();

// D. Calcular el total COMPROBADO (Facturas + Comprobantes)
$total_comprobado = (float)$total_facturas + (float)$total_comprobantes;

// E. CÁLCULO DEL PENDIENTE
$pendiente = $importe_transferencia - $total_comprobado;

// F. Consulta para obtener los comprobantes detallados (para la tabla)
$sql_comprobantes = "SELECT id, importe, descripcion FROM comprobantes_tcl WHERE folio = ?";
$stmt_comp = $conn->prepare($sql_comprobantes);
if ($stmt_comp) {
    $stmt_comp->bind_param('s', $folio);
    $stmt_comp->execute();
    $result_comprobantes = $stmt_comp->get_result();
    $comprobantes_array = $result_comprobantes->fetch_all(MYSQLI_ASSOC);
    $stmt_comp->close();
} else {
    $comprobantes_array = [];
    error_log("Error al preparar la consulta de comprobantes: " . $conn->error);
}

// Función para formatear moneda
function format_currency($amount, $symbol = '$') {
    return $symbol . number_format((float)$amount, 2, ".", ",");
}

?>

<style>
/* Estilos Bootstrap mejorados */
.container { max-width: 1600px; }
h2.section-title {
    color: #17202a;
    border-bottom: 2px solid #3498db;
    padding-bottom: 8px;
    margin-bottom: 20px;
    font-weight: 600;
}
.table th {
    background-color: #eaf2f8;
    color: #2c3e50;
    width: auto; /* Se ajusta automáticamente con más columnas */
}
.card {
    box-shadow: 0 4px 12px rgba(0,0,0,0.08);
    border-radius: 10px;
}
.card-header {
    background-color: #d6eaf8;
    color: #1a5276;
    font-weight: bold;
    border-radius: 10px 10px 0 0 !important;
}
.text-danger-custom { color: #e74c3c; font-weight: bold; }

/* Estilo para UUID más pequeño */
.table-facturas td:nth-child(4) {
    font-size: 0.8rem;
}
</style>

<div class="container mt-4">
    <?php if (isset($GLOBALS["mensaje_global"])) echo $GLOBALS["mensaje_global"]; ?>
    <div class="row">
        <!-- DETALLE DE TRANSFERENCIA -->
        <div class="col-md-6">
            <h2 class="section-title"><i class="fas fa-file-invoice-dollar"></i> Detalle de Transferencia</h2>
            <div class="card mb-4">
                <div class="card-body">
                    <table class="table table-striped table-hover table-sm">
                        <tbody>
                            <tr><th>Folio</th><td class="text-danger-custom"><?= htmlspecialchars($solicitud['folio']) ?></td></tr>
                            <tr><th>Sucursal</th><td><?= htmlspecialchars($solicitud['sucursal']) ?></td></tr>
                            <tr><th>Solicitante</th><td><?= htmlspecialchars($solicitud['nombre_usuario']) ?></td></tr>
                            <tr><th>Beneficiario</th><td><?= htmlspecialchars($solicitud['nombre_beneficiario']) ?></td></tr>
                            <tr><th>No. de Cuenta</th><td><?= !empty($solicitud['no_cuenta']) ? htmlspecialchars($solicitud['no_cuenta']) : 'N/A' ?></td></tr>
                            <tr><th>Departamento</th><td><?= htmlspecialchars($solicitud['departamento']) ?></td></tr>
                            <tr><th>Categoría</th><td><?= htmlspecialchars($solicitud['categoria']) ?></td></tr>
                            <tr><th>Fecha de Solicitud</th><td><?= htmlspecialchars($fecha_formateada) ?></td></tr>
                            <tr><th>Fecha de Vencimiento</th><td><?= htmlspecialchars($fecha_formateada1) ?></td></tr>
                            <tr><th>Descripción</th><td><?= htmlspecialchars($solicitud['descripcion']) ?></td></tr>
                            <tr><th>Observaciones</th><td><?= !empty($solicitud['observaciones']) ? htmlspecialchars($solicitud['observaciones']) : 'N/A' ?></td></tr>
                            <tr><th>Estado</th><td><?= htmlspecialchars($solicitud['estado']) ?></td></tr>
                            <?php if ($solicitud['estado'] == "Cancelada" || $solicitud['estado'] == "Rechazado"): ?>
                                <tr><th>Motivo</th><td><?= htmlspecialchars($solicitud['motivo']) ?></td></tr>
                            <?php endif; ?>
                            <tr><th>Autoriza</th><td><?= htmlspecialchars($solicitud['nombre_autoriza']) ?></td></tr>
                            
                            <?php if ($moneda_simbolo == 'US$'): ?>
                                <tr><th>Importe en Dólares</th><td><?= format_currency($solicitud['importedls'], 'US$') ?></td></tr>
                                <tr><th>Importe en Letra</th><td><?= htmlspecialchars($solicitud['importedls_letra']) ?></td></tr>
                            <?php else: ?>
                                <tr><th>Importe en Pesos</th><td><?= format_currency($solicitud['importe'], '$') ?></td></tr>
                                <tr><th>Importe en Letra</th><td><?= htmlspecialchars($solicitud['importe_letra']) ?></td></tr>
                            <?php endif; ?>
                            
                            <!-- Totales de Comprobación -->
                            <tr><th class="table-info">Total de Facturas</th><td class="table-info"><?= format_currency($total_facturas, $moneda_simbolo) ?></td></tr>
                            <tr><th class="table-info">Total de Recibos/Comprobantes</th><td class="table-info"><?= format_currency($total_comprobantes, $moneda_simbolo) ?></td></tr>
                            <tr><th class="table-success fw-bold">Total Comprobado</th><td class="table-success fw-bold"><?= format_currency($total_comprobado, $moneda_simbolo) ?></td></tr>
                            <tr><th class="table-danger">Pendiente por Comprobar</th>
                                <td class="table-danger fw-bold">
                                    <?= ($solicitud['estado'] != "Cancelada" && $solicitud['estado'] != "Rechazado") ? format_currency($pendiente, $moneda_simbolo) : format_currency(0, $moneda_simbolo); ?>
                                </td>
                            </tr>
                            
                            <?php if (!empty($solicitud['documento_adjunto'])): ?>
                                <tr>
                                    <th>Documento Adjunto (Solicitud)</th>
                                    <td><a href="<?= htmlspecialchars($solicitud['documento_adjunto']) ?>" target="_blank" class="btn btn-outline-primary btn-sm"><i class="fas fa-file-pdf"></i> Ver Documento</a></td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>

                    <!-- Botones de Acción -->
                    <div class="d-flex gap-2 mt-3 justify-content-end">
                        <?php if ($solicitud['estado'] === 'Pendiente' && $usuario_ses === $solicitud['nombre_usuario']) : ?>
                            <a href="TCL_edit_transfer.php?id=<?= $solicitud_id ?>&MT=true" class="btn btn-warning"><i class="fas fa-edit"></i> Editar Transferencia</a>
                        <?php endif; ?>
                        <?php if ($MTParam === 'true'): ?>
                            <a href="TCL_mis_transferencias.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Volver a Mis Transferencias</a>
                        <?php endif; ?>
                        <?php if ($ATParam === 'true'): ?>
                            <a href="TCL_por_autorizar.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Volver a Autorizaciones</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- FORMULARIOS DE CARGA DE COMPROBANTES Y TABLAS -->
        <?php if ($solicitud['estado'] != "Pendiente" && $solicitud['estado'] != "Rechazado" && $solicitud['estado'] != "Cancelado"): ?>
        <div class="col-md-6">

            <!-- Cargar Facturas (PDF/XML) -->
            <h2 class="section-title"><i class="fas fa-file-upload"></i> Cargar Facturas (PDF/XML)</h2>
            <div class="card mb-4">
                <div class="card-body">
                    <form action="" method="POST" name="formularioFacturas" id="formularioFacturas" enctype="multipart/form-data">
                        <div class='row mb-3'>
                            <div class='col-md-6'><h5 class='fw-normal'>Archivo PDF: </h5></div>
                            <div class='col-md-6'><h5 class='fw-normal'>Archivo XML: </h5></div>
                        </div>
                        <!-- Los campos de archivo se agregan dinámicamente con JavaScript en .nuevosCampos -->
                        <div class="nuevosCampos">
                        </div>
                        <button type="submit" name="submit_facturas" class="btn btn-primary mt-3"><i class="fas fa-cloud-upload-alt"></i> Cargar Factura(s)</button>
                    </form>
                </div>
            </div>

            <!-- Tabla de Facturas Subidas -->
            <?php if (!empty($facturas_array)): ?>
            <h2 class="section-title"><i class="fas fa-list-alt"></i> Facturas Subidas</h2>
            <div class="card mb-4">
                <div class="card-body p-0">
                    <table class="table table-sm table-striped table-hover mb-0 table-facturas">
                        <thead>
                            <!-- MODIFICACIÓN: Encabezados de tabla según la solicitud del usuario -->
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
                            <?php foreach ($facturas_array as $row_factura): 
                                // Formato de fecha para la tabla si existe el campo FECHA
                                $factura_fecha_display = isset($row_factura['FECHA']) 
                                    ? (new DateTime($row_factura['FECHA']))->format('Y-m-d') 
                                    : 'N/A';
                            ?>
                            <tr>
                                <!-- Fecha -->
                                <td><?= htmlspecialchars($factura_fecha_display) ?></td>
                                <!-- RFC -->
                                <td><?= htmlspecialchars($row_factura['RFC'] ?? 'N/A') ?></td>
                                <!-- Total -->
                                <td><?= format_currency($row_factura['TOTAL'], $moneda_simbolo) ?></td>
                                <!-- UUID -->
                                <td><?= htmlspecialchars($row_factura['UUID']) ?></td>
                                <!-- Ver (Asumo enlace para ver el PDF/XML) -->
                                <td>
                                    <a href="TCL_controller/factura_files.php?uuid=<?= htmlspecialchars($row_factura['UUID']) ?>&file_type=pdf&action=view" target="_blank" class="btn btn-sm btn-outline-primary" title="Ver PDF">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                </td>
                                <!-- Descargar (Asumo enlace para descargar el PDF/XML. Usaremos el XML por defecto) -->
                                <td>
                                    <a href="TCL_controller/factura_files.php?uuid=<?= htmlspecialchars($row_factura['UUID']) ?>&file_type=xml&action=download" class="btn btn-sm btn-outline-secondary" title="Descargar XML">
                                        <i class="fas fa-download"></i>
                                    </a>
                                </td>
                                <!-- Reiniciar (Mantiene el botón de Reiniciar) -->
                                <td>
                                    <button type="button" class="btn btn-sm btn-outline-danger" onclick="abrirModalReset('<?= htmlspecialchars($row_factura['UUID']) ?>')">
                                        <i class="fas fa-redo"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>
            
            <hr class="my-4">
            
            <!-- Cargar Comprobantes/Recibos (Individual) -->
            <h2 class="section-title"><i class="fas fa-receipt"></i> Cargar Comprobantes/Recibos</h2>
            <div class="card mb-4">
                <div class="card-body">
                    <form action="" method="POST" name="formularioComprobantes" id="formularioComprobantes" enctype="multipart/form-data">
                        
                        <div class="mb-3">
                            <label for="importe_comprobante" class="form-label">Monto/Importe del Comprobante: *</label>
                            <div class="input-group">
                                <span class="input-group-text"><?= $moneda_simbolo ?></span>
                                <input type="number" step="0.01" class="form-control" id="importe_comprobante" name="importe_comprobante" min="0.01" required>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="descripcion_comprobante" class="form-label">Descripción del Recibo/Gasto: *</label>
                            <textarea class="form-control" id="descripcion_comprobante" name="descripcion_comprobante" rows="2" required></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label for="evidencia_comprobante" class="form-label">Evidencia (Imagen, Foto, PDF): *</label>
                            <input type="file" class="form-control" id="evidencia_comprobante" name="evidencia_comprobante" accept="image/*,.pdf" required>
                        </div>
                        
                        <input type="hidden" name="folio_solicitud" value="<?= htmlspecialchars($solicitud['folio']) ?>">
                        <input type="hidden" name="submit_comprobante" value="1">
                        
                        <button type="submit" name="submit_comprobantes" class="btn btn-success mt-3"><i class="fas fa-save"></i> Subir Comprobante</button>
                    </form>
                </div>
            </div>
            
            <!-- Tabla de Comprobantes/Recibos Subidos -->
            <?php if (!empty($comprobantes_array)): ?>
                <h2 class="section-title"><i class="fas fa-clipboard-list"></i> Recibos/Comprobantes Subidos</h2>
                <div class="card mb-4">
                    <div class="card-body p-0">
                        <table class="table table-sm table-striped table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Importe</th>
                                    <th>Descripción</th>
                                    <th>Evidencia</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($comprobantes_array as $row_comprobante): ?>
                                <tr>
                                    <td><?= format_currency($row_comprobante['importe'], $moneda_simbolo) ?></td>
                                    <td><?= htmlspecialchars($row_comprobante['descripcion']) ?></td>
                                    <td>
                                        <a href="view_evidencia.php?id=<?= $row_comprobante['id'] ?>" target="_blank" class="text-primary" title="Ver Evidencia">
                                            <i class="fas fa-image fa-2x"></i>
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>

        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal de Confirmación para Reset de Factura -->
<div class="modal fade" id="modalResetFactura" tabindex="-1" aria-labelledby="modalResetLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="modalResetLabel"><i class="fas fa-exclamation-triangle"></i> Confirmar Reinicio de Factura</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <p>Estás a punto de reiniciar el estado de una factura. Esto puede requerir que se suba de nuevo.</p>
                <input type="hidden" id="uuidInput">
                <div class="form-group mb-3">
                    <label for="descripcionInput" class="form-label">Motivo del reinicio: *</label>
                    <textarea id="descripcionInput" class="form-control" rows="3" required placeholder="Escribe el motivo del reinicio de la factura..."></textarea>
                </div>
                <!-- Mensaje de respuesta, reemplaza al alert() -->
                <div id="respuestaReset" class="text-danger fw-bold mt-2"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-danger" onclick="confirmarReset()"><i class="fas fa-undo"></i> Confirmar Reinicio</button>
            </div>
        </div>
    </div>
</div>

<script>
// --- Funciones para el Modal de Reinicio (Reemplazo de alert()) ---

/**
 * Abre el modal para confirmar el reinicio de una factura.
 * @param {string} uuid - El UUID de la factura a reiniciar.
 */
function abrirModalReset(uuid) {
    // Se asume que jQuery y Bootstrap 5 están cargados (por la estructura del modal)
    $('#uuidInput').val(uuid);
    $('#descripcionInput').val('');
    $('#respuestaReset').text('');
    var resetModal = new bootstrap.Modal(document.getElementById('modalResetFactura'));
    resetModal.show();
}

/**
 * Envía la solicitud AJAX para reiniciar la factura.
 */
function confirmarReset() {
    const uuid = $('#uuidInput').val();
    const descripcion = $('#descripcionInput').val();
    const $respuestaReset = $('#respuestaReset');

    if (descripcion.trim() === '') {
        $respuestaReset.text('Por favor escribe un motivo para el reinicio.');
        return;
    }

    $respuestaReset.text('Procesando...');

    $.ajax({
        url: 'TCL_controller/factura_reset.php',
        method: 'POST',
        data: {
            UUID: uuid,
            descripcion: descripcion
        },
        success: function (respuesta) {
            if (respuesta.trim() === 'success') {
                // Ocultar modal y mostrar mensaje de éxito temporalmente
                $('#modalResetFactura').modal('hide');
                const successMsg = `<div class="alert alert-success mt-3" role="alert">Factura reiniciada correctamente. Recargando...</div>`;
                $('.container').prepend(successMsg);
                
                // Recargar después de un breve retraso
                setTimeout(() => {
                    location.reload(); 
                }, 1000);
            } else {
                $respuestaReset.text('Ocurrió un error al reiniciar la factura. Respuesta del servidor: ' + respuesta);
            }
        },
        error: function () {
            $respuestaReset.text('Error de comunicación con el servidor.');
        }
    });
}


// --- Lógica de Manejo de Formularios Dinámicos y Mensajes ---

// Inicialización de campos de archivo dinámicos
document.addEventListener('DOMContentLoaded', function() {
    // Función auxiliar para agregar dinámicamente el bloque de HTML
    function agregarBloqueHTML() {
        const fileSection = document.querySelector('.nuevosCampos');
        const folio = "<?php echo htmlspecialchars($solicitud['folio']); ?>";
        
        // Verificar si ya existen campos vacíos (al menos uno de los inputs sin archivo)
        const existingInputs = fileSection.querySelectorAll('input[type="file"]');
        let hasEmptyPair = false;

        // Itera sobre los inputs de dos en dos (PDF y XML)
        for (let i = 0; i < existingInputs.length; i += 2) {
            const pdfInput = existingInputs[i];
            const xmlInput = existingInputs[i + 1];
            
            // Si una pareja existe y tiene al menos un campo vacío, no agregamos más.
            if (pdfInput && xmlInput && (pdfInput.files.length === 0 || xmlInput.files.length === 0)) {
                hasEmptyPair = true;
                break;
            }
        }
        
        // Si no hay una pareja vacía, crea una nueva
        if (fileSection.children.length === 0 || !hasEmptyPair) {
            const div = document.createElement('div');
            div.classList.add('row', 'mb-3', 'g-2'); // Uso de row y g-2 para espaciado en Bootstrap
            
            div.innerHTML = `
                <div class="col-md-6">
                    <input type="file" name="file_pdf[]" class="form-control" accept=".pdf" required />
                </div>
                <div class="col-md-6">
                    <input type="file" name="file_xml[]" class="form-control" accept=".xml" required />
                    <input type="hidden" name="ordenCompra[]" value="${folio}">
                </div>
            `;
            
            fileSection.appendChild(div);

            // Escuchar el evento change en los nuevos campos de archivo para agregar el siguiente bloque
            const newFileInputs = div.querySelectorAll('input[type="file"]');
            newFileInputs.forEach(function(input) {
                input.addEventListener('change', function() {
                    // Verificar si ambos campos en este *mismo* bloque tienen archivos seleccionados
                    const pdfInput = div.querySelector('input[name="file_pdf[]"]');
                    const xmlInput = div.querySelector('input[name="file_xml[]"]');
                    if (pdfInput.files.length > 0 && xmlInput.files.length > 0) {
                        agregarBloqueHTML();
                    }
                });
            });
        }
    }

    // Llama a la función para agregar el bloque de HTML inicial
    agregarBloqueHTML();
});

// Control de validación de formularios (las funciones soloNumeros y soloLetras deben estar definidas en adminheader.php o un script incluido)
// Se han comentado las validaciones que requerían campos específicos que se añaden dinámicamente. 
// El siguiente bloque de código se ha simplificado ya que los campos `ordenCompra` y `folioFactura` ya no son inputs directos en el HTML estático.

/*
var formFacturas = document.querySelector('#formularioFacturas');
if (formFacturas) {
    // Si necesitas validar dinámicamente, lo harías dentro del event listener 'change' del file input.
    // Por ejemplo: formFacturas.addEventListener('submit', function(e) { ... });
}
*/

// Ocultar mensajes globales después de 5 segundos
$(document).ready(function() {
    setTimeout(function() {
        // Elimina el mensaje global si existe
        $('#mensajes_globales').remove(); 
    }, 5000);

    // Muestra el modal de resultados de subida (si PHP lo indica)
    <?php 
    if ((isset($mostrar_modal_errores) && $mostrar_modal_errores) || 
        (isset($mostrar_modal_exito) && $mostrar_modal_exito)) { 
    ?>
        // Usamos Bootstrap 5
        var myModal = new bootstrap.Modal(document.getElementById('modal_resultados'), {
            keyboard: false
        });
        myModal.show();
    <?php } ?>
});
</script>

<?php
// Cierre de la conexión y footer
// Esto es importante si la conexión $conn sigue abierta
if (isset($conn)) {
    $conn->close();
}
include("src/templates/adminfooter.php");
?>