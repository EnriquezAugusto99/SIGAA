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
    echo '<script>alert("No tiene permisos para gestionar asignaciones docente-materia-curso"); window.location="../../recursos/panel.php";</script>';
    exit();
}

include '../../recursos/conexion.php';

$id = $_GET['id'];

// 1. Verificar que el ID sea numérico
if(!is_numeric($id)){
    echo '<script>alert("ERROR: ID inválido"); window.location="listado_docentemateriacurso.php";</script>';
    exit();
}

// 2. Verificar si la asignación existe y obtener datos
$query_verificar = "SELECT docentemateriacurso.id_dmc, usuario.DNI_U as dni_docente, usuario.Nombre as nombre_docente, usuario.Apellido as apellido_docente, materia.ID_materia as id_materia, materia.Nom_materia as nombre_materia, curso.ID_curso as id_curso, curso.curso as curso_numero, curso.division as curso_division, curso.turno as curso_turno FROM docentemateriacurso INNER JOIN usuario ON docentemateriacurso.id_docente = usuario.DNI_U INNER JOIN materia ON docentemateriacurso.id_materia = materia.ID_materia INNER JOIN curso ON docentemateriacurso.id_curso = curso.ID_curso WHERE docentemateriacurso.id_dmc = '$id'";
$res_verificar = mysqli_query($con, $query_verificar);

if(mysqli_num_rows($res_verificar) == 0){
    echo '<script>alert("ERROR: La relación no existe"); window.location="listado_docentemateriacurso.php";</script>';
    exit();
}

$fila = mysqli_fetch_array($res_verificar);
$nombre_docente = $fila['apellido_docente'] . ', ' . $fila['nombre_docente'];
$nombre_materia = $fila['nombre_materia'];
$turno_texto = $fila['curso_turno'] == 'M' ? 'Mañana' : 'Tarde';
$nombre_curso = $fila['curso_numero'] . '° "' . $fila['curso_division'] . '" - ' . $turno_texto;

// 3. Verificar si hay calificaciones asociadas a esta asignación
$query_calificaciones = "SELECT COUNT(*) as total_calificaciones FROM calificaciones WHERE id_docente = '" . $fila['dni_docente'] . "' AND id_materia = '" . $fila['id_materia'] . "' AND EXISTS (SELECT 1 FROM usuario WHERE DNI_U = calificaciones.id_alumno AND id_curso = '" . $fila['id_curso'] . "')";
$res_calificaciones = mysqli_query($con, $query_calificaciones);
$fila_calificaciones = mysqli_fetch_array($res_calificaciones);

if($fila_calificaciones['total_calificaciones'] > 0){
    echo '<script>
        if(!confirm("ADVERTENCIA: Esta asignación tiene ' . $fila_calificaciones['total_calificaciones'] . ' calificación(es) registrada(s).\\n\\nSi elimina esta asignación, las calificaciones podrían quedar sin referencia.\\n\\n¿Está seguro que desea eliminar esta asignación?")) {
            window.location = "listado_docentemateriacurso.php";
            exit();
        }
    </script>';
}

// 4. Verificar si hay horarios asociados a esta asignación
$query_horarios = "SELECT COUNT(*) as total_horarios FROM horarios WHERE id_docente = '" . $fila['dni_docente'] . "' AND id_materia = '" . $fila['id_materia'] . "' AND id_curso = '" . $fila['id_curso'] . "'";
$res_horarios = mysqli_query($con, $query_horarios);
$fila_horarios = mysqli_fetch_array($res_horarios);

if($fila_horarios['total_horarios'] > 0){
    echo '<script>
        if(!confirm("ADVERTENCIA: Esta asignación tiene ' . $fila_horarios['total_horarios'] . ' horario(s) asignado(s).\\n\\nSi elimina esta asignación, los horarios quedarán sin docente.\\n\\n¿Está seguro que desea eliminar esta asignación?")) {
            window.location = "listado_docentemateriacurso.php";
            exit();
        }
    </script>';
}

// 5. Eliminar la asignación
$query = "DELETE FROM docentemateriacurso WHERE id_dmc = '$id'";
$res = mysqli_query($con, $query);

if($res){
    echo '<script>alert("Asignación eliminada exitosamente:"); window.location="listado_docentemateriacurso.php";</script>';
    exit();
} else {
    echo '<script>alert("ERROR: No se pudo eliminar la asignación"); window.location="listado_docentemateriacurso.php";</script>';
}

mysqli_close($con);
?>