<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mis Solicitudes de Transferencias</title>
    <!-- Tailwind CSS CDN para un diseño moderno y responsive -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Font Awesome para iconos -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <!-- jQuery y DataTables (Requeridos para el manejo avanzado de tablas con AJAX) -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/v/dt/dt-2.0.7/r-3.0.2/datatables.min.css"/>
    <script type="text/javascript" src="https://cdn.datatables.net/v/dt/dt-2.0.7/r-3.0.2/datatables.min.js"></script>

    <style>
        /* Estilos personalizados de Tailwind */
        .dataTables_wrapper .dt-buttons {
            margin-bottom: 1rem;
        }
        .dataTables_wrapper .dataTables_filter {
            margin-bottom: 1rem;
        }
    </style>
</head>
<body class="bg-gray-100 font-sans p-4 sm:p-6 md:p-8">

    <div class="max-w-7xl mx-auto bg-white shadow-xl rounded-xl p-4 sm:p-6">
        <h1 class="text-3xl font-bold text-gray-800 mb-4 border-b-2 border-indigo-600 pb-2">Mis Solicitudes de Transferencias</h1>
        
        <div id="alert-messages" class="mb-4">
            <!-- Los mensajes de éxito/error se insertarán aquí dinámicamente -->
        </div>

        <h6 class="text-sm text-gray-500 mb-4">
            <span class="inline-flex items-center">
                <span class="w-3 h-3 bg-red-500 rounded-full mr-2"></span> Folios en <strong>rojo</strong> sin facturas adjuntas
            </span>
        </h6>

        <!-- FORMULARIO DE FILTROS (Responsive con Grid de Tailwind) -->
        <h2 class="text-xl font-semibold text-indigo-700 mb-3">Filtros de Búsqueda</h2>
        <form id="filter-form" class="grid grid-cols-2 md:grid-cols-6 gap-4 mb-6 p-4 border rounded-lg bg-gray-50">
            
            <!-- Departamento -->
            <div class="col-span-2 sm:col-span-1">
                <label for="filtro_departamento" class="block text-sm font-medium text-gray-700">Departamento</label>
                <!-- NOTA: En la implementación real, deberías cargar estas opciones vía AJAX/PHP -->
                <select id="filtro_departamento" name="departamento" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 p-2">
                    <option value="">Todos</option>
                    <option value="1">Ventas</option>
                    <option value="2">Contabilidad</option>
                    <option value="3">Sistemas</option>
                </select>
            </div>
            
            <!-- Sucursal -->
            <div class="col-span-2 sm:col-span-1">
                <label for="filtro_sucursal" class="block text-sm font-medium text-gray-700">Sucursal</label>
                <select id="filtro_sucursal" name="sucursal" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 p-2">
                    <option value="">Todas</option>
                    <option value="10">Centro</option>
                    <option value="20">Norte</option>
                </select>
            </div>
            
            <!-- Estado -->
            <div class="col-span-2 sm:col-span-1">
                <label for="filtro_estado" class="block text-sm font-medium text-gray-700">Estado</label>
                <select id="filtro_estado" name="estado" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 p-2">
                    <option value="">Todos</option>
                    <option value="Pendiente">Pendiente</option>
                    <option value="Autorizado">Autorizado</option>
                    <option value="Cancelada">Cancelada</option>
                </select>
            </div>
            
            <!-- Fecha Inicio -->
            <div class="col-span-2 sm:col-span-1">
                <label for="filtro_fecha_inicio" class="block text-sm font-medium text-gray-700">Fecha inicio</label>
                <input type="date" id="filtro_fecha_inicio" name="fecha_inicio" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 p-2">
            </div>
            
            <!-- Fecha Fin -->
            <div class="col-span-2 sm:col-span-1">
                <label for="filtro_fecha_fin" class="block text-sm font-medium text-gray-700">Fecha fin</label>
                <input type="date" id="filtro_fecha_fin" name="fecha_fin" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 p-2">
            </div>

            <!-- Botones -->
            <div class="col-span-2 sm:col-span-1 flex items-end space-x-2">
                <button type="submit" class="w-1/2 md:w-full bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded-lg shadow transition duration-150">
                    <i class="fas fa-filter"></i> Filtrar
                </button>
                <button type="button" onclick="resetFilters()" class="w-1/2 md:w-full bg-gray-200 hover:bg-gray-300 text-gray-800 font-bold py-2 px-4 rounded-lg shadow transition duration-150">
                    Limpiar
                </button>
            </div>
        </form>

        <!-- CONTENEDOR DE LA TABLA -->
        <div class="overflow-x-auto shadow-md rounded-lg">
            <table id="solicitudesTable" class="w-full text-sm text-left text-gray-500 dark:text-gray-400">
                <thead class="text-xs text-white uppercase bg-indigo-700">
                    <tr>
                        <th scope="col" class="px-6 py-3">Folio</th>
                        <th scope="col" class="px-6 py-3">Beneficiario</th>
                        <th scope="col" class="px-6 py-3">Sucursal</th>
                        <th scope="col" class="px-6 py-3">Departamento</th>
                        <th scope="col" class="px-6 py-3">Elabora</th>
                        <th scope="col" class="px-6 py-3">Autoriza</th>
                        <th scope="col" class="px-6 py-3">Importe</th>
                        <th scope="col" class="px-6 py-3">Facturado</th>
                        <th scope="col" class="px-6 py-3">Pendiente</th>
                        <th scope="col" class="px-6 py-3">Fecha</th>
                        <th scope="col" class="px-6 py-3">Estado</th>
                        <th scope="col" class="px-6 py-3">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- Los datos se cargarán aquí por DataTables vía AJAX -->
                </tbody>
            </table>
        </div>
    </div>

    <script>
        // Simulamos la ID del usuario loggeado. En PHP real, esto sería de $_SESSION.
        const USER_ID = 101; 
        // El endpoint al que haremos la llamada AJAX para obtener los datos.
        const API_ENDPOINT = 'api_transferencias.php'; 

        let dataTable;

        /**
         * Obtiene los parámetros de filtro del formulario.
         * @returns {string} String de parámetros de consulta (query string).
         */
        function getFilterParams() {
            const depto = $('#filtro_departamento').val();
            const sucursal = $('#filtro_sucursal').val();
            const estado = $('#filtro_estado').val();
            const fecha_inicio = $('#filtro_fecha_inicio').val();
            const fecha_fin = $('#filtro_fecha_fin').val();
            
            // Construimos los parámetros de la API, incluyendo el ID de usuario para la seguridad.
            const params = new URLSearchParams({
                usuario_id: USER_ID,
                departamento: depto,
                sucursal: sucursal,
                estado: estado,
                fecha_inicio: fecha_inicio,
                fecha_fin: fecha_fin,
            });

            return params.toString();
        }

        /**
         * Inicializa o recarga el DataTables usando la fuente de datos AJAX.
         */
        function initializeDataTable() {
            if (dataTable) {
                // Si ya existe, simplemente recargamos la fuente de datos con los nuevos filtros
                dataTable.ajax.url(`${API_ENDPOINT}?${getFilterParams()}`).load();
                return;
            }

            dataTable = $('#solicitudesTable').DataTable({
                // Configuración AJAX: llama a tu API PHP
                ajax: {
                    url: `${API_ENDPOINT}?${getFilterParams()}`,
                    type: 'GET',
                    dataSrc: 'data' // La API PHP debe retornar un objeto con una clave 'data'
                },
                // Configuración de columnas
                columns: [
                    // Folio (Columna 0)
                    { data: 'folio', render: function(data, type, row) {
                        const folioClass = parseFloat(row.total_facturas_num) > 0 ? 'text-indigo-600 font-semibold' : 'text-red-500 font-semibold';
                        // Enlace al detalle, usando el ID de la fila (MIN(t.id))
                        return `<a href="TR_detalle_transferencias.php?id=${row.id}&MT=true" class="${folioClass} hover:underline">${data}</a>`;
                    }},
                    // Beneficiario (Columna 1)
                    { data: 'beneficiario' },
                    // Sucursal (Columna 2)
                    { data: 'sucursal' },
                    // Departamento (Columna 3)
                    { data: 'departamento', responsivePriority: 10 },
                    // Elabora (Columna 4)
                    { data: 'usuario', responsivePriority: 5 },
                    // Autoriza (Columna 5)
                    { data: 'autorizacion_id', responsivePriority: 6 },
                    // Importe (Columna 6)
                    { data: 'importe_principal', className: 'text-right', render: function(data, type, row) {
                        return `$${parseFloat(data).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ",")}`; // Formato de moneda
                    }},
                    // Total Facturado (Columna 7)
                    { data: 'total_facturas_str', className: 'text-right', responsivePriority: 3 },
                    // Pendiente por Subir (Columna 8)
                    { data: 'pendiente_str', className: 'text-right', responsivePriority: 4 },
                    // Fecha (Columna 9)
                    { data: 'fecha_solicitud', render: function(data) {
                        // Formatear fecha de YYYY-MM-DD a DD/MM/YYYY
                        return new Date(data).toLocaleDateString('es-ES');
                    }, responsivePriority: 7},
                    // Estado (Columna 10)
                    { data: 'estado', render: function(data) {
                        let badgeClass = 'bg-gray-200 text-gray-800';
                        if (data === 'Autorizado') badgeClass = 'bg-green-100 text-green-800';
                        else if (data === 'Pendiente') badgeClass = 'bg-yellow-100 text-yellow-800';
                        else if (data === 'Cancelada' || data === 'Rechazado') badgeClass = 'bg-red-100 text-red-800';
                        return `<span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full ${badgeClass}">${data}</span>`;
                    }, responsivePriority: 1 }, // Prioridad alta para que se vea en móvil
                    // Acciones (Columna 11)
                    { data: null, orderable: false, responsivePriority: 2, render: function(data, type, row) {
                        let html = '';
                        // Enlace Documento Adjunto
                        if (row.documento_adjunto) {
                            html += `<a href="${row.documento_adjunto}" target="_blank" class="text-indigo-600 hover:text-indigo-900 mx-1" title="Documento Adjunto"><i class="fas fa-file-alt"></i></a>`;
                        } else {
                            html += `<span class="text-gray-400 mx-1" title="Sin Adjunto"><i class="fas fa-file-alt"></i></span>`;
                        }
                        // Enlace Recibo
                        if (row.recibo) {
                            html += `<a href="${row.recibo}" download class="text-gray-600 hover:text-gray-900 mx-1" title="Descargar Recibo"><i class="fas fa-file-download"></i></a>`;
                        } else {
                            html += `<span class="text-gray-400 mx-1" title="Sin Recibo"><i class="fas fa-file-download"></i></span>`;
                        }
                        // Botón de Edición/Detalle (usando el mismo enlace del folio, sin icono de eliminar que no estaba en el código original)
                        html += `<a href="TR_detalle_transferencias.php?id=${row.id}&MT=true" class="text-yellow-600 hover:text-yellow-900 mx-1" title="Ver Detalle"><i class="fas fa-edit"></i></a>`;

                        return `<div class="flex justify-center">${html}</div>`;
                    }},
                ],
                // Configuración general de DataTables
                language: {
                    url: "https://cdn.datatables.net/plug-ins/2.0.7/i18n/es-ES.json" // Idioma español
                },
                responsive: {
                    details: {
                        display: $.fn.dataTable.Responsive.display.modal( {
                            header: function ( row ) {
                                var data = row.data();
                                return 'Detalles del Folio: ' + data.folio;
                            }
                        } ),
                        renderer: $.fn.dataTable.Responsive.renderer.tableAll({
                            tableClass: 'table'
                        })
                    }
                },
                processing: true,
                serverSide: false, // Usaremos DataTables client-side con AJAX, asumiendo un volumen de datos manejable. Si tienes miles de registros, Server-Side Processing es mejor.
                order: [[0, 'desc']],
                // Botones de Exportación
                dom: '<"flex flex-col md:flex-row justify-between items-center mb-4"lBf>rtip',
                buttons: [
                    {
                        text: '<i class="fas fa-file-excel mr-2"></i> Reporte Excel',
                        className: 'bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded shadow transition duration-150',
                        action: function (e, dt, node, config) {
                            // Construimos la URL para la descarga, incluyendo el parámetro 'reporte: excel'
                            const params = new URLSearchParams(getFilterParams());
                            params.append('reporte', 'excel');
                            window.location.href = `${API_ENDPOINT}?${params.toString()}`;
                        }
                    },
                    {
                        extend: 'print',
                        text: '<i class="fas fa-print mr-2"></i> Imprimir',
                        className: 'bg-gray-400 hover:bg-gray-500 text-white font-bold py-2 px-4 rounded shadow transition duration-150'
                    }
                ]
            });
        }

        /**
         * Manejador de envío del formulario de filtros.
         */
        $('#filter-form').on('submit', function(e) {
            e.preventDefault();
            initializeDataTable(); // Recarga la tabla con los nuevos filtros
        });
        
        /**
         * Limpia el formulario y recarga la tabla.
         */
        function resetFilters() {
            $('#filter-form')[0].reset();
            initializeDataTable(); 
        }

        // Inicializar DataTables al cargar la página
        $(document).ready(function() {
            // Mostrar mensajes de la URL si existen
            const urlParams = new URLSearchParams(window.location.search);
            const msg = urlParams.get('msg');
            const alertDiv = $('#alert-messages');
            
            if (msg) {
                let message, type;
                switch (msg) {
                    case 'success':
                        message = 'Transferencia eliminada exitosamente.';
                        type = 'bg-green-100 border-green-400 text-green-700';
                        break;
                    case 'error':
                        message = 'Error al eliminar la transferencia.';
                        type = 'bg-red-100 border-red-400 text-red-700';
                        break;
                    case 'sqlerror':
                        message = 'Error en la consulta SQL.';
                        type = 'bg-red-100 border-red-400 text-red-700';
                        break;
                    case 'invalidid':
                        message = 'ID de transferencia no válido.';
                        type = 'bg-yellow-100 border-yellow-400 text-yellow-700';
                        break;
                    default:
                        message = null;
                }

                if (message) {
                    alertDiv.html(`
                        <div class="border px-4 py-3 rounded relative ${type}" role="alert">
                            <span class="block sm:inline">${message}</span>
                        </div>
                    `);
                }
                // Limpiar el parámetro de la URL después de mostrar
                history.replaceState(null, null, window.location.pathname);
            }

            initializeDataTable();
        });
    </script>
</body>
</html>