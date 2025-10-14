<?php
session_start();
require("config/db.php");
include("src/templates/adminheader.php");

// 1. Validaciones de seguridad
if (!isset($_SESSION['loggedin']) || !$_SESSION['loggedin']) {
    header("Location: index.php");
    exit();
}
if (!isset($_GET['folio'])) {
    die("Folio de cotización no proporcionado.");
}

$folio_cotizacion = $_GET['folio'];

// =================================================================
// === PASO A: OBTENER DATOS DE LA COTIZACIÓN APROBADA
// =================================================================
function ObtenerDatosGenerales($conn, $folio) {
    $sql = "SELECT * FROM datos_generales_co WHERE folio = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $folio);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

function ObtenerProductos($conn, $folio) {
    $sql = "SELECT * FROM productos_co WHERE folio = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $folio);
    $stmt->execute();
    $result = $stmt->get_result();
    $productos = [];
    while ($row = $result->fetch_assoc()) {
        $productos[] = $row;
    }
    return $productos;
}

$cotizacion = ObtenerDatosGenerales($conn, $folio_cotizacion);
$productos_cotizados = ObtenerProductos($conn, $folio_cotizacion);

if (!$cotizacion) {
    die("No se encontró la cotización.");
}

// =================================================================
// === PASO B: PREPARAR EL NUEVO FORMULARIO DE PEDIDO ESPECIAL
// =================================================================

// Generar un nuevo folio para el Pedido Especial (lógica que ya tenías)
$sql_folio_pe = "SELECT folio FROM control_folios_pe WHERE id = 1 FOR UPDATE";
$resultado_pe = $conn->query($sql_folio_pe);
$fila_pe = $resultado_pe->fetch_assoc();
$nuevo_folio_pe = ($fila_pe['folio'] ?? 0) + 1;
$folio_formateado_pe = sprintf('%09d', $nuevo_folio_pe);

// Consultas para los select de Uso y Sucursal (lógica que ya tenías)
$resultUso = $conn->query("SELECT * FROM uso");
$resultSucursal = $conn->query("SELECT * FROM sucursales");

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Generar Pedido Especial</title>
</head>
<body>
    <div class="container mt-4">
        <div class="card shadow-lg border-0">
            <div class="card-body p-4">
                <form id="pedido_especial" action="COT_controller/generar_pedido_desde_cot.php" method="post">
                    <h2 class="text-center mb-4"><i class="bi bi-box-seam"></i> Revisar y Generar Pedido Especial</h2>

                    <div class="row mb-4"><div class="col-md-6"><h5>Nuevo Folio de Pedido: <span class="text-danger fw-bold"><?= $folio_formateado_pe; ?></span></h5><input type="hidden" name="folio" value="<?= $folio_formateado_pe; ?>"></div></div>
                    
                    <input type="hidden" name="folio_cotizacion_origen" value="<?= htmlspecialchars($folio_cotizacion) ?>">

                    <div class="row g-3 mb-3">
                        <div class="col-md-6"><label class="form-label">No. de Cliente*</label><input class="form-control" type="number" name="no_cliente" required></div>
                        <div class="col-md-6"><label class="form-label">Nombre del Cliente*</label><input class="form-control" type="text" name="nombre_cliente" value="<?= htmlspecialchars($cotizacion['cliente']) ?>" required></div>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-md-6"><label class="form-label">Uso*</label><select class="form-select" name="uso" required><?php while ($row = $resultUso->fetch_assoc()) { echo "<option value='" . $row['id'] . "'>" . $row['nombre'] . "</option>"; } ?></select></div>
                        <div class="col-md-6"><label class="form-label">Sucursal*</label><select class="form-select" name="sucursal" required><?php while ($row = $resultSucursal->fetch_assoc()) { echo "<option value='" . $row['id'] . "'>" . $row['sucursal'] . "</option>"; } ?></select></div>
                    </div>
                    <div class="mb-3"><label class="form-label">Observaciones</label><textarea class="form-control" name="observaciones" rows="3"><?= htmlspecialchars($cotizacion['observaciones']) ?></textarea></div>

                    <h4 class="text-center mb-3 mt-4">Selección de Proveedor por Producto</h4>
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead class="table-light">
                                <tr>
                                    <th>Producto</th>
                                    <th>Opciones de Proveedor (Selecciona una por producto)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($productos_cotizados as $producto): ?>
                                    <tr>
                                        <td>
                                            <strong>SKU:</strong> <?= htmlspecialchars($producto['sku']) ?><br>
                                            <small><?= htmlspecialchars($producto['descripcion']) ?></small><br>
                                            <strong>Cant:</strong> <?= htmlspecialchars($producto['cantidad']) ?>
                                        </td>
                                        
                                        <td>
                                            <?php for ($i = 1; $i <= 3; $i++):
                                                $proveedor = $producto["proveedor{$i}"];
                                                
                                                // Si no hay proveedor, saltamos esta opción
                                                if (empty($proveedor)) continue;
                    
                                                $costo = $producto["costo{$i}"];
                                                $disponibilidad = $producto["disponibilidad{$i}"];
                                                // CAMBIO: Obtenemos el tiempo de entrega
                                                $tiempo_entrega = $producto["tiempo_entrega{$i}"];
                                                $es_recomendado = $producto["recomendacion{$i}"] == 1;
                                            ?>
                                                <div class="form-check">
                                                    <input class="form-check-input" type="radio" 
                                                           name="proveedor_seleccionado[<?= $producto['id'] ?>]" 
                                                           id="prod_<?= $producto['id'] ?>_prov_<?= $i ?>"
                                                           value="<?= $i ?>" <?= $es_recomendado ? 'checked' : '' ?> required>
                                                    <label class="form-check-label" for="prod_<?= $producto['id'] ?>_prov_<?= $i ?>">
                                                        <strong><?= htmlspecialchars($proveedor) ?></strong> - 
                                                        Costo: $<?= number_format($costo, 2) ?> - 
                                                        Disp: <?= htmlspecialchars($disponibilidad) ?> -
                                                        Tiempo de Entrega: <?= htmlspecialchars($tiempo_entrega) ?>
                                                        <?= $es_recomendado ? '<span class="badge bg-success ms-2">Recomendado</span>' : '' ?>
                                                    </label>
                                                </div>
                                            <?php endfor; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="text-center mt-4">
                        <input type="submit" class="btn btn-success px-4" value="Crear Pedido Especial">
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

        fetch('COT_controller/generar_pedido_desde_cot.php', {
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
                    // Quitamos el timer para que el usuario presione OK
                    confirmButtonText: 'OK'
                }).then((result) => {
                    // Cuando el usuario presiona OK, lo redirigimos
                    if (result.isConfirmed) {
                        window.location.href = 'COT_mis_cotizaciones.php'; // CAMBIO CLAVE AQUÍ
                    }
                });
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

document.addEventListener('DOMContentLoaded', function() {
    limitarCaracteres(); // Aplicar restricciones a los campos iniciales
});
</script>
</body>
</html>
<?php include("src/templates/adminfooter.php"); ?>