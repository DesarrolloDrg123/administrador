<?php
session_start();

$usuario_id = $_SESSION['usuario_id'];
require("config/db.php");
include("src/templates/adminheader.php");
require('vendor/autoload.php');

$nombreSolicitante = $_SESSION['nombre'];

if (!isset($_SESSION['loggedin']) || !$_SESSION['loggedin']) {
    header("Location: index.php");
    exit();
}
/*
if (!isset($_SESSION['permisos'][5])) { //Permiso de Solicitar vacaciones
    header("Location: inicio.php");
    exit();
}*/

?>


<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Periodos Vacacionales</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <style>
    
        .jumbotron {
            padding: 1rem 1rem;
            margin-top: 25px;
            margin-bottom: 2rem;
            background-color: #e9ecef;
            border-radius: .9rem;
        }
        .jb1 {
            
            background-color: #343a40;
            color: white;
            width: fit-content;
        }
    
        p {
            font-size: 30px;
        }
    
        .btn {
            font-size: 20px;
        }
    
        input[type="date"] {
            width: 150px;
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 5px;
            font-size: 16px;
        }
    
        body {
            background-image: url('../img/drg3.png');
            background-size: cover;
            background-attachment: fixed;
            background-position: center;
            background-repeat: no-repeat;
        }
    
        table {
            width: 100%;
            border-collapse: collapse;
        }
    
        table,
        th,
        td {
            border: 1px solid black;
        }
    
        th,
        td {
            padding: 10px;
            text-align: center;
        }
    
        th {
            background-color: #343a40;
            color: white;
        }
    </style>
