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
        1. ESTRUCTURA PRINCIPAL
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
            box-shadow: 2px 0 5px rgba(0,0,0,0.1); /* Sombra sutil a la derecha */
        }

        /* =========================================
        2. CABECERA (LOGO Y BOTÓN) - DISEÑO NUEVO
        ========================================= */
        .sidebar-top {
            padding: 0 20px; /* Más aire a los lados */
            display: flex;
            align-items: center;
            justify-content: space-between;
            height: 70px; /* Altura fija elegante */
            border-bottom: 1px solid rgba(255,255,255,0.05); /* Línea divisora muy sutil */
        }

        /* Contenedor del Logo */
        .logo-container {
            display: flex;
            align-items: center;
            max-width: 170px; /* Evita que choque con el botón */
            overflow: hidden;
            transition: opacity 0.3s;
        }

        #sidebar-logo {
            max-height: 40px; /* Controla la altura del logo */
            width: auto;
        }

        /* BOTÓN HAMBURGUESA (Minimalista) */
        #btn-toggle-sidebar {
            background-color: transparent !important; /* Sin fondo */
            border: none !important;
            box-shadow: none !important;
            color: #aebecd; /* Gris azulado suave */
            font-size: 1.4rem;
            cursor: pointer;
            margin-left: 15px; /* Separación del logo */
            padding: 5px;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        #btn-toggle-sidebar:hover {
            color: #ffffff; /* Blanco al pasar el mouse */
            transform: scale(1.1);
        }

        /* =========================================
        3. CUERPO Y PIE DE PÁGINA
        ========================================= */
        .sidebar-center {
            flex: 1;
            overflow-y: auto;
            overflow-x: hidden;
            padding-top: 10px;
        }
        
        /* Scroll personalizado fino */
        .sidebar-center::-webkit-scrollbar { width: 4px; }
        .sidebar-center::-webkit-scrollbar-thumb { background: #4b545c; border-radius: 4px; }

        .sidebar-footer {
            padding: 10px 0;
            background-color: #2c343b;
            border-top: 1px solid rgba(255,255,255,0.05);
        }

        /* =========================================
        4. ENLACES Y DROPDOWNS
        ========================================= */
        .nav-link {
            color: #d1d4d7 !important;
            padding: 12px 20px !important;
            display: flex;
            align-items: center;
            white-space: nowrap; /* Evita que el texto baje de línea */
            transition: all 0.2s;
            border-left: 3px solid transparent; /* Preparado para hover */
        }

        .nav-link:hover {
            background-color: rgba(255,255,255,0.05);
            color: #fff !important;
            border-left: 3px solid #3498db; /* Línea azul al pasar el mouse */
        }

        .nav-link i {
            min-width: 30px;
            font-size: 1.1rem;
            text-align: center;
            margin-right: 15px;
        }

        /* Estilo de Dropdowns abiertos */
        header .dropdown-menu {
            position: static !important;
            float: none !important;
            background-color: #22282e !important; /* Un poco más oscuro que el fondo */
            border: none;
            padding: 0;
            margin: 0;
            display: none; /* Controlado por JS de Bootstrap */
        }
        
        header .dropdown-menu.show { display: block; }
        
        header .dropdown-item {
            color: #adb5bd !important;
            padding: 10px 10px 10px 60px !important; /* Sangría para jerarquía */
            font-size: 0.9rem;
            transition: background 0.2s;
        }
        
        header .dropdown-item:hover {
            background-color: rgba(255,255,255,0.05);
            color: white !important;
        }

        /* =========================================
        5. ESTADO COLAPSADO (CERRADO)
        ========================================= */
        
        /* Usamos ID + Clase para máxima prioridad */
        header#main-sidebar.sidebar-collapsed { 
            width: 70px; 
        }

        body.sidebar-collapsed { 
            margin-left: 70px; 
        }

        /* Ocultar elementos de texto y logo */
        header#main-sidebar.sidebar-collapsed .nav-text, 
        header#main-sidebar.sidebar-collapsed #sidebar-logo,
        header#main-sidebar.sidebar-collapsed .dropdown-toggle::after {
            display: none !important;
        }

        /* Ajustar botón hamburguesa al centro */
        header#main-sidebar.sidebar-collapsed #btn-toggle-sidebar {
            margin-left: 0;
            width: 100%;
        }

        /* Centrar iconos de navegación */
        header#main-sidebar.sidebar-collapsed .nav-link {
            justify-content: center !important;
            padding: 15px 0 !important;
        }

        header#main-sidebar.sidebar-collapsed .nav-link i {
            margin-right: 0 !important;
        }

        /* Asegurar que los submenús no molesten */
        header#main-sidebar.sidebar-collapsed .dropdown-menu {
            display: none !important;
        }
    </style>
</head>

<body>
    <header id="main-sidebar">
        <div class="sidebar-top">
            <div class="logo-container">
                <img src="../img/logo-drg.png" alt="Logo" id="sidebar-logo">
            </div>
            <button id="btn-toggle-sidebar">
                <i class="fas fa-bars"></i>
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
            </nav>
        </div>

        <div class="sidebar-footer">
            <ul class="navbar-nav">
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
                        <i class="fa fa-user"></i>
                        <span class="nav-text"><?php echo $_SESSION['nombre']; ?></span>
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
        document.addEventListener('DOMContentLoaded', function() {
            const btnToggle = document.getElementById('btn-toggle-sidebar');
            const sidebar = document.getElementById('main-sidebar');
            const body = document.body;

            // Verificar si el botón existe antes de agregar el evento
            if (btnToggle) {
                btnToggle.addEventListener('click', (e) => {
                    e.preventDefault(); // Prevenir comportamientos extraños
                    const isCollapsed = sidebar.classList.toggle('sidebar-collapsed');
                    body.classList.toggle('sidebar-collapsed');
                    
                    localStorage.setItem('sidebarStatus', isCollapsed ? 'closed' : 'open');
                });
            }

            // Cargar estado al iniciar
            if (localStorage.getItem('sidebarStatus') === 'closed') {
                sidebar.classList.add('sidebar-collapsed');
                body.classList.add('sidebar-collapsed');
            }
        });
    </script>
</body> </html>