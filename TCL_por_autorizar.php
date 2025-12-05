<?php
session_start();
require "config/db.php";
include "src/templates/adminheader.php";

if (!isset($_SESSION['loggedin']) || !$_SESSION['loggedin']) {
    header("Location: ../index.php");
    exit();
}

$autorizacion_id = $_SESSION['usuario_solicitante_id'];
$solicitudes = [];

try {
    // Obtener solicitudes pendientes para el usuario
    $sql = 'SELECT
        t.id,
        t.folio,
        s.sucursal,
        b.beneficiario,
        t.fecha_solicitud,
        t.importe,
        t.descripcion,
        t.estado,
        t.documento_adjunto
    FROM
        transferencias_clara_tcl t
    JOIN sucursales s ON t.sucursal_id = s.id
    JOIN beneficiarios b ON t.beneficiario_id = b.id
    WHERE
        t.autorizacion_id = ? AND t.estado = "Pendiente"
    ORDER BY
        t.folio DESC';
    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        throw new Exception($conn->error);
    }
    $stmt->bind_param("i", $autorizacion_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $solicitudes = $result->fetch_all(MYSQLI_ASSOC);

    $fmt = new IntlDateFormatter('es_ES', IntlDateFormatter::LONG, IntlDateFormatter::NONE);
    foreach ($solicitudes as &$solicitud) {
        // Convertir la fecha a un objeto DateTime
        $fecha = new DateTime($solicitud['fecha_solicitud']);
        // Formatear la fecha y reemplazar en el array
        $solicitud['fecha_solicitud'] = $fmt->format($fecha);
    }
    unset($solicitud); // Romper la referencia con el último elemento

} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
    exit();
}
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="utf-8" />
    <title>Solicitudes</title>
    <style>
        .table thead {
        background-color: #343a40;
        color: #fff;
        }
    
        .table {
            background-color: #ffffff;
            border-radius: 10px;
            overflow: hidden;
        }
    
        .table th {
            background-color: #333;
            color: #ffffff;
            padding: 10px;
            border-bottom: 1px solid #3498db;
        }
    
        .table td {
            padding: 10px;
            border-bottom: 1px solid #dddddd;
        }
        .btn-group {
            display: flex;
            gap: 5px;
        }
        body {
        background-color: #EEF1F2;
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        h1 {
        color: #2c3e50;
        border-bottom: 2px solid #3498db;
        padding-bottom: 10px;
        }
    </style>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>

<body>
    <div class="container mt-5">
        <h1 class="mb-4">Pendientes por Autorizar</h1>

        <?php if (count($solicitudes) > 0): ?>
            <table class="table table-striped" id="solicitudesTable">
                <thead>
                    <tr>
                        <th>Folio</th>
                        <th>Sucursal</th>
                        <th>Beneficiario</th>
                        <th>Fecha Solicitud</th>
                        <th>Importe</th>
                        <th>Descripción</th>
                        <th>Status</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($solicitudes as $solicitud): ?>
                        <tr>
                            <td><a href="TCL_detalle_transferencias.php?id=<?php echo htmlspecialchars($solicitud['id']); ?>&AT=true"><?php echo htmlspecialchars($solicitud['folio']); ?></a></td>
                            <td><?php echo htmlspecialchars($solicitud['sucursal']); ?></td>
                            <td><?php echo htmlspecialchars($solicitud['beneficiario']); ?></td>
                            <td><?php echo htmlspecialchars($solicitud['fecha_solicitud']); ?></td>
                            <td><?php echo htmlspecialchars('$' . $solicitud['importe']); ?></td>
                            <td><?php echo htmlspecialchars($solicitud['descripcion']); ?></td>
                            <td><?php echo htmlspecialchars($solicitud['estado']); ?></td>
                            <td>
                                <div class="btn-group">
                                    <?php if (!empty($solicitud['documento_adjunto'])): ?>
                                        <a href="<?php echo htmlspecialchars($solicitud['documento_adjunto']); ?>" class="btn btn-primary btn-sm" target="_blank">Ver Adjunto</a>
                                    <?php endif; ?>
                    
                                    <?php if ($solicitud['estado'] == 'Pendiente'): ?>
                                        <a href="#" 
                                           data-url="TCL_controller/aprobar.php?id=<?php echo htmlspecialchars($solicitud['folio']); ?>" 
                                           data-action="Aprobar" 
                                           data-past-action="aprobada"
                                           class="btn btn-success btn-sm btn-accion-solicitud">Aprobar</a>
                                        
                                        <a href="#" 
                                           data-url="TCL_controller/rechazar.php?id=<?php echo htmlspecialchars($solicitud['id']); ?>" 
                                           data-action="Rechazar"
                                           data-past-action="rechazada"
                                           class="btn btn-danger btn-sm btn-accion-solicitud">Rechazar</a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p>No hay solicitudes pendientes.</p>
        <?php endif; ?>
    </div>

    <script>
    function filterTable() {
        var input, filter, table, tr, td, i, j, txtValue;
        input = document.getElementById("search");
        filter = input.value.toLowerCase();
        table = document.getElementById("solicitudesTable");
        tr = table.getElementsByTagName("tr");

        for (i = 1; i < tr.length; i++) {
            tr[i].style.display = "none"; // Initially hide the row
            td = tr[i].getElementsByTagName("td");
            for (j = 0; j < td.length; j++) {
                if (td[j]) {
                    txtValue = td[j].textContent || td[j].innerText;
                    if (txtValue.toLowerCase().indexOf(filter) > -1) {
                        tr[i].style.display = ""; // Show the row if any cell matches the filter
                        break; // Exit the loop once a match is found
                    }
                }
            }
        }
    }

    // Espera a que todo el contenido de la página esté cargado
    document.addEventListener('DOMContentLoaded', function() {
        const tabla = document.getElementById('solicitudesTable'); 
        
        if (tabla) {
            tabla.addEventListener('click', function(e) {
                
                // Verificamos si el clic fue en un botón de acción
                if (e.target.classList.contains('btn-accion-solicitud')) {
                    e.preventDefault(); // Prevenir la navegación del enlace
    
                    // Obtenemos los datos del botón que fue presionado
                    const boton = e.target;
                    const url = boton.dataset.url;
                    const action = boton.dataset.action; // Contiene 'Aprobar' o 'Rechazar'
                    const pastAction = boton.dataset.pastAction;
                    if (action === 'Aprobar') {
                        
                        Swal.fire({
                            title: '¿Estás seguro?',
                            text: `¿Realmente deseas aprobar esta solicitud?`,
                            icon: 'question',
                            showCancelButton: true,
                            confirmButtonColor: '#28a745', // Verde para aprobar
                            cancelButtonColor: '#d33',
                            confirmButtonText: 'Sí, ¡aprobar!',
                            cancelButtonText: 'Cancelar'
                        }).then((result) => {
                            if (result.isConfirmed) {
                                // Mostrar "Cargando"
                                Swal.fire({
                                    title: 'Procesando...',
                                    allowOutsideClick: false,
                                    didOpen: () => { Swal.showLoading(); }
                                });
    
                                // Realizar la petición GET (simple)
                                fetch(url)
                                    .then(response => response.json())
                                    .then(data => {
                                        if (data.success) {
                                            Swal.fire('¡Aprobada!', 'La solicitud ha sido aprobada.', 'success').then(() => location.reload());
                                        } else {
                                            Swal.fire('Error', data.message, 'error');
                                        }
                                    })
                                    .catch(error => Swal.fire('Error de Conexión', 'No se pudo comunicar con el servidor.', 'error'));
                            }
                        });
                    } 
                    else if (action === 'Rechazar') {
                        
                        Swal.fire({
                            title: 'Motivo del Rechazo',
                            input: 'textarea',
                            inputPlaceholder: 'Escribe aquí por qué se rechaza la solicitud...',
                            showCancelButton: true,
                            confirmButtonText: 'Rechazar Solicitud',
                            cancelButtonText: 'Cancelar',
                            inputValidator: (value) => {
                                if (!value) {
                                    return '¡Necesitas escribir un motivo para el rechazo!';
                                }
                            }
                        }).then((result) => {
                            if (result.isConfirmed) {
                                const motivo = result.value;
    
                                // Mostrar "Cargando"
                                Swal.fire({
                                    title: 'Procesando...',
                                    allowOutsideClick: false,
                                    didOpen: () => { Swal.showLoading(); }
                                });
    
                                // Realizar la petición POST para enviar el motivo
                                fetch(url, {
                                    method: 'POST',
                                    headers: {
                                        'Content-Type': 'application/x-www-form-urlencoded',
                                    },
                                    body: `motivo=${encodeURIComponent(motivo)}`
                                })
                                .then(response => response.json())
                                .then(data => {
                                    if (data.success) {
                                        Swal.fire('¡Rechazada!', 'La solicitud ha sido rechazada.', 'success').then(() => location.reload());
                                    } else {
                                        Swal.fire('Error', data.message, 'error');
                                    }
                                })
                                .catch(error => Swal.fire('Error de Conexión', 'No se pudo comunicar con el servidor.', 'error'));
                            }
                        });
                    }
                }
            });
        }
    });
    </script>
    
    <script>
            $(document).ready(function() {
                var table = $('#solicitudesTable').DataTable({
                    "language": {
                        "url": "//cdn.datatables.net/plug-ins/1.10.16/i18n/Spanish.json"
                    },
                    "pageLength": 10,
                    "lengthMenu": [5, 10, 20],
                    "responsive": true,  // Habilitar la respuesta en diferentes tamaños de pantalla
                    "processing": true,
                    "order": [[0, "desc"]],
                    "columnDefs": [
                        { "orderable": false, "targets": [0,1,2,4,5,6,7] }
                    ]
                });
            });
        </script>
</body>

</html>

<?php
$conn->close();
?>

<?php
include('src/templates/adminfooter.php');
?>
