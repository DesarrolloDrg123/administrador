<?php
require '../vendor/autoload.php';
use Mpdf\Mpdf;

function crearReportePDF($id_auditoria, $conn) {
    // 1. Obtener datos (JOINs con vehiculos, sucursales y usuarios)
    $query = "SELECT a.*,a.observaciones AS observaciones_auditoria, v.*, s.sucursal as sucursal_nombre, u.nombre as responsable_nombre, 
              u.email as correo_responsable, u.num_empleado, v.no_licencia, v.fecha_vencimiento_licencia,
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

        .obs-box { border: 1px solid #ccc; background-color: #f9f9f9; padding: 10px; min-height: 50px; margin-bottom: 10px; }
    </style>

    <table class="header-table">
        <tr>
            <td style="border:none;"><img src="../img/logo-drg.png" class="logo"></td>
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
            <td class="label"><span class="check">✔</span> No. de Empleado</td><td class="value">'.$data['num_empleado'].'</td>
        </tr>
        <tr>
            <td class="label"><span class="check">✔</span> No. de Licencia</td><td class="value">'.$data['no_licencia'].'</td>
            <td class="label"><span class="check">✔</span> Vencimiento Licencia</td>
                <td class="value">'.($data['fecha_vencimiento_licencia'] ? date("d/m/Y", strtotime($data['fecha_vencimiento_licencia'])) : 'N/A').'</td>
        </tr>
        <tr>
            <td class="label"><span class="check">✔</span> No. de Placas</td><td class="value">'.$data['placas'].'</td>
            <td class="label"><span class="check">✔</span> T. de Circulación</td><td class="value">'.$data['tarjeta_circulacion'].'</td>
        </tr>
        <tr>
            <td class="label"><span class="check">✔</span> Póliza de Seguro</td><td class="value">'.$data['no_poliza'].'</td>
            <td class="label"><span class="check">✔</span> Vigencia Póliza</td>
                <td class="value">'.($data['vigencia_poliza'] ? date("d/m/Y", strtotime($data['vigencia_poliza'])) : 'N/A').'</td>
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

    $queryDetalle = "SELECT i.tipo AS categoria_nombre, i.descripcion as nombre_item, d.valor_seleccionado, d.puntos_obtenidos 
                     FROM auditorias_detalle_aud d
                     JOIN cat_items_auditoria_aud i ON d.concepto_id = i.id
                     WHERE d.auditoria_id = $id_auditoria
                     ORDER BY i.tipo ASC"; // O i.categoria, ajusta al nombre real de tu columna

    $resDet = $conn->query($queryDetalle);
    
    $categoriaActual = "";
    $subtotalCat = 0;
    $puntosTotales = 0;
    $registros = $resDet->fetch_all(MYSQLI_ASSOC);

    foreach($registros as $index => $row) {
        
        // ¿Cambió la categoría?
        if ($row['categoria_nombre'] !== $categoriaActual) {
            
            // Si no es la primera, imprimimos el subtotal de la categoría que acaba de terminar
            if ($categoriaActual !== "") {
                $html .= '
                <tr class="subtotal-row">
                    <td colspan="3" style="text-align:right;">Subtotal '.$categoriaActual.':</td>
                    <td style="text-align:center;">'.$subtotalCat.'</td>
                </tr>';
                $subtotalCat = 0; // Reiniciamos para la nueva
            }

            // Pintar el nombre de la nueva categoría
            $html .= '
            <tr class="category-header">
                <td colspan="4">'.strtoupper($row['categoria_nombre']).'</td>
            </tr>';
            
            $categoriaActual = $row['categoria_nombre'];
        }

        $subtotalCat += $row['puntos_obtenidos'];
        $puntosTotales += $row['puntos_obtenidos'];

        $html .= '<tr>
                    <td style="width:5%; text-align:center;"><span class="check">✔</span></td>
                    <td style="width:55%;">'.$row['nombre_item'].'</td>
                    <td style="width:25%; text-align:center;">'.$row['valor_seleccionado'].'</td>
                    <td style="width:15%; text-align:center;">'.$row['puntos_obtenidos'].'</td>
                  </tr>';

        // Si es el último registro de todos, imprimimos el último subtotal pendiente
        if ($index === count($registros) - 1) {
            $html .= '
            <tr class="subtotal-row">
                <td colspan="3" style="text-align:right;">Subtotal '.$categoriaActual.':</td>
                <td style="text-align:center;">'.$subtotalCat.'</td>
            </tr>
        </tbody>
    </table>';
        }
    }

    $html .= '</tbody></table>';

    // --- SECCIÓN: MTTOS CAPTURADOS ---
    $html .= '<div class="section-title">SERVICIOS DE MANTENIMIENTO</div>
    <table>
        <thead>
            <tr style="background-color: #f8f9fa;">
                <th style="text-align:center; width:15%;">Fecha</th>
                <th style="text-align:center; width:10%;">KM</th>
                <th style="text-align:center; width:20%;">Tipo de Servicio</th>
                <th style="text-align:center; width:15%;">Taller</th>
                <th style="text-align:center; width:40%;">Observaciones</th>
            </tr>
        </thead>
        <tbody>';

    $resMant = $conn->query("SELECT * FROM auditorias_mantenimiento_aud WHERE auditoria_id = $id_auditoria");
    
    if ($resMant && $resMant->num_rows > 0) {
        while($m = $resMant->fetch_assoc()) {
            $fechaMant = ($m['fecha'] && $m['fecha'] != '0000-00-00') ? date("d/m/Y", strtotime($m['fecha'])) : 'N/A';
            $html .= '<tr>
                        <td style="text-align:center;">'.$fechaMant.'</td>
                        <td style="text-align:center;">'.number_format($m['km']).'</td>
                        <td>'.$m['servicio'].'</td>
                        <td>'.$m['taller'].'</td>
                        <td>'.$m['observaciones'].'</td>
                      </tr>';
        }
    } else {
        // Filas vacías para mantener el formato si no hay datos
        for ($i = 0; $i < 3; $i++) {
            $html .= '<tr><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td></tr>';
        }
    }
    $html .= '</tbody></table>';

    // --- SECCIÓN: INCIDENCIAS CAPTURADAS ---
    $html .= '<div class="section-title">INCIDENCIAS REPORTADAS EN ESTA AUDITORÍA</div>
    <table>
        <thead>
            <tr style="background-color: #f8f9fa;">
                <th style="width:70%;">Descripción de la Incidencia</th>
                <th style="width:30%; text-align:center;">Estatus</th>
            </tr>
        </thead>
        <tbody>';
    
    $resInc = $conn->query("SELECT descripcion, estatus FROM auditorias_incidencias_aud WHERE auditoria_id = $id_auditoria");
    if ($resInc && $resInc->num_rows > 0) {
        while($inc = $resInc->fetch_assoc()) {
            $html .= '<tr>
                        <td>'.$inc['descripcion'].'</td>
                        <td style="text-align:center;">'.$inc['estatus'].'</td>
                      </tr>';
        }
    } else {
        $html .= '<tr><td colspan="2" style="text-align:center; color: #999;">No se reportaron incidencias en esta revisión.</td></tr>';
    }
    $html .= '</tbody></table>';

    $html .= '<div class="section-title">OBSERVACIONES GENERALES DEL AUDITOR</div>
    <div class="obs-box">
        '.(!empty($data['observaciones_auditoria']) ? nl2br($data['observaciones_auditoria']) : 'Sin observaciones adicionales por parte del auditor.').'
    </div>';

    // --- SECCIÓN: EVIDENCIAS FOTOGRAFICAS ---
    $html .= '
    <div class="section-title">EVIDENCIA FOTOGRÁFICA</div>
    <div style="width:100%; margin-top:10px;">';

    $fotos = $conn->query("SELECT * FROM auditorias_evidencias_aud WHERE auditoria_id = $id_auditoria AND tipo_archivo = 'foto'");
    $count = 0;
    while($f = $fotos->fetch_assoc()) {
        // 1. Ruta relativa para que PHP la encuentre y valide
        $rutaParaValidar = '../' . $f['ruta_archivo']; 
        
        if(file_exists($rutaParaValidar)) {
            $html .= '
            <div class="foto-box">
                <img src="'.$rutaParaValidar.'" class="foto-img" />
                <div style="font-size:7pt; font-weight:bold; color:#80bf1f;">'.$f['tipo_archivo'].'</div>
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
            <td style="border:none; width:45%; text-align:center;">
                <div class="signature-box">
                    <strong>'.$data['responsable_nombre'].'</strong><br>
                    Responsable del Vehículo
                </div>
            </td>
            <td style="border:none; width:10%;"></td>
            <td style="border:none; width:45%; text-align:center;">
                <div class="signature-box">
                    <strong>'.$data['gerente_nombre'].'</strong><br>
                    Gerente de Sucursal
                </div>
            </td>
        </tr>
    </table>';

    // 3. Inicializar mPDF y generar
    $mpdf = new \Mpdf\Mpdf([
        'margin_left' => 12,
        'margin_right' => 12,
        'margin_top' => 12,
        'margin_bottom' => 12,
    ]);

    $mpdf->WriteHTML($html);
    
    $nombreArchivo = "reportes/Auditoria_{$data['folio']}.pdf";
    $mpdf->Output($nombreArchivo, \Mpdf\Output\Destination::FILE);
    
    // --- AQUÍ GUARDAMOS SOLO LA UBICACIÓN (EL TEXTO) ---
    $sqlRuta = "UPDATE auditorias_vehiculos_aud SET reporte = ? WHERE id = ?";
    $stmtRuta = $conn->prepare($sqlRuta);
    $stmtRuta->bind_param("si", $nombreArchivo, $id_auditoria);
    $stmtRuta->execute();
    // --------------------------------------------------
    
    $adjuntosExtra = [];
    $resEvidencias = $conn->query("SELECT ruta_archivo FROM auditorias_evidencias_aud WHERE auditoria_id = $id_auditoria");
    while($ev = $resEvidencias->fetch_assoc()){
        if (str_ends_with(strtolower($ev['ruta_archivo']), '.pdf')) {
            $rutaCompleta = '../' . $ev['ruta_archivo'];
            if(file_exists($rutaCompleta)){
                $adjuntosExtra[] = $rutaCompleta;
            }
        }
    }

    return [
        'ruta' => $nombreArchivo,
        'correo_responsable' => $data['correo_responsable'],
        'folio' => $data['folio'],
        'adjuntos_pdf' => $adjuntosExtra // Agregamos este array al retorno
    ];
}
?>