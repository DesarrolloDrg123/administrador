<?php
    session_start();
   include "validate_sat.php";
   include "validate_xml.php";

   date_default_timezone_set("America/Mexico_City");
   $dateTimeNow = date("Y-m-d H:i:s");
   $mensaje_global="";
   $mensaje_global_pdf_result="<h5>Seleccione el archivo pdf:</h5>";
   $mensaje_global_xml_result="<h5>Seleccione el archivo xml:</h5>";

   $email = $_SESSION['email'];
   
   $facturas_path="$_SERVER[DOCUMENT_ROOT]/facturas/";
   $backup_facturas_path="$_SERVER[DOCUMENT_ROOT]/facturas/backup/";

   $tipo_archivo=1;
   
   $errores = array();
   $tipoerror = array();

   // --- AGREGA ESTAS LÍNEAS AQUÍ ---
    $mensajes_exito = array();      // Inicializar array de éxitos para evitar errores si está vacío
    $mostrar_modal_errores = false; // Inicializar en falso por defecto
    $mostrar_modal_exito = false;   // Inicializar en falso por defecto
    // ---------------------------------
    
   $mostrar_modal = false;

   if(isset($_POST['submit']))
    {
        try
        {
          if(count($_FILES['file_pdf']['name']) == count($_FILES['file_xml']['name'])){
              //Si hay el mismo numero de archivo en ambos lados
              
              //Lectura de todos los documentos
            for ($i = 0; $i < count($_FILES['file_xml']['name']); $i++) {
                $is_correct_pdf=false;
                $is_correct_xml=false;
                $pdf_errors= array();
                $xml_errors= array();
                $pdf_file_name = $_FILES['file_pdf']['name'][$i];
                $pdf_file_size =$_FILES['file_pdf']['size'][$i];
                $pdf_file_tmp =$_FILES['file_pdf']['tmp_name'][$i];
                $pdf_file_type=$_FILES['file_pdf']['type'][$i];
                $pdf_file_ext=pathinfo(strtolower($pdf_file_name), PATHINFO_EXTENSION);
    
                $xml_file_name = $_FILES['file_xml']['name'][$i];
                $xml_file_size =$_FILES['file_xml']['size'][$i];
                $xml_file_tmp =$_FILES['file_xml']['tmp_name'][$i];
                $xml_file_type=$_FILES['file_xml']['type'][$i];
                $xml_file_ext=pathinfo(strtolower($xml_file_name), PATHINFO_EXTENSION);
                
                $ordenCompra = $_POST['ordenCompra'][$i];
    
                $pdf_extensions= array("pdf");
                $xml_extensions= array("xml");
                
                if(in_array($pdf_file_ext,$pdf_extensions)=== false){
                   $pdf_errors[]='La extension del archivo debe de ser pdf';
                   $GLOBALS["mensaje_global_pdf_result"]="<h4>El archivo ".$pdf_file_name." debe de ser pdf</h4>";
                }
                if(in_array($xml_file_ext,$xml_extensions)=== false){
                   $xml_errors[]='La extension del archivo debe de ser xml';
                   $GLOBALS["mensaje_global_xml_result"]="<h4>El archivo ".$xml_file_name." debe de ser xml</h4>";
                }
                if(empty($pdf_errors)==true && empty($xml_errors)==true){
                   $pdf_file_name=strtolower($pdf_file_name);
                   $xml_file_name=strtolower($xml_file_name);

                    if(empty($ordenCompra)==true)
                        {
                            $errores[] = "Orden de Compra no Registrado";
                            $tipoerror[] = $pdf_file_name;
                        } else
                        {
                                if (!file_exists($facturas_path)) {
                                   # si no existe el path destino -> /sistema-proveedores/facturas
                                   //$GLOBALS["mensaje_global"]="<h4>La ruta ".$facturas_path." no exite!</h4>";
                                    $errores[] = "La ruta no existe";
                                    $tipoerror[] = $pdf_file_name;
                                }
                                else
                                {
                                   if (move_uploaded_file($xml_file_tmp,$facturas_path.$xml_file_name)) 
                                   {
                                      # Si se subio correctamente el xml
                                      $is_correct_xml=true;
        
                                   } else  $GLOBALS["mensaje_global"]="<h4>Error al subir el xml</h4>";
        
                                   if (move_uploaded_file($pdf_file_tmp,$facturas_path.$pdf_file_name)) {
                                      # Si se subio correctamente el pdf
                                      $is_correct_pdf=true;
                                      
                                   } else  $GLOBALS["mensaje_global"]="<h4>Error al subir el pdf</h4>";
        
                                   if ($is_correct_xml && $is_correct_pdf) {
                                    
                                        // Verificar si el folio existe
                                        $stmt = "SELECT * FROM transferencias WHERE folio = '".$ordenCompra."' ";
                                        $result = $conn->query($stmt);
                                        
                                        if($result->num_rows == 0){
                                            $errores[] = "La Orden de Compra no Existe o no esta Autorizada";
                                            $tipoerror[] = $pdf_file_name;
                                        }
                                        else{
                                            # Verifica que se hayan cargado correctamente
                                              list($emisor_nombre, $folioFactura, $receptor_uso_cfdi,$emisor_rfc, $receptor_rfc, $total, $sat_uuid, $total_impuestos_trasladados, $regimen_fiscal_receptor, $subtotal,
                                              $tipo_moneda, $tipo_cambio, $descuento, $tipo_comprobante, $fecha_factura, $concepto_claveprov, $metodo_pago, $forma_pago) = LeerXMLv1($facturas_path,$xml_file_name,$ordenCompra);
                                              $full_pdf_path=$facturas_path.$pdf_file_name;
                                              $full_xml_path=$facturas_path.$xml_file_name;
        
                                              //Validaciones de Facturas ante el SAT
                                              list($efos,$codigo_estatus,$es_cancelable,$estado_sat,$estatus_cancelacion) = ValidarSAT($emisor_rfc, $receptor_rfc, $total, $sat_uuid);
                                              if(strpos($codigo_estatus,'601') !== false|| strpos($codigo_estatus,'602') !== false){
                                                 deleteFiles($full_xml_path,$full_pdf_path);
                                                $errores[] = "Este CFDI fue rechazado por el SAT";
                                                $tipoerror[] = $pdf_file_name;
        
                                                }
                                                else{
                                                    if (empty($sat_uuid) || empty($emisor_rfc)) //Si estan vacios los campos si es que no se han subido
                                                    {
                                                        deleteFiles($full_xml_path,$full_pdf_path);
                                                            //echo '<script type="text/javascript">alert("Error: La factura '.$sat_uuid.' ya fue subida")</script> '; 
                                                            //$GLOBALS["mensaje_global"]="<h4>Hubo un error al subir los archivos, contacte a su administrador.</h4>";
                                                            $errores[] = "No cuenta con UUID o RFC Emisor";
                                                            $tipoerror[] = $pdf_file_name;
                                                    }
                                                    else
                                                    {
                                                        //Validadores de los datos para DRG
                                                        if($regimen_fiscal_receptor != '601')  //Regimen Fiscal
                                                            {
                                                                deleteFiles($full_xml_path,$full_pdf_path);
                                                                //$GLOBALS["mensaje_global"]="<h4>Hubo un error al subir los archivos, el Regimen fiscal receptor esta equivocado.</h4>";
                                                                $errores[] = "Regimen Fiscal Receptor";
                                                                $tipoerror[] = $pdf_file_name;
                                                            }
                                                            else
                                                            {
                                                                if($metodo_pago == 'PUE' || $metodo_pago == 'PPD') //Metodo de Pago
                                                                {
                                                                    //Forma de Pago
                                                                    if($forma_pago != '01' && $forma_pago != '02' && $forma_pago != '03' && $forma_pago != '04' && $forma_pago != '28' && $forma_pago != '99') 
                                                                    {
                                                                        deleteFiles($full_xml_path,$full_pdf_path);
                                                                        //$GLOBALS["mensaje_global"]="<h4>Hubo un error al subir los archivos, la forma de Pago esta equivocada.</h4>";
                                                                        $errores[] = "Forma de pago";
                                                                        $tipoerror[] = $pdf_file_name;
                                                                    }
                                                                    else
                                                                    {
                                                                        if($tipo_comprobante != 'I') //Tipo de comprobante
                                                                        {
                                                                            deleteFiles($full_xml_path,$full_pdf_path);
                                                                            //echo '<script type="text/javascript">alert("Error: El Tipo de Comprobante de '.$sat_uuid.' esta equivocado.")</script> '; 
                                                                            //$GLOBALS["mensaje_global"]="<h4>Hubo un error al subir los archivos, el Tipo de Comprobante esta equivocado.</h4>";
                                                                            $errores[] = "Tipo de Comprobante";
                                                                            $tipoerror[] = $pdf_file_name;
                                                                        }
                                                                        else
                                                                        {
                                                                            if($receptor_uso_cfdi != 'G01' && $receptor_uso_cfdi != 'G03') //UsoCFDI
                                                                            {
                                                                                deleteFiles($full_xml_path,$full_pdf_path);
                                                                                //echo '<script type="text/javascript">alert("Error: El uso de CDFI. '.$sat_uuid.' esta equivocado.")</script> '; 
                                                                                //$GLOBALS["mensaje_global"]="<h4>Hubo un error al subir los archivos, el Uso CFDI esta equivocado.</h4>";
                                                                                $errores[] = "Uso de CFDI";
                                                                                $tipoerror[] = $pdf_file_name;
                                                                            }
                                                                            else
                                                                            {
                                                                                if($receptor_rfc != 'DRG810506I80') {  //RFC receptor 
                                                                                    deleteFiles($full_xml_path,$full_pdf_path);
                                                                                    //echo '<script type="text/javascript">alert("Error: El RFC Receptor de '.$sat_uuid.' esta equivocado.")</script> '; 
                                                                                    //$GLOBALS["mensaje_global"]="<h4>Hubo un error al subir los archivos, el RFC Receptor esta equivocado.</h4>";
                                                                                    $errores[] = "RFC Receptor";
                                                                                    $tipoerror[] = $pdf_file_name;
                                                                                }
                                                                                else {
                                                                                    $validation = mysqli_query($conn,"SELECT * FROM control_facturas_tcl WHERE reseted=0 and nombre='".$sat_uuid."'");
                                                                                    $rows = mysqli_num_rows($validation);
                                                    
                                                                                    $newPath=$facturas_path.$emisor_rfc."/";
                                                                                    if (!file_exists($newPath)) {
                                                                                        if (!mkdir($newPath, 0777, true)) {
                                                                                            $errores[] = "No se pudo crear la ruta de RFC Emisor";
                                                                                            $tipoerror[] = $pdf_file_name;
                                                                                        }
                                                                                    }
                                                                                    if ($rows>0) 
                                                                                    {   
                                                                                        $row = mysqli_fetch_assoc($validation);
                                                                                        $ordenCompra = $row['ordencompra']; // Asegúrate que este es el nombre real de la columna en la base
                                                                                        //echo '<script type="text/javascript">alert("Error: La factura '.$sat_uuid.' ya fue subida anteriormente")</script> ';  
                                                                                        //$GLOBALS["mensaje_global"]="<h4>Ya se ha cargado esta factura, verificalo con el administrador.</h4>";
                                                                                        $errores[] = "Este CFDI ya ha sido cargado anteriormente. Folio relacionado: " . $ordenCompra . ". Verificar con el administrador.";
                                                                                        $tipoerror[] = $pdf_file_name;
                                                                                    }
                                                                                    else 
                                                                                    {
                                                                                        $full_pdf_new_path=$newPath.$sat_uuid.".pdf";
                                                                                        $full_xml_new_path=$newPath.$sat_uuid.".xml";
                                                    
                                                    
                                                                                        if (file_exists($full_pdf_new_path) && file_exists($full_xml_new_path)) {
                                                                                        # si ya se subio una factura anteriormente
                                                    
                                                                                        if (file_exists($full_pdf_new_path))   
                                                                                        {
                                                                                            if (!file_exists($backup_facturas_path)) {
                                                                                                mkdir($backup_facturas_path, 0777, true);
                                                                                            }
                                                    
                                                                                            $backup_path_pdf=$backup_facturas_path.$sat_uuid."-".str_replace(":", "_", $dateTimeNow).".pdf";
                                                                                            rename($full_pdf_new_path,$backup_path_pdf);
                                                                                        } else $GLOBALS["mensaje_global"]="<h4>Error al guardar el backup del pdf en la carpeta de backups!</h4>";
                                                                                                                    
                                                                                        if (file_exists($full_xml_new_path))   
                                                                                        {
                                                                                            $backup_path_xml=$backup_facturas_path.$sat_uuid."-".str_replace(":", "_", $dateTimeNow).".xml";
                                                                                            rename($full_xml_new_path,$backup_path_xml);
                                                                                        } else $GLOBALS["mensaje_global"]="<h4>Error al guardar el backup del xml en la carpeta de backups!</h4>";
                                                                                        }
                                                    
                                                                                        if (file_exists($full_pdf_path))   
                                                                                        {
                                                                                        rename($full_pdf_path, $full_pdf_new_path);
                                                                                        } else $GLOBALS["mensaje_global"]="<h4>Error al guardar el pdf en la carpeta del RFC!</h4>";
                                                                                                                
                                                                                        if (file_exists($full_xml_path))   
                                                                                        {
                                                                                        rename($full_xml_path,$full_xml_new_path);
                                                                                        } else {
                                                                                            $GLOBALS["mensaje_global"]="<h4>Error al guardar el xml en la carpeta del RFC!</h4>";
                                                                                            
                                                                                        }
                                                                                        
                                                                                        $insert_archivoXML = RegistrarArchivo(
                                                                                            $conn,
                                                                                            $folioFactura,                         // folio
                                                                                            $ordenCompra,                          // ordencompra
                                                                                            $full_xml_path . " -> " . $full_xml_new_path, // ruta
                                                                                            $xml_file_type,                        // tipo
                                                                                            $dateTimeNow,                          // fecha
                                                                                            $xml_file_size,                        // size
                                                                                            "0",                                   // status
                                                                                            $sat_uuid,                             // nombre
                                                                                            $emisor_rfc,                           // rfc_ruta
                                                                                            1                                      // tipo_archivo
                                                                                        );
    
                                                                                        $insert_archivoXML1 = RegistrarFactura(
                                                                                            $conn,
                                                                                            "Subida",                   // estado_factura
                                                                                            $emisor_nombre,             // nombre_proveedor
                                                                                            $folioFactura,              // no_factura
                                                                                            $ordenCompra,               // no_orden_compra
                                                                                            $receptor_uso_cfdi,         // uso_cfdi
                                                                                            $emisor_rfc,                // rfc_emisor
                                                                                            $receptor_rfc,              // rfc_receptor
                                                                                            $total,                     // total
                                                                                            $sat_uuid,                  // uuid
                                                                                            $total_impuestos_trasladados, // iva
                                                                                            $subtotal,                  // sub_total
                                                                                            $tipo_moneda,               // tipo_moneda
                                                                                            $tipo_cambio,               // tipo_cambio
                                                                                            $descuento,                 // monto_descuento
                                                                                            $tipo_comprobante,          // tipo_comprobante
                                                                                            $fecha_factura,             // fecha_factura
                                                                                            $dateTimeNow,               // fecha_archivado
                                                                                            $efos,                      // efos
                                                                                            $codigo_estatus,            // codigo_estatus
                                                                                            $es_cancelable,             // es_cancelable
                                                                                            $estado_sat,                // estado_sat
                                                                                            $estatus_cancelacion,       // estatus_cancelacion
                                                                                            $email,                     // email_portal
                                                                                            $concepto_claveprov         // petroleo_destillado
                                                                                        );
                                                                                        
                                                                                        // Comprueba si el registro en la base de datos fue exitoso
                                                                                        if ($insert_archivoXML && $insert_archivoXML1) {
                                                                                            $mensajes_exito[] = "El archivo <strong>" . htmlspecialchars(basename($xml_file_name)) . "</strong> se procesó correctamente.";
                                                                                        
                                                                                        } else {
                                                                                            $errores[] = "Error al registrar en la Base de Datos.";
                                                                                            $tipoerror[] = htmlspecialchars(basename($xml_file_name)); // Usamos el nombre del archivo para identificar el error
                                                                                            
                                                                                            // Como falló el registro, es importante eliminar los archivos que ya se habían movido a su carpeta final.
                                                                                            if (isset($final_xml_path) && file_exists($final_xml_path)) {
                                                                                                unlink($final_xml_path);
                                                                                            }
                                                                                            if (isset($final_pdf_path) && file_exists($final_pdf_path)) {
                                                                                                unlink($final_pdf_path);
                                                                                            }
                                                                                        }
                                                                                    }
                                                                                }
                                                                            }
                                                                        }
                                                                    }
                                                                }
                                                                else
                                                                {
                                                                    deleteFiles($full_xml_path,$full_pdf_path);
                                                                    $errores[] = "Metodo de Pago";
                                                                    $tipoerror[] = $pdf_file_name;
                                                                }
                                                            }
                                                    }
                                                }
                                        }
                                   }
                                }                   
                        }

                }else{
                   //print_r($pdf_errors);
                }
            }
          } else {
              //No son la misma cantidad de archivos
              $GLOBALS["mensaje_global"]="<h4>Hubo un error al subir los archivos, no son la misma cantidad de archivos.</h4>";
          }
        }
        catch(Exception $e)
        {
         $GLOBALS["mensaje_global"]=$e->getMessage();
         //die($e->getMessage());
        }
    }
    
