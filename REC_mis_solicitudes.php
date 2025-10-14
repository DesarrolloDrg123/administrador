<?php
    session_start();
    
    require("config/db.php");
    include("src/templates/adminheader.php");
    
    $nombre_solicitante = $_SESSION['nombre'];
    
    // Funci√≥n para obtener las solicitudes de vacantes del usuario actual
    function obtenerMisSolicitudesVacantes($conn, $nombre) {
        $nombre = $conn->real_escape_string($nombre);
        // Se consulta la nueva tabla 'solicitudes_vacantes'
        $sql = "SELECT 
                    solicitud_id, 
                    folio, 
                    estatus, 
                    fecha_hora_solicitud, 
                    solicitante, 
                    puesto_solicitado,
                    tipo_vacante,
                    requisitos
                FROM solicitudes_vacantes 
                WHERE solicitante = '$nombre' 
                ORDER BY solicitud_id DESC";
        $result = $conn->query($sql);
        return ($result && $result->num_rows > 0) ? $result->fetch_all(MYSQLI_ASSOC) : [];
    }

    $mis_solicitudes = obtenerMisSolicitudesVacantes($conn, $nombre_solicitante);

    // --- Obtener datos √∫nicos para los filtros ---
    $puestos_filtro = array_unique(array_column($mis_solicitudes, 'puesto_solicitado'));
    $estatus_filtro = array_unique(array_column($mis_solicitudes, 'estatus'));
    $tipo_filtro = array_unique(array_column($mis_solicitudes, 'tipo_vacante'));
    sort($puestos_filtro);
    sort($estatus_filtro);
    sort($tipo_filtro);
    
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
    /* --- Tus estilos se mantienen igual, no es necesario cambiarlos --- */
    .table { background-color: #ffffff; border-radius: 10px; overflow: hidden; }
    .table th { background-color: #333; color: #ffffff; }
    .table td { vertical-align: middle; }
    .container { max-width: 100%; }
    .btn-historial {
        color: #ffffff !important; padding: 5px 10px; border-radius: 5px; text-decoration: none !important;
        font-weight: bold; transition: background-color 0.3s ease; border: none; cursor: pointer;
        font-size: 0.9em; margin: 0 2px; background-color: #3498db;
    }
    .btn-historial:hover { background-color: #2980b9; color: #ffffff !important; }
    .badge-status { padding: 5px 12px; border-radius: 15px; color: white; font-weight: bold; font-size: 0.9em; }
    /* Estatus para Reclutamiento */
    .status_nueva_solicitud { background-color: #3498db; }
    .status_autorizada { background-color: #27ae60; }
    .status_rechazada { background-color: #e74c3c; }
    .status_publicada { background-color: #9b59b6; }
    .status_en_proceso_de_seleccion { background-color: #f39c12; }
    .status_finalizada { background-color: #2c3e50; }
    .historial-tabla { width: 100%; border-collapse: collapse; text-align: left; }
    .historial-tabla th, .historial-tabla td { padding: 8px 12px; border: 1px solid #ddd; }
    .historial-tabla th { background-color: #f2f2f2; }
    .swal2-html-container { max-height: 400px; overflow-y: auto; text-align: left; }
</style>
<body>
    
    <div class="container">
        <div>
            <br>
            <h1 class="text-center mb-3">Mis Solicitudes de Vacantes</h1><br>

            <div class="row g-3 mb-4">
                <div class="col-md-4">
                    <label for="filtro_puesto" class="form-label">Filtrar por Puesto</label>
                    <select id="filtro_puesto" class="form-select">
                        <option value="">Todos</option>
                        <?php foreach ($puestos_filtro as $puesto): ?>
                            <option value="<?= htmlspecialchars($puesto) ?>"><?= htmlspecialchars($puesto) ?></option>
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
                <div class="col-md-4">
                    <label for="filtro_tipo" class="form-label">Filtrar por Tipo de Vacante</label>
                    <select id="filtro_tipo" class="form-select">
                        <option value="">Todos</option>
                        <?php foreach ($tipo_filtro as $tipo): ?>
                            <option value="<?= htmlspecialchars($tipo) ?>"><?= htmlspecialchars($tipo) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <table class="table table-bordered table-striped" id="tabla-solicitudes">
                <thead>
                    <tr style="text-align:center;">
                        <th>Folio</th>
                        <th>Fecha</th>
                        <th>Solicitante</th>
                        <th>Puesto Solicitado</th>
                        <th>Requisitos</th>
                        <th>Tipo de Vacante</th>
                        <th>Estatus</th>
                        <th>Acci√≥n</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    if (!empty($mis_solicitudes)) {
                        foreach ($mis_solicitudes as $solicitud) {
                            echo "<tr>";
                            // El enlace ahora apunta a un detalle de vacante
                            echo "<td style='text-align:center;'><a href='REC_detalle_vacante.php?id=" . urlencode($solicitud['solicitud_id']) . "'>" . htmlspecialchars($solicitud['folio']) . "</a></td>";
                            echo "<td style='text-align:center;'>" . date('d-m-Y', strtotime($solicitud['fecha_hora_solicitud'])) . "</td>";
                            echo "<td style='text-align:center;'>" . htmlspecialchars($solicitud['solicitante']) . "</td>";
                            echo "<td style='text-align:center;'>" . htmlspecialchars($solicitud['puesto_solicitado']) . "</td>";
                            echo "<td style='text-align:center;'>" . htmlspecialchars($solicitud['requisitos']) . "</td>";
                            echo "<td style='text-align:center;'>" . htmlspecialchars($solicitud['tipo_vacante']) . "</td>";
                            
                            $estatus = $solicitud['estatus'];
                            // Crea una clase CSS a partir del estatus (ej. "Nueva Solicitud" -> "status_nueva_solicitud")
                            $clase_css_estatus = 'status_' . strtolower(str_replace(' ', '_', $estatus));
                            echo "<td style='text-align:center;'><span class='badge-status " . $clase_css_estatus . "'>" . htmlspecialchars($estatus) . "</span></td>";
                            
                            echo "<td style='text-align:center;'>";
                            // El data-id ahora usa 'solicitud_id'
                            echo "<button class='btn-historial' onclick='mostrarHistorial(this)' data-id='" . $solicitud['solicitud_id'] . "' data-folio='" . $solicitud['folio'] . "'>Historial</button>";
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
            var table = $('#tabla-solicitudes').DataTable({
                "language": { "url": "//cdn.datatables.net/plug-ins/1.10.16/i18n/Spanish.json" },
                "pageLength": 10,
                "lengthMenu": [10, 25, 50],
                "responsive": true,
                "order": [[0, "desc"]],
                "columnDefs": [ { "orderable": false, "targets": [2,3,4,5] } ]
            });

            // --- Se actualiza el script de filtrado para incluir el nuevo campo ---
            $('#filtro_solicitante, #filtro_puesto, #filtro_estatus, #filtro_tipo').on('change', function() {
                let puesto = $('#filtro_puesto').val();
                let tipo = $('#filtro_tipo').val();
                let estatus = $('#filtro_estatus').val();
                
                // Se ajustan los ®™ndices de columna para el filtro
                table.column(3).search(puesto ? '^' + puesto.replace(/[-\/\\^$*+?.()|[\]{}]/g, '\\$&') + '$' : '', true, false);
                table.column(5).search(tipo ? '^' + tipo.replace(/[-\/\\^$*+?.()|[\]{}]/g, '\\$&') + '$' : '', true, false);
                table.column(6).search(estatus ? '^' + estatus.replace(/[-\/\\^$*+?.()|[\]{}]/g, '\\$&') + '$' : '', true, false);
                
                table.draw();
            });
        });

        function mostrarHistorial(btn) {
            const id = btn.dataset.id;
            const folio = btn.dataset.folio;

            Swal.fire({
                title: 'Cargando historial...',
                allowOutsideClick: false,
                didOpen: () => Swal.showLoading()
            });

            $.ajax({
                // La URL ahora apunta al controlador del historial de reclutamiento
                url: 'REC_controller/obtener_historial_vacante.php',
                type: 'GET',
                data: { id: id },
                dataType: 'json',
                success: function(response) {
                    if (response.success && response.data.length > 0) {
                        let htmlTabla = `<table class="historial-tabla"><thead><tr><th>Fecha</th><th>Usuario</th><th>Estatus Nuevo</th><th>Comentarios</th></tr></thead><tbody>`;
                        response.data.forEach(item => {
                            // Se asume que el JSON de respuesta tiene estos campos
                            const fecha = new Date(item.fecha_accion).toLocaleString('es-MX');
                            const comentarios = item.comentarios || '<em>N/A</em>';
                            htmlTabla += `<tr><td>${fecha}</td><td>${item.usuario_accion}</td><td>${item.estatus_nuevo}</td><td>${comentarios}</td></tr>`;
                        });
                        htmlTabla += '</tbody></table>';

                        Swal.fire({ title: `Historial de la Solicitud #${folio}`, html: htmlTabla, width: '800px' });
                    } else {
                        Swal.fire({ title: `Historial de la Solicitud #${folio}`, text: 'No se encontraron registros de historial.', icon: 'info' });
                    }
                },
                error: function() {
                    Swal.fire('Error', 'No se pudo obtener el historial.', 'error');
                }
            });
        }
    </script>
    
</body>
</html>
<?php
include("src/templates/adminfooter.php");
?>