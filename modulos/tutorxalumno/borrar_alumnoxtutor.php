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
    echo '<script>alert("No tiene permisos para gestionar relaciones tutor-alumno"); window.location="../../recursos/panel.php";</script>';
    exit();
}

include '../../recursos/conexion.php';

$id = $_GET['id'];

if(!is_numeric($id)){
    echo '<script>alert("ERROR: ID inválido"); window.location="listado_alumnoxtutor.php";</script>';
    exit();
}

// CORREGIDO: La consulta ahora tiene espacios correctos
$query_verificar = "SELECT ax.id_AlumnoxTutor,
                           a.DNI_U as dni_alumno,
                           a.Nombre as nombre_alumno,
                           a.Apellido as apellido_alumno,
                           t.DNI_U as dni_tutor,
                           t.Nombre as nombre_tutor,
                           t.Apellido as apellido_tutor
                    FROM alumnoxtutor ax
                    INNER JOIN usuario a ON ax.id_alumno = a.DNI_U
                    INNER JOIN usuario t ON ax.id_tutor = t.DNI_U
                    WHERE ax.id_AlumnoxTutor = '$id'";
    
$res_verificar = mysqli_query($con, $query_verificar);

if(!$res_verificar){
    echo '<script>alert("ERROR en consulta: ' . mysqli_error($con) . '"); window.location="listado_alumnoxtutor.php";</script>';
    exit();
}

if(mysqli_num_rows($res_verificar) == 0){
    echo '<script>alert("ERROR: La relación no existe"); window.location="listado_alumnoxtutor.php";</script>';
    exit();
}

$fila = mysqli_fetch_array($res_verificar);
$nombre_alumno = $fila['apellido_alumno'] . ', ' . $fila['nombre_alumno'];
$nombre_tutor = $fila['apellido_tutor'] . ', ' . $fila['nombre_tutor'];

// 3. Verificar si el alumno quedaría sin tutores
$query_contar_tutores = "SELECT COUNT(*) as total_tutores FROM alumnoxtutor WHERE id_alumno = '" . $fila['dni_alumno'] . "'";
$res_contar_tutores = mysqli_query($con, $query_contar_tutores);
$fila_contar = mysqli_fetch_array($res_contar_tutores);

if($fila_contar['total_tutores'] <= 1){
    echo '<script>
        if(!confirm("ADVERTENCIA: El alumno ' . $nombre_alumno . ' quedaría SIN TUTORES asignados.\\n\\n¿Está seguro que desea eliminar esta relación?")) {
            window.location = "listado_alumnoxtutor.php";
        }
    </script>';
}

// 4. Eliminar la relación
$query = "DELETE FROM alumnoxtutor WHERE id_AlumnoxTutor = '$id'";
$res = mysqli_query($con, $query);

if($res){
    echo '<script>alert("Relación eliminada exitosamente"); window.location="listado_alumnoxtutor.php";</script>';
    exit();
} else {
    echo '<script>alert("ERROR: No se pudo eliminar la relación: ' . mysqli_error($con) . '"); window.location="listado_alumnoxtutor.php";</script>';
    exit();
}

mysqli_close($con);
?>