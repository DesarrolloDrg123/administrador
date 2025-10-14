<?php
session_start();
require("config/db.php");
include("src/templates/adminheader.php");

// 1. Validaciones de seguridad y de datos
if (!isset($_SESSION['loggedin']) || !$_SESSION['loggedin']) {
    header("Location: index.php");
    exit();
}
if (!isset($_GET['folio'])) {
    echo "Folio no proporcionado.";
    exit();
}

$folio = $_GET['folio'];

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

$productos = ObtenerProductos($conn, $folio);

if (empty($productos)) {
    echo "<div class='container mt-5'><div class='alert alert-danger'>No se encontraron productos para el folio proporcionado o el folio es inválido.</div></div>";
    include("src/templates/adminfooter.php");
    exit();
}

$folio_formateado = str_pad($folio, 9, "0", STR_PAD_LEFT);
?>
<style>
    .container {
        max-width: 1600px;
        margin-top: 50px;
    }
</style>

<div class="container mt-4">
    <div class="card shadow-lg border-0">
        <div class="card-body p-4">
            <h2 class="text-center mb-4">
                <i class="fas fa-dollar-sign"></i> Cotizar Productos del Folio: 
                <span class="text-danger"><?= htmlspecialchars($folio_formateado) ?></span>
            </h2>
            <hr>

            <form id="formCotizacion" action="COT_controller/guardar_precios.php" method="post">
                <input type="hidden" name="folio" value="<?= htmlspecialchars($folio) ?>">
                <input type="hidden" name="user_cotizador" value="<?= htmlspecialchars($_SESSION['nombre']) ?>">

                <div class="table-responsive">
                    <table class="table table-bordered align-middle">
                        <thead class="table-light">
                            <tr>
                                <th rowspan="2" class="text-center align-middle">Producto</th>
                                <th colspan="5" class="text-center">Proveedor 1</th>
                                <th colspan="5" class="text-center">Proveedor 2</th>
                                <th colspan="5" class="text-center">Proveedor 3</th>
                            </tr>
                            <tr>
                                <th>Nombre</th><th>Costo</th><th>Disponibilidad</th><th>T. Entrega</th><th>Rec.</th>
                                <th>Nombre</th><th>Costo</th><th>Disponibilidad</th><th>T. Entrega</th><th>Rec.</th>
                                <th>Nombre</th><th>Costo</th><th>Disponibilidad</th><th>T. Entrega</th><th>Rec.</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($productos as $producto): ?>
                                <tr>
                                    <td>
                                        <strong>SKU:</strong> <?= htmlspecialchars($producto['sku']) ?><br>
                                        <small><?= htmlspecialchars($producto['descripcion']) ?></small><br>
                                        <strong>Cant:</strong> <?= htmlspecialchars($producto['cantidad']) ?>
                                        <input type="hidden" name="id_producto[]" value="<?= $producto['id'] ?>">
                                    </td>
                                    
                                    <td><input type="text" name="proveedor1[]" class="form-control form-control-sm" value="<?= htmlspecialchars($producto['proveedor1']) ?>"></td>
                                    <td><input type="number" name="costo1[]" class="form-control form-control-sm" step="0.01" min="0" value="<?= htmlspecialchars($producto['costo1']) ?>"></td>
                                    <td><input type="text" name="disponibilidad1[]" class="form-control form-control-sm" value="<?= htmlspecialchars($producto['disponibilidad1']) ?>"></td>
                                    <td><input type="text" name="tiempo_entrega1[]" class="form-control form-control-sm" value="<?= htmlspecialchars($producto['tiempo_entrega1']) ?>"></td>
                                    <td><div class="form-check d-flex justify-content-center"><input class="form-check-input" type="radio" name="recomendacion[<?= $producto['id'] ?>]" value="1" <?= ($producto['recomendacion1'] == 1) ? 'checked' : '' ?>></div></td>
                                    
                                    <td><input type="text" name="proveedor2[]" class="form-control form-control-sm" value="<?= htmlspecialchars($producto['proveedor2']) ?>"></td>
                                    <td><input type="number" name="costo2[]" class="form-control form-control-sm" step="0.01" min="0" value="<?= htmlspecialchars($producto['costo2']) ?>"></td>
                                    <td><input type="text" name="disponibilidad2[]" class="form-control form-control-sm" value="<?= htmlspecialchars($producto['disponibilidad2']) ?>"></td>
                                    <td><input type="text" name="tiempo_entrega2[]" class="form-control form-control-sm" value="<?= htmlspecialchars($producto['tiempo_entrega2']) ?>"></td>
                                    <td><div class="form-check d-flex justify-content-center"><input class="form-check-input" type="radio" name="recomendacion[<?= $producto['id'] ?>]" value="2" <?= ($producto['recomendacion2'] == 1) ? 'checked' : '' ?>></div></td>
                                    
                                    <td><input type="text" name="proveedor3[]" class="form-control form-control-sm" value="<?= htmlspecialchars($producto['proveedor3']) ?>"></td>
                                    <td><input type="number" name="costo3[]" class="form-control form-control-sm" step="0.01" min="0" value="<?= htmlspecialchars($producto['costo3']) ?>"></td>
                                    <td><input type="text" name="disponibilidad3[]" class="form-control form-control-sm" value="<?= htmlspecialchars($producto['disponibilidad3']) ?>"></td>
                                    <td><input type="text" name="tiempo_entrega3[]" class="form-control form-control-sm" value="<?= htmlspecialchars($producto['tiempo_entrega3']) ?>"></td>
                                    <td><div class="form-check d-flex justify-content-center"><input class="form-check-input" type="radio" name="recomendacion[<?= $producto['id'] ?>]" value="3" <?= ($producto['recomendacion3'] == 1) ? 'checked' : '' ?>></div></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="text-center mt-4">
                    <button type="submit" name="accion" value="guardar_borrador" class="btn btn-secondary btn-lg">
                        <i class="fas fa-save"></i> Guardar Borrador
                    </button>
                    <button type="submit" name="accion" value="enviar_cotizacion" class="btn btn-primary btn-lg">
                        <i class="fas fa-paper-plane"></i> Enviar Cotización
                    </button>
                    <a href="javascript:history.back()" class="btn btn-light btn-lg border">Cancelar</a>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