</head>
    <body>
        <div class="container">
            <div class="jumbotron jb1">
                <div id="empleados">
        <h2 class="mt-5 text-center">Periodos Vacacionales</h2>
        <a href="VAC_controller/exportar_excel.php" class="btn btn-success">Exportar a Excel</a>
    
         <?php
        // Mostrar mensaje basado en el estado recibido
        if (isset($_GET['status'])) {
            if ($_GET['status'] === 'success') {
                echo "<div class='alert alert-success'>Días disfrutados actualizados correctamente.</div>";
            } elseif ($_GET['status'] === 'error') {
                echo "<div class='alert alert-danger'>Hubo un error al actualizar los días disfrutados. Por favor, inténtalo de nuevo.</div>";
            }
        }
        ?>
        <div class="d-flex mt-5">
            <?php
            $sql_periodos = "
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
    
            if ($stmt1 = $conn->prepare($sql_periodos)) {
                $stmt1->execute();
                $resultado = $stmt1->get_result();
                if ($stmt1 = $conn->prepare($sql_periodos)) {
                $stmt1->execute();
                $resultado = $stmt1->get_result();
                if ($resultado->num_rows > 0) {
                    echo "<form method='POST' action='VAC_controller/actualizar_dias.php'>"; // Formulario que envía datos a un archivo PHP
                    echo "<table>";
                    echo "<tr>
                            <th>No. Empleado</th>
                            <th>Nombre</th>
                            <th>Fecha de Ingreso</th>
                            <th>Antigüedad</th>
                            <th>Periodo</th>
                            <th>Días Agregados</th>
                            <th>Días Disfrutados</th>
                            <th>Días Restantes</th>
                            <th>Acción</th>
                          </tr>";
    
                    $last_name = null; // Variable para rastrear el último nombre mostrado
                    while ($row = $resultado->fetch_assoc()) {
                        $fecha_ingreso_formateada = DateTime::createFromFormat('Y-m-d', $row['ingreso'])->format('d/m/Y');
                        $antiguedad = (new DateTime())->diff(new DateTime($row['ingreso']))->y;
    
                        echo "<tr>";
                        // Mostrar el nombre y fecha de ingreso solo si es diferente al último
                        if ($row['nombre_usuario'] !== $last_name) {
                            echo "<td>" . htmlspecialchars($row['noempleado']) . "</td>";
                            echo "<td>" . htmlspecialchars($row['nombre_usuario']) . "</td>";
                            echo "<td>" . htmlspecialchars($fecha_ingreso_formateada) . "</td>";
                            echo "<td>" . htmlspecialchars($antiguedad . ' años') . "</td>";
                            $last_name = $row['nombre_usuario'];
                        } else {
                            // Si el nombre ya fue mostrado, dejar las celdas vacías
                            echo "<td></td><td></td><td></td><td></td>";
                        }
                        // Mostrar siempre los datos del periodo
                        echo "<td>" . htmlspecialchars($row['periodo']) . "</td>";
                        echo "<td>" . htmlspecialchars($row['diasAgregados']) . "</td>";
                        echo "<td>
                                <input type='number' name='dias_disfrutados[{$row['periodo_id']}]' value='" . htmlspecialchars($row['diasDisfrutados']) . "' min='0' />
                              </td>";
                        echo "<td>" . htmlspecialchars($row['diasRestantes']) . "</td>";
                        echo "<td>
                                <button type='submit' name='guardar' value='{$row['periodo_id']}'>Guardar</button>
                              </td>";
                        echo "</tr>";
                    }
                    echo "</table>"; // Cierre de la tabla
                    echo "</form>"; // Cierre del formulario
                } else {
                    echo "<p>No se encontraron resultados.</p>";
                }
            } else {
                echo "<p>Error en la consulta SQL.</p>";
            }
            ?>
        </div>
    </div>
                <div id="autorizacion" class="collapse">
                <br>
                <h2>Autorizaciones pendientes</h2>
    
                <?php
                // Obtener todas las solicitudes de vacaciones pendientes
                $sql = "SELECT 
                s.id AS solicitud_id,
                s.fecha_inicio, 
                s.fecha_fin, 
                s.estatus, 
                s.fecha_solicitud, 
                u.nombre,
                u.departamento,
                u.puesto, 
                u.id AS usuario_id
                    FROM 
                        solicitudes_vacaciones s
                    JOIN 
                        usuarios u 
                    ON 
                        s.usuario_id = u.id
                    WHERE 
                        u.puesto LIKE '%Gerente%'
                        OR u.puesto LIKE '%Supervisor%'
    
                    ORDER BY 
                        s.fecha_solicitud DESC;";
    
    
                if ($stmt = $conn->prepare($sql)) {
                    $stmt->execute();
                    $result = $stmt->get_result();
    
                    if ($result->num_rows > 0) {
                        echo "<table>";
                        echo "<tr><th>Fecha de Inicio</th><th>Fecha de Fin</th><th>Fecha de Solicitud</th><th>Solicitante</th><th>Estatus</th><th>Acciones</th></tr>";
    
                        while ($row = $result->fetch_assoc()) {
                           
                           
                                $fecha_inicio = DateTime::createFromFormat('Y-m-d', $row['fecha_inicio'])->format('d/m/Y');
                                $fecha_fin = DateTime::createFromFormat('Y-m-d', $row['fecha_fin'])->format('d/m/Y');
                                $fecha_solicitud = DateTime::createFromFormat('Y-m-d H:i:s', $row['fecha_solicitud'])->format('d/m/Y');
    
                                echo "<tr>";
                                echo "<td>" . htmlspecialchars($fecha_inicio) . "</td>";
                                echo "<td>" . htmlspecialchars($fecha_fin) . "</td>";
                                echo "<td>" . htmlspecialchars($fecha_solicitud) . "</td>";
                                echo "<td>" . htmlspecialchars($row['nombre']) . "</td>";
                                if ($row['estatus'] == 'aprobada') {
                                    echo "<td>" . htmlspecialchars("Aprobada") . "</td>";
                                } elseif ($row['estatus'] == 'rechazada') {
                                    echo "<td>" . htmlspecialchars("Rechazada") . "</td>";
                                } elseif ($row['estatus'] == 'pendiente') {
                                    echo "<td>" . htmlspecialchars("Pendiente") . "</td>";
                                } elseif ($row['estatus'] == 'pagado') {
                                    echo "<td>" . htmlspecialchars("Pagado") . "</td>";
                                }
    
                                if ($row['estatus'] == 'pendiente') {
                                    echo "<td>
                            <form method='POST' action='VAC_controller/procesar_vacaciones_admin.php'>
                                <input type='hidden' name='solicitud_id' value='" . $row['solicitud_id'] . "'>
                                <input type='hidden' name='usuario_id' value='" . $row['usuario_id'] . "'>
                                <input type='hidden' name='nombre_solicitante' value= '" . $row['nombre'] . "'>
                                <input type='hidden' name='fecha_inicio' value='" . $row['fecha_inicio'] . "'>  
                                <input type='hidden' name='fecha_fin' value='" . $row['fecha_fin'] . "'>        
                                <button type='submit' name='accion' value='aprobar' class='btn btn-info'>Aprobar</button>
                                <button type='button' class='btn btn-danger' onclick='showCancelForm(" . $row['solicitud_id'] . ")'>Rechazar</button>
                            </form>
                            <div id='cancel-form-" . $row['solicitud_id'] . "' style='display:none;'>
                                <form method='POST' action='VAC_controller/autorizar_vacaciones.php'>
                                    <input type='hidden' name='solicitud_id' value='" . $row['solicitud_id'] . "'>
                                    <input type='hidden' name='usuario_id' value='" . $row['usuario_id'] . "'>
                                    <input type='hidden' name='nombre_solicitante' value= '" . $row['nombre'] . "'>
                                    <input type='hidden' name='fecha_inicio' value='" . $row['fecha_inicio'] . "'>  
                                    <input type='hidden' name='fecha_fin' value='" . $row['fecha_fin'] . "'>        
                                    <textarea name='razon_cancelacion' placeholder='Razón de rechazo' required></textarea>
                                    <button type='submit' name='accion' value='rechazar' class='btn btn-danger'>Confirmar Rechazo</button>
                                </form>
                            </div>
                        </td>";
                                } else {
                                    echo "<td>N/A</td>";
                                }
                            }
                                echo "</tr>";
                            
                        }
    
                        echo "</table>";
                    } else {
                        echo "<p>No hay solicitudes pendientes de autorización.</p>";
                    }
    
                    $stmt->close();
                    
                
                ?>
                
                </div>
                <div id="status" class="collapse">
                <br>
                <h2 class="mb-5 text-center">Estatus de vacaciones</h2>
    
                <?php
                // Obtener las solicitudes de vacaciones del usuario autenticado
                
                $sql = "SELECT * FROM solicitudes_vacaciones WHERE usuario_id = ? ORDER BY fecha_solicitud DESC";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("i", $usuario_id);  // Vincular el id del usuario
                $stmt->execute();
                $result = $stmt->get_result();
                
    
                if ($result->num_rows > 0) {
                    
                    // Mostrar la tabla con las solicitudes
                    echo "<table>";
                    echo "<tr><th>Fecha de Inicio</th><th>Fecha de Fin</th><th>Estatus</th><th>Fecha de Solicitud</th></tr>";
    
                    // Recorrer las solicitudes y mostrarlas en la tabla
                    while ($row = $result->fetch_assoc()) {
                       
                        // Formatear las fechas a dd/mm/aaaa
                        $fecha_inicio = DateTime::createFromFormat('Y-m-d', $row['fecha_inicio'])->format('d/m/Y');
                        $fecha_fin = DateTime::createFromFormat('Y-m-d', $row['fecha_fin'])->format('d/m/Y');
                        $fecha_solicitud = DateTime::createFromFormat('Y-m-d H:i:s', $row['fecha_solicitud'])->format('d/m/Y');
    
                        echo "<tr>";
                        echo "<td>" . htmlspecialchars($fecha_inicio) . "</td>";
                        echo "<td>" . htmlspecialchars($fecha_fin) . "</td>"; 
                        if($row['estatus'] === 'pendiente')
                        {
                            echo "<td>" . htmlspecialchars('Pendiente de Autorizacion') . "</td>";
    
                        }
                        elseif($row['estatus'] === 'aprobada'){
                            echo "<td>" . htmlspecialchars('Autorizada, Disfruta tus Vacaciones') . "</td>";
    
                        }
                        elseif($row['estatus']=== 'rechazada'){
                            echo "<td>" . htmlspecialchars('Rechazada, ') . $row['razon_cancelacion'] . "</td>";
                        
    
                        }
                        elseif($row['estatus']==='pagado'){
                            echo "<td>" . htmlspecialchars('Autorizada, Disfruta tus Vacaciones') . "</td>";
    
                        }
                        echo "<td>" . htmlspecialchars($fecha_solicitud) . "</td>";
                        echo "</tr>";
                    }
    
                    echo "</table>";
                } else {
                    echo "<p>No has realizado ninguna solicitud de vacaciones.</p>";
              
                }
            }
            ?>
        </div>
            </div>
        </div>
        
    </div>
    </body>
</html>

<?php
$conn->close();
include("src/templates/adminfooter.php");
?>