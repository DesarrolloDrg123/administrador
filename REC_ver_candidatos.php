<?php
session_start();
require("config/db.php");
include("src/templates/adminheader.php");

// 1. Seguridad y Validación del ID de la vacante
if (!isset($_SESSION['loggedin']) || !$_SESSION['loggedin']) {
    header("Location: index.php");
    exit();
}

$solicitud_id = $_GET['id'] ?? 0;
if (!is_numeric($solicitud_id) || $solicitud_id <= 0) {
    die("<div class='container mt-5'><div class='alert alert-danger'>ID de solicitud no válido.</div></div>");
}

// 2. Obtener la información de la vacante para el título
$stmt_vacante = $conn->prepare("SELECT puesto_solicitado, folio FROM solicitudes_vacantes WHERE solicitud_id = ?");
$stmt_vacante->bind_param("i", $solicitud_id);
$stmt_vacante->execute();
$vacante = $stmt_vacante->get_result()->fetch_assoc();

if (!$vacante) {
    die("<div class='container mt-5'><div class='alert alert-danger'>Vacante no encontrada.</div></div>");
}

// 3. Obtener la lista de candidatos para esta vacante (usando 'area_interes')
$stmt_candidatos = $conn->prepare(
    "SELECT candidato_id, nombre_completo, correo_electronico, telefono, estatus, cv_adjunto_path 
     FROM solicitudes_vacantes_candidatos 
     WHERE area_interes = ? 
     ORDER BY fecha_captura DESC"
);
$stmt_candidatos->bind_param("s", $vacante['puesto_solicitado']);
$stmt_candidatos->execute();
$candidatos = $stmt_candidatos->get_result()->fetch_all(MYSQLI_ASSOC);

