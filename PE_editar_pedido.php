<?php
    session_start();
    
    require("config/db.php");
    include("src/templates/adminheader.php");


    if (!isset($_SESSION['loggedin']) || !$_SESSION['loggedin']) {
        header("Location: index.php");
        exit();
        }

    if (!isset($_GET['doc'])) {
        echo "Folio no proporcionado.";
        exit();
    }
    
    $mParam = isset($_GET['M']) ? $_GET['M'] : null;
    $tParam = isset($_GET['T']) ? $_GET['T'] : null;
    $aParam = isset($_GET['A']) ? $_GET['A'] : null;
    
    $pedido = PedidoEspecial($conn, $_GET['doc']);
    
    function PedidoEspecial($conn, $id) {
        $id = $conn->real_escape_string($id);
        $sql = "SELECT p.*, u.nombre AS uso_nombre, s.sucursal AS sucursal_nombre
                FROM pedidos_especiales p
                JOIN uso u ON p.uso = u.id
                JOIN sucursales s ON p.sucursal = s.id
                WHERE p.id = '$id'";
        
        $result = $conn->query($sql);
        
        if ($result->num_rows > 0) {
            return $result->fetch_assoc(); // Retorna el primer registro encontrado
        } else {
            return null; // Retorna null si no se encontró ningún registro
        }
    }
    
    $fmt = new IntlDateFormatter('es_ES', IntlDateFormatter::LONG, IntlDateFormatter::NONE);
    $fecha = new DateTime($pedido['fecha']);
    $fecha_formateada = $fmt->format($fecha);

?>

