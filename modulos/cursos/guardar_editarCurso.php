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
    echo '<script>alert("No tiene permisos para gestionar cursos"); window.location="../../recursos/panel.php";</script>';
    exit();
}

$id = $_POST['txt_id'];
$curso = $_POST['txt_curso'];
$division = $_POST['txt_division'];
$turno = $_POST['txt_turno'];

// VALIDACIONES
if(empty($curso)){
    echo '<script>alert("El año es requerido"); history.go(-1);</script>';
    exit();
}

if($division == -1){
    echo '<script>alert("Debe seleccionar una división"); history.go(-1);</script>';
    exit();
}

if($turno == -1){
    echo '<script>alert("Debe seleccionar un turno"); history.go(-1);</script>';
    exit();
}

if(!is_numeric($curso)){
    echo '<script>alert("El año debe ser un número"); history.go(-1);</script>';
    exit();
}

if($curso < 1 || $curso > 6){
    echo '<script>alert("El año debe estar entre 1 y 6"); history.go(-1);</script>';
    exit();
}

// Validar que la división sea una letra válida
$divisiones_validas = ['A', 'B', 'C', 'D', 'E', 'F'];
if(!in_array($division, $divisiones_validas)){
    echo '<script>alert("División no válida"); history.go(-1);</script>';
    exit();
}

// Validar que el turno sea válido
$turnos_validos = ['M', 'T'];
if(!in_array($turno, $turnos_validos)){
    echo '<script>alert("Turno no válido"); history.go(-1);</script>';
    exit();
}

include '../../recursos/conexion.php';

// Verificar si el curso ya existe en OTRO registro
$query_verificar = "SELECT ID_curso FROM curso WHERE curso = '$curso' AND division = '$division' AND turno = '$turno' AND ID_curso != '$id'";
$res_verificar = mysqli_query($con, $query_verificar);

if(mysqli_num_rows($res_verificar) > 0){
    echo '<script>alert("ERROR: Ya existe otro curso con esa combinación (Año: ' . $curso . '°, División: ' . $division . ', Turno: ' . ($turno == 'M' ? 'Mañana' : 'Tarde') . ')"); history.go(-1);</script>';
    exit();
}

// Verificar si el curso tiene alumnos asignados (solo para advertencia)
$query_check = "SELECT COUNT(*) as total FROM usuario WHERE id_curso = '$id' AND ID_rol = 3"; // Solo estudiantes
$res_check = mysqli_query($con, $query_check);
$fila_check = mysqli_fetch_array($res_check);

if($fila_check['total'] > 0){
    echo '<script>
        if(!confirm("ADVERTENCIA: Este curso tiene ' . $fila_check['total'] . ' estudiantes asignados. ¿Está seguro de que desea cambiar los datos del curso?")) {
            history.go(-1);
        }
    </script>';
}

// Verificar si el curso tiene horarios asignados
$query_horarios = "SELECT COUNT(*) as total FROM horarios WHERE id_curso = '$id'";
$res_horarios = mysqli_query($con, $query_horarios);
$fila_horarios = mysqli_fetch_array($res_horarios);

if($fila_horarios['total'] > 0){
    echo '<script>
        if(!confirm("ADVERTENCIA: Este curso tiene ' . $fila_horarios['total'] . ' horarios asignados. ¿Está seguro de que desea cambiar los datos del curso?")) {
            history.go(-1);
        }
    </script>';
}

// Actualizar el curso
$query = "UPDATE curso SET curso = '$curso', division = '$division', turno = '$turno' WHERE ID_curso = '$id'";
$res = mysqli_query($con, $query);

if($res) {
    header("Location: listado_curso.php");
    exit();
} else {
    echo '<script>alert("Error al actualizar el curso"); history.go(-1);</script>';
}

mysqli_close($con);
?>