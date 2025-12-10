<?php 
session_start();
require("config/db.php");
include("src/templates/adminheader.php");
include "TCL_controller/validate_sat.php";
include "TCL_controller/validate_xml.php";

if (!isset($_SESSION['loggedin']) || !$_SESSION['loggedin']) {
    header("Location: index.php");
    exit();
}

$usuario_id = $_SESSION['usuario_id'];

// Manejo de mensajes de éxito o error
if (isset($_GET['msg'])) {
    switch ($_GET['msg']) {
        case 'success':
            echo '<div class="alert alert-success" role="alert">Transferencia eliminada exitosamente.</div>';
            break;
        case 'error':
            echo '<div class="alert alert-danger" role="alert">Error al eliminar la transferencia.</div>';
            break;
        case 'sqlerror':
            echo '<div class="alert alert-danger" role="alert">Error en la consulta SQL.</div>';
            break;
        case 'invalidid':
            echo '<div class="alert alert-warning" role="alert">ID de transferencia no válido.</div>';
            break;
    } // Corrección: aquí se cierra el switch con un paréntesis
}
?>
<style>
    body {
        background-color: #f7f8fa;
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        
    }

    .container {
        max-width: 1200px;
        margin-top: 30px;
    }

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

    .btn-primary {
        background-color: #3498db;
        border-color: #3498db;
        color: #fff;
        font-weight: bold;
        padding: 10px 20px;
        transition: background-color 0.3s ease;
    }
    .btn-primary:hover {
        background-color: #2980b9;
        border-color: #2980b9;
    }
    
    .btn-secondary {
        color: #fff;
        font-weight: bold;
        padding: 10px 20px;
        transition: background-color 0.3s ease;
    }

    .btn-danger {
        background-color: #e74c3c;
        border-color: #e74c3c;
        color: #fff;
        font-weight: bold;
        padding: 5px 10px;
        transition: background-color 0.3s ease;
    }

    .btn-danger:hover {
        background-color: #c0392b;
        border-color: #c0392b;
    }
    h2 {
        color: #2c3e50;
        border-bottom: 2px solid #3498db;
        padding-bottom: 10px;
    }
    /* Si quieres aplicar estos estilos específicamente para los botones de exportación de DataTables, puedes usar este selector: */
    .dt-buttons .btn {
        background-color: #3498db;  /* Color de fondo */
        border-color: #3498db;      /* Color del borde */
        color: #fff;                /* Color del texto */
        font-weight: bold;          /* Negrita */
        padding: 10px 20px;         /* Relleno de los botones */
        transition: background-color 0.3s ease; /* Transición suave al cambiar de color */
        border-radius: 5px;         /* Bordes redondeados */
    }
    
    .dt-buttons .btn:hover {
        background-color: #2980b9;  /* Color de fondo cuando el botón está en hover */
        border-color: #2980b9;      /* Color del borde cuando el botón está en hover */
        cursor: pointer;            /* Cambio de cursor al pasar el mouse */
    }
</style>

