<?php
session_start();
header('Content-Type: application/json');

// ============================================
// CÓDIGO DE DEPURACIÓN - MOSTRAR TODOS LOS ERRORES
// ============================================
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Crear archivo de log personalizado
function debug_log($mensaje) {
    $log_file = __DIR__ . '/debug_previas.log';
    $fecha = date('Y-m-d H:i:s');
    file_put_contents($log_file, "[$fecha] $mensaje\n", FILE_APPEND);
}

debug_log("=== INICIO DE PETICIÓN ===");
debug_log("POST data: " . print_r($_POST, true));

if(isset($_SESSION["dni"]) && (time() - $_SESSION["ultima_actividad"] > 18000)){
    debug_log("Error: Sesión expirada");
    echo json_encode(['success' => false, 'message' => 'Sesión expirada']);
    exit();
}

if(!isset($_SESSION["dni"])){
    debug_log("Error: No autorizado");
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit();
}

debug_log("Sesión activa - DNI: " . $_SESSION["dni"]);

$es_admin = ($_SESSION['rol'] == 'Admin');
$es_preceptor = ($_SESSION['rol'] == 'Preceptor');

debug_log("Rol: " . $_SESSION['rol']);

include '../../recursos/conexion.php';

// Verificar conexión
if(!$con) {
    debug_log("Error de conexión a BD: " . mysqli_connect_error());
    echo json_encode(['success' => false, 'message' => 'Error de conexión a la base de datos']);
    exit();
}
debug_log("Conexión a BD exitosa");

$action = $_POST['action'] ?? '';
debug_log("Action: " . $action);

// ============================================
// ACCIÓN: GUARDAR PREVIA
// ============================================
if($action == 'guardar_previa'){
    debug_log("=== PROCESANDO guardar_previa ===");
    
    $alumno_id = $_POST['alumno_id'] ?? 0;
    $id_materia = $_POST['id_materia'] ?? null;
    $id_taller = $_POST['id_taller'] ?? null;
    $tipo_item = $_POST['tipo_item'] ?? 'materia';
    $tipo_previa = $_POST['tipo_previa'] ?? 0;
    
    debug_log("alumno_id: " . $alumno_id);
    debug_log("id_materia: " . ($id_materia ?? 'NULL'));
    debug_log("id_taller: " . ($id_taller ?? 'NULL'));
    debug_log("tipo_item: " . $tipo_item);
    debug_log("tipo_previa: " . $tipo_previa);
    
    if(!$alumno_id || !$tipo_previa){
        debug_log("Error: Faltan datos requeridos - alumno_id o tipo_previa vacío");
        echo json_encode(['success' => false, 'message' => 'Faltan datos requeridos: alumno o tipo de previa']);
        exit();
    }
    
    if($tipo_item == 'materia' && !$id_materia){
        debug_log("Error: Falta materia");
        echo json_encode(['success' => false, 'message' => 'Falta seleccionar la materia']);
        exit();
    }
    
    if($tipo_item == 'taller' && !$id_taller){
        debug_log("Error: Falta taller");
        echo json_encode(['success' => false, 'message' => 'Falta seleccionar el taller']);
        exit();
    }
    
    // Verificar si ya existe la previa
    if($tipo_item == 'materia'){
        $query_check = "SELECT ID_previas FROM previas WHERE DNI_U = '$alumno_id' AND ID_materia = '$id_materia' AND ID_taller IS NULL";
    } else {
        $query_check = "SELECT ID_previas FROM previas WHERE DNI_U = '$alumno_id' AND ID_taller = '$id_taller' AND ID_materia IS NULL";
    }
    debug_log("Query check: " . $query_check);
    
    $res_check = mysqli_query($con, $query_check);
    
    if(!$res_check){
        debug_log("Error en query check: " . mysqli_error($con));
        echo json_encode(['success' => false, 'message' => 'Error en verificación: ' . mysqli_error($con)]);
        exit();
    }
    
    if(mysqli_num_rows($res_check) > 0){
        debug_log("Ya existe previa para este alumno en este ítem");
        echo json_encode(['success' => false, 'message' => 'Ya existe una previa registrada para este alumno en este ítem']);
        exit();
    }
    
    // Insertar nueva previa
    if($tipo_item == 'materia'){
        $query = "INSERT INTO previas (DNI_U, ID_materia, ID_taller, Aprobado, ID_tp) 
                  VALUES ('$alumno_id', '$id_materia', NULL, 0, '$tipo_previa')";
    } else {
        $query = "INSERT INTO previas (DNI_U, ID_materia, ID_taller, Aprobado, ID_tp) 
                  VALUES ('$alumno_id', NULL, '$id_taller', 0, '$tipo_previa')";
    }
    debug_log("Query insert: " . $query);
    
    if(mysqli_query($con, $query)){
    debug_log("Previa guardada correctamente - filas afectadas: " . mysqli_affected_rows($con));
    echo json_encode(['success' => true, 'message' => 'Previa registrada correctamente']);
} else {
    $error = mysqli_error($con);
    debug_log("Error al insertar: " . $error);
    debug_log("Número de error: " . mysqli_errno($con));
    echo json_encode(['success' => false, 'message' => 'Error al guardar: ' . $error]);
}
    exit();
}

