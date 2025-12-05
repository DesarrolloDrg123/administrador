<?php

if (isset($_POST['submit_comprobantes'])) {
    
    // 1. Obtener y sanitizar los datos del formulario
    // Asegúrate de que $solicitud['folio'] ya esté definido y contenga el folio correcto
    $folio = $_POST['folio_solicitud'] ?? ''; // Usamos el hidden input para asegurar el folio
    
    // Sanitización de datos
    $importe = filter_input(INPUT_POST, 'importe_comprobante', FILTER_VALIDATE_FLOAT);
    // Usamos FILTER_SANITIZE_STRING (o sus equivalentes modernos) para limpiar la descripción
    $descripcion = trim($_POST['descripcion_comprobante']);
    
    $evidencia_file = $_FILES['evidencia_comprobante'];
    $tipo_comprobante = "Recibo No Deducible"; // Valor fijo
    
    // --- Validación Básica ---
    if (empty($folio) || $importe === false || $importe <= 0 || empty($descripcion) || $evidencia_file['error'] != UPLOAD_ERR_OK) {
        $GLOBALS["mensaje_global"] = '<div class="alert alert-danger">Error: Faltan datos obligatorios o el importe es inválido.</div>';
    } else {
        
        // 2. Definir la ruta de destino y subir el archivo
        
        $target_dir_base = "uploads/comprobantes/"; // RUTA RELATIVA AL ARCHIVO PHP QUE INCLUYE EL CONTROLADOR (probablemente el index/raiz)
        $target_dir = $target_dir_base . htmlspecialchars($folio) . "/"; 

        // Crea la carpeta si no existe
        if (!is_dir($target_dir)) {
            mkdir($target_dir, 0777, true); 
        }

        // Generar un nombre único para el archivo
        $file_extension = pathinfo($evidencia_file['name'], PATHINFO_EXTENSION);
        $file_name_unique = time() . "_" . uniqid() . "." . $file_extension;
        $target_file = $target_dir . $file_name_unique;
        $evidencia_db_path = $target_file; // La ruta que guardaremos en la DB
        
        // Mover el archivo subido
        if (move_uploaded_file($evidencia_file["tmp_name"], $target_file)) {
            
            // 3. Inserción en la Base de Datos
            $sql = "INSERT INTO comprobantes_tcl (folio, tipo_comprobante, importe, descripcion, evidencia) VALUES (?, ?, ?, ?, ?)";
            
            if ($stmt_insert = $conn->prepare($sql)) {
                
                // Tipo de parámetros: s=string, s=string, d=double/float, s=string, s=string
                $stmt_insert->bind_param("ssdds", $folio, $tipo_comprobante, $importe, $descripcion, $evidencia_db_path);
                
                if ($stmt_insert->execute()) {
                    // Éxito:
                    $GLOBALS["mensaje_global"] = '<div class="alert alert-success">Comprobante subido y registrado con éxito.</div>';
                    
                    // Opcional: Redirigir para evitar el reenvío del formulario
                    // header("Location: detalle_transferencia.php?id=$solicitud_id"); 
                    // exit();
                } else {
                    $GLOBALS["mensaje_global"] = '<div class="alert alert-danger">Error al registrar en la BD: ' . $stmt_insert->error . '</div>';
                }
                $stmt_insert->close();
            } else {
                $GLOBALS["mensaje_global"] = '<div class="alert alert-danger">Error de preparación de la consulta: ' . $conn->error . '</div>';
            }
            
        } else {
            $GLOBALS["mensaje_global"] = '<div class="alert alert-danger">Error al mover el archivo de evidencia.</div>';
        }
    }
}
?>