<?php
include '../../recursos/conexion.php';

$token = isset($_GET['token']) ? mysqli_real_escape_string($con, $_GET['token']) : '';
$mensaje = "";
$exito = false;

if(!empty($token)){
    // Buscar si el token existe
    $query = "SELECT cl.*, 
                     u_t.Nombre AS t_nom, u_t.Apellido AS t_ape,
                     u_a.Nombre AS a_nom, u_a.Apellido AS a_ape
              FROM confirmacion_libreta cl
              INNER JOIN usuario u_t ON cl.id_tutor = u_t.DNI_U
              INNER JOIN usuario u_a ON cl.id_alumno = u_a.DNI_U
              WHERE cl.token = '$token'";
              
    $res = mysqli_query($con, $query);
    
    if(mysqli_num_rows($res) > 0){
        $datos = mysqli_fetch_assoc($res);
        
        if($datos['confirmado'] == 1){
            $mensaje = "Hola <strong>" . $datos['t_nom'] . "</strong>, esta libreta ya fue confirmada previamente el " . date('d/m/Y H:i', strtotime($datos['fecha_confirmacion'])) . " hs.";
            $exito = true;
        } else {
            // Actualizar la base de datos
            $query_upd = "UPDATE confirmacion_libreta SET confirmado = 1, fecha_confirmacion = NOW() WHERE token = '$token'";
            if(mysqli_query($con, $query_upd)){
                $mensaje = "¡Muchas gracias <strong>" . $datos['t_nom'] . " " . $datos['t_ape'] . "</strong>!<br>Se registró con éxito la notificación de la libreta de <strong>" . $datos['a_nom'] . " " . $datos['a_ape'] . "</strong> (" . $datos['tipo_envio'] . ").";
                $exito = true;
            } else {
                $mensaje = "Ocurrió un error interno al procesar la validación.";
            }
        }
    } else {
        $mensaje = "El enlace de confirmación es inválido o ha expirado.";
    }
} else {
    $mensaje = "Acceso denegado. Falta el parámetro de verificación.";
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Confirmación de Recepción - EPET N°34</title>
    <style>
        body { font-family: Arial, sans-serif; background-color: #f4f4f6; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
        .card { background: white; padding: 40px; border-radius: 10px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); text-align: center; max-width: 450px; border-top: 5px solid #7a0000; }
        h2 { color: #7a0000; margin-bottom: 20px; }
        .icon { font-size: 50px; margin-bottom: 15px; color: <?= $exito ? '#2e7d32' : '#c62828' ?>; }
        p { color: #555; line-height: 1.6; font-size: 16px; }
        .footer { margin-top: 25px; font-size: 12px; color: #999; }
    </style>
</head>
<body>
    <div class="card">
        <div class="icon"><?= $exito ? '✔' : '❌' ?></div>
        <h2>Sistema de Notificaciones</h2>
        <p><?= $mensaje ?></p>
        <div class="footer">Escuela Provincial de Educación Técnica N° 34</div>
    </div>
</body>
</html>