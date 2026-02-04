<?php
ob_start();
session_start(); 
include("config/db.php"); 

// Corrección de lógica de seguridad: redirigir si NO hay sesión
if(!isset($_SESSION["usuario"]) && (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true)){
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
    <script src="https://cdn.jsdelivr.net/npm/jquery@3.5.1/dist/jquery.slim.min.js" integrity="sha384-DfXdz2htPH0lsSSs5nCTpuj/zy4C+OGpamoFVy38MVBnE+IbbVYUew+OrCXaRkfj" crossorigin="anonymous"></script>
    
    
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
        /* =========================================
        1. ESTRUCTURA Y SIDEBAR
        ========================================= */
        body {
            background-color: #EEF1F2;
            margin-left: 260px;
            transition: margin-left 0.3s ease;
        }

        header#main-sidebar {
            width: 260px;
            height: 100vh;
            position: fixed;
            left: 0;
            top: 0;
            background-color: #2c343b;
            display: flex;
            flex-direction: column;
            z-index: 1050;
            transition: width 0.3s ease;
        }

        .sidebar-top {
            padding: 0 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            height: 70px;
            border-bottom: 1px solid rgba(255,255,255,0.05);
        }

        #sidebar-logo { max-height: 40px; width: auto; }

        #btn-toggle-sidebar {
            background: transparent;
            border: none;
            color: #aebecd;
            font-size: 1.4rem;
            cursor: pointer;
        }

        /* =========================================
        2. NAVEGACIÓN E ICONOS (SOLUCIÓN CENTRADO)
        ========================================= */
        .sidebar-center { flex: 1; overflow-y: auto; overflow-x: hidden; }

        .nav-link {
            color: #d1d4d7 !important;
            padding: 12px 20px !important;
            display: flex;
            align-items: center;
            transition: all 0.2s;
        }

        .nav-link i {
            width: 30px; /* Ancho fijo para centrado */
            text-align: center;
            font-size: 1.1rem;
            margin-right: 15px;
        }

        /* Estado Colapsado */
        header#main-sidebar.sidebar-collapsed { width: 70px; }
        body.sidebar-collapsed { margin-left: 70px; }

        header#main-sidebar.sidebar-collapsed .nav-text,
        header#main-sidebar.sidebar-collapsed #sidebar-logo,
        header#main-sidebar.sidebar-collapsed .dropdown-toggle::after {
            display: none !important;
        }

        /* Centrado de iconos al cerrar barra */
        header#main-sidebar.sidebar-collapsed .nav-link {
            justify-content: center !important;
            padding: 15px 0 !important;
        }

        header#main-sidebar.sidebar-collapsed .nav-link i {
            margin-right: 0 !important;
        }

        /* =========================================
        3. FOOTER Y CERRAR SESIÓN (SOLUCIÓN DROPUPS)
        ========================================= */
        .sidebar-footer {
            padding: 10px 0;
            background-color: #2c343b;
            border-top: 1px solid rgba(255,255,255,0.05);
        }

        .sidebar-footer .navbar-nav {
            width: 100%;
        }

        .sidebar-footer .nav-item {
            width: 100%;
        }

        .sidebar-footer .dropup .dropdown-menu {
            position: absolute !important;
            bottom: 100% !important;
            top: auto !important;
            left: 5px !important;
            margin-bottom: 10px;
            background-color: #22282e !important;
            border: 1px solid rgba(255,255,255,0.1);
            min-width: 200px;
        }

        .sidebar-footer .dropdown-item {
            color: #adb5bd !important;
            padding: 10px 15px !important;
        }

        .sidebar-footer .dropdown-item:hover {
            background-color: rgba(255,255,255,0.05);
            color: white !important;
        }

        /* Estilos para cuando el sidebar está colapsado */
        header#main-sidebar.sidebar-collapsed .sidebar-footer .nav-link {
            justify-content: center !important;
            padding: 15px 0 !important;
        }

        header#main-sidebar.sidebar-collapsed .sidebar-footer .nav-link i {
            margin-right: 0 !important;
        }

        header#main-sidebar.sidebar-collapsed .sidebar-footer .nav-text {
            display: none !important;
        }

        header#main-sidebar.sidebar-collapsed .sidebar-footer .dropdown-menu {
            position: absolute !important;
            bottom: 100% !important;
            top: auto !important;
            left: 75px !important;
            margin-bottom: 10px;
            background-color: #22282e !important;
            border: 1px solid rgba(255,255,255,0.1);
            min-width: 200px;
        }

        header#main-sidebar.sidebar-collapsed .nav-item.dropdown {
            position: relative;
        }

        header#main-sidebar.sidebar-collapsed .nav-item.dropdown .dropdown-menu {
            /* Reseteamos el comportamiento de Bootstrap */
            position: absolute !important;
            top: 0 !important;
            left: 100% !important; /* Lo saca justo a la derecha del sidebar */
            margin: 0 !important;
            transform: none !important;
            
            /* Diseño del panel flotante */
            background-color: #2c343b;
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 0 6px 6px 0;
            box-shadow: 5px 0 15px rgba(0,0,0,0.3);
            min-width: 200px;
            padding: 10px 0;
            display: none; /* Se controla por JS de Bootstrap o por Hover */
        }

        /* Opcional: Mostrar al pasar el mouse (más intuitivo cuando está cerrado) */
        header#main-sidebar.sidebar-collapsed .nav-item.dropdown:hover > .dropdown-menu {
            display: block !important;
        }

        /* Estilo de los items dentro del submenú flotante */
        header#main-sidebar.sidebar-collapsed .dropdown-menu .dropdown-item {
            color: #d1d4d7 !important;
            padding: 10px 20px !important;
            font-size: 0.9rem;
        }

        header#main-sidebar.sidebar-collapsed .dropdown-menu .dropdown-item:hover {
            background-color: rgba(255,255,255,0.05);
            color: #fff !important;
        }

        /* Ajuste para que el icono del folder no se vea desplazado */
        header#main-sidebar.sidebar-collapsed .dropdown-toggle {
            width: 100%;
            display: flex;
            justify-content: center;
        }
    </style>
