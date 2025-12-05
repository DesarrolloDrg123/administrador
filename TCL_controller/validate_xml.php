<?php 

function LeerXMLv1($folderpath,$fileName,$orden)
{
    try
    {
        $sat_uuid="";
        $emisor_rfc="";
        $condiciones_pago="";
        $xml = simplexml_load_file($folderpath.$fileName); 
        $ns = $xml->getNamespaces(true);
        if (!empty($ns['cfdi'])) 
        {
                $result = $xml->registerXPathNamespace('c', $ns['cfdi']);
                $xml->registerXPathNamespace('t', $ns['tfd']);

                
                $_ordenCompra=$orden;
                $_dateTimeNow = date("Y-m-d");
                 
                foreach ($xml->xpath('//cfdi:Comprobante') as $cfdiComprobante){ 
                    
                    $fecha      = $cfdiComprobante['Fecha']; 
                    list($fecha_factura) = explode("T", "$fecha");
                        
                    $version      = $cfdiComprobante['version'];
                    $folio        = $cfdiComprobante['Folio']; 
                    $serie      = $cfdiComprobante['Serie'];
                    $total      = $cfdiComprobante['Total']; 
                    $subtotal       = $cfdiComprobante['SubTotal']; 
                    $tipo_moneda     = $cfdiComprobante['Moneda']; 
                    $tipo_cambio    = $cfdiComprobante['TipoCambio']; 
                    $descuento  = $cfdiComprobante['Descuento']; 
                    
                    $tipo_comprobante = $cfdiComprobante['TipoDeComprobante']; 
                    $metodo_pago = $cfdiComprobante['MetodoPago'];                    

                    $condiciones_pago  = $cfdiComprobante['CondicionesDePago']; 

                    $forma_pago = $cfdiComprobante['FormaPago']; 
                    $lugar_expedicion = $cfdiComprobante['LugarExpedicion']; 

                    $noCertificado = $cfdiComprobante['NoCertificado']; 
                    $certificado = $cfdiComprobante['Certificado']; 
                    $sello  = $cfdiComprobante['Sello']; 

                    
                          
                }
                if (strpos($condiciones_pago, '<br>') !== false) {
                   $condiciones_pago=str_replace('<br>', '.', $condiciones_pago);
                }
                

                foreach ($xml->xpath('//cfdi:Emisor') as $cfdiEmisor){ 
                
                    //echo "ETIQUETAS PARA CFDI:Emisor <br>";
                    $emisor_nombre  = $cfdiEmisor['Nombre']; 
                    $emisor_rfc     = $cfdiEmisor['Rfc'];
                    

                    if (strpos($emisor_nombre, '&') !== false) {
                        $emisor_nombre=str_replace('&', '&amp;', $emisor_nombre);
                    }
                
                }
                foreach ($xml->xpath('//cfdi:Receptor') as $cfdiReceptor){ 
                
                    $receptor_uso_cfdi  = $cfdiReceptor['UsoCFDI']; 
                    $receptor_rfc           = $cfdiReceptor['Rfc'];
                    $regimen_fiscal_receptor = $cfdiReceptor['RegimenFiscalReceptor'];
                
                } 
                    
                foreach ($xml->xpath('//t:TimbreFiscalDigital') as $tfd) {
                
                    $sat_uuid =  $tfd['UUID']; 
                    
                    if ($folio == "" || $folio == null) {
                        $folioFactura = $sat_uuid; 
                    } else {
                        $folioFactura=$serie.$folio; //Serie + Folio
                        break;
                    }
                       
                }
                    
                foreach ($xml->xpath('//cfdi:Comprobante//cfdi:Impuestos//cfdi:Traslados//cfdi:Traslado') as $Traslado){ 
                
                    $traslado_importe = $Traslado['Importe']; 
                
                } 
                    
                foreach ($xml->xpath('//cfdi:Impuestos') as $cfdiImpuestos){ 
                
                    $total_impuestos_trasladados = $cfdiImpuestos['TotalImpuestosTrasladados']; 
                
                }
                //Gasolina
                $concepto_claveprov = ""; // Definir un valor predeterminado

                    foreach ($xml->xpath('//cfdi:Comprobante//cfdi:Conceptos//cfdi:Concepto') as $Concepto) {
                        $concepto_claveprovserv = (string)$Concepto['ClaveProdServ']; // Convertir el valor a una cadena
                        
                        if ($concepto_claveprovserv == "15101500" || $concepto_claveprovserv == "15101514" ||
                            $concepto_claveprovserv == "15101513" || $concepto_claveprovserv == "15101515") {
                                
                            $concepto_claveprov = "SI"; 
                        } else {
                            $concepto_claveprov = "NO";
                            break;
                        }
                    }
                
        } else $GLOBALS["mensaje_global"]="<h4>La version de este xml debería de ser 3.3 </h4>";
        //Regresar los campos
        return array($emisor_nombre, $folioFactura, $receptor_uso_cfdi,$emisor_rfc, $receptor_rfc, $total, $sat_uuid, $total_impuestos_trasladados, $regimen_fiscal_receptor, $subtotal,
        $tipo_moneda, $tipo_cambio, $descuento, $tipo_comprobante, $fecha_factura, $concepto_claveprov, $metodo_pago, $forma_pago);
        
    }
    catch(Exception $e)
      {
        
         die($e->getMessage());
      }
}

