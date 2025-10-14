<?php
    session_start();
    
    require("config/db.php");
    include("src/templates/adminheader.php");
    
    // Función para obtener TODAS las solicitudes de vacantes
    function obtenerTodasLasSolicitudes($conn) {
        $sql = "SELECT 
                    sv.solicitud_id, 
                    sv.folio, 
                    sv.estatus, 
                    sv.fecha_hora_solicitud, 
                    sv.solicitante, 
                    sv.puesto_solicitado,
                    sv.tipo_vacante,
                    COUNT(sc.candidato_id) AS total_candidatos
                FROM 
                    solicitudes_vacantes sv
                LEFT JOIN 
                    solicitudes_vacantes_candidatos sc ON sv.solicitud_id = sc.solicitud_id
                GROUP BY
                    sv.solicitud_id
                ORDER BY 
                    sv.solicitud_id DESC";
        $result = $conn->query($sql);
        return ($result && $result->num_rows > 0) ? $result->fetch_all(MYSQLI_ASSOC) : [];
    }
    
    $solicitudes = obtenerTodasLasSolicitudes($conn);
    
    // --- Obtener datos únicos para los filtros (ahora de TODAS las solicitudes) ---
    $solicitantes_filtro = array_unique(array_column($solicitudes, 'solicitante'));
    $puestos_filtro = array_unique(array_column($solicitudes, 'puesto_solicitado'));
    $estatus_filtro = array_unique(array_column($solicitudes, 'estatus'));
    $tipo_filtro = array_unique(array_column($solicitudes, 'tipo_vacante'));
    sort($solicitantes_filtro);
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
    .table { background-color: #ffffff; border-radius: 10px; overflow: hidden; }
    .table th { background-color: #333; color: #ffffff; }
    .table td { vertical-align: middle; }
    .container { max-width: 100%; }
    
    .card {  max-width: 1600px  }
    
    .badge-status { padding: 5px 12px; border-radius: 15px; color: white; font-weight: bold; font-size: 0.9em; }
    /* Estatus para Reclutamiento */
    .status_nueva_solicitud { background-color: #3498db; }
    .status_autorizada { background-color: #27ae60; }
    .status_rechazada { background-color: #e74c3c; }
    .status_publicada { background-color: #9b59b6; }
    .status_en_proceso_de_seleccion { background-color: #f39c12; }
    .status_finalizada { background-color: #2c3e50; }

    .swal2-textarea { width: 90% !important; margin-top: 15px !important; }
</style>
<body>
    
    <div class="container">
        <div>
            <br>
            <h1 class="text-center mb-3">Panel de Gestión de Reclutamiento</h1><br>
            
            <div class="row g-3 mb-4">
                <div class="col-md-3">
                    <label for="filtro_solicitante" class="form-label">Filtrar por Solicitante</label>
                    <select id="filtro_solicitante" class="form-select">
                        <option value="">Todos</option>
                        <?php foreach ($solicitantes_filtro as $solicitante): ?>
                            <option value="<?= htmlspecialchars($solicitante) ?>"><?= htmlspecialchars($solicitante) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="filtro_puesto" class="form-label">Filtrar por Puesto</label>
                    <select id="filtro_puesto" class="form-select">
                        <option value="">Todos</option>
                        <?php foreach ($puestos_filtro as $puesto): ?>
                            <option value="<?= htmlspecialchars($puesto) ?>"><?= htmlspecialchars($puesto) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="filtro_estatus" class="form-label">Filtrar por Estatus</label>
                    <select id="filtro_estatus" class="form-select">
                        <option value="">Todos</option>
                        <?php foreach ($estatus_filtro as $estatus): ?>
                            <option value="<?= htmlspecialchars($estatus) ?>"><?= htmlspecialchars($estatus) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="filtro_tipo" class="form-label">Filtrar por Tipo de Vacante</label>
                    <select id="filtro_tipo" class="form-select">
                        <option value="">Todos</option>
                        <?php foreach ($tipo_filtro as $tipo): ?>
                            <option value="<?= htmlspecialchars($tipo) ?>"><?= htmlspecialchars($tipo) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <table class="table table-bordered table-striped" id="tabla_solicitudes">
                <thead>
                    <tr style="text-align:center;">
                        <th>Folio</th>
                        <th>Fecha</th>
                        <th>Solicitante</th>
                        <th>Puesto Solicitado</th>
                        <th>Tipo</th>
                        <th>Postulaciones</th>
                        <th>Estatus</th>
                        <th>Acción</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($solicitudes)): ?>
                        <?php foreach ($solicitudes as $solicitud):
                            $id = $solicitud['solicitud_id'];
                            $folio = $solicitud['folio'];
                            $estatus = $solicitud['estatus'];
                            $clase_css = 'status_' . strtolower(str_replace(' ', '_', $estatus));
                        ?>
                            <tr>
                                <td style="text-align:center;"><a href="REC_detalle_vacante.php?id=<?= urlencode($id) ?>"><?= htmlspecialchars($folio) ?></a></td>
                                <td style="text-align:center;"><?= date('d-m-Y', strtotime($solicitud['fecha_hora_solicitud'])) ?></td>
                                <td style="text-align:center;"><?= htmlspecialchars($solicitud['solicitante']) ?></td>
                                <td style="text-align:center;"><?= htmlspecialchars($solicitud['puesto_solicitado']) ?></td>
                                <td style="text-align:center;"><?= htmlspecialchars($solicitud['tipo_vacante']) ?></td>
                                <td style="text-align:center;">
                                    <a href="REC_ver_candidatos.php?id=<?= urlencode($solicitud['solicitud_id']) ?>" class="btn btn-outline-dark btn-sm">
                                        Ver (<?= $solicitud['total_candidatos'] ?>)
                                    </a>
                                </td>
                                <td style="text-align:center;"><span class="badge-status <?= $clase_css ?>"><?= htmlspecialchars($estatus) ?></span></td>
                                <td style="text-align:center;">
                                    <?php
                                    // Lógica para mostrar el botón de acción según el estatus
                                    switch ($estatus) {
                                        case 'Nueva Solicitud':
                                            echo "<button class='btn btn-primary' onclick='gestionarVacante(this)' data-id='$id' data-folio='$folio' data-estatus='$estatus'>Autorizar</button>";
                                            break;
                                        case 'Autorizada':
                                            echo "<button class='btn btn-info' onclick='gestionarVacante(this)' data-id='$id' data-folio='$folio' data-estatus='$estatus'>Publicar Vacante</button>";
                                            break;
                                        case 'Publicada':
                                            echo "<button class='btn btn-secondary' onclick='gestionarVacante(this)' data-id='$id' data-folio='$folio' data-estatus='$estatus'>Iniciar Entrevistas</button>";
                                            break;
                                        case 'En Proceso de Seleccion':
                                            echo "<button class='btn btn-warning' onclick='gestionarVacante(this)' data-id='$id' data-folio='$folio' data-estatus='$estatus'>Candidato Seleccionado</button>";
                                            break;
                                        case 'Finalizada':
                                        case 'Rechazada':
                                            echo "<button class='btn btn-light' disabled>Finalizado</button>";
                                            break;
                                        default:
                                            echo "<span>-</span>";
                                            break;
                                    }
                                    ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <script>
        $(document).ready(function() {
            var table = $('#tabla_solicitudes').DataTable({
                "language": { "url": "//cdn.datatables.net/plug-ins/1.10.16/i18n/Spanish.json" },
                "pageLength": 10,
                "lengthMenu": [10, 25, 50],
                "responsive": true,
                "order": [[0, "desc"]],
                "columnDefs": [ { "orderable": false, "targets": [2,3,4,5] } ]
            });

            // --- Se actualiza el script de filtrado para incluir el nuevo campo ---
            $('#filtro_solicitante, #filtro_puesto, #filtro_estatus, #filtro_tipo').on('change', function() {
                let solicitante = $('#filtro_solicitante').val();
                let puesto = $('#filtro_puesto').val();
                let tipo = $('#filtro_tipo').val();
                let estatus = $('#filtro_estatus').val();
                
                // Se ajustan los índices de columna para el filtro
                table.column(2).search(solicitante ? '^' + solicitante.replace(/[-\/\\^$*+?.()|[\]{}]/g, '\\$&') + '$' : '', true, false);
                table.column(3).search(puesto ? '^' + puesto.replace(/[-\/\\^$*+?.()|[\]{}]/g, '\\$&') + '$' : '', true, false);
                table.column(4).search(tipo ? '^' + tipo.replace(/[-\/\\^$*+?.()|[\]{}]/g, '\\$&') + '$' : '', true, false);
                table.column(6).search(estatus ? '^' + estatus.replace(/[-\/\\^$*+?.()|[\]{}]/g, '\\$&') + '$' : '', true, false);
                
                table.draw();
            });
        });

        function gestionarVacante(btn) {
            const id = btn.dataset.id;
            const folio = btn.dataset.folio;
            const estatus = btn.dataset.estatus;
        
            const obs_html = '<textarea id="observaciones" class="swal2-textarea" placeholder="Añade un comentario (opcional)..."></textarea>';
            const preConfirmObs = () => ({ observaciones: document.getElementById('observaciones').value });
        
            switch (estatus) {
                case 'Nueva Solicitud':
                    Swal.fire({
                        title: `Autorizar Solicitud #${folio}`,
                        html: '¿Deseas autorizar esta solicitud para iniciar el proceso de reclutamiento?' + obs_html,
                        icon: 'question', showDenyButton: true, showCancelButton: true,
                        confirmButtonText: '✅ Autorizar', denyButtonText: '❌ Rechazar',
                        preConfirm: preConfirmObs
                    }).then((result) => {
                        if (result.isConfirmed) {
                            actualizarEstatus(id, 'Autorizada', { observaciones: result.value.observaciones });
                        } else if (result.isDenied) {
                            actualizarEstatus(id, 'Rechazada', { observaciones: 'Rechazada por la gerencia.' });
                        }
                    });
                    break;
                case 'Autorizada':
                    Swal.fire({
                        title: `Publicar Vacante #${folio}`,
                        html: `
                            <p class="swal2-content">Confirma que la vacante ha sido publicada. Opcionalmente, puedes añadir preguntas de filtrado para esta postulación.</p>
                            <div id="preguntas-container" class="text-start mt-3">
                                <div class="input-group mb-2">
                                    <input type="text" name="preguntas[]" class="form-control" placeholder="Escribe una pregunta...">
                                </div>
                            </div>
                            <button type="button" id="agregar-pregunta-btn" class="btn btn-outline-secondary btn-sm mt-2">+ Agregar otra pregunta</button>
                            <hr>
                            <textarea id="observaciones" class="swal2-textarea" placeholder="Añade un comentario (opcional)..."></textarea>
                        `,
                        icon: 'info',
                        showCancelButton: true,
                        confirmButtonText: 'Sí, Publicar y Guardar',
                        didOpen: () => {
                            // Lógica para el botón "+ Agregar otra pregunta"
                            document.getElementById('agregar-pregunta-btn').addEventListener('click', () => {
                                const container = document.getElementById('preguntas-container');
                                const newInputGroup = document.createElement('div');
                                newInputGroup.className = 'input-group mb-2';
                                newInputGroup.innerHTML = `
                                    <input type="text" name="preguntas[]" class="form-control" placeholder="Escribe otra pregunta...">
                                    <button type="button" class="btn btn-outline-danger btn-sm" onclick="this.parentElement.remove()">X</button>
                                `;
                                container.appendChild(newInputGroup);
                            });
                        },
                        preConfirm: () => {
                            // Recolectar todas las preguntas y las observaciones
                            const preguntas = Array.from(document.querySelectorAll('input[name="preguntas[]"]'))
                                .map(input => input.value)
                                .filter(value => value.trim() !== ''); // Ignorar preguntas vacías
                            
                            return {
                                observaciones: document.getElementById('observaciones').value,
                                preguntas: preguntas // Este es el array de preguntas
                            };
                        }
                    }).then((result) => {
                        if (result.isConfirmed) {
                            // Enviamos los datos recolectados (observaciones y preguntas) al backend
                            actualizarEstatus(id, 'Publicada', result.value);
                        }
                    });
                    break;
                case 'Publicada':
                    Swal.fire({ title: `Iniciar Selección #${folio}`, html: 'Esto marca el inicio de la fase de revisión y selección de candidatos.' + obs_html, icon: 'info', showCancelButton: true, confirmButtonText: 'Iniciar Proceso', preConfirm: preConfirmObs})
                    .then((r) => { if(r.isConfirmed) actualizarEstatus(id, 'En Proceso de Seleccion', { observaciones: r.value.observaciones }); });
                    break;
                case 'En Proceso de Seleccion':
                     Swal.fire({ title: `Candidato Contratado #${folio}`, html: 'Confirma que un candidato ha sido seleccionado y contratado para esta vacante.' + obs_html, icon: 'success', showCancelButton: true, confirmButtonText: 'Sí, Contratado', preConfirm: preConfirmObs})
                    .then((r) => { if(r.isConfirmed) actualizarEstatus(id, 'Finalizada', { observaciones: r.value.observaciones }); });
                    break;
                // Los casos 'Finalizada' y 'Rechazada' ya no necesitan un botón de acción, se manejan en el PHP.
            }
        }
        
        function actualizarEstatus(id, nuevo_estatus, datos_adicionales = {}) {
            // Apunta al nuevo controlador para vacantes
            const postData = { id: id, nuevo_estatus: nuevo_estatus, ...datos_adicionales };
            $.ajax({
                url: 'REC_controller/actualizar_estatus_vacante.php',
                type: 'POST',
                data: postData,
                dataType: 'json',
                success: (response) => {
                    if (response.success) {
                        Swal.fire('¡Éxito!', response.message, 'success').then(() => location.reload());
                    } else {
                        Swal.fire('Error', response.message, 'error');
                    }
                },
                error: () => Swal.fire('Error', 'No se pudo comunicar con el servidor.', 'error')
            });
        }
    </script>
    
</body>
</html>
<?php
include("src/templates/adminfooter.php");
?>