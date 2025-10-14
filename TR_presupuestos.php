<?php
require("config/db.php");
include("src/templates/adminheader.php");

$userId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!isset($_SESSION['loggedin']) || !$_SESSION['loggedin']) {
    header("Location: index.php");
    exit();
}

/*------------------Sacar Periodo Actual--------------------*/
$meses = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
$mes_num = date('n') - 1; // n va de 1 a 12, restamos 1 para ¨ªndice 0-based
$anio = date('y');
$periodoAct = $meses[$mes_num] . '-' . $anio;

// Traemos los Presupuestos
//Por numero asignado de sucursal y departamento
$sql = "
    SELECT 
        p.id,
        p.periodo,
        p.presupuesto,
        p.registrado,
        p.restante,
        s.sucursal AS sucursal,
        d.departamento AS departamento
    FROM presupuestos p
    JOIN sucursales s ON p.sucursal_id = s.id
    JOIN departamentos d ON p.departamento_id = d.id
    ORDER BY 
        SUBSTRING_INDEX(p.periodo, '-', -1) DESC,
        FIELD(SUBSTRING_INDEX(p.periodo, '-', 1), 'Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic') DESC,
        s.id ASC,
        d.id ASC
";
//Si se quiere acomodar por nombre de las sucursales y departamentos
/*$sql = "
    SELECT 
        p.id,
        p.periodo,
        p.presupuesto,
        p.registrado,
        p.restante,
        s.sucursal AS sucursal,
        d.departamento AS departamento
    FROM presupuestos p
    JOIN sucursales s ON p.sucursal_id = s.id
    JOIN departamentos d ON p.departamento_id = d.id
    ORDER BY p.periodo ASC, s.sucursal ASC, d.departamento ASC
";*/

$presupuestos = $conn->query($sql);

$estructura = [];

while ($row = $presupuestos->fetch_assoc()) {
    $periodo = $row['periodo'];
    $sucursal = $row['sucursal'];         
    $departamento = $row['departamento']; 
    $id = $row['id'];

    $estructura[$periodo][$sucursal][$departamento][] = [
        'id' => $row['id'],
        'presupuesto' => $row['presupuesto'],
        'registrado' => $row['registrado'],
        'restante' => $row['restante']
    ];

}


?>
<!-- Estilos personalizados -->
<style>

    h2 {
        border-bottom: 2px solid #3498db;
        padding-bottom: 10px;
    }
    .btn-primary {
        /*position: relative;*/
        bottom: 16px;
        background-color: #299dbf;
        border-color: #299dbf;
        font-weight: bold;
        width: 85%;
        color: #fff;
        text-align: left;
    }
    .form-check {
        padding-left: 30px;
    }
</style>