// Primero, movemos la lógica de envío a su propia función para poder reutilizarla.
function enviarFormulario(formData) {
    // 1. Mostrar una alerta de "Cargando"
    Swal.fire({
        title: 'Guardando...',
        text: 'Por favor, espera.',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });
    
    // 2. Enviar los datos usando Fetch (AJAX)
    fetch('COT_controller/guardar_precios.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        // 3. Manejar la respuesta del servidor
        if (data.success) {
            Swal.fire({
                icon: 'success',
                title: '¡Éxito!',
                text: data.message,
            }).then(() => {
                window.location.href = 'COT_procesar_cotizaciones.php';
            });
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: data.message,
            });
        }
    })
    .catch(error => {
        // 4. Manejar errores de red
        console.error('Error:', error);
        Swal.fire({
            icon: 'error',
            title: 'Error de Conexión',
            text: 'No se pudo conectar con el servidor. Intenta de nuevo.',
        });
    });
}

// Evento principal que se dispara al enviar el formulario
document.getElementById('formCotizacion').addEventListener('submit', function(event) {
    // Prevenimos el envío tradicional siempre
    event.preventDefault();

    const formData = new FormData(this);
    const submitter = event.submitter; // El botón que fue presionado

    if (submitter) {
        formData.append(submitter.name, submitter.value);
    }

    // CAMBIO CLAVE: Verificamos qué botón se presionó
    if (submitter && submitter.value === 'enviar_cotizacion') {
        // Si fue "Enviar Cotización", mostramos la alerta de confirmación
        Swal.fire({
            title: '¿Estás seguro?',
            text: "Esta acción enviará la cotización y ya no podra realizar ningún otro cambio.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Sí, ¡enviar!',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            // Si el usuario hace clic en "Sí, ¡enviar!"
            if (result.isConfirmed) {
                // Procedemos a enviar el formulario
                enviarFormulario(formData);
            }
        });
    } else {
        // Si se presionó "Guardar Borrador" (o cualquier otro botón), enviamos directamente
        enviarFormulario(formData);
    }
});
</script>

<?php include("src/templates/adminfooter.php"); ?>