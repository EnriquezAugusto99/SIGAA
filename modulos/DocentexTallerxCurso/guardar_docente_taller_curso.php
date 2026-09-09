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
    echo '<script>alert("No tiene permisos para gestionar asignaciones"); window.location="../../recursos/panel.php";</script>';
    exit();
}

// Verificar CSRF token
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    $_SESSION = array();
    session_destroy();
    header("Location: ../../index.php");
    exit();
}

$ID_docente = $_POST['ID_docente'];
$ID_taller = $_POST['ID_taller'];
$ID_curso = $_POST['ID_curso'];
$turno = $_POST['turno'];
$anio = $_POST['anio'];

// Validaciones
if(empty($ID_docente) || empty($ID_taller) || empty($ID_curso) || empty($turno)){
    echo '<script>alert("ERROR: Todos los campos son requeridos"); history.go(-1);</script>';
    exit();
}

if($turno != 'M' && $turno != 'T'){
    echo '<script>alert("ERROR: El turno debe ser M (Mañana) o T (Tarde)"); history.go(-1);</script>';
    exit();
}

include '../../recursos/conexion.php';

// Verificar que el docente existe y es profesor
$query_check_docente = "SELECT DNI_U FROM usuario WHERE DNI_U = '$ID_docente' AND ID_rol = 2";
$res_check_docente = mysqli_query($con, $query_check_docente);
if(mysqli_num_rows($res_check_docente) == 0){
    echo '<script>alert("ERROR: El docente seleccionado no existe o no tiene rol de Profesor"); history.go(-1);</script>';
    exit();
}

// Verificar que el taller existe
$query_check_taller = "SELECT ID_taller FROM talleres WHERE ID_taller = '$ID_taller'";
$res_check_taller = mysqli_query($con, $query_check_taller);
if(mysqli_num_rows($res_check_taller) == 0){
    echo '<script>alert("ERROR: El taller seleccionado no existe"); history.go(-1);</script>';
    exit();
}

// Verificar que el curso existe
$query_check_curso = "SELECT ID_curso FROM curso WHERE ID_curso = '$ID_curso'";
$res_check_curso = mysqli_query($con, $query_check_curso);
if(mysqli_num_rows($res_check_curso) == 0){
    echo '<script>alert("ERROR: El curso seleccionado no existe"); history.go(-1);</script>';
    exit();
}

// Verificar si ya existe la asignación (evitar duplicados)
$query_verificar = "SELECT ID_dtc FROM docente_taller_curso 
                    WHERE ID_docente = '$ID_docente' 
                    AND ID_taller = '$ID_taller' 
                    AND ID_curso = '$ID_curso' 
                    AND turno = '$turno'
                    AND anio = '$anio'";
$res_verificar = mysqli_query($con, $query_verificar);

if(mysqli_num_rows($res_verificar) > 0){
    echo '<script>alert("ERROR: Ya existe esta asignación para este docente, taller, curso y turno en el año ' . $anio . '"); history.go(-1);</script>';
    exit();
}

// Insertar la asignación
$query = "INSERT INTO docente_taller_curso (ID_docente, ID_taller, ID_curso, turno, anio) 
          VALUES ('$ID_docente', '$ID_taller', '$ID_curso', '$turno', '$anio')";
$res = mysqli_query($con, $query);

if($res){
    echo '<script>alert("Asignación guardada exitosamente"); window.location="listado_docente_taller_curso.php";</script>';
    exit();
} else {
    echo '<script>alert("ERROR: No se pudo guardar la asignación. Error: ' . mysqli_error($con) . '"); history.go(-1);</script>';
}

mysqli_close($con);
?>