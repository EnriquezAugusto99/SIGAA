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
    echo '<script>alert("No tiene permisos para gestionar cursos"); window.location="../../recursos/panel.php";</script>';
    exit();
}

// Verificar CSRF token
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    $_SESSION = array();
    session_destroy();
    header("Location: ../../index.php");
    exit();
}

$curso = $_POST['txt_curso'];
$division = $_POST['txt_division'];
$turno = $_POST['txt_turno'];

// VALIDACIONES
if(empty($curso)){
    echo '<script>alert("El año es requerido"); history.go(-1);</script>';
    exit();
}

if($division == -1){
    echo '<script>alert("Debe seleccionar una división"); history.go(-1);</script>';
    exit();
}

if($turno == -1){
    echo '<script>alert("Debe seleccionar un turno"); history.go(-1);</script>';
    exit();
}

if(!is_numeric($curso)){
    echo '<script>alert("El año debe ser un número"); history.go(-1);</script>';
    exit();
}

if($curso < 1 || $curso > 6){
    echo '<script>alert("El año debe estar entre 1 y 6"); history.go(-1);</script>';
    exit();
}

// Validar que la división sea una letra válida
$divisiones_validas = ['A', 'B', 'C', 'D', 'E', 'F'];
if(!in_array($division, $divisiones_validas)){
    echo '<script>alert("División no válida"); history.go(-1);</script>';
    exit();
}

// Validar que el turno sea válido
$turnos_validos = ['M', 'T'];
if(!in_array($turno, $turnos_validos)){
    echo '<script>alert("Turno no válido"); history.go(-1);</script>';
    exit();
}

include '../../recursos/conexion.php';

// Verificar si el curso ya existe
$query_verificar = "SELECT ID_curso FROM curso WHERE curso = '$curso' AND division = '$division' AND turno = '$turno'";
$res_verificar = mysqli_query($con, $query_verificar);

if(mysqli_num_rows($res_verificar) > 0){
    echo '<script>alert("ERROR: Ya existe un curso con esa combinación (Año: ' . $curso . '°, División: ' . $division . ', Turno: ' . ($turno == 'M' ? 'Mañana' : 'Tarde') . ')"); history.go(-1);</script>';
    exit();
}

// Insertar el curso
$query = "INSERT INTO curso (curso, division, turno) VALUES ('$curso', '$division', '$turno')";
$res = mysqli_query($con, $query);

if($res){
    header("Location: listado_curso.php");
    exit();
} else {
    echo '<script>alert("ERROR: No se pudo guardar el curso"); history.go(-1);</script>';
}

mysqli_close($con);
?>