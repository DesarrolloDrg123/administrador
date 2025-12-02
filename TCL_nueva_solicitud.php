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

$autorizacion_id = $usuario_id;

try {
    $conn->begin_transaction();
    // Nota: Asegúrate que la tabla control_folios_tcl exista y tenga el id 1
    $stmt = $conn->prepare("SELECT folio FROM control_folios_tcl WHERE id = 1 FOR UPDATE");
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $ultimo_folio = $row['folio'];
    } else {
        // Fallback si no existe registro inicial
        $ultimo_folio = 0;
    }
    
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
        display: none;
        width: 100%;
        margin-top: 0.25rem;
        font-size: 0.875em;
        color: #dc3545;
    }
    .validation-feedback.visible {
        display: block;
    }
</style>
<br>
<div class="container mt-4">
    <div class="card shadow-lg border-0">
        <div class="card-body p-4">
            <form method="POST" enctype="multipart/form-data" id="form-transferencia">
                <h2 class="text-center mb-4">Solicitud de Transferencia Clara</h2>

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
                            <option value="" disabled selected>Cargando sucursales...</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label for="departamento" class="form-label">Departamento*</label>
                        <select class="form-select" name="departamento" id="departamento" required>
                            <option value="" disabled selected>Cargando departamentos...</option>
                        </select>
                    </div>
                </div>

                <!-- Beneficiario y Fecha -->
                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label for="beneficiario" class="form-label">Beneficiario*</label>
                        <select class="form-select" name="beneficiario" id="beneficiario" required>
                            <option value="" disabled selected>Cargando beneficiarios...</option>
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

                <!-- Categoría -->
                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label for="categoria" class="form-label">Categoría*</label>
                        <select class="form-select" name="categoria" id="categoria" required>
                            <option value="" disabled selected>Cargando categorías...</option>
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
                            <option value="" disabled selected>Cargando autorizadores...</option>
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
    // --- 1. ELEMENTOS DOM ---
    const miFormulario = document.getElementById('form-transferencia');
    const sucursalSelect = document.getElementById('sucursal');
    const departamentoSelect = document.getElementById('departamento');
    const categoriaSelect = document.getElementById('categoria');
    const autorizacionSelect = document.getElementById('autorizar');
    const autorizacionHidden = document.getElementById('autorizacion_hidden');
    const beneficiarioSelect = document.getElementById('beneficiario');

    // --- 2. FUNCIONES DE CARGA AJAX (Genérica) ---
    
    // Función auxiliar para cargar cualquier select
    function cargarOpciones(url, selectElement, defaultText, mapFunc) {
        fetch(url)
            .then(response => {
                if (!response.ok) throw new Error(`Error red: ${url}`);
                return response.json();
            })
            .then(data => {
                if (data.error) {
                    console.error(`Error servidor (${url}):`, data.error);
                    selectElement.innerHTML = `<option value="" disabled>Error al cargar</option>`;
                    return;
                }
                
                selectElement.innerHTML = `<option value="" disabled selected>${defaultText}</option>`;
                
                if (Array.isArray(data)) {
                    data.forEach(item => {
                        const option = document.createElement('option');
                        // Usamos la función de mapeo para saber qué campos usar (id/nombre, id/sucursal, etc)
                        const { value, text } = mapFunc(item);
                        option.value = value;
                        option.textContent = text;
                        selectElement.appendChild(option);
                    });
                }
                
                // Disparar evento change por si hay lógica dependiente (como en departamentos)
                selectElement.dispatchEvent(new Event('change'));
            })
            .catch(error => {
                console.error(`Error fetch (${url}):`, error);
                selectElement.innerHTML = `<option value="" disabled>Error de conexión</option>`;
            });
    }

    // --- 3. CARGA DE DATOS ESPECÍFICOS ---

    // 3.1 Beneficiarios
    cargarOpciones('TCL_controller/get_beneficiarios.php', beneficiarioSelect, 'Selecciona un Beneficiario', (item) => ({
        value: item.id,
        text: item.nombre
    }));

    // 3.2 Sucursales
    cargarOpciones('TCL_controller/get_sucursales.php', sucursalSelect, 'Selecciona una Sucursal', (item) => ({
        value: item.id,
        text: item.sucursal // Asegúrate que tu JSON devuelve 'sucursal'
    }));

    // 3.3 Departamentos (Recomendado crear este archivo)
    cargarOpciones('TCL_controller/get_departamentos.php', departamentoSelect, 'Selecciona un Departamento', (item) => ({
        value: item.id,
        text: item.departamento // Asegúrate que tu JSON devuelve 'departamento'
    }));

    // 3.4 Categorías (Recomendado crear este archivo)
    cargarOpciones('TCL_controller/get_categorias.php', categoriaSelect, 'Selecciona una Categoría', (item) => ({
        value: item.id,
        text: item.categoria // Asegúrate que tu JSON devuelve 'categoria'
    }));

    // 3.5 Autorizadores
    cargarOpciones('TCL_controller/get_autorizadores.php', autorizacionSelect, 'Selecciona un Autorizador', (item) => ({
        value: item.id,
        text: item.nombre
    }));


    // --- 4. LOGICA DE NEGOCIO (Autorización y Bloqueos) ---
    function updateAutorizacion() {
        if (!departamentoSelect || !autorizacionSelect || !autorizacionHidden) return; 
        
        // Verificamos si hay algo seleccionado
        if (departamentoSelect.selectedIndex === -1) return;

        const selectedDepartamentoText = departamentoSelect.options[departamentoSelect.selectedIndex].text;
        
        if (selectedDepartamentoText === 'VENTAS' || selectedDepartamentoText === 'SERVICIO TECNICO') {
            // Ajustar el ID '2' según sea necesario en tu base de datos
            autorizacionSelect.value = '2'; 
            autorizacionSelect.disabled = true;
            autorizacionHidden.value = '2';
        } else {
            autorizacionSelect.disabled = false;
            autorizacionHidden.value = autorizacionSelect.value;
        }
    }

    // --- 5. LISTENERS ---

    // Cambio en departamento afecta autorización
    departamentoSelect.addEventListener('change', function() {
        updateAutorizacion(); 
    });
    
    // Cambio manual en autorización
    autorizacionSelect.addEventListener('change', function() {
        autorizacionHidden.value = this.value;
    });

    // Envío del formulario
    if (miFormulario) {
        miFormulario.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(miFormulario);
            const boton = miFormulario.querySelector('button[type="submit"]');
            
            if (boton) boton.disabled = true;
            
            Swal.fire({ 
                title: 'Guardando solicitud...', 
                html: 'Por favor espera', 
                allowOutsideClick: false, 
                didOpen: () => Swal.showLoading() 
            });

            fetch("TCL_controller/guardar_transferencia.php", { 
                method: "POST", 
                body: formData 
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({ 
                        icon: 'success', 
                        title: 'Éxito', 
                        text: data.message, 
                        timer: 2500, 
                        showConfirmButton: false 
                    }).then(() => location.reload());
                } else {
                    Swal.fire({ 
                        icon: 'warning', 
                        title: 'Advertencia', 
                        text: data.message 
                    });
                    if (boton) boton.disabled = false;
                }
            })
            .catch(error => {
                console.error("Error en la solicitud:", error);
                Swal.fire({ 
                    icon: 'error', 
                    title: 'Error', 
                    text: 'No se pudo procesar la solicitud.' 
                });
                if (boton) boton.disabled = false;
            });
        });
    }
});
</script>

<?php include("src/templates/adminfooter.php"); ?>