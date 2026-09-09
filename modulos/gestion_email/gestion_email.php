<?php
session_start();

if(isset($_SESSION["dni"]) && (time() - $_SESSION["ultima_actividad"] > 18000)){
    echo '<script>alert("Se venció el tiempo de la sesion"); window.location="../../index.php";</script>';
    exit();
}

if(!isset($_SESSION["dni"])){
    echo '<script>alert("Se venció el tiempo de la sesion"); window.location="../../index.php";</script>';
    exit();
}

include '../../recursos/conexion.php';

$dni = $_SESSION["dni"];
$error = '';
$success = '';

// Obtener email actual
$query = "SELECT email FROM usuario WHERE DNI_U = '$dni'";
$res = mysqli_query($con, $query);
$email_actual = '';
if($res && $fila = mysqli_fetch_assoc($res)){
    $email_actual = $fila['email'];
}

// Procesar actualización de email
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['actualizar_email'])){
    
    // Verificar token CSRF
    if(!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']){
        $error = "Token de seguridad inválido. Por favor, recargue la página.";
    } else {
        $nuevo_email = trim($_POST['nuevo_email']);
        
        // ============================================
        // VALIDACIONES DE SEGURIDAD
        // ============================================
        
        // 1. Validar que no esté vacío
        if(empty($nuevo_email)){
            $error = "El correo electrónico no puede estar vacío.";
        }
        // 2. Validar formato de email (máxima seguridad)
        elseif(!filter_var($nuevo_email, FILTER_VALIDATE_EMAIL)){
            $error = "El formato del correo electrónico no es válido. Ejemplo: usuario@dominio.com";
        }
        // 3. Validar longitud máxima (evitar ataques de buffer)
        elseif(strlen($nuevo_email) > 150){
            $error = "El correo electrónico no puede superar los 150 caracteres.";
        }
        // 4. Validar caracteres permitidos (solo letras, números, puntos, guiones, @, _)
        elseif(!preg_match('/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/', $nuevo_email)){
            $error = "El correo contiene caracteres no permitidos.";
        }
        // 5. Validar dominios comunes (evitar correos temporales)
        else {
            $dominios_bloqueados = ['tempmail.com', '10minutemail.com', 'guerrillamail.com', 'mailinator.com', 'throwawaymail.com'];
            $partes = explode('@', $nuevo_email);
            $dominio = strtolower(end($partes));
            
            if(in_array($dominio, $dominios_bloqueados)){
                $error = "No se permiten correos temporales o desechables. Use un correo personal válido.";
            } else {
                // Escapar para SQL (protección adicional)
                $nuevo_email_escapado = mysqli_real_escape_string($con, $nuevo_email);
                
                // Verificar si el email ya está en uso por otro usuario
                $query_check = "SELECT DNI_U FROM usuario WHERE email = '$nuevo_email_escapado' AND DNI_U != '$dni'";
                $res_check = mysqli_query($con, $query_check);
                if(mysqli_num_rows($res_check) > 0){
                    $error = "Este correo electrónico ya está registrado por otro usuario.";
                } else {
                    // Actualizar email
                    $query_update = "UPDATE usuario SET email = '$nuevo_email_escapado' WHERE DNI_U = '$dni'";
                    if(mysqli_query($con, $query_update)){
                        $success = "Correo electrónico actualizado correctamente.";
                        $email_actual = $nuevo_email;
                    } else {
                        $error = "Error al actualizar el correo: " . mysqli_error($con);
                    }
                }
            }
        }
    }
}