<div class="mt-5 col-md-12">
    <h2 class="mb-4">Mis Transferencias Clara</h2>
    <h6 class="mb-4 text-muted">
        <span>
            <span class="badge bg-danger">&nbsp;</span> Folios en <strong>rojo</strong> sin facturas adjuntas
        </span>
    </h6>
    
        <?php
        
        // Filtros
        $where = [];
        
        $where[] = "(t.usuario_solicitante_id = ? OR t.autorizacion_id = ? OR t.beneficiario_id = ?)";
        $params[] = $usuario_id;
        $params[] = $usuario_id;
        $params[] = $usuario_id;
        
        if (!empty($_GET['departamento'])) {
            $where[] = "t.departamento_id = ?";
            $params[] = intval($_GET['departamento']);
        }
        if (!empty($_GET['sucursal'])) {
            $where[] = "t.sucursal_id = ?";
            $params[] = intval($_GET['sucursal']);
        }
        if (!empty($_GET['estado'])) {
            $where[] = "t.estado = ?";
            $params[] = intval($_GET['estado']);
        }
        if (!empty($_GET['fecha_inicio'])) {
            $fecha_inicio = date('Y-m-d', strtotime($_GET['fecha_inicio']));
            $where[] = "t.fecha_solicitud >= ?";
            $params[] = $fecha_inicio;
        }
        if (!empty($_GET['fecha_fin'])) {
            $fecha_fin = date('Y-m-d', strtotime($_GET['fecha_fin']));
            $where[] = "t.fecha_solicitud <= ?";
            $params[] = $fecha_fin;
        }
        if (!empty($_GET['fecha_fin'])) {
            $fecha_fin = date('Y-m-d', strtotime($_GET['fecha_fin']));
            $where[] = "t.fecha_solicitud <= ?";
            $params[] = $fecha_fin;
        }
        
        
        $where_sql = count($where) > 0 ? " WHERE " . implode(" AND ", $where) : "";
        
        $sql = "SELECT 
                MIN(t.id) AS id, 
                t.folio,
                
                -- Lógica para la sucursal o consolidación corporativa
                CASE 
                    WHEN COUNT(t.folio) > 1 THEN 'Corporativo' 
                    ELSE MAX(s.sucursal) 
                END AS sucursal,
                
                -- Sumamos los importes de la transferencia para obtener el total del folio
                SUM(COALESCE(t.importe, 0)) AS importe, 
                SUM(COALESCE(t.importedls, 0)) AS importedls,
        
                -- Para los demás campos, tomamos un valor representativo del grupo
                MAX(b.nombre) AS beneficiario,
                MAX(d.departamento) AS departamento,
                MAX(c.categoria) AS categoria,
                MAX(u2.nombre) AS usuario,
                MAX(u.nombre) AS autorizacion_id,
                MAX(t.fecha_solicitud) AS fecha_solicitud, 
                MAX(t.no_cuenta) AS no_cuenta, 
                MAX(t.fecha_vencimiento) AS fecha_vencimiento, 
                MAX(t.importe_letra) AS importe_letra, 
                MAX(t.importedls_letra) AS importedls_letra, 
                MAX(t.tipo_cambio) AS tipo_cambio, 
                MAX(t.descripcion) AS descripcion, 
                MAX(t.observaciones) AS observaciones, 
                MAX(t.estado) AS estado, 
                MAX(t.documento_adjunto) AS documento_adjunto,
                MAX(t.recibo) AS recibo,
                MAX(t.motivo) AS motivo
            FROM 
                transferencias_clara_tcl t
            JOIN usuarios b ON t.beneficiario_id = b.id
            JOIN sucursales s ON t.sucursal_id = s.id
            JOIN departamentos d ON t.departamento_id = d.id
            JOIN categorias c ON t.categoria_id = c.id
            JOIN usuarios u ON t.autorizacion_id = u.id
            JOIN usuarios u2 ON t.usuario_solicitante_id = u2.id
            $where_sql AND (t.usuario_solicitante_id = ? OR t.autorizacion_id = ? OR t.beneficiario_id = ?)
            GROUP BY 
                t.folio
            ORDER BY 
            t.folio DESC";
        
        $stmt = $conn->prepare($sql);
        
        // Agregar el usuario a los parámetros
        $params[] = $usuario_id;
        $params[] = $usuario_id;
        $params[] = $usuario_id;
        
        // Verificar si hay parámetros antes de hacer bind_param
        if (!empty($params)) {
            // Generar el string de tipos dinámicamente: 's' para string
            $types = str_repeat("s", count($params)); 
            // Esto asume que todos los parámetros se manejan como strings para simplicidad,
            // pero si tiene enteros debe ajustarse ('i'). Mantendré 's' por consistencia con el código original.
            $stmt->bind_param($types, ...$params);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        
        ?>
        <h5>Filtros de Búsqueda</h5>
        <div class="row mb-3">
            <form method="get" action="" class="row g-3 align-items-end mb-4">
                <!-- Departamento -->
                <div class="col-md-2">
                    <label for="filtro_departamento" class="form-label">Departamento</label>
                    <select id="filtro_departamento" name="departamento" class="form-select">
                        <option value="">Todos</option>
                        <?php
                        $sql_departamentos = "SELECT id, departamento FROM departamentos";
                        $result_departamentos = $conn->query($sql_departamentos);
                        while ($row = $result_departamentos->fetch_assoc()) {
                            echo '<option value="'.$row['id'].'"'.(isset($_GET['departamento']) && $_GET['departamento'] == $row['id'] ? ' selected' : '').'>'.$row['departamento'].'</option>';
                        }
                        ?>
                    </select>
                </div>
        
                <!-- Sucursal -->
                <div class="col-md-2">
                    <label for="filtro_sucursal" class="form-label">Sucursal</label>
                    <select id="filtro_sucursal" name="sucursal" class="form-select">
                        <option value="">Todas</option>
                        <?php
                        $sql_sucursales = "SELECT id, sucursal FROM sucursales";
                        $result_sucursales = $conn->query($sql_sucursales);
                        while ($row = $result_sucursales->fetch_assoc()) {
                            echo '<option value="'.$row['id'].'"'.(isset($_GET['sucursal']) && $_GET['sucursal'] == $row['id'] ? ' selected' : '').'>'.$row['sucursal'].'</option>';
                        }
                        ?>
                    </select>
                </div>
        
                <!-- Estado -->
                <div class="col-md-2">
                    <label for="filtro_estado" class="form-label">Estado</label>
                    <select id="filtro_estado" name="estado" class="form-select">
                        <option value="">Todas</option>
                        <?php
                        // Nota: La tabla 'transferencias' debe ser 'transferencias_clara_tcl' si esa es la tabla principal.
                        // Mantengo la consulta original por si 'transferencias' es otra tabla de estados.
                        $sql_estado = "SELECT DISTINCT estado FROM transferencias"; 
                        $result_estado = $conn->query($sql_estado);
                        while ($row = $result_estado->fetch_assoc()) {
                            echo '<option value="'.$row['estado'].'"'.(isset($_GET['estado']) && $_GET['estado'] == $row['estado'] ? ' selected' : '').'>'.$row['estado'].'</option>';
                        }
                        ?>
                    </select>
                </div>
        
                <!-- Fecha Inicio -->
                <div class="col-md-2">
                    <label for="filtro_fecha_inicio" class="form-label">Fecha inicio de Solicitud</label>
                    <input type="date" id="filtro_fecha_inicio" name="fecha_inicio" class="form-control" value="<?php echo isset($_GET['fecha_inicio']) ? $_GET['fecha_inicio'] : ''; ?>">
                </div>
        
                <!-- Fecha Fin -->
                <div class="col-md-2">
                    <label for="filtro_fecha_fin" class="form-label">Fecha fin de Solicitud</label>
                    <input type="date" id="filtro_fecha_fin" name="fecha_fin" class="form-control" value="<?php echo isset($_GET['fecha_fin']) ? $_GET['fecha_fin'] : ''; ?>">
                </div>
        
                <!-- Botones -->
                <div class="col-md-2 d-flex gap-2">
                    <button type="submit" class="btn btn-primary w-100">Filtrar</button>
                    <button type="button" class="btn btn-outline-secondary w-100" onclick="window.location.href = window.location.pathname;">Limpiar</button>
                </div>
            </form>
        </div>
        
        <?php if ($result->num_rows > 0): ?>
        <div class="table-responsive">
            <table class="table table-striped table-hover align-middle" id="solicitudesTable">
                <thead class="table-dark text-center">
                    <tr>
                        <th>Folio</th>
                        <th>Beneficiario</th>
                        <th>Descripcion</th>
                        <th>Sucursal</th>
                        <th>Departamento</th>
                        <th>Categoría</th>
                        <th>Elabora</th>
                        <th>Autoriza</th>
                        <th>Importe</th>
                        <th>Moneda</th>
                        <th>Tipo de Cambio</th>
                        <th>Total de Comprobaciones</th>
                        <th>Pendiente por Comprobar</th>
                        <th>Fecha de Solicitud</th>
                        <th>Estado</th>
                        <th>Documento Adjunto</th>
                        <th>Recibo</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                <?php
                    $transferencias = $result->fetch_all(MYSQLI_ASSOC);
                    
                    // Extraemos todos los folios únicos de los resultados.
                    $folios_a_buscar = array_unique(array_column($transferencias, 'folio'));
                    $facturas_por_folio = [];
                    
                    // Si hay folios en nuestra lista, buscamos la suma de sus FACTURAS (XML, etc.).
                    if (!empty($folios_a_buscar)) {
                        
                        $placeholders = implode(',', array_fill(0, count($folios_a_buscar), '?'));
                        
                        $sql_total_facturas = "SELECT NO_ORDEN_COMPRA, SUM(TOTAL) AS total_facturas 
                                               FROM facturas_tcl 
                                               WHERE NO_ORDEN_COMPRA IN ($placeholders) 
                                               GROUP BY NO_ORDEN_COMPRA";
                                            
                        $stmt_facturas = $conn->prepare($sql_total_facturas);
                        
                        // El string de tipos ('s', 's', 's'...) debe coincidir con el número de folios.
                        $types = str_repeat('s', count($folios_a_buscar));
                        $stmt_facturas->bind_param($types, ...$folios_a_buscar);
                        
                        $stmt_facturas->execute();
                        $result_facturas = $stmt_facturas->get_result();
                    
                        // Guardamos los totales de facturas en un array.
                        while ($row = $result_facturas->fetch_assoc()) {
                            $facturas_por_folio[$row['NO_ORDEN_COMPRA']] = $row['total_facturas'];
                        }
                        $stmt_facturas->close();
                    }

                    // INICIO DEL CAMBIO: Sumar los COMPLEMENTOS / COMPROBANTES NO-FACTURAS
                    $comprobantes_por_folio = [];
                    
                    if (!empty($folios_a_buscar)) {
                        $placeholders = implode(',', array_fill(0, count($folios_a_buscar), '?'));
                        
                        // Consulta para sumar los importes de la tabla de comprobantes (complementos/recibos).
                        $sql_total_comprobantes = "SELECT folio, SUM(importe) AS total_comprobantes
                        FROM comprobantes_tcl
                        WHERE folio IN ($placeholders)
                        GROUP BY folio";
                        $stmt_comprobantes = $conn->prepare($sql_total_comprobantes);
                        $types_comp = str_repeat('s', count($folios_a_buscar));
                        $stmt_comprobantes->bind_param($types_comp, ...$folios_a_buscar);
                        $stmt_comprobantes->execute();
                        $result_comprobantes = $stmt_comprobantes->get_result();
                        while ($row_comp = $result_comprobantes->fetch_assoc()) {
                            $comprobantes_por_folio[$row_comp['folio']] = $row_comp['total_comprobantes'];
                        }
                        $stmt_comprobantes->close();
                    }
                    // FIN DEL CAMBIO
                    
                    $folio_anterior = null;
                    
                    // Ahora, recorremos las transferencias.
                    foreach ($transferencias as $filas):
                        
                        $fecha = new DateTime($filas['fecha_solicitud']);
                        $fecha_formateada = $fecha->format('d/m/Y');
                        
                        // Total comprobado por facturas (XML)
                        $total_facturas = $facturas_por_folio[$filas['folio']] ?? 0;
                        
                        // Total comprobado por complementos/comprobantes (NO-XML)
                        $total_comprobantes = $comprobantes_por_folio[$filas['folio']] ?? 0;
                        
                        // *** LA SUMATORIA TOTAL DE COMPROBACIONES (Facturas + Complementos) ***
                        $total_comprobado = $total_facturas + $total_comprobantes;
                        
                        $folio_class = ($total_comprobado > 0) ? 'text-success fw-bold' : 'text-danger fw-bold';
                        
                        $importe_num = ($filas['importedls'] && $filas['importedls'] != "0.00") ? $filas['importedls'] : $filas['importe'];
                        $moneda = ($filas['importedls'] && $filas['importedls'] != "0.00") ? 'USD' : 'MXN';
                    ?>
                        <tr class="text-center align-middle">
                            <td><a href="TCL_detalle_transferencias.php?id=<?= htmlspecialchars($filas['id']) ?>&MT=true" class="<?= $folio_class ?>"><?= htmlspecialchars($filas['folio']) ?></a></td>
                            <td><?= htmlspecialchars($filas['beneficiario']) ?></td>
                            <td style="text-align:center; max-width:200px;"><?= htmlspecialchars($filas['descripcion']) ?></td>
                            <td><?= htmlspecialchars($filas['sucursal']) ?></td>
                            <td><?= htmlspecialchars($filas['departamento']) ?></td>
                            <td><?= htmlspecialchars($filas['categoria']) ?></td>
                            <td><?= htmlspecialchars($filas['usuario']) ?></td>
                            <td><?= htmlspecialchars($filas['autorizacion_id']) ?></td>
                            <td>$<?= number_format($importe_num, 2, ".", ",") ?></td>
                            <td><?= htmlspecialchars($moneda) ?></td>
                            <td>$<?= number_format($filas['tipo_cambio'], 2, ".", ",") ?></td>
                            
                            <?php
                                // Mostramos el total de comprobaciones y el pendiente solo una vez por folio (en la primera fila del grupo)
                                if ($filas['folio'] !== $folio_anterior):
                                    $importe_transferencia = $importe_num;
                                    
                                    $pendiente = $importe_transferencia - $total_comprobado;
                                    
                                    // Formateamos los valores y los dejamos en $0.00 si está Cancelada o Rechazada
                                    $importe_comprobado_str = ($filas['estado'] != "Cancelada" && $filas['estado'] != "Rechazado") ? '$' . number_format($total_comprobado, 2) : '$0.00';
                                    $importe_pendiente_str = ($filas['estado'] != "Cancelada" && $filas['estado'] != "Rechazado") ? '$' . number_format($pendiente, 2) : '$0.00';
                            ?>
                                <td><?= $importe_comprobado_str ?></td>
                                <td><?= $importe_pendiente_str ?></td>
                            <?php else: ?>
                                <td>$0.00</td>
                                <td>$0.00</td>
                            <?php endif; ?>
                    
                            <td><?= $fecha_formateada ?></td>
                            <td><?= htmlspecialchars($filas['estado']) ?></td>
                            <td>
                                <?php if (!empty($filas['documento_adjunto'])): ?>
                                    <a href="<?= htmlspecialchars($filas['documento_adjunto']) ?>" target="_blank" class="btn btn-outline-primary btn-sm" title="Documento Adjunto">
                                        <i class="fas fa-file-alt fa-3x"></i>
                                    </a>
                                <?php else: ?>
                                    <span class="text-muted">N/A</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($filas['recibo'])): ?>
                                    <a href="<?= htmlspecialchars($filas['recibo']) ?>" download class="btn btn-outline-secondary btn-sm" title="Descargar Recibo">
                                        <i class="fas fa-file-download fa-3x"></i>
                                    </a>
                                <?php else: ?>
                                    <span class="text-muted">N/A</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($filas['estado'] == 'Pagado'): ?>
                                    <button class="btn btn-warning btn-sm"
                                        onclick="solicitarCancelacion('<?= htmlspecialchars($filas['folio']) ?>')">
                                        Solicitar cancelación
                                    </button>
                                <?php elseif ($filas['estado'] == 'Pendiente' || $filas['estado'] == 'Aprobado' || $filas['Subir a Pago'] == 'Cancelada') : ?>
                                    <button class="btn btn-danger btn-sm"
                                        onclick="cancelarTransferencia('<?= htmlspecialchars($filas['folio']) ?>')">
                                        Cancelar
                                    </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php
                        $folio_anterior = $filas['folio'];
                    endforeach;
                    ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
            <p class="text-center text-muted">No se encontraron resultados.</p>
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
            "responsive": true,
            "processing": true,
            "order": [[0, 'desc']], // Ordenar por folio de forma descendente
            "columnDefs": [
                { "orderable": false, "targets": [ 9, 10, 11] },
                { "targets": [5,6,7,9,10], "visible": false }
            ],
            dom: 'lBfrtip', // Cambié la posición de 'l' para que esté antes de los botones
            buttons: [
                {
                    // Este es tu nuevo botón personalizado
                    text: '<i class="fas fa-file-excel"></i> Reporte Excel',
                    className: 'btn btn-success btn-lg m-2',
                    action: function (e, dt, node, config) {
                        // 1. Recolectamos los valores de los filtros
                        const depto = $('#filtro_departamento').val();
                        const sucursal = $('#filtro_sucursal').val();
                        const estado = $('#filtro_estado').val();
                        const fecha_inicio = $('#filtro_fecha_inicio').val();
                        const fecha_fin = $('#filtro_fecha_fin').val();
                        
                        // 2. Construimos la URL con los parámetros
                        const params = new URLSearchParams({
                            departamento: depto,
                            sucursal: sucursal,
                            estado: estado,
                            fecha_inicio: fecha_inicio,
                            fecha_fin: fecha_fin,
                            usuario_id: <?php echo json_encode($_SESSION['usuario_id']); ?>, 
                            reporte: 'excel' // Un parámetro extra para tu script PHP
                        });
                        
                        // 3. Redirigimos para iniciar la descarga
                        window.location.href = `TCL_controller/generar_reporte.php?${params.toString()}`;
                    }
                },
                {
                    extend: 'print', // Imprimir la tabla
                    text: 'Imprimir',
                    className: 'btn btn-info btn-lg m-2'
                }
            ]
        });

        // 🔹 Filtrar por Departamento
        $('#filtro_departamento').on('change', function() {
            var filtroDepto = $(this).val();
            if (filtroDepto) {
                table.column(4).search($('#filtro_departamento option:selected').text()).draw();
            } else {
                table.column(4).search('').draw();
            }
        });

        // 🔹 Filtrar por Sucursal
        $('#filtro_sucursal').on('change', function() {
            var filtroSucursal = $(this).val();
            if (filtroSucursal) {
                table.column(3).search($('#filtro_sucursal option:selected').text()).draw();
            } else {
                table.column(3).search('').draw();
            }
        });
        // 🔹 Filtrar por Estado
        $('#filtro_estado').on('change', function() {
            var filtroEstado = $(this).val();
            if (filtroEstado) {
                table.column(14).search($('#filtro_estado option:selected').text()).draw();
            } else {
                table.column(14).search('').draw();
            }
        });
    });
