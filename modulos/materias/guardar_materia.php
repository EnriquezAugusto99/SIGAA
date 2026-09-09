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

$nombre_materia = trim($_POST['txt_nombre']);
$id_curso = $_POST['txt_curso'];

if(empty($nombre_materia)){
    echo '<script>alert("ERROR: El nombre de la materia es requerido"); history.go(-1);</script>';
    exit();
}

if(strlen($nombre_materia) > 100){
    echo '<script>alert("ERROR: El nombre de la materia no puede exceder los 100 caracteres"); history.go(-1);</script>';
    exit();
}

if($id_curso == -1){
    echo '<script>alert("ERROR: Debe seleccionar un curso"); history.go(-1);</script>';
    exit();
}

if(!is_numeric($id_curso)){
    echo '<script>alert("ERROR: El curso seleccionado no es válido"); history.go(-1);</script>';
    exit();
}

include '../../recursos/conexion.php';

$query_verificar_curso = "SELECT ID_curso FROM curso WHERE ID_curso = '$id_curso'";
$res_verificar_curso = mysqli_query($con, $query_verificar_curso);
if(mysqli_num_rows($res_verificar_curso) == 0){
    echo '<script>alert("ERROR: El curso seleccionado no existe"); history.go(-1);</script>';
    exit();
}

$query_verificar = "SELECT ID_materia FROM materia WHERE LOWER(Nom_materia) = LOWER('" . mysqli_real_escape_string($con, $nombre_materia) . "') AND id_curso = '$id_curso'";
$res_verificar = mysqli_query($con, $query_verificar);

if(mysqli_num_rows($res_verificar) > 0){
    echo '<script>alert("ERROR: Ya existe una materia con el nombre \'' . htmlspecialchars($nombre_materia) . '\' en este curso"); history.go(-1);</script>';
    exit();
}

$query = "INSERT INTO materia (Nom_materia, id_curso) VALUES ('" . mysqli_real_escape_string($con, $nombre_materia) . "', '$id_curso')";
$res = mysqli_query($con, $query);

if($res){
    header("Location: listado_materia.php");
    exit();
} else {
    echo '<script>alert("ERROR: No se pudo guardar la materia"); history.go(-1);</script>';
}

mysqli_close($con);
?>