<?php
session_start();
if (!isset($_SESSION['loggedin']) || !$_SESSION['loggedin']) {
    header("Location: index.php");
    exit();
}

$usuario_id = $_SESSION['usuario_id'];
include("src/templates/adminheader.php");
require("config/db.php");
require 'vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$autorizacion_id = $usuario_id;
$alertMessage = '';
$alertType = '';
$folio_formateado = '';

try {
    $conn->begin_transaction();
    $stmt = $conn->prepare("SELECT ultimo_folio FROM folio WHERE id = 1 FOR UPDATE");
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $ultimo_folio = $row['ultimo_folio'];
    
    $nuevo_folio = $ultimo_folio + 1;
    $folio_formateado = sprintf("%09d", $nuevo_folio);

    $_SESSION['folio_formateado'] = $folio_formateado;

    $conn->commit();
} catch (Exception $e) {
    $conn->rollback();
    die("Error al generar el folio: " . $e->getMessage());
}

?>
<style>
    h1 {
        color: #2c3e50;
        border-bottom: 2px solid #3498db;
        padding-bottom: 10px;
    }
    .validation-feedback {
        display: none; /* Oculto por defecto */
        width: 100%;
        margin-top: 0.25rem;
        font-size: 0.875em; /* 14px si la fuente base es 16px */
        color: #dc3545; /* Color de peligro de Bootstrap */
    }
    
    /* Clase para hacer visible el mensaje de error */
    .validation-feedback.visible {
        display: block;
    }
