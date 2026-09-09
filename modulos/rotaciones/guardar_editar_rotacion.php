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

if($_SESSION['rol'] != 'Admin' && $_SESSION['rol'] != 'Preceptor'){
    echo '<script>alert("No tiene permisos para gestionar rotaciones"); window.location="../../recursos/panel.php";</script>';
    exit();
}

if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    $_SESSION = array();
    session_destroy();
    header("Location: ../../index.php");
    exit();
}

$id = $_POST['id'];
$numero_rotacion = $_POST['numero_rotacion'];
$nombre = trim($_POST['nombre']);
$fecha_inicio = $_POST['fecha_inicio'];
$fecha_fin = $_POST['fecha_fin'];
$duracion_semanas = !empty($_POST['duracion_semanas']) ? $_POST['duracion_semanas'] : null;
$anio = $_POST['anio'];
$activo = $_POST['activo'] ?? 1;

include '../../recursos/conexion.php';

// Verificar duplicado (excluyendo el actual)
$query_check = "SELECT ID_rotacion FROM rotaciones WHERE numero_rotacion = '$numero_rotacion' AND anio = '$anio' AND ID_rotacion != '$id'";
$res_check = mysqli_query($con, $query_check);

if(mysqli_num_rows($res_check) > 0){
    echo '<script>alert("ERROR: Ya existe otra rotación con el número ' . $numero_rotacion . ' para el año ' . $anio . '"); history.go(-1);</script>';
    exit();
}

$query = "UPDATE rotaciones SET 
            numero_rotacion = '$numero_rotacion',
            nombre = '" . mysqli_real_escape_string($con, $nombre) . "',
            fecha_inicio = '$fecha_inicio',
            fecha_fin = '$fecha_fin',
            duracion_semanas = " . ($duracion_semanas ? "'$duracion_semanas'" : "NULL") . ",
            activo = '$activo'
          WHERE ID_rotacion = '$id'";

if(mysqli_query($con, $query)){
    echo '<script>alert("Rotación actualizada exitosamente"); window.location="rotaciones.php?filtro_anio=' . $anio . '";</script>';
} else {
    echo '<script>alert("ERROR: No se pudo actualizar la rotación"); history.go(-1);</script>';
}

mysqli_close($con);
?>