</head>

<body>
    <header id="main-sidebar">
        <div class="sidebar-top">
            <div class="logo-container">
                <img src="../img/logo-drg.png" alt="Logo" id="sidebar-logo">
            </div>
            <button id="btn-toggle-sidebar"><i class="fas fa-bars"></i></button>
        </div>

        <div class="sidebar-center">
            <nav class="navbar p-0">
                <ul class="navbar-nav w-100">
                    <li class="nav-item">
                        <a class="nav-link" href="inicio.php">
                            <i class="fas fa-home"></i><span class="nav-text">Inicio</span>
                        </a>
                    </li>
                    <?php if (!empty($_SESSION['permisos'])) : ?>
                        <?php foreach ($_SESSION['permisos'] as $categoria => $programas) : ?>
                            <li class="nav-item dropdown">
                                <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
                                    <i class="fas fa-folder"></i><span class="nav-text"><?php echo $categoria; ?></span>
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

        <div class="sidebar-footer">
            <ul class="navbar-nav w-100">
                <li class="nav-item dropup"> 
                    <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
                        <i class="fa fa-user"></i>
                        <span class="nav-text"><?php echo $_SESSION['nombre']; ?></span>
                    </a>
                    <ul class="dropdown-menu"> 
                        <li>
                            <a class="dropdown-item" href="logout.php">
                                <i class="fas fa-sign-out-alt"></i> Cerrar Sesión
                            </a>
                        </li>
                    </ul>
                </li>
            </ul>
        </div>
    </header>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const btnToggle = document.getElementById('btn-toggle-sidebar');
            const sidebar = document.getElementById('main-sidebar');
            const body = document.body;

            btnToggle.addEventListener('click', () => {
                const isCollapsed = sidebar.classList.toggle('sidebar-collapsed');
                body.classList.toggle('sidebar-collapsed');
                localStorage.setItem('sidebarStatus', isCollapsed ? 'closed' : 'open');
            });

            if (localStorage.getItem('sidebarStatus') === 'closed') {
                sidebar.classList.add('sidebar-collapsed');
                body.classList.add('sidebar-collapsed');
            }
        });
    </script>
</body>
</html>