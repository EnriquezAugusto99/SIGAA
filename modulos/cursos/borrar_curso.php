<?php
session_start();

if(isset($_SESSION["dni"]) && (time() - $_SESSION["ultima_actividad"] > 1800)){
    header("Location: ../../index.php");
    exit();
}

if(!isset($_SESSION["dni"])){
    header("Location: ../../index.php");
    exit();
}

// Verificar permisos
if($_SESSION['rol'] != 'Admin' && $_SESSION['rol'] != 'Preceptor'){
    $_SESSION['mensaje_error'] = "No tiene permisos para gestionar cursos";
    header("Location: listado_curso.php");
    exit();
}

// Verificar CSRF token
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    $_SESSION['mensaje_error'] = "Token de seguridad inválido";
    header("Location: listado_curso.php");
    exit();
}

include '../../recursos/conexion.php';

// Obtener ID desde POST
$id = isset($_POST['id']) ? $_POST['id'] : 0;

if(empty($id) || !is_numeric($id)){
    $_SESSION['mensaje_error'] = "ID de curso no válido";
    header("Location: listado_curso.php");
    exit();
}

// Verificar si el curso existe
$query_verificar = "SELECT curso, division, turno FROM curso WHERE ID_curso = '$id'";
$res_verificar = mysqli_query($con, $query_verificar);

if(!$res_verificar || mysqli_num_rows($res_verificar) == 0){
    $_SESSION['mensaje_error'] = "El curso con ID $id no existe";
    header("Location: listado_curso.php");
    exit();
}

$fila = mysqli_fetch_array($res_verificar);
$curso_nombre = $fila['curso'] . '° "' . $fila['division'] . '" - ' . ($fila['turno'] == 'M' ? 'Mañana' : 'Tarde');

// Verificar si el curso tiene estudiantes asignados
$query_estudiantes = "SELECT COUNT(*) as total FROM usuario WHERE id_curso = '$id' AND ID_rol = 3";
$res_estudiantes = mysqli_query($con, $query_estudiantes);
$fila_est = mysqli_fetch_array($res_estudiantes);

if($fila_est['total'] > 0){
    $_SESSION['mensaje_error'] = "No se puede eliminar el curso '$curso_nombre' porque tiene " . $fila_est['total'] . " estudiantes asignados";
    header("Location: listado_curso.php");
    exit();
}

// Verificar si el curso tiene horarios asignados
$query_horarios = "SELECT COUNT(*) as total FROM horarios WHERE id_curso = '$id'";
$res_horarios = mysqli_query($con, $query_horarios);
$fila_hor = mysqli_fetch_array($res_horarios);

if($fila_hor['total'] > 0){
    $_SESSION['mensaje_error'] = "No se puede eliminar el curso '$curso_nombre' porque tiene " . $fila_hor['total'] . " horarios asignados";
    header("Location: listado_curso.php");
    exit();
}

// Verificar si el curso tiene docentes asignados
$query_docentes = "SELECT COUNT(*) as total FROM docentemateriacurso WHERE id_curso = '$id'";
$res_docentes = mysqli_query($con, $query_docentes);
$fila_doc = mysqli_fetch_array($res_docentes);

if($fila_doc['total'] > 0){
    $_SESSION['mensaje_error'] = "No se puede eliminar el curso '$curso_nombre' porque tiene " . $fila_doc['total'] . " docentes asignados";
    header("Location: listado_curso.php");
    exit();
}

// Verificar si el curso tiene preceptores asignados
$query_preceptores = "SELECT COUNT(*) as total FROM preceptorxcurso WHERE id_curso = '$id'";
$res_preceptores = mysqli_query($con, $query_preceptores);
$fila_pre = mysqli_fetch_array($res_preceptores);

if($fila_pre['total'] > 0){
    $_SESSION['mensaje_error'] = "No se puede eliminar el curso '$curso_nombre' porque tiene " . $fila_pre['total'] . " preceptores asignados";
    header("Location: listado_curso.php");
    exit();
}

// Verificar si el curso tiene inasistencias registradas
$query_inasistencias = "SELECT COUNT(*) as total FROM inasistencias WHERE id_curso = '$id'";
$res_inasistencias = mysqli_query($con, $query_inasistencias);
$fila_inas = mysqli_fetch_array($res_inasistencias);

if($fila_inas['total'] > 0){
    $_SESSION['mensaje_error'] = "No se puede eliminar el curso '$curso_nombre' porque tiene " . $fila_inas['total'] . " inasistencias registradas";
    header("Location: listado_curso.php");
    exit();
}

// Verificar si el curso tiene materias asignadas
$query_materias = "SELECT COUNT(*) as total FROM materia WHERE id_curso = '$id'";
$res_materias = mysqli_query($con, $query_materias);
$fila_mat = mysqli_fetch_array($res_materias);

if($fila_mat['total'] > 0){
    $_SESSION['mensaje_error'] = "No se puede eliminar el curso '$curso_nombre' porque tiene " . $fila_mat['total'] . " materias asignadas";
    header("Location: listado_curso.php");
    exit();
}

// Eliminar el curso
$query = "DELETE FROM curso WHERE ID_curso = '$id'";
$res = mysqli_query($con, $query);

if($res) {
    $_SESSION['mensaje_exito'] = "Curso '$curso_nombre' eliminado correctamente";
    header("Location: listado_curso.php");
    exit();
} else {
    $_SESSION['mensaje_error'] = "Error al eliminar el curso: " . mysqli_error($con);
    header("Location: listado_curso.php");
    exit();
}

mysqli_close($con);
?>