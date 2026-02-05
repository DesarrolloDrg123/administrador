<?php


session_start();


if (!isset($_SESSION['loggedin']) || !$_SESSION['loggedin']) {
    header("Location: index.php");
    exit();
}
/*
if (!isset($_SESSION['permisos'][1])) { //Permiso de Solicitar vacaciones
    header("Location: inicio.php");
    exit();
}*/

$usuario_id = $_SESSION['usuario_id'];
$nombreSolicitante = $_SESSION['nombre'];
$autoriza = $_SESSION['jefe'];
$puesto = $_SESSION['puesto'];


require("config/db.php");
include("src/templates/adminheader.php");
require('vendor/autoload.php');

try {
    // Consulta para obtener los días feriados desde la base de datos
    $sql = "SELECT fecha FROM dias_feriados";
    $result = $conn->query($sql);

    // Crear un array de días feriados en el formato `d/m/Y`
    $dias_feriados = [];
    while ($row = $result->fetch_assoc()) {
        $dias_feriados[] = DateTime::createFromFormat('Y-m-d', $row['fecha'])->format('d/m/Y');
    }
    // Convertir el array a JSON para usarlo en JavaScript
    $json_dias_feriados = json_encode($dias_feriados);
} catch (Exception $e) {
    error_log("Error al obtener los días feriados: " . $e->getMessage());
    $json_dias_feriados = json_encode([]);
}




?>


<br>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Solicitud de Vacaciones</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f8f9fa;
            margin: 0;
            padding: 0;
        }
        .jb1 {
            background-color: #22282e;
            color: white;
            width: 1000px;
        }
        p {
            font-size: 30px;
        }
        .btn-request {
            background-color: white;
            color: #22282e;
            padding: 10px 30px;
            border: 2px solid #ffffff;
            border-radius: 25px;
            font-size: 16px;
            cursor: pointer;
            transition: background-color 0.3s ease, color 0.3s ease;
        }
        .btn-request:hover {
            background-color: #343a40;
            color: white;
        }
        .form-group {
            margin: 20px 0;
            text-align: center;
        }
        .date-container {
            display: flex;
            justify-content: space-between;
        }
        .date-container .form-group {
            flex: 1;
            margin-right: 30px;
        }
        .date-container .form-group:last-child {
            margin-right: 0;
        }
        input[type="text"] {
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 5px;
            font-size: 16px;
            width: 100%;
        }
        
    .jb1 {
        background-color: #22282e;
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
        background-color: #343a40;
        color: white;
    }
    </style>
</head>

