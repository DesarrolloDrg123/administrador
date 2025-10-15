<?php
    session_start();
    require("config/db.php");
    include("src/templates/adminheader.php");
    
    if (!isset($_SESSION['loggedin']) || !$_SESSION['loggedin']) {
        header("Location: index.php");
        exit();
    }
    
    // Función para obtener TODOS los candidatos
    function obtenerTodosLosCandidatos($conn) {
        $sql = "SELECT 
                    candidato_id,
                    nombre_completo,
                    correo_electronico,
                    telefono,
                    area_interes,
                    estatus,
                    fecha_captura
                FROM 
                    solicitudes_vacantes_candidatos
                ORDER BY 
                    fecha_captura DESC";
        $result = $conn->query($sql);
        return ($result && $result->num_rows > 0) ? $result->fetch_all(MYSQLI_ASSOC) : [];
    }
    
    $todos_los_candidatos = obtenerTodosLosCandidatos($conn);

    // --- Obtener datos para los filtros ---
    $puestos_filtro = array_unique(array_column($todos_los_candidatos, 'area_interes'));
    $estatus_filtro = array_unique(array_column($todos_los_candidatos, 'estatus'));
    sort($puestos_filtro);
    sort($estatus_filtro);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        /* (Tus estilos para los badges de estatus y tablas se pueden copiar aquí) */
        .badge-status { padding: 5px 12px; border-radius: 15px; color: white; font-weight: bold; }
        .status_nuevo_candidato { background-color: #299dbf; }          /* Azul base de la empresa */
        .status_en_revision { background-color: #f1c40f; }               /* Amarillo cálido, cercano a verde/azul */
        .status_evaluacion_psicometrica { background-color: #16a085; }   /* Verde-azulado, armoniza con verde empresa */
        .status_rechazado { background-color: #e74c3c; }                 /* Rojo, sigue destacando para rechazo */
        .status_evaluacion_tecnica { background-color: #2980b9; }        /* Azul oscuro, armoniza con azul empresa */
        .status_entrevista_con_jefe { background-color: #8e44ad; }       /* Morado suave, distingue etapas avanzadas */
        .status_aprobado { background-color: #80bf1f; }                  /* Verde de la empresa */
        .status_espera_de_documentos { background-color: #34495e; }      /* Gris azulado, neutral */
        .status_documentos_recibidos { background-color: #2c3e50; }      /* Gris oscuro, neutral pero distinto del anterior */

        
        .container { max-width: 90%; }

        .historial-tabla { width: 100%; border-collapse: collapse; text-align: left; }
        .historial-tabla th, .historial-tabla td { padding: 8px 12px; border: 1px solid #ddd; }
        .historial-tabla th { background-color: #f2f2f2; }
        .swal2-html-container { max-height: 400px; overflow-y: auto; text-align: left; }
    </style>
</head>
<body>
    <div class="container mt-4">
        <h1 class="text-center mb-4">Bolsa de Trabajo Interna</h1>

        <div class="row g-3 mb-4">
            <div class="col-md-6">
                <label for="filtro_puesto" class="form-label">Filtrar por Puesto de Interés</label>
                <select id="filtro_puesto" class="form-select">
                    <option value="">Todos</option>
                    <?php foreach ($puestos_filtro as $puesto): ?>
                        <option value="<?= htmlspecialchars($puesto) ?>"><?= htmlspecialchars($puesto) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-6">
                <label for="filtro_estatus" class="form-label">Filtrar por Estatus</label>
                <select id="filtro_estatus" class="form-select">
                    <option value="">Todos</option>
                    <?php foreach ($estatus_filtro as $estatus): ?>
                        <option value="<?= htmlspecialchars($estatus) ?>"><?= htmlspecialchars($estatus) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="card shadow-sm">
            <div class="card-body">
                <table class="table table-hover" id="tabla_candidatos_general">
                    <thead>
                        <tr class="text-center">
                            <th>Nombre</th>
                            <th>Contacto</th>
                            <th>Puesto de Interés</th>
                            <th>Fecha de Postulación</th>
                            <th>Estatus</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($todos_los_candidatos)): ?>
                            <?php foreach ($todos_los_candidatos as $candidato):
                                $clase_css = 'status_' . strtolower(str_replace(' ', '_', $candidato['estatus']));
                            ?>
                                <tr>
                                    <td><?= htmlspecialchars($candidato['nombre_completo']) ?></td>
                                    <td>
                                        <i class="fas fa-envelope me-2 text-muted"></i><?= htmlspecialchars($candidato['correo_electronico']) ?><br>
                                        <i class="fas fa-phone me-2 text-muted"></i><?= htmlspecialchars($candidato['telefono']) ?>
                                    </td>
                                    <td><?= htmlspecialchars($candidato['area_interes']) ?></td>
                                    <td class="text-center"><?= date('d/m/Y', strtotime($candidato['fecha_captura'])) ?></td>
                                    <td class="text-center">
                                        <span class="badge-status <?= $clase_css ?>"><?= htmlspecialchars($candidato['estatus']) ?></span>
                                    </td>
                                    <td class="text-center">
                                        <div class="btn-group">
                                            <!-- Botón principal -->
                                            <button type="button" class="btn btn-info btn-sm" onclick="mostrarDetalles(this)" data-id="<?= $candidato['candidato_id'] ?>">
                                                <i class="fas fa-eye"></i> Detalles
                                            </button>
                                    
                                            <!-- Botón dropdown -->
                                            <button type="button" class="btn btn-info btn-sm dropdown-toggle dropdown-toggle-split" data-bs-toggle="dropdown" aria-expanded="false">
                                                <span class="visually-hidden">Toggle Dropdown</span>
                                            </button>
                                    
                                            <ul class="dropdown-menu">
                                                <li>
                                                    <a class="dropdown-item" href="javascript:void(0);" onclick="mostrarHistorial(<?= $candidato['candidato_id'] ?>)">
                                                        <i class="fas fa-history me-2"></i> Ver Historial
                                                    </a>
                                                </li>
                                                <li>
                                                    <a class="dropdown-item" href="javascript:void(0);" onclick="subirDocumentos(<?= $candidato['candidato_id'] ?>)">
                                                        <i class="fas fa-upload me-2"></i> Subir Documentos
                                                    </a>
                                                </li>
                                                <li>
                                                    <a class="dropdown-item" href="javascript:void(0);" onclick="verDocumentos(<?= $candidato['candidato_id'] ?>)">
                                                        <i class="fas fa-folder-open me-2"></i> Ver Documentos
                                                    </a>
                                                </li>
                                            </ul>
                                        </div>
                                    </td>

                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        $(document).ready(function() {
            var table = $('#tabla_candidatos_general').DataTable({
                "language": { "url": "//cdn.datatables.net/plug-ins/1.10.16/i18n/Spanish.json" }
            });

            // Lógica de filtrado
            $('#filtro_puesto, #filtro_estatus').on('change', function() {
                let puesto = $('#filtro_puesto').val();
                let estatus = $('#filtro_estatus').val();
                
                table.column(2).search(puesto).draw();
                table.column(4).search(estatus).draw();
            });
        });

        function mostrarDetalles(btn) {
            const candidatoId = btn.dataset.id;
        
            Swal.fire({
                title: 'Cargando detalles...',
                allowOutsideClick: false,
                didOpen: () => Swal.showLoading()
            });
        
            $.ajax({
                url: 'REC_controller/obtener_respuestas_candidato.php',
                type: 'GET',
                data: { id: candidatoId },
                dataType: 'json',
                success: function(response) {
                    if (response.success && response.detalles) {
                        const detalles = response.detalles;
                        
                        let html = `
                            <div style="text-align: left; padding: 15px;">
                                <div class="row">
                                    <div class="col-md-6">
                                        <h4 style="border-bottom: 1px solid #eee; padding-bottom: 10px; margin-bottom: 15px;">Información Profesional</h4>
                                        <ul class="detail-list">
                                            <li><strong>Nivel de Estudios:</strong> ${detalles.nivel_estudios}</li>
                                            <li><strong>Carrera:</strong> ${detalles.carrera || 'No especificado'}</li>
                                            <li><strong>Años de Experiencia:</strong> ${detalles.experiencia_laboral_anios || 'No especificado'}</li>
                                            <li><strong>Sueldo Deseado:</strong> ${detalles.rango_salarial_deseado ? '$' + parseFloat(detalles.rango_salarial_deseado).toLocaleString('es-MX') : 'No especificado'}</li>
                                        </ul>
                                    </div>
                                    <div class="col-md-6">
                                        <h4 style="border-bottom: 1px solid #eee; padding-bottom: 10px; margin-bottom: 15px;">Información Adicional</h4>
                                        <ul class="detail-list">
                                            <li><strong>Disponibilidad para Viajar:</strong> ${detalles.disponibilidad_viajar == 1 ? 'Sí' : 'No'}</li>
                                            <li><strong>Vehículo Propio:</strong> ${detalles.vehiculo_propio == 1 ? 'Sí' : 'No'}</li>
                                            <li><strong>Disponibilidad para Iniciar:</strong> ${detalles.disponibilidad_trabajar}</li>
                                            <li><strong>Fuente:</strong> ${detalles.fuente_vacante || 'No especificado'}</li>
                                        </ul>
                                    </div>
                                </div>
                        `;
        
                        // Añadir sección de respuestas si existen
                        if (response.respuestas.length > 0) {
                            html += `
                                <div class="row mt-3">
                                    <div class="col-12">
                                        <h4 style="border-bottom: 1px solid #eee; padding-bottom: 10px; margin-bottom: 15px;">Respuestas a Preguntas Específicas</h4>
                                        <ul class="detail-list">
                            `;
                            response.respuestas.forEach(item => {
                                html += `<li><strong>${item.pregunta_texto}</strong><span>${item.respuesta_texto}</span></li>`;
                            });
                            html += '</ul></div></div>';
                        }
                        if (detalles.ref1_nombre) {
                             html += `
                                <div class="row mt-3">
                                    <div class="col-12">
                                        <h4 style="border-bottom: 1px solid #eee; padding-bottom: 10px; margin-bottom: 15px;">Referencias Laborales</h4>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <ul class="detail-list">
                                            <li><strong>Nombre (Ref. 1):</strong> ${detalles.ref1_nombre}</li>
                                            <li><strong>Contacto (Ref. 1):</strong> ${detalles.ref1_contacto || 'N/A'}</li>
                                            <li><strong>Empresa (Ref. 1):</strong> ${detalles.ref1_empresa || 'N/A'}</li>
                                            <li><strong>Puesto (Ref. 1):</strong> ${detalles.ref1_puesto || 'N/A'}</li>
                                        </ul>
                                    </div>
        
                                    ${detalles.ref2_nombre ? `
                                    <div class="col-md-6">
                                        <ul class="detail-list">
                                            <li><strong>Nombre (Ref. 2):</strong> ${detalles.ref2_nombre}</li>
                                            <li><strong>Contacto (Ref. 2):</strong> ${detalles.ref2_contacto || 'N/A'}</li>
                                            <li><strong>Empresa (Ref. 2):</strong> ${detalles.ref2_empresa || 'N/A'}</li>
                                            <li><strong>Puesto (Ref. 2):</strong> ${detalles.ref2_puesto || 'N/A'}</li>
                                        </ul>
                                    </div>
                                    ` : ''}
                                </div>
                             `;
                        }
                         // Referencia 3 (solo si existe, en una nueva fila para que no se vea apretado)
                         if (detalles.ref3_nombre) {
                            html += `
                                <div class="row mt-3">
                                    <div class="col-md-6">
                                        <ul class="detail-list">
                                            <li><strong>Nombre (Ref. 3):</strong> ${detalles.ref3_nombre}</li>
                                            <li><strong>Contacto (Ref. 3):</strong> ${detalles.ref3_contacto || 'N/A'}</li>
                                            <li><strong>Empresa (Ref. 3):</strong> ${detalles.ref3_empresa || 'N/A'}</li>
                                            <li><strong>Puesto (Ref. 3):</strong> ${detalles.ref3_puesto || 'N/A'}</li>
                                        </ul>
                                    </div>
                                </div>
                            `;
                         }
        
                        html += '</div>'; // Cierre del div principal
        
                        Swal.fire({
                            title: `Detalles de ${detalles.nombre_completo}`,
                            html: html,
                            width: '900px',
                            confirmButtonText: 'Cerrar'
                        });
        
                    } else {
                         Swal.fire('Error', 'No se pudieron cargar los detalles del candidato.', 'error');
                    }
                },
                error: function() {
                    Swal.fire('Error de Conexión', 'No fue posible comunicarse con el servidor.', 'error');
                }
            });
        }

        function mostrarHistorial(candidatoId) {
            Swal.fire({
                title: 'Cargando historial...',
                allowOutsideClick: false,
                didOpen: () => Swal.showLoading()
            });
        
            $.ajax({
                url: 'REC_controller/obtener_historial_candidato.php', // Apunta al script correcto
                type: 'GET',
                data: { id: candidatoId },
                dataType: 'json',
                success: function(response) {
                    if (response.success && response.historial.length > 0) {
                        let htmlTabla = `
                            <table class="historial-tabla">
                                <thead>
                                    <tr>
                                        <th>Fecha</th>
                                        <th>Usuario</th>
                                        <th>Estatus anterior</th>
                                        <th>Estatus nuevo</th>
                                        <th>Comentarios</th>
                                    </tr>
                                </thead>
                                <tbody>
                        `;
                        response.historial.forEach(item => {
                            const fecha = new Date(item.fecha_accion).toLocaleString('es-MX');
                            htmlTabla += `
                                <tr>
                                    <td>${fecha}</td>
                                    <td>${item.usuario_accion}</td>
                                    <td>${item.estatus_anterior}</td>
                                    <td>${item.estatus_nuevo}</td>
                                    <td>${item.comentarios || '<em>N/A</em>'}</td>
                                </tr>
                            `;
                        });
                        htmlTabla += '</tbody></table>';
        
                        Swal.fire({
                            title: `Historial del Candidato`,
                            html: htmlTabla,
                            width: '900px',
                            confirmButtonText: 'Cerrar'
                        });
                    } else {
                        Swal.fire('Sin Registros', 'No se encontró historial para este candidato.', 'info');
                    }
                },
                error: function() {
                    Swal.fire('Error', 'No se pudo obtener el historial.', 'error');
                }
            });
        }
        
        function subirDocumentos(candidatoId) {
            Swal.fire({
                title: 'Selecciona el/los archivos',
                html: `<input type="file" id="archivos" multiple>`,
                showCancelButton: true,
                confirmButtonText: 'Subir',
                preConfirm: () => {
                    const input = Swal.getPopup().querySelector('#archivos');
                    if (!input.files.length) {
                        Swal.showValidationMessage('Debes seleccionar al menos un archivo');
                        return false;
                    }
                    return input.files;
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    const files = result.value;
                    const formData = new FormData();
                    formData.append('id', candidatoId);
                    for (let i = 0; i < files.length; i++) {
                        formData.append('archivos[]', files[i]);
                    }
        
                    Swal.fire({
                        title: 'Subiendo archivos...',
                        allowOutsideClick: false,
                        didOpen: () => Swal.showLoading()
                    });
        
                    fetch('REC_controller/subir_documentos.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        Swal.close();
                        if (data.success) {
                            Swal.fire('Éxito', data.message, 'success');
                        } else {
                            Swal.fire('Error', data.message, 'error');
                        }
                    })
                    .catch(() => {
                        Swal.fire('Error', 'No se pudo subir los archivos.', 'error');
                    });
                }
            });
        }
        
        function verDocumentos(candidatoId) {
            Swal.fire({
                title: 'Cargando documentos...',
                allowOutsideClick: false,
                didOpen: () => Swal.showLoading()
            });
        
            fetch('REC_controller/listar_documentos.php?id=' + candidatoId)
                .then(res => res.json())
                .then(data => {
                    Swal.close();
                    if (data.success) {
                        if (data.archivos.length === 0) {
                            Swal.fire('Sin Archivos', 'No se encontraron documentos.', 'info');
                            return;
                        }
                        let html = '<ul>';
                        data.archivos.forEach(file => {
                            html += `<li><a href="REC_controller/${file.ruta}" target="_blank">${file.nombre}</a></li>`;
                        });
                        html += '</ul>';
                        Swal.fire({
                            title: 'Documentos del candidato',
                            html: html,
                            width: '600px'
                        });
                    } else {
                        Swal.fire('Error', data.message, 'error');
                    }
                })
                .catch(() => {
                    Swal.fire('Error', 'No se pudieron obtener los documentos.', 'error');
                });
        }


    </script>

<?php include("src/templates/adminfooter.php"); ?>
</body>
</html>