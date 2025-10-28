<?php
    session_start();
    
    require("config/db.php");
    include("src/templates/adminheader.php");
    
    $nombre = $_SESSION['nombre'];
    
    // Función para obtener las solicitudes del usuario actual
    function MisMovimientosPerso($conn, $nombre) {
        $nombre = $conn->real_escape_string($nombre);
        $sql = "SELECT mp.id, mp.folio, mp.codigo_form, mp.estatus, mp.fecha_solicitud, mp.solicitante, mp.tipo_solicitud, s.sucursal FROM solicitudes_movimientos_personal mp LEFT JOIN sucursales s ON mp.sucursal_id = s.id WHERE mp.solicitante = '$nombre' ORDER BY mp.id DESC";
        $result = $conn->query($sql);
        return ($result && $result->num_rows > 0) ? $result->fetch_all(MYSQLI_ASSOC) : [];
    }

    $mpersonal = MisMovimientosPerso($conn, $nombre);

    // --- INICIO: OBTENER DATOS ÚNICOS PARA LOS FILTROS (basado en las solicitudes del usuario) ---
    $sucursales_filtro = array_unique(array_column($mpersonal, 'sucursal'));
    $tipos_filtro = array_unique(array_column($mpersonal, 'tipo_solicitud'));
    $estatus_filtro = array_unique(array_column($mpersonal, 'estatus'));
    sort($sucursales_filtro);
    sort($tipos_filtro);
    sort($estatus_filtro);
    // --- FIN: LÓGICA PARA FILTROS ---
    
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

    .btn-editar, .btn-historial {
        color: #ffffff !important; padding: 5px 10px; border-radius: 5px; text-decoration: none !important;
        font-weight: bold; transition: background-color 0.3s ease; border: none; cursor: pointer;
        font-size: 0.9em; margin: 0 2px;
    }
    .btn-editar { background-color: #f1c40f; }
    .btn-editar:hover { background-color: #f39c12; color: #ffffff !important; }
    .btn-historial { background-color: #3498db; }
    .btn-historial:hover { background-color: #2980b9; color: #ffffff !important; }

    .badge-status { padding: 5px 12px; border-radius: 15px; color: white; font-weight: bold; font-size: 0.9em; text-shadow: 1px 1px 1px rgba(0,0,0,0.2); }
    .status_nueva_solicitud { background-color: #3498db; }
    .status_pend_revision_solicitante { background-color: #f1c40f; }
    .status_proceso { background-color: #e67e22; }
    .status_pend_compra_de_recursos, .status_pend_confirmacion_baja_th, .status_pend_quitar_accesos, .status_gestion_baja { background-color: #9b59b6; }
    .status_entrega_de_recursos { background-color: #1abc9c; }
    .status_cierre_de_proceso { background-color: #34495e; }
    .status_fin { background-color: #2ecc71; }

    .historial-tabla { width: 100%; border-collapse: collapse; text-align: left; }
    .historial-tabla th, .historial-tabla td { padding: 8px 12px; border: 1px solid #ddd; }
    .historial-tabla th { background-color: #f2f2f2; font-weight: bold; color: #333; }
    .swal2-html-container { max-height: 400px; overflow-y: auto; overflow-x: hidden; padding: 0.5em; }
</style>
<body>
    
    <div class="container">
        <div>
            <br>
            <h1 class="text-center mb-3">Mis Solicitudes de Altas Bajas y Cambios</h1><br>

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
                        <th>Codigo</th>
                        <th>Fecha</th>
                        <th>Solicitante</th>
                        <th>Sucursal</th>
                        <th>Tipo</th>
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
                            echo "<td style='text-align:center;'>" . htmlspecialchars($personal['codigo_form']) . "</td>";
                            echo "<td style='text-align:center;'>" . date('d-m-Y', strtotime($personal['fecha_solicitud'])) . "</td>";
                            echo "<td style='text-align:center;'>" . htmlspecialchars($personal['solicitante']) . "</td>";
                            $sucursal_texto = !empty($personal['sucursal']) ? $personal['sucursal'] : 'N/A';
                            echo "<td style='text-align:center;'>" . htmlspecialchars($sucursal_texto) . "</td>";
                            echo "<td style='text-align:center;'>" . htmlspecialchars($personal['tipo_solicitud']) . "</td>";
                            
                            $estatus = $personal['estatus'];
                            $clase_css_estatus = 'status_' . strtolower(str_replace(' ', '_', str_replace(['.', '-'], '', $estatus)));
                            echo "<td style='text-align:center;'><span class='badge-status " . $clase_css_estatus . "'>" . htmlspecialchars($estatus) . "</span></td>";
                            
                            echo "<td style='text-align:center;'>";
                            if ($estatus == 'Pend. Revision Solicitante') {
                                echo "<a href='ABC_formulario_solicitud.php?id_solicitud=" . urlencode($personal['id']) . "' class='btn-editar'>Editar</a>";
                            }
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
                "columnDefs": [ { "orderable": false, "targets": [1,2,3,4,5,6] } ]
            });

            // --- INICIO: SCRIPT PARA FILTRADO DINÁMICO Y ESPECÍFICO ---
            $('#filtro_sucursal, #filtro_tipo, #filtro_estatus').on('change', function() {
                let sucursal = $('#filtro_sucursal').val();
                let tipo = $('#filtro_tipo').val();
                let estatus = $('#filtro_estatus').val();
                
                table.column(4).search(sucursal ? '^' + sucursal.replace(/[-\/\\^$*+?.()|[\]{}]/g, '\\$&') + '$' : '', true, false);
                table.column(5).search(tipo ? '^' + tipo.replace(/[-\/\\^$*+?.()|[\]{}]/g, '\\$&') + '$' : '', true, false);
                table.column(6).search(estatus ? '^' + estatus.replace(/[-\/\\^$*+?.()|[\]{}]/g, '\\$&') + '$' : '', true, false);
                
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
                        Swal.fire({ title: `Historial de la Solicitud #${folio}`, text: 'No se encontraron registros de historial.', icon: 'warning' });
                    }
                },
                error: function() {
                    Swal.fire('Error', 'No se pudo obtener el historial. Inténtalo de nuevo.', 'error');
                }
            });
        }
    </script>
    
</body>
</html>
<?php
include("src/templates/adminfooter.php");
?>

