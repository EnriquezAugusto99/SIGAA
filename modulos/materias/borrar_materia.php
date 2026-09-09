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
if($_SESSION['rol'] != 'Admin' && $_SESSION['rol'] != 'Preceptor'){
    echo '<script>alert("No tiene permisos para gestionar materias"); window.location="../../recursos/panel.php";</script>';
    exit();
}

// Verificar CSRF token
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    $_SESSION = array();
    session_destroy();
    header("Location: ../../index.php");
    exit();
}

include '../../recursos/conexion.php';

$id = $_GET['id'];

// Verificar si la materia existe
$query_verificar = "SELECT Nom_materia, id_curso FROM materia WHERE ID_materia = '$id'";
$res_verificar = mysqli_query($con, $query_verificar);

if(mysqli_num_rows($res_verificar) == 0){
    echo '<script>alert("La materia no existe"); window.location="listado_materia.php";</script>';
    exit();
}

$fila = mysqli_fetch_array($res_verificar);
$nombre_materia = $fila['Nom_materia'];

// Verificar si la materia tiene calificaciones asignadas
$query_calificaciones = "SELECT COUNT(*) as total FROM calificaciones WHERE id_materia = '$id'";
$res_calificaciones = mysqli_query($con, $query_calificaciones);
$fila_cal = mysqli_fetch_array($res_calificaciones);

if($fila_cal['total'] > 0){
    echo '<script>alert("No se puede eliminar la materia \'' . $nombre_materia . '\' porque tiene ' . $fila_cal['total'] . ' calificación(es) asignada(s)"); window.location="listado_materia.php";</script>';
    exit();
}

// Verificar si la materia tiene docentes asignados
$query_docentes = "SELECT COUNT(*) as total FROM docentemateriacurso WHERE id_materia = '$id'";
$res_docentes = mysqli_query($con, $query_docentes);
$fila_doc = mysqli_fetch_array($res_docentes);

if($fila_doc['total'] > 0){
    echo '<script>alert("No se puede eliminar la materia \'' . $nombre_materia . '\' porque tiene ' . $fila_doc['total'] . ' docente(s) asignado(s)"); window.location="listado_materia.php";</script>';
    exit();
}

// Eliminar la materia
$query = "DELETE FROM materia WHERE ID_materia = '$id'";
$res = mysqli_query($con, $query);

if($res) {
    header("Location: listado_materia.php");
    exit();
} else {
    echo '<script>alert("Error al eliminar la materia"); history.go(-1);</script>';
}

mysqli_close($con);
?>