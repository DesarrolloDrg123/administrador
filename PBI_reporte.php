<?php
require("config/db.php");
include("src/templates/adminheader.php");

$user_id = $_SESSION['usuario_id'] ?? 0;

if ($user_id === 0) {
    header("Location: index.php");
    exit();
}

// 1. OBTENER TODOS LOS REPORTES PERMITIDOS PARA EL USUARIO
$stmt = $conn->prepare("
    SELECT r.id, r.report_name, r.report_link, r.parent_id
    FROM powerbi_reports r
    JOIN powerbi_permissions p ON r.id = p.report_id
    WHERE r.is_active = 1 AND p.user_id = ?
    ORDER BY r.report_name ASC
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result_reports = $stmt->get_result();

// 2. CONSTRUIR UN ÁRBOL JERÁRQUICO CON LOS REPORTES
$reports = [];
while ($row = $result_reports->fetch_assoc()) {
    $reports[$row['id']] = $row;
    $reports[$row['id']]['children'] = [];
}
$tree = [];
foreach ($reports as $id => &$report) {
    if ($report['parent_id'] && isset($reports[$report['parent_id']])) {
        $reports[$report['parent_id']]['children'][] =& $report;
    } else {
        $tree[] =& $report;
    }
}
unset($report); // Romper la referencia

function render_menu_sidebar($items, $is_submenu = false) {
    $html = '<div class="' . ($is_submenu ? 'ps-3' : 'accordion') . '" id="reportAccordion">';

    foreach ($items as $item) {
        $has_children = !empty($item['children']);
        $is_link_only = !$has_children && !empty($item['report_link']);
        $report_id_a_mostrar = $_GET['report_id'] ?? 0;
        
        // Si es un enlace simple (no tiene hijos)
        if ($is_link_only) {
            $active_class = ($item['id'] == $report_id_a_mostrar) ? 'active' : '';
            $html .= '<a class="nav-link ' . $active_class . '" href="?report_id=' . $item['id'] . '">' . htmlspecialchars($item['report_name']) . '</a>';
        } 
        // Si tiene hijos, se crea un acordeón
        elseif ($has_children) {
            $html .= '<div class="accordion-item">';
            $html .= '<h2 class="accordion-header">';
            $html .= '<button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse-' . $item['id'] . '">';
            $html .= htmlspecialchars($item['report_name']);
            $html .= '</button>';
            $html .= '</h2>';
            $html .= '<div id="collapse-' . $item['id'] . '" class="accordion-collapse collapse" data-bs-parent="#reportAccordion">';
            $html .= '<div class="accordion-body p-0">';
            $html .= render_menu_sidebar($item['children'], true); // Llamada recursiva para los hijos
            $html .= '</div></div></div>';
        }
    }
    $html .= '</div>';
    return $html;
}

// 4. OBTENER EL REPORTE SELECCIONADO PARA MOSTRAR
$reporte_seleccionado = null;
$report_id_a_mostrar = isset($_GET['report_id']) ? intval($_GET['report_id']) : 0;
if ($report_id_a_mostrar > 0) {
    $stmt_check = $conn->prepare("SELECT r.report_link FROM powerbi_reports r JOIN powerbi_permissions p ON r.id = p.report_id WHERE r.id = ? AND p.user_id = ?");
    $stmt_check->bind_param("ii", $report_id_a_mostrar, $user_id);
    $stmt_check->execute();
    $reporte_seleccionado = $stmt_check->get_result()->fetch_assoc();
}
?>

<style>
    .report-viewer-container { display: flex; height: calc(100vh - 120px); }
    .report-sidebar { width: 300px; background-color: #f8f9fa; padding: 20px; border-right: 1px solid #dee2e6; overflow-y: auto; }
    .report-sidebar h4 { border-bottom: 2px solid #0d6efd; padding-bottom: 10px; margin-bottom: 15px; font-size: 1.1rem; }
    
    /* Estilos para el acordeón en la barra lateral */
    .report-sidebar .accordion-item { border: none; }
    .report-sidebar .accordion-button {
        background-color: transparent;
        color: #212529;
        font-weight: bold;
        padding: 0.75rem 1rem;
        border-radius: .25rem !important;
    }
    .report-sidebar .accordion-button:not(.collapsed) {
        background-color: #e9ecef;
        box-shadow: none;
    }
    .report-sidebar .accordion-button:focus { box-shadow: none; }
    .report-sidebar .accordion-button::after { /* Oculta la flecha por defecto si prefieres un look más limpio */
        display: none;
    }
    .report-sidebar .accordion-body { padding-left: 1rem !important; }
    .report-sidebar .nav-link { color: #212529; padding: .5rem 1rem; border-radius: .25rem; }
    .report-sidebar .nav-link:hover { background-color: #e9ecef; }
    .report-sidebar .nav-link.active { background-color: #0d6efd; color: white; font-weight: bold; }
    
    .report-content { flex-grow: 1; padding: 20px; display: flex; }
    .report-content iframe { width: 100%; height: 100%; border: none; border-radius: .5rem; box-shadow: 0 4px 8px rgba(0,0,0,.1); }
    .report-placeholder { display: flex; align-items: center; justify-content: center; height: 100%; text-align: center; background-color: #e9ecef; border-radius: .5rem; }
</style>

<div class="report-viewer-container">
    <div class="report-sidebar">
        <h4>Reportes Disponibles</h4>
        <?php echo render_menu_sidebar($tree); ?>
    </div>
    <div class="report-content">
        <?php if ($reporte_seleccionado && !empty($reporte_seleccionado['report_link'])): ?>
            <iframe title="Reporte Power BI" src="<?= htmlspecialchars($reporte_seleccionado['report_link']) ?>" allowFullScreen="true"></iframe>
        <?php else: ?>
            <div class="report-placeholder">
                <div>
                    <h2><i class="fas fa-binoculars fa-2x mb-3"></i></h2>
                    <h2>Bienvenido al Visualizador de Reportes</h2>
                    <p class="lead text-muted">Por favor, seleccione un reporte del menú para comenzar.</p>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php 
$conn->close();
include("src/templates/adminfooter.php"); 
?>