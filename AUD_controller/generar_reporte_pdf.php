<?php
// AUD_controller/generar_reporte_pdf.php
require_once '../vendor/autoload.php'; // Requiere Dompdf
use Dompdf\Dompdf;

function crearReportePDF($id_auditoria, $conn) {
    // 1. Obtener datos completos (Cabecera, Detalles, Fotos y Mantenimientos)
    $query = "SELECT a.*, v.*, s.sucursal as sucursal_nombre, u.nombre as responsable_nombre, 
              u.email as correo_responsable, g.nombre as gerente_nombre, auditor.nombre as auditor_nombre
              FROM auditorias_vehiculos_aud a
              JOIN vehiculos_aud v ON a.vehiculo_id = v.id
              JOIN sucursales s ON v.sucursal_id = s.id
              JOIN usuarios u ON v.responsable_id = u.id
              JOIN usuarios g ON v.gerente_reportar_id = g.id
              JOIN usuarios auditor ON a.usuario_id = auditor.id
              WHERE a.id = ?";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $id_auditoria);
    $stmt->execute();
    $data = $stmt->get_result()->fetch_assoc();

    // 2. Construir el HTML con el formato de la bitácora 
    $html = '
    <html>
    <head>
        <style>
            body { font-family: sans-serif; font-size: 12px; }
            .header-table { width: 100%; border-collapse: collapse; background-color: #80bf1f; color: white; }
            .section-title { background-color: #80bf1f; color: white; padding: 5px; font-weight: bold; text-transform: uppercase; margin-top: 10px; }
            table { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
            th, td { border: 1px solid #ccc; padding: 6px; text-align: left; }
            .foto-container { width: 30%; display: inline-block; margin: 5px; text-align: center; }
            .foto-img { width: 150px; height: 100px; object-fit: cover; }
        </style>
    </head>
    <body>
        <table class="header-table">
            <tr>
                <td style="border:none;"><strong>BITÁCORA DE CONTROL VEHICULAR</strong></td>
                <td style="text-align:right; border:none;">Folio: '.$data['folio'].'</td>
            </tr>
        </table>

        <div class="section-title">Información General de la Unidad</div>
        <table>
            <tr>
                <td><strong>Marca/Modelo:</strong> '.$data['marca'].' '.$data['modelo'].'</td>
                <td><strong>No. Serie:</strong> '.$data['no_serie'].'</td>
            </tr>
        </table>

        <div class="section-title">Datos Generales</div>
        <table>
            <tr><td><strong>Sucursal:</strong> '.$data['sucursal_nombre'].'</td><td><strong>Gerente:</strong> '.$data['gerente_nombre'].'</td></tr>
            <tr><td><strong>Responsable:</strong> '.$data['responsable_nombre'].'</td><td><strong>Auditor:</strong> '.$data['auditor_nombre'].'</td></tr>
            <tr><td><strong>Placas:</strong> '.$data['placas'].'</td><td><strong>Vig. Póliza:</strong> '.$data['vigencia_poliza'].'</td></tr>
        </table>

        <div class="section-title">Resultados de Auditoría (Puntos)</div>
        <table>
            <thead><tr><th>Elemento</th><th>Estado</th><th>Puntos</th></tr></thead>
            <tbody>';
            
    // Consulta de puntos/preguntas
    $res = $conn->query("SELECT * FROM auditorias_detalle_aud WHERE auditoria_id = $id_auditoria");
    while($row = $res->fetch_assoc()) {
        $html .= "<tr><td>{$row['pregunta']}</td><td>{$row['valor_seleccionado']}</td><td>{$row['puntos_obtenidos']}</td></tr>";
    }

    $html .= '</tbody></table>
        <div class="section-title">Fotografías y Evidencias</div>
        <div style="width:100%;">';
    
    // Agregar fotos [cite: 4, 6-14]
    $fotos = $conn->query("SELECT * FROM auditorias_evidencias_aud WHERE auditoria_id = $id_auditoria");
    while($f = $fotos->fetch_assoc()) {
        $ruta = '../' . $f['ruta_archivo'];
        if(file_exists($ruta) && !str_ends_with($ruta, '.pdf')) {
            $html .= '<div class="foto-container"><img src="'.$ruta.'" class="foto-img"><br><small>Evidencia</small></div>';
        }
    }

    $html .= '</div></body></html>';

    // 3. Generar el PDF
    $dompdf = new Dompdf(['isRemoteEnabled' => true]);
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();
    
    // Guardar en servidor para futuras consultas
    $output = $dompdf->output();
    $nombreArchivo = "reportes/Auditoria_{$id_auditoria}.pdf";
    file_put_contents("../".$nombreArchivo, $output);
    
    return [
        'ruta' => "../".$nombreArchivo,
        'correo_responsable' => $data['correo_responsable'],
        'correo_auditor' => 'vgonzalez@drg.mx', // 
        'folio' => $data['folio']
    ];
}
?>