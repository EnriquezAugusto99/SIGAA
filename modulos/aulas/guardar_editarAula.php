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

$id = $_POST['txt_id'];
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

$query_existe = "SELECT aula FROM aulas WHERE id_aula = '$id'";
$res_existe = mysqli_query($con, $query_existe);

if(mysqli_num_rows($res_existe) == 0){
    echo '<script>alert("ERROR: El aula que intenta editar no existe"); window.location="listado_aulas.php";</script>';
    exit();
}

$query_duplicado = "SELECT id_aula FROM aulas WHERE LOWER(aula) = LOWER('" . mysqli_real_escape_string($con, $nombre_aula) . "') AND id_aula != '$id'";
$res_duplicado = mysqli_query($con, $query_duplicado);

if(mysqli_num_rows($res_duplicado) > 0){
    echo '<script>alert("ERROR: Ya existe otra aula con el nombre \'' . htmlspecialchars($nombre_aula) . '\'"); history.go(-1);</script>';
    exit();
}

$query_horarios = "SELECT COUNT(*) as total FROM horarios WHERE id_aula = '$id'";
$res_horarios = mysqli_query($con, $query_horarios);
$fila_horarios = mysqli_fetch_array($res_horarios);

if($fila_horarios['total'] > 0){
    echo '<script>
        if(!confirm(" ADVERTENCIA: Esta aula tiene ' . $fila_horarios['total'] . ' horario(s) asignado(s).\\n\\n¿Está seguro de que desea cambiar los datos del aula?")) {
            history.go(-1);
            exit();
        }
    </script>';
}

$query = "UPDATE aulas SET aula = '" . mysqli_real_escape_string($con, $nombre_aula) . "', descripcion = '" . mysqli_real_escape_string($con, $descripcion) . "' WHERE id_aula = '$id'";
$res = mysqli_query($con, $query);

if($res) {
    echo '<script>alert(" Aula actualizada exitosamente"); window.location="listado_aulas.php";</script>';
    exit();
} else {
    echo '<script>alert("ERROR: No se pudo actualizar el aula."); history.go(-1);</script>';
}

mysqli_close($con);
?>