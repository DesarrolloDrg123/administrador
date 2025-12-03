<style>
    .table { background-color: #ffffff; border-radius: 10px; overflow: hidden; }
    .table th { background-color: #333; color: #ffffff; }
    .table td { vertical-align: middle; }
    .container { max-width: 100%; }
    
    .card {  max-width: 1600px  }
    
    .badge-status { padding: 5px 12px; border-radius: 15px; color: white; font-weight: bold; font-size: 0.9em; }
    /* Estatus para Reclutamiento */
    .status_nueva_solicitud { background-color: #299dbf; }            /* Azul base de la empresa */
    .status_autorizada { background-color: #80bf1f; }                 /* Verde de la empresa */
    .status_rechazada { background-color: #e74c3c; }                  /* Rojo para destacar rechazo */
    .status_publicada { background-color: #2980b9; }                  /* Azul oscuro, armoniza con azul empresa */
    .status_en_proceso_de_seleccion { background-color: #f1c40f; }    /* Amarillo cálido, destaca proceso activo */
    .status_finalizada { background-color: #34495e; }                 /* Gris azulado, neutral y elegante */


    .swal2-textarea { width: 90% !important; margin-top: 15px !important; }
</style>
<div class="container mt-4">

    <h2 class="text-center">Mis Transferencias</h2>
    <br>

    <div class="row g-3">

        <div class="col-md-3">
            <label>Departamento</label>
            <select id="filtro_departamento" class="form-select">
                <option value="">Todos</option>
            </select>
        </div>

        <div class="col-md-3">
            <label>Sucursal</label>
            <select id="filtro_sucursal" class="form-select">
                <option value="">Todos</option>
            </select>
        </div>

        <div class="col-md-3">
            <label>Fecha Inicio</label>
            <input type="date" id="filtro_fecha_ini" class="form-control">
        </div>

        <div class="col-md-3">
            <label>Fecha Fin</label>
            <input type="date" id="filtro_fecha_fin" class="form-control">
        </div>

    </div>

    <br>

    <button class="btn btn-dark" id="btn_filtrar">Aplicar Filtros</button>
    <br><br>

    <table id="tabla_transferencias" 
           class="display responsive nowrap" 
           style="width:100%">
        <thead>
            <tr>
                <th>Folio</th>
                <th>Fecha Solicitud</th>
                <th>Sucursal</th>
                <th>Departamento</th>
                <th>Beneficiario</th>
                <th>Importe</th>
                <th>Estado</th>
            </tr>
        </thead>
        <tbody></tbody>
    </table>

</div>

<script>

    let tabla;

    function cargarTabla() {

        if (tabla) tabla.destroy();

        tabla = $("#tabla_transferencias").DataTable({
            ajax: {
                url: "get_mis_transferencias.php",
                data: {
                    departamento: $("#filtro_departamento").val(),
                    sucursal: $("#filtro_sucursal").val(),
                    fecha_ini: $("#filtro_fecha_ini").val(),
                    fecha_fin: $("#filtro_fecha_fin").val()
                },
                dataSrc: "transferencias"
            },
            responsive: true,
            columns: [
                { data: "folio" },
                { data: "fecha_solicitud" },
                { data: "sucursal" },
                { data: "departamento" },
                { data: "beneficiario" },
                { data: "importe" },
                { data: "estado" }
            ]
        });

    }

    $("#btn_filtrar").on("click", function() {
        cargarTabla();
    });

    $(document).ready(function() {
        cargarTabla();
    });

</script>