$stmt_vacante->close();
$stmt_candidatos->close();
?>
<style>
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
    .status_confi { background-color: #18212bff; }      /* Gris oscuro, neutral pero distinto del anterior */

    .container { max-width: 1200px; }

    .historial-tabla { width: 100%; border-collapse: collapse; text-align: left; }
    .historial-tabla th, .historial-tabla td { padding: 8px 12px; border: 1px solid #ddd; }
    .historial-tabla th { background-color: #f2f2f2; }
    .swal2-html-container { max-height: 400px; overflow-y: auto; text-align: left; }
</style>

<div class="container mt-4">
    <a href="REC_gestion_vacantes.php" class="btn btn-outline-secondary mb-3"><i class="fas fa-arrow-left me-2"></i>Volver al Panel</a>
    <h1 class="mb-2">Candidatos para: <?= htmlspecialchars($vacante['puesto_solicitado']) ?></h1>
    <h5 class="text-muted mb-4">Folio de la Vacante: <?= htmlspecialchars($vacante['folio']) ?></h5>

    <div class="card shadow-sm">
        <div class="card-body">
            <table class="table table-hover" id="tabla_candidatos">
                <thead>
                    <tr class="text-center">
                        <th>Nombre</th>
                        <th>Contacto</th>
                        <th>Estatus</th>
                        <th>CV</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($candidatos)): ?>
                        <?php foreach ($candidatos as $candidato): 
                            $clase_css = 'status_' . strtolower(str_replace(' ', '_', $candidato['estatus']));
                            $id_candidato = $candidato['candidato_id'];
                            $estatus_candidato = $candidato['estatus'];
                        ?>
                            <tr>
                                <td><?= htmlspecialchars($candidato['nombre_completo']) ?></td>
                                <td>
                                    <i class="fas fa-envelope me-2 text-muted"></i><?= htmlspecialchars($candidato['correo_electronico']) ?><br>
                                    <i class="fas fa-phone me-2 text-muted"></i><?= htmlspecialchars($candidato['telefono']) ?>
                                </td>
                                <td class="text-center">
                                    <span class="badge-status <?= $clase_css ?>"><?= htmlspecialchars($estatus_candidato) ?></span>
                                </td>
                                <td class="text-center">
                                    <?php
                                        // Obtener la carpeta
                                        $carpeta = htmlspecialchars($candidato['cv_adjunto_path']);
                                
                                        // Generar nombre del archivo según el nombre del candidato
                                        $nombre_carpeta_limpio = strtolower(iconv('UTF-8', 'ASCII//TRANSLIT', $candidato['nombre_completo']));
                                        $nombre_carpeta_limpio = preg_replace('/[^a-z0-9\s-]/', '', $nombre_carpeta_limpio);
                                        $nombre_carpeta_limpio = preg_replace('/[\s-]+/', '_', trim($nombre_carpeta_limpio, '_'));
                                
                                        // Buscar archivo existente (PDF, DOC o DOCX)
                                        $extensiones = ['pdf', 'doc', 'docx'];
                                        $ruta_cv = null;

                                        foreach ($extensiones as $ext) {
                                            $ruta_relativa = $carpeta . 'cv_' . $nombre_carpeta_limpio . '.' . $ext;
                                            $ruta_absoluta = $_SERVER['DOCUMENT_ROOT'] . '/' . $ruta_relativa;

                                            if (file_exists($ruta_absoluta)) {
                                                $ruta_cv = $ruta_relativa;
                                                break;
                                            }
                                        }
                                    ?>
                                    <a href="REC_controller/<?= $ruta_cv ?>" target="_blank" class="btn btn-secondary btn-sm" title="Ver CV">
                                        <i class="fas fa-file-download"></i>
                                    </a>
                                </td>
                                <td class="text-center">
                                    <div class="btn-group">
                                        <button class="btn btn-info btn-sm" onclick="mostrarDetalles(this)" data-id="<?= $id_candidato ?>">
                                            <i class="fas fa-eye"></i> Detalles
                                        </button>
                                        <button type="button" class="btn btn-info btn-sm dropdown-toggle dropdown-toggle-split" data-bs-toggle="dropdown" aria-expanded="false"></button>
                                        <ul class="dropdown-menu">
                                            <?php
                                            switch ($estatus_candidato) {
                                                case 'Nuevo Candidato':
                                                    echo '<li><a class="dropdown-item" href="#" onclick="gestionarCandidato('.$id_candidato.', \'En Revision\')">Marcar como Revisado</a></li>';
                                                    echo '<li><a class="dropdown-item text-danger" href="#" onclick="gestionarCandidato('.$id_candidato.', \'Rechazado\')">Rechazar</a></li>';
                                                    break;
                                                case 'En Revision':
                                                    echo '<li><a class="dropdown-item" href="#" onclick="gestionarCandidato('.$id_candidato.', \'Evaluacion Psicometrica\')">Pasar a Ev. Psicométrica</a></li>';
                                                    echo '<li><a class="dropdown-item text-danger" href="#" onclick="gestionarCandidato('.$id_candidato.', \'Rechazado\')">Rechazar</a></li>';
                                                    break;
                                                case 'Evaluacion Psicometrica':
                                                    echo '<li><a class="dropdown-item" href="#" onclick="gestionarCandidato('.$id_candidato.', \'Entrevista con Jefe\')">Pasar a Entrevista con Jefe</a></li>';
                                                    echo '<li><a class="dropdown-item" href="#" onclick="gestionarCandidato('.$id_candidato.', \'Evaluacion Tecnica\')">Pasar a Ev. Técnica</a></li>';
                                                    echo '<li><a class="dropdown-item text-danger" href="#" onclick="gestionarCandidato('.$id_candidato.', \'Rechazado\')">Rechazar</a></li>';
                                                    break;
                                                case 'Evaluacion Tecnica':
                                                    echo '<li><a class="dropdown-item" href="#" onclick="gestionarCandidato('.$id_candidato.', \'Entrevista con Jefe\')">Pasar a Entrevista con Jefe</a></li>';
                                                    echo '<li><a class="dropdown-item text-danger" href="#" onclick="gestionarCandidato('.$id_candidato.', \'Rechazado\')">Rechazar</a></li>';
                                                    break;
                                                case 'Entrevista con Jefe':
                                                    echo '<li><a class="dropdown-item" href="#" onclick="gestionarCandidato('.$id_candidato.', \'Aprobado\')">Aprobar Candidato</a></li>';
                                                    echo '<li><a class="dropdown-item text-danger" href="#" onclick="gestionarCandidato('.$id_candidato.', \'Rechazado\')">Rechazar</a></li>';
                                                    break;
                                                case 'Aprobado':
                                                    echo '<li><a class="dropdown-item" href="#" onclick="gestionarCandidato('.$id_candidato.', \'Espera de Documentos\')">Solicitar Documentos</a></li>';
                                                    //echo '<li><a class="dropdown-item" href="#" onclick="abrirCargaContrato('.$id_candidato.')">Cargar Contrato</a></li>';
                                                    echo '<li><a class="dropdown-item text-danger" href="#" onclick="gestionarCandidato('.$id_candidato.', \'Rechazado\')">Rechazar</a></li>';
                                                    break;
                                                case 'Espera de Documentos':
                                                    echo '<li><a class="dropdown-item" href="#" onclick="gestionarCandidato('.$id_candidato.', \'Espera de Documentos\')">Reenviar Solicitud de Documentos</a></li>';
                                                    echo '<li><a class="dropdown-item text-danger" href="#" onclick="gestionarCandidato('.$id_candidato.', \'Rechazado\')">Rechazar</a></li>';
                                                    break;
                                                case 'Documentos Recibidos':
                                                    echo '<li><a class="dropdown-item" href="#" onclick="gestionarCandidato('.$id_candidato.', \'Espera de Documentos\')">Reenviar Solicitud de Documentos</a></li>';
                                                    echo '<li><a class="dropdown-item" href="#" onclick="gestionarCandidato('.$id_candidato.', \'Documentos Confirmados\')">Confirmar Documentos</a></li>';
                                                    echo '<li><a class="dropdown-item text-danger" href="#" onclick="gestionarCandidato('.$id_candidato.', \'Rechazado\')">Rechazar</a></li>';
                                                    break;
                                            }
                                            ?>
                                            <li><hr class="dropdown-divider"></li>
                                            <li><a class="dropdown-item" href="#" onclick="mostrarHistorial(<?= $id_candidato ?>)">Ver Historial</a></li>
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
    // Inicialización de DataTables para paginación y búsqueda
    $(document).ready(function() {
        $('#tabla_candidatos').DataTable({
            "language": { "url": "//cdn.datatables.net/plug-ins/1.10.16/i18n/Spanish.json" },
            "order": [[0, "asc"]]
        });
    });

    // Función para mostrar los detalles y respuestas del candidato
// En REC_ver_candidatos.php

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

function gestionarCandidato(candidatoId, nuevoEstatus) {
    Swal.fire({
        title: `Cambiar estatus a "${nuevoEstatus}"`,
        html: `
            <p>¿Estás seguro de que deseas cambiar el estatus de este candidato?</p>
            <textarea id="observaciones" class="swal2-textarea" placeholder="Añade un comentario (obligatorio si se rechaza)..."></textarea>
        `,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sí, cambiar estatus',
        cancelButtonText: 'Cancelar',
        preConfirm: () => {
            const observaciones = document.getElementById('observaciones').value;
            // Si el estatus es 'Rechazado', el comentario es obligatorio
            if (nuevoEstatus === 'Rechazado' && !observaciones) {
                Swal.showValidationMessage('El motivo del rechazo es obligatorio.');
                return false;
            }
            return { observaciones: observaciones };
        }
    }).then((result) => {
        if (result.isConfirmed) {
            // Llamada AJAX para actualizar el estatus del candidato
            $.ajax({
                url: 'REC_controller/actualizar_estatus_candidato.php', // NUEVO SCRIPT BACKEND
                type: 'POST',
                data: {
                    id: candidatoId,
                    nuevo_estatus: nuevoEstatus,
                    observaciones: result.value.observaciones
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        Swal.fire('¡Actualizado!', response.message, 'success').then(() => location.reload());
                    } else {
                        Swal.fire('Error', response.message, 'error');
                    }
                },
                error: function() {
                    Swal.fire('Error', 'No se pudo comunicar con el servidor.', 'error');
                }
            });
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

// En REC_ver_candidatos.php, reemplaza tu función 'abrirCargaContrato' por esta:

function abrirCargaContrato(candidatoId) {
    Swal.fire({
        title: 'Gestión de Contrato',
        html: `
            <div style="text-align: left;">
                <p><strong>Paso 1:</strong> Descarga la plantilla del contrato, llénala y fírmala.</p>
                <a href="documentos_base/contrato_base.docx" class="btn btn-outline-primary w-100 mb-4" download>
                    <i class="fas fa-download me-2"></i>Descargar Plantilla de Contrato
                </a>
                
                <p><strong>Paso 2:</strong> Sube el contrato firmado aquí.</p>
                <input type="file" id="contratoArchivo" class="form-control mb-3" accept=".pdf,.doc,.docx" />
                
                <iframe id="previewContrato" style="width:100%; height:400px; display:none; border:1px solid #ddd;"></iframe>
            </div>
        `,
        width: '800px',
        showCancelButton: true,
        confirmButtonText: 'Subir Archivo',
        cancelButtonText: 'Cancelar',
        didOpen: () => {
            // Lógica para la previsualización del archivo
            const inputFile = Swal.getPopup().querySelector('#contratoArchivo');
            const iframe = Swal.getPopup().querySelector('#previewContrato');
            
            inputFile.addEventListener('change', function(e) {
                const file = e.target.files[0];
                if (file) {
                    // Solo los PDF se pueden previsualizar de forma nativa y segura
                    if (file.type === "application/pdf") {
                        iframe.src = URL.createObjectURL(file);
                        iframe.style.display = 'block';
                    } else {
                        // Para otros archivos (DOCX), ocultamos el preview
                        iframe.style.display = 'none';
                        iframe.src = '';
                    }
                }
            });
        },
        preConfirm: () => {
            const archivo = Swal.getPopup().querySelector('#contratoArchivo').files[0];
            if (!archivo) {
                Swal.showValidationMessage('Por favor, selecciona un archivo para subir.');
                return false; // Evita que se cierre el modal
            }
            return archivo;
        }
    }).then(result => {
        if (result.isConfirmed) {
            const file = result.value;
            const formData = new FormData();
            formData.append('id', candidatoId);
            formData.append('contrato_file', file); // El nombre debe coincidir con el del PHP

            Swal.fire({
                title: 'Subiendo...',
                text: 'El contrato se está guardando en el expediente del candidato.',
                allowOutsideClick: false,
                didOpen: () => Swal.showLoading()
            });

            fetch('REC_controller/subir_contrato.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    Swal.fire('¡Éxito!', data.message, 'success').then(() => location.reload());
                } else {
                    Swal.fire('Error', data.message, 'error');
                }
            })
            .catch(err => {
                Swal.fire('Error', 'No se pudo subir el archivo. ' + err.message, 'error');
            });
        }
    });
}

</script>

<?php include("src/templates/adminfooter.php"); ?>