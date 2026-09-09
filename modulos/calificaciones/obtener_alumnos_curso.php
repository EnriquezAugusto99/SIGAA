<?php
session_start();

if(isset($_SESSION["dni"]) && (time() - $_SESSION["ultima_actividad"] > 1800)){
    echo '<script>alert("Se venció el tiempo de la sesion"); window.location="../../index.php";</script>';
    exit();
}

if(!isset($_SESSION["dni"])){
    echo '<script>alert("Se venció el tiempo de la sesion"); window.location="../../index.php";</script>';
    exit();
}

// Verificar permisos
if($_SESSION['rol'] != 'Admin' && $_SESSION['rol'] != 'Preceptor' && $_SESSION['rol'] != 'Secretario' && $_SESSION['rol'] != 'Profesor'){
    echo '<script>alert("No tienes permisos para modificar esta sección"); window.location="../../recursos/panel.php";</script>';
    exit();
}

include '../../recursos/conexion.php';

$curso_id = $_GET['curso_id'] ?? '';

// Validar que el curso_id sea numérico
if(!is_numeric($curso_id) || $curso_id <= 0){
    echo json_encode([]);
    exit();
}

// Obtener alumnos del curso específico (solo estudiantes activos)
$query = "SELECT DNI_U, Nombre, Apellido FROM usuario WHERE id_curso = '$curso_id' AND ID_rol = 3 AND ID_Estado = 1 ORDER BY Apellido, Nombre";
$res = mysqli_query($con, $query);

$alumnos = [];
if($res && mysqli_num_rows($res) > 0){
    while($fila = mysqli_fetch_array($res)){
        $alumnos[] = [
            'DNI_U' => $fila['DNI_U'],
            'Nombre' => $fila['Nombre'],
            'Apellido' => $fila['Apellido']
        ];
    }
}

header('Content-Type: application/json');
echo json_encode($alumnos);

mysqli_close($con);
?>