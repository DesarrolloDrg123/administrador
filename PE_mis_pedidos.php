<?php
    session_start();
    
    require("config/db.php");
    include("src/templates/adminheader.php");
    
    $nombre = $_SESSION['nombre'];
    
    function MisPedidosEspe($conn, $nombre) {
        // Escapar el nombre para evitar inyecciones SQL
        $nombre = $conn->real_escape_string($nombre);
    
        // Consulta SQL con cl¨¢usula WHERE para filtrar por el nombre del solicitante
        $sql = "SELECT p.*, u.nombre AS uso_nombre, s.sucursal AS sucursal_nombre
                FROM pedidos_especiales p
                JOIN uso u ON p.uso = u.id
                JOIN sucursales s ON p.sucursal = s.id
                WHERE p.solicitante = '$nombre'
                ORDER BY p.id DESC";
    
        $result = $conn->query($sql);
        
        if ($result->num_rows > 0) {
            $pedidos = [];
            
            while ($row = $result->fetch_assoc()) {
                $pedidos[] = $row;
            }
            
            return $pedidos;
        } else {
            return []; // Retorna un array vac¨ªo si no se encontraron registros
        }
    }
    
    //Obtener mi pedido Especial
    $pedidos = MisPedidosEspe($conn, $nombre);//filtrar por usuario de sesion
    
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
    /* Si quieres aplicar estos estilos espec¨ªficamente para los botones de exportaci¨®n de DataTables, puedes usar este selector: */
    .dt-buttons .btn {
        background-color: #3498db;  /* Color de fondo */
        border-color: #3498db;      /* Color del borde */
        color: #fff;                /* Color del texto */
        font-weight: bold;          /* Negrita */
        padding: 10px 20px;         /* Relleno de los botones */
        transition: background-color 0.3s ease; /* Transici¨®n suave al cambiar de color */
        border-radius: 5px;         /* Bordes redondeados */
    }
    
    .dt-buttons .btn:hover {
        background-color: #2980b9;  /* Color de fondo cuando el bot¨®n est¨¢ en hover */
        border-color: #2980b9;      /* Color del borde cuando el bot¨®n est¨¢ en hover */
        cursor: pointer;           /* Cambio de cursor al pasar el mouse */
    }
