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
if($_SESSION['rol'] != 'Admin' && $_SESSION['rol'] != 'Preceptor' && $_SESSION['rol'] != 'Secretario'){
    echo '<script>alert("No tiene permisos para gestionar aulas"); window.location="../../recursos/panel.php";</script>';
    exit();
}

// Verificar CSRF token
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    $_SESSION = array();
    session_destroy();
    header("Location: ../../index.php");
    exit();
}

$nombre_aula = trim($_POST['txt_aula']);
$descripcion = trim($_POST['txt_descripcion']);

if(empty($nombre_aula)){
    echo '<script>alert("ERROR: El nombre del aula es requerido"); history.go(-1);</script>';
    exit();
}


if(strlen($nombre_aula) > 50){
    echo '<script>alert("ERROR: El nombre del aula no puede exceder los 50 caracteres"); history.go(-1);</script>';
    exit();
}

if(!preg_match('/^[a-zA-Z0-9áéíóúÁÉÍÓÚñÑ\s\-\.]+$/', $nombre_aula)){
    echo '<script>alert("ERROR: El nombre del aula solo puede contener letras, números, espacios, guiones y puntos"); history.go(-1);</script>';
    exit();
}

if(strlen($descripcion) > 65535){ 
    echo '<script>alert("ERROR: La descripción es demasiado larga"); history.go(-1);</script>';
    exit();
}

include '../../recursos/conexion.php';

// 5. Verificar si el aula ya existe (evitar duplicados)
$query_verificar = "SELECT id_aula FROM aulas WHERE LOWER(aula) = LOWER('" . mysqli_real_escape_string($con, $nombre_aula) . "')";
$res_verificar = mysqli_query($con, $query_verificar);

if(mysqli_num_rows($res_verificar) > 0){
    echo '<script>alert("ERROR: Ya existe un aula con el nombre \'' . htmlspecialchars($nombre_aula) . '\'"); history.go(-1);</script>';
    exit();
}

// Insertar el aula
$query = "INSERT INTO aulas (aula, descripcion) VALUES ('" . mysqli_real_escape_string($con, $nombre_aula) . "', '" . mysqli_real_escape_string($con, $descripcion) . "')";
$res = mysqli_query($con, $query);

if($res){
    echo '<script>alert("Aula creada exitosamente"); window.location="listado_aulas.php";</script>';
    exit();
} else {
    echo '<script>alert("ERROR: No se pudo guardar el aula"); history.go(-1);</script>';
}

mysqli_close($con);
?>