<?php

require("config/db.php");
include("src/templates/adminheader.php");
require('vendor/autoload.php');

if (!isset($_SESSION['loggedin']) || !$_SESSION['loggedin']) {
    header("Location: index.php");
    exit();
}
/*
if (!isset($_SESSION['permisos'][3])) { //Permiso de Solicitar vacaciones
    header("Location: inicio.php");
    exit();
}*/


?>
<style>
    .jb1 {
        background-color: #2C343B;
        color: white;
        width: 1000px;
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
        background-color: #212529;
        color: white;
    }

    body {
        font-family: Arial, sans-serif;
        background-color: #f8f9fa;
        margin: 0;
        padding: 0;
    }

    .header {
        background-color: #192a56;
        color: white;
        padding: 15px;
        text-align: center;
    }

    .header img {
        height: 50px;
        vertical-align: middle;
    }

    .nav {
        display: flex;
        justify-content: center;
        background-color: #192a56;
        padding: 10px;
    }

    .nav a {
        color: white;
        margin: 0 15px;
        text-decoration: none;
        font-size: 18px;
    }

    .nav a:hover {
        text-decoration: underline;
    }

    .container {
        padding: 40px;
        max-width: 1000px;
        margin: auto;
    }

    .title {
        text-align: center;
        font-size: 28px;
        margin-bottom: 20px;
    }

    /* Flexbox for layout */
    .content {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
    }

    /* Left side content - Bienvenido and Antigüedad containers */
    .left-box {
        font-size: 10px;
        background-color: #ffffff;
        /* Azul oscuro */
        color: black;
        padding: 10px;
        /* Reducir padding */
        margin-bottom: 10px;
        width: 320px;
        height: 80px;
        display: flex;
        align-items: center;
        justify-content: center;
        text-align: center;
        border-radius: 45px;
        /* Elimina bordes redondeados */
    }

    /* Right side content */
    .right-box {
        width: 45%;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
    }

    .right-box p {
        margin: 0 0 20px;
        font-size: 18px;
    }

    .btn-request {
        background-color: white;
        color: #34495e;
        padding: 10px 30px;
        border: 2px solid #ffffff;
        border-radius: 25px;
        font-size: 16px;
        cursor: pointer;
        transition: background-color 0.3s ease, color 0.3s ease;
    }

    .btn-request:hover {
        background-color: #212529;
        color: white;
    }

    .form-group {
        margin: 20px 0;
        text-align: center;
    }

    /* Flexbox for the date fields */
    .date-container {
        display: flex;
        justify-content: space-between;
    }

    /* Add space between date fields */
    .date-container .form-group {
        flex: 1;
        margin-right: 30px;
    }

    .date-container .form-group:last-child {
        margin-right: 0;
    }

    input[type="date"] {
        padding: 10px;
        border: 1px solid #ccc;
        border-radius: 5px;
        font-size: 16px;
        width: 100%;
    }

    .footer {
        text-align: center;
        padding: 20px;
        background-color: #f1f1f1;
        margin-top: 50px;
        color: #555;
    }

    .line {
        margin: 10px 0;
        border-bottom: 1px solid #ffffff;
        width: 100%;
    }

    .plus-icon {
        display: inline-block;
        padding-left: 10px;
        font-size: 20px;
        color: #ffffff;
    }
</style>



<br>

<form action="VAC_controller/aprobar_vacaciones.php" method="post">
    <div class="container jumbotron jb1 mt-5" style="background-color:#212529; color: white;">
        <?php if($_SESSION['departamento'] == 'Gerente Sistemas'): ?>
        <h1>Departamento de Sistemas</h1>
        <?php else :?>
        <h1>Departamento de <?php echo htmlspecialchars($_SESSION['departamento']) ?></h1>
        <?php endif?>

        <h2>Autorizaciones pendientes</h2>


        <?php
        // Obtener todas las solicitudes de vacaciones pendientes
        $sql = "SELECT 
                s.id AS solicitud_id,
                s.fecha_inicio, 
                s.fecha_fin, 
                s.estatus, 
                s.fecha_solicitud, 
                u.jefe_directo,
                u.nombre,
                u.departamento, 
                u.rol as rol,
                u.id AS usuario_id
            FROM 
                solicitudes_vacaciones s
            JOIN 
                usuarios u 
            ON 
                s.usuario_id = u.id
            ORDER BY 
                s.fecha_solicitud DESC;";
                
        

        if ($stmt = $conn->prepare($sql)) {
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                echo "<table>";
                echo "<tr><th>Fecha de Inicio</th><th>Fecha de Fin</th><th>Fecha de Solicitud</th>
                <th>Solicitante</th><th>Estatus</th><th>Acciones</th></tr>";


                while ($row = $result->fetch_assoc()) {
                    
                    $fecha_inicio = DateTime::createFromFormat('Y-m-d', $row['fecha_inicio'])->format('d/m/Y');
                    $fecha_fin = DateTime::createFromFormat('Y-m-d', $row['fecha_fin'])->format('d/m/Y');
                    $fecha_solicitud = DateTime::createFromFormat('Y-m-d H:i:s', $row['fecha_solicitud'])->format('d/m/Y');
        
                    if ($row['jefe_directo'] == $_SESSION['usuario_id'] ||  // Jefe directo puede verlas
                        $_SESSION['usuario_id'] == 5 && $row['jefe_directo'] == 1082){
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
                                echo "<td>" . htmlspecialchars("Pagada") . "</td>";
                            }
                            if ($row['estatus'] == 'pendiente') {
                                echo "<td>
                            <form method='POST' action='VAC_controller/aprobar_vacaciones.php'>
                                <input type='hidden' name='solicitud_id' value='" . $row['solicitud_id'] . "'>
                                <input type='hidden' name='usuario_id' value='" . $row['usuario_id'] . "'>
                                <input type='hidden' name='nombre_solicitante' value= '" . $row['nombre'] . "'>
                                <input type='hidden' name='fecha_inicio' value='" . $row['fecha_inicio'] . "'>  
                                <input type='hidden' name='fecha_fin' value='" . $row['fecha_fin'] . "'>        
                                <button type='submit' name='accion' value='aprobar' class='btn btn-info'>Aprobar</button>
                                <button type='button' class='btn btn-danger' onclick='showCancelForm(" . $row['solicitud_id'] . ")'>Rechazar</button>
                            </form>
                            <div id='cancel-form-" . $row['solicitud_id'] . "' style='display:none;'>
                                <form method='POST' action='VAC_controller/aprobar_vacaciones.php'>
                                    <input type='hidden' name='solicitud_id' value='" . $row['solicitud_id'] . "'>
                                    <input type='hidden' name='usuario_id' value='" . $row['usuario_id'] . "'>
                                    <textarea name='razon_cancelacion' placeholder='Razón de rechazo' required></textarea>
                                    <button type='submit' name='accion' value='rechazar' class='btn btn-danger'>Confirmar Rechazo</button>
                                </form>
                            </div>
                        </td>";
                            } else {
                                
                                echo "<td>N/A</td>";
                            }
                            echo "</tr>";
                            
                        }
                    }
                }
                echo "</table>";
            } else {
                echo "<p>No hay solicitudes pendientes de autorización.</p>";
            }

            $stmt->close();
        ?>
        <!-- <div class="footer">
           <© 2024 DRG Services & Solutions - Todos los derechos reservados
    </div>-->



</form>



<script>
    function showCancelForm(id) {
        document.getElementById('cancel-form-' + id).style.display = 'block';
    }
</script>
<?php 
require("src/templates/adminfooter.php");
?>