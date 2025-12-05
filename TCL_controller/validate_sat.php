<?php

function ValidarSAT($emisor_rfc, $receptor_rfc, $total, $uuid){
    // URL del WSDL del Servicio de Consulta de CFDI del SAT
    $wsdl = "https://consultaqr.facturaelectronica.sat.gob.mx/ConsultaCFDIService.svc";

    try {
        // Crear una instancia de SoapClient con las opciones necesarias
        $options = array(
            'trace' => 1,        // Habilita la traza para depuraci贸n
            'exceptions' => true // Lanza excepciones en caso de error
        );
        $client = new SoapClient($wsdl, $options);

        // Par谩metros para la llamada al servicio web
        $params = array(
            'expresionImpresa' => '?&re='.$emisor_rfc.'&rr='.$receptor_rfc.'&tt='.$total.'&id='.$uuid.''
        );

        // Llamar a la funci贸n 'Consulta' del servicio web
        $response = $client->__soapCall('Consulta', array($params));

        $consultaResult = $response->ConsultaResult;

        $codigo_estatus = $consultaResult->CodigoEstatus;
        $es_cancelable = $consultaResult->EsCancelable;
        $estado_sat = $consultaResult->Estado;
        $estatus_cancelacion = $consultaResult->EstatusCancelacion;
        $efos = $consultaResult->ValidacionEFOS;

        return array($efos,$codigo_estatus,$es_cancelable,$estado_sat,$estatus_cancelacion);
    } catch (SoapFault $e) {
        // Manejo de errores
        echo "Error: {$e->getMessage()}";
        return array(null, null, null, null, null);
    }
}

?>