</style>
<body>
    
    <div class="container">
        <div>
            <br>
            <h1 class="text-center mb-3">Mis Pedidos Especiales</h1><br>
            
            <table class="table table-bordered table-striped" id="pedidos">
                <thead>
                    <tr style="text-align:center;">
                        <th>Folio</th>
                        <th>Fecha de Registro</th>
                        <th>Orden de Compra</th>
                        <th>Fecha de Proceso</th>
                        <th>Procesado por</th>
                        <th>Solicitante</th>
                        <th>No. Cliente</th>
                        <th>Nombre del cliente</th>
                        <th>Uso</th>
                        <th>Sucursal</th>
                        <th>Estatus</th>
                        <th>AcciÃ³n</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    // Recorrer el array de pedidos y crear las filas
                    if (!empty($pedidos)) {
                        foreach ($pedidos as $pedido) {
                            echo "<tr>";
                            echo "<td style='text-align:center;'><a href='PE_detalles_pedido.php?doc=" . urlencode($pedido['id']) . "&M=true'>" . htmlspecialchars($pedido['folio']) . "</a></td>";
                            echo "<td style='text-align:center;'>" . date('d-m-Y', strtotime($pedido['fecha'])) . "</td>";
                            echo "<td style='text-align:center;'>" . htmlspecialchars($pedido['oc']) . "</td>";
                            if($pedido['fecha_autorizacion'] != "0000-00-00"){
                                echo "<td style='text-align:center;'>" . date('d-m-Y', strtotime($pedido['fecha_autorizacion'])) . "</td>";
                            } else {
                                echo "<td></td>";
                            }
                            echo "<td style='text-align:center;'>" . htmlspecialchars($pedido['autorizado_por']) . "</td>";
                            echo "<td style='text-align:center;'>" . htmlspecialchars($pedido['solicitante']) . "</td>";
                            echo "<td style='text-align:center;'>" . htmlspecialchars($pedido['numero_cliente']) . "</td>";
                            echo "<td style='text-align:center;'>" . htmlspecialchars($pedido['nombre_cliente']) . "</td>";
                            echo "<td style='text-align:center;'>" . htmlspecialchars($pedido['uso_nombre']) . "</td>";
                            echo "<td style='text-align:center;'>" . htmlspecialchars($pedido['sucursal_nombre']) . "</td>";
                            //Estatus
                            if ($pedido['estatus'] == "Recibido-Total") {
                                echo "<td style='text-align:center; color:green;'>" . htmlspecialchars($pedido['estatus']) . "</td>";
                            }else if($pedido['estatus'] == "Procesado" || $pedido['estatus'] == "Recibido-Parcial") {
                                echo "<td style='text-align:center; color:blue;'>" . htmlspecialchars($pedido['estatus']) . "</td>";
                            }else if($pedido['estatus'] == "Devuelto") {
                                echo "<td style='text-align:center; color:#c1a600;'>" . htmlspecialchars($pedido['estatus']) . "</td>";
                            }else if($pedido['estatus'] == "Rechazado") {
                                echo "<td style='text-align:center; color:red;' >" . htmlspecialchars($pedido['estatus']) . "</td>";
                            } else {
                                echo "<td style='text-align:center;' >" . htmlspecialchars($pedido['estatus']) . "</td>";
                            }
                            //Accion
                            if($pedido['estatus'] == "Devuelto" || $pedido['estatus'] == "Nuevo"){
                                echo "<td style='text-align:center;'><a href='PE_editar_pedido.php?doc=" . urlencode($pedido['id']) . "&M=true' class='btn btn-warning'>Editar</a></td>";
                            } else if($pedido['estatus'] == "Procesado" ){
                                echo "<td style='text-align:center;'>
                                    <a href='PE_controller/procesar.php?doc=" . urlencode($pedido['id']) . "&R=true' class='btn btn-success'>Recibir-Total</a>
                                    <a href='PE_controller/procesar.php?doc=" . urlencode($pedido['id']) . "&RP=true' class='btn btn-primary'>Recibir-Parcial</a>
                                </td>";
                            } else if ($pedido['estatus']== "Recibido-Parcial") {
                                echo "<td style='text-align:center;'>
                                    <a href='PE_controller/procesar.php?doc=" . urlencode($pedido['id']) . "&R=true' class='btn btn-success'>Recibir-Total</a>
                                    <a href='PE_controller/procesar.php?doc=" . urlencode($pedido['id']) . "&RP=true' class='btn btn-primary'>Recibir-Parcial</a>
                                  </td>";
                            } else {
                                echo "<td></td>";
                            }
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
            $('#pedidos').DataTable({
                "language": {
                    "url": "//cdn.datatables.net/plug-ins/1.10.16/i18n/Spanish.json",
                    "emptyTable": "No hay pedidos disponibles"
                },
                "pageLength": 10, // Muestra 5 registros por p¨¢gina
                "lengthMenu": [10, 25, 50], // Opciones de n¨²mero de registros por p¨¢gina
                "responsive": true,
                "order": [[0, "desc"]],
                "processing": true,
                "columnDefs": [
                    { "orderable": false, "targets": [2,3,4,5,6,7] }
                ],
                dom: 'lBfrtip', // Cambi¨¦ la posici¨®n de 'l' para que est¨¦ antes de los botones
                buttons: [
                    {
                        extend: 'excelHtml5', // Exportar a Excel
                        text: 'Exportar a Excel',
                        className: 'btn btn-success btn-lg m-2'
                    },
                    {
                        extend: 'print', // Imprimir la tabla
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