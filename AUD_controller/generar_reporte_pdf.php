<?php
require '../vendor/autoload.php';
use Mpdf\Mpdf;

function crearReportePDF($id_auditoria, $conn) {
    // 1. Obtener datos (JOINs con vehiculos, sucursales y usuarios)
    $query = "SELECT a.*, v.*, s.sucursal as sucursal_nombre, u.nombre as responsable_nombre, 
              u.email as correo_responsable, g.nombre as gerente_nombre, auditor.nombre as auditor_nombre
              FROM auditorias_vehiculos_aud a
              JOIN vehiculos v ON a.vehiculo_id = v.id
              JOIN sucursales s ON v.sucursal_id = s.id
              JOIN usuarios u ON v.responsable_id = u.id
              JOIN usuarios g ON v.gerente_reportar_id = g.id
              JOIN usuarios auditor ON a.usuario_id = auditor.id
              WHERE a.id = ?";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $id_auditoria);
    $stmt->execute();
    $data = $stmt->get_result()->fetch_assoc();

    // 2. Definir estilos y HTML
    $html = '
    <style>
        body { font-family: "Arial", sans-serif; font-size: 10pt; color: #333; }
        .header { background-color: #80bf1f; color: white; padding: 15px; width: 100%; }
        .section-title { background-color: #80bf1f; color: white; padding: 5px 10px; font-weight: bold; margin-top: 15px; text-transform: uppercase; }
        table { width: 100%; border-collapse: collapse; margin-top: 5px; }
        th, td { border: 1px solid #80bf1f; padding: 6px; text-align: left; }
        .bg-gray { background-color: #f2f9e9; font-weight: bold; width: 25%; }
        .foto-box { border: 1px solid #ccc; text-align: center; margin: 5px; padding: 5px; float: left; width: 30%; }
        .foto-img { width: 100%; height: auto; }
        .footer-table { margin-top: 40px; border: none; }
        .footer-table td { border: none; text-align: center; width: 50%; }
        .linea-firma { border-top: 1px solid #000; padding-top: 5px; margin: 0 20px; }
    </style>

    <div class="header">
        <table style="width:100%; border:none;">
            <tr>
                <td style="border:none; color:white; font-size:14pt;"><strong>BÍTACORA DE CONTROL VEHICULAR</strong></td>
                <td style="border:none; color:white; text-align:right;">Folio: '.$data['folio'].'</td>
            </tr>
        </table>
    </div>

    <div class="section-title">INFORMACIÓN GENERAL DE LA UNIDAD</div>
    <table>
        <tr>
            <td class="bg-gray">Modelo / Marca</td><td>'.$data['modelo'].' / '.$data['marca'].'</td>
            <td class="bg-gray">No de Serie</td><td>'.$data['no_serie'].'</td>
        </tr>
    </table>

    <div class="section-title">DATOS GENERALES</div>
    <table>
        <tr>
            <td class="bg-gray">Sucursal</td><td>'.$data['sucursal_nombre'].'</td>
            <td class="bg-gray">Gerente de Sucursal</td><td>'.$data['gerente_nombre'].'</td>
        </tr>
        <tr>
            <td class="bg-gray">Responsable</td><td>'.$data['responsable_nombre'].'</td>
            <td class="bg-gray">Auditor Realizó</td><td>'.$data['auditor_nombre'].'</td>
        </tr>
        <tr>
            <td class="bg-gray">No. de Placas</td><td>'.$data['placas'].'</td>
            <td class="bg-gray">T. de Circulación</td><td>'.$data['tarjeta_circulacion'].'</td>
        </tr>
        <tr>
            <td class="bg-gray">Póliza / Vigencia</td><td>'.$data['no_poliza'].' / '.$data['vigencia_poliza'].'</td>
            <td class="bg-gray">Aseguradora / Tel</td><td>'.$data['aseguradora'].' / '.$data['telefono_siniestro'].'</td>
        </tr>
    </table>

    <div class="section-title">RESULTADOS DE LA AUDITORÍA (PUNTOS)</div>
    <table>
        <thead>
            <tr style="background-color: #f2f9e9;">
                <th>Elemento</th><th style="text-align:center;">Estado</th><th style="text-align:center;">Puntos</th>
            </tr>
        </thead>
        <tbody>';

    $resDet = $conn->query("SELECT * FROM auditorias_detalle_aud WHERE auditoria_id = $id_auditoria");
    $puntosTotales = 0;
    while($row = $resDet->fetch_assoc()) {
        $puntosTotales += $row['puntos_obtenidos'];
        $html .= "<tr>
                    <td>{$row['pregunta']}</td>
                    <td style='text-align:center;'>{$row['valor_seleccionado']}</td>
                    <td style='text-align:center;'>{$row['puntos_obtenidos']}</td>
                  </tr>";
    }

    $html .= '
            <tr style="font-weight:bold; background-color:#e2f2cf;">
                <td colspan="2" style="text-align:right;">CALIFICACIÓN TOTAL:</td>
                <td style="text-align:center;">'.$puntosTotales.'</td>
            </tr>
        </tbody>
    </table>

    <div class="section-title">EVIDENCIA FOTOGRÁFICA</div>
    <div style="width:100%;">';

    $fotos = $conn->query("SELECT * FROM auditorias_evidencias_aud WHERE auditoria_id = $id_auditoria");
    while($f = $fotos->fetch_assoc()) {
        $ruta = $f['ruta_archivo'];
        if(file_exists($ruta) && !str_ends_with($ruta, '.pdf')) {
            $html .= '
            <div class="foto-box">
                <img src="'.$ruta.'" class="foto-img" />
                <div style="font-size:8pt; margin-top:3px;">'.$f['tipo_evidencia'].'</div>
            </div>';
        }
    }

    $html .= '
    </div>
    <pagebreak /> <div class="section-title">FIRMAS DE CONFORMIDAD</div>
    <p style="font-size:8pt;">Quien firma este documento avala que la información proporcionada es la correcta.</p>
    
    <table class="footer-table">
        <tr>
            <td>
                <div class="linea-firma"></div>
                <strong>Nombre y Firma</strong><br>Responsable del Vehículo: '.$data['responsable_nombre'].'
            </td>
            <td>
                <div class="linea-firma"></div>
                <strong>Nombre y Firma</strong><br>Gerente de Sucursal: '.$data['gerente_nombre'].'
            </td>
        </tr>
    </table>';

    // 3. Inicializar mPDF y generar
    $mpdf = new \Mpdf\Mpdf([
        'margin_left' => 10,
        'margin_right' => 10,
        'margin_top' => 10,
        'margin_bottom' => 10,
    ]);

    $mpdf->WriteHTML($html);
    
    $nombreArchivo = "reportes/Auditoria_{$data['folio']}.pdf";
    $mpdf->Output($nombreArchivo, \Mpdf\Output\Destination::FILE);
    
    return [
        'ruta' => $nombreArchivo,
        'correo_responsable' => $data['correo_responsable'],
        'correo_auditor' => 'vgonzalez@drg.mx',
        'folio' => $data['folio']
    ];
}
?>