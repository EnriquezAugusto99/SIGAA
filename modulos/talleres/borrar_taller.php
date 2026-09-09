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

include '../../recursos/conexion.php';

$id = $_GET['id'];

$query_verificar = "SELECT nombre FROM talleres WHERE ID_taller = '$id'";
$res_verificar = mysqli_query($con, $query_verificar);

if(mysqli_num_rows($res_verificar) == 0){
    echo '<script>alert("ERROR: El taller no existe"); window.location="listado_talleres.php";</script>';
    exit();
}

$fila = mysqli_fetch_array($res_verificar);
$nombre_taller = $fila['nombre'];

// Verificar si tiene referencias (docentes o cursos asignados)
$query_docentes = "SELECT COUNT(*) as total FROM docente_taller WHERE ID_taller = '$id'";
$res_docentes = mysqli_query($con, $query_docentes);
$fila_docentes = mysqli_fetch_array($res_docentes);

$query_cursos = "SELECT COUNT(*) as total FROM tallerxcurso WHERE ID_taller = '$id'";
$res_cursos = mysqli_query($con, $query_cursos);
$fila_cursos = mysqli_fetch_array($res_cursos);

if($fila_docentes['total'] > 0 || $fila_cursos['total'] > 0){
    echo '<script>alert("NO SE PUEDE ELIMINAR: El taller \'' . $nombre_taller . '\' tiene ' . $fila_docentes['total'] . ' docente(s) y ' . $fila_cursos['total'] . ' curso(s) asignado(s).\\n\\nDebe eliminar o reasignar las relaciones antes de eliminar el taller."); window.location="listado_talleres.php";</script>';
    exit();
}

$query = "DELETE FROM talleres WHERE ID_taller = '$id'";
$res = mysqli_query($con, $query);

if($res) {
    echo '<script>alert("Taller eliminado exitosamente"); window.location="listado_talleres.php";</script>';
    exit();
} else {
    echo '<script>alert("ERROR: No se pudo eliminar el taller."); window.location="listado_talleres.php";</script>';
}

mysqli_close($con);
?>