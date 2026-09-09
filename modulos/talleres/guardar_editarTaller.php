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
    echo '<script>alert("No tiene permisos para gestionar talleres"); window.location="../../recursos/panel.php";</script>';
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
$nombre = trim($_POST['txt_nombre']);
$anio = trim($_POST['txt_anio']);
$descripcion = trim($_POST['txt_descripcion']);
$activo = isset($_POST['txt_activo']) ? (int)$_POST['txt_activo'] : 1;

// Validaciones
if(empty($nombre)){
    echo '<script>alert("ERROR: El nombre del taller es requerido"); history.go(-1);</script>';
    exit();
}

if(strlen($nombre) > 100){
    echo '<script>alert("ERROR: El nombre del taller no puede exceder los 100 caracteres"); history.go(-1);</script>';
    exit();
}

if(empty($anio)){
    echo '<script>alert("ERROR: Debe seleccionar el año del taller (I o II)"); history.go(-1);</script>';
    exit();
}

if($anio != 'I' && $anio != 'II'){
    echo '<script>alert("ERROR: El año del taller debe ser I o II"); history.go(-1);</script>';
    exit();
}

if(!preg_match('/^[a-zA-Z0-9áéíóúÁÉÍÓÚñÑ\s\-\.]+$/', $nombre)){
    echo '<script>alert("ERROR: El nombre del taller solo puede contener letras, números, espacios, guiones y puntos"); history.go(-1);</script>';
    exit();
}

if(strlen($descripcion) > 65535){
    echo '<script>alert("ERROR: La descripción es demasiado larga"); history.go(-1);</script>';
    exit();
}

include '../../recursos/conexion.php';

// Verificar que el taller existe
$query_existe = "SELECT nombre FROM talleres WHERE ID_taller = '$id'";
$res_existe = mysqli_query($con, $query_existe);

if(mysqli_num_rows($res_existe) == 0){
    echo '<script>alert("ERROR: El taller que intenta editar no existe"); window.location="listado_talleres.php";</script>';
    exit();
}

// Verificar duplicado (mismo nombre y mismo año, excluyendo el actual)
$query_duplicado = "SELECT ID_taller FROM talleres WHERE LOWER(nombre) = LOWER('" . mysqli_real_escape_string($con, $nombre) . "') AND anio_taller = '$anio' AND ID_taller != '$id'";
$res_duplicado = mysqli_query($con, $query_duplicado);

if(mysqli_num_rows($res_duplicado) > 0){
    echo '<script>alert("ERROR: Ya existe otro taller con el nombre \'' . htmlspecialchars($nombre) . '\' para ' . ($anio == 'I' ? 'primer año' : 'segundo año') . '"); history.go(-1);</script>';
    exit();
}

// Verificar si tiene referencias para advertencia (no bloquea, solo advierte)
$query_docentes = "SELECT COUNT(*) as total FROM docente_taller WHERE ID_taller = '$id'";
$res_docentes = mysqli_query($con, $query_docentes);
$fila_docentes = mysqli_fetch_array($res_docentes);

$query_cursos = "SELECT COUNT(*) as total FROM tallerxcurso WHERE ID_taller = '$id'";
$res_cursos = mysqli_query($con, $query_cursos);
$fila_cursos = mysqli_fetch_array($res_cursos);

if($fila_docentes['total'] > 0 || $fila_cursos['total'] > 0){
    echo '<script>
        if(!confirm("ADVERTENCIA: Este taller tiene ' . $fila_docentes['total'] . ' docente(s) y ' . $fila_cursos['total'] . ' curso(s) asignado(s).\\n\\n¿Está seguro de que desea modificar los datos del taller?")) {
            history.go(-1);
        }
    </script>';
}

// Actualizar el taller
$query = "UPDATE talleres SET nombre = '" . mysqli_real_escape_string($con, $nombre) . "', anio_taller = '$anio', descripcion = '" . mysqli_real_escape_string($con, $descripcion) . "', activo = '$activo' WHERE ID_taller = '$id'";
$res = mysqli_query($con, $query);

if($res) {
    echo '<script>alert("Taller actualizado exitosamente"); window.location="listado_talleres.php";</script>';
    exit();
} else {
    echo '<script>alert("ERROR: No se pudo actualizar el taller."); history.go(-1);</script>';
}

mysqli_close($con);
?>