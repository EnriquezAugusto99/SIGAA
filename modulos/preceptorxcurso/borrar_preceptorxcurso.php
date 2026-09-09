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
    echo '<script>alert("No tiene permisos para eliminar asignaciones"); window.location="../../recursos/panel.php";</script>';
    exit();
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if($id <= 0){
    echo '<script>alert("ID de asignación inválido"); window.location="listado_preceptorxcurso.php";</script>';
    exit();
}

include '../../recursos/conexion.php';

// Verificar que la asignación exista
$query_check = "SELECT pc.id_preceptorxcurso, p.Nombre, p.Apellido, c.curso, c.division 
                FROM preceptorxcurso pc
                INNER JOIN usuario p ON pc.id_preceptor = p.DNI_U
                INNER JOIN curso c ON pc.id_curso = c.ID_curso
                WHERE pc.id_preceptorxcurso = '$id'";
$res_check = mysqli_query($con, $query_check);

if(mysqli_num_rows($res_check) == 0){
    echo '<script>alert("La asignación no existe"); window.location="listado_preceptorxcurso.php";</script>';
    exit();
}

$asignacion = mysqli_fetch_assoc($res_check);
$nombre_preceptor = $asignacion['Apellido'] . ', ' . $asignacion['Nombre'];
$curso_nombre = $asignacion['curso'] . '° "' . $asignacion['division'] . '"';

// Eliminar la asignación
$query_delete = "DELETE FROM preceptorxcurso WHERE id_preceptorxcurso = '$id'";
$res_delete = mysqli_query($con, $query_delete);

if($res_delete){
    echo '<script>alert("✅ Asignación eliminada exitosamente\\nPreceptor: ' . $nombre_preceptor . '\\nCurso: ' . $curso_nombre . '"); window.location="listado_preceptorxcurso.php";</script>';
} else {
    echo '<script>alert("❌ Error al eliminar la asignación: ' . mysqli_error($con) . '"); window.location="listado_preceptorxcurso.php";</script>';
}

mysqli_close($con);
?>