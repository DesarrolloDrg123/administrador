<?php
require("../config/db.php");

// Encabezados para forzar la descarga del archivo Excel
header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=periodos_vacacionales.xls");
header("Pragma: no-cache");
header("Expires: 0");

// Conexión a la base de datos y consulta
$sql = "
    SELECT 
        p.id as periodo_id,
        p.num_periodo as periodo,
        p.dias_agregados as diasAgregados,
        p.dias_disfrutados as diasDisfrutados,
        p.dias_restantes as diasRestantes,
        p.vigencia as vigencia,
        u.nombre as nombre_usuario,
        u.fecha_ingreso as ingreso,
        u.num_empleado as noempleado
    FROM periodos p
    JOIN usuarios u ON p.usuario_id = u.id
    ORDER BY u.num_empleado, u.nombre, p.num_periodo
";

$result = $conn->query($sql);

// Inicio de la tabla con estilos básicos
echo "<table border='1' style='border-collapse: collapse; width: 100%; font-family: Arial, sans-serif;'>";
echo "<tr style='background-color: #343a40; color: white; font-weight: bold; text-align: center;'>
        <th style='padding: 10px; border: 1px solid #000;'>No. Empleado</th>
        <th style='padding: 10px; border: 1px solid #000;'>Nombre</th>
        <th style='padding: 10px; border: 1px solid #000;'>Fecha de Ingreso</th>
        <th style='padding: 10px; border: 1px solid #000;'>Periodo</th>
        <th style='padding: 10px; border: 1px solid #000;'>Días Agregados</th>
        <th style='padding: 10px; border: 1px solid #000;'>Días Disfrutados</th>
        <th style='padding: 10px; border: 1px solid #000;'>Días Restantes</th>
        <th style='padding: 10px; border: 1px solid #000;'>Vigencia</th>
      </tr>";

// Recorrer los resultados y generar las filas de la tabla
while ($row = $result->fetch_assoc()) {
    $fecha_ingreso_formateada = DateTime::createFromFormat('Y-m-d', $row['ingreso'])->format('d/m/Y');

    echo "<tr style='text-align: center;'>";
    echo "<td style='padding: 8px; border: 1px solid #000;'>" . htmlspecialchars($row['noempleado']) . "</td>";
    echo "<td style='padding: 8px; border: 1px solid #000;'>" . htmlspecialchars($row['nombre_usuario']) . "</td>";
    echo "<td style='padding: 8px; border: 1px solid #000;'>" . htmlspecialchars($fecha_ingreso_formateada) . "</td>";
    echo "<td style='padding: 8px; border: 1px solid #000;'>" . htmlspecialchars($row['periodo']) . "</td>";
    echo "<td style='padding: 8px; border: 1px solid #000;'>" . htmlspecialchars($row['diasAgregados']) . "</td>";
    echo "<td style='padding: 8px; border: 1px solid #000;'>" . htmlspecialchars($row['diasDisfrutados']) . "</td>";
    echo "<td style='padding: 8px; border: 1px solid #000;'>" . htmlspecialchars($row['diasRestantes']) . "</td>";
    echo "<td style='padding: 8px; border: 1px solid #000;'>" . htmlspecialchars($row['vigencia']) . "</td>";
    echo "</tr>";
}

// Fin de la tabla
echo "</table>";

// Cerrar la conexión a la base de datos
$conn->close();
?>
