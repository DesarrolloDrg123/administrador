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
    $sql = "SELECT folio FROM control_folios_co WHERE id = 1 FOR UPDATE";
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
                <h2 class="text-center mb-4"><i class="bi bi-box-seam"></i>Nueva Cotización</h2>

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
                    <label class="form-label">Solicitante: <span class="fw-bold"><?php echo htmlspecialchars($_SESSION['nombre']) ?></span></label><br>
                </div>

                <!-- Empresa / Nombre Cliente -->
                <div class="row g-3 mb-3">

                    <div class="col-md-6">
                        <label for="empresa" class="form-label">Empresa*</label>
                        <div class="input-group">
                            <span class="input-group-text">
                                <i class="fas fa-building"></i>
                            </span>
                            <input class="form-control" type="text" id="empresa" name="empresa" placeholder="Nombre de la empresa" required>
                        </div>
                    </div>
                
                    <div class="col-md-6">
                        <label for="nombre_cliente" class="form-label">Nombre del Cliente*</label>
                        <div class="input-group">
                            <span class="input-group-text">
                                <i class="fas fa-user"></i>
                            </span>
                            <input class="form-control" type="text" id="nombre_cliente" name="nombre_cliente" placeholder="Nombre y Apellido" required>
                        </div>
                    </div>
                
                </div>

                <!-- Telefono / Celular / Correo -->
                <div class="row g-3 mb-3">

                    <div class="col-md-4">
                        <label for="telefono" class="form-label">Teléfono</label>
                        <div class="input-group">
                            <span class="input-group-text">
                                <i class="fas fa-phone"></i>
                            </span>
                            <input type="tel" class="form-control" id="telefono" name="telefono" placeholder="Ej. 8711234567" pattern="[0-9]{10}">
                        </div>
                    </div>
                
                    <div class="col-md-4">
                        <label for="celular" class="form-label">Celular</label>
                        <div class="input-group">
                            <span class="input-group-text">
                                <i class="fas fa-mobile"></i>
                            </span>
                            <input type="tel" class="form-control" id="celular" name="celular" placeholder="Ej. 8711234567" pattern="[0-9]{10}">
                        </div>
                    </div>
                
                    <div class="col-md-4">
                        <label for="correo_cliente" class="form-label">Correo</label>
                        <div class="input-group">
                            <span class="input-group-text">
                                <i class="fas fa-envelope"></i>
                            </span>
                            <input type="email" class="form-control" id="correo_cliente" name="correo_cliente" placeholder="ejemplo@correo.com">
                        </div>
                    </div>
                
                </div>

                <!-- Observaciones -->
                <div class="mb-3">
                    <label for="observaciones" class="form-label">Observaciones</label>
                    <textarea class="form-control" id="observaciones" name="observaciones" rows="3"></textarea>
                </div>
                
                <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" value="1" id="checkRfid" name="is_rfid">
                    <label class="form-check-label" for="checkRfid">
                        ¿Es un proyecto RFID?
                    </label>
                </div>

                <!-- Tabla de Detalles -->
                <h4 class="text-center mb-3 mt-4"><i class="bi bi-card-list"></i> Detalles de Productos</h4>
                
                <th>
                    <div class="d-flex align-items-center">
                        
                        <button class="btn btn-success" type="button" onclick="abrirSelectorDeArchivo()" title="Importar Excel">
                            <i class="fas fa-file-import"></i> Importar Excel
                        </button>
                
                        <a href="COT_controller/plantilla/Plantilla_de_Cotizacion.xlsx" class="ms-3 small" download>
                            Descargar plantilla
                        </a>
                
                    </div>
                </th>
                
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
                                <th>
                                    <button id="btnAgregarFila" class="btn btn-primary" type="button" title="Agregar fila"> Agregar Fila
                                    </button>
                                </th>
                            </tr>
                        </thead>
                        <tbody id="cuerpoTabla">
                            <tr>
                                <td></td>
                                <td><input class="form-control" type="text" name="sku[]" oninput="verificarCampos(this)"></td>
                                <td><input class="form-control" type="text" name="descripcion[]" oninput="verificarCampos(this)"></td>
                                <td><input class="form-control" type="number" name="cantidad[]" oninput="verificarCampos(this)" inputmode="numeric"></td>
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
    // Se ejecuta cuando todo el HTML ha sido cargado
    document.addEventListener('DOMContentLoaded', () => {

        // =================================================================
        // === 1. CONSTANTES Y SELECTORES GLOBALES (SIEMPRE PRIMERO)
        // =================================================================
        const formPedido = document.getElementById('pedido_especial');
        const importadorInput = document.getElementById('importadorExcel');
        const cuerpoTabla = document.getElementById('cuerpoTabla');
        const btnAgregarFila = document.getElementById('btnAgregarFila');


        // =================================================================
        // === 2. DEFINICIÓN DE FUNCIONES
        // =================================================================

        // --- Funciones de la Tabla ---
        const agregarFila = () => {
            const nuevaFilaHTML = `
                <tr>
                    <td></td>
                    <td><input class="form-control" type="text" name="sku[]" required oninput="verificarCampos(this)"></td>
                    <td><input class="form-control" type="text" name="descripcion[]" required oninput="verificarCampos(this)"></td>
                    <td><input class="form-control" type="number" name="cantidad[]" required oninput="verificarCampos(this)" inputmode="numeric"></td>
                    <td><input class="form-control" type="text" name="nota[]"></td>
                    <td><button class="btn btn-danger" type="button" onclick="eliminarFila(this)">Eliminar</button></td>
                </tr>
            `;
            cuerpoTabla.insertAdjacentHTML('beforeend', nuevaFilaHTML);
            actualizarNumerosDeFila();
        };

        window.eliminarFila = (boton) => {
            const fila = boton.closest('tr');
            if (fila) {
                fila.remove();
                actualizarNumerosDeFila();
            }
        };
        
        const actualizarNumerosDeFila = () => {
            const filas = cuerpoTabla.querySelectorAll('tr');
            filas.forEach((fila, index) => {
                const celdaNumero = fila.querySelector('td:first-child');
                if (celdaNumero) {
                    celdaNumero.textContent = index + 1;
                }
            });
        };

        const existeFilaVacia = () => {
            const filas = cuerpoTabla.querySelectorAll('tr');
            return Array.from(filas).some(fila => {
                const sku = fila.querySelector('input[name="sku[]"]').value;
                const descripcion = fila.querySelector('input[name="descripcion[]"]').value;
                const cantidad = fila.querySelector('input[name="cantidad[]"]').value;
                return !sku || !descripcion || !cantidad;
            });
        };

        window.verificarCampos = (input) => {
            const fila = input.closest('tr');
            const sku = fila.querySelector('input[name="sku[]"]').value;
            const descripcion = fila.querySelector('input[name="descripcion[]"]').value;
            const cantidad = fila.querySelector('input[name="cantidad[]"]').value;

            if (sku && descripcion && cantidad) {
                if (!existeFilaVacia()) {
                    agregarFila();
                }
            }
        };

        // --- Funciones de Importación ---
        window.abrirSelectorDeArchivo = () => {
            importadorInput.click();
        };

        const manejarImportacion = (evento) => {
            const archivo = evento.target.files[0];
            if (!archivo) return;

            const reader = new FileReader();
            reader.onload = (e) => {
                const data = new Uint8Array(e.target.result);
                const workbook = XLSX.read(data, { type: 'array' });
                const sheetName = workbook.SheetNames[0];
                const worksheet = workbook.Sheets[sheetName];
                const jsonData = XLSX.utils.sheet_to_json(worksheet, { header: 1 });

                cuerpoTabla.innerHTML = '';
                jsonData.slice(1).forEach(filaData => {
                    if (filaData.length === 0) return;
                    const [sku = '', descripcion = '', cantidad = '', nota = ''] = filaData;
                    const nuevaFilaHTML = `
                        <tr>
                            <td></td>
                            <td><input class="form-control" type="text" name="sku[]" value="${sku}" oninput="verificarCampos(this)"></td>
                            <td><input class="form-control" type="text" name="descripcion[]" value="${descripcion}" oninput="verificarCampos(this)"></td>
                            <td><input class="form-control" type="number" name="cantidad[]" value="${cantidad}" oninput="verificarCampos(this)" inputmode="numeric"></td>
                            <td><input class="form-control" type="text" name="nota[]" value="${nota}"></td>
                            <td><button class="btn btn-danger" type="button" onclick="eliminarFila(this)">Eliminar</button></td>
                        </tr>`;
                    cuerpoTabla.insertAdjacentHTML('beforeend', nuevaFilaHTML);
                });
                actualizarNumerosDeFila();
                evento.target.value = '';
            };
            reader.readAsArrayBuffer(archivo);
        };

        // --- Función de Envío de Formulario ---
        const enviarFormulario = (evento) => {
            evento.preventDefault();
            const botonEnviar = formPedido.querySelector('input[type="submit"]');
            botonEnviar.disabled = true;

            Swal.fire({
                title: 'Guardando cotizacion...',
                html: 'Por favor espera',
                allowOutsideClick: false,
                didOpen: () => Swal.showLoading()
            });

            const formData = new FormData(formPedido);
            fetch('COT_controller/guardar_cotizacion.php', { method: 'POST', body: formData })
                .then(response => {
                    if (!response.ok) throw new Error('Error en la respuesta del servidor');
                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        Swal.fire({ icon: 'success', title: '¡Cotización Registrada!', text: data.message, timer: 3000, showConfirmButton: false });
                        setTimeout(() => location.reload(), 3000);
                    } else {
                        Swal.fire({ icon: 'error', title: 'Error al registrar el pedido', text: data.message });
                        botonEnviar.disabled = false;
                    }
                })
                .catch(error => {
                    console.error('Error en el envío:', error);
                    Swal.fire({ icon: 'error', title: 'Error de conexión', text: 'No se pudo enviar el pedido. Intenta de nuevo más tarde.' });
                    botonEnviar.disabled = false;
                });
        };


        // =================================================================
        // === 3. ASIGNACIÓN DE EVENTOS E INICIALIZACIÓN (SIEMPRE AL FINAL)
        // =================================================================
        if (formPedido) {
            formPedido.addEventListener('submit', enviarFormulario);
        }
        if (importadorInput) {
            importadorInput.addEventListener('change', manejarImportacion);
        }
        if (btnAgregarFila) {
            btnAgregarFila.addEventListener('click', agregarFila);
        }

        // Llamadas iniciales al cargar la página
        actualizarNumerosDeFila();
    });
</script>

</body>
</html>

<?php
include("src/templates/adminfooter.php");
?>