<div class="container mt-5">
    <form id="editar_pedido" method="post">


    <table class="table table-striped" id="detalles">
        <h2>Editar Pedido Especial</h2>
        <thead>
            <tr>
                <th>Informacion</th>
                <th> </th>
            </tr>
        </thead>
        <tbody>

            <tr>
                <td>Folio</td>
                <td style="color:red; font-weight:bold;"><?php echo htmlspecialchars($pedido['folio']); ?></td>
                <input type="hidden" name="folio" value="<?php echo htmlspecialchars($pedido['folio']); ?>">
            </tr>
            
            <?php 
            if(!empty($pedido['oc'])){?>
                <tr>
                    <td>Orden de Compra</td>
                    <td><?php echo htmlspecialchars($pedido['oc']); ?></td>
                </tr>
            <?php    
            }
            ?>
            
            <tr>
                <td>Sucursal</td>
                <td><?php echo htmlspecialchars($pedido['sucursal_nombre']); ?></td>
            </tr>
            <tr>
                <td>Solicitante</td>
                <td><?php echo htmlspecialchars($pedido['solicitante']); ?></td>
            </tr>
            <tr>
                <td>No. de Cuenta</td>
                <td><?php echo htmlspecialchars($pedido['numero_cliente']); ?></td>
            </tr>
            
            <tr>
                <td>Nombre del Cliente</td>
                <td><?php echo htmlspecialchars($pedido['nombre_cliente']); ?></td>
            </tr>
        
            </tr>
            <tr>
                <td>Categoria</td>
                <td><?php echo htmlspecialchars($pedido['uso_nombre']); ?></td>
            </tr>
            
            <tr>
                <td>Fecha de Solicitud</td>
                <td><?php echo htmlspecialchars($fecha_formateada); ?></td>
            </tr>
            
            <?php 
            if(!empty($pedido['observaciones'])){?>
                <tr>
                    <td>Observaciones</td>
                    <td><?php echo htmlspecialchars($pedido['observaciones']); ?></td>
                </tr>
            <?php    
            } else { ?>
               <tr>
                    <td>Observaciones</td>
                    <td>N/A</td>
                </tr>
            <?php      
            }
            ?>
            
            <tr>
                <td>Estado</td>
                <td><?php echo htmlspecialchars($pedido['estatus']); ?></td>
            </tr>
            
            <?php 
            if(!empty($pedido['motivo_devolucion'])){?>
                <tr>
                    <td style="color:red; font-weight:bold;">Motivo de Devoluci&oacute;n</td>
                    <td><?php echo htmlspecialchars($pedido['motivo_devolucion']); ?></td>
                </tr>
                <input type="hidden" name="devolucion" value="<?php echo htmlspecialchars($pedido['motivo_devolucion']); ?>">
            <?php    
            }
            ?>
            
            <?php 
            if(!empty($pedido['motivo_rechazo'])){?>
                <tr>
                    <td style="color:red; font-weight:bold;">Motivo de Rechazo</td>
                    <td><?php echo htmlspecialchars($pedido['motivo_rechazo']); ?></td>
                </tr>
            <?php    
            }
            ?>

        </tbody>
    </table>
    
    <div class="alert alert-success alert-dismissible fade show" role="alert" id="mensajeAlertaE" style="display:none;">
        <strong>¡Exito!</strong> Pedido guardado exitosamente.
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        <br>
    </div>
    
    <table class="table table-striped" id="tabla">
        <thead>
            <tr>
                <th>Sku*</th>
                <th>Descripci&oacute;n*</th>
                <th>Cantidad*</th>
                <th>Notas</th>
                <th>
                    <button class="btn btn-primary" type="button" onclick="agregarFila()" title="Agregar fila">
                        <i class="bi bi-plus-square"></i> Agregar Fila
                    </button>
                </th>
            </tr>
        </thead>
        <tbody>
            <?php
            // Divide los valores concatenados en arrays
            $skus = explode(';', $pedido['sku']);
            $descripciones = explode(';', $pedido['descripcion']);
            $cantidades = explode(';', $pedido['cantidad']);
            $notas = explode(';', $pedido['nota']);
    
            // Asegura que todos los arrays tengan el mismo número de elementos
            $numItems = count($skus);
            for ($i = 0; $i < $numItems; $i++):
            ?>
                <tr>
                    <?php
                    /* 
                    <td><?php echo htmlspecialchars($skus[$i]); ?></td>
                    <td><?php echo htmlspecialchars($descripciones[$i]); ?></td>
                    <td><?php echo htmlspecialchars($cantidades[$i]); ?></td>
                    <td><?php echo htmlspecialchars($notas[$i]); ?></td>
                    */
                    ?>
                    
                    <td><input class="form-control" id="sku" type="text" name="sku[]" required oninput="verificarCampos(this)" value="<?php echo htmlspecialchars($skus[$i]); ?>"></td>
                    <td><input class="form-control" id="descripcion" type="text" name="descripcion[]" required oninput="verificarCampos(this)" value="<?php echo htmlspecialchars($descripciones[$i]); ?>"></td>
                    <td><input class="form-control" id="cantidad" type="number" name="cantidad[]" required oninput="verificarCampos(this)" inputmode="numeric" value="<?php echo htmlspecialchars($cantidades[$i]); ?>"></td>
                    <td><input class="form-control" id="nota" type="text" name="nota[]" value="<?php echo htmlspecialchars($notas[$i]); ?>"></td>
                    <td><button class="btn btn-danger" type="button" onclick="eliminarFila(this)">Eliminar</button></td>
                </tr>
            <?php endfor; ?>
        </tbody>
    </table>
    
    <div class="row mb-1">
                <div class="col text-center" >
                    <input type="submit" class="btn btn-success" value="Enviar" style="width: 150px"><br><br><br>
                </div>
            </div>
    </form>
    
    <?php 
        if ($mParam === 'true') : ?>
            <a class="btn btn-primary" href="PE_mis_pedidos.php" role="button">Volver</a>
    <?php endif; ?>
    
    <?php if ($tParam === 'true') : ?>
            <a class="btn btn-primary" href="PE_todos_pedidos.php" role="button">Volver</a>
    <?php endif; ?>
    
    <?php if ($aParam === 'true') : ?>
            <a class="btn btn-primary" href="PE_por_procesar.php" role="button">Volver</a>
    <?php endif; ?>
    
</div>

