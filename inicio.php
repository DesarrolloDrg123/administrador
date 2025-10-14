<?php
include("src/templates/adminheader.php");
require("config/db.php");
session_start();

if (!isset($_SESSION['nombre'])) {
    // Redirigir a la página principal si no hay sesión activa
    header("Location: index.php");  // Cambia "/index.php" por la URL de tu página principal
    exit();
}

$usuario = $_SESSION['nombre'];
$echo = $_SESSION['puesto'];

$sql = "SELECT id, titulo, descripcion, ruta_imagen FROM noticias_inicio WHERE id IN (1, 2, 3) ORDER BY id ASC";
$result = $conn->query($sql);

$noticias_data = [1 => [], 2 => [], 3 => []];

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $noticias_data[$row['id']] = $row;
    }
}



?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Página de Inicio</title>
    
    <style>
        body {
            /* Cambia 'img/mi_fondo.jpg' por la ruta a tu imagen de fondo */
            background-image: url('img/drg3.png');
            
            /* Hace que la imagen cubra toda la pantalla sin deformarse */
            background-size: cover; 
            
            /* Centra la imagen */
            background-position: center center; 
            
            /* Evita que la imagen se repita */
            background-repeat: no-repeat; 
            
            /* Fija la imagen para que el contenido se desplace sobre ella (efecto parallax) */
            background-attachment: fixed; 
            
            /* Asegura que el fondo se extienda a toda la altura */
            min-height: 100vh; 
            
            /* Un color de fondo de respaldo por si la imagen no carga */
            background-color: #f8f9fa;
        }

        /* Opcional: Un pequeño overlay oscuro para que el texto resalte más */
        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.1); /* Ajusta la opacidad (el último valor) */
            z-index: -1;
        }

        /* Estilos para las tarjetas para que se vean mejor sobre el fondo */
        .card {
            /* Efecto de "vidrio esmerilado" para mejorar la legibilidad */
            background-color: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(5px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        /* Nueva clase para controlar la altura de la imagen del card */
        .card-img-fixed-height {
            /* Define la altura que deseas. Puedes cambiar este valor (ej. 180px, 220px, etc.) */
            height: 250px; 
            object-fit: cover; 
        }
        .container {
            max-width: 80%;
        }
    </style>
</head>
<body>
    <div class="container my-5">
        <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">

            <?php foreach ($noticias_data as $noticia): ?>
                <?php
                // Nos aseguramos de que la noticia tenga título para mostrarla.
                // Si el título está vacío, no se crea la tarjeta.
                if (!empty($noticia['titulo'])):
                ?>
                    <div class="col">
                        <div class="card h-100 shadow-sm">
                            
                            <img src="<?php echo htmlspecialchars($noticia['ruta_imagen']); ?>" 
                                 class="card-img-top card-img-fixed-height" 
                                 alt="<?php echo htmlspecialchars($noticia['titulo']); ?>">
                            
                            <div class="card-body">
                                
                                <h5 class="card-title"><?php echo htmlspecialchars($noticia['titulo']); ?></h5>
                                
                                <div class="card-text">
                                    <?php echo $noticia['descripcion']; ?>
                                </div>

                            </div>
                            <div class="card-footer">
                                </div>
                        </div>
                    </div>
                <?php 
                endif; 
                endforeach; 
                ?>

        </div>
    </div>

    <?php
        // Tu footer se mantiene igual
        include("src/templates/adminfooter.php");
    ?>
</body>
</html>




