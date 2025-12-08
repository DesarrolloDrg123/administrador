<?php
    // Verificar si la sesión no ha sido iniciada
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }

    // Usamos __DIR__ para asegurar que encuentra los archivos en la misma carpeta que este controlador
    include "validate_sat.php";
    include "validate_xml.php";

    date_default_timezone_set("America/Mexico_City");
    $dateTimeNow = date("Y-m-d H:i:s");
    $mensaje_global="";
    $mensaje_global_pdf_result="<h5>Seleccione el archivo pdf:</h5>";
    $mensaje_global_xml_result="<h5>Seleccione el archivo xml:</h5>";

    // Validar que la sesión tenga el email, si no, asignar uno por defecto o manejar el error
    $email = isset($_SESSION['email']) ? $_SESSION['email'] : 'usuario@sistema.com';
    
    $facturas_path = $_SERVER['DOCUMENT_ROOT'] . "/facturas/";
    $backup_facturas_path = $_SERVER['DOCUMENT_ROOT'] . "/facturas/backup/";

    // Crear directorios si no existen
    if (!file_exists($facturas_path)) { mkdir($facturas_path, 0777, true); }
    if (!file_exists($backup_facturas_path)) { mkdir($backup_facturas_path, 0777, true); }

    $tipo_archivo=1;
    
    $errores = array();
    $tipoerror = array();

    // --- VARIABLES DE CONTROL DE MODALES ---
    $mensajes_exito = array();      
    $mostrar_modal_errores = false; 
    $mostrar_modal_exito = false;   
    // ---------------------------------
    $mostrar_modal = false;

    // IMPORTANTE: Cambiado a 'submit_facturas' para coincidir con el name del botón en el HTML
    if(isset($_POST['submit_facturas']))
    {
        try
        {
            // Validar que se hayan enviado archivos
            if (isset($_FILES['file_pdf']) && isset($_FILES['file_xml'])) {
                
                // Filtrar entradas vacías (en caso de que el JS haya dejado inputs vacíos)
                $valid_indices = [];
                foreach ($_FILES['file_pdf']['name'] as $k => $v) {
                    if (!empty($_FILES['file_pdf']['name'][$k]) && !empty($_FILES['file_xml']['name'][$k])) {
                        $valid_indices[] = $k;
                    }
                }

                if(count($valid_indices) > 0){
                    
                    // Iterar solo sobre los índices válidos
                    foreach ($valid_indices as $i) {
                        $is_correct_pdf=false;
                        $is_correct_xml=false;
                        $pdf_errors= array();
                        $xml_errors= array();
                        
                        $pdf_file_name = $_FILES['file_pdf']['name'][$i];
                        $pdf_file_size = $_FILES['file_pdf']['size'][$i];
                        $pdf_file_tmp  = $_FILES['file_pdf']['tmp_name'][$i];
                        $pdf_file_type = $_FILES['file_pdf']['type'][$i];
                        $pdf_file_ext  = pathinfo(strtolower($pdf_file_name), PATHINFO_EXTENSION);
    
                        $xml_file_name = $_FILES['file_xml']['name'][$i];
                        $xml_file_size = $_FILES['file_xml']['size'][$i];
                        $xml_file_tmp  = $_FILES['file_xml']['tmp_name'][$i];
                        $xml_file_type = $_FILES['file_xml']['type'][$i];
                        $xml_file_ext  = pathinfo(strtolower($xml_file_name), PATHINFO_EXTENSION);
                        
                        $ordenCompra = isset($_POST['ordenCompra'][$i]) ? $_POST['ordenCompra'][$i] : '';
    
                        $pdf_extensions= array("pdf");
                        $xml_extensions= array("xml");
                        
                        if(in_array($pdf_file_ext,$pdf_extensions)=== false){
                           $pdf_errors[]='La extensión del archivo debe ser pdf';
                        }
                        if(in_array($xml_file_ext,$xml_extensions)=== false){
                           $xml_errors[]='La extensión del archivo debe ser xml';
                        }

                        if(empty($pdf_errors)==true && empty($xml_errors)==true){
                           $pdf_file_name=strtolower($pdf_file_name);
                           $xml_file_name=strtolower($xml_file_name);

                            if(empty($ordenCompra)==true)
                            {
                                $errores[] = "Orden de Compra no registrada para el archivo " . $pdf_file_name;
                                $tipoerror[] = "Error de Datos";
                            } 
                            else
                            {
                                if (move_uploaded_file($xml_file_tmp,$facturas_path.$xml_file_name)) 
                                {
                                    $is_correct_xml=true;
                                } else {
                                    $errores[] = "Error al mover el archivo XML al servidor";
                                    $tipoerror[] = $xml_file_name;
                                }
        
                                if (move_uploaded_file($pdf_file_tmp,$facturas_path.$pdf_file_name)) {
                                    $is_correct_pdf=true;
                                } else {
                                    $errores[] = "Error al mover el archivo PDF al servidor";
                                    $tipoerror[] = $pdf_file_name;
                                }
        
                                if ($is_correct_xml && $is_correct_pdf) {
                                    
                                    // Verificar si el folio existe en transferencias
                                    // Se usa sentencias preparadas para seguridad
                                    $stmt_check = $conn->prepare("SELECT id FROM transferencias_clara_tcl WHERE folio = ?");
                                    $stmt_check->bind_param("s", $ordenCompra);
                                    $stmt_check->execute();
                                    $result_check = $stmt_check->get_result();
                                    
                                    if($result_check->num_rows == 0){
                                        $errores[] = "La Orden de Compra/Folio ($ordenCompra) no existe o no es válida.";
                                        $tipoerror[] = $pdf_file_name;
                                        // Borrar archivos huérfanos
                                        unlink($facturas_path.$xml_file_name);
                                        unlink($facturas_path.$pdf_file_name);
                                    }
                                    else{
                                        # Leer XML
                                        list($emisor_nombre, $folioFactura, $receptor_uso_cfdi,$emisor_rfc, $receptor_rfc, $total, $sat_uuid, $total_impuestos_trasladados, $regimen_fiscal_receptor, $subtotal,
                                        $tipo_moneda, $tipo_cambio, $descuento, $tipo_comprobante, $fecha_factura, $concepto_claveprov, $metodo_pago, $forma_pago) = LeerXMLv1($facturas_path,$xml_file_name,$ordenCompra);
                                        static $uuidProcesados = [];
                                        if (in_array($sat_uuid, $uuidProcesados)) {
                                            continue; // Ya se procesó en esta misma carga
                                        }
                                        $uuidProcesados[] = $sat_uuid;
                                        
                                        $full_pdf_path=$facturas_path.$pdf_file_name;
                                        $full_xml_path=$facturas_path.$xml_file_name;
        
                                        // Validaciones de Facturas ante el SAT
                                        list($efos,$codigo_estatus,$es_cancelable,$estado_sat,$estatus_cancelacion) = ValidarSAT($emisor_rfc, $receptor_rfc, $total, $sat_uuid);
                                        
                                        if(strpos($codigo_estatus,'601') !== false|| strpos($codigo_estatus,'602') !== false){
                                            deleteFiles($full_xml_path,$full_pdf_path);
                                            $errores[] = "Este CFDI fue rechazado por el SAT (Estatus: $codigo_estatus)";
                                            $tipoerror[] = $pdf_file_name;
                                        }
                                        else{
                                            if (empty($sat_uuid) || empty($emisor_rfc)) 
                                            {
                                                deleteFiles($full_xml_path,$full_pdf_path);
                                                $errores[] = "El XML no cuenta con UUID o RFC Emisor válido.";
                                                $tipoerror[] = $pdf_file_name;
                                            }
                                            else
                                            {
                                                // Validadores de datos de negocio
                                                // NOTA: Ajusta el RFC 'DRG810506I80' si es necesario
                                                if($receptor_rfc != 'DRG810506I80') {  
                                                    deleteFiles($full_xml_path,$full_pdf_path);
                                                    $errores[] = "RFC Receptor incorrecto. Se esperaba DRG810506I80, llegó $receptor_rfc";
                                                    $tipoerror[] = $pdf_file_name;
                                                }
                                                else {
                                                    // Verificar duplicados en la base de datos
                                                    $check_dup = $conn->prepare("SELECT * FROM control_facturas_tcl WHERE reseted=0 AND nombre=?");
                                                    $check_dup->bind_param("s", $sat_uuid);
                                                    $check_dup->execute();
                                                    $result_dup = $check_dup->get_result();
                                                    $rows = $result_dup->num_rows;
                                                    
                                                    // Crear carpeta por RFC Emisor
                                                    $newPath=$facturas_path.$emisor_rfc."/";
                                                    if (!file_exists($newPath)) {
                                                        if (!mkdir($newPath, 0777, true)) {
                                                            $errores[] = "No se pudo crear la carpeta del proveedor.";
                                                            $tipoerror[] = $pdf_file_name;
                                                        }
                                                    }

                                                    if ($rows > 0) {   
                                                        $row = $result_dup->fetch_assoc();
                                                        $ordenCompraExistente = $row['ordencompra'];
                                                        $errores[] = "Esta factura ya fue cargada anteriormente para el folio: " . $ordenCompraExistente;
                                                        $tipoerror[] = $pdf_file_name;
                                                        // Eliminar los archivos temporales subidos
                                                        deleteFiles($full_xml_path, $full_pdf_path);
                                                    }
                                                    else 
                                                    {
                                                        $full_pdf_new_path = $newPath.$sat_uuid.".pdf";
                                                        $full_xml_new_path = $newPath.$sat_uuid.".xml";
                                                        
                                                        // Backup si ya existen físicamente
                                                        if (file_exists($full_pdf_new_path)) {
                                                            $backup_path_pdf=$backup_facturas_path.$sat_uuid."-".str_replace(":", "_", $dateTimeNow).".pdf";
                                                            rename($full_pdf_new_path,$backup_path_pdf);
                                                        }
                                                        if (file_exists($full_xml_new_path)) {
                                                            $backup_path_xml=$backup_facturas_path.$sat_uuid."-".str_replace(":", "_", $dateTimeNow).".xml";
                                                            rename($full_xml_new_path,$backup_path_xml);
                                                        }
                                                        
                                                        // Mover a destino final
                                                        $moved_pdf = rename($full_pdf_path, $full_pdf_new_path);
                                                        $moved_xml = rename($full_xml_path, $full_xml_new_path);

                                                        if($moved_pdf && $moved_xml) {
                                                            
                                                            // Registrar en BD
                                                            $insert_archivoXML = RegistrarArchivo(
                                                                $conn,
                                                                $folioFactura,
                                                                $ordenCompra,
                                                                $newPath, // Guardamos la carpeta base
                                                                $xml_file_type,
                                                                $dateTimeNow,
                                                                $xml_file_size,
                                                                "0",
                                                                $sat_uuid,
                                                                $emisor_rfc,
                                                                1
                                                            );
    
                                                            $insert_archivoXML1 = RegistrarFactura(
                                                                $conn,
                                                                "Subida",
                                                                $emisor_nombre,
                                                                $folioFactura,
                                                                $ordenCompra,
                                                                $receptor_uso_cfdi,
                                                                $emisor_rfc,
                                                                $receptor_rfc,
                                                                $total,
                                                                $sat_uuid,
                                                                $total_impuestos_trasladados,
                                                                $subtotal,
                                                                $tipo_moneda,
                                                                $tipo_cambio,
                                                                $descuento,
                                                                $tipo_comprobante,
                                                                $fecha_factura,
                                                                $dateTimeNow,
                                                                $efos,
                                                                $codigo_estatus,
                                                                $es_cancelable,
                                                                $estado_sat,
                                                                $estatus_cancelacion,
                                                                $email,
                                                                $concepto_claveprov
                                                            );
    
                                                            if ($insert_archivoXML && $insert_archivoXML1) {
                                                                $mensajes_exito[] = "Factura <strong>" . htmlspecialchars($sat_uuid) . "</strong> cargada correctamente.";
                                                            } else {
                                                                $errores[] = "Error al guardar el registro en la Base de Datos.";
                                                                $tipoerror[] = $pdf_file_name;
                                                            }
                                                        } else {
                                                            $errores[] = "Error al mover los archivos a su carpeta final.";
                                                            $tipoerror[] = $pdf_file_name;
                                                        }
                                                    }
                                                }
                                            }
                                        }
                                    }
                                }
                            }
                        } else {
                            // Errores de extensión
                            if(!empty($pdf_errors)) $errores[] = implode(", ", $pdf_errors);
                            if(!empty($xml_errors)) $errores[] = implode(", ", $xml_errors);
                            $tipoerror[] = "Extensión Inválida";
                        }
                    } // Fin foreach
                } else {
                    $GLOBALS["mensaje_global"] = "<div class='alert alert-warning'>No se seleccionaron archivos válidos.</div>";
                }
            } else {
                $GLOBALS["mensaje_global"] = "<div class='alert alert-danger'>Faltan archivos PDF o XML.</div>";
            }
        }
        catch(Exception $e)
        {
            $GLOBALS["mensaje_global"] = "<div class='alert alert-danger'>Excepción: " . $e->getMessage() . "</div>";
        }
    }
    if (isset($_SESSION['factura_en_proceso'])) {
        return;
    }
    $_SESSION['factura_en_proceso'] = true;

    
    /* 1. LÓGICA PARA EL MODAL DE ERRORES */
    if (!empty($errores)) {
        $mostrar_modal_errores = true;

        $items_error = "";
        for($k=0; $k<count($errores); $k++) {
            $err = isset($errores[$k]) ? $errores[$k] : 'Error desconocido';
            $typ = isset($tipoerror[$k]) ? $tipoerror[$k] : 'Archivo';
            $items_error .= "<li><strong>" . htmlspecialchars($typ) . ":</strong> " . htmlspecialchars($err) . "</li>";
        }

        $GLOBALS["mensaje_global"] = '
            <div class="modal fade" id="modal_resultados" tabindex="-1" role="dialog" aria-labelledby="resultadoModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered" role="document">
                    <div class="modal-content">
                        <div class="modal-header bg-danger text-white">
                            <h5 class="modal-title" id="resultadoModalLabel"><i class="fas fa-exclamation-triangle"></i> Errores al Cargar</h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                        </div>
                        <div class="modal-body">
                            <p>Se encontraron los siguientes problemas:</p>
                            <ul>' . $items_error . '</ul>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                        </div>
                    </div>
                </div>
            </div>';
    } 
    /* 2. LÓGICA PARA EL MODAL DE ÉXITO (Si NO hubo errores, pero SÍ hubo éxitos) */
    else if (!empty($mensajes_exito)) {
        $mostrar_modal_exito = true;

        $lista_exitos_html = '';
        foreach ($mensajes_exito as $exito) {
            $lista_exitos_html .= '<li class="text-success"><i class="fas fa-check-circle"></i> ' . $exito . '</li>';
        }

        $GLOBALS["mensaje_global"] = '
            <div class="modal fade" id="modal_resultados" tabindex="-1" role="dialog" aria-labelledby="resultadoModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered" role="document">
                    <div class="modal-content">
                        <div class="modal-header bg-success text-white">
                            <h5 class="modal-title" id="resultadoModalLabel">Carga Exitosa</h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                        </div>
                        <div class="modal-body">
                            <p>Los siguientes documentos se procesaron correctamente:</p>
                            <ul class="list-unstyled">' . $lista_exitos_html . '</ul>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-primary" data-bs-dismiss="modal" onclick="location.reload();">Aceptar</button>
                        </div>
                    </div>
                </div>
            </div>';
    }
?>