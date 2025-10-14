<?php
    session_start();
    
    require("config/db.php");
    include("src/templates/adminheader.php");
    
    $nombre = $_SESSION['nombre'];
    
    function TodosMovimientosPerso($conn) {
        $sql = "SELECT 
                    mp.id,
                    mp.folio,
                    mp.estatus,
                    mp.fecha_solicitud,
                    mp.solicitante,
                    mp.tipo_solicitud,
                    s.sucursal
                FROM 
                    solicitudes_movimientos_personal mp
                LEFT JOIN 
                    sucursales s ON mp.sucursal_id = s.id
                ORDER BY mp.id DESC";
    
        $result = $conn->query($sql);
        
        if ($result && $result->num_rows > 0) {
            return $result->fetch_all(MYSQLI_ASSOC);
        } else {
            return [];
        }
    }
    
    $mpersonal = TodosMovimientosPerso($conn);

    // --- INICIO: OBTENER DATOS ÚNICOS PARA LOS FILTROS ---
    $sucursales_filtro = array_unique(array_column($mpersonal, 'sucursal'));
    $tipos_filtro = array_unique(array_column($mpersonal, 'tipo_solicitud'));
    $estatus_filtro = array_unique(array_column($mpersonal, 'estatus'));
    sort($sucursales_filtro);
    sort($tipos_filtro);
    sort($estatus_filtro);
    // --- FIN: OBTENER DATOS PARA FILTROS ---
    
    if (!isset($_SESSION['loggedin']) || !$_SESSION['loggedin']) {
        header("Location: index.php");
        exit();
    }
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<style>
    .table { background-color: #ffffff; border-radius: 10px; overflow: hidden; }
    .table th { background-color: #333; color: #ffffff; padding: 10px; border-bottom: 1px solid #3498db; }
    .table td { padding: 10px; border-bottom: 1px solid #dddddd; vertical-align: middle; }
    .container { max-width: 100%; }
    .dt-buttons .btn { background-color: #3498db; border-color: #3498db; color: #fff; font-weight: bold; padding: 10px 20px; transition: background-color 0.3s ease; border-radius: 5px; }
    .dt-buttons .btn:hover { background-color: #2980b9; border-color: #2980b9; cursor: pointer; }
    
    .btn-historial {
        color: #ffffff !important;
        padding: 5px 10px;
        border-radius: 5px;
        text-decoration: none !important;
        font-weight: bold;
        transition: background-color 0.3s ease;
        border: none;
        cursor: pointer;
        font-size: 0.9em;
        background-color: #3498db;
    }
    .btn-historial:hover { background-color: #2980b9; color: #ffffff !important; }

    .badge-status { padding: 5px 12px; border-radius: 15px; color: white; font-weight: bold; font-size: 0.9em; text-shadow: 1px 1px 1px rgba(0,0,0,0.2); }
    .status-nueva_solicitud { background-color: #3498db; }
    .status-pend_revision_solicitante { background-color: #f1c40f; }
    .status-proceso { background-color: #e67e22; }
    .status-pendcompra_de_recursos { background-color: #9b59b6; }
    .status-entrega_de_recursos { background-color: #1abc9c; }
    .status-cierre_de_proceso { background-color: #34495e; }
    .status-fin { background-color: #2ecc71; }

    .historial-tabla { width: 100%; border-collapse: collapse; text-align: left; }
    .historial-tabla th, .historial-tabla td { padding: 8px 12px; border: 1px solid #ddd; }
    .historial-tabla th { background-color: #f2f2f2; font-weight: bold; color: #333; }
    .historial-tabla tr:nth-child(even) { background-color: #f9f9f9; }
    .swal2-html-container { max-height: 400px; overflow-y: auto; overflow-x: hidden; padding: 0.5em; }
</style>
<body>
    
    <div class="container">
        <div>
            <br>
            <h1 class="text-center mb-3">Todas las Solicitudes de Altas, Bajas y Cambios</h1><br>
            
             <!-- --- INICIO: SECCIÓN DE FILTROS --- -->
            <div class="row g-3 mb-4">
                <div class="col-md-4">
                    <label for="filtro_sucursal" class="form-label">Filtrar por Sucursal</label>
                    <select id="filtro_sucursal" class="form-select">
                        <option value="">Todas</option>
                        <option value="N/A">No asignada</option>
                        <?php foreach ($sucursales_filtro as $sucursal): if(empty($sucursal)) continue; ?>
                            <option value="<?= htmlspecialchars($sucursal) ?>"><?= htmlspecialchars($sucursal) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label for="filtro_tipo" class="form-label">Filtrar por Tipo</label>
                    <select id="filtro_tipo" class="form-select">
                        <option value="">Todos</option>
                         <?php foreach ($tipos_filtro as $tipo): ?>
                            <option value="<?= htmlspecialchars($tipo) ?>"><?= htmlspecialchars($tipo) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label for="filtro_estatus" class="form-label">Filtrar por Estatus</label>
                    <select id="filtro_estatus" class="form-select">
                        <option value="">Todos</option>
                        <?php foreach ($estatus_filtro as $estatus): ?>
                            <option value="<?= htmlspecialchars($estatus) ?>"><?= htmlspecialchars($estatus) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <!-- --- FIN: SECCIÓN DE FILTROS --- -->

            <table class="table table-bordered table-striped" id="pedidos">
                <thead>
                    <tr style="text-align:center;">
                        <th>Folio</th>
                        <th>Fecha de Solicitud</th>
                        <th>Solicitante</th>
                        <th>Sucursal</th>
                        <th>Tipo de Solicitud</th>
                        <th>Estatus</th>
                        <th>Acción</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    if (!empty($mpersonal)) {
                        foreach ($mpersonal as $personal) {
                            echo "<tr>";
                            echo "<td style='text-align:center;'><a href='ABC_detalle_solicitud.php?doc=" . urlencode($personal['id']) . "'>" . htmlspecialchars($personal['folio']) . "</a></td>";
                            echo "<td style='text-align:center;'>" . date('d-m-Y', strtotime($personal['fecha_solicitud'])) . "</td>";
                            echo "<td style='text-align:center;'>" . htmlspecialchars($personal['solicitante']) . "</td>";
                            echo "<td style='text-align:center;'>" . htmlspecialchars($personal['sucursal'] ?? 'N/A') . "</td>";
                            echo "<td style='text-align:center;'>" . htmlspecialchars($personal['tipo_solicitud']) . "</td>";
                            
                            $estatus = $personal['estatus'];
                            $clase_css_estatus = 'status-' . strtolower(str_replace([' ', '.'], ['_', ''], $estatus));
                            echo "<td style='text-align:center;'><span class='badge-status " . $clase_css_estatus . "'>" . htmlspecialchars($estatus) . "</span></td>";
                            
                            echo "<td style='text-align:center;'>";
                            echo "<button class='btn-historial' onclick='mostrarHistorial(this)' data-id='" . $personal['id'] . "' data-folio='" . $personal['folio'] . "'>Historial</button>";
                            echo "</td>";

                            echo "</tr>";
                        }
                    } 
                    ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <script>
        $(document).ready(function() {
            var table = $('#pedidos').DataTable({
                "language": { "url": "//cdn.datatables.net/plug-ins/1.10.16/i18n/Spanish.json" },
                "pageLength": 10,
                "lengthMenu": [10, 25, 50],
                "responsive": true,
                "order": [[0, "desc"]],
                "processing": true,
                "columnDefs": [ { "orderable": false, "targets": [2,3,4,5,6] } ],
                dom: 'lBfrtip',
                buttons: [
                    { extend: 'excelHtml5', text: 'Exportar a Excel', className: 'btn btn-success btn-lg m-2' },
                    { extend: 'print', text: 'Imprimir', className: 'btn btn-info btn-lg m-2' }
                ]
            });

             // --- INICIO: SCRIPT PARA FILTRADO DINÁMICO Y ESPECÍFICO ---
            $('#filtro_sucursal, #filtro_tipo, #filtro_estatus').on('change', function() {
                let sucursal = $('#filtro_sucursal').val();
                let tipo = $('#filtro_tipo').val();
                let estatus = $('#filtro_estatus').val();
                
                // Aplicar cada filtro con búsqueda exacta (regex)
                table.column(3).search(sucursal ? '^' + sucursal.replace(/[-\/\\^$*+?.()|[\]{}]/g, '\\$&') + '$' : '', true, false);
                table.column(4).search(tipo ? '^' + tipo.replace(/[-\/\\^$*+?.()|[\]{}]/g, '\\$&') + '$' : '', true, false);
                table.column(5).search(estatus ? '^' + estatus.replace(/[-\/\\^$*+?.()|[\]{}]/g, '\\$&') + '$' : '', true, false);
                
                table.draw();
            });
            // --- FIN: SCRIPT PARA FILTRADO ---
        });

        function mostrarHistorial(btn) {
            const id = btn.dataset.id;
            const folio = btn.dataset.folio;

            Swal.fire({
                title: 'Cargando historial...',
                text: `Buscando registros para la solicitud #${folio}`,
                allowOutsideClick: false,
                didOpen: () => Swal.showLoading()
            });

            $.ajax({
                url: 'ABC_controller/obtener_historial.php',
                type: 'GET',
                data: { id: id },
                dataType: 'json',
                success: function(response) {
                    if (response.success && response.data.length > 0) {
                        let htmlTabla = `<table class="historial-tabla"><thead><tr><th>Fecha</th><th>Usuario</th><th>Nuevo Estatus</th><th>Observación</th></tr></thead><tbody>`;
                        response.data.forEach(item => {
                            const fecha = new Date(item.fecha_hora).toLocaleString('es-MX', { year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit' });
                            const observacion = item.observacion || '<em>Sin observación</em>';
                            htmlTabla += `<tr><td>${fecha.replace(',', '')}</td><td>${item.usuario_nombre}</td><td>${item.estatus_cambio}</td><td>${observacion}</td></tr>`;
                        });
                        htmlTabla += '</tbody></table>';

                        Swal.fire({ title: `Historial de la Solicitud #${folio}`, html: htmlTabla, width: '800px' });
                    } else {
                        Swal.fire({ title: `Historial de la Solicitud #${folio}`, text: 'No se encontraron registros de historial para esta solicitud.', icon: 'warning' });
                    }
                },
                error: function(jqXHR, textStatus, errorThrown) {
                    Swal.fire('Error', 'No se pudo obtener el historial. Revisa la consola para más detalles.', 'error');
                }
            });
        }
    </script>
    
</body>
</html>

<?php
include("src/templates/adminfooter.php");
?>