<div class="container">
    <h2 class="mb-4 text-center">Carga de Presupuestos</h2>
    
    <form id="formPresupuestos" enctype="multipart/form-data">
      <label for="excel_periodos" class="form-label">Adjuntar Excel con Presupuestos*</label><a href="TR_controller/plantilla/FORMATO_PARA_PRESUPUESTOS.xlsx" class="ms-3 small" download>
        Descargar plantilla
    </a>
      <div class="d-flex align-items-center gap-2">
        <input type="file" name="excel_periodos" id="excel_periodos" class="form-control"
          accept=".xls, .xlsx" required>
        <button type="button" class="btn btn-info" id="btnCargar">Cargar Presupuestos</button>
      </div>
    </form>
    
    <div id="spinner" style="display:none; text-align:center; margin-top:15px;">
        <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden">Cargando...</span>
        </div>
        <p>Cargando archivo, por favor espera...</p>
    </div>
    
    <div id="respuesta" style="margin-top:10px;"></div>
    <br>
    
    <h2 class="mb-4 text-center">Periodos Presupuestales</h2>

    

    <?php foreach ($estructura as $periodo => $sucursales): ?>
        <a class="btn btn-primary w-80 mb-2 text-white" data-bs-toggle="collapse" href="#periodo-<?php echo md5($periodo); ?>">
            <?php echo htmlspecialchars($periodo); ?>
        </a>
        <button type="button" class="btn btn-outline-secondary mb-2 ms-2" data-bs-toggle="modal" data-bs-target="#historial-<?php echo md5($periodo); ?>">
            Ver Historial
        </button>

        <div class="collapse" id="periodo-<?php echo md5($periodo); ?>">
            <?php foreach ($sucursales as $sucursal => $departamentos): ?>
                <a class="btn btn-secondary w-100 mb-2 ms-3 text-white" data-bs-toggle="collapse" href="#sucursal-<?php echo md5($periodo . $sucursal); ?>">
                    <?php echo htmlspecialchars($sucursal); ?>
                </a>
                <div class="collapse ms-3" id="sucursal-<?php echo md5($periodo . $sucursal); ?>">
                    <ul class="list-group mb-3 ms-4">
                        <?php foreach ($departamentos as $departamento => $datos): ?>
                            <?php foreach ($datos as $index => $dato): 
                                $modalId = 'modal-' . md5($periodo . $sucursal . $departamento . $index);
                            ?>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <span>
                                        <strong><?php echo htmlspecialchars($departamento); ?>:</strong>
                                        Presupuesto: $<?php echo number_format($dato['presupuesto'], 2); ?> |
                                        Registrado: $<?php echo number_format($dato['registrado'], 2); ?> |
                                        Restante: $<?php echo number_format($dato['restante'], 2); ?>
                                    </span>
                                    
                                    <?php if ($periodoAct == $periodo) { ?>
                                        <div class="ms-2">
                                            <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#<?php echo $modalId; ?>">
                                                Editar
                                            </button>
                                        </div>
                                    
                                    <?php } ?>
                                    
                                </li>
                                <!-- Modal de ediciÃ³n -->
                                <div class="modal fade" id="<?php echo $modalId; ?>" tabindex="-1" aria-labelledby="<?php echo $modalId; ?>Label" aria-hidden="true">
                                    <div class="modal-dialog">
                                        <form class="edit-form" data-id="<?php echo $dato['id']; ?>">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title" id="<?php echo $modalId; ?>Label"><?php echo htmlspecialchars($sucursal)." - ".htmlspecialchars($departamento); ?></h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <div class="mb-3">
                                                        <label class="form-label">Presupuesto*</label>
                                                        <div class="input-group">
                                                            <span class="input-group-text">$</span>
                                                            <input 
                                                                type="text" 
                                                                class="form-control" 
                                                                name="presupuesto" 
                                                                value="<?php echo number_format($dato['presupuesto'], 2); ?>" 
                                                                oninput="this.value = this.value.replace(/[^0-9.,]/g, '')"
                                                                required
                                                            >
                                                            <span class="input-group-text">MXN</span>
                                                        </div>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label">Registrado</label>
                                                        <div class="input-group">
                                                            <span class="input-group-text">$</span>
                                                            <input 
                                                                type="text" 
                                                                class="form-control" 
                                                                name="registrado" 
                                                                value="<?php echo number_format($dato['registrado'], 2); ?>" 
                                                                oninput="this.value = this.value.replace(/[^0-9.,]/g, '')"
                                                                readonly
                                                            >
                                                            <span class="input-group-text">MXN</span>
                                                        </div>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label">Restante</label>
                                                        <div class="input-group">
                                                            <span class="input-group-text">$</span>
                                                            <input 
                                                                type="text" 
                                                                class="form-control" 
                                                                name="restante" 
                                                                value="<?php echo number_format($dato['restante'], 2); ?>" 
                                                                oninput="this.value = this.value.replace(/[^0-9.,]/g, '')"
                                                                readonly
                                                            >
                                                            <span class="input-group-text">MXN</span>
                                                        </div>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label">Motivo de Edicion*</label>
                                                        <div class="input-group">
                                                            <input 
                                                                type="text" 
                                                                class="form-control" 
                                                                name="motivo" 
                                                                required
                                                            >
                                                        </div>
                                                    </div>
                                                    
                                                    <div id="respuesta" style="margin-top:10px;"></div>
                                                    
                                                    <!-- Campos ocultos para identificaciÃ³n -->
                                                    <input type="hidden" name="periodo" value="<?php echo htmlspecialchars($periodo); ?>">
                                                    <input type="hidden" name="sucursal" value="<?php echo htmlspecialchars($sucursal); ?>">
                                                    <input type="hidden" name="departamento" value="<?php echo htmlspecialchars($departamento); ?>">
                                                    <input type="hidden" name="index" value="<?php echo $index; ?>">
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                                                    <button type="submit" class="btn btn-info">Guardar</button>
                                                </div>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endforeach; ?>
        </div>
        <!-- Modal de historial por periodo -->
        <div class="modal fade" id="historial-<?php echo md5($periodo); ?>" tabindex="-1" aria-labelledby="historial-<?php echo md5($periodo); ?>Label" aria-hidden="true">
          <div class="modal-dialog modal-xl modal-dialog-scrollable" style="max-width: 70%;">
            <div class="modal-content">
              <div class="modal-header">
                <h5 class="modal-title" id="historial-<?php echo md5($periodo); ?>Label">Historial de cambios - <?php echo htmlspecialchars($periodo); ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
              </div>
              <div class="modal-body" style="overflow-y: auto;">
                <?php
                $periodoSeguro = $conn->real_escape_string($periodo);
                $sqlHistorial = "
                  SELECT 
                    h.*, 
                    s.sucursal AS nombre_sucursal, 
                    d.departamento AS nombre_departamento
                  FROM historial_presupuestos h
                  LEFT JOIN sucursales s ON h.id_sucursal = s.id
                  LEFT JOIN departamentos d ON h.id_departamento = d.id
                  WHERE h.periodo = '$periodoSeguro'
                  ORDER BY h.fecha DESC
                ";
                $resultHistorial = $conn->query($sqlHistorial);
                
                if ($resultHistorial && $resultHistorial->num_rows > 0): ?>
                  <table class="table table-bordered">
                    <thead>
                      <tr>
                        <th>Usuario</th>
                        <th>Sucursal</th>
                        <th>Departamento</th>
                        <th>Campo modificado</th>
                        <th>Valor anterior</th>
                        <th>Valor nuevo</th>
                        <th>Fecha</th>
                        <th>Motivo</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php while ($hist = $resultHistorial->fetch_assoc()): ?>
                        <tr>
                          <td><?php echo htmlspecialchars($hist['usuario']); ?></td>
                          <td><?php echo htmlspecialchars($hist['nombre_sucursal']); ?></td>
                          <td><?php echo htmlspecialchars($hist['nombre_departamento']); ?></td>
                          <td><?php echo htmlspecialchars($hist['campo_modificado']); ?></td>
                          <td>$<?php echo number_format($hist['valor_anterior'], 2); ?></td>
                          <td>$<?php echo number_format($hist['valor_nuevo'], 2); ?></td>
                          <td><?php echo htmlspecialchars($hist['fecha']); ?></td>
                          <td><?php echo htmlspecialchars($hist['motivo_edicion']); ?></td>
                        </tr>
                      <?php endwhile; ?>
                    </tbody>
                  </table>
                <?php else: ?>
                  <p>No hay historial para este periodo.</p>
                <?php endif; ?>
              </div>
            </div>
          </div>
        </div>
    <?php endforeach; ?>
