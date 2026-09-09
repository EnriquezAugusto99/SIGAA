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
    echo '<script>alert("No tiene permisos para eliminar asignaciones"); window.location="../../recursos/panel.php";</script>';
    exit();
}

// Verificar CSRF token
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    $_SESSION = array();
    session_destroy();
    header("Location: ../../index.php");
    exit();
}

$id_dtc = $_POST['id_dtc'];

if(empty($id_dtc)){
    echo '<script>alert("ERROR: ID de asignación no válido"); window.location="listado_docente_taller_curso.php";</script>';
    exit();
}

include '../../recursos/conexion.php';

// Verificar que la asignación existe
$query_verificar = "SELECT ID_dtc FROM docente_taller_curso WHERE ID_dtc = '$id_dtc'";
$res_verificar = mysqli_query($con, $query_verificar);

if(mysqli_num_rows($res_verificar) == 0){
    echo '<script>alert("ERROR: La asignación no existe"); window.location="listado_docente_taller_curso.php";</script>';
    exit();
}

// Eliminar la asignación
$query = "DELETE FROM docente_taller_curso WHERE ID_dtc = '$id_dtc'";
$res = mysqli_query($con, $query);

if($res){
    echo '<script>alert("Asignación eliminada exitosamente"); window.location="listado_docente_taller_curso.php";</script>';
} else {
    echo '<script>alert("ERROR: No se pudo eliminar la asignación"); window.location="listado_docente_taller_curso.php";</script>';
}

mysqli_close($con);
?>