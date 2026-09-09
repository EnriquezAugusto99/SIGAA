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

// Verificar permisos (solo Admin, Preceptor y Secretario)
if($_SESSION['rol'] != 'Admin' && $_SESSION['rol'] != 'Preceptor' && $_SESSION['rol'] != 'Secretario'){
    echo '<script>alert("No tiene permisos para eliminar calificaciones"); window.location="../../recursos/panel.php";</script>';
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

// Verificar si la calificación existe
$query_verificar = "SELECT calificaciones.id_calificacion, calificaciones.nota, calificaciones.fecha, calificaciones.tipo_evaluacion, usuario.Nombre as nombre_alumno, usuario.Apellido as apellido_alumno, materia.Nom_materia as nombre_materia, usuario2.Nombre as nombre_docente, usuario2.Apellido as apellido_docente, curso.curso, curso.division, curso.turno FROM calificaciones INNER JOIN usuario ON calificaciones.id_alumno = usuario.DNI_U INNER JOIN materia ON calificaciones.id_materia = materia.ID_materia INNER JOIN usuario as usuario2 ON calificaciones.id_docente = usuario2.DNI_U INNER JOIN curso ON usuario.id_curso = curso.ID_curso WHERE calificaciones.id_calificacion = '$id'";
$res_verificar = mysqli_query($con, $query_verificar);

if(mysqli_num_rows($res_verificar) == 0){
    echo '<script>alert("La calificación no existe"); window.location="listado_calificacion.php";</script>';
    exit();
}

$fila = mysqli_fetch_array($res_verificar);
$turno_texto = $fila['turno'] == 'M' ? 'Mañana' : 'Tarde';
$curso_completo = $fila['curso'] . '° "' . $fila['division'] . '" - ' . $turno_texto;

// Mostrar confirmación antes de eliminar
echo '<script>
    if(confirm("¿Está seguro que desea eliminar esta calificación?\\n\\n' .
    'ID: ' . $fila['id_calificacion'] . '\\n' .
    'Alumno: ' . addslashes($fila['apellido_alumno'] . ', ' . $fila['nombre_alumno']) . '\\n' .
    'Materia: ' . addslashes($fila['nombre_materia']) . '\\n' .
    'Curso: ' . addslashes($curso_completo) . '\\n' .
    'Docente: ' . addslashes($fila['apellido_docente'] . ', ' . $fila['nombre_docente']) . '\\n' .
    'Nota: ' . $fila['nota'] . '\\n' .
    'Tipo: ' . addslashes($fila['tipo_evaluacion']) . '\\n' .
    'Fecha: ' . date('d/m/Y', strtotime($fila['fecha'])) . '\\n\\n' .
    'Esta acción no se puede deshacer.")) {
        ';

// Si confirma, proceder con la eliminación
$query = "DELETE FROM calificaciones WHERE id_calificacion = '$id'";
$res = mysqli_query($con, $query);

if($res) {
    echo 'alert("Calificación eliminada exitosamente"); window.location="listado_calificacion.php";';
} else {
    echo 'alert("Error al eliminar la calificación"); window.location="listado_calificacion.php";';
}

echo '} else {';
echo 'window.location="listado_calificacion.php";';
echo '}';
echo '</script>';

mysqli_close($con);
?>