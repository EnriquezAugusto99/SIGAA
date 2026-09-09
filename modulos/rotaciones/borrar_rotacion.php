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

if($_SESSION['rol'] != 'Admin' && $_SESSION['rol'] != 'Preceptor'){
    echo '<script>alert("No tiene permisos para eliminar rotaciones"); window.location="../../recursos/panel.php";</script>';
    exit();
}

if (!isset($_GET['csrf_token']) || $_GET['csrf_token'] !== $_SESSION['csrf_token']) {
    $_SESSION = array();
    session_destroy();
    header("Location: ../../index.php");
    exit();
}

$id = $_GET['id'];
$anio = $_GET['anio'] ?? date('Y');

include '../../recursos/conexion.php';

// TODO: Verificar si tiene referencias (planilla_rotaciones, calificaciones_taller, etc.)
// Por ahora, eliminamos directamente

$query = "DELETE FROM rotaciones WHERE ID_rotacion = '$id'";

if(mysqli_query($con, $query)){
    echo '<script>alert("Rotación eliminada exitosamente"); window.location="rotaciones.php?filtro_anio=' . $anio . '";</script>';
} else {
    echo '<script>alert("ERROR: No se pudo eliminar la rotación"); window.location="rotaciones.php?filtro_anio=' . $anio . '";</script>';
}

mysqli_close($con);
?>