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
<style>
/* SIDEBAR LATERAL */
.sidebar {
    width: 250px;
    height: 100vh;
    position: fixed;
    left: 0;
    top: 0;
    background: #343a40;
    padding-top: 20px;
    overflow-y: auto;
}

.sidebar .nav-link {
    color: #fff;
    padding: 10px 20px;
}

.sidebar .nav-link:hover {
    background: #495057;
    color: #fff;
}

.sidebar .dropdown-menu {
    background: #343a40;
    border: none;
}

.sidebar .dropdown-item {
    color: #fff;
}

.sidebar .dropdown-item:hover {
    background: #495057;
    color: #fff;
}

/* Contenido movido para no quedar debajo del menú */
.content-wrapper {
    margin-left: 260px;
    padding: 20px;
}
</style>

</head>

<body>
    <header>
        <!-- Barra de navegacion -->
        <div class="sidebar">
            <div class="text-center mb-4">
                <img src="../img/logo-drg.png" width="150">
            </div>

            <ul class="nav flex-column">

                <!-- INICIO -->
                <li class="nav-item">
                    <a class="nav-link" href="inicio.php">
                        <i class="fas fa-home"></i> Inicio
                    </a>
                </li>

                <!-- MENÚ DINÁMICO POR PERMISOS -->
                <?php 
                if (!empty($_SESSION['permisos']) && is_array($_SESSION['permisos'])) {
                    foreach ($_SESSION['permisos'] as $categoria => $programas) {
                        ?>
                        <li class="nav-item dropdown">

                            <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="collapse" data-bs-target="#drop_<?php echo $categoria; ?>">
                                <i class="fas fa-folder"></i> <?php echo htmlspecialchars($categoria); ?>
                            </a>

                            <div class="collapse" id="drop_<?php echo $categoria; ?>">
                                <ul class="nav flex-column ms-3">
                                    <?php foreach ($programas as $permiso): ?>
                                        <li class="nav-item">
                                            <a class="nav-link" href="<?= htmlspecialchars($permiso['url']) ?>">
                                                - <?= htmlspecialchars($permiso['nombre_programa']) ?>
                                            </a>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>

                        </li>
                        <?php
                    }
                } else {
                    echo '<li class="nav-item"><a class="nav-link">Sin permisos</a></li>';
                }
                ?>

                <hr class="bg-light">

                <!-- USUARIO -->
                <li class="nav-item dropdown mt-3">
                    <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="collapse" data-bs-target="#userMenu">
                        <i class="fa fa-user"></i> <?= htmlspecialchars($_SESSION['nombre']) ?>
                    </a>

                    <div class="collapse" id="userMenu">
                        <ul class="nav flex-column ms-3">
                            <li><a class="nav-link" href="logout.php"><i class="fas fa-sign-out-alt"></i> Cerrar Sesión</a></li>
                        </ul>
                    </div>
                </li>

            </ul>
        </div>

<!-- CONTENIDO GENERAL -->
<div class="content-wrapper">

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