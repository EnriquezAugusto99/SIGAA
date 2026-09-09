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
if($_SESSION['rol'] != 'Admin' && $_SESSION['rol'] != 'Secretario'){
    echo '<script>alert("No tiene permisos para gestionar asignaciones preceptor-curso"); window.location="../../recursos/panel.php";</script>';
    exit();
}

// Verificar CSRF token
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    $_SESSION = array();
    session_destroy();
    header("Location: ../../index.php");
    exit();
}

$id_preceptor = $_POST['txt_preceptor'];
$id_curso = $_POST['txt_curso'];

if(empty($id_preceptor) || empty($id_curso)){
    echo '<script>alert("ERROR: Debe seleccionar preceptor y curso"); history.go(-1);</script>';
    exit();
}

include '../../recursos/conexion.php';

// Verificar que el preceptor exista y sea preceptor (ID_rol = 1)
$query_preceptor = "SELECT DNI_U, Nombre, Apellido FROM usuario WHERE DNI_U = '$id_preceptor' AND ID_rol = 1 AND ID_Estado = 1";
$res_preceptor = mysqli_query($con, $query_preceptor);

if(mysqli_num_rows($res_preceptor) == 0){
    echo '<script>alert("ERROR: El preceptor seleccionado no existe o no está activo"); history.go(-1);</script>';
    exit();
}
$preceptor = mysqli_fetch_assoc($res_preceptor);
$nombre_preceptor = $preceptor['Apellido'] . ', ' . $preceptor['Nombre'];

// Verificar que el curso exista
$query_curso = "SELECT ID_curso, curso, division, turno FROM curso WHERE ID_curso = '$id_curso'";
$res_curso = mysqli_query($con, $query_curso);

if(mysqli_num_rows($res_curso) == 0){
    echo '<script>alert("ERROR: El curso seleccionado no existe"); history.go(-1);</script>';
    exit();
}
$curso = mysqli_fetch_assoc($res_curso);
$turno_texto = ($curso['turno'] == 'M') ? 'Mañana' : 'Tarde';
$nombre_curso = $curso['curso'] . '° "' . $curso['division'] . '" - ' . $turno_texto;

// Verificar si la asignación ya existe
$query_check = "SELECT id_preceptorxcurso FROM preceptorxcurso WHERE id_preceptor = '$id_preceptor' AND id_curso = '$id_curso'";
$res_check = mysqli_query($con, $query_check);

if(mysqli_num_rows($res_check) > 0){
    echo '<script>alert("ADVERTENCIA: Esta asignación ya existe\\nPreceptor: ' . $nombre_preceptor . '\\nCurso: ' . $nombre_curso . '"); history.go(-1);</script>';
    exit();
}

// Guardar la asignación
$query = "INSERT INTO preceptorxcurso (id_preceptor, id_curso) VALUES ('$id_preceptor', '$id_curso')";
$res = mysqli_query($con, $query);

if($res){
    echo '<script>alert("✅ Asignación creada exitosamente\\nPreceptor: ' . $nombre_preceptor . '\\nCurso: ' . $nombre_curso . '"); window.location="listado_preceptorxcurso.php";</script>';
    exit();
} else {
    echo '<script>alert("ERROR: No se pudo guardar la asignación. Error: ' . mysqli_error($con) . '"); history.go(-1);</script>';
}

mysqli_close($con);
?>