<?php
session_start();
if (!isset($_SESSION['loggedin']) || !$_SESSION['loggedin']) {
    header("Location: index.php");
    exit();
}

$usuario_id = $_SESSION['usuario_id'];
include("src/templates/header.php");
require("config/db.php");
require 'vendor/autoload.php'; // Asegúrate de que PHPMailer esté instalado y autoloaded

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$autorizacion_id = $usuario_id;
$alertMessage = '';
$alertType = '';
$folio_formateado = '';

// Obtener el próximo folio disponible
try {
    $conn->begin_transaction();
    // Bloquear la fila con el folio actual para evitar condiciones de carrera
    $stmt = $conn->prepare("SELECT ultimo_folio FROM folio WHERE id = 1 FOR UPDATE");
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $ultimo_folio = $row['ultimo_folio'];
    
    $nuevo_folio = $ultimo_folio + 1;
    $folio_formateado = sprintf("%09d", $nuevo_folio);

    // Guardar el folio en la variable de sesión para mostrarlo en el HTML
    $_SESSION['folio_formateado'] = $folio_formateado;

    $conn->commit();
} catch (Exception $e) {
    $conn->rollback();
    die("Error al generar el folio: " . $e->getMessage());
}

// Variables de sesión
$nombreSolicitante = $_SESSION['nombre'];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Verificación de variables POST
    $requiredFields = ['sucursales', 'beneficiario', 'date', 'noCuenta', 'endDate', 'departamento', 'categoria', 'descripcion', 'autorizacion'];
    foreach ($requiredFields as $field) {
        if (!isset($_POST[$field])) {
            die('Falta el campo obligatorio: ' . $field);
        }
    }

    $conn->begin_transaction();
    try {
        // Actualizar el folio en la tabla folio
        $stmt = $conn->prepare("UPDATE folio SET ultimo_folio = ? WHERE id = 1");
        $stmt->bind_param("i", $nuevo_folio);
        $stmt->execute();
        
        // Datos del formulario
        $sucursal_id = $_POST['sucursales'];
        $beneficiario_id = $_POST['beneficiario'];
        $fecha_solicitud = $_POST['date'];
        $no_cuenta = $_POST['noCuenta'];
        $fecha_vencimiento = $_POST['endDate'];
        $importe = $_POST['importe'] ?? null;
        $importe_letra = $_POST['importe-letra'] ?? null;
        $importedls = $_POST['importedls'] ?? null;
        $importedls_letra = $_POST['importedls_letra'] ?? null;
        $departamento_id = $_POST['departamento'];
        $categoria_id = $_POST['categoria'];
        $descripcion = $_POST['descripcion'];
        $observaciones = $_POST['observaciones'] ?? '';
        $autorizacion_id = $_POST['autorizacion'];

        // Manejo de subida de archivo
        $documento_adjunto = null;
        if (isset($_FILES['documento_adjunto']) && $_FILES['documento_adjunto']['error'] == UPLOAD_ERR_OK) {
            $upload_dir = 'uploads/'; // Directorio donde se guardarán los archivos
            $documento_adjunto = $upload_dir . basename($_FILES['documento_adjunto']['name']);
            if (!move_uploaded_file($_FILES['documento_adjunto']['tmp_name'], $documento_adjunto)) {
                throw new Exception('Error al subir el archivo.');
            }
        }

        $fields = ["folio", "sucursal_id", "beneficiario_id", "fecha_solicitud", "no_cuenta", "fecha_vencimiento", "departamento_id", "categoria_id", "descripcion", "observaciones", "autorizacion_id", "usuario_id"];
        $values = [$folio_formateado, $sucursal_id, $beneficiario_id, $fecha_solicitud, $no_cuenta, $fecha_vencimiento, $departamento_id, $categoria_id, $descripcion, $observaciones, $autorizacion_id, $usuario_id];

        if (!empty($importe)) {
            $fields[] = "importe";
            $values[] = $importe;
        }
        if (!empty($importe_letra)) {
            $fields[] = "importe_letra";
            $values[] = $importe_letra;
        }
        if (!empty($importedls)) {
            $fields[] = "importedls";
            $values[] = $importedls;
        }
        if (!empty($importedls_letra)) {
            $fields[] = "importedls_letra";
            $values[] = $importedls_letra;
        }
        if ($documento_adjunto) {
            $fields[] = "documento_adjunto";
            $values[] = $documento_adjunto;
        }

        // Guardar en la base de datos
        $sql = "INSERT INTO transferencias (" . implode(", ", $fields) . ") VALUES (" . implode(", ", array_fill(0, count($values), "?")) . ")";
        $stmt = $conn->prepare($sql);
        if ($stmt === false) {
            throw new Exception('Error al preparar la consulta: ' . $conn->error);
        }

        // Asignar todos los valores como cadenas
        $types = str_repeat('s', count($values));
        $stmt->bind_param($types, ...$values);
        $stmt->execute();

        // Obtener el último ID insertado
        $last_id = $conn->insert_id;

        $stmt = $conn->prepare("
            SELECT 
                s.sucursal, 
                b.beneficiario, 
                d.departamento, 
                c.categoria, 
                u.nombre AS usuario_autoriza,
                u.email AS email_autoriza
            FROM transferencias t
            JOIN sucursales s ON t.sucursal_id = s.id
            JOIN beneficiarios b ON t.beneficiario_id = b.id
            JOIN departamentos d ON t.departamento_id = d.id
            JOIN categorias c ON t.categoria_id = c.id
            JOIN usuarios u ON t.autorizacion_id = u.id
            WHERE t.id = ?
        ");
        if ($stmt === false) {
            throw new Exception('Error al preparar la consulta: ' . $conn->error);
        }
        $stmt->bind_param('i', $last_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();

        // Configurar PHPMailer para enviar el correo
        $mail = new PHPMailer(true);

        try {
            // Configuración SMTP de HostGator
            $mail->isSMTP();
            $mail->Host = 'mail.intranetdrg.com.mx'; // Reemplaza con tu dominio
            $mail->SMTPAuth = true;
            $mail->Username = 'administrador@intranetdrg.com.mx'; // Reemplaza con tu correo
            $mail->Password = 'WbrE5%7p'; // Reemplaza con tu contraseña
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; // Usa ENCRYPTION_STARTTLS si el puerto es 587
            $mail->Port = 465; // Usa 587 si usas TLS

            $mail->setFrom('administrador@intranetdrg.com.mx', 'Transferencias Electronicas');
            $mail->addAddress('ebetancourt@drg.mx');
            $mail->addAddress($row['email_autoriza']);

            $fechaSolicitud = new DateTime($fecha_solicitud);
            $fmt = new IntlDateFormatter('es_ES', IntlDateFormatter::LONG, IntlDateFormatter::NONE);
            $fmt->setPattern('d MMMM yyyy'); // Cambia el patrón según sea necesario
            $fechaSolicitudFormateada = $fmt->format($fechaSolicitud);

            $fechaVencimiento = new DateTime($fecha_vencimiento);
            $fechaVencimientoFormateada = $fmt->format($fechaVencimiento);

            $mail->isHTML(true);
            $mail->Subject = 'Solicitud de Transferencia Electronica';
            $mail->Body = "
            <html>
            <head>
                <style>
                    body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                    h1 { color: #2c3e50; border-bottom: 2px solid #3498db; padding-bottom: 10px; }
                    h2 { color: #2980b9; }
                    strong { color: #2c3e50; }
                    .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                    .info-row { margin-bottom: 10px; }
                    .label { font-weight: bold; color: #34495e; }
                    .value { margin-left: 10px; }
                    .logo { position: absolute; top: 20px; right: 100px; max-width: 300px; height: 250; width: 250px; }

                </style>
            </head>
            <body>
                <div class='container'>
                    <img src='https://i.ibb.co/drvS4yF/logo-drg.png' alt='Logo' class='logo'>
                    <h1>Nueva Solicitud de transferencia electrónica.</h1>
                    <h2>Solicitante: <strong>{$nombreSolicitante}</strong></h2>
                    
                    <div class='info-row'>
                        <span class='label'>Sucursal:</span>
                        <span class='value'>{$row['sucursal']}</span>
                    </div>
                    
                    <div class='info-row'>
                        <span class='label'>Beneficiario:</span>
                        <span class='value'>{$row['beneficiario']}</span>
                    </div>
                    
                    <div class='info-row'>
                        <span class='label'>Fecha de Solicitud:</span>
                        <span class='value'>$fechaSolicitudFormateada</span>
                    </div>
                    
                    <div class='info-row'>
                        <span class='label'>No. de Cuenta:</span>
                        <span class='value'>$no_cuenta</span>
                    </div>
                    
                    
                    <div class='info-row'>
                        <span class='label'>Importe Pesos:</span>
                        <span class='value'>$ $importe</span>
                    </div>
                    
                    <div class='info-row'>
                        <span class='label'>Importe con Letra Pesos:</span>
                        <span class='value'>$importe_letra</span>
                    </div>

                    <div class='info-row'>
                        <span class='label'>Importe en Dolares:</span>
                        <span class='value'>$$importedls</span>
                    </div>
                    
                    <div class='info-row'>
                        <span class='label'>Importe con Letra Dolares:</span>
                        <span class='value'>$importedls_letra</span>
                    </div>
                    
                    <div class='info-row'>
                        <span class='label'>Departamento:</span>
                        <span class='value'>{$row['departamento']}</span>
                    </div>
                    
                    <div class='info-row'>
                        <span class='label'>Categoria:</span>
                        <span class='value'>{$row['categoria']}</span>
                    </div>
                    
                    <div class='info-row'>
                        <span class='label'>Descripción:</span>
                        <span class='value'>$descripcion</span>
                    </div>
                    
                    <div class='info-row'>
                        <span class='label'>Observaciones:</span>
                        <span class='value'>$observaciones</span>
                    </div>
                    
                    <div class='info-row'>
                        <span class='label'>Autoriza:</span>
                        <span class='value'>{$row['usuario_autoriza']}</span>
                    </div>
                    <h1>Confirmar Transferencia en el Portal.</h1>

                </div>
            </body>
            </html>
            ";
            $mail->send();
            $alertMessage = 'Mensaje enviado con éxito.';
            $alertType = 'success'; // Color verde para éxito
        } catch (Exception $e) {
            $alertMessage = "No se pudo enviar el correo. Error: {$mail->ErrorInfo}";
            $alertType = 'danger'; // Color rojo para error
        }
        $conn->commit();
    } catch (Exception $e) {
        $conn->rollback();
        $alertMessage = "Error al procesar la solicitud: " . $e->getMessage();
        $alertType = 'danger';
    }
}
?>

<br>
<div id="alert-container"></div>

<container class="d-flex justify-content-center">
    <form method="POST" action="#" enctype="multipart/form-data">
        <h1 class="text-center mb-3">Solicitud de Transferencia Electronica</h1>
        <?php if ($alertMessage): ?>
            <div class="alert alert-<?= $alertType ?> alert-dismissible fade show" role="alert">
                <strong><?= htmlspecialchars($alertMessage) ?></strong>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="row">
            <div class="col">
                <img src="img/logo-drg.png" alt="col" class="align-center">
            </div>
            <div class="col">
                <h3>Folio: <span style="color:red;"><?php echo isset($_SESSION['folio_formateado']) ? htmlspecialchars($_SESSION['folio_formateado']) : ''; ?></span></h3>
            </div>
        </div>
        <br>

        <div class="row mb-1">
            <div class="col">
                <h5>
                    <label for="sucursal">Sucursal</label>
                    <select class="form-control" name="sucursales" id="sucursal">
                        <?php
                        try {
                            $stmt = $conn->prepare("SELECT id, sucursal FROM sucursales");
                            $stmt->execute();
                            $result = $stmt->get_result();

                            while ($row = $result->fetch_assoc()) {
                                echo '<option value="' . htmlspecialchars($row['id']) . '">' . htmlspecialchars($row['sucursal']) . '</option>';
                            }
                        } catch (Exception $e) {
                            echo "Error: " . $e->getMessage();
                        }
                        ?>
                    </select>
                </h5>
            </div>
        </div>

        <div class="row mb-1">
            <div class="col">
                <label for="beneficiario">Beneficiario</label>
                <select class="form-label" name="beneficiario" id="beneficiario" width="30px">
                    <?php
                    try {
                        $stmt = $conn->prepare("SELECT id, beneficiario FROM beneficiarios");
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

            <div class="col">
                <label class="form-label">Fecha de Solicitud</label>
                <input type="date" name="date" class="form-control" placeholder="" aria-label="" required>
            </div>
        </div>

        <div class="row mb-1">
            <div class="col">
                <label class="form-label">No. de Cuenta</label>
                <input id="noCuenta" type="text" name="noCuenta" class="form-control" placeholder="No. de Cuenta" aria-label="No. de Cuenta" required oninput="this.value = this.value.toUpperCase()">
            </div>
            <div class="col">
                <label class="form-label">Fecha de Vencimiento</label>
                <input type="date" name="endDate" id="endDate" class="form-control" placeholder="Fecha De Vencimiento" aria-label="" required>
            </div>
        </div>

        <div class="row mb-1">
            <div class="col">
                <label class="form-label">Importe en pesos: </label>
                <div class="input-group">
                    <span class="input-group-text">$</span>
                    <input type="number" step="0.01" name="importe" id="importe" class="form-control" placeholder="0.00" aria-label="Importe" >
                </div>
            </div>

            <div class="col">
                <label class="form-label">Importe Con Letra</label>
                <input type="text" name="importe-letra" id="importe-letra" class="form-control" placeholder="Importe Con Letra" aria-label="Importe Con Letra">
            </div>
        </div>

        <div class="row mb-1">
            <div class="col">
                <label class="form-label">Importe en Dolares: </label>
                <div class="input-group">
                    <span class="input-group-text">$</span>
                    <input type="number" step="0.01" name="importedls" id="importedls" class="form-control" placeholder="0.00" aria-label="ImporteDolares" >
                </div>
            </div>

            <div class="col">
                <label class="form-label">Importe Con Letra</label>
                <input type="text" name="importedls_letra" id="importedls_letra" class="form-control" placeholder="Importe Con Letra" aria-label="Importe Con Letra"  >
            </div>
        </div>

        <div class="row mb-1">
            <div class="col">
                <br>
                <label for="departamento" class="form-label">Departamento</label>
                <select class="form-label" name="departamento" id="departamento" class="form-control">
                    <?php
                    try {
                        $stmt = $conn->prepare("SELECT id, departamento FROM departamentos");
                        $stmt->execute();
                        $result = $stmt->get_result();

                        while ($row = $result->fetch_assoc()) {
                            echo '<option value="' . htmlspecialchars($row['id']) . '">' . htmlspecialchars($row['departamento']) . '</option>';
                        }
                    } catch (Exception $e) {
                        echo "Error: " . $e->getMessage();
                    }
                    ?>
                </select>
            </div>

            <div class="col">
                <br>
                <label for="categoria" class="form-label">Categoria</label>
                <select class="form-label" name="categoria" id="categoria" class="form-control">
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

        <div class="row mb-1">
            <div class="col">
                <label class="form-label">Descripcion</label>
                <textarea type="text" class="form-control" name="descripcion" id="descripcion" placeholder="Descripcion"></textarea>
            </div>
        </div>

        <div class="row mb-1">
            <div class="col">
                <label for="" class="form-label">Observaciones especiales</label>
                <textarea type="text" class="form-control" name="observaciones" id="observaciones" placeholder="Observaciones"></textarea>
            </div>

            <div class="col">
                <label for="usuarios" class="form-label autorizacion">Usuario que Autoriza</label>
                <select name="autorizacion" id="autorizar">
                    <?php
                    try {
                        $rol = 'autorizador';
                        $stmt = $conn->prepare("SELECT id, nombre, rol FROM usuarios WHERE rol = ? ");
                        $stmt->bind_param('s', $rol);
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

        <div class="row mb-1">
            <div class="col">
                <label for="documento_adjunto" class="form-label">Documento Adjunto</label>
                <input type="file" class="form-control" name="documento_adjunto" id="documento_adjunto">
            </div>
        </div>

        <button type="submit" class="btn btn-success mt-2">Enviar</button>
    </form>
    <br>
</container>
<br>
<script>
function showAlert(message, type) {
    var alertContainer = document.getElementById('alert-container');
    var alertType = type === 'success' ? 'alert-success' : 'alert-danger';

    alertContainer.innerHTML = `<div class="alert ${alertType} alert-dismissible fade show" role="alert">
                                    <strong>${message}</strong>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>`;
}
</script>

<?php include("src/templates/footer.php"); ?>
