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
if($_SESSION['rol'] != 'Admin'){
    echo '<script>alert("No tiene permisos para eliminar roles adicionales"); window.location="../../recursos/panel.php";</script>';
    exit();
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$dni_usuario = isset($_GET['dni']) ? (int)$_GET['dni'] : 0;

if($id <= 0){
    echo '<script>alert("ID de asignación inválido"); window.location="listado_usuario_rol.php";</script>';
    exit();
}

include '../../recursos/conexion.php';

// Verificar que la asignación exista
$query_check = "SELECT ur.id_usuario_rol, ur.dni_usuario, u.Nombre, u.Apellido, r.nom_rol 
                FROM usuario_rol ur
                INNER JOIN usuario u ON ur.dni_usuario = u.DNI_U
                INNER JOIN rol r ON ur.id_rol = r.ID_rol
                WHERE ur.id_usuario_rol = '$id'";
$res_check = mysqli_query($con, $query_check);

if(mysqli_num_rows($res_check) == 0){
    echo '<script>alert("La asignación no existe"); window.location="listado_usuario_rol.php";</script>';
    exit();
}

$asignacion = mysqli_fetch_assoc($res_check);
$nombre_usuario = $asignacion['Apellido'] . ', ' . $asignacion['Nombre'];
$nombre_rol = $asignacion['nom_rol'];

// Eliminar la asignación
$query_delete = "DELETE FROM usuario_rol WHERE id_usuario_rol = '$id'";
$res_delete = mysqli_query($con, $query_delete);

if($res_delete){
    // Verificar si el usuario tiene otros roles adicionales
    $query_count = "SELECT COUNT(*) as total FROM usuario_rol WHERE dni_usuario = '$dni_usuario' AND activo = 1";
    $res_count = mysqli_query($con, $query_count);
    $count = mysqli_fetch_assoc($res_count)['total'];
    
    if($count == 0){
        // Si no tiene más roles adicionales, desactivar el flag
        $query_update = "UPDATE usuario SET tiene_multiples_roles = 0 WHERE DNI_U = '$dni_usuario'";
        mysqli_query($con, $query_update);
    }
    
    echo '<script>alert("✅ Rol adicional eliminado exitosamente\nUsuario: ' . $nombre_usuario . '\nRol: ' . $nombre_rol . '"); window.location="listado_usuario_rol.php";</script>';
} else {
    echo '<script>alert("❌ Error al eliminar la asignación: ' . mysqli_error($con) . '"); window.location="listado_usuario_rol.php";</script>';
}

mysqli_close($con);
?>