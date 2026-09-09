<?php
session_start();

if(isset($_SESSION["dni"]) && (time() - $_SESSION["ultima_actividad"] > 1800)){
    echo '<script>alert("Se venció el tiempo de la sesion"); window.location="../../index.php";</script>';
}

if(!isset($_SESSION["dni"])){
    echo '<script>alert("Se venció el tiempo de la sesion"); window.location="../../index.php";</script>';
}

// Verificar permisos
if($_SESSION['rol'] != 'Admin' && $_SESSION['rol'] != 'Preceptor' && $_SESSION['rol'] != 'Secretario'){
    echo '<script>alert("No tienes permisos para esta sección"); window.location="../../recursos/panel.php";</script>';
    exit();
}

include '../../recursos/conexion.php';

$curso_id = $_GET['curso_id'] ?? '';

// Validar que el curso_id sea numérico
if(!is_numeric($curso_id) || $curso_id <= 0){
    echo json_encode([]);
    exit();
}

// Obtener materias del curso específico
$query = "SELECT ID_materia, Nom_materia FROM materia WHERE id_curso = '$curso_id' ORDER BY Nom_materia";
$res = mysqli_query($con, $query);

$materias = [];
if($res && mysqli_num_rows($res) > 0){
    while($fila = mysqli_fetch_array($res)){
        $materias[] = [
            'ID_materia' => $fila['ID_materia'],
            'Nom_materia' => $fila['Nom_materia']
        ];
    }
}

header('Content-Type: application/json');
echo json_encode($materias);

mysqli_close($con);
?>