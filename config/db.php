<?php
// Configuración de la base de datos
$servername = "localhost:3306";
$username = "intran23_root";
$password = "Intranet12_";
$dbname ="intran23_administrador";

$conn = new mysqli($servername, $username, $password, $dbname);
$conn->set_charset("utf8mb4"); 
if ($conn->connect_error) {
    die("Error de Conexion". $conn->connect_error);
}

$servidor = "localhost:3306";
$nombre = "intran23_root";
$contra = "Intranet12_";
$bdname = "intran23_proveedores_drg";

$conn2 = new mysqli($servidor, $nombre, $contra, $bdname);
if ($conn2->connect_error){
    die("Error de conexion: " . $conn2->connect_error);
}

?>