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

include '../../recursos/conexion.php';

$id = $_GET['id'];

$query_verificar = "SELECT aula FROM aulas WHERE id_aula = '$id'";
$res_verificar = mysqli_query($con, $query_verificar);

if(mysqli_num_rows($res_verificar) == 0){
    echo '<script>alert("ERROR: El aula no existe"); window.location="listado_aulas.php";</script>';
    exit();
}

$fila = mysqli_fetch_array($res_verificar);
$nombre_aula = $fila['aula'];

$query_horarios = "SELECT COUNT(*) as total FROM horarios WHERE id_aula = '$id'";
$res_horarios = mysqli_query($con, $query_horarios);
$fila_horarios = mysqli_fetch_array($res_horarios);

if($fila_horarios['total'] > 0){
    echo '<script>alert(" NO SE PUEDE ELIMINAR: El aula \'' . $nombre_aula . '\' tiene ' . $fila_horarios['total'] . ' horario(s) asignado(s).\\n\\nDebe eliminar o reasignar los horarios antes de eliminar el aula."); window.location="listado_aulas.php";</script>';
    exit();
}

$query = "DELETE FROM aulas WHERE id_aula = '$id'";
$res = mysqli_query($con, $query);

if($res) {
    echo '<script>alert("Aula eliminada exitosamente"); window.location="listado_aulas.php";</script>';
    exit();
} else {
    echo '<script>alert("ERROR: No se pudo eliminar el aula."); window.location="listado_aulas.php";</script>';
}

mysqli_close($con);
?>