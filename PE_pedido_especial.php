<?php

    session_start();
    
    session_regenerate_id(true);
    require("config/db.php");
    include("src/templates/adminheader.php");
    
    if (!isset($_SESSION['loggedin']) || !$_SESSION['loggedin']) {
    header("Location: index.php");
    exit();
    }
    
    // Consulta para obtener las opciones de "Uso"
    $sqlUso = "SELECT * FROM uso";
    $resultUso = $conn->query($sqlUso);
    
    // Consulta para obtener las opciones de "Sucursal"
    $sqlSucursal = "SELECT * FROM sucursales";
    $resultSucursal = $conn->query($sqlSucursal);
    
    // Consulta SQL para obtener el folio basado en el ID del pedido
    $sql = "SELECT folio FROM control_folios_pe WHERE id = 1 FOR UPDATE";
    $resultado = $conn->query($sql);

    // Verificar si hay resultados
    if ($resultado->num_rows > 0) {
        $fila = $resultado->fetch_assoc();
        $ultimo_folio = $fila['folio'];

        // Incrementar el folio
        if ($ultimo_folio !== null) {
            $ultimo_folio++;
        } else {
            $ultimo_folio = 1; // Si no hay registros, el folio comienza en 1
        }

        // Formatear el número con ceros a la izquierda
        $folio_formateado = sprintf('%09d', $ultimo_folio);
    } else {
        return sprintf('%09d', 1); // Si no se encontró ningún registro, comenzamos en 1 y lo formateamos
    }
    
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pedidos Especiales</title>
    
    <!-- Agrega este script para cargar SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    