</script>
<script>
function solicitarCancelacion(folio) {
    Swal.fire({
        title: 'Solicitar cancelación',
        text: 'Escribe el motivo de la solicitud:',
        input: 'textarea',
        inputPlaceholder: 'Motivo de cancelación',
        inputValidator: (value) => {
            if (!value) return 'Debes escribir el motivo';
        },
        showCancelButton: true,
        confirmButtonText: 'Enviar solicitud',
        cancelButtonText: 'Salir'
    }).then((result) => {
        if (result.isConfirmed) {
            let motivo = result.value;

            Swal.fire({
                title: 'Enviando...',
                allowOutsideClick: false,
                didOpen: () => Swal.showLoading()
            });

            $.ajax({
                url: 'TCL_controller/solicitar_cancelacion.php',
                type: 'POST',
                dataType: 'json',
                data: { folio: folio, motivo: motivo },
                success: function(resp) {
                    if (resp.success) {
                        Swal.fire('Enviado', resp.message, 'success').then(() => {
                            location.reload();
                        });
                    } else {
                        Swal.fire('Error', resp.message, 'error');
                    }
                },
                error: function(xhr) {
                    Swal.fire('Error', 'Error del servidor: ' + xhr.responseText, 'error');
                }
            });
        }
    });
}

function cancelarTransferencia(folio) {
    Swal.fire({
        title: 'Cancelar transferencia',
        text: 'Por favor, escribe el motivo de la cancelación:',
        input: 'textarea',
        inputPlaceholder: 'Ej. Error en el monto, solicitud duplicada, etc.',
        inputAttributes: {
            'aria-label': 'Motivo de cancelación'
        },
        inputValidator: (value) => {
            if (!value) {
                return 'Debes escribir un motivo';
            }
        },
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Cancelar transferencia',
        cancelButtonText: 'Salir'
    }).then((result) => {
        if (result.isConfirmed) {
            const motivo = result.value;

            Swal.fire({
                title: 'Procesando...',
                text: 'Cancelando transferencia',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            $.ajax({
                url: 'TCL_controller/cancelar_transferencia.php',
                type: 'POST',
                data: { 
                    folio: folio,
                    motivo: motivo
                },
                success: function(resp) {
                    if (resp.trim() === 'success') {
                        Swal.fire(
                            'Cancelada',
                            'La transferencia fue cancelada correctamente.',
                            'success'
                        ).then(() => {
                            location.reload();
                        });
                    } else {
                        Swal.fire('Error', resp, 'error');
                    }
                },
                error: function() {
                    Swal.fire(
                        'Error',
                        'No se pudo contactar al servidor.',
                        'error'
                    );
                }
            });
        }
    });
}

</script>


<?php 
include("src/templates/adminfooter.php");
?>