// Generar token CSRF si no existe
if(empty($_SESSION['csrf_token'])){
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Determinar si tiene email
$tiene_email = !empty($email_actual) && $email_actual !== '';
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Correo Electrónico</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Montserrat', sans-serif; background: #f5f5f5; padding: 30px 20px; }
        .container { max-width: 600px; margin: 0 auto; }
        .header { background: linear-gradient(135deg, #3F070B 0%, #710A14 100%); border-radius: 12px; padding: 25px 30px; margin-bottom: 30px; color: white; }
        .header h1 { font-size: 24px; margin-bottom: 8px; display: flex; align-items: center; gap: 12px; }
        .header p { opacity: 0.9; font-size: 14px; }
        .card { background: white; border-radius: 12px; padding: 25px; margin-bottom: 30px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
        .card h2 { font-size: 18px; color: #3F070B; margin-bottom: 20px; border-left: 4px solid #710A14; padding-left: 15px; }
        .form-group { margin-bottom: 20px; }
        .form-group label { font-weight: 600; color: #333; font-size: 14px; display: block; margin-bottom: 8px; }
        .form-group label i { color: #710A14; margin-right: 6px; }
        .form-group input { width: 100%; padding: 12px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 14px; font-family: 'Montserrat', sans-serif; transition: all 0.3s ease; }
        .form-group input:focus { outline: none; border-color: #710A14; box-shadow: 0 0 0 3px rgba(113,10,20,0.1); }
        .form-group input:disabled { background: #f5f5f5; color: #666; cursor: not-allowed; }
        .btn-primary { background: #710A14; color: white; border: none; padding: 12px 24px; border-radius: 8px; font-weight: 600; cursor: pointer; transition: all 0.3s ease; display: inline-flex; align-items: center; gap: 8px; width: 100%; justify-content: center; }
        .btn-primary:hover { background: #3F070B; transform: translateY(-2px); }
        .btn-secondary { background: #666; color: white; border: none; padding: 12px 24px; border-radius: 8px; font-weight: 600; cursor: pointer; transition: all 0.3s ease; display: inline-flex; align-items: center; gap: 8px; text-decoration: none; justify-content: center; }
        .btn-secondary:hover { background: #555; transform: translateY(-2px); }
        .btn-group { display: flex; gap: 15px; margin-top: 20px; flex-wrap: wrap; }
        .mensaje-exito, .mensaje-error { padding: 12px 15px; border-radius: 8px; margin-bottom: 20px; display: block; }
        .mensaje-exito { background: #d4edda; color: #155724; border-left: 4px solid #28a745; }
        .mensaje-error { background: #f8d7da; color: #721c24; border-left: 4px solid #dc3545; }
        .info-text { background: #e8f0fe; padding: 12px; border-radius: 8px; font-size: 13px; color: #3F070B; margin-top: 20px; display: flex; align-items: center; gap: 10px; }
        .badge-email { display: inline-flex; align-items: center; gap: 6px; padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; }
        .badge-success { background: #d4edda; color: #155724; }
        .badge-danger { background: #f8d7da; color: #721c24; }
        .correo-actual { background: #f0f0f0; padding: 15px; border-radius: 8px; margin-bottom: 20px; text-align: center; }
        .correo-actual span { font-weight: bold; color: #710A14; }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1><i class="fas fa-envelope"></i> Gestión de Correo Electrónico</h1>
        <p><i class="fas fa-info-circle"></i> Agrega o actualiza tu correo para recibir notificaciones y recuperar tu contraseña</p>
    </div>

    <div class="card">
        <h2><i class="fas fa-edit"></i> Configuración de Email</h2>
        
        <?php if($error): ?>
            <div class="mensaje-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <?php if($success): ?>
            <div class="mensaje-exito"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>
        
        <div class="correo-actual">
            <strong>📧 Estado actual:</strong><br>
            <?php if($tiene_email): ?>
                <span class="badge-email badge-success"><i class="fas fa-check-circle"></i> Correo registrado</span>
                <p style="margin-top: 12px;"><strong>Correo actual:</strong> <?= htmlspecialchars($email_actual) ?></p>
            <?php else: ?>
                <span class="badge-email badge-danger"><i class="fas fa-exclamation-triangle"></i> Sin correo registrado</span>
                <p style="margin-top: 12px;">⚠️ No tienes un correo asociado. ¡Agrega uno para poder recuperar tu contraseña!</p>
            <?php endif; ?>
        </div>
        
        <form method="POST" action="" id="formEmail">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <input type="hidden" name="actualizar_email" value="1">
            
            <div class="form-group">
                <label><i class="fas fa-envelope"></i> Correo actual</label>
                <input type="email" value="<?= htmlspecialchars($email_actual ?: 'No registrado') ?>" disabled>
            </div>
            
            <div class="form-group">
                <label><i class="fas fa-pen"></i> Nuevo correo electrónico</label>
                <input type="email" name="nuevo_email" id="nuevo_email" placeholder="ejemplo@correo.com" value="<?= htmlspecialchars($email_actual) ?>" required>
                <small style="color: #666; font-size: 12px; margin-top: 5px; display: block;">
                </small>
            </div>
            
            <button type="submit" class="btn-primary">
                <i class="fas fa-save"></i> Guardar Cambios
            </button>
        </form>
    </div>
    
    <div class="btn-group">
        <a href="../../recursos/panel.php" class="btn-secondary">
            <i class="fas fa-arrow-left"></i> Volver al Panel
        </a>
    </div>
</div>

<script>
document.getElementById('formEmail')?.addEventListener('submit', function(e) {
    const emailInput = document.getElementById('nuevo_email');
    const email = emailInput.value.trim();
    
    // Validación adicional en cliente
    if(email === '') {
        alert('Por favor, ingrese un correo electrónico válido.');
        e.preventDefault();
        return false;
    }
    
    // Expresión regular para validar email
    const emailRegex = /^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/;
    if(!emailRegex.test(email)) {
        alert('El formato del correo no es válido. Ejemplo: usuario@dominio.com');
        e.preventDefault();
        return false;
    }
    
    return true;
});
</script>
</body>
</html>
<?php mysqli_close($con); ?>