</head>
<body>
    
    <div class="container mt-4">
    <div class="card shadow-lg border-0">
        <div class="card-body p-4">
            <form id="pedido_especial" method="post">
                <h2 class="text-center mb-4"><i class="bi bi-box-seam"></i> Nuevo Pedido Especial</h2>

                <!-- Folio y Fecha -->
                <div class="row mb-4 align-items-center">
                    <div class="col-md-6">
                        <h5>Folio: <span class="text-danger fw-bold"><?php echo $folio_formateado; ?></span></h5>
                        <input type="hidden" id="folio" name="folio" value="<?php echo $folio_formateado; ?>">
                    </div>
                    <div class="col-md-6 text-end">
                        <h5 class="fw-bold"><?php echo date('d-m-Y'); ?></h5>
                    </div>
                </div>

                <!-- Solicitante -->
                <div class="mb-3">
                    <label class="form-label">Solicitante*</label><br>
                    <span class="fw-bold"><?php echo htmlspecialchars($_SESSION['nombre']) ?></span>
                </div>

                <!-- No Cliente / Nombre Cliente -->
                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label for="no_cliente" class="form-label">No. de Cliente*</label>
                        <input class="form-control" type="number" id="no_cliente" name="no_cliente" min="000001" max="999999" required>
                    </div>
                    <div class="col-md-6">
                        <label for="nombre_cliente" class="form-label">Nombre del Cliente*</label>
                        <input class="form-control" type="text" id="nombre_cliente" name="nombre_cliente" required>
                    </div>
                </div>

                <!-- Uso / Sucursal -->
                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label for="uso" class="form-label">Uso*</label>
                        <select class="form-select" id="uso" name="uso" required>
                            <?php
                            if ($resultUso->num_rows > 0) {
                                while ($row = $resultUso->fetch_assoc()) {
                                    echo "<option value='" . $row['id'] . "'>" . $row['nombre'] . "</option>";
                                }
                            } else {
                                echo "<option value=''>No hay opciones</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label for="sucursal" class="form-label">Sucursal*</label>
                        <select class="form-select" id="sucursal" name="sucursal" required>
                            <?php
                            if ($resultSucursal->num_rows > 0) {
                                while ($row = $resultSucursal->fetch_assoc()) {
                                    echo "<option value='" . $row['id'] . "'>" . $row['sucursal'] . "</option>";
                                }
                            } else {
                                echo "<option value=''>No hay opciones</option>";
                            }
                            ?>
                        </select>
                    </div>
                </div>

                <!-- Observaciones -->
                <div class="mb-3">
                    <label for="observaciones" class="form-label">Observaciones</label>
                    <textarea class="form-control" id="observaciones" name="observaciones" rows="3"></textarea>
                </div>

                <!-- Tabla de Detalles -->
                <h4 class="text-center mb-3 mt-4"><i class="bi bi-card-list"></i> Detalles del Pedido</h4>

                <div class="table-responsive">
                    <table id="tabla" class="table table-striped align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>N° de Parte*</th>
                                <th>Descripción*</th>
                                <th>Cantidad*</th>
                                <th>Nota</th>
                                <th>
                                    <button class="btn btn-primary" type="button" onclick="agregarFila()" title="Agregar fila">
                                        <i class="bi bi-plus-square"></i>Agregar Fila
                                    </button>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><input class="form-control" type="text" name="sku[]" required oninput="verificarCampos(this)"></td>
                                <td><input class="form-control" type="text" name="descripcion[]" required oninput="verificarCampos(this)"></td>
                                <td><input class="form-control" type="number" name="cantidad[]" required oninput="verificarCampos(this)" inputmode="numeric"></td>
                                <td><input class="form-control" type="text" name="nota[]"></td>
                                <td><button class="btn btn-danger" type="button" onclick="eliminarFila(this)">Eliminar</button></td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Botón de Enviar -->
                <div class="text-center mt-4">
                    <input type="submit" class="btn btn-success px-4" value="Enviar">
                </div>
            </form>
        </div>
    </div>
</div>


<script>
    // Función para eliminar una fila
    function eliminarFila(boton) {
        var fila = boton.parentNode.parentNode;
        fila.parentNode.removeChild(fila);
    }
</script>
<script>
    // Función para verificar si los primeros tres campos están llenos
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
    
    // Función para agregar una nueva fila automáticamente
    function agregarFila() {
        var tabla = document.getElementById("tabla").getElementsByTagName('tbody')[0];
        var nuevaFila = tabla.insertRow();
        
        var celdaSKU = nuevaFila.insertCell(0);
        var celdaDescripcion = nuevaFila.insertCell(1);
        var celdaCantidad = nuevaFila.insertCell(2);
        var celdaNota = nuevaFila.insertCell(3);
        var celdaAcciones = nuevaFila.insertCell(4);
    
        // Crear los inputs para la nueva fila
        celdaSKU.innerHTML = '<input class="form-control" id="sku" type="text" name="sku[]" required oninput="verificarCampos(this)">';
        celdaDescripcion.innerHTML = '<input class="form-control" id="descripcion" type="text" name="descripcion[]" required oninput="verificarCampos(this)">';
        celdaCantidad.innerHTML = '<input class="form-control" id="cantidad" type="number" name="cantidad[]" required oninput="verificarCampos(this)" inputmode="numeric">';
        celdaNota.innerHTML = '<input class="form-control" id="nota" type="text" name="nota[]">';
        celdaAcciones.innerHTML = '<button class="btn btn-danger" type="button" onclick="eliminarFila(this)">Eliminar</button>';
    
        // Aplicar la validación de caracteres a los nuevos campos
        limitarCaracteres();
    }
</script>
<script>
    // Elimina cualquier carácter que no sea número // Reemplaza cualquier carácter que no sea número entero
    function validarEnteros(input) {
        input.value = input.value.replace(/\D/g, '');
    }
</script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    document.getElementById("pedido_especial").addEventListener("submit", function (event) {
        event.preventDefault();

        // Mostrar un SweetAlert de cargando
        Swal.fire({
            title: 'Guardando pedido...',
            html: 'Por favor espera',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });

        // Desactivar el botón de enviar para evitar clics múltiples
        const botonEnviar = this.querySelector('input[type="submit"]');
        botonEnviar.disabled = true;

        var formData = new FormData(this);

        fetch('PE_controller/pedido_especial.php', {
            method: 'POST',
            body: formData
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('Error en la respuesta del servidor');
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                Swal.fire({
                    icon: 'success',
                    title: '¡Pedido Registrado!',
                    text: data.message,
                    timer: 3000,
                    showConfirmButton: false
                });

                setTimeout(() => {
                    location.reload(); // Recarga la página para obtener el nuevo folio
                }, 3000);
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Error al registrar el pedido',
                    text: data.message
                });
                botonEnviar.disabled = false; // Rehabilitar si hubo error
            }
        })
        .catch(error => {
            console.error('Error en el envío:', error);
            Swal.fire({
                icon: 'error',
                title: 'Error de conexión',
                text: 'No se pudo enviar el pedido. Intenta de nuevo más tarde.'
            });
            botonEnviar.disabled = false;
        });
    });
});
</script>
<script>
function limitarCaracteres() {
    
    document.querySelectorAll('input[name=no_cliente]').forEach(function (input) {
        input.addEventListener('input', function () {
            if (this.value.length > 6) {
                this.value = this.value.slice(0, 6); // Limitar a 40 caracteres
            }
        });
    });
    
    document.querySelectorAll('input[name="sku[]"]').forEach(function (input) {
        input.addEventListener('input', function () {
            if (this.value.length > 20) {
                this.value = this.value.slice(0, 20); // Limitar a 20 caracteres
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

document.addEventListener('DOMContentLoaded', function() {
    limitarCaracteres(); // Aplicar restricciones a los campos iniciales
});
</script>
</body>
</html>

<?php
include("src/templates/adminfooter.php");
?>