/* Si la variable tiene errores */
if (!empty($errores)) {
    $mostrar_modal_errores = true;

    if (count($errores) == count($tipoerror)) {
        $mensajes = array_map(function($error, $tipo) {
            return '<li><strong>' . htmlspecialchars($tipo) . ':</strong> ' . htmlspecialchars($error) . '</li>';
        }, $errores, $tipoerror);

        // Crear el HTML para el modal de ERRORES
        $GLOBALS["mensaje_global"] = '
            <div class="modal fade" id="modal_resultados" tabindex="-1" role="dialog" aria-labelledby="resultadoModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered" role="document">
                    <div class="modal-content">
                        <div class="modal-header bg-danger text-white">
                            <h5 class="modal-title" id="resultadoModalLabel">Errores al Cargar Documentos</h5>
                            <button type="button" class="close text-white" data-bs-dismiss="modal" aria-label="Cerrar">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                        <div class="modal-body">
                            <p>Los siguientes documentos no se cargaron:</p>
                            <ul>' . implode('', $mensajes) . '</ul>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                        </div>
                    </div>
                </div>
            </div>';
    } else {
        $GLOBALS["mensaje_global"] = "Error interno: Los arrays de errores no coinciden.";
    }
} 
/* Si NO hubo errores, pero SÍ hubo éxitos */
else if (!empty($mensajes_exito)) {
    $mostrar_modal_exito = true;

    // Construye la lista de éxitos
    $lista_exitos_html = '';
    foreach ($mensajes_exito as $exito) {
        $lista_exitos_html .= '<li>' . $exito . '</li>';
    }

    // Crear el HTML para el modal de ÉXITO
    $GLOBALS["mensaje_global"] = '
        <div class="modal fade" id="modal_exito" tabindex="-1" role="dialog" aria-labelledby="exitoModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered" role="document">
                <div class="modal-content">
                    <div class="modal-header bg-success text-white">
                        <h5 class="modal-title" id="exitoModalLabel">¡Proceso Exitoso! ✅</h5>
                        <button type="button" class="close text-white" data-bs-dismiss="modal" aria-label="Cerrar">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <p>Los siguientes documentos se cargaron y procesaron correctamente:</p>
                        <ul>' . $lista_exitos_html . '</ul>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                    </div>
                </div>
            </div>
        </div>';
}

// Imprime el HTML del modal que se haya generado
echo $GLOBALS["mensaje_global"];

// Activar el script para MOSTRAR el modal correspondiente
if ($mostrar_modal_errores) {
    echo '
        <script>
            $(document).ready(function() {
                $("#modal_resultados").modal("show");
            });
        </script>';
} else if ($mostrar_modal_exito) {
    echo '
        <script>
            $(document).ready(function() {
                $("#modal_exito").modal("show");
            });
        </script>';
}
?>