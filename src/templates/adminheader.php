<?php
ob_start();
session_start(); // Asegurarse de que la sesión está iniciada al principio
include("config/db.php"); // Incluir la configuración de la base de datos

if(isset($_SESSION["usuario"]) || $_SESSION['loggedin'] !== true){
    header("Location: login.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <title>DRG Administrador</title>
    <link rel="icon" href="../img/icon-drg.png" type="image/png">
    <!-- Required meta tags -->
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
    
    <!-- Incluir Font Awesome 5 -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
    
    <script src="https://cdn.tiny.cloud/1/ym6fo23cll1and4bzrx4y9be6ou3trb7ivc375dc95m7790n/tinymce/8/tinymce.min.js" referrerpolicy="origin" crossorigin="anonymous"></script>

    <link rel="stylesheet" href="../css/style.css">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-T3c6CoIi6uLrA9TneNEoa7RxnatzjcDSCmG1MXxSR1GAsXEV/Dwwykc2MPK8M2HN" crossorigin="anonymous" />
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css" integrity="sha384-ggOyR0iXCbMQv3Xipma34MD+dH/1fQ784/j6cY/iJTQUOhcWr7x9JvoRxT2MZw1T" crossorigin="anonymous">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- jQuery (necesario para DataTables) -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/jquery.dataTables.min.css">
    
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    
    <!-- DataTables JS -->
    <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
    
    <!-- DataTables Buttons CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.2.3/css/buttons.dataTables.min.css">
    
    <!-- DataTables Buttons JS -->
    <script type="text/javascript" charset="utf8" src="https://cdn.datatables.net/buttons/2.2.3/js/dataTables.buttons.min.js"></script>
    <script type="text/javascript" charset="utf8" src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.1.3/jszip.min.js"></script>
    <script type="text/javascript" charset="utf8" src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.36/pdfmake.min.js"></script>
    <script type="text/javascript" charset="utf8" src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.36/vfs_fonts.js"></script>
    <script type="text/javascript" charset="utf8" src="https://cdn.datatables.net/buttons/2.2.3/js/buttons.html5.min.js"></script>
    <script type="text/javascript" charset="utf8" src="https://cdn.datatables.net/buttons/2.2.3/js/buttons.print.min.js"></script>
    
    <!-- CDN de SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <script src="https://cdn.sheetjs.com/xlsx-latest/package/dist/xlsx.full.min.js"></script>
    
    <style>
        .search {
            max-width: 400px;
        }
        .logo {
            position: absolute;
            top: 20px;
            right: 100px;
            max-width: 300px;
            height: 250px;
            width: 250px;
        }
        #searchInput{
            max-width: 400px;
        }

        body {
        background-color: #EEF1F2;
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

    .navbar {
        background-color: #333;
        border-bottom: 2px solid #3498db;
    }

    .navbar .nav-link {
        color: #ffffff;
        margin-right: 15px;
    }

    .navbar .nav-link:hover {
        color: #3498db;
    }

    .container {
        max-width: 900px;
        margin-top: 50px;
    }

    .bg-light {
        background-color: #ffffff !important;
        border-radius: 10px;
        padding: 40px;
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
    }

    .btn-primary:hover {
        background-color: #2980b9;
        border-color: #2980b9;
    }

    .display-4 {
        font-size: 2.5rem;
        font-weight: 300;
        color: #333;
    }

    .text-secondary {
        color: #7f8c8d !important;
    }
    
    /*Fondo blanco para el buscador de todas las tablas*/
    .dataTables_wrapper .dataTables_filter input {
        background-color: white;
    }
    
    /*Fondo planco para el buscador de todas las tablas*/
    .dt-button .buttons-excel .buttons-html5 input {
        background-color: white;
    }
    .navbar-nav .dropdown-toggle::after {
        display: none !important;
    }
    </style>
</head>

<body>
    <header>
        <!-- Barra de navegacion -->
        <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
            <a class="navbar-brand" href="#">
                <img src="../img/logo-drg.png" alt="Logo" width="200" height="90">
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav">
                    <?php if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) : ?>
                            <li class="nav-item">
                                <a class="nav-link" href="inicio.php">Inicio</a>
                            </li>
            
                            <?php 
                            if (!empty($_SESSION['permisos']) && is_array($_SESSION['permisos'])) {
                                foreach ($_SESSION['permisos'] as $categoria => $programas) {
                                    ?>
                                    <li class="nav-item dropdown">
                                        <a class="nav-link dropdown-toggle" id="<?php echo htmlspecialchars($categoria); ?>Dropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                            <?php echo htmlspecialchars($categoria); ?>
                                        </a>
                                        <ul class="dropdown-menu" aria-labelledby="<?php echo htmlspecialchars($categoria); ?>Dropdown">
                                            <?php 
                                            foreach ($programas as $permiso) {
                                                echo '<li><a class="dropdown-item" href="' . htmlspecialchars($permiso['url']) . '">' . htmlspecialchars($permiso['nombre_programa']) . '</a></li>';
                                            }
                                            ?>
                                        </ul>
                                    </li>
                                    <?php
                                }
                            } else {
                                echo '<li><a class="nav-link" href="#">No tienes permisos</a></li>';
                            }
                            ?>
                    <?php endif; ?>
                </ul>
                <!-- Mueve el usuario al extremo derecho -->
                <ul class="navbar-nav ms-auto">
                        <!-- 🔔 Botón de Notificaciones -->
                        <!--
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="notificationsDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="fa fa-bell"></i> 
                                <span id="notificacionContador" class="badge bg-danger"></span>
                            </a>
                            <div class="dropdown-menu dropdown-menu-end" aria-labelledby="notificationsDropdown" id="notificacionesDropdownContent">
                                <p class="dropdown-item text-muted">Cargando...</p>
                            </div>
                        </li> 
                        -->
                    
                    <?php if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) : ?>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                <i class="fa fa-user"></i> <?php echo htmlspecialchars($_SESSION['nombre']); ?>
                            </a>
                            <div class="dropdown-menu" aria-labelledby="userDropdown">
                                <a class="dropdown-item" href="logout.php">
                                    <i class="fas fa-sign-out-alt"></i> Cerrar Sesión
                                </a>
                            </div>
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
<!-- Dropdown donde se cargar��n las notificaciones -->
<div class="dropdown-menu" id="notificacionesDropdownContent">
    <!-- Las notificaciones se cargar��n aqu�� -->
</div>

<!-- Modal para mostrar todas las notificaciones -->
<div class="modal fade" id="notificacionesModal" tabindex="-1" role="dialog" aria-labelledby="notificacionesModalLabel" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="notificacionesModalLabel">Todas las Notificaciones</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body" id="notificacionesModalContent">
        <!-- Aqu�� se cargar��n todas las notificaciones -->
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-danger" id="limpiarNotificacionesBtn">Limpiar Notificaciones</button>
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
      </div>
    </div>
  </div>
</div>
</body>