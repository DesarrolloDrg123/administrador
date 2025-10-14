<?php

    session_start();
    require("config/db.php");
    include("src/templates/adminheader.php");
    
    //Obtener Todos los pedidos
    $pedidos = PedidosEspe($conn);
    
    function PedidosEspe($conn){
        $sql = "SELECT p.*, u.nombre AS uso_nombre, s.sucursal AS sucursal_nombre
                FROM pedidos_especiales p
                JOIN uso u ON p.uso = u.id
                JOIN sucursales s ON p.sucursal = s.id
                ORDER BY p.id DESC";
        $result = $conn->query($sql);
        
        if ($result->num_rows > 0) {
            $pedidos = [];
            
            // Recorrer los resultados
            while ($row = $result->fetch_assoc()) {
                $pedidos[] = $row;
            }
            
            return $pedidos;
        } else {
            //return ['success' => false, 'message' => 'No se encontraron registros.'];
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
<body>
    
    <div class="container">
        <div>
            <br>
            <h1 class="text-center mb-3">Todos los Pedidos Especiales</h1><br>
            
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
                        <th>Observaciones</th>
                        <th>Estatus</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    // Recorrer el array de pedidos y crear las filas
                    if (!empty($pedidos)) {
                        foreach ($pedidos as $pedido) {
                            echo "<tr>";
                            echo "<td style='text-align:center;'><a href='PE_detalles_pedido.php?doc=" . urlencode($pedido['id']) . "&T=true'>" . htmlspecialchars($pedido['folio']) . "</a></td>";
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
                            echo "<td style='text-align:center;'>" . htmlspecialchars($pedido['observaciones']) . "</td>";
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
                            // Celda de Acciones
                            echo "<td style='text-align:center;'>";
                            
                            // Si el estatus es 'Procesado' o similar, muestra el botón para EDITAR la OC.
                            if ($pedido['estatus'] == 'Procesado' || $pedido['estatus'] == 'Recibido-Parcial' || $pedido['estatus'] == 'Recibido-Total') {
                                
                                echo "<button class='btn btn-warning btn-sm btn-cambiar-oc' data-folio='" . htmlspecialchars($pedido['folio']) . "'>Editar OC</button>";
                            
                            } else {
                                echo "N/A"; // No hay acciones para pedidos rechazados
                            }
                            
                            echo "</td>";
                        }
                    } else {
                        // Si no hay pedidos, mostrar un mensaje
                        echo "<tr><td colspan='12'>No hay pedidos disponibles.</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
        
    </div>
    
<script>
        $(document).ready(function() {
            // Inicialización de DataTable (tu código existente)
            $('#pedidos').DataTable({
                "language": {
                    "url": "//cdn.datatables.net/plug-ins/1.10.16/i18n/Spanish.json"
                },
                "pageLength": 10,
                "lengthMenu": [10, 25, 50],
                "responsive": false,
                "order": [[0, "desc"]],
                "processing": true,
                "columnDefs": [
                    { "orderable": false, "targets": [2,3,4,5,6,7,12] } // Agregué la columna 12 (acciones) para que no se ordene
                ],
                dom: 'lBfrtip',
                buttons: [
                    { extend: 'excelHtml5', text: 'Exportar a Excel', className: 'btn btn-success btn-lg m-2' },
                    { extend: 'print', text: 'Imprimir', className: 'btn btn-info btn-lg m-2' }
                ]
            });

            // NUEVO CÓDIGO PARA MANEJAR EL CLIC EN EL BOTÓN 'PROCESAR'
            $('#pedidos tbody').on('click', '.btn-cambiar-oc', function() {
                const folio = $(this).data('folio');
                const autorizador = "<?php echo isset($_SESSION['nombre_usuario']) ? $_SESSION['nombre_usuario'] : 'Admin'; ?>"; // Obtiene el usuario de la sesión

                Swal.fire({
                    title: 'Editar Orden de Compra',
                    html: `Folio: <strong>${folio}</strong>`,
                    input: 'text',
                    inputLabel: 'Orden de Compra',
                    inputPlaceholder: 'Introduce la orden de compra...',
                    showCancelButton: true,
                    confirmButtonText: 'Actualizar',
                    cancelButtonText: 'Cancelar',
                    inputValidator: (value) => {
                        if (!value) {
                            return '¡Necesitas escribir una orden de compra!'
                        }
                    }
                }).then((result) => {
                    if (result.isConfirmed) {
                        const ordenCompra = result.value;

                        // Petición AJAX para enviar los datos al servidor
                        $.ajax({
                            url: 'PE_controller/auto_dev_rec.php', // El archivo PHP que procesa los datos
                            type: 'POST',
                            data: {
                                folio: folio,
                                orden_compra: ordenCompra,
                                autorizador: autorizador,
                                operacion: 'Actualizar' // Coincide con tu PHP de la pregunta anterior
                            },
                            success: function(response) {
                                Swal.fire({
                                    title: '¡Éxito!',
                                    text: 'El pedido ha sido actualizado correctamente.',
                                    icon: 'success'
                                }).then(() => {
                                    location.reload(); // Recarga la página para ver los cambios
                                });
                            },
                            error: function() {
                                Swal.fire({
                                    title: 'Error',
                                    text: 'No se pudo procesar el pedido.',
                                    icon: 'error'
                                });
                            }
                        });
                    }
                });
            });
        });
    </script>
</body>

</html>
<?php
include("src/templates/adminfooter.php");
?>

