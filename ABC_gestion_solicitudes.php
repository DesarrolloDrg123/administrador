<?php
    session_start();
    
    require("config/db.php");
    include("src/templates/adminheader.php");
    
    $nombre = $_SESSION['nombre'];
    
    function TodosMovimientosPerso($conn) {
        $sql = "SELECT 
                    mp.id, mp.folio, mp.estatus, mp.fecha_solicitud, mp.solicitante,
                    mp.tipo_solicitud, s.sucursal
                FROM 
                    solicitudes_movimientos_personal mp
                LEFT JOIN 
                    sucursales s ON mp.sucursal_id = s.id
                ORDER BY mp.id DESC";
    
        $result = $conn->query($sql);
        
        if ($result && $result->num_rows > 0) {
            $mpersonal = [];
            while ($row = $result->fetch_assoc()) {
                $mpersonal[] = $row;
            }
            return $mpersonal;
        } else {
            return [];
        }
    }
    
    $mpersonal = TodosMovimientosPerso($conn);
    
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
    
    .badge-status { padding: 5px 12px; border-radius: 15px; color: white; font-weight: bold; font-size: 0.9em; text-shadow: 1px 1px 1px rgba(0,0,0,0.2); }
    /* Estatus Flujo ALTA */
    .status_nueva_solicitud { background-color: #3498db; }
    .status_pend_revision_solicitante { background-color: #f1c40f; }
    .status_proceso { background-color: #e67e22; }
    .status_pend_compra_de_recursos { background-color: #9b59b6; }
    .status_entrega_de_recursos { background-color: #1abc9c; }
    /* Estatus Flujo BAJA */
    .status_pend_confirmacion_baja_th { background-color: #e74c3c; } /* Nuevo estatus */
    .status_pend_quitar_accesos { background-color: #f39c12; }
    .status_gestion_baja { background-color: #8e44ad; }
    /* Estatus Compartidos */
    .status_cierre_de_proceso { background-color: #34495e; }
    .status_fin { background-color: #2ecc71; }
    
    .swal2-textarea { width: 90% !important; margin-top: 15px !important; }
</style>
<body>
    
    <div class="container">
        <div>
            <br>
            <h1 class="text-center mb-3">Gestión de Solicitudes de Altas, Bajas y Cambios</h1><br>
            
            <table class="table table-bordered table-striped" id="tabla_solicitudes">
                <thead>
                    <tr style="text-align:center;">
                        <th>Folio</th>
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
                            $id = $personal['id'];
                            $folio = $personal['folio'];
                            $estatus = $personal['estatus'];
                            $tipo_solicitud = $personal['tipo_solicitud'];

                            echo "<tr>";
                            echo "<td style='text-align:center;'><a href='ABC_detalle_solicitud.php?doc=" . urlencode($id) . "'>" . htmlspecialchars($folio) . "</a></td>";
                            echo "<td style='text-align:center;'>" . date('d-m-Y', strtotime($personal['fecha_solicitud'])) . "</td>";
                            echo "<td style='text-align:center;'>" . htmlspecialchars($personal['solicitante']) . "</td>";
                            echo "<td style='text-align:center;'>" . htmlspecialchars($personal['sucursal'] ?? 'N/A') . "</td>";
                            echo "<td style='text-align:center;'>" . htmlspecialchars($tipo_solicitud) . "</td>";
                            
                            $clase_css_estatus = 'status_' . strtolower(str_replace([' ', '.'], ['_', ''], $estatus));
                            echo "<td style='text-align:center;'><span class='badge-status " . $clase_css_estatus . "'>" . htmlspecialchars($estatus) . "</span></td>";

                            echo "<td style='text-align:center;'>";
                            
                            $tipos_alta_cambio = ['Alta de usuario', 'Alta por cambio de puesto', 'Alta por remplazo de usuario', 'Practicante', 'Cambio'];
                            $tipos_baja = ['Baja', 'Baja de usuario'];

                            if (in_array($tipo_solicitud, $tipos_alta_cambio)) {
                                // --- FLUJO PARA ALTAS Y CAMBIOS ---
                                switch ($estatus) {
                                    case 'Nueva Solicitud': echo "<button class='btn btn-primary' onclick='gestionarSolicitud(this)' data-id='$id' data-folio='$folio' data-estatus='$estatus' data-tipo='Alta'>Revisar</button>"; break;
                                    case 'Proceso': echo "<button class='btn btn-warning' onclick='gestionarSolicitud(this)' data-id='$id' data-folio='$folio' data-estatus='$estatus' data-tipo='Alta'>Gestionar</button>"; break;
                                    case 'Pend.Compra de Recursos': echo "<button class='btn btn-info' onclick='gestionarSolicitud(this)' data-id='$id' data-folio='$folio' data-estatus='$estatus' data-tipo='Alta'>Registrar Compra</button>"; break;
                                    case 'Entrega de Recursos': echo "<button class='btn btn-success' onclick='gestionarSolicitud(this)' data-id='$id' data-folio='$folio' data-estatus='$estatus' data-tipo='Alta'>Gestionar Entrega</button>"; break;
                                    case 'Cierre de Proceso': echo "<button class='btn btn-dark' onclick='gestionarSolicitud(this)' data-id='$id' data-folio='$folio' data-estatus='$estatus' data-tipo='Alta'>Cerrar</button>"; break;
                                    case 'Fin': echo "<button class='btn btn-light' disabled>Finalizado</button>"; break;
                                    case 'Pend. Revision Solicitante': echo "<span>N/A</span>"; break;
                                    default: echo "<span>-</span>"; break;
                                }
                            } else if (in_array($tipo_solicitud, $tipos_baja)) {
                                // --- FLUJO PARA BAJAS (LÓGICA CORREGIDA) ---
                                switch ($estatus) {
                                    case 'Nueva Solicitud': echo "<button class='btn btn-primary' onclick='gestionarSolicitud(this)' data-id='$id' data-folio='$folio' data-estatus='$estatus' data-tipo='Baja'>Revisar</button>"; break;
                                    case 'Pend. Confirmación Baja TH.': echo "<button class='btn btn-danger' onclick='gestionarSolicitud(this)' data-id='$id' data-folio='$folio' data-estatus='$estatus' data-tipo='Baja'>Confirmar con TH</button>"; break;
                                    case 'Pend. Quitar Accesos': echo "<button class='btn btn-warning' onclick='gestionarSolicitud(this)' data-id='$id' data-folio='$folio' data-estatus='$estatus' data-tipo='Baja'>Confirmar Accesos Removidos</button>"; break;
                                    case 'Gestion Baja': echo "<button class='btn btn-info' onclick='gestionarSolicitud(this)' data-id='$id' data-folio='$folio' data-estatus='$estatus' data-tipo='Baja'>Confirmar Activos Recibidos</button>"; break;
                                    case 'Cierre de Proceso': echo "<button class='btn btn-dark' onclick='gestionarSolicitud(this)' data-id='$id' data-folio='$folio' data-estatus='$estatus' data-tipo='Baja'>Cerrar</button>"; break;
                                    case 'Fin': echo "<button class='btn btn-light' disabled>Finalizado</button>"; break;
                                    case 'Pend. Revision Solicitante': echo "<span>N/A</span>"; break;
                                    default: echo "<span>-</span>"; break;
                                }
                            }
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
            $('#tabla_solicitudes').DataTable({
                "language": { "url": "//cdn.datatables.net/plug-ins/1.10.16/i18n/Spanish.json" },
                "pageLength": 10,
                "lengthMenu": [10, 25, 50],
                "responsive": true,
                "order": [[0, "desc"]]
            });
        });

        function gestionarSolicitud(btn) {
            const id = btn.dataset.id;
            const folio = btn.dataset.folio;
            const estatus = btn.dataset.estatus;
            const tipo = btn.dataset.tipo;

            const obs_html = '<textarea id="observaciones" class="swal2-textarea" placeholder="Añade observaciones (opcional)..."></textarea>';
            const preConfirmObs = () => ({ observaciones: document.getElementById('observaciones').value });

            switch (estatus) {
                case 'Nueva Solicitud':
                    Swal.fire({
                        title: `Revisar Solicitud #${folio}`, text: '¿La información es correcta y completa?', icon: 'question',
                        showDenyButton: true, showCancelButton: true, confirmButtonText: '✅ Sí, es correcta', denyButtonText: '❌ No, regresar', cancelButtonText: 'Cancelar'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            Swal.fire({ title: 'Aprobar Solicitud', input: 'textarea', inputLabel: 'Observaciones (opcional)', inputPlaceholder: 'Ej: Se procesará...', showCancelButton: true, confirmButtonText: 'Aprobar' })
                            .then((resultObs) => {
                                if (resultObs.isConfirmed) {
                                    const proximo_estatus = (tipo === 'Baja') ? 'Pend. Confirmación Baja TH.' : 'Proceso';
                                    actualizarEstatus(id, proximo_estatus, { observaciones: resultObs.value || '' });
                                }
                            });
                        } else if (result.isDenied) {
                            Swal.fire({ title: 'Motivo de la Devolución', input: 'textarea', inputLabel: 'Describe por qué se regresa la solicitud.', inputPlaceholder: 'Ej: Falta información...', showCancelButton: true, confirmButtonText: 'Enviar a Revisión', inputValidator: (v) => !v && 'El motivo es obligatorio.' })
                            .then((resultMotivo) => { if (resultMotivo.isConfirmed) { actualizarEstatus(id, 'Pend. Revision Solicitante', { observaciones: resultMotivo.value }); } });
                        }
                    });
                    break;

                case 'Proceso': // Solo para Altas
                    Swal.fire({ title: `Gestionar Recursos para #${folio}`, html: '¿Los recursos están en stock o se requiere compra?' + obs_html, icon: 'question', showDenyButton: true, confirmButtonText: '✅ Recursos Completos', denyButtonText: '🛒 Pasar a Compras', showCancelButton: true, preConfirm: preConfirmObs })
                    .then((result) => {
                        if (result.isConfirmed) { actualizarEstatus(id, 'Entrega de Recursos', { observaciones: result.value.observaciones }); }
                        else if (result.isDenied) { actualizarEstatus(id, 'Pend.Compra de Recursos', { observaciones: result.value.observaciones }); }
                    });
                    break;
                
                // --- LÓGICA CORREGIDA Y ORDENADA PARA FLUJO DE BAJA ---
                case 'Pend. Confirmación Baja TH.':
                    Swal.fire({ title: `Confirmar con TH para #${folio}`, html: 'Confirma si Talento Humano ya ha validado la baja del colaborador.' + obs_html, icon: 'warning', showCancelButton: true, confirmButtonText: 'Sí, TH confirma', preConfirm: preConfirmObs })
                    .then((result) => { if (result.isConfirmed) { actualizarEstatus(id, 'Pend. Quitar Accesos', { observaciones: result.value.observaciones }); } });
                    break;
                case 'Pend. Quitar Accesos':
                     Swal.fire({ title: `Confirmar Accesos Removidos para #${folio}`, html: 'Confirma la remoción de todos los accesos (sistemas, correo, etc.).' + obs_html, icon: 'info', showCancelButton: true, confirmButtonText: 'Confirmar', preConfirm: preConfirmObs })
                    .then((result) => { if (result.isConfirmed) { actualizarEstatus(id, 'Gestion Baja', { observaciones: result.value.observaciones }); } });
                    break;
                case 'Gestion Baja':
                    Swal.fire({ title: `Confirmar Activos Recibidos para #${folio}`, html: 'Confirma la recepción de todos los activos (laptop, celular, etc.) asignados.' + obs_html, icon: 'success', showCancelButton: true, confirmButtonText: 'Confirmar Recepción', preConfirm: preConfirmObs })
                    .then((result) => { if (result.isConfirmed) { actualizarEstatus(id, 'Cierre de Proceso', { observaciones: result.value.observaciones }); } });
                    break;

                // --- FLUJO DE ALTA/CAMBIO (SIN CAMBIOS) ---
                case 'Pend.Compra de Recursos':
                    Swal.fire({ title: `Confirmar Compra para #${folio}`, html: 'Confirma cuando los recursos hayan llegado físicamente.' + obs_html, icon: 'info', showCancelButton: true, confirmButtonText: 'Recursos Recibidos', preConfirm: preConfirmObs })
                    .then((result) => { if (result.isConfirmed) { actualizarEstatus(id, 'Entrega de Recursos', { observaciones: result.value.observaciones }); } });
                    break;
                case 'Entrega de Recursos':
                    Swal.fire({ title: `Confirmar Entrega #${folio}`, html: 'Confirma que los recursos han sido entregados al usuario.' + obs_html, icon: 'info', showCancelButton: true, confirmButtonText: 'Confirmar Entrega', preConfirm: preConfirmObs })
                    .then((result) => { if (result.isConfirmed) { actualizarEstatus(id, 'Cierre de Proceso', { observaciones: result.value.observaciones }); } });
                    break;

                // --- ESTATUS COMPARTIDOS ---
                case 'Cierre de Proceso':
                     Swal.fire({ title: `Finalizar Solicitud #${folio}`, html: 'Esto cerrará el proceso. ¿Estás seguro?' + obs_html, icon: 'warning', showCancelButton: true, confirmButtonText: 'Sí, cerrar y notificar', preConfirm: preConfirmObs })
                     .then((result) => { if (result.isConfirmed) { actualizarEstatus(id, 'Fin', { observaciones: result.value.observaciones }); } });
                    break;
            }
        }
        
        function actualizarEstatus(id, nuevo_estatus, datos_adicionales = {}) {
            let postData = { id: id, nuevo_estatus: nuevo_estatus, ...datos_adicionales };
            $.ajax({
                url: 'ABC_controller/actualizar_estatus.php', type: 'POST', data: postData, dataType: 'json',
                success: (response) => {
                    if (response.success) Swal.fire('¡Éxito!', response.message, 'success').then(() => location.reload());
                    else Swal.fire('Error', response.message, 'error');
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

