<?php
function numeroALetras($numero) {
    $unidades = array('', 'un', 'dos', 'tres', 'cuatro', 'cinco', 'seis', 'siete', 'ocho', 'nueve');
    $decenas = array('diez', 'once', 'doce', 'trece', 'catorce', 'quince', 'dieciséis', 'diecisiete', 'dieciocho', 'diecinueve');
    $decenas10 = array('', '', 'veinte', 'treinta', 'cuarenta', 'cincuenta', 'sesenta', 'setenta', 'ochenta', 'noventa');
    $centenas = array('', 'ciento', 'doscientos', 'trescientos', 'cuatrocientos', 'quinientos', 'seiscientos', 'setecientos', 'ochocientos', 'novecientos');

    $numero = number_format($numero, 2, '.', '');
    $num_entero = floor($numero);
    $centavos = round(($numero - $num_entero) * 100);

    $texto = '';

    if ($num_entero == 0) {
        $texto = 'cero';
    } else {
        if ($num_entero >= 1000000) {
            $millones = floor($num_entero / 1000000);
            $texto .= numeroALetras($millones) . ' millón' . ($millones > 1 ? 'es ' : ' ');
            $num_entero = $num_entero % 1000000;
        }

        if ($num_entero >= 1000) {
            $miles = floor($num_entero / 1000);
            if ($miles == 1) {
                $texto .= 'mil ';
            } elseif ($miles == 2) {
                $texto .= 'dos mil ';
            } elseif ($miles == 3) {
                $texto .= 'tres mil ';
            } elseif ($miles == 4) {
                $texto .= 'cuatro mil ';
            } elseif ($miles == 5) {
                $texto .= 'cinco mil ';
            } elseif ($miles == 6){
                $texto .= 'seis mil ';
            }
            elseif($miles == 7){
                $texto .= 'siete mil ';
            }
            elseif($miles == 8){
                $texto .= 'ocho mil ';
            }
            elseif($miles == 9){
                $texto .= 'nueve mil ';
            }
             else {
                $texto .= numeroALetras($miles) . ' mil ';
            }
            $num_entero = $num_entero % 1000;
        }

        if ($num_entero >= 100) {
            if ($num_entero == 100) {
                $texto .= 'cien ';
            } else {
                $cen = floor($num_entero / 100);
                $texto .= $centenas[$cen] . ' ';
            }
            $num_entero = $num_entero % 100;
        }

        if ($num_entero >= 20) {
            $dec = floor($num_entero / 10);
            $texto .= $decenas10[$dec];
            if (($num_entero % 10) > 0) {
                $texto .= ' y ' . $unidades[$num_entero % 10];
            }
        } elseif ($num_entero >= 10) {
            $texto .= $decenas[$num_entero - 10];
        } elseif ($num_entero > 0) {
            $texto .= $unidades[$num_entero];
        }
    }

    $texto = trim($texto);
    $texto = $texto . ' pesos ' . $centavos . '/100 M.N.';

    return ucfirst($texto);
}

if (isset($_POST['numero'])) {
    echo numeroALetras($_POST['numero']);
}
?>