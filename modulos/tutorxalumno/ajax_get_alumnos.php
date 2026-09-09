<?php
session_start();
header('Content-Type: application/json');

if(!isset($_SESSION['dni'])){
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit();
}

include '../../recursos/conexion.php';

$curso_id = isset($_POST['curso_id']) ? (int)$_POST['curso_id'] : 0;

if($curso_id <= 0){
    echo json_encode(['success' => false, 'error' => 'ID de curso inválido']);
    exit();
}

$alumnos = [];

// Alumnos con rol principal ESTUDIANTE (ID_rol = 3)
$query1 = "SELECT DNI_U, Nombre, Apellido 
           FROM usuario 
           WHERE id_curso = '$curso_id' AND ID_rol = 3 AND ID_Estado = 1
           ORDER BY Apellido, Nombre";
$res1 = mysqli_query($con, $query1);
while($row = mysqli_fetch_assoc($res1)){
    $alumnos[] = [
        'dni' => $row['DNI_U'],
        'nombre' => $row['Nombre'],
        'apellido' => $row['Apellido']
    ];
}

// Alumnos con rol ESTUDIANTE en tabla usuario_rol (roles adicionales)
$query2 = "SELECT u.DNI_U, u.Nombre, u.Apellido 
           FROM usuario u
           INNER JOIN usuario_rol ur ON u.DNI_U = ur.dni_usuario
           WHERE u.id_curso = '$curso_id' AND ur.id_rol = 3 AND ur.activo = 1 AND u.ID_Estado = 1
           ORDER BY u.Apellido, u.Nombre";
$res2 = mysqli_query($con, $query2);
while($row = mysqli_fetch_assoc($res2)){
    // Evitar duplicados
    $existe = false;
    foreach($alumnos as $a){
        if($a['dni'] == $row['DNI_U']){
            $existe = true;
            break;
        }
    }
    if(!$existe){
        $alumnos[] = [
            'dni' => $row['DNI_U'],
            'nombre' => $row['Nombre'],
            'apellido' => $row['Apellido']
        ];
    }
}

echo json_encode(['success' => true, 'alumnos' => $alumnos]);
mysqli_close($con);
?>