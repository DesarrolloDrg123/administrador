<?php
$servidor = "localhost:3306";
$nombre = "intran23_root";
$contra = "Intranet12_";
$bdname = "intran23_proveedores_drg";

$conn2 = new mysqli($servidor, $nombre, $contra, $bdname);
if ($conn2->connect_error){
    die("Error de conexion: " . $conn2->connect_error);
}

?>