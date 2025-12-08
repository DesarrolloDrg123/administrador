<?php 
session_start();
require("config/db.php");
include("src/templates/adminheader.php");

if (!isset($_SESSION['loggedin']) || !$_SESSION['loggedin']) {
    header("Location: index.php");
    exit();
}


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
        cursor: pointer;           /* Cambio de cursor al pasar el mouse */
    }
</style>

<div class="mt-5 col-md-12">
    <h2 class="mb-4">Cancelar las Transferencias </h2>
    <h6 class="mb-4 text-muted">
        <span>
            <span class="badge bg-danger">&nbsp;</span> Folios en <strong>rojo</strong> sin facturas adjuntas
        </span>
    </h6>
    
        <?php
        
        // Filtros
        $where[] = "t.estado IN ('Subido a Pago', 'Pendiente', 'Aprobado')";
        
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
        
        $where_sql = count($where) > 0 ? " WHERE " . implode(" AND ", $where) : "";
        
        $sql = "
            SELECT 
                t.id, 
                t.fecha_solicitud, 
                t.no_cuenta, 
                t.fecha_vencimiento, 
                t.importe, 
                t.importe_letra, 
                t.importedls, 
                t.importedls_letra, 
                t.tipo_cambio,
                t.descripcion, 
                t.observaciones, 
                u.nombre AS autorizacion_id, 
                t.estado, 
                b.beneficiario AS beneficiario, 
                s.sucursal AS sucursal, 
                d.departamento AS departamento, 
                c.categoria AS categoria, 
                u2.nombre AS usuario, 
                t.folio, 
                t.documento_adjunto,
                t.recibo
            FROM 
                transferencias t
            JOIN beneficiarios b ON t.beneficiario_id = b.id
            JOIN sucursales s ON t.sucursal_id = s.id
            JOIN departamentos d ON t.departamento_id = d.id
            JOIN categorias c ON t.categoria_id = c.id
            JOIN usuarios u ON t.autorizacion_id = u.id
            JOIN usuarios u2 ON t.usuario_id = u2.id
            $where_sql
            ORDER BY t.folio DESC";
        
        $stmt = $conn->prepare($sql);
        
        // Verificar si hay parámetros antes de hacer bind_param
        if (!empty($params)) {
            $types = str_repeat("s", count($params));
            $stmt->bind_param($types, ...$params);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        
        ?>
        <h5>Filtros de Búsqueda</h5>
        <div class="row mb-3">
            <form method="get" action="" class="d-flex">
                <!-- Filtro Departamento -->
                <div class="col-md-2 mb-2">
                    <label for="filtro_departamento">Departamento:</label>
                    <select id="filtro_departamento" name="departamento" class="form-control">
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
                
                <!-- Filtro Sucursal -->
                <div class="col-md-2 mb-2">
                    <label for="filtro_sucursal">Sucursal:</label>
                    <select id="filtro_sucursal" name="sucursal" class="form-control">
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
                
                <!-- Filtro Sucursal -->
                <div class="col-md-2 mb-2">
                    <label for="filtro_estado">Estado:</label>
                    <select id="filtro_estado" name="estado" class="form-control">
                        <option value="">Todas</option>
                        <?php
                        $sql_estado = "SELECT DISTINCT estado FROM transferencias";
                        $result_estado = $conn->query($sql_estado);
                        while ($row = $result_estado->fetch_assoc()) {
                            echo '<option value="'.$row['estado'].'"'.(isset($_GET['estado']) && $_GET['estado'] == $row['estado'] ? ' selected' : '').'>'.$row['estado'].'</option>';
                        }
                        ?>
                    </select>
                </div>
        
                <!-- Filtro Fecha Inicio -->
                <div class="col-md-2 mb-2">
                    <label for="filtro_fecha_inicio">Fecha inicio de Solicitud:</label>
                    <input type="date" id="filtro_fecha_inicio" name="fecha_inicio" class="form-control" value="<?php echo isset($_GET['fecha_inicio']) ? $_GET['fecha_inicio'] : ''; ?>">
                </div>
        
                <!-- Filtro Fecha Fin -->
                <div class="col-md-2 mb-2">
                    <label for="filtro_fecha_fin">Fecha fin de Solicitud:</label>
                    <input type="date" id="filtro_fecha_fin" name="fecha_fin" class="form-control" value="<?php echo isset($_GET['fecha_fin']) ? $_GET['fecha_fin'] : ''; ?>">
                </div>
        
                <!-- Botón Filtrar -->
                <div class="col mb-3">
                    <button type="submit" class="btn btn-primary">Por Fecha</button>
                    <button type="button" class="btn btn-secondary" onclick="window.location.href = window.location.pathname;">Limpiar Filtro</button>
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
                            <th>Total de Facturas Subidas</th>
                            <th>Pendiente por Subir</th>
                            <th>Fecha de Solicitud</th>
                            <th>Estado</th>
                            <th>Documento Adjunto</th>
                            <th>Recibo</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        // Asumimos que tu consulta principal ya se ejecutó y está en la variable $result.
                        $transferencias = $result->fetch_all(MYSQLI_ASSOC);
                        
                        // Extraemos todos los folios únicos para hacer una sola consulta de facturas.
                        $folios_a_buscar = array_unique(array_column($transferencias, 'folio'));
                        $facturas_por_folio = [];
                        
                        if (!empty($folios_a_buscar)) {
                            // --- PASO 2: HACER UNA SOLA CONSULTA PARA TODAS LAS FACTURAS ---
                            $placeholders = implode(',', array_fill(0, count($folios_a_buscar), '?'));
                            
                            $sql_total_facturas = "SELECT NO_ORDEN_COMPRA, SUM(TOTAL) AS total_facturas 
                                                   FROM facturas 
                                                   WHERE NO_ORDEN_COMPRA IN ($placeholders) 
                                                   GROUP BY NO_ORDEN_COMPRA";
                                                   
                            $stmt_facturas = $conn->prepare($sql_total_facturas);
                            $types = str_repeat('s', count($folios_a_buscar));
                            $stmt_facturas->bind_param($types, ...$folios_a_buscar);
                            $stmt_facturas->execute();
                            $result_facturas = $stmt_facturas->get_result();
                        
                            // Guardamos los totales en un array para un acceso rápido.
                            while ($row = $result_facturas->fetch_assoc()) {
                                $facturas_por_folio[$row['NO_ORDEN_COMPRA']] = $row['total_facturas'];
                            }
                            $stmt_facturas->close();
                        }
                        
                        $folio_anterior = null; // Para llevar la cuenta del último folio impreso.
                        
                        foreach ($transferencias as $filas):
                            $fecha_formateada = (new DateTime($filas['fecha_solicitud']))->format('d/m/Y');
                            
                            // Obtenemos el total de la factura desde nuestro array pre-calculado.
                            $total_facturas = $facturas_por_folio[$filas['folio']] ?? 0;
                            
                            // Verificación de factura para el color del folio
                            $folio_class = ($total_facturas > 0) ? 'text-success fw-bold' : 'text-danger fw-bold';
                            
                            // Identificamos la moneda y el importe principal
                            if (!empty($filas['importedls']) && $filas['importedls'] !== "0.00") {
                                $importe = $filas['importedls'];
                                $moneda = 'USD';
                            } else {
                                $importe = $filas['importe'];
                                $moneda = 'MXN';
                            }
                        ?>
                            <tr class="text-center align-middle">
                                <td>
                                    <a href="TR_edit_transferencia.php?id=<?= htmlspecialchars($filas['id']) ?>&CT=true" class="<?= $folio_class ?>">
                                        <?= htmlspecialchars($filas['folio']) ?>
                                    </a>
                                </td>
                                <td><?= htmlspecialchars($filas['beneficiario']) ?></td>
                                <td style="max-width: 200px;"><?= htmlspecialchars($filas['descripcion']) ?></td>
                                <td><?= htmlspecialchars($filas['sucursal']) ?></td>
                                <td><?= htmlspecialchars($filas['departamento']) ?></td>
                                <td><?= htmlspecialchars($filas['categoria']) ?></td>
                                <td><?= htmlspecialchars($filas['usuario']) ?></td>
                                <td><?= htmlspecialchars($filas['autorizacion_id']) ?></td>
                                <td>$<?= number_format($importe, 2) ?></td>
                                <td><?= $moneda ?></td>
                                <td>$<?= number_format($filas['tipo_cambio'], 2) ?></td>
                                
                                <?php
                                if ($filas['folio'] !== $folio_anterior):
                                    // Si el folio es nuevo, calculamos y mostramos los valores.
                                    $importe_transferencia_total = 0;
                                    // Necesitamos sumar los importes de todas las filas con el mismo folio
                                    foreach ($transferencias as $sub_fila) {
                                        if ($sub_fila['folio'] === $filas['folio']) {
                                             $sub_importe = (!empty($sub_fila['importedls']) && $sub_fila['importedls'] != "0.00") ? $sub_fila['importedls'] : $sub_fila['importe'];
                                             $importe_transferencia_total += floatval($sub_importe);
                                        }
                                    }
                        
                                    $pendiente = $importe_transferencia_total - $total_facturas;
                                ?>
                                    <td>$<?= number_format($total_facturas, 2) ?></td>
                                    <td>$<?= number_format($pendiente, 2) ?></td>
                                <?php else: ?>
                                    <td>$0.00</td>
                                    <td>$0.00</td>
                                <?php endif; ?>
                        
                                <td><?= $fecha_formateada ?></td>
                                <td><?= htmlspecialchars($filas['estado']) ?></td>
                        
                                <td>
                                    <?php if (!empty($filas['documento_adjunto'])): ?>
                                        <a href="<?= htmlspecialchars($filas['documento_adjunto']) ?>" target="_blank" class="btn btn-outline-primary btn-sm" title="Ver Documento">
                                            <i class="fas fa-file-alt fa-2x"></i>
                                        </a>
                                    <?php else: ?>
                                        <span class="text-muted">N/A</span>
                                    <?php endif; ?>
                                </td>
                                
                                <td>
                                    <?php if (!empty($filas['recibo'])): ?>
                                        <a href="<?= htmlspecialchars($filas['recibo']) ?>" download class="btn btn-outline-secondary btn-sm" title="Descargar Recibo">
                                            <i class="fas fa-file-download fa-2x"></i>
                                        </a>
                                    <?php else: ?>
                                        <span class="text-muted">N/A</span>
                                    <?php endif; ?>
                                </td>
                        
                                <td>
                                    <?php if (in_array($filas['estado'], ['Subido a Pago', 'Pendiente', 'Aprobado'])): ?>
                                        <a href="#" 
                                           data-url="TR_controller/delete_transferencia.php?id=<?= $filas['id'] ?>" 
                                           data-folio="<?= $filas['id'] ?>"
                                           class="btn btn-danger btn-sm btn-accion-cancelar">
                                           Cancelar
                                        </a>
                                    <?php else: ?>
                                        <span class="text-muted">No permitido</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php
                            // Al final de la vuelta, actualizamos el folio anterior.
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
document.addEventListener('DOMContentLoaded', function() {
    // ... (aquí van tus otros listeners de 'aprobar' y 'rechazar') ...

    const tabla = document.querySelector('body'); // Escuchamos en todo el body para asegurar que encuentre el botón

    tabla.addEventListener('click', function(e) {
        
        // Verificamos si el clic fue en un botón de cancelar
        if (e.target.classList.contains('btn-accion-cancelar')) {
            e.preventDefault();

            const boton = e.target;
            const url = boton.dataset.url;
            const folio = boton.dataset.folio;

            // 1. Pedir motivo de la cancelación
            Swal.fire({
                title: `Cancelar Transferencia #${folio}`,
                input: 'textarea',
                inputPlaceholder: 'Escribe aquí el motivo de la cancelación...',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: '¡Cancelar!',
                cancelButtonText: 'No',
                confirmButtonColor: '#d33',
                inputValidator: (value) => {
                    if (!value) {
                        return '¡El motivo de la cancelación es obligatorio!';
                    }
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    const motivo = result.value;

                    // 2. Mostrar "Cargando"
                    Swal.fire({
                        title: 'Procesando cancelación...',
                        allowOutsideClick: false,
                        didOpen: () => { Swal.showLoading(); }
                    });
                    
                    // 3. Enviar petición POST con el motivo
                    fetch(url, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: `motivo=${encodeURIComponent(motivo)}`
                    })
                    .then(response => response.json())
                    .then(data => {
                        // 4. Mostrar resultado
                        if (data.success) {
                            Swal.fire('¡Cancelada!', data.message, 'success').then(() => location.reload());
                        } else {
                            Swal.fire('Error', data.message, 'error');
                        }
                    })
                    .catch(error => Swal.fire('Error de Conexión', 'No se pudo comunicar con el servidor.', 'error'));
                }
            });
        }
    });
});
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
                { "orderable": false, "targets": [8, 9, 10] },
                { "targets": [5,6,7,9,10], "visible": false }
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
        // 🔹 Filtrar por Departamento
        $('#filtro_estado').on('change', function() {
            var filtroEstado = $(this).val();
            if (filtroEstado) {
                table.column(9).search($('#filtro_estado option:selected').text()).draw();
            } else {
                table.column(9).search('').draw();
            }
        });
    });
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
