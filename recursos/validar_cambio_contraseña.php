<?php
session_start();
if (!isset($_SESSION['dni'])) {
    header("Location: ../index.php");
    exit();
}

// Verificar CSRF token
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    $_SESSION = array();
    session_destroy();
    echo '<script>alert("Sesión expirada. Por favor, inicie sesión nuevamente"); window.location.href="../index.php";</script>';
    exit();
}

include 'conexion.php';

// OBTENER DATOS
$dni = $_SESSION['dni']; 
$con1 = $_POST["txt_contraseña"];
$con2 = $_POST["txt_confirmar_contraseña"];

// VALIDACIONES
if(empty($con1) || empty($con2)){
    echo '<script>alert("La contraseña es requerida"); history.go(-1);</script>';
    exit();
}

if($con1 !== $con2){
    echo '<script>alert("Las contraseñas no coinciden"); history.go(-1);</script>';
    exit();
}
$claves_debiles = ["0", "12345678", $dni];
if(in_array($con1, $claves_debiles)){
    echo '<script>alert("La contraseña es demasiado débil"); history.go(-1);</script>';
    exit();
}
if(isset($_POST['txt_contraseña_actual'])) {
    $stmt = mysqli_prepare($con, "SELECT clave FROM usuario WHERE DNI_U = ?");
    mysqli_stmt_bind_param($stmt, "i", $dni);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $fila = mysqli_fetch_array($res);

    if($fila['clave'] != $_POST['txt_contraseña_actual']) {
        echo '<script>alert("La contraseña actual es incorrecta"); history.go(-1);</script>';
        exit();
    }
}

// PREPARAR LA ACTUALIZACIÓN
$query_actualizacion = "UPDATE usuario SET clave = ? WHERE DNI_U = ?";
$stmt = mysqli_prepare($con, $query_actualizacion);
mysqli_stmt_bind_param($stmt, "si", $con1, $dni);
$res = mysqli_stmt_execute($stmt);
// EJECUTAR Y VERIFICAR
if($res) {
    unset($_SESSION['forzar_cambio']);
    $_SESSION = array();
    session_destroy();
    echo '<script>alert("Contraseña actualizada con éxito"); window.location.href="../index.php";</script>';
} else {
  error_log(mysqli_error($con));
  echo '<script>alert("Error al actualizar la contraseña. Intente nuevamente."); history.go(-1);</script>';
}
mysqli_close($con);
?>