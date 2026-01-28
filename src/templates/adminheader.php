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
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css" integrity="sha384-xOolHFLEh07PJGoPkLv1IbcEPTNtaed2xpHsD9ESMhqIYd0nLMwNLD69Npy4HI+N" crossorigin="anonymous">
    <script src="https://cdn.jsdelivr.net/npm/jquery@3.5.1/dist/jquery.slim.min.js" integrity="sha384-DfXdz2htPH0lsSSs5nCTpuj/zy4C+OGpamoFVy38MVBnE+IbbVYUew+OrCXaRkfj" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-Fy6S3B9q64WdZWQUiU+q4/2Lc9npb8tCaSX9FK7E8HnRr0Jz8D6OP9dO5Vg3Q9ct" crossorigin="anonymous"></script>
  
    
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
        /* 1. Ajustes Base del Body */
        body {
            background-color: #EEF1F2;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin-left: 260px; /* Ancho por defecto del sidebar */
            transition: all 0.3s ease-in-out;
        }

        /* 2. Configuración del Sidebar */
        header {
            width: 260px;
            height: 100vh;
            position: fixed;
            top: 0;
            left: 0;
            background-color: #333;
            border-right: 3px solid #3498db;
            z-index: 1050; /* Por encima de todo */
            overflow-y: auto;
            overflow-x: hidden;
            transition: all 0.3s ease-in-out;
        }

        /* 3. Navbar Vertical */
        .navbar {
            flex-direction: column !important;
            align-items: flex-start !important;
            padding: 15px 0 !important;
        }

        .navbar-brand {
            padding: 10px;
            margin-bottom: 20px;
            width: 100%;
            text-align: center;
        }

        .navbar-nav {
            width: 100%;
            flex-direction: column !important;
        }

        .nav-item {
            width: 100%;
            position: relative;
        }

        .nav-link {
            padding: 12px 20px !important;
            color: #fff !important;
            display: flex;
            align-items: center;
            transition: background 0.2s;
        }

        .nav-link:hover {
            background-color: #444;
            color: #3498db !important;
        }

        /* 4. FIX DE DROPDOWNS (Para que no se encimen) */
        header .dropdown-menu {
            position: static !important; /* IMPORTANTE: Esto hace que empuje hacia abajo */
            float: none !important;
            transform: none !important; /* Anula el cálculo de Bootstrap */
            background-color: #222 !important; /* Color más oscuro para distinguir el submenú */
            border: none;
            width: 100%;
            margin: 0;
            padding: 0;
            box-shadow: none;
            display: none; /* Se controla con la clase .show de Bootstrap */
        }

        header .dropdown-menu.show {
            display: block;
        }

        header .dropdown-item {
            color: #bbb !important;
            padding: 10px 10px 10px 50px !important; /* Sangría para el submenú */
            font-size: 0.9rem;
        }

        header .dropdown-item:hover {
            background-color: #3498db !important;
            color: white !important;
        }

        /* 5. Estado Colapsado */
        body.sidebar-collapsed {
            margin-left: 70px;
        }

        header.sidebar-collapsed {
            width: 70px;
        }

        header.sidebar-collapsed .nav-text,
        header.sidebar-collapsed .navbar-brand span,
        header.sidebar-collapsed .dropdown-toggle::after {
            display: none !important;
        }

        header.sidebar-collapsed .navbar-brand img {
            width: 45px !important;
        }

        header.sidebar-collapsed .nav-link {
            justify-content: center;
            padding: 15px 0 !important;
        }

        header.sidebar-collapsed .nav-link i {
            margin: 0 !important;
            font-size: 1.3rem;
        }

        /* Ocultar dropdowns abiertos al colapsar para evitar errores visuales */
        header.sidebar-collapsed .dropdown-menu {
            display: none !important;
        }

        /* 6. Botón de Toggle */
        #btn-toggle-sidebar {
            background: #3498db;
            border: none;
            color: white;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            cursor: pointer;
            position: absolute;
            right: -15px;
            top: 25px;
            z-index: 1100;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 2px 0 5px rgba(0,0,0,0.2);
        }

        /* 7. Utilidades para las tablas y contenedores */
        .container-fluid {
            padding: 20px;
        }

        /* Quitar flecha de dropdown si se desea */
        .dropdown-toggle::after {
            margin-left: auto;
        }
    </style>
</head>

<body>
    <header id="main-sidebar">
    <button id="btn-toggle-sidebar">
        <i class="fas fa-chevron-left" id="toggle-icon"></i>
    </button>

    <nav class="navbar navbar-dark bg-dark">
        <a class="navbar-brand text-center" href="inicio.php">
            <img src="../img/logo-drg.png" alt="Logo" id="sidebar-logo" style="width: 150px;">
        </a>

        <div class="collapse navbar-collapse show">
            <ul class="navbar-nav mb-4">
                <li class="nav-item">
                    <a class="nav-link" href="inicio.php">
                        <i class="fas fa-home me-2"></i>
                        <span class="nav-text">Inicio</span>
                    </a>
                </li>

                <?php if (!empty($_SESSION['permisos'])) : ?>
                    <?php foreach ($_SESSION['permisos'] as $categoria => $programas) : ?>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" data-bs-toggle="dropdown">
                                <i class="fas fa-folder me-2"></i>
                                <span class="nav-text text-truncate"><?php echo $categoria; ?></span>
                            </a>
                            <ul class="dropdown-menu">
                                <?php foreach ($programas as $permiso) : ?>
                                    <li><a class="dropdown-item" href="<?php echo $permiso['url']; ?>"><?php echo $permiso['nombre_programa']; ?></a></li>
                                <?php endforeach; ?>
                            </ul>
                        </li>
                    <?php endforeach; ?>
                <?php endif; ?>
            </ul>

            <ul class="navbar-nav mt-auto border-top pt-3">
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" data-bs-toggle="dropdown">
                        <i class="fa fa-user me-2"></i>
                        <span class="nav-text"><?php echo $_SESSION['nombre']; ?></span>
                    </a>
                    <div class="dropdown-menu">
                        <a class="dropdown-item" href="logout.php"><i class="fas fa-sign-out-alt"></i> Salir</a>
                    </div>
                </li>
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
<script>
    const btnToggle = document.getElementById('btn-toggle-sidebar');
    const sidebar = document.getElementById('main-sidebar');
    const body = document.body;
    const icon = document.getElementById('toggle-icon');

    // Al hacer clic, alternar clases
    btnToggle.addEventListener('click', () => {
        sidebar.classList.toggle('sidebar-collapsed');
        body.classList.toggle('sidebar-collapsed');
        
        // Cambiar el icono de la flecha
        if (sidebar.classList.contains('sidebar-collapsed')) {
            icon.classList.replace('fa-chevron-left', 'fa-chevron-right');
            localStorage.setItem('sidebarStatus', 'closed');
        } else {
            icon.classList.replace('fa-chevron-right', 'fa-chevron-left');
            localStorage.setItem('sidebarStatus', 'open');
        }
    });

    // Mantener el estado al recargar la página
    if (localStorage.getItem('sidebarStatus') === 'closed') {
        sidebar.classList.add('sidebar-collapsed');
        body.classList.add('sidebar-collapsed');
        icon.classList.replace('fa-chevron-left', 'fa-chevron-right');
    }
</script>