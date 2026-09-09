<?php
session_start();
header('Content-Type: application/json');

if(!isset($_SESSION['dni']) || $_SESSION['rol'] != 'Admin'){
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit();
}

include '../../recursos/conexion.php';

$rol_id = isset($_POST['rol_id']) ? (int)$_POST['rol_id'] : 0;
$rol_nombre = isset($_POST['rol_nombre']) ? $_POST['rol_nombre'] : '';
$curso_id = isset($_POST['curso_id']) ? (int)$_POST['curso_id'] : 0;

if($rol_id <= 0){
    echo json_encode(['success' => false, 'error' => 'Rol no válido']);
    exit();
}

// Obtener el nombre del rol
$query_rol_nombre = "SELECT nom_rol FROM rol WHERE ID_rol = '$rol_id'";
$res_rol_nombre = mysqli_query($con, $query_rol_nombre);
$rol_nombre_db = mysqli_fetch_assoc($res_rol_nombre)['nom_rol'];

$usuarios = [];

if($rol_nombre_db == 'Estudiante'){
    // Para estudiantes, podemos filtrar por curso
    $where_curso = "";
    if($curso_id > 0){
        $where_curso = " AND u.id_curso = '$curso_id'";
    }
    
    // Usuarios que NO tienen el rol de Estudiante como principal (o sea, que NO son estudiantes principalmente)
    // Esto es para asignar rol de Estudiante a profesores, tutores, etc.
    $query = "SELECT u.DNI_U, u.Nombre, u.Apellido, u.id_curso, u.ID_rol, r.nom_rol as rol_principal
              FROM usuario u
              INNER JOIN rol r ON u.ID_rol = r.ID_rol
              WHERE u.ID_Estado = 1 
              AND u.ID_rol != 3
              AND NOT EXISTS (SELECT 1 FROM usuario_rol WHERE dni_usuario = u.DNI_U AND id_rol = 3 AND activo = 1)
              $where_curso
              ORDER BY u.Apellido, u.Nombre";
} else {
    // Para otros roles: Usuarios que NO tienen el rol seleccionado como principal
    // y que tampoco lo tengan como adicional
    $query = "SELECT u.DNI_U, u.Nombre, u.Apellido, u.ID_rol, r.nom_rol as rol_principal
              FROM usuario u
              INNER JOIN rol r ON u.ID_rol = r.ID_rol
              WHERE u.ID_Estado = 1 
              AND u.ID_rol != '$rol_id'
              AND NOT EXISTS (SELECT 1 FROM usuario_rol WHERE dni_usuario = u.DNI_U AND id_rol = '$rol_id' AND activo = 1)
              ORDER BY u.Apellido, u.Nombre";
}

$res = mysqli_query($con, $query);

if(!$res){
    echo json_encode(['success' => false, 'error' => 'Error en consulta: ' . mysqli_error($con)]);
    exit();
}

while($row = mysqli_fetch_assoc($res)){
    $usuarios[] = [
        'dni' => $row['DNI_U'],
        'nombre' => $row['Nombre'],
        'apellido' => $row['Apellido'],
        'rol_principal' => $row['rol_principal'] ?? ''
    ];
}

echo json_encode(['success' => true, 'usuarios' => $usuarios]);
mysqli_close($con);
?>