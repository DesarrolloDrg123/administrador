<?php
include("src/templates/adminheader.php");
require("config/db.php");

if (!isset($_SESSION['loggedin']) || !$_SESSION['loggedin']) {
    header("Location: index.php");
    exit();
}

if (!isset($_GET['id'])) {
    echo "Id no proporcionado.";
    exit();
}

$solicitud_id = $_GET['id'];

try {
    $sql = 'SELECT * FROM transferencias_clara_tcl WHERE id = ?';

    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        throw new Exception($conn->error);
    }

    $stmt->bind_param('i', $solicitud_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $solicitud = $result->fetch_assoc();

    if (!$solicitud) {
        echo "No se encontró la solicitud.";
        exit();
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
    exit();
}

$MTParam = isset($_GET['MT']) ? $_GET['MT'] : null;
$TTParam = isset($_GET['TT']) ? $_GET['TT'] : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $sucursal = $_POST['sucursales'];
    $beneficiario = $_POST['beneficiario'];
    $fecha_solicitud = $_POST['date'];
    $no_cuenta = $_POST['noCuenta'];
    $fecha_vencimiento = $_POST['endDate'];
    $importe = $_POST['importe'];
    $importe_letra = $_POST['importe-letra'];
    $importe_dolares = $_POST['importedls'];
    $importe_dolares_letra = $_POST['importedls_letra'];
    $departamento = $_POST['departamento'];
    $categoria = $_POST['categoria'];
    $descripcion = $_POST['descripcion'];
    $observaciones = $_POST['observaciones'];
    $autorizacion = $_POST['autorizacion'];
    $documento_adjunto = $_FILES['documento_adjunto'];

    try {
        $sql = 'UPDATE transferencias_clara_tcl 
                SET sucursal_id = ?, beneficiario_id = ?, fecha_solicitud = ?, no_cuenta = ?, fecha_vencimiento = ?, 
                    importe = ?, importe_letra = ?, importedls = ?, importedls_letra = ?, departamento_id = ?, 
                    categoria_id = ?, descripcion = ?, observaciones = ?, autorizacion_id = ? 
                WHERE id = ?';

        $stmt = $conn->prepare($sql);
        if ($stmt === false) {
            throw new Exception($conn->error);
        }

        $stmt->bind_param('iisisdsdsiissii', $sucursal, $beneficiario, $fecha_solicitud, $no_cuenta, $fecha_vencimiento, 
                          $importe, $importe_letra, $importe_dolares, $importe_dolares_letra, $departamento, $categoria, 
                          $descripcion, $observaciones, $autorizacion, $solicitud_id);

        $stmt->execute();

        if (isset($_FILES['documento_adjunto']) && $_FILES['documento_adjunto']['error'] == UPLOAD_ERR_OK) {
            $upload_dir = 'uploads/';
            if (!is_dir($upload_dir) && !mkdir($upload_dir, 0777, true)) {
                throw new Exception('No se pudo crear el directorio de subidas.');
            }
            $documento_adjunto = $upload_dir . basename($_FILES['documento_adjunto']['name']);
            if (!move_uploaded_file($_FILES['documento_adjunto']['tmp_name'], $documento_adjunto)) {
                throw new Exception('Error al subir el archivo.');
            }
        }

        echo "<div class='alert alert-success'>Transferencia actualizada correctamente.</div>";
        echo '<meta http-equiv="refresh" content="5">';
    } catch (Exception $e) {
        echo "<div class='alert alert-danger'>Error: " . $e->getMessage() . "</div>";
    }
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
<div class="container mt-4">
    <div class="card shadow-lg border-0">
        <div class="card-body p-4">
            <form method="POST" enctype="multipart/form-data" id="editTransferenciaForm">
                <h2 class="text-center mb-4">Editar Transferencia Electronica</h2>

                <div class="row mb-4 align-items-center">
                    <div class="col-md-6 text-center">
                        <img src="../img/logo-drg.png" alt="Logo DRG" class="img-fluid" style="max-height: 80px;">
                    </div>
                    <div class="col-md-6 text-center">
                        <h5>Folio: <span class="text-danger fw-bold"><?php echo htmlspecialchars($solicitud['folio']); ?></span></h5>
                    </div>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label for="sucursal" class="form-label">Sucursal*</label>
                        <select class="form-select" name="sucursales" id="sucursal" required>
                            <?php
                            try {
                                $stmt = $conn->prepare("SELECT id, sucursal FROM sucursales");
                                $stmt->execute();
                                $result = $stmt->get_result();
    
                                while ($row = $result->fetch_assoc()) {
                                    echo '<option value="' . htmlspecialchars($row['id']) . '" ' . ($row['id'] == $solicitud['sucursal_id'] ? 'selected' : '') . '>' . htmlspecialchars($row['sucursal']) . '</option>';
                                }
                            } catch (Exception $e) {
                                echo "Error: " . $e->getMessage();
                            }
                            ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label for="departamento" class="form-label">Departamento*</label>
                        <select class="form-select" name="departamento" id="departamento" required>
                            <?php
                            try {
                                $stmt = $conn->prepare("SELECT id, departamento FROM departamentos");
                                $stmt->execute();
                                $result = $stmt->get_result();
        
                                while ($row = $result->fetch_assoc()) {
                                    echo '<option value="' . htmlspecialchars($row['id']) . '" ' . ($row['id'] == $solicitud['departamento_id'] ? 'selected' : '') . '>' . htmlspecialchars($row['departamento']) . '</option>';
                                }
                            } catch (Exception $e) {
                                echo "Error: " . $e->getMessage();
                            } 
                            ?>
                        </select>
                    </div>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label for="beneficiario" class="form-label">Beneficiario*</label>
                        <select class="form-select" name="beneficiario" id="beneficiario" required>
                             <?php
                            try {
                                $stmt = $conn->prepare("SELECT id, nombre FROM usuarios WHERE estatus = '1' ORDER BY nombre ASC");
                                $stmt->execute();
                                $result = $stmt->get_result();
        
                                while ($row = $result->fetch_assoc()) {
                                    echo '<option value="' . htmlspecialchars($row['id']) . '" ' . ($row['id'] == $solicitud['beneficiario_id'] ? 'selected' : '') . '>' . htmlspecialchars($row['nombre']) . '</option>';
                                }
                            } catch (Exception $e) {
                                echo "Error: " . $e->getMessage();
                            }
                            ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label for="fechaSolicitud" class="form-label">Fecha de Solicitud*</label>
                        <input type="date" name="date" id="fechaSolicitud" class="form-control" value="<?php echo htmlspecialchars($solicitud['fecha_solicitud']); ?>" required>
                    </div>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label for="noCuenta" class="form-label">No. de Cuenta</label>
                        <input type="text" name="noCuenta" id="noCuenta" class="form-control" value="<?php echo htmlspecialchars($solicitud['no_cuenta']); ?>">
                    </div>
                    <div class="col-md-6">
                        <label for="endDate" class="form-label">Fecha de Vencimiento*</label>
                        <input type="date" name="endDate" id="endDate" class="form-control" value="<?php echo htmlspecialchars($solicitud['fecha_vencimiento']); ?>" required>
                    </div>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="form-label">Importe en Pesos</label>
                        <div class="input-group">
                            <span class="input-group-text">$</span>
                            <input type="number" name="importe" id="importe" class="form-control" value="<?php echo htmlspecialchars($solicitud['importe']); ?>" step=".01">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label for="importe-letra" class="form-label">Importe con Letra</label>
                        <input type="text" name="importe-letra" id="importe-letra" class="form-control" value="<?php echo htmlspecialchars($solicitud['importe_letra']); ?>">
                    </div>
                </div>
                
                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label for="categoria" class="form-label">Categoria*</label>
                        <select class="form-select" name="categoria" id="categoria" required>
                             <?php
                            try {
                                $stmt = $conn->prepare("SELECT id, categoria FROM categorias");
                                $stmt->execute();
                                $result = $stmt->get_result();
        
                                while ($row = $result->fetch_assoc()) {
                                    echo '<option value="' . htmlspecialchars($row['id']) . '" ' . ($row['id'] == $solicitud['categoria_id'] ? 'selected' : '') . '>' . htmlspecialchars($row['categoria']) . '</option>';
                                }
                            } catch (Exception $e) {
                                echo "Error: " . $e->getMessage();
                            }
                            ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label for="autorizar" class="form-label">Usuario que Autoriza*</label>
                        <select name="autorizacion" id="autorizar" class="form-select" required>
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
                                    echo '<option value="' . htmlspecialchars($row['id']) . '" ' . ($row['id'] == $solicitud['autorizacion_id'] ? 'selected' : '') . '>' . htmlspecialchars($row['nombre']) . '</option>';
                                }
                            } catch (Exception $e) {
                                echo "Error: " . $e->getMessage();
                            }
                            ?>
                        </select>
                    </div>
                </div>

                <div class="mb-3">
                     <label for="descripcion" class="form-label">Descripcion*</label>
                     <textarea class="form-control" name="descripcion" id="descripcion" rows="3" required><?php echo htmlspecialchars($solicitud['descripcion']); ?></textarea>
                </div>
                 <div class="mb-3">
                    <label for="observaciones" class="form-label">Observaciones Especiales</label>
                    <textarea class="form-control" name="observaciones" id="observaciones" rows="2"><?php echo htmlspecialchars($solicitud['observaciones']); ?></textarea>
                </div>
                 <div class="mb-3">
                    <label for="documento_adjunto" class="form-label">Adjuntar Nuevo Documento (Opcional)</label>
                    <input type="file" name="documento_adjunto" id="documento_adjunto" class="form-control">
                </div>
                
                <div class="text-center mt-4">
                    <input type="hidden" name="id" value="<?php echo htmlspecialchars($solicitud['id']); ?>">
                    <button type="submit" class="btn btn-success px-4 me-2">
                        <i class="fas fa-save"></i> Guardar Cambios
                    </button>
                    <a href="javascript:history.back()" class="btn btn-secondary">Cancelar</a>
                </div>

            </form>
        </div>
    </div>
</div>


<?php include("src/templates/adminfooter.php"); ?>
