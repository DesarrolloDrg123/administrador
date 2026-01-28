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
        /* 1. Ajuste del Body para dar espacio al menú */
        body {
            background-color: #EEF1F2;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin-left: 260px; /* Debe coincidir con el ancho del header */
            transition: margin-left 0.3s;
        }

        /* 2. Configuración del Header como Barra Lateral */
        header {
            width: 260px;
            height: 100vh;
            position: fixed;
            top: 0;
            left: 0;
            background-color: #333;
            border-right: 3px solid #3498db; /* Cambiamos border-bottom por border-right */
            z-index: 1000;
            overflow-y: auto; /* Esto permite el "slider" o scroll si hay muchos permisos */
            overflow-x: hidden;
        }

        /* 3. Ajuste de la Navbar para que sea vertical */
        .navbar {
            flex-direction: column !important;
            align-items: flex-start !important;
            padding: 20px 10px !important;
            background-color: transparent !important; /* El fondo lo da el header */
            border-bottom: none !important;
        }

        .navbar-brand {
            margin-bottom: 30px;
            text-align: center;
            width: 100%;
        }

        .navbar-collapse {
            width: 100%;
        }

        .navbar-nav {
            flex-direction: column !important;
            width: 100%;
        }

        .nav-item {
            width: 100%;
            border-bottom: 1px solid #444; /* Separador sutil */
        }

        /* 4. Dropdowns estilo acordeón (empujan hacia abajo) */
        .dropdown-menu {
            position: static !important;
            float: none !important;
            background-color: #444 !important;
            border: none !important;
            width: 100%;
            margin: 0 !important;
            box-shadow: none !important;
        }

        .dropdown-item {
            color: #ddd !important;
            padding-left: 30px !important;
        }

        /* 5. Ajuste del contenedor principal de las vistas */
        .container {
            max-width: 95% !important; /* Más ancho ya que tenemos menos espacio horizontal */
            margin-top: 30px;
            margin-left: auto;
            margin-right: auto;
        }

        /* Scrollbar personalizado para el menú lateral */
        header::-webkit-scrollbar {
            width: 6px;
        }
        header::-webkit-scrollbar-thumb {
            background: #555;
            border-radius: 10px;
        }

        /* Responsivo: Si la pantalla es pequeña, vuelve a ser horizontal o se oculta */
        @media (max-width: 991px) {
            body { margin-left: 0; }
            header {
                width: 100%;
                height: auto;
                position: relative;
                border-right: none;
                border-bottom: 3px solid #3498db;
            }
        }
        /* Transiciones suaves para todo */
        body, header, .navbar-brand img, .nav-link span {
            transition: all 0.3s ease-in-out;
        }

        /* Estado Colapsado */
        body.sidebar-collapsed {
            margin-left: 70px;
        }

        header.sidebar-collapsed {
            width: 70px;
        }

        /* Ocultar texto y logo grande al colapsar */
        header.sidebar-collapsed .nav-text, 
        header.sidebar-collapsed .navbar-brand span,
        header.sidebar-collapsed .dropdown-toggle::after {
            display: none !important;
        }

        header.sidebar-collapsed .navbar-brand img {
            width: 40px !important;
        }

        /* Ajuste de iconos al colapsar */
        header.sidebar-collapsed .nav-link {
            text-align: center;
            padding-left: 0;
        }

        header.sidebar-collapsed .nav-link i {
            margin-right: 0 !important;
            font-size: 1.2rem;
        }

        /* Botón de toggle (Hamburgesa) */
        #btn-toggle-sidebar {
            background: #3498db;
            border: none;
            color: white;
            padding: 5px 10px;
            border-radius: 5px;
            cursor: pointer;
            position: absolute;
            right: -15px;
            top: 20px;
            z-index: 1001;
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
                            <a class="nav-link dropdown-toggle" data-bs-toggle="dropdown">
                                <i class="fas fa-folder me-2"></i>
                                <span class="nav-text"><?php echo $categoria; ?></span>
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