<div class="container">
    <div class="jumbotron jb1">
      
        <h1><?php
            echo $_SESSION['departamento'];
        ?></h1>
        <p>Realiza tu Solicitud de Vacaciones</p>
    
        <div>
            <style>
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
                    width: 400px;
                    height: 120px;
                    display: flex;
                    flex-direction: column;
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
                    background-color: #343a40;
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

            <body>
                <form action="VAC_controller/procesar_vacaciones.php" method="post">

                    <div class="container jumbotron mt-5" style="background-color:#343a40; color: white;">
                        <!-- Flex container for layout -->
                        <div class="content">
                            <!-- Left box for Bienvenido and Antigüedad -->
                            <div>
                                <div class="left-box">
                                    <p style="font-size:20px; margin-bottom: 1px;"><strong>Bienvenido(a): <br></strong><?php echo htmlspecialchars($_SESSION['nombre']); ?></p>

                                    <?php
                                    $antiguedad_completa_años = $_SESSION['antiguedad_años'];
                                    $antiguedad_completa_dias = $_SESSION['antiguedad_dias'];

                                    $antiguedad_formateada_dias = number_format($antiguedad_completa_dias / 365, 2);

                                    ?>
                                    <p style="font-size:20px; font-weight:bold;">Antigüedad: <?php echo htmlspecialchars($antiguedad_formateada_dias) ?> Años</p>




                                </div>
                                <a href="inicio.php"><p style="width:fit-content; position:relative; left:80px;" class="btn-request">Volver al Menú Anterior</p></a>
                            </div>


                            <!-- Right box for Verificar Solicitudes and Nueva Solicitud -->
                            <div class="right-box">
                                <p>Dias Restantes:<span>
                                        <?php
                                        try {
                                            $sql = "SELECT * FROM periodos WHERE usuario_id = ?";
                                            $stmt = $conn->prepare($sql);

                                            if (!$stmt) {
                                                throw new Exception("Error al preparar la consulta: " . $conn->error);
                                            }

                                            $stmt->bind_param("i", $usuario_id);  // 'i' indica que es un valor entero

                                            $stmt->execute();
                                            $result = $stmt->get_result();
                                        } catch (Exception $e) {
                                            error_log("Error al obtener los periodos: " . $e->getMessage());
                                            echo "Ocurrió un error al obtener los periodos del usuario.";
                                        }
                                        $total_dias_restantes = 0;

                                        if ($result->num_rows > 0) {
                                            while ($row = $result->fetch_assoc())

                                            $total_dias_restantes += $row['dias_restantes'];
                                            echo htmlspecialchars($total_dias_restantes);
                                        } else {
                                            echo htmlspecialchars("No cuentas con dias restantes");
                                        }

                                        ?>
                                    </span></p>


                                    <p>
                                        <?php
                                        try{
                                            $sql_periodos1 = "SELECT * FROM periodos WHERE usuario_id = ? AND dias_restantes > 0 ORDER BY vigencia ASC LIMIT 1";
                                            $stmt1 = $conn->prepare($sql_periodos1);
                                            if(!$stmt1){
                                                throw new Exception("Error al preparar consulta: " . $conn->error);
                                            }
                                            $stmt1->bind_param("i", $usuario_id);

                                            $stmt1->execute();
                                            $result_periodos1 = $stmt1->get_result();
                                        }
                                        catch (Exception $e){
                                            error_log("Error al obtener los periodos: " . $e->getMessage());
                                            echo "Ocurrio un error al obtener los periodos del usuario";
                                        }

                                        if ($result_periodos1->num_rows > 0) {
                                            // Extraemos el primer resultado
                                            $row_periodo = $result_periodos1->fetch_assoc();
                                            
                                            if ($row_periodo) {
                                                // Formateamos la fecha de vigencia
                                                $vigencia = $row_periodo['vigencia']; // Fecha original de la base de datos
                                                $date = new DateTime($vigencia); // Crear un objeto DateTime desde la fecha
                                                $vigencia_formateada = $date->format('d/m/Y'); // Formatear la fecha a 'd/m/Y'
                                        
                                                // Mostramos los días restantes
                                                $total_dias_restantes = $row_periodo['dias_restantes']; // Obtenemos días restantes
                                                echo "Tienes : " . htmlspecialchars($total_dias_restantes) . " dias " . "que vencen en: " . htmlspecialchars($vigencia_formateada) . "<br>";
                                            }
                                        } else {
                                            echo "No se encontraron periodos con días restantes para este usuario.";
                                        }

                                        
                                        
                                        ?>
                                    </p>
                                    
                                    <?php 
                                    if ($result->num_rows > 0) {
                                    ?> 
                                    <div class="line"> </div>
                                    <p><strong>Realizar Nueva Solicitud</strong></p>
                                        <div class="date-container">
                                            <div class="form-group">
                                                <label for="fecha-inicio">Fecha de inicio</label>
                                                <input type="text" id="fecha-inicio" name="fecha_inicio">
                                            </div>
                                            <div class="form-group">
                                                <label for="fecha-fin">Fecha final</label>
                                                <input type="text" id="fecha-fin" name="fecha_fin">
                                            </div>
                                        </div>
                                        
                                        <div class="text-center my-3" style="color: white; font-size: 1.2rem;">
                                            <p>Días hábiles solicitados: <strong id="contador-dias" style="font-size: 1.5rem;">0</strong></p>
                                        </div>
                                        
                                        <button type="submit" class="btn-request">Realizar Solicitud</button>
                                    
                                    <?php    
                                    }
                                    ?> 
                            </div>
                        </div>
                    </div>
                </form>
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/es.js"></script>
<script>
document.addEventListener("DOMContentLoaded", function () {
    // 1. Elementos del DOM y variables
    const diasFeriadosMexico = <?php echo $json_dias_feriados; ?>;
    const inputFechaInicio = document.getElementById('fecha-inicio');
    const inputFechaFin = document.getElementById('fecha-fin');
    const contadorDiasEl = document.getElementById('contador-dias');

    // Declaramos las variables de los calendarios para poder acceder a ellas
    let fp_inicio, fp_fin;

    // 2. Función que cuenta los días hábiles (la misma de antes, es correcta)
    function calcularDiasHabiles(fechaInicioStr, fechaFinStr) {
        if (!fechaInicioStr || !fechaFinStr) {
            return 0;
        }

        // Flatpickr nos da las fechas en formato d/m/Y, pero el new Date() de JS
        // a veces tiene problemas con ese formato. Lo convertimos a Y-m-d.
        const [d1, m1, y1] = fechaInicioStr.split('/');
        const [d2, m2, y2] = fechaFinStr.split('/');
        const fechaInicio = new Date(`${y1}-${m1}-${d1}T00:00:00`);
        const fechaFin = new Date(`${y2}-${m2}-${d2}T00:00:00`);

        if (fechaFin < fechaInicio) return 0;

        let count = 0;
        let current = new Date(fechaInicio.getTime());

        while (current <= fechaFin) {
            const diaSemana = current.getDay(); // 0=Domingo, 6=Sábado
            const fechaFormateada = 
                ('0' + current.getDate()).slice(-2) + '/' + 
                ('0' + (current.getMonth() + 1)).slice(-2) + '/' + 
                current.getFullYear();
            
            if (diaSemana !== 0 && diaSemana !== 6 && !diasFeriadosMexico.includes(fechaFormateada)) {
                count++;
            }
            current.setDate(current.getDate() + 1);
        }
        return count;
    }

    // 3. Función para actualizar el contador en la pantalla
    function actualizarConteo() {
        const dias = calcularDiasHabiles(inputFechaInicio.value, inputFechaFin.value);
        contadorDiasEl.textContent = dias;
    }

    // 4. Configuración base para ambos calendarios
    const opcionesBase = {
        dateFormat: "d/m/Y",
        minDate: "today",
        locale: "es",
        disable: [
            function (date) {
                return date.getDay() === 0 || date.getDay() === 6;
            },
            ...diasFeriadosMexico
        ]
    };

    // 5. Inicialización de los calendarios CONECTADOS
    fp_inicio = flatpickr(inputFechaInicio, {
        ...opcionesBase,
        onChange: function(selectedDates, dateStr, instance) {
            // Cuando se elige una fecha de inicio...
            if (selectedDates.length > 0) {
                // ...configuramos la fecha mínima del calendario de fin.
                fp_fin.set('minDate', selectedDates[0]);
            }
            // Actualizamos el conteo cada vez que cambia
            actualizarConteo();
        }
    });

    fp_fin = flatpickr(inputFechaFin, {
        ...opcionesBase,
        onChange: function(selectedDates, dateStr, instance) {
            // Actualizamos el conteo cada vez que cambia
            actualizarConteo();
        }
    });
});
</script>

                




            </body>

            </html>

                   


        </div>

        <div id="status" class="collapse">
            <br>
            <h2>Estatus de vacaciones</h2>

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
                    $fecha_solicitud = DateTime::createFromFormat('Y-m-d H:i:s', $row['fecha_solicitud'])->format('d/m/Y H:i:s');

                    echo "<tr>";
                    echo "<td>" . htmlspecialchars($fecha_inicio) . "</td>";
                    echo "<td>" . htmlspecialchars($fecha_fin) . "</td>";
                    if ($row['estatus'] === 'pendiente') {
                        echo "<td>" . htmlspecialchars('Pendiente de Autorizacion') . "</td>";
                    } elseif ($row['estatus'] === 'aprobada') {
                        echo "<td>" . htmlspecialchars('Autorizada, Disfruta tus Vacaciones') . "</td>";
                    } elseif ($row['estatus'] === 'rechazada') {
                        echo "<td>" . htmlspecialchars('Rechazada, ') . $row['razon_cancelacion'] . "</td>";
                    }
                    echo "<td>" . htmlspecialchars($fecha_solicitud) . "</td>";
                    echo "</tr>";
                }

                echo "</table>";
            } else {
                echo "<p>No has realizado ninguna solicitud de vacaciones.</p>";
            }

            // Cerrar la conexión
            $stmt->close();
            ?>

       

            <br>
        </div>
        <div class="footer">
            © 2024 DRG Services & Solutions - Todos los derechos reservados
        </div>
    </div>

</div>


</div>


</div>

<?php include("src/templates/footer.php"); ?>