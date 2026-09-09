<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

include 'recursos/conexion.php';

$token = $_GET['token'] ?? '';

if(empty($token)){
    $_SESSION['mensaje_error'] = "Token inválido.";
    header("Location: olvide_contraseña.php");
    exit();
}

// Verificar token
$query = "SELECT * FROM recuperacion_password WHERE token = '$token' AND usado = 0 AND fecha_expiracion > NOW()";
$res = mysqli_query($con, $query);

if(mysqli_num_rows($res) == 0){
    $_SESSION['mensaje_error'] = "El enlace es inválido o ya expiró.";
    header("Location: olvide_contraseña.php");
    exit();
}

$recuperacion = mysqli_fetch_assoc($res);
$dni = $recuperacion['dni_usuario'];

$error = '';

if($_SERVER['REQUEST_METHOD'] === 'POST'){
    $nueva_password = $_POST['password'] ?? '';
    $confirmar_password = $_POST['confirmar_password'] ?? '';
    
    if(empty($nueva_password) || empty($confirmar_password)){
        $error = "Complete ambos campos.";
    } elseif(strlen($nueva_password) < 8){
        $error = "La contraseña debe tener al menos 8 caracteres.";
    } elseif($nueva_password !== $confirmar_password){
        $error = "Las contraseñas no coinciden.";
    } else {
        $query_update = "UPDATE usuario SET clave = '$nueva_password' WHERE DNI_U = '$dni'";
        if(mysqli_query($con, $query_update)){
            mysqli_query($con, "UPDATE recuperacion_password SET usado = 1 WHERE token = '$token'");
            $_SESSION['mensaje_exito'] = "Contraseña actualizada correctamente. Ya podés iniciar sesión.";
            header("Location: index.php");
            exit();
        } else {
            $error = "Error al actualizar la contraseña.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Restablecer Contraseña</title>
    <link rel="stylesheet" href="recursos/styles.css">
    <style>
        .container {
            max-width: 450px;
            margin: 50px auto;
            background: white;
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        .header {
            background: #7a0000;
            padding: 25px;
            text-align: center;
        }
        .header h1 {
            color: white;
            margin: 0;
            font-size: 22px;
        }
        .body {
            padding: 30px;
        }
        .campo {
            margin-bottom: 20px;
        }
        .campo label {
            display: block;
            font-weight: 600;
            margin-bottom: 8px;
        }
        .campo input {
            width: 100%;
            padding: 12px;
            border: 2px solid #ddd;
            border-radius: 8px;
        }
        button {
            width: 100%;
            background: #7a0000;
            color: white;
            padding: 12px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
        }
        button:hover {
            background: #5a0000;
        }
        .mensaje-error {
            background: #f8d7da;
            color: #721c24;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: center;
        }
        .volver {
            text-align: center;
            margin-top: 20px;
        }
        .volver a {
            color: #7a0000;
            text-decoration: none;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1> Nueva Contraseña</h1>
        </div>
        <div class="body">
            <?php if($error): ?>
                <div class="mensaje-error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            
            <form method="post">
                <div class="campo">
                    <label>Nueva contraseña</label>
                    <input type="password" name="password" required>
                </div>
                <div class="campo">
                    <label>Confirmar contraseña</label>
                    <input type="password" name="confirmar_password" required>
                </div>
                <button type="submit">Restablecer contraseña</button>
            </form>
            
            <div class="volver">
                <a href="index.php">← Volver al inicio de sesión</a>
            </div>
        </div>
    </div>
</body>
</html>