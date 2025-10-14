<?php
require("config/db.php");
include("src/templates/adminheader.php");
$usuario_id = $_SESSION['usuario_id'];

if (!isset($_SESSION['loggedin']) || !$_SESSION['loggedin']) {
    header("Location: index.php");
    exit();
}

?>


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

<style>
    .jb1 {
        background-color: #343a40;
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



<div class="container">
    <div class="jumbotron jb1">
        <h1>Departamento de <?php echo htmlspecialchars($_SESSION['departamento']) ?></h1>
        <p>Bienvenido(a): <?php echo htmlspecialchars($_SESSION['nombre']) ?></p>


        <div>
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
                echo "<tr><th>Fecha de Inicio</th><th>Fecha de Fin</th><th>Dias Solicitados</th><th>Estatus</th><th>Fecha de Solicitud</th></tr>";

                // Recorrer las solicitudes y mostrarlas en la tabla
                while ($row = $result->fetch_assoc()) {
                   
                    // Formatear las fechas a dd/mm/aaaa
                    $fecha_inicio = DateTime::createFromFormat('Y-m-d', $row['fecha_inicio'])->format('d/m/Y');
                    $fecha_fin = DateTime::createFromFormat('Y-m-d', $row['fecha_fin'])->format('d/m/Y');
                    $fecha_solicitud = DateTime::createFromFormat('Y-m-d H:i:s', $row['fecha_solicitud'])->format('d/m/Y H:i:s');
                    $dias_solicitados = $row['dias_solicitados'];

                    echo "<tr>";
                    echo "<td>" . htmlspecialchars($fecha_inicio) . "</td>";
                    echo "<td>" . htmlspecialchars($fecha_fin) . "</td>"; 
                    echo "<td>" . htmlspecialchars($dias_solicitados) . "</td>"; 
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
                    elseif($row['estatus']=== 'pagado'){
                        echo "<td>" . htmlspecialchars('Registrada en Nomina'). "</td>";
                    

                    }
                    echo "<td>" . htmlspecialchars($fecha_solicitud) . "</td>";
                    echo "</tr>";
                }

                echo "</table>";
            } else {
                echo "<p>No has realizado ninguna solicitud de vacaciones.</p>";
          
            }
            
            ?>
            <br>
        </div>
    </div>
</div>
<?php
$conn->close();
include("src/templates/adminfooter.php");
?>