</style>
<br>
<div class="container mt-4">
    <div class="card shadow-lg border-0">
        <div class="card-body p-4">
            <form method="POST" enctype="multipart/form-data" id="form-transferencia">
                <h2 class="text-center mb-4">Solicitud de Transferencia Electrónica</h2>

                <div class="row mb-4 align-items-center">
                    <div class="col-md-6 text-center">
                        <img src="img/logo-drg.png" alt="Logo DRG" class="img-fluid" style="max-height: 80px;">
                    </div>
                    <div class="col-md-6 text-center">
                        <h5>Folio: <span class="text-danger fw-bold">
                            <?= isset($_SESSION['folio_formateado']) ? htmlspecialchars($_SESSION['folio_formateado']) : '' ?>
                        </span></h5>
                    </div>
                </div>

                <!-- Sucursal -->
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="sucursal" class="form-label">Sucursal*</label>
                        <select class="form-select" name="sucursales" id="sucursal" required>
                            <option value="" disabled selected>Selecciona una Sucursal</option>
                            <?php
                            // Tu código PHP para llenar las sucursales no cambia
                            try {
                                $stmt = $conn->prepare("SELECT id, sucursal FROM sucursales");
                                $stmt->execute();
                                $result = $stmt->get_result();
                                while ($row = $result->fetch_assoc()) {
                                    echo '<option value="' . htmlspecialchars($row['id']) . '">' . htmlspecialchars($row['sucursal']) . '</option>';
                                }
                            } catch (Exception $e) { /* ... */ }
                            ?>
                            <option value="varias">Corporativo</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label for="departamento" class="form-label">Departamento*</label>
                        <select class="form-select" name="departamento" id="departamento" required>
                            <option value="" disabled selected>Selecciona un Departamento</option>
                            <?php
                            // Tu código PHP para llenar los departamentos no cambia
                            try {
                                $stmt = $conn->prepare("SELECT id, departamento FROM departamentos");
                                $stmt->execute();
                                $result = $stmt->get_result();
                                while ($row = $result->fetch_assoc()) {
                                    echo '<option value="' . htmlspecialchars($row['id']) . '">' . htmlspecialchars($row['departamento']) . '</option>';
                                }
                            } catch (Exception $e) { /* ... */ }
                            ?>
                        </select>
                    </div>
                </div>

                <div class="mb-3" id="sucursales-multiples-container" style="display: none;">
                    <label class="form-label">Distribución por Sucursal</label>
                    <div class="p-3 bg-light rounded border">
                        
                        <div id="sucursales-multiples-lista"></div>
                
                        <button type="button" id="agregar-sucursal" class="btn btn-outline-primary btn-sm mt-2">
                            <i class="fas fa-plus"></i> Agregar otra sucursal
                        </button>
                
                        <div class="text-end mt-2">
                            <strong>Total distribuido: $<span id="total-montos">0.00</span></strong>
                        </div>
                    </div>
                </div>
                
                <div class="row g-3 mb-3">
                    <div class="col-md-4">
                        <div id="presupuesto-container" style="display: none; width: 100%;">
                            <label for="presupuesto-disponible" class="form-label">Presupuesto Disponible</label>
                            <div class="input-group">
                                <span class="input-group-text">$</span>
                                <input type="text" id="presupuesto-disponible" class="form-control" readonly style="background-color: #e9ecef; font-weight: bold;">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Beneficiario y Fecha -->
                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label for="beneficiario" class="form-label">Beneficiario*</label>
                        <select class="form-select" name="beneficiario" id="beneficiario" required>
                            <option value="" disabled selected>Selecciona un Beneficiario</option>
                            <?php
                            try {
                                $stmt = $conn->prepare("SELECT id, beneficiario FROM beneficiarios ORDER BY beneficiario ASC");
                                $stmt->execute();
                                $result = $stmt->get_result();
                                while ($row = $result->fetch_assoc()) {
                                    echo '<option value="' . htmlspecialchars($row['id']) . '">' . htmlspecialchars($row['beneficiario']) . '</option>';
                                }
                            } catch (Exception $e) {
                                echo "Error: " . $e->getMessage();
                            }
                            ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label for="fechaSolicitud" class="form-label">Fecha de Solicitud</label>
                        <input 
                            type="date" 
                            name="date" 
                            id="fechaSolicitud" 
                            class="form-control"
                            value="<?php echo date('Y-m-d'); ?>" 
                            readonly
                            required
                        >
                    </div>
                </div>

                <!-- Cuenta y Vencimiento -->
                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label for="noCuenta" class="form-label">No. de Cuenta</label>
                        <input type="text" name="noCuenta" id="noCuenta" class="form-control" placeholder="No. de Cuenta" oninput="this.value = this.value.toUpperCase()">
                    </div>
                    <div class="col-md-6">
                        <label for="endDate" class="form-label">Fecha de Vencimiento*</label>
                        <input type="date" name="endDate" id="endDate" class="form-control" required>
                    </div>
                </div>

                <!-- Importes -->
                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="form-label">Importe en Pesos</label>
                        <div class="input-group">
                            <span class="input-group-text">$</span>
                            <input type="number" name="importe" id="importe" class="form-control" step=".01" placeholder="0.00">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label for="importe-letra" class="form-label">Importe con Letra</label>
                        <input type="text" name="importe-letra" id="importe-letra" class="form-control" placeholder="Ej. Mil pesos 00/100 M.N.">
                    </div>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="form-label">Importe en Dólares</label>
                        <div class="input-group">
                            <span class="input-group-text">$</span>
                            <input type="text" name="importedls" id="importedls" class="form-control" placeholder="0.00">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label for="importedls_letra" class="form-label">Importe con Letra</label>
                        <input type="text" name="importedls_letra" id="importedls_letra" class="form-control" placeholder="Ej. Mil dólares 00/100 USD">
                    </div>
                </div>

                <!-- Categoría -->
                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label for="categoria" class="form-label">Categoría*</label>
                        <select class="form-select" name="categoria" id="categoria" required>
                            <option value="" disabled selected>Selecciona una Categoría</option>
                            <?php
                            try {
                                $stmt = $conn->prepare("SELECT id, categoria FROM categorias");
                                $stmt->execute();
                                $result = $stmt->get_result();
                                while ($row = $result->fetch_assoc()) {
                                    echo '<option value="' . htmlspecialchars($row['id']) . '">' . htmlspecialchars($row['categoria']) . '</option>';
                                }
                            } catch (Exception $e) {
                                echo "Error: " . $e->getMessage();
                            }
                            ?>
                        </select>
                    </div>
                </div>

                <!-- Descripción y Observaciones -->
                <div class="mb-3">
                    <label for="descripcion" class="form-label">Descripción*</label>
                    <textarea class="form-control" name="descripcion" id="descripcion" rows="3" required></textarea>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label for="observaciones" class="form-label">Observaciones Especiales</label>
                        <textarea class="form-control" name="observaciones" id="observaciones" rows="2"></textarea>
                    </div>
                    <div class="col-md-6">
                        <label for="usuarios" class="form-label">Usuario que Autoriza*</label>
                        <select class="form-select" name="autorizacion" id="autorizar" required>
                            <option value="" disabled selected>Selecciona un Autorizador</option>
                            <?php
                            try {
                                $programa = 17;
                                $acceso = 1;
                                $stmt = $conn->prepare("
                                    SELECT u.id, u.nombre 
                                    FROM usuarios u
                                    INNER JOIN permisos p ON u.id = p.id_usuario
                                    WHERE p.id_programa = ? AND p.acceso = ?
                                    ORDER BY u.nombre ASC
                                ");
                                $stmt->bind_param('ii', $programa, $acceso);
                                $stmt->execute();
                                $result = $stmt->get_result();
                        
                                while ($row = $result->fetch_assoc()) {
                                    echo '<option value="' . htmlspecialchars($row['id']) . '">' . htmlspecialchars($row['nombre']) . '</option>';
                                }
                            } catch (Exception $e) {
                                echo "Error: " . $e->getMessage();
                            }
                            ?>
                        </select>
                    </div>
                </div>

                <!-- Documento adjunto -->
                <div class="mb-3">
                    <label for="documento_adjunto" class="form-label">Adjuntar Documento</label>
                    <input type="file" name="documento_adjunto" id="documento_adjunto" class="form-control">
                </div>

                <input type="hidden" name="autorizacion_hidden" id="autorizacion_hidden">

                <div class="text-center">
                    <button type="submit" class="btn btn-success px-4">
                        <i class="bi bi-send-fill"></i> Enviar Solicitud
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<br>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // --- 1. DECLARACIÓN DE ELEMENTOS ---
    const miFormulario = document.getElementById('form-transferencia');
    const sucursalPrincipalSelect = document.getElementById('sucursal');
    const departamentoSelect = document.getElementById('departamento');
    const presupuestoContainer = document.getElementById('presupuesto-container');
    const presupuestoInput = document.getElementById('presupuesto-disponible');
    const contenedorMultiple = document.getElementById('sucursales-multiples-container');
    const listaSucursalesDinamicas = document.getElementById('sucursales-multiples-lista');
    const totalMontosSpan = document.getElementById('total-montos');
    const btnAgregarSucursal = document.getElementById('agregar-sucursal');
    let todasLasSucursales = [];

    // Elementos para la lógica de autorización (la que faltaba)
    const autorizacionSelect = document.getElementById('autorizar');
    const autorizacionHidden = document.getElementById('autorizacion_hidden');


    // --- 2. FUNCIONES AUXILIARES ---

    // --- LÓGICA PARA AUTORIZACIÓN (LA PARTE QUE SE REINTEGRA) ---
    function updateAutorizacion() {
        if (!departamentoSelect || !autorizacionSelect || !autorizacionHidden) return; // Seguridad
        
        const selectedDepartamentoText = departamentoSelect.options[departamentoSelect.selectedIndex].text;
        
        if (selectedDepartamentoText === 'VENTAS' || selectedDepartamentoText === 'SERVICIO TECNICO') {
            autorizacionSelect.value = '2'; // Asegúrate que el ID '2' corresponde al autorizador correcto
            autorizacionSelect.disabled = true;
            autorizacionHidden.value = '2';
        } else {
            autorizacionSelect.disabled = false;
            // Si no está deshabilitado, se asegura que el valor oculto coincida con el visible
            autorizacionHidden.value = autorizacionSelect.value;
        }
    }

    // --- Lógica para el corporativo (ya funcional) ---
    function fetchSucursales() {
        if (todasLasSucursales.length === 0) {
            return fetch('TR_controller/get_sucursales.php')
                .then(res => res.json())
                .then(data => {
                    todasLasSucursales = data;
                    return data;
                });
        }
        return Promise.resolve(todasLasSucursales);
    }
    // ... (El resto de funciones auxiliares no cambian)
    function calcularTotalMontos() { let total = 0; listaSucursalesDinamicas.querySelectorAll('.monto-sucursal').forEach(input => { total += parseFloat(input.value) || 0; }); totalMontosSpan.textContent = total.toFixed(2); }
    function validarDuplicados() { const selects = listaSucursalesDinamicas.querySelectorAll('.sucursal-select'); const valoresSeleccionados = new Set(); let esValido = true; selects.forEach(select => { const feedback = select.parentElement.querySelector('.validation-feedback'); feedback.classList.remove('visible'); }); selects.forEach(select => { const valor = select.value; if (valor) { if (valoresSeleccionados.has(valor)) { esValido = false; select.parentElement.querySelector('.validation-feedback').classList.add('visible'); } valoresSeleccionados.add(valor); } }); return esValido; }
    function agregarFilaSucursal() { fetchSucursales().then(() => { const sucursalesSeleccionadas = Array.from(listaSucursalesDinamicas.querySelectorAll('.sucursal-select')).map(s => s.value); const sucursalesDisponibles = todasLasSucursales.filter(s => !sucursalesSeleccionadas.includes(s.id.toString())); btnAgregarSucursal.style.display = sucursalesDisponibles.length > 0 ? 'inline-block' : 'none'; if (sucursalesDisponibles.length === 0) return; const opcionesHTML = sucursalesDisponibles.map(s => `<option value="${s.id}">${s.sucursal}</option>`).join(''); const divFila = document.createElement('div'); divFila.className = 'row g-3 mb-2 align-items-center sucursal-fila'; divFila.innerHTML = `<div class="col-sm-5"><select name="sucursal_ids[]" class="form-select sucursal-select" required><option value="" disabled selected>Selecciona Sucursal...</option>${opcionesHTML}</select><div class="validation-feedback"><i class="fas fa-exclamation-triangle"></i> Esta sucursal ya fue seleccionada.</div></div><div class="col-sm-3"><div class="input-group"><span class="input-group-text">$</span><input type="number" name="montos[]" class="form-control monto-sucursal" placeholder="Monto" step="0.01" required></div></div><div class="col-sm-3"><div class="input-group"><span class="input-group-text" title="Presupuesto Disponible">Disp.</span><input type="text" class="form-control presupuesto-display" readonly style="background-color: #e9ecef; font-size: 0.9em;"></div></div><div class="col-sm-1 text-end"><button type="button" class="btn btn-danger btn-sm remover-sucursal" title="Eliminar Fila"><i class="fas fa-trash"></i></button></div>`; listaSucursalesDinamicas.appendChild(divFila); }); }
    function actualizarPresupuestoPrincipal() { const sucursalId = sucursalPrincipalSelect.value; const deptoId = departamentoSelect.value; if (sucursalId && sucursalId !== 'varias' && deptoId) { presupuestoContainer.style.display = 'block'; presupuestoInput.value = 'Consultando...'; fetch(`TR_controller/get_presupuesto.php?sucursal_id=${sucursalId}&depto_id=${deptoId}`).then(r => r.json()).then(data => { presupuestoInput.value = data.success ? data.presupuesto.toLocaleString('es-MX', { minimumFractionDigits: 2 }) : data.presupuesto; }).catch(() => presupuestoInput.value = 'Error'); } else { presupuestoContainer.style.display = 'none'; } }
    function mostrarPresupuestoFila(selectSucursal) { const fila = selectSucursal.closest('.sucursal-fila'); if (!fila) return; const presupuestoDisplay = fila.querySelector('.presupuesto-display'); const sucursalId = selectSucursal.value; const deptoId = departamentoSelect.value; if (sucursalId && deptoId) { presupuestoDisplay.value = '...'; fetch(`TR_controller/get_presupuesto.php?sucursal_id=${sucursalId}&depto_id=${deptoId}`).then(r => r.json()).then(data => { presupuestoDisplay.value = data.success ? data.presupuesto.toLocaleString('es-MX', { minimumFractionDigits: 2 }) : data.presupuesto; }).catch(() => presupuestoDisplay.value = 'Error'); } else { presupuestoDisplay.value = ''; } }


    // --- 3. EVENT LISTENERS ---
    sucursalPrincipalSelect.addEventListener('change', function() {
        if (this.value === 'varias') {
            contenedorMultiple.style.display = 'block';
            if (listaSucursalesDinamicas.children.length === 0) agregarFilaSucursal();
        } else {
            contenedorMultiple.style.display = 'none';
        }
        actualizarPresupuestoPrincipal();
    });

    departamentoSelect.addEventListener('change', function() {
        actualizarPresupuestoPrincipal();
        listaSucursalesDinamicas.querySelectorAll('.sucursal-select').forEach(select => mostrarPresupuestoFila(select));
        updateAutorizacion(); // <-- Se añade la llamada aquí
    });
    
    // Listener para el selector de autorización (se reintegra)
    autorizacionSelect.addEventListener('change', function() {
        autorizacionHidden.value = this.value;
    });

    listaSucursalesDinamicas.addEventListener('change', e => { if (e.target.classList.contains('sucursal-select')) { validarDuplicados(); mostrarPresupuestoFila(e.target); } });
    listaSucursalesDinamicas.addEventListener('input', e => { if (e.target.classList.contains('monto-sucursal')) calcularTotalMontos(); });
    listaSucursalesDinamicas.addEventListener('click', e => { const botonRemover = e.target.closest('.remover-sucursal'); if (botonRemover) { botonRemover.closest('.sucursal-fila').remove(); calcularTotalMontos(); validarDuplicados(); btnAgregarSucursal.style.display = 'inline-block'; } });
    btnAgregarSucursal.addEventListener('click', agregarFilaSucursal);

    // --- 4. GESTIÓN DEL ENVÍO DEL FORMULARIO ---
    if (miFormulario) {
        miFormulario.addEventListener('submit', function(e) {
            e.preventDefault();
            if (sucursalPrincipalSelect.value === 'varias' && !validarDuplicados()) {
                Swal.fire({ icon: 'warning', title: 'Sucursales Duplicadas', text: 'Por favor, corrige las sucursales repetidas.' });
                return;
            }
            const formData = new FormData(miFormulario);
            const boton = miFormulario.querySelector('button[type="submit"]');
            if (boton) boton.disabled = true;
            Swal.fire({ title: 'Guardando transferencia...', html: 'Por favor espera', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
            fetch("TR_controller/guardar_transferencia.php", { method: "POST", body: formData })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({ icon: 'success', title: 'Éxito', text: data.message, timer: 2500, showConfirmButton: false }).then(() => location.reload());
                } else {
                    Swal.fire({ icon: 'warning', title: 'Advertencia', text: data.message });
                    if (boton) boton.disabled = false;
                }
            })
            .catch(error => {
                console.error("Error en la solicitud:", error);
                Swal.fire({ icon: 'error', title: 'Error del servidor', text: 'No se pudo procesar la solicitud.' });
                if (boton) boton.disabled = false;
            });
        });
    }

    // --- 5. INICIALIZACIÓN AL CARGAR LA PÁGINA ---
    updateAutorizacion(); // <-- Se llama al cargar la página para establecer el estado inicial correcto
});
</script>


<?php include("src/templates/adminfooter.php"); ?>
