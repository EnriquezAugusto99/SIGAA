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

include '../../recursos/conexion.php';

// Verificar permisos (solo Admin)
if($_SESSION['rol'] != 'Admin'){
    echo '<script>alert("No tiene permisos para gestionar usuarios"); window.location="../../recursos/panel.php";</script>';
    exit();
}

$id = $_GET['id'];

$query_verificar = "SELECT DNI_U FROM usuario WHERE DNI_U = '$id'";
$res_verificar = mysqli_query($con, $query_verificar);

if(mysqli_num_rows($res_verificar) == 0){
    echo '<script>alert("El usuario no existe"); window.location="listado_usuario.php";</script>';
    exit();
}

$query_check = "SELECT ID_rol FROM usuario WHERE DNI_U = '$id'";
$res_check = mysqli_query($con, $query_check);
$fila_check = mysqli_fetch_array($res_check);

if($fila_check['ID_rol'] == 3){ // Si es Estudiante
    // Verificar si tiene calificaciones
    $query_calif = "SELECT id_calificacion FROM calificaciones WHERE id_alumno = '$id'";
    $res_calif = mysqli_query($con, $query_calif);
    
    if(mysqli_num_rows($res_calif) > 0){
        echo '<script>alert("No se puede eliminar el estudiante porque tiene calificaciones registradas"); window.location="listado_usuario.php";</script>';
        exit();
    }
    
    // Verificar si tiene inasistencias
    $query_inasist = "SELECT id_inasistencia FROM inasistencias WHERE id_alumno = '$id'";
    $res_inasist = mysqli_query($con, $query_inasist);
    
    if(mysqli_num_rows($res_inasist) > 0){
        echo '<script>alert("No se puede eliminar el estudiante porque tiene inasistencias registradas"); window.location="listado_usuario.php";</script>';
        exit();
    }
}

if($fila_check['ID_rol'] == 8){ // Si es Tutor
    $query_tutor = "SELECT id_AlumnoxTutor FROM alumnoxtutor WHERE id_tutor = '$id'";
    $res_tutor = mysqli_query($con, $query_tutor);
    
    if(mysqli_num_rows($res_tutor) > 0){
        echo '<script>alert("No se puede eliminar el tutor porque tiene alumnos asignados"); window.location="listado_usuario.php";</script>';
        exit();
    }
}

if($fila_check['ID_rol'] == 2){ // Si es Profesor
    $query_docente = "SELECT id_dmc FROM docentemateriacurso WHERE id_docente = '$id'";
    $res_docente = mysqli_query($con, $query_docente);
    
    if(mysqli_num_rows($res_docente) > 0){
        echo '<script>alert("No se puede eliminar el docente porque tiene materias asignadas"); window.location="listado_usuario.php";</script>';
        exit();
    }
}

// Eliminar el usuario
$query = "DELETE FROM usuario WHERE DNI_U = '$id'";
$res = mysqli_query($con, $query);

if($res) {
    header("Location: listado_usuario.php");
    exit();
} else {
    echo '<script>alert("Error al eliminar el usuario"); history.go(-1);</script>';
}

mysqli_close($con);
?>