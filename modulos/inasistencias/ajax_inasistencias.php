<?php
session_start();
header('Content-Type: application/json');

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

function debug_log($mensaje) {
    $log_file = __DIR__ . '/debug_inasistencias.log';
    $fecha = date('Y-m-d H:i:s');
    file_put_contents($log_file, "[$fecha] $mensaje\n", FILE_APPEND);
}

debug_log("=== INICIO DE PETICIÓN INASISTENCIAS ===");
debug_log("POST data: " . print_r($_POST, true));

if(isset($_SESSION["dni"]) && (time() - $_SESSION["ultima_actividad"] > 18000)){
    echo json_encode(['success' => false, 'message' => 'Sesión expirada']);
    exit();
}

if(!isset($_SESSION["dni"])){
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit();
}

$es_admin = ($_SESSION['rol'] == 'Admin');
$es_preceptor = ($_SESSION['rol'] == 'Preceptor');

include '../../recursos/conexion.php';

if(!$con) {
    echo json_encode(['success' => false, 'message' => 'Error de conexión a la base de datos']);
    exit();
}

$action = $_POST['action'] ?? '';

// ============================================
// ACCIÓN: OBTENER ALUMNOS DE UN CURSO
// ============================================
if($action == 'get_alumnos_curso'){
    debug_log("=== get_alumnos_curso ===");
    
    $curso_id = $_POST['curso_id'] ?? 0;
    debug_log("Curso ID: " . $curso_id);
    
    $query = "SELECT u.DNI_U, u.Nombre, u.Apellido
              FROM usuario u
              WHERE u.id_curso = '$curso_id'
              AND u.ID_rol = 3
              AND u.ID_Estado = 1
              ORDER BY u.Apellido, u.Nombre";
    debug_log("Query: " . $query);
    
    $res = mysqli_query($con, $query);
    
    if(!$res){
        debug_log("Error query: " . mysqli_error($con));
        echo json_encode(['success' => false, 'message' => 'Error al cargar alumnos']);
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

// ============================================
// ACCIÓN: REGISTRAR INASISTENCIAS
// ============================================
if($action == 'registrar_inasistencias'){
    debug_log("=== registrar_inasistencias ===");
    
    $inasistencias_json = $_POST['inasistencias'] ?? '';
    $trimestre = $_POST['trimestre'] ?? 0;
    $id_preceptor = $_SESSION["dni"];
    
    debug_log("Trimestre: " . $trimestre);
    debug_log("Preceptor DNI: " . $id_preceptor);
    
    if(empty($inasistencias_json)){
        echo json_encode(['success' => false, 'message' => 'No hay datos de inasistencias']);
        exit();
    }
    
    $inasistencias = json_decode($inasistencias_json, true);
    
    if(!$inasistencias){
        echo json_encode(['success' => false, 'message' => 'Error al procesar los datos']);
        exit();
    }
    
    debug_log("Inasistencias a procesar: " . print_r($inasistencias, true));
    
    $fecha_actual = date('Y-m-d');
    $total_registros = 0;
    $total_completas = 0;
    $total_medias = 0;
    $total_cuartos = 0;
    
    mysqli_begin_transaction($con);
    
    try {
        foreach($inasistencias as $item){
            $alumno_id = $item['dni'];
            $cantidad = intval($item['cantidad']);
            $tipo_falta = $item['tipo_falta']; // 'completa', 'media', 'cuarto'
            $justificada = intval($item['justificada']);
            $curso_id = $item['curso_id'];
            
            debug_log("Procesando alumno $alumno_id - Cantidad: $cantidad - Tipo: $tipo_falta - Justificada: $justificada");
            
            for($i = 0; $i < $cantidad; $i++){
                $query = "INSERT INTO inasistencias (id_alumno, fecha, justificada, trimestre, id_preceptor, id_curso, tipo_falta) 
                          VALUES ('$alumno_id', '$fecha_actual', '$justificada', '$trimestre', '$id_preceptor', '$curso_id', '$tipo_falta')";
                
                debug_log("Query insert: " . $query);
                
                if(!mysqli_query($con, $query)){
                    throw new Exception("Error al insertar inasistencia para alumno $alumno_id: " . mysqli_error($con));
                }
                $total_registros++;
                
                switch($tipo_falta){
                    case 'completa': $total_completas++; break;
                    case 'media': $total_medias++; break;
                    case 'cuarto': $total_cuartos++; break;
                }
            }
        }
        
        mysqli_commit($con);
        
        $mensaje = "Se registraron $total_registros inasistencias";
        if($total_completas > 0) $mensaje .= " ($total_completas completa)";
        if($total_medias > 0) $mensaje .= " ($total_medias media)";
        if($total_cuartos > 0) $mensaje .= " ($total_cuartos cuarto)";
        
        debug_log("Transacción exitosa. " . $mensaje);
        echo json_encode(['success' => true, 'message' => $mensaje]);
        
    } catch (Exception $e) {
        mysqli_rollback($con);
        debug_log("Error en transacción: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    
    exit();
}

// ============================================
// ACCIÓN: ELIMINAR INASISTENCIA
// ============================================
if($action == 'eliminar_inasistencia'){
    debug_log("=== eliminar_inasistencia ===");
    
    $id_inasistencia = $_POST['id_inasistencia'] ?? 0;
    debug_log("ID Inasistencia: " . $id_inasistencia);
    
    if(!$id_inasistencia){
        echo json_encode(['success' => false, 'message' => 'ID de inasistencia no válido']);
        exit();
    }
    
    if(!$es_admin){
        $preceptor_dni = $_SESSION["dni"];
        $query_permiso = "SELECT i.id_inasistencia 
                          FROM inasistencias i
                          WHERE i.id_inasistencia = '$id_inasistencia' 
                          AND i.id_preceptor = '$preceptor_dni'";
        $res_permiso = mysqli_query($con, $query_permiso);
        
        if(mysqli_num_rows($res_permiso) == 0){
            echo json_encode(['success' => false, 'message' => 'No tiene permisos para eliminar esta inasistencia']);
            exit();
        }
    }
    
    $query = "DELETE FROM inasistencias WHERE id_inasistencia = '$id_inasistencia'";
    debug_log("Query delete: " . $query);
    
    if(mysqli_query($con, $query)){
        echo json_encode(['success' => true, 'message' => 'Inasistencia eliminada correctamente']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error al eliminar: ' . mysqli_error($con)]);
    }
    exit();
}

echo json_encode(['success' => false, 'message' => 'Acción no válida: ' . $action]);
?>