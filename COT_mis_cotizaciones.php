<?php
    session_start();
    
    require("config/db.php");
    include("src/templates/adminheader.php");
    
    // Verifica si el usuario está logueado, si no, lo redirige
    if (!isset($_SESSION['loggedin']) || !$_SESSION['loggedin']) {
        header("Location: index.php");
        exit();
    }

    $nombre = $_SESSION['nombre'];
    
    function ObtenerCotizaciones($conn, $nombre) {
        // CAMBIO: La consulta ahora apunta a `datos_generales_co` y usa consultas preparadas
        $sql = "SELECT * FROM datos_generales_co 
                WHERE user_solicitante = ? 
                ORDER BY folio DESC";
        
        $stmt = $conn->prepare($sql);
        if ($stmt === false) {
            // Manejar error de preparación
            return [];
        }
        
        // Vincular el parámetro de forma segura
        $stmt->bind_param("s", $nombre);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $cotizaciones = [];
            while ($row = $result->fetch_assoc()) {
                $cotizaciones[] = $row;
            }
            return $cotizaciones;
        } else {
            return []; // Retorna un array vacío si no se encontraron registros
        }
    }
    
    // Obtener las cotizaciones del usuario actual
    $cotizaciones = ObtenerCotizaciones($conn, $nombre);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<style>
    /* Tus estilos CSS (sin cambios) */
    .table { background-color: #ffffff; border-radius: 10px; overflow: hidden; }
    .table th { background-color: #333; color: #ffffff; padding: 10px; border-bottom: 1px solid #3498db; }
    .table td { padding: 10px; border-bottom: 1px solid #dddddd; }
    .container { max-width: 100%; }
    .dt-buttons .btn { background-color: #3498db; border-color: #3498db; color: #fff; font-weight: bold; padding: 10px 20px; transition: background-color 0.3s ease; border-radius: 5px; }
    .dt-buttons .btn:hover { background-color: #2980b9; border-color: #2980b9; cursor: pointer; }
</style>
<body>
    
    <div class="container">
        <div>
            <br>
            <h1 class="text-center mb-3">Mis Solicitudes de Cotización</h1><br>
            
            <table class="table table-bordered table-striped" id="tabla-cotizaciones">
                <thead>
                    <tr style="text-align:center;">
                        <th>Folio</th>
                        <th>Fecha de Solicitud</th>
                        <th>Fecha de Cotización</th>
                        <th>Empresa</th>
                        <th>Cliente</th>
                        <th>Etiquetado y Codificado</th>
                        <th>Cotizado Por</th>
                        <th>Estatus</th>
                        <th>Acción</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    // CAMBIO: Recorrer el nuevo array de cotizaciones
                    if (!empty($cotizaciones)) {
                        foreach ($cotizaciones as $cotizacion) {
                            echo "<tr>";
                            $folio = $cotizacion['folio'];
                            $folio_formateado = str_pad($folio, 9, "0", STR_PAD_LEFT);
                            echo "<td style='text-align:center;'><a href='COT_detalle_cotizacion.php?folio=" . urlencode($cotizacion['folio']) . "' '>".$folio_formateado."</a></td>";
                            echo "<td style='text-align:center;'>" . date('d-m-Y ', strtotime($cotizacion['fecha_solicitud'])) . "</td>";
                            $fecha_cotizacion = ($cotizacion['fecha_cotizacion'] == "0000-00-00 00:00:00" || $cotizacion['fecha_cotizacion'] == null) ? "N/A" : date('d-m-Y ', strtotime($cotizacion['fecha_solicitud']));
                            echo "<td style='text-align:center;'>" . $fecha_cotizacion . "</td>";
                            echo "<td style='text-align:center;'>" . htmlspecialchars($cotizacion['empresa']) . "</td>";
                            echo "<td style='text-align:center;'>" . htmlspecialchars($cotizacion['cliente']) . "</td>";
                            $rfid = htmlspecialchars($cotizacion['rfid']);
                            $SiNo = "";
                            $SiNo = ($rfid == "1") ? "Si" : "No";
                            echo "<td style='text-align:center;'>" . $SiNo . "</td>";
                            
                            $userCot = htmlspecialchars($cotizacion['user_cotizador']);
                            $cotizado = "";
                            $cotizado = ($userCot == "" || $userCot == null ) ? "N/A" : $userCot;
                            echo "<td style='text-align:center;'>" . $cotizado . "</td>";
                            
                            $estatus = htmlspecialchars($cotizacion['estatus']);
                            $folio_url = urlencode($cotizacion['folio']);
                            $color = 'gray';
                            $accion_principal_html = ''; // Variable para el botón de acción que cambia
                            
                            // Usamos switch para organizar la lógica por estatus
                            switch ($estatus) {
                                case 'Nuevo':
                                    $color = 'blue';
                                    // Si es Nuevo o Devuelto, la acción es Editar
                                    $accion_principal_html = "N/A";
                                    break;
                                case 'En Revision':
                                    $color = 'blue';
                                    // Si es Nuevo o Devuelto, la acción es Editar
                                    $accion_principal_html = "N/A";
                                    break;
                                case 'Devuelta':
                                    $color = 'orange';
                                    // Si es Nuevo o Devuelto, la acción es Editar
                                    $accion_principal_html = "<a href='COT_editar_cotizacion.php?folio={$folio_url}' class='btn btn-warning btn-sm'>Editar</a>";
                                    break;
                            
                                case 'Cotizado':
                                    $color = 'green';
                                    $accion_principal_html = "
                                        <a href='COT_generar_pedido.php?folio={$folio_url}' class='btn btn-success btn-sm'>Generar Pedido</a>
                                        <button type='button' class='btn btn-danger btn-sm ms-1' onclick='rechazarPorSolicitante(\"{$folio_url}\")'>Rechazar</button>
                                    ";
                                    break;
                            
                                case 'Rechazada':
                                    $color = 'red';
                                    // Si está Rechazado, no hay acción principal, solo se pueden ver los detalles
                                    $accion_principal_html = "N/A";
                                    break;
                                
                                case 'Pedido Generado':
                                    $color = 'green';
                                    // Sin acción principal si ya fue enviado
                                    $accion_principal_html = "N/A";
                                    break;
                            
                                default:
                                    // Para cualquier otro estatus no definido
                                    $color = 'gray';
                                    $accion_principal_html = "N/A";
                                    break;
                            }
                            
                            // Imprime la celda del estatus con su color
                            echo "<td style='text-align:center; color:{$color}; font-weight:bold;'>" . $estatus . "</td>";
                            
                            // Imprime la celda de acciones con los botones
                            echo "<td style='text-align:center;'>
                                    {$accion_principal_html}
                                  </td>";
                            
                            echo "</tr>";
                        }
                    }   
                    ?>
                </tbody>
            </table>
        </div>
    </div>
    
<script>
    function rechazarPorSolicitante(folio) {
        Swal.fire({
            title: 'Rechazar Cotización',
            input: 'textarea',
            inputLabel: 'Motivo del rechazo',
            inputPlaceholder: 'Escribe aquí por qué el cliente rechazó la cotización...',
            showCancelButton: true,
            confirmButtonText: 'Confirmar Rechazo',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#d9534f',
            inputValidator: (value) => {
                if (!value) {
                    return '¡Necesitas escribir un motivo!'
                }
            }
        }).then((result) => {
            if (result.isConfirmed) {
                // Preparamos los datos para el envío
                const formData = new FormData();
                formData.append('folio', folio);
                formData.append('motivo', result.value);

                // Mostramos la alerta de "Cargando"
                Swal.fire({
                    title: 'Procesando...',
                    allowOutsideClick: false,
                    didOpen: () => Swal.showLoading()
                });

                // Apuntamos al NUEVO script de backend
                fetch('COT_controller/rechazar_por_solicitante.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        Swal.fire({
                            icon: 'success',
                            title: '¡Éxito!',
                            text: 'La cotización ha sido rechazada.'
                        }).then(() => location.reload());
                    } else {
                        Swal.fire({ icon: 'error', title: 'Error', text: data.message });
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    Swal.fire({ icon: 'error', title: 'Error de Conexión', text: 'No se pudo conectar con el servidor.' });
                });
            }
        });
    }

    $(document).ready(function() {
        // CAMBIO: El ID de la tabla ahora es #tabla-cotizaciones
        $('#tabla-cotizaciones').DataTable({
            "language": {
                "url": "//cdn.datatables.net/plug-ins/1.10.16/i18n/Spanish.json",
                "emptyTable": "No hay cotizaciones disponibles"
            },
            "pageLength": 10,
            "lengthMenu": [10, 25, 50],
            "responsive": true,
            "order": [[0, "desc"]], // Ordenar por folio descendente
            dom: 'lBfrtip',
            buttons: [
                {
                    extend: 'excelHtml5',
                    text: 'Exportar a Excel',
                    className: 'btn btn-success btn-lg m-2'
                },
                {
                    extend: 'print',
                    text: 'Imprimir',
                    className: 'btn btn-info btn-lg m-2'
                }
            ]
        });
    });
</script>
    
</body>
</html>

<?php
include("src/templates/adminfooter.php");
?>