<?php
// Configuración de la base de datos
$servername = "localhost:3306";
$username = "intran23_root";
$password = "#JKhoI88+xXR";
$dbname ="intran23_copia";

$conn = new mysqli($servername, $username, $password, $dbname);
$conn->set_charset("utf8mb4"); 
if ($conn->connect_error) {
    die("Error de Conexion". $conn->connect_error);
}


?>