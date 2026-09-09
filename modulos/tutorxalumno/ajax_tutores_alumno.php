<?php
session_start();
header('Content-Type: application/json');

if(!isset($_SESSION['dni'])){
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit();
}

$es_admin = ($_SESSION['rol'] == 'Admin');
$es_preceptor = ($_SESSION['rol'] == 'Preceptor');
$es_equipo = ($_SESSION['rol'] == 'Equipo de Orientacion');

if(!$es_admin && !$es_preceptor && !$es_equipo){
    echo json_encode(['success' => false, 'message' => 'No tiene permisos']);
    exit();
}

include '../../recursos/conexion.php';

$alumno_dni = isset($_POST['alumno_dni']) ? $_POST['alumno_dni'] : 0;

if(!$alumno_dni){
    echo json_encode(['success' => false, 'message' => 'DNI de alumno no válido']);
    exit();
}

// Verificar que el alumno existe y es estudiante
$query_check = "SELECT DNI_U FROM usuario WHERE DNI_U = '$alumno_dni' AND ID_rol = 3 AND ID_Estado = 1";
$res_check = mysqli_query($con, $query_check);
if(mysqli_num_rows($res_check) == 0){
    echo json_encode(['success' => false, 'message' => 'Alumno no encontrado']);
    exit();
}

// Obtener tutores del alumno
$query = "SELECT u.DNI_U, u.Nombre, u.Apellido, u.email
          FROM alumnoxtutor at
          INNER JOIN usuario u ON at.id_tutor = u.DNI_U
          WHERE at.id_alumno = '$alumno_dni'
          AND u.ID_Estado = 1
          ORDER BY u.Apellido, u.Nombre";
$res = mysqli_query($con, $query);

$tutores = [];
while($row = mysqli_fetch_assoc($res)){
    $tutores[] = $row;
}

echo json_encode(['success' => true, 'tutores' => $tutores]);
?>