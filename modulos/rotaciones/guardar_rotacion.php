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

$numero_rotacion = $_POST['numero_rotacion'];
$nombre = trim($_POST['nombre']);
$fecha_inicio = $_POST['fecha_inicio'];
$fecha_fin = $_POST['fecha_fin'];
$duracion_semanas = !empty($_POST['duracion_semanas']) ? $_POST['duracion_semanas'] : null;
$anio = $_POST['anio'];
$activo = $_POST['activo'] ?? 1;

if(empty($numero_rotacion) || empty($nombre) || empty($fecha_inicio) || empty($fecha_fin)){
    echo '<script>alert("ERROR: Todos los campos son requeridos"); history.go(-1);</script>';
    exit();
}

include '../../recursos/conexion.php';

// Verificar si ya existe una rotación con el mismo número para este año
$query_check = "SELECT ID_rotacion FROM rotaciones WHERE numero_rotacion = '$numero_rotacion' AND anio = '$anio'";
$res_check = mysqli_query($con, $query_check);

if(mysqli_num_rows($res_check) > 0){
    echo '<script>alert("ERROR: Ya existe una rotación con el número ' . $numero_rotacion . ' para el año ' . $anio . '"); history.go(-1);</script>';
    exit();
}

$query = "INSERT INTO rotaciones (numero_rotacion, nombre, fecha_inicio, fecha_fin, duracion_semanas, anio, activo) 
          VALUES ('$numero_rotacion', '" . mysqli_real_escape_string($con, $nombre) . "', '$fecha_inicio', '$fecha_fin', " . ($duracion_semanas ? "'$duracion_semanas'" : "NULL") . ", '$anio', '$activo')";

if(mysqli_query($con, $query)){
    echo '<script>alert("Rotación creada exitosamente"); window.location="rotaciones.php?filtro_anio=' . $anio . '";</script>';
} else {
    echo '<script>alert("ERROR: No se pudo guardar la rotación"); history.go(-1);</script>';
}

mysqli_close($con);
?>