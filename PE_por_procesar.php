<?php

    session_start();

    require("config/db.php");
    include("src/templates/adminheader.php");
    
    //Obtener Todos los pedidos
    $pedidos = Pendientes($conn);
    
    function Pendientes($conn){
        $sql = "SELECT p.*, u.nombre AS uso_nombre, s.sucursal AS sucursal_nombre
                FROM pedidos_especiales p
                JOIN uso u ON p.uso = u.id
                JOIN sucursales s ON p.sucursal = s.id
                WHERE estatus = 'Nuevo' OR estatus = 'Por Revisar'
                ORDER BY p.id DESC"; // Ordenar por ID en orden descendente
        $result = $conn->query($sql);
        
        if ($result->num_rows > 0) {
            $pedidos = [];
            
            // Recorrer los resultados
            while ($row = $result->fetch_assoc()) {
                $pedidos[] = $row;
            }
            
            return $pedidos;
        } else {
            return []; // Devuelve un array vacío si no se encuentran registros
        }
    }
    
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
    <title>Pedidos Especiales</title>
</head>
<style>
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
    .container {
        max-width: 100%;
    }
</style>
<body>
    
    <div class="container">
        <div>
            <br>
            <h1 class="text-center mb-3">Pendiente por Procesar</h1><br>
            
            <table class="table table-bordered table-striped" id="pedidos">
                <thead>
                    <tr style="text-align:center;">
                        <th>Folio</th>
                        <th>Fecha</th>
                        <th>Solicitante</th>
                        <th>No. Cliente</th>
                        <th>Nombre del cliente</th>
                        <th>Uso</th>
                        <th>Sucursal</th>
                        <th>Observaciones</th>
                        <th>Estatus</th>
                        <th></th> 
                    </tr>
                </thead>
                <tbody>
                    <?php
                    // Recorrer el array de pedidos y crear las filas
                    if (!empty($pedidos)) {
                        foreach ($pedidos as $pedido) {
                            
                            echo "<tr>";
                            echo "<td style='text-align:center;'><a href='PE_detalles_pedido.php?doc=" . urlencode($pedido['id']) . "&A=true'>" . htmlspecialchars($pedido['folio']) . "</a></td>";
                            echo "<td style='text-align:center;'>" . date('d-m-Y', strtotime($pedido['fecha'])) . "</td>";
                            echo "<td style='text-align:center;'>" . htmlspecialchars($pedido['solicitante']) . "</td>";
                            echo "<td style='text-align:center;'>" . htmlspecialchars($pedido['numero_cliente']) . "</td>";
                            echo "<td style='text-align:center;'>" . htmlspecialchars($pedido['nombre_cliente']) . "</td>";
                            echo "<td style='text-align:center;'>" . htmlspecialchars($pedido['uso_nombre']) . "</td>";
                            echo "<td style='text-align:center;'>" . htmlspecialchars($pedido['sucursal_nombre']) . "</td>";
                            echo "<td style='text-align:center;'>" . htmlspecialchars($pedido['observaciones']) . "</td>";
                            echo "<td style='text-align:center;'>" . htmlspecialchars($pedido['estatus']) . "</td>";
                            echo "<td style='text-align:center;'>
                                <button type='button' class='btn btn-success' 
                                onclick='ModalAutorizar(\"" . htmlspecialchars($pedido['folio'] . ';' . $_SESSION['nombre']) . "\")'>Procesar</button>
                                <button type='button' class='btn btn-danger' 
                                onclick='ModalRechazar(\"" . htmlspecialchars($pedido['folio']) . "\")'>Rechazar</button>
                                <button type='button' class='btn btn-warning' 
                                onclick='ModalDevolver(\"" . htmlspecialchars($pedido['folio']) . "\")'>Devolver</button>
                              </td>";

                        }
                    }
                    ?>
                </tbody>
            </table>
        </div>
        
    </div>
    
    <!-- Modal Autorizar -->
    <div class="modal fade" id="modalAutorizar" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
      <div class="modal-dialog">
        <div class="modal-content">
          <!-- El contenido se cargará aquí desde modal_autorizar.php -->
        </div>
      </div>
    </div>
    
    <!-- Modal Rechazar -->
    <div class="modal fade" id="modalRechazar" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
      <div class="modal-dialog">
        <div class="modal-content">
          <!-- El contenido se cargará aquí desde modal_rechazar.php -->
        </div>
      </div>
    </div>
    
    <!-- Modal Devolver -->
    <div class="modal fade" id="modalDevolver" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
      <div class="modal-dialog">
        <div class="modal-content">
          <!-- El contenido se cargará aquí desde modal_devolver.php -->
        </div>
      </div>
    </div>

    <script>
    function ModalAutorizar(data) {
        // Separar el string en dos partes usando split
        let parts = data.split(';');
        let folio = parts[0];
        let autorizador = parts[1];
    
        // Cargar el contenido del modal
        $('#modalAutorizar .modal-content').load('PE_modal_autorizar.php?folio=' + encodeURIComponent(folio) + '&autorizador=' + encodeURIComponent(autorizador), function() {
            $('#modalAutorizar').modal('show');
        });
    }
    
    function ModalRechazar(folio) {
        $('#modalRechazar .modal-content').load('PE_modal_rechazar.php?folio=' + encodeURIComponent(folio), function() {
            $('#modalRechazar').modal('show');
        });
    }
    
    function ModalDevolver(folio) {
        $('#modalDevolver .modal-content').load('PE_modal_devolver.php?folio=' + encodeURIComponent(folio), function() {
            $('#modalDevolver').modal('show');
        });
    }


    </script>
    
    <script>
        $(document).ready(function() {
            $('#pedidos').DataTable({
                "language": {
                    "url": "//cdn.datatables.net/plug-ins/1.10.16/i18n/Spanish.json",
                    "emptyTable": "No hay pedidos disponibles"
                },
                "pageLength": 10, // Muestra 5 registros por página
                "lengthMenu": [10, 25, 50], // Opciones de número de registros por página
                "responsive": true,
                "order": [[0, "desc"]],
                "columnDefs": [
                    { "orderable": false, "targets": [2,3,4,5,6,7] }
                ]

            });
        });
    </script>
    
    
</body>

</html>


<?php
include("src/templates/adminfooter.php");
?>

