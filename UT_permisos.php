<?php
require("config/db.php");
include("src/templates/adminheader.php");

$userId = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($userId === 0) {
    die("Error: ID de usuario no válido.");
}

// --- Obtener nombre del usuario ---
$stmt_user = $conn->prepare("SELECT nombre FROM usuarios WHERE id = ?");
$stmt_user->bind_param("i", $userId);
$stmt_user->execute();
$usuario = $stmt_user->get_result()->fetch_assoc()['nombre'] ?? 'Usuario no encontrado';
$stmt_user->close();

// --- Obtener TODOS los permisos (Programas Y Reportes PBI) ---

// 1. Obtener Programas del sistema y sus permisos
$stmt_programas = $conn->prepare("
    SELECT p.id, p.descripcion, p.categoria, 
           CASE WHEN per.acceso = 1 THEN 1 ELSE 0 END AS tiene_permiso
    FROM programas p
    LEFT JOIN permisos per ON p.id = per.id_programa AND per.id_usuario = ?
    ORDER BY p.categoria, p.descripcion
");
$stmt_programas->bind_param("i", $userId);
$stmt_programas->execute();
$result_programas = $stmt_programas->get_result();

$categorias = [];
while ($row = $result_programas->fetch_assoc()) {
    $categorias[$row['categoria']][] = $row;
}
$stmt_programas->close();

// 2. Obtener Reportes de Power BI y sus permisos
$stmt_pbi = $conn->prepare("
    SELECT r.id, r.report_name, 
           CASE WHEN p.user_id IS NOT NULL THEN 1 ELSE 0 END AS tiene_permiso
    FROM powerbi_reports r
    LEFT JOIN powerbi_permissions p ON r.id = p.report_id AND p.user_id = ?
    ORDER BY r.report_name
");
$stmt_pbi->bind_param("i", $userId);
$stmt_pbi->execute();
$result_pbi = $stmt_pbi->get_result();
$reportes_pbi = $result_pbi->fetch_all(MYSQLI_ASSOC);
$stmt_pbi->close();

// 3. --- CAMBIO CLAVE: Unificar los permisos de Power BI en la categoría 'Power BI' ---
// Si ya existe una categoría llamada "Power BI" de la tabla de programas, los unimos.
// Si no, creamos una nueva.
if (!isset($categorias['Power BI'])) {
    $categorias['Power BI'] = [];
}

foreach ($reportes_pbi as $reporte) {
    $categorias['Power BI'][] = [
        'id' => $reporte['id'],
        'descripcion' => $reporte['report_name'], // Usamos 'descripcion' para que sea consistente
        'tiene_permiso' => $reporte['tiene_permiso'],
        'es_pbi' => true // Un indicador para el JavaScript
    ];
}
?>

<style>
    /* Estilos mejorados del acordeón */
    .accordion-button { 
        background-color: #343a40; 
        color: #ffffff; 
        font-weight: bold; 
    }
    .accordion-button:not(.collapsed) { 
        background-color: #495057; 
        color: #ffffff; 
    }
    .accordion-button:focus { 
        box-shadow: none; 
    }
    .accordion-body { 
        padding: 1.5rem;
        background-color: #f8f9fa; 
    }

    /* --- Estilo para cada elemento de permiso --- */
    .form-check {
        /* Se mantiene el display flex para alinear el switch y el texto */
        display: flex;
        align-items: center;
    }
    .form-check-label {
        /* ESTA LÍNEA ES LA SOLUCIÓN CLAVE */
        /* Permite que la etiqueta se encoja y que el texto se ajuste en varias líneas */
        min-width: 0; 
    }
</style>

<div class="container mt-4 mb-5" style="max-width: 900px;">
    <div class="card shadow-sm">
        <div class="card-header">
            <h2 class="mb-0">Permisos para: <strong><?= htmlspecialchars($usuario); ?></strong></h2>
        </div>
        <div class="card-body">
            <div class="accordion" id="accordionPermisos">
                <?php foreach ($categorias as $categoria => $permisos): ?>
                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse-<?= strtolower(str_replace(' ', '-', $categoria)); ?>">
                                <?= htmlspecialchars($categoria) ?>
                            </button>
                        </h2>
                        <div id="collapse-<?= strtolower(str_replace(' ', '-', $categoria)); ?>" class="accordion-collapse collapse" data-bs-parent="#accordionPermisos">
                            <div class="accordion-body">
                                <div class="row">
                                    <?php foreach ($permisos as $permiso): ?>
                                        <div class="col-md-4 mb-3">
                                            <div class="form-check form-switch">
                                                <?php
                                                    // Determinar la clase y el data-attribute según el tipo de permiso
                                                    $es_pbi = isset($permiso['es_pbi']) && $permiso['es_pbi'];
                                                    $checkbox_class = $es_pbi ? 'pbi-permiso-checkbox' : 'permiso-checkbox';
                                                    $data_attribute = $es_pbi ? 'data-id-reporte' : 'data-id-programa';
                                                ?>
                                                <input class="form-check-input <?= $checkbox_class ?>" type="checkbox"
                                                       id="permiso_<?= $categoria . '_' . $permiso['id'] ?>"
                                                       <?= $data_attribute ?>="<?= $permiso['id'] ?>"
                                                       <?= $permiso['tiene_permiso'] ? 'checked' : '' ?>>
                                                <label class="form-check-label" for="permiso_<?= $categoria . '_' . $permiso['id'] ?>">
                                                    <?= htmlspecialchars($permiso['descripcion']) ?>
                                                </label>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="card-footer text-end">
            <a href="UT_gestion_usuarios.php" class="btn btn-secondary">Regresar</a>
        </div>
    </div>
</div>

<script>
$(document).ready(function () {
    const userId = <?= $userId; ?>;

    // AJAX para permisos de Programas
    $(".permiso-checkbox").on("change", function () {
        $.ajax({
            url: "UT_controller/modificar_permisos.php",
            type: "POST",
            data: {
                id_usuario: userId,
                id_permiso: $(this).data("id-programa"),
                acceso: $(this).is(":checked") ? 1 : 0,
                tipo: 'programa'
            }
        });
    });

    // AJAX para permisos de Reportes Power BI
    $(".pbi-permiso-checkbox").on("change", function () {
        $.ajax({
            url: "UT_controller/modificar_permisos.php",
            type: "POST",
            data: {
                id_usuario: userId,
                id_permiso: $(this).data("id-reporte"),
                acceso: $(this).is(":checked") ? 1 : 0,
                tipo: 'pbi'
            }
        });
    });
});
</script>

<?php include("src/templates/adminfooter.php"); ?>