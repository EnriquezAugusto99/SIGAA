<?php
session_start();
header('Content-Type: application/json');

if(!isset($_SESSION['dni'])){
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit();
}

$es_admin = ($_SESSION['rol'] == 'Admin');
$es_preceptor = ($_SESSION['rol'] == 'Preceptor');

if(!$es_admin && !$es_preceptor){
    echo json_encode(['success' => false, 'message' => 'No tiene permisos']);
    exit();
}

include '../../recursos/conexion.php';

$action = isset($_POST['action']) ? $_POST['action'] : '';

if($action == 'get_alumnos_curso'){
    $curso_id = $_POST['curso_id'] ?? 0;
    
    if(!$curso_id){
        echo json_encode(['success' => false, 'message' => 'Curso no válido']);
        exit();
    }
    
    $query = "SELECT u.DNI_U, u.Nombre, u.Apellido
              FROM usuario u
              WHERE u.id_curso = '$curso_id'
              AND u.ID_rol = 3
              AND u.ID_Estado = 1
              ORDER BY u.Apellido, u.Nombre";
    $res = mysqli_query($con, $query);
    
    if(!$res){
        echo json_encode(['success' => false, 'message' => 'Error al cargar alumnos']);
        exit();
    }
    
    $alumnos = [];
    while($row = mysqli_fetch_assoc($res)){
        $alumnos[] = [
            'DNI_U' => $row['DNI_U'],
            'Nombre' => $row['Nombre'],
            'Apellido' => $row['Apellido']
        ];
    }
    
    echo json_encode(['success' => true, 'alumnos' => $alumnos]);
    exit();
}

echo json_encode(['success' => false, 'message' => 'Acción no válida']);
?>