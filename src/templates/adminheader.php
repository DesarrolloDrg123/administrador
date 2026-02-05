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
    1. ESTRUCTURA BASE
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
        flex-shrink: 0;
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
    2. NAVEGACIÓN (FLUJO ÚNICO)
    ========================================= */
    .sidebar-center { 
        flex: 1; 
        overflow-y: auto; 
        overflow-x: visible; /* Permitir que los menús floten fuera */
    }

    .nav-link {
        color: #d1d4d7 !important;
        padding: 12px 20px !important;
        display: flex;
        align-items: center;
        transition: all 0.2s;
    }

    .nav-link i {
        width: 30px;
        text-align: center;
        font-size: 1.1rem;
        margin-right: 15px;
    }

    /* Línea divisoria para el usuario */
    .sidebar-divider {
        border-top: 1px solid rgba(255,255,255,0.1);
        margin: 10px 20px;
    }

    /* --- ESTADO COLAPSADO --- */
    header#main-sidebar.sidebar-collapsed { width: 70px; }
    body.sidebar-collapsed { margin-left: 70px; }

    header#main-sidebar.sidebar-collapsed .nav-text,
    header#main-sidebar.sidebar-collapsed #sidebar-logo,
    header#main-sidebar.sidebar-collapsed .dropdown-toggle::after,
    header#main-sidebar.sidebar-collapsed .sidebar-divider {
        display: none !important;
    }

    header#main-sidebar.sidebar-collapsed .nav-link {
        justify-content: center !important;
        padding: 15px 0 !important;
    }

    header#main-sidebar.sidebar-collapsed .nav-link i { margin-right: 0 !important; }

    /* =========================================
    3. SUBMENÚS (CARPETAS Y USUARIO)
    ========================================= */
    
    /* CUANDO ESTÁ ABIERTO: Menús normales hacia abajo */
    .nav-item.dropdown .dropdown-menu {
        position: static !important; /* Lo mete dentro del flujo para no tapar nada */
        float: none;
        background-color: #22282e !important;
        border: none;
        padding: 0;
        margin: 0;
        box-shadow: none;
        width: 100%;
    }

    .nav-item.dropdown .dropdown-item {
        color: #adb5bd !important;
        padding: 10px 20px 10px 65px !important; /* Sangría para que se vea dentro */
        font-size: 0.9rem;
    }

    /* CUANDO ESTÁ COLAPSADO: Menús flotantes a la DERECHA */
    header#main-sidebar.sidebar-collapsed .nav-item.dropdown .dropdown-menu {
        position: absolute !important;
        left: 70px !important;
        top: 0 !important;
        display: none;
        background-color: #2c343b !important;
        min-width: 200px;
        border-radius: 0 6px 6px 0;
        box-shadow: 5px 0 15px rgba(0,0,0,0.3);
        z-index: 2000;
    }

    header#main-sidebar.sidebar-collapsed .nav-item.dropdown:hover > .dropdown-menu {
        display: block !important;
    }

    header#main-sidebar.sidebar-collapsed .dropdown-item {
        padding: 12px 20px !important; /* Reset de sangría al estar colapsado */
    }

    /* Estilo especial botón Cerrar Sesión */
    .logout-link:hover {
        background-color: #dc3545 !important;
        color: white !important;
    }
    
    .logout-link i {
        margin-right: 10px;
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

                    <li class="nav-item dropdown user-section">
                        <hr class="sidebar-divider"> <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
                            <i class="fa fa-user"></i>
                            <span class="nav-text"><?php echo $_SESSION['nombre']; ?></span>
                        </a>
                        <ul class="dropdown-menu"> 
                            <li>
                                <a class="dropdown-item logout-link" href="logout.php">
                                    <i class="fas fa-sign-out-alt"></i> <span>Cerrar Sesión</span>
                                </a>
                            </li>
                        </ul>
                    </li>
                </ul>
            </nav>
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