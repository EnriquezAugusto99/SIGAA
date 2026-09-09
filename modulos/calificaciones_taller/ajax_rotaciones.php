<?php
session_start();
header('Content-Type: application/json');

if(isset($_SESSION["dni"]) && (time() - $_SESSION["ultima_actividad"] > 1800)){
    echo json_encode(['success' => false, 'message' => 'Sesión expirada']);
    exit();
}

if(!isset($_SESSION["dni"])){
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit();
}

include '../../recursos/conexion.php';

$action = $_POST['action'] ?? '';
$anio_actual = date('Y');

if($action == 'get_rotaciones'){
    $taller_id = $_POST['taller_id'];
    
    $query = "SELECT DISTINCT r.ID_rotacion, r.numero_rotacion, r.nombre, 
                     DATE_FORMAT(r.fecha_inicio, '%d/%m') as fecha_ini,
                     DATE_FORMAT(r.fecha_fin, '%d/%m') as fecha_fin
              FROM rotaciones r
              INNER JOIN planilla_rotaciones pr ON r.ID_rotacion = pr.ID_rotacion
              WHERE pr.ID_taller = '$taller_id' AND r.anio = '$anio_actual'
              ORDER BY r.numero_rotacion";
    $res = mysqli_query($con, $query);
    
    $rotaciones = [];
    while($row = mysqli_fetch_array($res)){
        $rotaciones[] = [
            'ID_rotacion' => $row['ID_rotacion'],
            'nombre' => $row['nombre'],
            'fechas' => $row['fecha_ini'] . ' al ' . $row['fecha_fin']
        ];
    }
    
    echo json_encode(['success' => true, 'rotaciones' => $rotaciones]);
    exit();
}

if($action == 'get_cursos'){
    $rotacion_id = $_POST['rotacion_id'];
    $taller_id = $_POST['taller_id'];
    
    $query = "SELECT DISTINCT c.ID_curso, CONCAT(c.curso, '° \"', c.division, '\" - ', IF(c.turno='M','Mañana','Tarde')) as nombre
              FROM curso c
              INNER JOIN planilla_rotaciones pr ON c.ID_curso = pr.ID_curso
              WHERE pr.ID_rotacion = '$rotacion_id' AND pr.ID_taller = '$taller_id'
              ORDER BY c.curso, c.division";
    $res = mysqli_query($con, $query);
    
    $cursos = [];
    while($row = mysqli_fetch_array($res)){
        $cursos[] = $row;
    }
    
    // También obtener info de la rotación
    $q_rot = "SELECT nombre, DATE_FORMAT(fecha_inicio, '%d/%m/%Y') as fecha_inicio, DATE_FORMAT(fecha_fin, '%d/%m/%Y') as fecha_fin 
              FROM rotaciones WHERE ID_rotacion = '$rotacion_id'";
    $r_rot = mysqli_query($con, $q_rot);
    $rot_info = mysqli_fetch_array($r_rot);
    
    echo json_encode([
        'success' => true, 
        'cursos' => $cursos,
        'rotacion_info' => $rot_info
    ]);
    exit();
}

if($action == 'get_grupos'){
    $curso_id = $_POST['curso_id'];
    $rotacion_id = $_POST['rotacion_id'];
    $taller_id = $_POST['taller_id'];
    
    $query = "SELECT DISTINCT numero_grupo
              FROM planilla_rotaciones
              WHERE ID_curso = '$curso_id' 
              AND ID_rotacion = '$rotacion_id' 
              AND ID_taller = '$taller_id'
              ORDER BY numero_grupo";
    $res = mysqli_query($con, $query);
    
    $grupos = [];
    while($row = mysqli_fetch_array($res)){
        $grupos[] = $row['numero_grupo'];
    }
    
    echo json_encode(['success' => true, 'grupos' => $grupos]);
    exit();
}

echo json_encode(['success' => false, 'message' => 'Acción no válida']);
?>