//Registrar los archivos
function RegistrarArchivo(
    $conn, // <- conexión PDO
    $folio, $ordencompra, $ruta, $tipo,
    $fecha, $size, $status, $nombre,
    $rfc_ruta, $tipo_archivo
) {
    try {
        $sql = "INSERT INTO control_facturas_tcl (
                    folio,
                    ordencompra,
                    ruta,
                    tipo,
                    fecha,
                    size,
                    status,
                    nombre,
                    rfc_ruta,
                    tipo_archivo
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = $conn->prepare($sql);
        $stmt->execute([
            $folio,
            $ordencompra,
            $ruta,
            $tipo,
            $fecha,
            $size,
            $status,
            $nombre,
            $rfc_ruta,
            $tipo_archivo
        ]);

        return true;
    } catch (Exception $e) {
        die("Error al registrar archivo: " . $e->getMessage());
    }
}


//Registrar Facturas
function RegistrarFactura(
    $conn, // <- conexión PDO
    $estado_factura, $nombre_proveedor, $no_factura, $no_orden_compra,
    $uso_cfdi, $rfc_emisor, $rfc_receptor, $total, $uuid,
    $iva, $sub_total, $tipo_moneda, $tipo_cambio, $monto_descuento,
    $tipo_comprobante, $fecha_factura, $fecha_archivado,
    $efos, $codigo_estatus, $es_cancelable, $estado_sat,
    $estatus_cancelacion, $email_portal, $petroleo_destillado
) {
    try {
        $sql = "INSERT INTO facturas_tcl (
                    estado_factura,
                    nombre_proveedor,
                    no_factura,
                    no_orden_compra,
                    uso_cfdi,
                    rfc_emisor,
                    rfc_receptor,
                    total,
                    uuid,
                    iva,
                    sub_total,
                    tipo_moneda,
                    tipo_cambio,
                    monto_descuento,
                    tipo_comprobante,
                    fecha_factura,
                    fecha_archivado,
                    efos,
                    codigo_estatus,
                    es_cancelable,
                    estado_sat,
                    estatus_cancelacion,
                    email_portal,
                    petroleo_destillado
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = $conn->prepare($sql);
        $stmt->execute([
            $estado_factura,
            $nombre_proveedor,
            $no_factura,
            $no_orden_compra,
            $uso_cfdi,
            $rfc_emisor,
            $rfc_receptor,
            $total,
            $uuid,
            $iva,
            $sub_total,
            $tipo_moneda,
            $tipo_cambio,
            $monto_descuento,
            $tipo_comprobante,
            $fecha_factura,
            $fecha_archivado,
            $efos,
            $codigo_estatus,
            $es_cancelable,
            $estado_sat,
            $estatus_cancelacion,
            $email_portal,
            $petroleo_destillado
        ]);
        return true;
    } catch (Exception $e) {
        die("Error al registrar factura: " . $e->getMessage());
    }
}

function deleteFiles($full_xml_path,$full_pdf_path)
{
    if (file_exists($full_xml_path)) 
    {
        if (!unlink($full_xml_path)) {  
            echo ("El archivo $full_xml_path no pudo ser eliminado");  
        } 
    } 
    if (file_exists($full_pdf_path)) {
        if (!unlink($full_pdf_path)) {  
            echo ("El archivo $full_pdf_path no pudo ser eliminado");  
        } 
    } 
}




?>