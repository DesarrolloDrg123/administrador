<?php
    session_start();
    
    require("config/db.php");
    include("src/templates/adminheader.php");
    
    // Verifica si el usuario está logueado, si no, lo redirige
    if (!isset($_SESSION['loggedin']) || !$_SESSION['loggedin']) {
        header("Location: index.php");
        exit();
    }

    function ObtenerCotizaciones($conn) {
        // CAMBIO: La consulta ahora apunta a `datos_generales_co` y usa consultas preparadas
        $sql = "SELECT * FROM datos_generales_co 
                ORDER BY folio DESC";
        
        $stmt = $conn->prepare($sql);
        if ($stmt === false) {
            // Manejar error de preparación
            return [];
        }
        
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
            <h1 class="text-center mb-3">Todas las Solicitudes de Cotización</h1><br>
            
            <table class="table table-bordered table-striped" id="tabla-cotizaciones">
                <thead>
                    <tr style="text-align:center;">
                        <th>Folio</th>
                        <th>Solicitado por</th>
                        <th>Fecha de Solicitud</th>
                        <th>Fecha de Cotización</th>
                        <th>Empresa</th>
                        <th>Observaciones</th>
                        <th>Cliente</th>
                        <th>Etiquetado y Codificado</th>
                        <th>Cotizado Por</th>
                        <th>Estatus</th>
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
                            echo "<td style='text-align:center;'>" . htmlspecialchars($cotizacion['user_solicitante']) . "</td>";
                            echo "<td style='text-align:center;'>" . date('d-m-Y ', strtotime($cotizacion['fecha_solicitud'])) . "</td>";
                            $fecha_cotizacion = ($cotizacion['fecha_cotizacion'] == "0000-00-00 00:00:00" || $cotizacion['fecha_cotizacion'] == null) ? "N/A" : date('d-m-Y ', strtotime($cotizacion['fecha_solicitud']));
                            echo "<td style='text-align:center;'>" . $fecha_cotizacion . "</td>";
                            echo "<td style='text-align:center;'>" . htmlspecialchars($cotizacion['empresa']) . "</td>";
                            echo "<td style='text-align:center;'>" . htmlspecialchars($cotizacion['observaciones']) . "</td>";
                            echo "<td style='text-align:center;'>" . htmlspecialchars($cotizacion['cliente']) . "</td>";
                            $rfid = htmlspecialchars($cotizacion['rfid']);
                            $SiNo = "";
                            $SiNo = ($rfid == "1") ? "Si" : "No";
                            echo "<td style='text-align:center;'>" . $SiNo . "</td>";
                            
                            $userCot = htmlspecialchars($cotizacion['user_cotizador']);
                            $cotizado = "";
                            $cotizado = ($userCot == "" || $userCot == null ) ? "N/A" : $userCot;
                            echo "<td style='text-align:center;'>" . $cotizado . "</td>";
                            
                            // Lógica de colores para el estatus
                            $estatus = htmlspecialchars($cotizacion['estatus']);
                            $accion = "";
                            $color = 'gray';
                            if ($estatus == 'Cotizado') {
                                $accion = "";
                                $color = 'green';
                            } elseif ($estatus == 'Rechazada') {
                                $color = 'red';
                                $accion = "";
                            } elseif ($estatus == 'Devuelta') {
                                $color = 'orange';
                                $accion = "";
                            } elseif ($estatus == 'Nuevo') {
                                $color = 'blue';
                                $accion = "";
                            } elseif ($estatus == 'Pedido Generado') {
                                $color = 'green';
                                $accion = "";
                            }
                            echo "<td style='text-align:center; color:{$color}; font-weight:bold;'>" . $estatus . "</td>";
                        }
                    }   
                    ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <script>
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