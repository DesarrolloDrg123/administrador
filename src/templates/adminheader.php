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
        body {
            background-color: #EEF1F2;
            margin-left: 260px;
            transition: all 0.3s ease;
        }

        header#main-sidebar {
            width: 260px;
            height: 100vh;
            position: fixed;
            left: 0;
            top: 0;
            background-color: #2c343b; /* Color oscuro profesional */
            display: flex;
            flex-direction: column; /* Alineación vertical */
            z-index: 1050;
            transition: all 0.3s ease;
        }

        /* --- ZONA SUPERIOR --- */
        .sidebar-header-top {
            padding: 20px;
            text-align: center;
            position: relative;
            border-bottom: 1px solid #3e474f;
        }
        #sidebar-logo { width: 150px; transition: 0.3s; }

        #btn-toggle-sidebar {
            position: absolute;
            right: -15px;
            top: 25px;
            background: #3498db;
            border: none;
            color: white;
            width: 30px;
            height: 30px;
            border-radius: 5px;
            cursor: pointer;
            z-index: 1100;
        }

        /* --- ZONA CENTRAL (SCROLL) --- */
        .sidebar-center {
            flex-grow: 1; /* Ocupa el espacio restante */
            overflow-y: auto; /* Solo esta parte tiene scroll */
            padding: 10px 0;
        }
        .sidebar-center::-webkit-scrollbar { width: 5px; }
        .sidebar-center::-webkit-scrollbar-thumb { background: #4b545c; }

        /* --- DROPDOWNS (FIX PARA QUE NO SE ENCIMEN) --- */
        header .nav-item .dropdown-menu {
            position: static !important; /* IMPORTANTE: Empuja el contenido abajo */
            float: none !important;
            background-color: #1e2429 !important;
            border: none;
            padding: 0;
            margin: 0;
            transform: none !important;
            display: none;
        }
        header .nav-item .dropdown-menu.show { display: block; }
        
        header .dropdown-item {
            color: #adb5bd !important;
            padding: 10px 10px 10px 45px !important;
            white-space: normal;
        }

        /* --- ZONA INFERIOR (USUARIO FIJO) --- */
        .sidebar-footer-bottom {
            padding: 15px 0;
            border-top: 1px solid #3e474f;
            background-color: #2c343b;
        }

        .nav-link {
            color: #d1d4d7 !important;
            padding: 12px 20px !important;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        .nav-link i { width: 20px; text-align: center; font-size: 1.1rem; }

        /* --- ESTADO COLAPSADO --- */
        body.sidebar-collapsed { margin-left: 70px; }
        header.sidebar-collapsed { width: 70px; }
        
        header.sidebar-collapsed .nav-text, 
        header.sidebar-collapsed #sidebar-logo,
        header.sidebar-collapsed .dropdown-toggle::after {
            display: none !important;
        }

        header.sidebar-collapsed .nav-link { justify-content: center; padding: 15px 0 !important; }
        header.sidebar-collapsed .nav-link i { margin: 0; }
        
        /* Evitar que se abran dropdowns en modo colapsado para que no se rompa */
        header.sidebar-collapsed .dropdown-menu { display: none !important; }

        @media (max-width: 991px) {
            body { margin-left: 0; }
            header { transform: translateX(-100%); }
            header.mobile-open { transform: translateX(0); }
        }
    </style>
</head>

<body>
    <header id="main-sidebar">
        <div class="sidebar-header-top">
            <a class="navbar-brand" href="inicio.php">
                <img src="../img/logo-drg.png" alt="Logo" id="sidebar-logo">
            </a>
            <button id="btn-toggle-sidebar">
                <i class="fas fa-bars" id="toggle-icon"></i>
            </button>
        </div>

        <div class="sidebar-center">
            <nav class="navbar">
                <ul class="navbar-nav">
                    <li class="nav-item">
                        <a class="nav-link" href="inicio.php">
                            <i class="fas fa-home"></i>
                            <span class="nav-text">Inicio</span>
                        </a>
                    </li>

                    <?php if (!empty($_SESSION['permisos'])) : ?>
                        <?php foreach ($_SESSION['permisos'] as $categoria => $programas) : ?>
                            <li class="nav-item dropdown">
                                <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
                                    <i class="fas fa-folder"></i>
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
            </nav>
        </div>

        <div class="sidebar-footer-bottom">
            <ul class="navbar-nav">
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
                        <i class="fa fa-user"></i>
                        <span class="nav-text text-truncate"><?php echo $_SESSION['nombre']; ?></span>
                    </a>
                    <div class="dropdown-menu dropup-custom">
                        <a class="dropdown-item" href="logout.php">
                            <i class="fas fa-sign-out-alt"></i> Cerrar Sesión
                        </a>
                    </div>
                </li>
            </ul>
        </div>
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

    btnToggle.addEventListener('click', () => {
        const isCollapsed = sidebar.classList.toggle('sidebar-collapsed');
        body.classList.toggle('sidebar-collapsed');
        
        // Cambiar icono: Si está colapsado dejamos las 3 barras, si no, podemos poner flecha o cruz
        if (isCollapsed) {
            icon.classList.replace('fa-bars', 'fa-bars'); // Se mantiene barras
            localStorage.setItem('sidebarStatus', 'closed');
        } else {
            icon.classList.replace('fa-bars', 'fa-bars');
            localStorage.setItem('sidebarStatus', 'open');
        }
    });

    // Cargar estado guardado
    if (localStorage.getItem('sidebarStatus') === 'closed') {
        sidebar.classList.add('sidebar-collapsed');
        body.classList.add('sidebar-collapsed');
    }
</script>