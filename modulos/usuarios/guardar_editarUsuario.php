<?php
session_start();

if(isset($_SESSION["dni"]) && (time() - $_SESSION["ultima_actividad"] > 18000)){
    echo '<script>alert("Se venció el tiempo de la sesion"); window.location="../../index.php";</script>';
    exit();
}

if(!isset($_SESSION["dni"])){
    echo '<script>alert("Se venció el tiempo de la sesion"); window.location="../../index.php";</script>';
    exit();
}

include '../../recursos/conexion.php';

// Verificar permisos (solo Admin y Preceptor)
if($_SESSION['rol'] != 'Admin' && $_SESSION['rol'] != 'Preceptor'){
    echo '<script>alert("No tiene permisos para gestionar usuarios"); window.location="../../recursos/panel.php";</script>';
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
$nombre = $_POST['txt_nombre'];
$apellido = $_POST['txt_apellido'];
$clave = $_POST['txt_clave'];
$email = $_POST['txt_email'] ?? '';
$curso = $_POST['txt_curso'] ?? '0';
$rol = $_POST['txt_rol'];
$estado = $_POST['txt_estado'];

// VALIDACIONES
if(empty($nombre)){
    echo '<script>alert("El nombre es requerido"); history.go(-1);</script>';
    exit();
}

if(empty($apellido)){
    echo '<script>alert("El apellido es requerido"); history.go(-1);</script>';
    exit();
}

if(empty($clave)){
    echo '<script>alert("La clave es requerida"); history.go(-1);</script>';
    exit();
}

if($rol == -1){
    echo '<script>alert("Debe seleccionar un rol"); history.go(-1);</script>';
    exit();
}

if($estado == -1){
    echo '<script>alert("Debe seleccionar un estado"); history.go(-1);</script>';
    exit();
}

if($rol == 3) { // Si es Estudiante
    // Validar que tenga un curso válido
    if(empty($curso) || $curso == "0"){
        echo '<script>alert("Debe seleccionar un curso para el estudiante"); history.go(-1);</script>';
        exit();
    }
    // Verificar que el curso exista
    $query_verificar_curso = "SELECT ID_curso FROM curso WHERE ID_curso = '$curso'";
    $res_verificar_curso = mysqli_query($con, $query_verificar_curso);
    
    if(mysqli_num_rows($res_verificar_curso) == 0){
        echo '<script>alert("ERROR: El curso seleccionado no existe"); history.go(-1);</script>';
        exit();
    }
    
    $curso_valor = "'$curso'";
} else {
    $curso_valor = "'0'";
}

// Verificar si el email ya existe en OTRO usuario
if(!empty($email)){
    $query_verificar_email = "SELECT DNI_U FROM usuario WHERE email = '$email' AND DNI_U != '$id'";
    $res_verificar_email = mysqli_query($con, $query_verificar_email);
    
    if(mysqli_num_rows($res_verificar_email) > 0){
        echo '<script>alert("ERROR: ya hay otro usuario con ese email"); history.go(-1);</script>';
        exit();
    }
}

$query = "UPDATE usuario SET 
          Nombre = '$nombre', 
          Apellido = '$apellido', 
          clave = '$clave', 
          email = '$email', 
          id_curso = $curso_valor, 
          ID_rol = '$rol', 
          ID_Estado = '$estado' 
          WHERE DNI_U = '$id'";

$res = mysqli_query($con, $query);

if($res) {
    header("Location: listado_usuario.php");
    exit();
} else {
    echo '<script>alert("Error al actualizar el usuario."); history.go(-1);</script>';
    exit();
}

mysqli_close($con);
?>