<div>
    <script>
        function agregarFila() {
        var tabla = document.getElementById("tabla").getElementsByTagName('tbody')[0];
        var nuevaFila = tabla.insertRow();
        
        var celdaSKU = nuevaFila.insertCell(0);
        var celdaDescripcion = nuevaFila.insertCell(1);
        var celdaCantidad = nuevaFila.insertCell(2);
        var celdaNota = nuevaFila.insertCell(3);
        var celdaAcciones = nuevaFila.insertCell(4);
    
        // Crear los inputs para la nueva fila
        celdaSKU.innerHTML = '<input class="form-control" type="text" name="sku[]" required oninput="verificarCampos(this)">';
        celdaDescripcion.innerHTML = '<input class="form-control" type="text" name="descripcion[]" required oninput="verificarCampos(this)">';
        celdaCantidad.innerHTML = '<input class="form-control" type="number" name="cantidad[]" required oninput="verificarCampos(this)" inputmode="numeric">';
        celdaNota.innerHTML = '<input class="form-control" type="text" name="nota[]">';
        celdaAcciones.innerHTML = '<button class="btn btn-danger" type="button" onclick="eliminarFila(this)">Eliminar</button>';
    
        // Aplicar la validación de caracteres a los nuevos campos
        limitarCaracteres();
        // Recalcular si se debe agregar una nueva fila
        verificarCampos();
    }
    
    function limitarCaracteres() {
        document.querySelectorAll('input[name="sku[]"]').forEach(function (input) {
            input.addEventListener('input', function () {
                if (this.value.length > 15) {
                    this.value = this.value.slice(0, 15); // Limitar a 15 caracteres
                }
            });
        });
        document.querySelectorAll('input[name="descripcion[]"]').forEach(function (input) {
            input.addEventListener('input', function () {
                if (this.value.length > 40) {
                    this.value = this.value.slice(0, 40); // Limitar a 40 caracteres
                }
            });
        });
        document.querySelectorAll('input[name="cantidad[]"]').forEach(function (input) {
            input.addEventListener('input', function () {
                if (this.value.length > 6) {
                    this.value = this.value.slice(0, 6); // Limitar a 6 caracteres
                }
            });
        });
        document.querySelectorAll('input[name="nota[]"]').forEach(function (input) {
            input.addEventListener('input', function () {
                if (this.value.length > 40) {
                    this.value = this.value.slice(0, 40); // Limitar a 40 caracteres
                }
            });
        });
    }
    
    // Función para eliminar una fila
    function eliminarFila(boton) {
        var fila = boton.parentNode.parentNode;
        fila.parentNode.removeChild(fila);
    }
    
    function verificarCampos(input) {
        // Obtener la fila en la que está el input
        var fila = input.parentNode.parentNode;
        
        // Obtener los campos SKU, Descripción y Cantidad de la misma fila
        var sku = fila.querySelector('input[name="sku[]"]').value;
        var descripcion = fila.querySelector('input[name="descripcion[]"]').value;
        var cantidad = fila.querySelector('input[name="cantidad[]"]').value;
    
        // Verificar si los tres campos tienen datos
        if (sku && descripcion && cantidad) {
            // Verificar si ya existe una fila vacía
            if (!existeFilaVacia()) {
                // Si no existe una fila vacía, agregar una nueva fila
                agregarFila();
            }
        }
    }
    
    // Función para verificar si ya existe una fila vacía
    function existeFilaVacia() {
        var filas = document.querySelectorAll("#tabla tbody tr");
        for (var i = 0; i < filas.length; i++) {
            var sku = filas[i].querySelector('input[name="sku[]"]').value;
            var descripcion = filas[i].querySelector('input[name="descripcion[]"]').value;
            var cantidad = filas[i].querySelector('input[name="cantidad[]"]').value;
    
            // Si hay una fila con SKU, Descripción o Cantidad vacíos, retornar true
            if (!sku || !descripcion || !cantidad) {
                return true;
            }
        }
        return false;
    }
    
    document.addEventListener('DOMContentLoaded', function() {
        limitarCaracteres(); // Aplicar restricciones a los campos iniciales
    });
    </script>
    <script>
        // Manejador del envío del formulario
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById("editar_pedido").addEventListener("submit", function(event) {
                event.preventDefault(); // Prevenir recargar la página
        
                var formData = new FormData(this); // Crear el objeto FormData con los datos del formulario
        
                fetch('PE_controller/editar_pedido.php', { 
                    method: 'POST',
                    body: formData
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.json(); // Convierte la respuesta del servidor a JSON
                })
                .then(data => {
                    // Mostrar alerta Bootstrap
                    var mensajeAlerta = document.getElementById('mensajeAlertaE');
                    if (data.success) {
                        mensajeAlerta.classList.remove('alert-danger');
                        mensajeAlerta.classList.add('alert-success');
                        mensajeAlerta.innerHTML = '&iexcl;&Eacute;xito! ' + data.message;
                    } else {
                        mensajeAlerta.classList.remove('alert-success');
                        mensajeAlerta.classList.add('alert-danger');
                        mensajeAlerta.innerHTML = '<strong>Error:</strong> ' + data.message;
                    }
                    mensajeAlerta.style.display = 'block'; // Mostrar la alerta
        
                    setTimeout(function() {
                        window.location = 'PE_mis_pedidos.php'; // Cambia la URL a la que desees redirigir
                    }, 3000); // Redirigir después de 5 segundos
                })
                .catch(error => {
                    console.error('Error al enviar el pedido:', error);
                });
            });
        });
    </script>
    
    
    
    
</div>

<?php
include("../src/templates/adminfooter.php");
?>