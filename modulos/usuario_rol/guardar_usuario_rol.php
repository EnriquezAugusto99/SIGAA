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
    echo '<script>alert("No tiene permisos para gestionar roles adicionales"); window.location="../../recursos/panel.php";</script>';
    exit();
}

// Verificar CSRF token
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    $_SESSION = array();
    session_destroy();
    header("Location: ../../index.php");
    exit();
}

$dni_usuario = $_POST['txt_usuario'];
$id_rol = $_POST['txt_rol'];

if(empty($dni_usuario) || empty($id_rol)){
    echo '<script>alert("ERROR: Debe seleccionar usuario y rol"); history.go(-1);</script>';
    exit();
}

include '../../recursos/conexion.php';

// Verificar que el usuario exista y esté activo
$query_usuario = "SELECT u.DNI_U, u.Nombre, u.Apellido, u.ID_rol, u.tiene_multiples_roles, r.nom_rol as rol_principal_nombre
                  FROM usuario u
                  INNER JOIN rol r ON u.ID_rol = r.ID_rol
                  WHERE u.DNI_U = '$dni_usuario' AND u.ID_Estado = 1";
$res_usuario = mysqli_query($con, $query_usuario);

if(mysqli_num_rows($res_usuario) == 0){
    echo '<script>alert("ERROR: El usuario seleccionado no existe o no está activo"); history.go(-1);</script>';
    exit();
}
$usuario = mysqli_fetch_assoc($res_usuario);
$nombre_usuario = $usuario['Apellido'] . ', ' . $usuario['Nombre'];

// Verificar que el rol exista
$query_rol = "SELECT ID_rol, nom_rol FROM rol WHERE ID_rol = '$id_rol'";
$res_rol = mysqli_query($con, $query_rol);

if(mysqli_num_rows($res_rol) == 0){
    echo '<script>alert("ERROR: El rol seleccionado no existe"); history.go(-1);</script>';
    exit();
}
$rol = mysqli_fetch_assoc($res_rol);
$nombre_rol = $rol['nom_rol'];

// Verificar que el rol no sea el mismo que el rol principal del usuario
if($usuario['ID_rol'] == $id_rol){
    echo '<script>alert("ERROR: No puede asignar el mismo rol que el usuario ya tiene como principal.\nEl usuario ya es: ' . $nombre_usuario . ' - Rol principal: ' . $usuario['rol_principal_nombre'] . '"); history.go(-1);</script>';
    exit();
}

// Verificar si la asignación ya existe
$query_check = "SELECT id_usuario_rol FROM usuario_rol WHERE dni_usuario = '$dni_usuario' AND id_rol = '$id_rol' AND activo = 1";
$res_check = mysqli_query($con, $query_check);

if(mysqli_num_rows($res_check) > 0){
    echo '<script>alert("ADVERTENCIA: Este usuario ya tiene asignado el rol ' . $nombre_rol . ' como rol adicional.\nUsuario: ' . $nombre_usuario . '"); history.go(-1);</script>';
    exit();
}

// Guardar la asignación
$query = "INSERT INTO usuario_rol (dni_usuario, id_rol, activo, fecha_asignacion) VALUES ('$dni_usuario', '$id_rol', 1, NOW())";
$res = mysqli_query($con, $query);

if($res){
    // Actualizar el campo tiene_multiples_roles en la tabla usuario
    $query_update = "UPDATE usuario SET tiene_multiples_roles = 1 WHERE DNI_U = '$dni_usuario'";
    mysqli_query($con, $query_update);
    
    echo '<script>alert("✅ Rol adicional asignado exitosamente\nUsuario: ' . $nombre_usuario . '\nRol adicional: ' . $nombre_rol . '\n\nEl usuario ahora puede cambiar entre roles en el panel."); window.location="listado_usuario_rol.php";</script>';
    exit();
} else {
    echo '<script>alert("ERROR: No se pudo guardar la asignación. Error: ' . mysqli_error($con) . '"); history.go(-1);</script>';
}

mysqli_close($con);
?>