// ============================================
// ACCIÓN: OBTENER MATERIAS Y TALLERES POR AÑO
// ============================================
if($action == 'get_materias_y_talleres_por_anio'){
    debug_log("=== PROCESANDO get_materias_y_talleres_por_anio ===");
    
    $anio = $_POST['anio'] ?? 0;
    debug_log("Año: " . $anio);
    
    if(!$anio){
        debug_log("Error: Año no válido");
        echo json_encode(['success' => false, 'message' => 'Año no válido']);
        exit();
    }
    
    $items = [];
    
    // Obtener materias
    $query_materias = "SELECT DISTINCT m.ID_materia, m.Nom_materia
                       FROM materia m
                       INNER JOIN curso c ON m.id_curso = c.ID_curso
                       WHERE c.curso = '$anio'
                       AND c.division = 'A'
                       AND m.Nom_materia NOT LIKE '%Taller%'
                       AND m.Nom_materia NOT LIKE '%Tutorias%'
                       AND m.Nom_materia NOT LIKE '%Tutoría%'
                       ORDER BY m.Nom_materia";
    debug_log("Query materias: " . $query_materias);
    
    $res_materias = mysqli_query($con, $query_materias);
    
    if(!$res_materias){
        debug_log("Error en query materias: " . mysqli_error($con));
    } else {
        while($row = mysqli_fetch_array($res_materias)){
            $items[] = [
                'id_materia' => $row['ID_materia'],
                'id_taller' => null,
                'nombre' => $row['Nom_materia'],
                'tipo' => 'materia'
            ];
        }
        debug_log("Materias encontradas: " . count($items));
    }
    
    // Obtener talleres (solo 1° y 2° año)
    if($anio == 1 || $anio == 2){
        $anio_taller = ($anio == 1) ? 'I' : 'II';
        $query_talleres = "SELECT ID_taller, nombre 
                           FROM talleres 
                           WHERE anio_taller = '$anio_taller' 
                           AND activo = 1
                           ORDER BY nombre";
        debug_log("Query talleres: " . $query_talleres);
        
        $res_talleres = mysqli_query($con, $query_talleres);
        
        if(!$res_talleres){
            debug_log("Error en query talleres: " . mysqli_error($con));
        } else {
            while($row = mysqli_fetch_array($res_talleres)){
                $items[] = [
                    'id_materia' => null,
                    'id_taller' => $row['ID_taller'],
                    'nombre' => 'Taller: ' . $row['nombre'],
                    'tipo' => 'taller'
                ];
            }
            debug_log("Talleres encontrados: " . (count($items) - (count($items) - ($res_talleres ? $res_talleres->num_rows : 0))));
        }
    }
    
    // Ordenar
    usort($items, function($a, $b) {
        return strcmp($a['nombre'], $b['nombre']);
    });
    
    debug_log("Total items a enviar: " . count($items));
    echo json_encode(['success' => true, 'items' => $items]);
    exit();
}

// ============================================
// ACCIÓN: OBTENER ALUMNOS
// ============================================
if($action == 'get_alumnos'){
    debug_log("=== PROCESANDO get_alumnos ===");
    
    $curso_id = $_POST['curso_id'] ?? 0;
    debug_log("Curso ID: " . $curso_id);
    
    $query = "SELECT u.DNI_U, u.Nombre, u.Apellido
              FROM usuario u
              WHERE u.id_curso = '$curso_id'
              AND u.ID_rol = 3
              AND u.ID_Estado = 1
              ORDER BY u.Apellido, u.Nombre";
    debug_log("Query alumnos: " . $query);
    
    $res = mysqli_query($con, $query);
    
    if(!$res){
        debug_log("Error en query alumnos: " . mysqli_error($con));
        echo json_encode(['success' => false, 'message' => 'Error al cargar alumnos: ' . mysqli_error($con)]);
        exit();
    }
    
    $alumnos = [];
    while($row = mysqli_fetch_array($res)){
        $alumnos[] = [
            'DNI_U' => $row['DNI_U'],
            'Nombre' => $row['Nombre'],
            'Apellido' => $row['Apellido']
        ];
    }
    
    debug_log("Alumnos encontrados: " . count($alumnos));
    echo json_encode(['success' => true, 'alumnos' => $alumnos]);
    exit();
}

if($action == 'editar_previa'){
    debug_log("=== editar_previa ===");
    
    $previa_id = $_POST['previa_id'] ?? 0;
    $tipo_previa = $_POST['tipo_previa'] ?? 0;
    $aprobado = $_POST['aprobado'] ?? 0;
    
    debug_log("previa_id: $previa_id, tipo_previa: $tipo_previa, aprobado: $aprobado");
    
    $query = "UPDATE previas SET ID_tp = '$tipo_previa', Aprobado = '$aprobado' WHERE ID_previas = '$previa_id'";
    debug_log("Query: " . $query);
    
    if(mysqli_query($con, $query)){
        echo json_encode(['success' => true, 'message' => 'Previa actualizada correctamente']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error al actualizar: ' . mysqli_error($con)]);
    }
    exit();
}

// Si ninguna acción coincide
debug_log("Acción no válida: " . $action);
echo json_encode(['success' => false, 'message' => 'Acción no válida: ' . $action]);
?>