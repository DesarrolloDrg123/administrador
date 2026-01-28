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

        /* Ajuste del Logo para que no se deforme */
        .logo-container {
            display: flex;
            justify-content: center;
            align-items: center;
            width: 100%;
            overflow: hidden;
        }

        .sidebar-top {
            padding: 15px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            min-height: 80px;
        }

        /* Centro y Scroll */
        .sidebar-center {
            flex: 1;
            overflow-y: auto;
            overflow-x: hidden;
        }

        .sidebar-footer {
            padding: 10px 0;
            background-color: #2c343b;
            border-top: 1px solid #3e474f;
        }

        /* Links y Iconos */
        .nav-link {
            color: #d1d4d7 !important;
            padding: 12px 20px !important;
            display: flex;
            align-items: center;
            white-space: nowrap;
            transition: all 0.3s;
        }

        .nav-link i {
            min-width: 30px; /* Ancho fijo para el icono */
            font-size: 1.2rem;
            text-align: center;
            margin-right: 15px;
        }

        /* Dropdowns en modo expandido */
        /* Usamos #main-sidebar.sidebar-collapsed para ganar prioridad sobre el ID normal */
        header#main-sidebar.sidebar-collapsed { 
            width: 70px; 
        }

        body.sidebar-collapsed { 
            margin-left: 70px; 
        }

        /* Ocultar texto y logo */
        header#main-sidebar.sidebar-collapsed .nav-text, 
        header#main-sidebar.sidebar-collapsed #sidebar-logo,
        header#main-sidebar.sidebar-collapsed .dropdown-toggle::after {
            display: none !important;
        }

        /* Centrar iconos cuando está cerrado */
        header#main-sidebar.sidebar-collapsed .nav-link {
            justify-content: center !important;
            padding: 15px 0 !important;
        }

        header#main-sidebar.sidebar-collapsed .nav-link i {
            margin-right: 0 !important;
        }

        /* Ocultar dropdowns */
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