<?php
include 'conexion.php';

if (isset($_GET['provincia'])) {
    $provincia = $_GET['provincia'];
    $query = "SELECT codigo_distrito, nombre_distrito FROM distrito WHERE codigo_provincia = '$provincia'";
    $result = $conexion->query($query);
    $distritos = [];
    while ($row = $result->fetch_assoc()) {
        $distritos[] = $row;
    }
    echo json_encode($distritos);
    exit;
}

if (isset($_GET['distrito'])) {
    $distrito = $_GET['distrito'];
    $query = "SELECT codigo_corregimiento, nombre_corregimiento FROM corregimiento WHERE codigo_distrito = '$distrito'";
    $result = $conexion->query($query);
    $corregimientos = [];
    while ($row = $result->fetch_assoc()) {
        $corregimientos[] = $row;
    }
    echo json_encode($corregimientos);
    exit;
}

if (isset($_GET['departamento'])) {
    $departamento = $_GET['departamento'];
    $query = "SELECT codigo, nombre FROM cargo WHERE dep_codigo = '$departamento'";
    $result = $conexion->query($query);
    $cargos = [];
    while ($row = $result->fetch_assoc()) {
        $cargos[] = $row;
    }
    echo json_encode($cargos);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);

    if ($data['action'] === 'deleteEmployees') {
        $employeeIds = $data['employeeIds'];

        foreach ($employeeIds as $cedula) {
            // Obtener datos del empleado
            $query = "SELECT * FROM empleados WHERE cedula = ?";
            $stmt = $conexion->prepare($query);
            $stmt->bind_param('s', $cedula);
            $stmt->execute();
            $result = $stmt->get_result();
            $employee = $result->fetch_assoc();

            if ($employee) {
                // Insertar en e_eliminados
                $insertQuery = "INSERT INTO e_eliminados (cedula, prefijo, tomo, asiento, nombre1, nombre2, apellido1, apellido2, apellidoc, genero, estado_civil, tipo_sangre, usa_ac, f_nacimiento, celular, telefono, correo, contraseña, provincia, distrito, corregimiento, calle, casa, comunidad, nacionalidad, f_contra, cargo, departamento, estado) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $insertStmt = $conexion->prepare($insertQuery);
                $insertStmt->bind_param('sssssssssiisiisissssssssssssi',
                    $employee['cedula'], $employee['prefijo'], $employee['tomo'], $employee['asiento'],
                    $employee['nombre1'], $employee['nombre2'], $employee['apellido1'], $employee['apellido2'],
                    $employee['apellidoc'], $employee['genero'], $employee['estado_civil'], $employee['tipo_sangre'],
                    $employee['usa_ac'], $employee['f_nacimiento'], $employee['celular'], $employee['telefono'],
                    $employee['correo'], $employee['contraseña'], $employee['provincia'], $employee['distrito'],
                    $employee['corregimiento'], $employee['calle'], $employee['casa'], $employee['comunidad'],
                    $employee['nacionalidad'], $employee['f_contra'], $employee['cargo'], $employee['departamento'],
                    $employee['estado']
                );
                $insertStmt->execute();

                // Eliminar de empleados
                $deleteQuery = "DELETE FROM empleados WHERE cedula = ?";
                $deleteStmt = $conexion->prepare($deleteQuery);
                $deleteStmt->bind_param('s', $cedula);
                $deleteStmt->execute();
            }
        }

        echo json_encode(['success' => true]);
    } elseif ($data['action'] === 'restoreEmployees') {
        $employeeIds = $data['employeeIds'];
        foreach ($employeeIds as $cedula) {
            // Obtener datos del empleado eliminado
            $query = "SELECT * FROM e_eliminados WHERE cedula = ?";
            $stmt = $conexion->prepare($query);
            $stmt->bind_param('s', $cedula);
            $stmt->execute();
            $result = $stmt->get_result();
            $employee = $result->fetch_assoc();
            if ($employee) {
                // Insertar de nuevo en empleados
                $insertQuery = "INSERT INTO empleados (cedula, prefijo, tomo, asiento, nombre1, nombre2, apellido1, apellido2, apellidoc, genero, estado_civil, tipo_sangre, usa_ac, f_nacimiento, celular, telefono, correo, contraseña, provincia, distrito, corregimiento, calle, casa, comunidad, nacionalidad, f_contra, cargo, departamento, estado) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $insertStmt = $conexion->prepare($insertQuery);
                $estadoActivo = 0;
                $insertStmt->bind_param('sssssssssiisiisissssssssssssi',
                    $employee['cedula'], $employee['prefijo'], $employee['tomo'], $employee['asiento'],
                    $employee['nombre1'], $employee['nombre2'], $employee['apellido1'], $employee['apellido2'],
                    $employee['apellidoc'], $employee['genero'], $employee['estado_civil'], $employee['tipo_sangre'],
                    $employee['usa_ac'], $employee['f_nacimiento'], $employee['celular'], $employee['telefono'],
                    $employee['correo'], $employee['contraseña'], $employee['provincia'], $employee['distrito'],
                    $employee['corregimiento'], $employee['calle'], $employee['casa'], $employee['comunidad'],
                    $employee['nacionalidad'], $employee['f_contra'], $employee['cargo'], $employee['departamento'],
                    $estadoActivo
                );
                $insertStmt->execute();
                // Eliminar de e_eliminados
                $deleteQuery = "DELETE FROM e_eliminados WHERE cedula = ?";
                $deleteStmt = $conexion->prepare($deleteQuery);
                $deleteStmt->bind_param('s', $cedula);
                $deleteStmt->execute();
            }
        }
        echo json_encode(['success' => true]);
    } elseif ($data['action'] === 'deletePermanent') {
        $employeeIds = $data['employeeIds'];
        foreach ($employeeIds as $cedula) {
            $deleteQuery = "DELETE FROM e_eliminados WHERE cedula = ?";
            $deleteStmt = $conexion->prepare($deleteQuery);
            $deleteStmt->bind_param('s', $cedula);
            $deleteStmt->execute();
        }
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Acción no válida']);
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'getDeletedEmployees') {
    $query = "SELECT e.cedula, e.nombre1, e.apellido1, d.nombre AS departamento, c.nombre AS cargo, e.f_contra, 'admin' AS eliminado_por
    FROM e_eliminados e
    LEFT JOIN departamento d ON e.departamento = d.codigo
    LEFT JOIN cargo c ON e.cargo = c.codigo";
    $result = $conexion->query($query);

    $deletedEmployees = [];
    while ($row = $result->fetch_assoc()) {
        $deletedEmployees[] = $row;
    }

    echo json_encode($deletedEmployees);
    exit;
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'delete_employees') {
        $employees = $_POST['employees'];
        $response = ['success' => false, 'message' => ''];

        foreach ($employees as $employee) {
            $cedula = $employee['cedula'];
            $nombre1 = $employee['nombre1'];
            $apellido1 = $employee['apellido1'];
            $departamento = $employee['departamento'];
            $cargo = $employee['cargo'];
            $f_contra = $employee['f_contra'];
            $eliminado_por = $employee['eliminado_por'];

            $query = "INSERT INTO e_eliminados (cedula, nombre1, apellido1, departamento, cargo, f_contra, eliminado_por) VALUES (?, ?, ?, ?, ?, ?, ?)";
            $stmt = $conexion->prepare($query);
            $stmt->bind_param('sssssss', $cedula, $nombre1, $apellido1, $departamento, $cargo, $f_contra, $eliminado_por);

            if (!$stmt->execute()) {
                $response['message'] = 'Error al insertar en la base de datos: ' . $stmt->error;
                echo json_encode($response);
                exit;
            }
        }

        $response['success'] = true;
        echo json_encode($response);
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'fetchDeletedEmployees') {
    $query = "SELECT e.cedula, e.nombre1, e.apellido1, d.nombre AS departamento, c.nombre AS cargo, e.f_contra, 'admin' AS eliminado_por
FROM e_eliminados e
LEFT JOIN departamento d ON e.departamento = d.codigo
LEFT JOIN cargo c ON e.cargo = c.codigo";
    $result = $conexion->query($query);

    $deletedEmployees = [];
    while ($row = $result->fetch_assoc()) {
        $deletedEmployees[] = $row;
    }

    echo json_encode($deletedEmployees);
    exit;
} elseif ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'getEmployeeDetails' && isset($_GET['cedula'])) {
    $cedula = $_GET['cedula'];
    $stmt = $conexion->prepare("SELECT e.cedula, e.prefijo, e.tomo, e.asiento, e.nombre1, e.nombre2, e.apellido1, e.apellido2, e.apellidoc, e.genero, e.estado_civil, e.tipo_sangre, e.usa_ac, e.f_nacimiento, e.celular, e.telefono, e.correo, e.provincia, e.distrito, e.corregimiento, e.calle, e.casa, e.comunidad, e.nacionalidad, e.f_contra, e.cargo, c.nombre AS cargo_nombre, e.departamento, d.nombre AS departamento_nombre, e.estado FROM empleados e LEFT JOIN cargo c ON e.cargo = c.codigo LEFT JOIN departamento d ON e.departamento = d.codigo WHERE e.cedula = ?");
    $stmt->bind_param('s', $cedula);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        // Formatear datos para el modal (sin contraseña)
        $data = [
            'id' => $row['cedula'],
            'cedula' => $row['cedula'],
            'nombre' => trim($row['nombre1'] . ' ' . $row['nombre2']),
            'apellido' => trim($row['apellido1'] . ' ' . $row['apellido2'] . ' ' . $row['apellidoc']),
            'departamento' => $row['departamento_nombre'],
            'cargo' => $row['cargo_nombre'],
            'f_contra' => $row['f_contra'],
            'estado' => $row['estado'] == 0 ? 'Activo' : 'Inactivo',
            'correo' => $row['correo'],
            'telefono' => $row['telefono'],
            'direccion' => trim($row['calle'] . ' ' . $row['casa'] . ' ' . $row['comunidad']),
            'f_nacimiento' => $row['f_nacimiento'],
        ];
        echo json_encode($data);
    } else {
        echo json_encode(['error' => 'Empleado no encontrado']);
    }
    exit;
} elseif ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'getDeletedEmployeeDetails' && isset($_GET['cedula'])) {
    header('Content-Type: application/json');
    
    try {
        $cedula = $_GET['cedula'];
        $stmt = $conexion->prepare("SELECT e.cedula, e.nombre1, e.nombre2, e.apellido1, e.apellido2, e.apellidoc, e.genero, e.estado_civil, e.tipo_sangre, e.usa_ac, e.f_nacimiento, e.celular, e.telefono, e.correo, 
            e.calle, e.casa, e.comunidad, e.nacionalidad, e.f_contra, 
            e.cargo, car.nombre AS cargo_nombre, 
            e.departamento, dep.nombre AS departamento_nombre, 
            e.estado 
        FROM e_eliminados e 
        LEFT JOIN cargo car ON e.cargo = car.codigo 
        LEFT JOIN departamento dep ON e.departamento = dep.codigo
        WHERE e.cedula = ?");
        
        if (!$stmt) {
            throw new Exception("Error en la preparación de la consulta: " . $conexion->error);
        }
        
        $stmt->bind_param('s', $cedula);
        
        if (!$stmt->execute()) {
            throw new Exception("Error al ejecutar la consulta: " . $stmt->error);
        }
        
        $result = $stmt->get_result();
        $employee = $result->fetch_assoc();
        
        if ($employee) {
            // Formatear la dirección solo con calle, casa y comunidad
            $direccion = [];
            if (!empty($employee['calle'])) $direccion[] = $employee['calle'];
            if (!empty($employee['casa'])) $direccion[] = $employee['casa'];
            if (!empty($employee['comunidad'])) $direccion[] = $employee['comunidad'];
            $employee['direccion'] = implode(', ', $direccion);
            
            // Formatear los datos para que coincidan con el formato esperado por el frontend
            $data = [
                'cedula' => $employee['cedula'],
                'nombre1' => trim($employee['nombre1'] . ' ' . $employee['nombre2']),
                'apellido1' => trim($employee['apellido1'] . ' ' . $employee['apellido2'] . ' ' . $employee['apellidoc']),
                'departamento' => $employee['departamento_nombre'],
                'cargo' => $employee['cargo_nombre'],
                'f_contra' => $employee['f_contra'],
                'correo' => $employee['correo'],
                'telefono' => $employee['telefono'],
                'direccion' => $employee['direccion'],
                'f_nacimiento' => $employee['f_nacimiento']
            ];
            
            echo json_encode($data);
        } else {
            echo json_encode(['error' => 'Empleado no encontrado']);
        }
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}
?>