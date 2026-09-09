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
    echo '<script>alert("No tiene permisos para gestionar talleres"); window.location="../../recursos/panel.php";</script>';
    exit();
}

// Verificar CSRF token
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    $_SESSION = array();
    session_destroy();
    header("Location: ../../index.php");
    exit();
}

$nombre = trim($_POST['txt_nombre']);
$anio = trim($_POST['txt_anio']);
$descripcion = trim($_POST['txt_descripcion']);
$activo = isset($_POST['txt_activo']) ? (int)$_POST['txt_activo'] : 1;

// Validaciones
if(empty($nombre)){
    echo '<script>alert("ERROR: El nombre del taller es requerido"); history.go(-1);</script>';
    exit();
}

if(strlen($nombre) > 100){
    echo '<script>alert("ERROR: El nombre del taller no puede exceder los 100 caracteres"); history.go(-1);</script>';
    exit();
}

if(empty($anio)){
    echo '<script>alert("ERROR: Debe seleccionar el año del taller (I o II)"); history.go(-1);</script>';
    exit();
}

if($anio != 'I' && $anio != 'II'){
    echo '<script>alert("ERROR: El año del taller debe ser I o II"); history.go(-1);</script>';
    exit();
}

if(!preg_match('/^[a-zA-Z0-9áéíóúÁÉÍÓÚñÑ\s\-\.]+$/', $nombre)){
    echo '<script>alert("ERROR: El nombre del taller solo puede contener letras, números, espacios, guiones y puntos"); history.go(-1);</script>';
    exit();
}

if(strlen($descripcion) > 65535){
    echo '<script>alert("ERROR: La descripción es demasiado larga"); history.go(-1);</script>';
    exit();
}

include '../../recursos/conexion.php';

// Verificar si el taller ya existe (mismo nombre y mismo año)
$query_verificar = "SELECT ID_taller FROM talleres WHERE LOWER(nombre) = LOWER('" . mysqli_real_escape_string($con, $nombre) . "') AND anio_taller = '$anio'";
$res_verificar = mysqli_query($con, $query_verificar);

if(mysqli_num_rows($res_verificar) > 0){
    echo '<script>alert("ERROR: Ya existe un taller con el nombre \'' . htmlspecialchars($nombre) . '\' para ' . ($anio == 'I' ? 'primer año' : 'segundo año') . '"); history.go(-1);</script>';
    exit();
}

// Insertar el taller
$query = "INSERT INTO talleres (nombre, anio_taller, descripcion, activo) VALUES ('" . mysqli_real_escape_string($con, $nombre) . "', '" . mysqli_real_escape_string($con, $anio) . "', '" . mysqli_real_escape_string($con, $descripcion) . "', '$activo')";
$res = mysqli_query($con, $query);

if($res){
    echo '<script>alert("Taller creado exitosamente"); window.location="listado_talleres.php";</script>';
    exit();
} else {
    echo '<script>alert("ERROR: No se pudo guardar el taller"); history.go(-1);</script>';
}

mysqli_close($con);
?>