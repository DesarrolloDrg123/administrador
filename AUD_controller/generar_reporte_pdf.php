<?php
require '../vendor/autoload.php';
use Mpdf\Mpdf;

function crearReportePDF($id_auditoria, $conn) {
    // 1. Obtener datos (JOINs con vehiculos, sucursales y usuarios)
    $query = "SELECT a.*, v.*, s.sucursal as sucursal_nombre, u.nombre as responsable_nombre, 
              u.email as correo_responsable, u.no_empleado, u.no_licencia, u.vencimiento_licencia,
              g.nombre as gerente_nombre, auditor.nombre as auditor_nombre
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

    // 2. Definir estilos y HTML
    $html = '
    <style>
        body { font-family: "Helvetica", sans-serif; font-size: 9pt; color: #333; }
        .logo { width: 180px; }
        .header-table { width: 100%; border: none; margin-bottom: 5px; }
        .header-text { text-align: right; color: #999; font-size: 8pt; }
        .fecha-verificacion { color: #80bf1f; font-weight: bold; margin-bottom: 10px; }
        
        .main-title { background-color: #f8f9fa; color: #000; text-align: center; font-weight: bold; padding: 3px; border: 1px solid #ccc; }
        .section-title { background-color: #80bf1f; color: white; padding: 4px; font-weight: bold; text-align: center; text-transform: uppercase; margin-top: 1px; }
        
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #ccc; padding: 4px; }
        
        .label { background-color: #f8f9fa; width: 20%; color: #333; }
        .value { width: 30%; font-weight: normal; }
        .check { color: #80bf1f; font-family: DejaVu Sans, sans-serif; } /* Para el icono de check */
        
        .foto-box { border: 1px solid #ccc; text-align: center; margin-bottom: 10px; padding: 5px; width: 48%; float: left; margin-right: 2%; }
        .foto-img { width: 100%; height: 150px; object-fit: cover; }
        
        .footer-text { text-align: center; font-style: italic; margin-top: 20px; font-size: 9pt; }
        .signature-box { text-align: center; padding-top: 40px; }
    </style>

    <table class="header-table">
        <tr>
            <td style="border:none;"><img src="../img/logo_drg.png" class="logo"></td>
            <td class="header-text" style="border:none;">
                <strong style="color:#80bf1f; font-size:11pt;">Distribuidora Reyes G</strong><br>
                Tamazula 309-A, Parque Industrial,<br>
                35078 Gómez Palacio, Dgo.<br>
                www.drg.mx
            </td>
        </tr>
    </table>

    <div class="fecha-verificacion">Fecha de Verificación: '.date("d/m/Y", strtotime($data['fecha_auditoria'])).'</div>

    <div class="main-title">BÍTACORA DE CONTROL VEHICULAR</div>

    <div class="section-title">INFORMACIÓN GENERAL DE LA UNIDAD</div>
    <table>
        <tr>
            <td class="label"><span class="check">✔</span> Modelo</td><td class="value">'.$data['modelo'].'</td>
            <td class="label"><span class="check">✔</span> Marca</td><td class="value">'.$data['marca'].'</td>
        </tr>
        <tr>
            <td class="label"><span class="check">✔</span> Kilometraje</td><td class="value">'.number_format($data['kilometraje']).'</td>
            <td class="label"><span class="check">✔</span> No de Serie</td><td class="value">'.$data['no_serie'].'</td>
        </tr>
    </table>

    <div class="section-title">DATOS GENERALES</div>
    <table>
        <tr>
            <td class="label"><span class="check">✔</span> Sucursal</td><td class="value">'.$data['sucursal_nombre'].'</td>
            <td class="label"><span class="check">✔</span> Gerente de Sucursal</td><td class="value">'.$data['gerente_nombre'].'</td>
        </tr>
        <tr>
            <td class="label"><span class="check">✔</span> Responsable</td><td class="value">'.$data['responsable_nombre'].'</td>
            <td class="label"><span class="check">✔</span> No. de Empleado</td><td class="value">'.$data['no_empleado'].'</td>
        </tr>
        <tr>
            <td class="label"><span class="check">✔</span> No. de Licencia</td><td class="value">'.$data['no_licencia'].'</td>
            <td class="label"><span class="check">✔</span> Vencimiento Licencia</td><td class="value">'.$data['vencimiento_licencia'].'</td>
        </tr>
        <tr>
            <td class="label"><span class="check">✔</span> No. de Placas</td><td class="value">'.$data['placas'].'</td>
            <td class="label"><span class="check">✔</span> T. de Circulación</td><td class="value">'.$data['tarjeta_circulacion'].'</td>
        </tr>
        <tr>
            <td class="label"><span class="check">✔</span> Póliza de Seguro</td><td class="value">'.$data['no_poliza'].'</td>
            <td class="label"><span class="check">✔</span> Vigencia Póliza</td><td class="value">'.$data['vigencia_poliza'].'</td>
        </tr>
        <tr>
            <td class="label"><span class="check">✔</span> Aseguradora</td><td class="value">'.$data['aseguradora'].'</td>
            <td class="label"><span class="check">✔</span> Teléfono (Siniestro)</td><td class="value">'.$data['telefono_siniestro'].'</td>
        </tr>
    </table>

    <div class="section-title">CHECK LIST DEL VEHÍCULO</div>
    <table>
        <thead>
            <tr style="background-color: #f8f9fa;">
                <th colspan="2">Elemento Evaluado</th>
                <th style="text-align:center;">Resultado</th>
                <th style="text-align:center;">Puntos</th>
            </tr>
        </thead>
        <tbody>';

    // Aquí corregimos el nombre de los campos usando la columna 'pregunta' de tu tabla detalle
    $resDet = $conn->query("SELECT * FROM auditorias_detalle_aud WHERE auditoria_id = $id_auditoria");
    $puntosTotales = 0;
    while($row = $resDet->fetch_assoc()) {
        $puntosTotales += $row['puntos_obtenidos'];
        $html .= '<tr>
                    <td style="width:5%; text-align:center;"><span class="check">✔</span></td>
                    <td style="width:55%;">'.$row['pregunta'].'</td>
                    <td style="width:25%; text-align:center;">'.$row['valor_seleccionado'].'</td>
                    <td style="width:15%; text-align:center;">'.$row['puntos_obtenidos'].'</td>
                  </tr>';
    }

    $html .= '
            <tr style="font-weight:bold; background-color:#f2f9e9;">
                <td colspan="3" style="text-align:right;">CALIFICACIÓN TOTAL:</td>
                <td style="text-align:center;">'.$puntosTotales.'</td>
            </tr>
        </tbody>
    </table>

    <div class="section-title">EVIDENCIA FOTOGRÁFICA</div>
    <div style="width:100%; margin-top:10px;">';

    $fotos = $conn->query("SELECT * FROM auditorias_evidencias_aud WHERE auditoria_id = $id_auditoria");
    $count = 0;
    while($f = $fotos->fetch_assoc()) {
        $ruta = '../' . $f['ruta_archivo'];
        if(file_exists($ruta)) {
            $html .= '
            <div class="foto-box">
                <img src="'.$ruta.'" class="foto-img" />
                <div style="font-size:7pt; font-weight:bold; color:#80bf1f;">'.$f['tipo_evidencia'].'</div>
            </div>';
            $count++;
            if($count % 2 == 0) $html .= '<div style="clear:both;"></div>';
        }
    }

    $html .= '
    </div>
    <div style="clear:both;"></div>

    <table style="width:100%; margin-top:30px; border:none;">
        <tr>
            <td style="border:none; width:45%; border-top: 1px solid #000; text-align:center;">
                <div class="signature-box">
                    <strong>'.$data['responsable_nombre'].'</strong><br>
                    Responsable del Vehículo
                </div>
            </td>
            <td style="border:none; width:10%;"></td>
            <td style="border:none; width:45%; border-top: 1px solid #000; text-align:center;">
                <div class="signature-box">
                    <strong>'.$data['gerente_nombre'].'</strong><br>
                    Gerente de Sucursal
                </div>
            </td>
        </tr>
    </table>

    <div class="footer-text">
        Quien firma este documento avala que la información proporcionada es la correcta.
    </div>';

    // 3. Inicializar mPDF y generar
    $mpdf = new \Mpdf\Mpdf([
        'margin_left' => 12,
        'margin_right' => 12,
        'margin_top' => 12,
        'margin_bottom' => 12,
    ]);

    $mpdf->WriteHTML($html);
    
    $nombreArchivo = "reportes/Auditoria_{$data['folio']}.pdf";
    $mpdf->Output("../".$nombreArchivo, \Mpdf\Output\Destination::FILE);
    
    return [
        'ruta' => "../".$nombreArchivo,
        'correo_responsable' => $data['correo_responsable'],
        'folio' => $data['folio']
    ];
}