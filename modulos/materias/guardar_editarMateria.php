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
if($_SESSION['rol'] != 'Admin' && $_SESSION['rol'] != 'Preceptor'){
    echo '<script>alert("No tiene permisos para gestionar materias"); window.location="../../recursos/panel.php";</script>';
    exit();
}

$id = $_POST['txt_id'];
$nombre_materia = trim($_POST['txt_nombre']);
$id_curso = $_POST['txt_curso'];

if(empty($nombre_materia)){
    echo '<script>alert("ERROR: El nombre de la materia es requerido"); history.go(-1);</script>';
    exit();
}

if(strlen($nombre_materia) > 100){
    echo '<script>alert("ERROR: El nombre de la materia no puede exceder los 100 caracteres"); history.go(-1);</script>';
    exit();
}

if($id_curso == -1){
    echo '<script>alert("ERROR: Debe seleccionar un curso"); history.go(-1);</script>';
    exit();
}

if(!is_numeric($id_curso)){
    echo '<script>alert("ERROR: El curso seleccionado no es válido"); history.go(-1);</script>';
    exit();
}

include '../../recursos/conexion.php';

$query_existe = "SELECT id_curso FROM materia WHERE ID_materia = '$id'";
$res_existe = mysqli_query($con, $query_existe);
if(mysqli_num_rows($res_existe) == 0){
    echo '<script>alert("ERROR: La materia que intenta editar no existe"); window.location="listado_materia.php";</script>';
    exit();
}

$query_verificar_curso = "SELECT ID_curso FROM curso WHERE ID_curso = '$id_curso'";
$res_verificar_curso = mysqli_query($con, $query_verificar_curso);
if(mysqli_num_rows($res_verificar_curso) == 0){
    echo '<script>alert("ERROR: El curso seleccionado no existe"); history.go(-1);</script>';
    exit();
}

$query_duplicado = "SELECT ID_materia FROM materia WHERE LOWER(Nom_materia) = LOWER('" . mysqli_real_escape_string($con, $nombre_materia) . "') AND id_curso = '$id_curso' AND ID_materia != '$id'";
$res_duplicado = mysqli_query($con, $query_duplicado);

if(mysqli_num_rows($res_duplicado) > 0){
    echo '<script>alert("ERROR: Ya existe otra materia con el nombre \'' . htmlspecialchars($nombre_materia) . '\' en este curso"); history.go(-1);</script>';
    exit();
}

$query_referencias = "SELECT (SELECT COUNT(*) FROM calificaciones WHERE calificaciones.id_materia = '$id') as total_calificaciones, (SELECT COUNT(*) FROM docentemateriacurso WHERE docentemateriacurso.id_materia = '$id') as total_docentes";
$res_referencias = mysqli_query($con, $query_referencias);
$fila_ref = mysqli_fetch_array($res_referencias);

if($fila_ref['total_calificaciones'] > 0 || $fila_ref['total_docentes'] > 0){
    echo '<script>
        if(!confirm("ADVERTENCIA: Esta materia tiene ' . $fila_ref['total_calificaciones'] . ' calificación(es) y ' . $fila_ref['total_docentes'] . ' docente(s) asignado(s).\\n\\n¿Está seguro de que desea cambiar los datos de la materia?")) {
            history.go(-1);
            exit();
        }
    </script>';
}

$query = "UPDATE materia SET Nom_materia = '" . mysqli_real_escape_string($con, $nombre_materia) . "', id_curso = '$id_curso' WHERE ID_materia = '$id'";
$res = mysqli_query($con, $query);

if($res) {
    header("Location: listado_materia.php");
    exit();
} else {
    echo '<script>alert("Error al actualizar la materia"); history.go(-1);</script>';
}

mysqli_close($con);
?>