<?php
require("../config/db.php");

// Cabeceras para forzar la descarga del Excel
header("Content-Type: application/vnd.ms-excel; charset=utf-8");
header("Content-Disposition: attachment; filename=Reporte_Completo_Flotilla_" . date('Ymd_His') . ".xls");
header("Expires: 0");
header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
header("Cache-Control: private", false);

// Consulta completa con JOINs para traer nombres de sucursales y responsables
$query = "SELECT v.*, s.sucursal as sucursal_nombre, 
          u1.nombre as responsable_nombre, 
          u2.nombre as gerente_nombre 
          FROM vehiculos v
          LEFT JOIN sucursales s ON v.sucursal_id = s.id
          LEFT JOIN usuarios u1 ON v.responsable_id = u1.id
          LEFT JOIN usuarios u2 ON v.gerente_reportar_id = u2.id
          ORDER BY v.id DESC";

$result = $conn->query($query);
?>

<table border="1">
    <thead>
        <tr style="background-color: #007bff; color: #ffffff;">
            <th>ID</th>
            <th>No. Serie</th>
            <th>Fecha Alta</th>
            <th>Marca</th>
            <th>Modelo</th>
            <th>Anio</th>
            <th>Placas</th>
            <th>Estatus</th>
            <th>Sucursal</th>
            <th>Responsable</th>
            <th>Gerente a Reportar</th>
            <th>Tarjeta Circulacion</th>
            <th>No. Licencia</th>
            <th>Vigencia Licencia</th>
            <th>Aseguradora</th>
            <th>No. Poliza</th>
            <th>Vigencia Poliza</th>
            <th>Tel. Siniestro</th>
            <th>Observaciones</th>
        </tr>
    </thead>
    <tbody>
        <?php while ($v = $result->fetch_assoc()): ?>
        <tr>
            <td><?php echo $v['id']; ?></td>
            <td><?php echo $v['no_serie']; ?></td>
            <td><?php echo $v['fecha_alta']; ?></td>
            <td><?php echo $v['marca']; ?></td>
            <td><?php echo $v['modelo']; ?></td>
            <td><?php echo $v['anio']; ?></td>
            <td><?php echo $v['placas']; ?></td>
            <td><?php echo $v['estatus']; ?></td>
            <td><?php echo $v['sucursal_nombre']; ?></td>
            <td><?php echo $v['responsable_nombre']; ?></td>
            <td><?php echo $v['gerente_nombre']; ?></td>
            <td><?php echo $v['tarjeta_circulacion']; ?></td>
            <td><?php echo $v['no_licencia']; ?></td>
            <td><?php echo $v['fecha_vencimiento_licencia']; ?></td>
            <td><?php echo $v['aseguradora']; ?></td>
            <td><?php echo $v['no_poliza']; ?></td>
            <td><?php echo $v['vigencia_poliza']; ?></td>
            <td><?php echo $v['telefono_siniestro']; ?></td>
            <td><?php echo $v['observaciones']; ?></td>
        </tr>
        <?php endwhile; ?>
    </tbody>
</table>