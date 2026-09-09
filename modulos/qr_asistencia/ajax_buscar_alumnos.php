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

include '../../recursos/conexion.php';

$termino = isset($_POST['termino']) ? trim($_POST['termino']) : '';

if(empty($termino)){
    echo json_encode(['success' => true, 'alumnos' => []]);
    exit();
}

// Buscar alumnos por DNI, nombre o apellido (límite 7 para velocidad)
$query = "SELECT u.DNI_U, u.Nombre, u.Apellido, c.curso, c.division, c.turno
          FROM usuario u
          INNER JOIN curso c ON u.id_curso = c.ID_curso
          WHERE u.ID_rol = 3 
          AND u.ID_Estado = 1
          AND (u.DNI_U LIKE '%$termino%' 
               OR u.Nombre LIKE '%$termino%' 
               OR u.Apellido LIKE '%$termino%')
          ORDER BY u.Apellido, u.Nombre
          LIMIT 7";

$res = mysqli_query($con, $query);
$alumnos = [];

while($row = mysqli_fetch_assoc($res)){
    $alumnos[] = $row;
}

echo json_encode(['success' => true, 'alumnos' => $alumnos]);
?>