<?php
session_start(); // Asegurarse de que la sesión está iniciada al principio
include("../../config/db.php"); // Incluir la configuración de la base de datos

?>
<!DOCTYPE html>
<html lang="es">

<head>
    <title>Transferencia Electronica</title>
    <!-- Required meta tags -->
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />

    <!-- Bootstrap CSS v5.2.1 -->
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://maxcdn.bootstrapcdn.com/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-T3c6CoIi6uLrA9TneNEoa7RxnatzjcDSCmG1MXxSR1GAsXEV/Dwwykc2MPK8M2HN" crossorigin="anonymous" />
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css" integrity="sha384-ggOyR0iXCbMQv3Xipma34MD+dH/1fQ784/j6cY/iJTQUOhcWr7x9JvoRxT2MZw1T" crossorigin="anonymous">
    <style>
        .search {
            max-width: 400px;
        }
        #beneficiario{
            max-width: 370px;
            height: 40px;
        }
        #sucursal{
            max-width: 370px;
        }
        #categoria{
            max-width: 240px;
            height: 40px;
        }
        #departamento{
            height: 40px;
        }
        #autorizar{
            height: 40px;
            width: 400px;
        }
        .logo {
            position: absolute;
            top: 20px;
            right: 100px;
            max-width: 300px;
            height: 250px;
            width: 250px;
        }
        .mis-solis{
            font-size: 20px;
        }
    </style>
</head>

<body>
    <header>
        <!-- Barra de navegacion -->
        <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
            <a class="navbar-brand" href="inicio.php">
                <img src="../img/logo-drg.png" alt="Logo" width="200" height="90">
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav">
                    <?php if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) : ?>
                        <li class="nav-item">
                            <a class="nav-link" href="inicio.php">
                                Usuario: <?php echo htmlspecialchars($_SESSION['nombre']); ?>
                               <!-- Id: <?php echo htmlspecialchars($_SESSION['usuario_id']); ?>-->
                            </a>

                          

                        </li>
                        <li>
                            <a class="nav-link" href="transferencias.php">Nueva Solicitud</a>
                            

                        <?php if ($_SESSION['rol'] === 'usuario') : ?>
                            <li class="nav-item">
                                <a class="nav-link mis-soli" href="mis_transferencias_usuario.php">Mis solicitudes</a>
                            </li>
                        <?php endif; ?>

                        <?php if ($_SESSION['departamento'] === 'Almacen') : ?>
                            <li class="nav-item">
                            <a class="nav-link mis-soli" href="mis_transferencias_almacen.php">Solicitudes Almacen</a>
                            </li>
                        <?php endif; ?>

                        
                        <?php if ($_SESSION['rol'] === 'autorizador' || $_SESSION['rol'] === 'cuentas') : ?>
                            <li class="nav-item">
                                <a class="nav-link" href="mis_transferencias.php">Pendiente por autorizar</a>
                            </li>
                            
                            <li>
                                <a class="nav-link" href="mis_transferencias_autorizacion.php">Mis Solicitudes</a>
                            </li>
                        <?php endif; ?>


                        <?php if ($_SESSION['rol'] === 'cuentas' || $_SESSION['nombre'] === 'Martha Salas' || $_SESSION['nombre'] === 'Martha Martinez') : ?>
                            <li class="nav-item">
                                <a class="nav-link" href="pagos.php">Pendiente de pago</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="t_pagadas.php">Transferencias Pagadas</a>
                            </li>
                        <?php endif; ?>
                        

                        <li class="nav-item">
                            <a class="nav-link" href="logout.php">Cerrar Sesión</a>
                        </li>
                    <?php else : ?>
                        <li class="nav-item">
                            <a class="nav-link" href="index.php">Iniciar Sesión</a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </nav>
    </header>
    <main>
    </main>
</body>

</html>
