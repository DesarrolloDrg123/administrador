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
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
    <link rel="icon" href="../img/icon-drg.png" type="image/png">
    
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" />
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

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

        /* Forzar que el menú de usuario suba */
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

        /* Evitar que el dropdown se abra de lado en modo cerrado */
        header#main-sidebar.sidebar-collapsed .sidebar-footer .dropdown-menu {
            left: 75px !important;
            bottom: 10px !important;
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