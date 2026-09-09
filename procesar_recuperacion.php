<?php
session_start();
include 'recursos/conexion.php';

// RUTA CORRECTA SEGÚN TU ESTRUCTURA
require __DIR__ . '/recursos/vendor/src/PHPMailer.php';
require __DIR__ . '/recursos/vendor/src/SMTP.php';
require __DIR__ . '/recursos/vendor/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$dni = trim($_POST['dni'] ?? '');

if(empty($dni)){
    $_SESSION['mensaje_error'] = "Ingrese su DNI.";
    header("Location: olvide_contraseña.php");
    exit();
}

if(!is_numeric($dni) || strlen($dni) != 8){
    $_SESSION['mensaje_error'] = "DNI inválido. Debe tener 8 dígitos.";
    header("Location: olvide_contraseña.php");
    exit();
}

// Buscar usuario
$query = "SELECT DNI_U, Nombre, Apellido, email FROM usuario WHERE DNI_U = '$dni' AND ID_Estado = 1";
$res = mysqli_query($con, $query);

if(mysqli_num_rows($res) == 0){
    $_SESSION['mensaje_error'] = "No se encontró un usuario con ese DNI.";
    header("Location: olvide_contraseña.php");
    exit();
}

$usuario = mysqli_fetch_assoc($res);
$dni_usuario = $usuario['DNI_U'];
$email = $usuario['email'];
$nombre = $usuario['Nombre'] . ' ' . $usuario['Apellido'];

if(empty($email)){
    $_SESSION['mensaje_error'] = "El usuario no tiene un correo electrónico registrado.";
    header("Location: olvide_contraseña.php");
    exit();
}

// Crear tabla si no existe (por las dudas)
$query_create = "CREATE TABLE IF NOT EXISTS recuperacion_password (
    id_recuperacion INT AUTO_INCREMENT PRIMARY KEY,
    dni_usuario INT NOT NULL,
    token VARCHAR(64) NOT NULL,
    fecha_solicitud DATETIME NOT NULL,
    fecha_expiracion DATETIME NOT NULL,
    usado TINYINT DEFAULT 0
)";
mysqli_query($con, $query_create);

// Verificar intentos previos
$query_intentos = "SELECT COUNT(*) as intentos FROM recuperacion_password 
                   WHERE dni_usuario = '$dni_usuario' AND fecha_solicitud > DATE_SUB(NOW(), INTERVAL 1 HOUR)";
$res_intentos = mysqli_query($con, $query_intentos);
$intentos = mysqli_fetch_assoc($res_intentos)['intentos'] ?? 0;

if($intentos >= 5){
    $_SESSION['mensaje_error'] = "Demasiados intentos. Esperá 1 hora.";
    header("Location: olvide_contraseña.php");
    exit();
}

// Generar token
$token = bin2hex(random_bytes(32));
$fecha_expiracion = date('Y-m-d H:i:s', strtotime('+1 hour'));

// Guardar token
$query_insert = "INSERT INTO recuperacion_password (dni_usuario, token, fecha_solicitud, fecha_expiracion, usado) 
                 VALUES ('$dni_usuario', '$token', NOW(), '$fecha_expiracion', 0)";
mysqli_query($con, $query_insert);

// URL de recuperación
$url_recuperacion = "https://" . $_SERVER['HTTP_HOST'] . "/calificaciones/restablecer_password.php?token=" . $token;

// Enviar email con la misma configuración que usás en otros archivos
$mail = new PHPMailer(true);

try {
    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = 'sistema.calificacionesepet34@gmail.com';
    $mail->Password   = 'vpma sdyj taef gdao';
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = 587;
    $mail->CharSet    = 'UTF-8';
    
    $mail->SMTPOptions = [
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false,
            'allow_self_signed' => true
        ]
    ];
    
    $mail->setFrom('sistema@escuela.com', 'Sistema Escolar - EPET N°34');
    $mail->addAddress($email, $nombre);
    
    $mail->isHTML(true);
    $mail->Subject = '🔐 Recuperación de contraseña - EPET N°34';
    
    $cuerpo = '
    <div style="font-family: Arial, sans-serif;">
        <h2 style="color: #7a0000;">🔐 Recuperación de contraseña</h2>
        <p>Hola <strong>' . htmlspecialchars($nombre) . '</strong>,</p>
        <p>Recibimos una solicitud para restablecer tu contraseña.</p>
        <p>Hacé clic en el siguiente enlace para crear una nueva contraseña:</p>
        <p><a href="' . $url_recuperacion . '">' . $url_recuperacion . '</a></p>
        <p><strong>Este enlace es válido por 1 hora.</strong></p>
        <hr>
        <p style="font-size: 12px;">Si no solicitaste este cambio, ignorá este mensaje.</p>
    </div>';
    
    $mail->Body = $cuerpo;
    $mail->AltBody = "Recuperación de contraseña\n\nHola $nombre,\n\nHacé clic en este enlace: $url_recuperacion\n\nEl enlace es válido por 1 hora.";
    
    $mail->send();
    
    $_SESSION['mensaje_exito'] = "✅ Se envió un enlace de recuperación a tu correo electrónico.";
    
} catch(Exception $e) {
    error_log("Error al enviar email: " . $mail->ErrorInfo);
    $_SESSION['mensaje_error'] = "Error al enviar el correo. Intentá más tarde.";
}

header("Location: olvide_contraseña.php");
exit();
?>