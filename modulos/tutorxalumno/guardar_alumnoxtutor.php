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
    echo '<script>alert("No tiene permisos para gestionar relaciones tutor-alumno"); window.location="../../recursos/panel.php";</script>';
    exit();
}

// Verificar CSRF token
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    $_SESSION = array();
    session_destroy();
    header("Location: ../../index.php");
    exit();
}

$id_alumno = $_POST['txt_alumno'];
$id_tutor = $_POST['txt_tutor'];
$id_curso = $_POST['txt_curso'];

if(empty($id_alumno) || empty($id_tutor)){
    echo '<script>alert("ERROR: Debe seleccionar tanto un alumno como un tutor"); history.go(-1);</script>';
    exit();
}

if($id_alumno == $id_tutor){
    echo '<script>alert("ERROR: Un alumno no puede ser su propio tutor"); history.go(-1);</script>';
    exit();
}

if(!is_numeric($id_alumno) || !is_numeric($id_tutor)){
    echo '<script>alert("ERROR: Los datos enviados no son válidos"); history.go(-1);</script>';
    exit();
}

include '../../recursos/conexion.php';

// ============================================
// VERIFICAR ALUMNO
// ============================================
$query_verificar_alumno = "SELECT DNI_U, Nombre, Apellido, id_curso 
                           FROM usuario 
                           WHERE DNI_U = '$id_alumno' 
                           AND ID_rol = 3 
                           AND ID_Estado = 1
                           AND id_curso = '$id_curso'";
$res_verificar_alumno = mysqli_query($con, $query_verificar_alumno);

if(mysqli_num_rows($res_verificar_alumno) == 0){
    // Verificar si el alumno tiene rol ESTUDIANTE como rol adicional
    $query_alumno_adicional = "SELECT u.DNI_U, u.Nombre, u.Apellido, u.id_curso 
                               FROM usuario u
                               INNER JOIN usuario_rol ur ON u.DNI_U = ur.dni_usuario
                               WHERE u.DNI_U = '$id_alumno' 
                               AND ur.id_rol = 3 
                               AND ur.activo = 1 
                               AND u.ID_Estado = 1
                               AND u.id_curso = '$id_curso'";
    $res_alumno_adicional = mysqli_query($con, $query_alumno_adicional);
    
    if(mysqli_num_rows($res_alumno_adicional) == 0){
        echo '<script>alert("ERROR: El alumno seleccionado no existe o no pertenece al curso indicado"); history.go(-1);</script>';
        exit();
    }
    $fila_alumno = mysqli_fetch_array($res_alumno_adicional);
} else {
    $fila_alumno = mysqli_fetch_array($res_verificar_alumno);
}

$nombre_alumno = $fila_alumno['Apellido'] . ', ' . $fila_alumno['Nombre'];

// ============================================
// VERIFICAR TUTOR (rol principal o adicional)
// ============================================
$es_tutor_valido = false;
$fila_tutor = null;

// 1. Verificar como tutor principal (ID_rol = 8)
$query_verificar_tutor = "SELECT DNI_U, Nombre, Apellido 
                          FROM usuario 
                          WHERE DNI_U = '$id_tutor' 
                          AND ID_rol = 8 
                          AND ID_Estado = 1";
$res_verificar_tutor = mysqli_query($con, $query_verificar_tutor);

if(mysqli_num_rows($res_verificar_tutor) > 0){
    $es_tutor_valido = true;
    $fila_tutor = mysqli_fetch_array($res_verificar_tutor);
} else {
    // 2. Verificar como tutor adicional (id_rol = 8 en usuario_rol)
    $query_tutor_adicional = "SELECT u.DNI_U, u.Nombre, u.Apellido 
                              FROM usuario u
                              INNER JOIN usuario_rol ur ON u.DNI_U = ur.dni_usuario
                              WHERE u.DNI_U = '$id_tutor' 
                              AND ur.id_rol = 8 
                              AND ur.activo = 1 
                              AND u.ID_Estado = 1";
    $res_tutor_adicional = mysqli_query($con, $query_tutor_adicional);
    
    if(mysqli_num_rows($res_tutor_adicional) > 0){
        $es_tutor_valido = true;
        $fila_tutor = mysqli_fetch_array($res_tutor_adicional);
    }
}

if(!$es_tutor_valido){
    echo '<script>alert("ERROR: El tutor seleccionado no existe o no está activo"); history.go(-1);</script>';
    exit();
}

$nombre_tutor = $fila_tutor['Apellido'] . ', ' . $fila_tutor['Nombre'];

// ============================================
// VERIFICAR SI LA RELACIÓN YA EXISTE
// ============================================
$query_verificar_relacion = "SELECT id_AlumnoxTutor 
                            FROM alumnoxtutor 
                            WHERE id_alumno = '$id_alumno' AND id_tutor = '$id_tutor'";
$res_verificar_relacion = mysqli_query($con, $query_verificar_relacion);

if(mysqli_num_rows($res_verificar_relacion) > 0){
    echo '<script>alert("ADVERTENCIA: Esta relación ya existe entre ' . $nombre_alumno . ' y ' . $nombre_tutor . '"); history.go(-1);</script>';
    exit();
}

// ============================================
// GUARDAR LA RELACIÓN
// ============================================
$query = "INSERT INTO alumnoxtutor (id_alumno, id_tutor) VALUES ('$id_alumno', '$id_tutor')";
$res = mysqli_query($con, $query);

if($res){
    echo '<script>alert("✅ Relación creada exitosamente entre ' . $nombre_alumno . ' y ' . $nombre_tutor . '"); window.location="listado_alumnoxtutor.php";</script>';
    exit();
} else {
    echo '<script>alert("ERROR: No se pudo guardar la relación. Error: ' . mysqli_error($con) . '"); history.go(-1);</script>';
}

mysqli_close($con);
?>