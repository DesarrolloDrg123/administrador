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
    die("Folio no proporcionado.");
}

$folio = $_GET['folio'];

// 2. Reutilizamos las funciones para obtener los datos existentes
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

$cotizacion = ObtenerDatosGenerales($conn, $folio);
$productos = ObtenerProductos($conn, $folio);

if (!$cotizacion) {
    die("No se encontró la cotización.");
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Cotización</title>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.sheetjs.com/xlsx-latest/package/dist/xlsx.full.min.js"></script>
</head>
<body>
    <div class="container mt-4">
        <div class="card shadow-lg border-0">
            <div class="card-body p-4">
                <form id="formEditarCotizacion" action="COT_controller/actualizar_cotizacion.php" method="post">
                    <h2 class="text-center mb-4"><i class="fas fa-edit"></i> Editar Cotización</h2>
                    
                    <div class="row mb-4 align-items-center">
                        <div class="col-md-6">
                            <h5>Folio: <span class="text-danger fw-bold"><?= htmlspecialchars(str_pad($cotizacion['folio'], 9, "0", STR_PAD_LEFT)) ?></span></h5>
                            <input type="hidden" name="folio" value="<?= htmlspecialchars($cotizacion['folio']) ?>">
                        </div>
                    </div>

                    <?php if (!empty($cotizacion['motivo'])): ?>
                        <div class="alert alert-warning">
                            <strong>Motivo de la devolución:</strong> <?= htmlspecialchars($cotizacion['motivo']) ?>
                        </div>
                    <?php endif; ?>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6"><label for="empresa" class="form-label">Empresa*</label><input class="form-control" type="text" id="empresa" name="empresa" value="<?= htmlspecialchars($cotizacion['empresa']) ?>" required></div>
                        <div class="col-md-6"><label for="nombre_cliente" class="form-label">Nombre del Cliente*</label><input class="form-control" type="text" id="nombre_cliente" name="nombre_cliente" value="<?= htmlspecialchars($cotizacion['cliente']) ?>" required></div>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-md-4"><label for="telefono" class="form-label">Teléfono*</label><input type="tel" class="form-control" id="telefono" name="telefono" value="<?= htmlspecialchars($cotizacion['telefono']) ?>" required></div>
                        <div class="col-md-4"><label for="celular" class="form-label">Celular*</label><input type="tel" class="form-control" id="celular" name="celular" value="<?= htmlspecialchars($cotizacion['celular']) ?>" required></div>
                        <div class="col-md-4"><label for="correo_cliente" class="form-label">Correo*</label><input type="email" class="form-control" id="correo_cliente" name="correo_cliente" value="<?= htmlspecialchars($cotizacion['correo']) ?>" required></div>
                    </div>
                    <div class="mb-3"><label for="observaciones" class="form-label">Observaciones</label><textarea class="form-control" id="observaciones" name="observaciones" rows="3"><?= htmlspecialchars($cotizacion['observaciones']) ?></textarea></div>
                    <div class="form-check mb-3"><input class="form-check-input" type="checkbox" value="1" id="checkRfid" name="is_rfid" <?= ($cotizacion['rfid'] == 1) ? 'checked' : '' ?>><label class="form-check-label" for="checkRfid">¿Es un proyecto RFID?</label></div>

                    <h4 class="text-center mb-3 mt-4">Detalles de Productos</h4>
                    
                    <div class="d-flex align-items-center mb-3">
                        <button class="btn btn-success" type="button" onclick="abrirSelectorDeArchivo()" title="Importar Excel">
                            <i class="fas fa-file-import"></i> Importar Excel
                        </button>
                        <a href="ruta/a/tu/plantilla.xlsx" class="ms-3 small" download>Descargar plantilla</a>
                    </div>
                    <input type="file" id="importadorExcel" style="display: none;" accept=".xlsx, .xls, .csv">
                    
                    <div class="table-responsive">
                        <table id="tabla" class="table table-striped align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>#</th>
                                    <th>N° de Parte</th>
                                    <th>Descripción*</th>
                                    <th>Cantidad*</th>
                                    <th>Nota</th>
                                    <th><button id="btnAgregarFila" type="button" class="btn btn-primary btn-sm">Agregar Fila</button></th>
                                </tr>
                            </thead>
                            <tbody id="cuerpoTabla">
                                <?php foreach ($productos as $index => $producto): ?>
                                    <tr>
                                        <td><?= $index + 1 ?></td>
                                        <td><input class="form-control" type="text" name="sku[]" value="<?= htmlspecialchars($producto['sku']) ?>"></td>
                                        <td><input class="form-control" type="text" name="descripcion[]" value="<?= htmlspecialchars($producto['descripcion']) ?>" required></td>
                                        <td><input class="form-control" type="number" name="cantidad[]" value="<?= htmlspecialchars($producto['cantidad']) ?>" required></td>
                                        <td><input class="form-control" type="text" name="nota[]" value="<?= htmlspecialchars($producto['notas']) ?>"></td>
                                        <td><button class="btn btn-danger btn-sm" type="button" onclick="eliminarFila(this)">Eliminar</button></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="text-center mt-4">
                        <button type="submit" class="btn btn-success px-4">Guardar Cambios</button>
                        <a href="javascript:history.back()" class="btn btn-secondary">Cancelar</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script>
    document.addEventListener('DOMContentLoaded', () => {
        const form = document.getElementById('formEditarCotizacion');
        const cuerpoTabla = document.getElementById('cuerpoTabla');
        const btnAgregarFila = document.getElementById('btnAgregarFila');
        const importadorInput = document.getElementById('importadorExcel');

        // --- MANEJO DE LA TABLA (AGREGAR, ELIMINAR, NUMERAR) ---

        const actualizarNumerosDeFila = () => {
            const filas = cuerpoTabla.querySelectorAll('tr');
            filas.forEach((fila, index) => {
                const celdaNumero = fila.querySelector('td:first-child');
                if (celdaNumero) celdaNumero.textContent = index + 1;
            });
        };

        window.eliminarFila = (boton) => {
            boton.closest('tr').remove();
            actualizarNumerosDeFila();
        };

        const agregarFila = (sku = '', desc = '', cant = '', nota = '') => {
            const nuevaFilaHTML = `
                <tr>
                    <td></td>
                    <td><input class="form-control" type="text" name="sku[]" value="${sku}"></td>
                    <td><input class="form-control" type="text" name="descripcion[]" value="${desc}" required></td>
                    <td><input class="form-control" type="number" name="cantidad[]" value="${cant}" required></td>
                    <td><input class="form-control" type="text" name="nota[]" value="${nota}"></td>
                    <td><button class="btn btn-danger btn-sm" type="button" onclick="eliminarFila(this)">Eliminar</button></td>
                </tr>`;
            cuerpoTabla.insertAdjacentHTML('beforeend', nuevaFilaHTML);
            actualizarNumerosDeFila();
        };

        if (btnAgregarFila) {
            btnAgregarFila.addEventListener('click', () => agregarFila());
        }

        // --- IMPORTACIÓN DE EXCEL ---

        window.abrirSelectorDeArchivo = () => {
            importadorInput.click();
        };

        if (importadorInput) {
            importadorInput.addEventListener('change', (evento) => {
                const archivo = evento.target.files[0];
                if (!archivo) return;
                const reader = new FileReader();
                reader.onload = (e) => {
                    const data = new Uint8Array(e.target.result);
                    const workbook = XLSX.read(data, { type: 'array' });
                    const sheetName = workbook.SheetNames[0];
                    const worksheet = workbook.Sheets[sheetName];
                    const jsonData = XLSX.utils.sheet_to_json(worksheet, { header: 1 });

                    cuerpoTabla.innerHTML = ''; // Limpiar la tabla antes de importar
                    jsonData.slice(1).forEach(filaData => {
                        if (filaData.length > 0) {
                            agregarFila(filaData[0] || '', filaData[1] || '', filaData[2] || '', filaData[3] || '');
                        }
                    });
                    evento.target.value = '';
                };
                reader.readAsArrayBuffer(archivo);
            });
        }

        // --- ENVÍO DEL FORMULARIO CON AJAX ---

        if (form) {
            form.addEventListener('submit', (event) => {
                event.preventDefault();
                Swal.fire({
                    title: 'Guardando Cambios...',
                    text: 'Por favor, espera.',
                    allowOutsideClick: false,
                    didOpen: () => Swal.showLoading()
                });
                
                const formData = new FormData(form);

                fetch('COT_controller/actualizar_cotizacion.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        Swal.fire({
                            icon: 'success',
                            title: '¡Actualizado!',
                            text: data.message
                        }).then(() => {
                            window.location.href = 'COT_mis_cotizaciones.php'; // O a donde quieras redirigir
                        });
                    } else {
                        Swal.fire({ icon: 'error', title: 'Error', text: data.message });
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    Swal.fire({ icon: 'error', title: 'Error de Conexión', text: 'No se pudo conectar con el servidor.' });
                });
            });
        }
    });
    </script>
</body>
</html>

<?php include("src/templates/adminfooter.php"); ?>