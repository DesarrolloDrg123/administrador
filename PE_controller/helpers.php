<?php
//Conexion a la base de Datos
include $_SERVER['DOCUMENT_ROOT']."/config/config.php";

// Consulta para obtener las opciones de "Uso"
$sqlUso = "SELECT * FROM uso";
$resultUso = $conn->query($sqlUso);

// Consulta para obtener las opciones de "Sucursal"
$sqlSucursal = "SELECT * FROM sucursales";
$resultSucursal = $conn->query($sqlSucursal);

//Consulta para obtener los roles
$sqlRoles = "SELECT * FROM roles";
$resultRoles = $conn->query($sqlRoles);

function obtenerFolio($conn) {

    // Consulta SQL para obtener el folio basado en el ID del pedido
    $sql = "SELECT folio FROM control_folios_pe WHERE id = 1 FOR UPDATE";
    $resultado = $conn->query($sql);

    // Verificar si hay resultados
    if ($resultado->num_rows > 0) {
        $fila = $resultado->fetch_assoc();
        $ultimo_folio = $fila['folio'];

        // Incrementar el folio
        if ($ultimo_folio !== null) {
            $ultimo_folio++;
        } else {
            $ultimo_folio = 1; // Si no hay registros, el folio comienza en 1
        }

        // Formatear el número con ceros a la izquierda
        $folio_formateado = sprintf('%09d', $ultimo_folio);
        return $folio_formateado;
    } else {
        return sprintf('%09d', 1); // Si no se encontró ningún registro, comenzamos en 1 y lo formateamos
    }
}

function GuardarPedido($conn, $fecha, $solicitante, $no_cliente, $nombre_cliente, $uso, $sucursal, $observaciones, $sku, $descripcion, $cantidad, $nota) {
    // Traer el folio disponible
    $folio = obtenerFolio($conn);
    $estatus = "Nuevo";
    $nuevoFolio = $folio;
    
    $sql = "INSERT INTO pedidos_especiales
            (folio, fecha, solicitante, numero_cliente, nombre_cliente, uso, sucursal, observaciones, estatus, sku, descripcion, cantidad, nota) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $conn->prepare("UPDATE control_folios SET folio = ? WHERE id = 1");
    $stmt->bind_param("i", $nuevoFolio);
    $stmt->execute();
    
    // Preparar la consulta
    if ($stmt = $conn->prepare($sql)) {
        // Bind de parámetros
        $stmt->bind_param('sssssssssssss', $folio, $fecha, $solicitante, $no_cliente, $nombre_cliente, $uso, $sucursal, $observaciones, $estatus, $sku, $descripcion, $cantidad, $nota);
        
        // Ejecutar la consulta
        if ($stmt->execute()) {
            $stmt->close(); // Cierra la declaración
            return ['success' => true, 'message' => 'Pedido guardado exitosamente.'];
        } else {
            $stmt->close(); // Cierra la declaración
            return ['success' => false, 'message' => 'Error al guardar el Pedido: ' . $stmt->error];
        }
    } else {
        return ['success' => false, 'message' => 'Error en la preparación de la consulta: ' . $conn->error];
    }
}

function EditarPedido($conn, $fecha, $folio, $sku, $descripcion, $cantidad, $nota) {
    $estatus = "Por Revisar";
    $limpiarmotivo = "";
    
    // Consulta de actualización
    $sql = "UPDATE pedidos_especiales 
            SET fecha = ?, sku = ?, descripcion = ?, cantidad = ?, nota = ?, estatus = ?, motivo_devolucion = ? 
            WHERE folio = ?";

    // Preparar la consulta
    if ($stmt = $conn->prepare($sql)) {
        // Bind de parámetros
        $stmt->bind_param('ssssssss', $fecha, $sku, $descripcion, $cantidad, $nota, $estatus, $limpiarmotivo, $folio);
        
        // Ejecutar la consulta
        if ($stmt->execute()) {
            $stmt->close(); // Cierra la declaración
            return ['success' => true, 'message' => 'Pedido actualizado exitosamente.'];
        } else {
            $stmt->close(); // Cierra la declaración
            return ['success' => false, 'message' => 'Error al actualizar el Pedido: ' . $stmt->error];
        }
    } else {
        return ['success' => false, 'message' => 'Error en la preparación de la consulta: ' . $conn->error];
    }
}


function PedidosEspe($conn){
    $sql = "SELECT p.*, u.nombre AS uso_nombre, s.sucursal AS sucursal_nombre
            FROM pedidos_especiales p
            JOIN uso u ON p.uso = u.id
            JOIN sucursales s ON p.sucursal = s.id
            ORDER BY p.id DESC";
    $result = $conn->query($sql);
    
    if ($result->num_rows > 0) {
        $pedidos = [];
        
        // Recorrer los resultados
        while ($row = $result->fetch_assoc()) {
            $pedidos[] = $row;
        }
        
        return $pedidos;
    } else {
        //return ['success' => false, 'message' => 'No se encontraron registros.'];
    }
}

