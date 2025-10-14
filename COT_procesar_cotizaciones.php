<?php
session_start();

require("config/db.php");
include("src/templates/adminheader.php");

// Verifica si el usuario est谩 logueado, si no, lo redirige
if (!isset($_SESSION['loggedin']) || !$_SESSION['loggedin']) {
    header("Location: index.php");
    exit();
}

function ObtenerCotizacionesPendientes($conn) {
    //La consulta ahora apunta a `datos_generales_co` y busca el estatus 'Pendiente'
    $sql = "SELECT * FROM datos_generales_co WHERE estatus = 'Nuevo' OR estatus = 'En Proceso' OR estatus = 'En Revision' ORDER BY folio DESC";
    
    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        return []; // Manejar error de preparaci贸n
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $cotizaciones = [];
        while ($row = $result->fetch_assoc()) {
            $cotizaciones[] = $row;
        }
        return $cotizaciones;
    } else {
        return [];
    }
}

$cotizaciones = ObtenerCotizacionesPendientes($conn);

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Procesar Cotizaciones</title>
</head>
<style>
    /* Tus estilos (sin cambios) */
    .table { background-color: #ffffff; border-radius: 10px; overflow: hidden; }
    .table th { background-color: #333; color: #ffffff; padding: 10px; }
    .table td { padding: 10px; border-bottom: 1px solid #dddddd; }
    .container { max-width: 100%; }
</style>
<body>
    
    <div class="container">
        <div>
            <br>
            <h1 class="text-center mb-3">Cotizaciones Pendientes por Procesar</h1><br>
            
            <table class="table table-bordered table-striped" id="tabla-cotizaciones">
                <thead>
                    <tr style="text-align:center;">
                        <th>Folio</th>
                        <th>Fecha Solicitud</th>
                        <th>Solicitante</th>
                        <th>Empresa</th>
                        <th>Cliente</th>
                        <th>Etiquetado y Codificado</th>
                        <th>Observaciones</th>
                        <th>Estatus</th>
                        <th>Acciones</th> 
                    </tr>
                </thead>
                <tbody>
                    <?php
                    //Recorrer el array de cotizaciones
                    if (!empty($cotizaciones)) {
                        foreach ($cotizaciones as $cotizacion) {
                            echo "<tr>";
                            echo "<td style='text-align:center;'>" . htmlspecialchars(str_pad($cotizacion['folio'], 9, "0", STR_PAD_LEFT)) . "</td>";
                            echo "<td style='text-align:center;'>" . date('d-m-Y', strtotime($cotizacion['fecha_solicitud'])) . "</td>";
                            echo "<td style='text-align:center;'>" . htmlspecialchars($cotizacion['user_solicitante']) . "</td>";
                            echo "<td style='text-align:center;'>" . htmlspecialchars($cotizacion['empresa']) . "</td>";
                            echo "<td style='text-align:center;'>" . htmlspecialchars($cotizacion['cliente']) . "</td>";
                            $rfid = htmlspecialchars($cotizacion['rfid']);
                            $SiNo = "";
                            $SiNo = ($rfid == "1") ? "Si" : "No";
                            echo "<td style='text-align:center;'>" . $SiNo . "</td>";
                            echo "<td style='text-align:center;'>" . htmlspecialchars($cotizacion['observaciones']) . "</td>";
                            $color = "gray";
                            if($cotizacion['estatus'] == "Nuevo"|| $cotizacion['estatus'] == "En Revision"){
                                $color = "blue";
                            }
                            echo "<td style='text-align:center; color:".$color."; font-weight:bold;'>" . htmlspecialchars($cotizacion['estatus']) . "</td>";
                            $folio_url = urlencode($cotizacion['folio']);
                            echo "<td style='text-align:center;'>
                                    <a href='COT_cotizar_form.php?folio={$folio_url}' class='btn btn-success btn-sm'>Cotizar</a>
                                    <button type='button' class='btn btn-danger btn-sm' onclick='manejarAccion(\"{$folio_url}\", \"Rechazada\")'>Rechazar</button>
                                    <button type='button' class='btn btn-warning btn-sm' onclick='manejarAccion(\"{$folio_url}\", \"Devuelta\")'>Devolver</button>
                                  </td>";
                            
                            echo "</tr>";
                        }
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <div class="modal fade" id="modalCotizar" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog"><div class="modal-content"></div></div>
    </div>
    
    <div class="modal fade" id="modalRechazar" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog"><div class="modal-content"></div></div>
    </div>
    
    <div class="modal fade" id="modalDevolver" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog"><div class="modal-content"></div></div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    function manejarAccion(folio, estatusFinal) {
        // Determinamos el verbo de la acción para mostrarlo en la alerta
        const verboAccion = estatusFinal === 'Devuelta' ? 'Devolver' : 'Rechazar';
        
        Swal.fire({
            title: `${verboAccion} Cotizaci&oacute;n`,
            input: 'textarea',
            inputLabel: `Motivo para ${verboAccion.toLowerCase()}`,
            inputPlaceholder: 'Escribe aqui el motivo...',
            showCancelButton: true,
            confirmButtonText: verboAccion,
            cancelButtonText: 'Cancelar',
            confirmButtonColor: estatusFinal === 'Devuelta' ? '#f0ad4e' : '#d9534f',
            inputValidator: (value) => {
                if (!value) {
                    return '¡Necesitas escribir un motivo!'
                }
            }
        }).then((result) => {
            if (result.isConfirmed) {
                // Enviamos el estatus final ('Devuelta' o 'Rechazada') al servidor
                enviarActualizacion(folio, estatusFinal, result.value);
            }
        });
    }

    function enviarActualizacion(folio, nuevoEstatus, motivo) {
        Swal.fire({
            title: 'Actualizando...',
            text: 'Por favor, espera.',
            allowOutsideClick: false,
            didOpen: () => Swal.showLoading()
        });

        const formData = new FormData();
        formData.append('folio', folio);
        formData.append('estatus', nuevoEstatus);
        formData.append('motivo', motivo);

        fetch('COT_controller/actualizar_estatus.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                Swal.fire({
                    icon: 'success',
                    title: 'Actualizado',
                    text: `La cotizacion ha sido ${nuevoEstatus.toLowerCase()}.`, // Ej: "ha sido rechazada."
                }).then(() => {
                    location.reload();
                });
            } else {
                Swal.fire({ icon: 'error', title: 'Error', text: data.message });
            }
        })
        .catch(error => {
            console.error('Error:', error);
            Swal.fire({ icon: 'error', title: 'Error de Conexión', text: 'No se pudo conectar con el servidor.' });
        });
    }

    $(document).ready(function() {
        $('#tabla-cotizaciones').DataTable({
            "language": { "url": "//cdn.datatables.net/plug-ins/1.10.16/i18n/Spanish.json", "emptyTable": "No hay cotizaciones pendientes" },
            "pageLength": 10,
            "lengthMenu": [10, 25, 50],
            "responsive": true,
            "order": [[0, "desc"]]
        });
    });
</script>
    
</body>
</html>

<?php
include("src/templates/adminfooter.php");
?>