</div>
<script>
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.edit-form').forEach(form => {
        form.addEventListener('submit', function(e) {
            e.preventDefault();

            const formData = new FormData(form);
            formData.append('id', form.dataset.id); // Agregamos el ID

            fetch('TR_controller/guardar_presupuesto.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Presupuesto actualizado',
                        text: 'Los cambios se guardaron correctamente.',
                        confirmButtonText: 'Aceptar',
                        timer: 2500,
                        showConfirmButton: false
                    }).then(() => {
                        location.reload(); // Recarga despu¨¦s del mensaje
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: data.message || 'Hubo un problema al guardar',
                        confirmButtonText: 'Cerrar'
                    });
                }
            })
            .catch(err => {
                Swal.fire({
                    icon: 'error',
                    title: 'Error en la solicitud',
                    text: 'No se pudo conectar con el servidor',
                    confirmButtonText: 'Cerrar'
                });
                console.error(err);
            });
        });
    });
});
</script>
<script>
  $('#btnCargar').on('click', function () {
    var formData = new FormData($('#formPresupuestos')[0]);

    // Mostrar el spinner
    $('#spinner').show();
    $('#respuesta').html('');

    $.ajax({
        url: 'TR_controller/procesar_presupuestos.php',  // Aseg¨²rate de que esta URL es correcta
        type: 'POST',
        data: formData,
        contentType: false,
        processData: false,
        success: function (response) {
            $('#spinner').hide();

            // Intentar parsear la respuesta JSON
            try {
                var data = JSON.parse(response);

                // Verificar si la respuesta es exitosa
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Exito',
                        text: data.message,
                        confirmButtonText: 'Aceptar'
                    }).then(() => {
                        location.reload();
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: data.message,
                        confirmButtonText: 'Aceptar'
                    });
                }
            } catch (e) {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Error al procesar la respuesta del servidor.',
                    confirmButtonText: 'Aceptar'
                });
                console.error('Error al parsear JSON:', e);
            }
        },
        error: function (xhr, status, error) {
            $('#spinner').hide();
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'Error: ' + error,
                confirmButtonText: 'Aceptar'
            });
        }
    });
  });
</script>
<?php
include("src/templates/adminfooter.php");
?>
