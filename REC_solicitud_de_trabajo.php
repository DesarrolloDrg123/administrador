<?php
// No se necesita session_start() porque es una página pública
require("config/db.php"); // Asegúrate de que la ruta a tu conexión sea correcta

// --- Lógica para obtener las vacantes activas ---
$vacantes_disponibles = [];
try {
    // Asumimos que las vacantes abiertas tienen el estatus 'En Proceso'. 
    // ¡Ajusta este estatus si en tu sistema se llama diferente!
    $sql = "SELECT solicitud_id, puesto_solicitado 
        FROM solicitudes_vacantes 
        WHERE estatus IN ('Publicada', 'En Proceso de Seleccion') 
        ORDER BY puesto_solicitado ASC";
            
    $resultado = $conn->query($sql);
    if ($resultado) {
        $vacantes_disponibles = $resultado->fetch_all(MYSQLI_ASSOC);
    }
} catch (Exception $e) {
    // Si hay un error, la lista de vacantes simplemente estará vacía.
    error_log("Error al cargar vacantes públicas: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bolsa de Trabajo - Aplica a una Vacante</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; }
        .card { border: none; box-shadow: 0 4px 8px rgba(0,0,0,0.1); }
    </style>
</head>
<body>

<div class="container my-5">
    <div class="row justify-content-center">
        <div class="col-lg-10">
            <div class="card">
                <div class="card-body p-4 p-md-5">
                    <div class="d-flex justify-content-center align-items-center mb-4">
                        <img src="img/logo-drg.png" alt="Logo de la Empresa" class="img-fluid me-4" style="max-height: 60px;">
                        <h2 class="card-title mb-0">Aplica a una de Nuestras Vacantes</h2>
                    </div>
                    
                    <p class="text-center text-muted mb-5">Completa el siguiente formulario para postularte. Nos pondremos en contacto contigo a la brevedad.</p>
                    
                    <form id="formCandidato" enctype="multipart/form-data">

                        <h4 class="mb-3">Puesto de Interés</h4>
                        <div class="mb-4">
                            <label for="solicitud_id" class="form-label">Vacante a la que aplicas <span class="text-danger">*</span></label>
                            <select class="form-select" id="solicitud_id" name="solicitud_id" required>
                                <option value="" disabled selected>Selecciona un puesto...</option>
                                <?php if (!empty($vacantes_disponibles)): ?>
                                    <?php foreach ($vacantes_disponibles as $vacante): ?>
                                        <option value="<?= $vacante['solicitud_id'] ?>">
                                            <?= htmlspecialchars($vacante['puesto_solicitado']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <option value="" disabled>No hay vacantes disponibles por el momento.</option>
                                <?php endif; ?>
                            </select>
                        </div>
                        
                            
                        <h4 class="mb-3">I. Información Personal</h4>
                        <div class="row g-3">
                            <div class="col-md-8"><label for="nombre_completo" class="form-label">Nombre completo <span class="text-danger">*</span></label><input type="text" class="form-control" id="nombre_completo" name="nombre_completo" required></div>
                            <div class="col-md-4"><label for="edad" class="form-label">Edad <span class="text-danger">*</span></label><input type="number" class="form-control" id="edad" name="edad" required></div>
                            <div class="col-md-6"><label for="correo_electronico" class="form-label">Correo electrónico <span class="text-danger">*</span></label><input type="email" class="form-control" id="correo_electronico" name="correo_electronico" required></div>
                            <div class="col-md-6"><label for="telefono" class="form-label">Teléfono <span class="text-danger">*</span></label><input type="tel" class="form-control" id="telefono" name="telefono" required></div>
                            <div class="col-md-6"><label for="idiomas" class="form-label">Idiomas <span class="text-danger">*</span></label><input type="tel" class="form-control" id="idiomas" name="idiomas" required></div>
                            <div class="col-12"><label for="ciudad_residencia" class="form-label">Ciudad de residencia <span class="text-danger">*</span></label><input type="text" class="form-control" id="ciudad_residencia" name="ciudad_residencia" required></div>
                        </div>

                        <h4 class="mb-3 mt-5">II. Información Profesional</h4>
                        <div class="row g-3">
                            <div class="col-md-6"><label for="nivel_estudios" class="form-label">Nivel de estudios <span class="text-danger">*</span></label><select class="form-select" id="nivel_estudios" name="nivel_estudios" required><option value="" disabled selected>Selecciona...</option><option>Técnico</option><option>Preparatoria</option><option>Licenciatura</option><option>Maestría</option><option>Doctorado</option></select></div>
                            <div class="col-md-6"><label for="carrera" class="form-label">Carrera o especialidad <span class="text-danger">*</span></label><input type="text" class="form-control" id="carrera" name="carrera" required></div>
                            <div class="col-md-6"><label for="experiencia_laboral_anios" class="form-label">Años de experiencia laboral <span class="text-danger">*</span></label><input type="number" class="form-control" id="experiencia_laboral_anios" name="experiencia_laboral_anios" required></div>
                             <div class="col-md-6"><label for="rango_salarial_deseado" class="form-label">Rango salarial deseado (mensual) <span class="text-danger">*</span></label><input type="number" step="100" class="form-control" id="rango_salarial_deseado" name="rango_salarial_deseado" required></div>
                            <div class="col-12"><label for="habilidades_tecnicas" class="form-label">Habilidades técnicas / Software <span class="text-danger">*</span></label><textarea class="form-control" id="habilidades_tecnicas" name="habilidades_tecnicas" rows="3" required></textarea></div>
                        </div>
                        
                        <div id="preguntas-especificas-container" class="mb-4">
                            </div>

                        <h4 class="mb-3 mt-5">III. Información Adicional</h4>
                            
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">¿Disponibilidad para viajar? <span class="text-danger">*</span></label>
                                <div><div class="form-check form-check-inline"><input class="form-check-input" type="radio" name="disponibilidad_viajar" id="viajar_si" value="1" required><label class="form-check-label" for="viajar_si">Sí</label></div><div class="form-check form-check-inline"><input class="form-check-input" type="radio" name="disponibilidad_viajar" id="viajar_no" value="0" required><label class="form-check-label" for="viajar_no">No</label></div></div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">¿Cuentas con vehículo propio? <span class="text-danger">*</span></label>
                                <div>
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="radio" name="vehiculo_propio" id="vehiculo_si" value="1" required>
                                        <label class="form-check-label" for="vehiculo_si">Sí</label>
                                    </div>
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="radio" name="vehiculo_propio" id="vehiculo_no" value="0" required>
                                        <label class="form-check-label" for="vehiculo_no">No</label>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6"><label for="disponibilidad_trabajar" class="form-label">Disponibilidad para empezar a trabajar <span class="text-danger">*</span></label><select class="form-select" id="disponibilidad_trabajar" name="disponibilidad_trabajar" required><option value="" disabled selected>Selecciona...</option><option>Inmediata</option><option>1-2 semanas</option><option>1 mes</option></select></div>
                            <div class="col-md-6"><label for="fuente_vacante" class="form-label">¿Cómo te enteraste de la vacante?</label><input type="text" class="form-control" id="fuente_vacante" name="fuente_vacante" placeholder="Ej: LinkedIn, recomendación, etc."></div>
                            <div class="col-12">
                                <label for="cv_adjunto" class="form-label">Adjuntar CV (PDF, DOC, DOCX) <span class="text-danger">*</span></label>
                                <input class="form-control" type="file" id="cv_adjunto" name="cv_adjunto" accept=".pdf,.doc,.docx" required>
                            </div>
                        </div>
                        <h4 class="mb-3 mt-5">IV. Referencias Laborales (Opcional)</h4>
                        <p class="text-muted">Proporciona el contacto de hasta tres personas que puedan dar referencias sobre tu desempeño profesional.</p>
                        
                        <h5 class="mt-4 text-primary">Referencia 1</h5>
                        <div class="row g-3">
                            <div class="col-md-6"><label for="ref1_nombre" class="form-label">Nombre Completo</label><input type="text" class="form-control" id="ref1_nombre" name="ref1_nombre"></div>
                            <div class="col-md-6"><label for="ref1_contacto" class="form-label">Teléfono o Email de Contacto</label><input type="text" class="form-control" id="ref1_contacto" name="ref1_contacto"></div>
                            <div class="col-md-6"><label for="ref1_empresa" class="form-label">Nombre de la Empresa</label><input type="text" class="form-control" id="ref1_empresa" name="ref1_empresa"></div>
                            <div class="col-md-6"><label for="ref1_puesto" class="form-label">Puesto que Desempeña</label><input type="text" class="form-control" id="ref1_puesto" name="ref1_puesto"></div>
                        </div>
                        <hr class="my-4">
                        
                        <h5 class="text-primary">Referencia 2</h5>
                        <div class="row g-3">
                            <div class="col-md-6"><label for="ref2_nombre" class="form-label">Nombre Completo</label><input type="text" class="form-control" id="ref2_nombre" name="ref2_nombre"></div>
                            <div class="col-md-6"><label for="ref2_contacto" class="form-label">Teléfono o Email de Contacto</label><input type="text" class="form-control" id="ref2_contacto" name="ref2_contacto"></div>
                            <div class="col-md-6"><label for="ref2_empresa" class="form-label">Nombre de la Empresa</label><input type="text" class="form-control" id="ref2_empresa" name="ref2_empresa"></div>
                            <div class="col-md-6"><label for="ref2_puesto" class="form-label">Puesto que Desempeña</label><input type="text" class="form-control" id="ref2_puesto" name="ref2_puesto"></div>
                        </div>
                        <hr class="my-4">
                        
                        <h5 class="text-primary">Referencia 3</h5>
                        <div class="row g-3">
                            <div class="col-md-6"><label for="ref3_nombre" class="form-label">Nombre Completo</label><input type="text" class="form-control" id="ref3_nombre" name="ref3_nombre"></div>
                            <div class="col-md-6"><label for="ref3_contacto" class="form-label">Teléfono o Email de Contacto</label><input type="text" class="form-control" id="ref3_contacto" name="ref3_contacto"></div>
                            <div class="col-md-6"><label for="ref3_empresa" class="form-label">Nombre de la Empresa</label><input type="text" class="form-control" id="ref3_empresa" name="ref3_empresa"></div>
                            <div class="col-md-6"><label for="ref3_puesto" class="form-label">Puesto que Desempeña</label><input type="text" class="form-control" id="ref3_puesto" name="ref3_puesto"></div>
                        </div>
                        <div class="mt-5 text-center">
                            <button type="submit" class="btn btn-primary btn-lg px-5">Enviar Postulación</button>
                        </div>

                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    
    const selectVacante = document.getElementById('solicitud_id');
    const preguntasContainer = document.getElementById('preguntas-especificas-container');
    const form = document.getElementById('formCandidato');

    selectVacante.addEventListener('change', function() {
        const vacanteId = this.value;
        preguntasContainer.innerHTML = ''; // Limpia el contenedor

        if (!vacanteId) {
            return; // Si no hay vacante seleccionada, no hace nada
        }

        // Muestra un indicador de carga
        preguntasContainer.innerHTML = '<p class="text-muted">Cargando preguntas...</p>';

        fetch(`REC_controller/obtener_preguntas_vacante.php?id=${vacanteId}`)
            .then(response => response.json())
            .then(data => {
                preguntasContainer.innerHTML = ''; // Limpia el mensaje de carga

                if (data.success && data.preguntas.length > 0) {
                    // Si hay preguntas, crea los campos de texto
                    let html = '';
                    data.preguntas.forEach(pregunta => {
                        html += `
                            <div class="mb-3">
                                <label for="pregunta_${pregunta.pregunta_id}" class="form-label">${pregunta.pregunta_texto} <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="pregunta_${pregunta.pregunta_id}" name="respuestas[${pregunta.pregunta_id}]" required>
                            </div>
                        `;
                    });
                    preguntasContainer.innerHTML = html;
                }
            })
            .catch(error => {
                console.error('Error al cargar las preguntas:', error);
                preguntasContainer.innerHTML = '<p class="text-danger">No se pudieron cargar las preguntas.</p>';
            });
    });


    // --- Lógica de envío del formulario (la que ya tenías) ---
    form.addEventListener('submit', function(event) {
        event.preventDefault();

        const formData = new FormData(form);

        Swal.fire({
            title: 'Enviando postulación...',
            text: 'Por favor, espera un momento.',
            allowOutsideClick: false,
            didOpen: () => Swal.showLoading()
        });

        fetch('REC_controller/guardar_candidato.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                Swal.fire({
                    icon: 'success',
                    title: '¡Postulación Enviada!',
                    text: 'Hemos recibido tus datos correctamente. Gracias por tu interés.',
                }).then(() => {
                    form.reset();
                    preguntasContainer.innerHTML = ''; // Limpia también las preguntas
                });
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Error al Enviar',
                    text: data.message || 'Ocurrió un error. Por favor, revisa tus datos.'
                });
            }
        })
        .catch(error => {
            console.error('Error:', error);
            Swal.fire({
                icon: 'error',
                title: 'Error de Conexión',
                text: 'No se pudo comunicar con el servidor.'
            });
        });
    });

});
</script>

</body>
</html>