function MisPedidosEspe($conn, $nombre) {
    // Escapar el nombre para evitar inyecciones SQL
    $nombre = $conn->real_escape_string($nombre);

    // Consulta SQL con cláusula WHERE para filtrar por el nombre del solicitante
    $sql = "SELECT p.*, u.nombre AS uso_nombre, s.sucursal AS sucursal_nombre
            FROM pedidos_especiales p
            JOIN uso u ON p.uso = u.id
            JOIN sucursales s ON p.sucursal = s.id
            WHERE p.solicitante = '$nombre'
            ORDER BY p.id DESC";

    $result = $conn->query($sql);
    
    if ($result->num_rows > 0) {
        $pedidos = [];
        
        while ($row = $result->fetch_assoc()) {
            $pedidos[] = $row;
        }
        
        return $pedidos;
    } else {
        return []; // Retorna un array vacío si no se encontraron registros
    }
}

function UsuariosTD($conn) {

    // Consulta SQL con cláusula WHERE para filtrar por el nombre del solicitante
    $sql = "SELECT * FROM usuarios ORDER BY id DESC";

    $result = $conn->query($sql);
    
    if ($result->num_rows > 0) {
        $pedidos = [];
        
        while ($row = $result->fetch_assoc()) {
            $pedidos[] = $row;
        }
        
        return $pedidos;
    } else {
        return []; // Retorna un array vacío si no se encontraron registros
    }
}

function Usuario($conn, $id) {

    // Consulta SQL con cláusula WHERE para filtrar por el nombre del solicitante
    $sql = "SELECT * FROM usuarios WHERE id = '$id'";

    $result = $conn->query($sql);
    
    if ($result->num_rows > 0) {
        return $result->fetch_assoc(); // Retorna el primer registro encontrado
    } else {
        return null; // Retorna null si no se encontró ningún registro
    }
}

function UsuariosAutorizadores($conn) {
    $sql = "SELECT email FROM usuarios WHERE rol = 'Autorizador'";
    $result = $conn->query($sql);

    $emails = [];
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $emails[] = $row['email']; // Agrega cada correo al array
        }
    }
    return $emails; // Retorna un array (puede estar vacío si no hay resultados)
}

function PedidoEspecial($conn, $id) {
    $id = $conn->real_escape_string($id);
    $sql = "SELECT p.*, u.nombre AS uso_nombre, s.sucursal AS sucursal_nombre
            FROM pedidos_especiales p
            JOIN uso u ON p.uso = u.id
            JOIN sucursales s ON p.sucursal = s.id
            WHERE p.id = '$id'";
    
    $result = $conn->query($sql);
    
    if ($result->num_rows > 0) {
        return $result->fetch_assoc(); // Retorna el primer registro encontrado
    } else {
        return null; // Retorna null si no se encontró ningún registro
    }
}

function Pendientes($conn){
    $sql = "SELECT p.*, u.nombre AS uso_nombre, s.sucursal AS sucursal_nombre
            FROM pedidos_especiales p
            JOIN uso u ON p.uso = u.id
            JOIN sucursales s ON p.sucursal = s.id
            WHERE estatus = 'Nuevo' OR estatus = 'Por Revisar'
            ORDER BY p.id DESC"; // Ordenar por ID en orden descendente
    $result = $conn->query($sql);
    
    if ($result->num_rows > 0) {
        $pedidos = [];
        
        // Recorrer los resultados
        while ($row = $result->fetch_assoc()) {
            $pedidos[] = $row;
        }
        
        return $pedidos;
    } else {
        return []; // Devuelve un array vacío si no se encuentran registros
    }
}

function SucursalID($conn, $sucursal) {
    $sql = "SELECT sucursal FROM sucursales WHERE id = '$sucursal'";
    $result = $conn->query($sql);
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        return $row['sucursal']; // Retorna solo el valor del campo 'sucursal'
    } else {
        return null; // Retorna null si no se encontró ningún registro
    }
}


function UsoID($conn, $uso) {
    $sql = "SELECT nombre FROM uso WHERE id = '$uso'";
    $result = $conn->query($sql);
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        return $row['nombre']; // Retorna solo el valor del campo 'nombre'
    } else {
        return null; // Retorna null si no se encontró ningún registro
    }
}

function obtenerCorreo($conn, $folio) {
    $sql = "
        SELECT u.email
        FROM pedidos_especiales p
        JOIN usuarios u ON p.solicitante = u.nombre
        WHERE p.folio = '$folio'
    ";

    $result = $conn->query($sql);

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        return $row['email'];  // Retorna el correo del usuario
    } else {
        return null;  // Si no se encuentra el correo, retorna null
    }
}



?>