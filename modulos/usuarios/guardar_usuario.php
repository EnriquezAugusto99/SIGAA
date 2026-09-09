<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

if(isset($_SESSION["dni"]) && (time() - $_SESSION["ultima_actividad"] > 1800)){
    echo '<script>alert("Se venció el tiempo de la sesion"); window.location="../../index.php";</script>';
    exit();
}

if(!isset($_SESSION["dni"])){
    echo '<script>alert("Se venció el tiempo de la sesion"); window.location="../../index.php";</script>';
    exit();
}

// Verificar permisos (solo Admin)
if($_SESSION['rol'] != 'Admin'){
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

$dni = $_POST['txt_dni'];
$nombre = trim($_POST['txt_nombre']);
$apellido = trim($_POST['txt_apellido']);
$clave = $_POST['txt_clave'];
$email = $_POST['txt_email'] ?? '';
$curso = $_POST['txt_curso'] ?? '0';
$rol = $_POST['txt_rol'];
$estado = $_POST['txt_estado'];

// VALIDACIONES
if(empty($dni)){
    echo '<script>alert("El DNI es requerido"); history.go(-1);</script>';
    exit();
}

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

include '../../recursos/conexion.php';

// Verificar si el DNI ya existe
$query_verificar = "SELECT DNI_U FROM usuario WHERE DNI_U = '$dni'";
$res_verificar = mysqli_query($con, $query_verificar);

if(mysqli_num_rows($res_verificar) > 0){
    echo '<script>alert("ERROR: ya hay un usuario con ese DNI"); history.go(-1);</script>';
    exit();
}

// Verificar si el email ya existe (si fue ingresado)
if(!empty($email)){
    $query_verificar_email = "SELECT DNI_U FROM usuario WHERE email = '$email'";
    $res_verificar_email = mysqli_query($con, $query_verificar_email);
    
    if(mysqli_num_rows($res_verificar_email) > 0){
        echo '<script>alert("ERROR: ya hay un usuario con ese email"); history.go(-1);</script>';
        exit();
    }
}

// Insertar el usuario
if($rol == 3){
    if(empty($curso) || $curso == "0"){
        echo '<script>alert("Debe seleccionar un curso para el estudiante"); history.go(-1);</script>';
        exit();
    }
    $query = "INSERT INTO usuario (DNI_U, Nombre, Apellido, clave, email, id_curso, ID_rol, ID_Estado) 
              VALUES ('$dni', '$nombre', '$apellido', '$clave', '$email', '$curso', '$rol', '$estado')";
} else {
    $query = "INSERT INTO usuario (DNI_U, Nombre, Apellido, clave, email, id_curso, ID_rol, ID_Estado) 
              VALUES ('$dni', '$nombre', '$apellido', '$clave', '$email', NULL, '$rol', '$estado')";
}

$res = mysqli_query($con, $query);

if($res){
    header("Location: listado_usuario.php");
    exit();
} else {
    echo '<script>alert("ERROR: No se pudo guardar el usuario - ' . mysqli_error($con) . '"); history.go(-1);</script>';
}

mysqli_close($con);
?>