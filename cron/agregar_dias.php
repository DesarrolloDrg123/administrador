<?php
// Conexión a la base de datos usando mysqli
$host = "localhost:3306";
$username = "intran23_root";
$password = "Intranet12_";
$dbname = "intran23_administrador";

// Crear una conexión
$mysqli = new mysqli($host, $username, $password, $dbname);

// Verificar si la conexión es exitosa
if ($mysqli->connect_error) {
    die("Conexión fallida: " . $mysqli->connect_error . "\n");
} else {
    echo "Conexión exitosa a la base de datos.\n";
}

// Función para determinar los días de vacaciones según la antigüedad
function calcularDiasVacaciones($aniosLaborados) {
    if ($aniosLaborados == 1) {
        return 12;
    } elseif ($aniosLaborados == 2) {
        return 14;
    } elseif ($aniosLaborados == 3) {
        return 16;
    } elseif ($aniosLaborados == 4) {
        return 18;
    } elseif ($aniosLaborados == 5) {
        return 20;
    } elseif ($aniosLaborados >= 6 && $aniosLaborados <= 10) {
        return 22;
    } elseif ($aniosLaborados >= 11 && $aniosLaborados <= 15) {
        return 24;
    } elseif ($aniosLaborados >= 16 && $aniosLaborados <= 20) {
        return 26;
    } elseif ($aniosLaborados >= 21 && $aniosLaborados <= 25) {
        return 28;
    } elseif ($aniosLaborados >= 26 && $aniosLaborados <= 30) {
        return 30;
    } elseif ($aniosLaborados >= 31 && $aniosLaborados <= 35) {
        return 32;
    }
    return 12; // Valor por defecto (si no coincide con ningún caso)
}

// Obtener todos los usuarios
$sql_usuarios = "SELECT id, fecha_ingreso FROM usuarios";
$result_usuarios = $mysqli->query($sql_usuarios);

if ($result_usuarios === false) {
    die("Error en la consulta SQL de usuarios: " . $mysqli->error . "\n");
}

if ($result_usuarios->num_rows > 0) {
    echo "Usuarios encontrados: " . $result_usuarios->num_rows . "\n";
    
    // Procesar cada usuario
    while ($usuario = $result_usuarios->fetch_assoc()) {
        $usuario_id = $usuario['id'];
        $fecha_ingreso = $usuario['fecha_ingreso'];

        // Calcular los años trabajados
        $anios_laborados = floor((strtotime(date('Y-m-d')) - strtotime($fecha_ingreso)) / (365 * 24 * 60 * 60));

        echo "Usuario ID: $usuario_id - Años laborados: $anios_laborados\n";

        // Revisar todos los periodos registrados para este usuario
        $sql_periodos = "SELECT id, num_periodo, vigencia FROM periodos WHERE usuario_id = $usuario_id";
        $result_periodos = $mysqli->query($sql_periodos);

        if ($result_periodos === false) {
            echo "Error al consultar los periodos del usuario $usuario_id: " . $mysqli->error . "\n";
            continue; // Saltar este usuario si hay un error
        }

        // Procesar cada periodo existente y recalcular la vigencia
        while ($periodo = $result_periodos->fetch_assoc()) {
            $id_periodo = $periodo['id'];
            $num_periodo = $periodo['num_periodo'];

            // Calcular la fecha de aniversario (cumpleaños laboral) para este periodo
            $fecha_aniversario = date('Y-m-d', strtotime("+$num_periodo years", strtotime($fecha_ingreso)));

            // La vigencia será 18 meses a partir de la fecha de aniversario
            $nueva_vigencia = date('Y-m-d', strtotime("+18 months", strtotime($fecha_aniversario)));

            // Actualizar la vigencia del periodo
            $sql_update_vigencia = "
                UPDATE periodos 
                SET vigencia = '$nueva_vigencia' 
                WHERE id = $id_periodo
            ";

            if ($mysqli->query($sql_update_vigencia) === TRUE) {
                echo "Vigencia del periodo $num_periodo del usuario $usuario_id actualizada a $nueva_vigencia.\n";
            } else {
                echo "Error al actualizar la vigencia para el periodo $num_periodo del usuario $usuario_id: " . $mysqli->error . "\n";
            }
        }

        // Añadir nuevos periodos si es necesario
        $sql_ultimo_periodo = "SELECT MAX(num_periodo) as ultimo_periodo FROM periodos WHERE usuario_id = $usuario_id";
        $result_ultimo_periodo = $mysqli->query($sql_ultimo_periodo);

        if ($result_ultimo_periodo === false) {
            echo "Error al consultar el último periodo del usuario $usuario_id: " . $mysqli->error . "\n";
            continue; // Saltar este usuario si hay un error
        }

        $ultimo_periodo = $result_ultimo_periodo->fetch_assoc()['ultimo_periodo'] ?? 0;

        // Si hay periodos faltantes, los agregamos
        for ($periodo = $ultimo_periodo + 1; $periodo <= $anios_laborados; $periodo++) {
            $dias_vacaciones = calcularDiasVacaciones($periodo);
            $fecha_aniversario = date('Y-m-d', strtotime("+$periodo years", strtotime($fecha_ingreso)));
            $vigencia = date('Y-m-d', strtotime("+18 months", strtotime($fecha_aniversario)));

            $sql_insert = "
                INSERT INTO periodos (usuario_id, num_periodo, dias_agregados, dias_disfrutados, vigencia)
                VALUES ($usuario_id, $periodo, $dias_vacaciones, 0, '$vigencia')
            ";

            if ($mysqli->query($sql_insert) === TRUE) {
                echo "Nuevo periodo agregado para el usuario ID $usuario_id con $dias_vacaciones días de vacaciones y vigencia hasta $vigencia.\n";
            } else {
                echo "Error al agregar nuevo periodo para el usuario $usuario_id: " . $mysqli->error . "\n";
            }
        }
    }
} else {
    echo "No se encontraron usuarios.\n";
}

// Eliminar periodos cuya vigencia sea anterior o igual a la fecha actual
$sql_eliminar_periodos_vencidos = "DELETE FROM periodos WHERE vigencia <= CURDATE()";
if ($mysqli->query($sql_eliminar_periodos_vencidos) === TRUE) {
    echo "Periodos vencidos eliminados correctamente.\n";
} else {
    echo "Error al eliminar periodos vencidos: " . $mysqli->error . "\n";
}

// Cerrar la conexión
$mysqli->close();
