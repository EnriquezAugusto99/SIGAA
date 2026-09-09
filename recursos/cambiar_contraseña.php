<?php
session_start();
if (!isset($_SESSION['dni'])) {
    header("Location: ../index.php");
    exit();
}
// Generar token CSRF si no existe
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$es_forzado = isset($_SESSION['forzar_cambio']) && $_SESSION['forzar_cambio'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <title>Cambio de contraseña</title>
    <style>
        .password-container {
            position: relative;
            display: inline-block;
            width: 100%;
        }
        
        .password-container input {
            width: 100%;
            padding-right: 40px;
            box-sizing: border-box;
        }
        
        .toggle-password {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #666;
            font-size: 18px;
            background: transparent;
            border: none;
            padding: 0;
            z-index: 1;
        }
        
        .toggle-password:hover {
            color: #333;
        }
    </style>
</head>
<body>
    <div class="caja">
        <h1>Cambiar contraseña</h1>
        <form action="validar_cambio_contraseña.php" method="post">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
            
            <p>
                <label for="txt_contraseña_actual">Contraseña actual</label>
                <span class="password-container">
                    <input type="password" name="txt_contraseña_actual" id="txt_contraseña_actual" placeholder="Ingrese su contraseña actual" required>
                    <i class="far fa-eye toggle-password" onclick="togglePassword('txt_contraseña_actual')"></i>
                </span>
            </p>
            
            <p>
                <label for="txt_contraseña">Contraseña nueva</label>
                <span class="password-container">
                    <input type="password" name="txt_contraseña" id="txt_contraseña" placeholder="Ingrese la nueva contraseña" required>
                    <i class="far fa-eye toggle-password" onclick="togglePassword('txt_contraseña')"></i>
                </span>
            </p>

            <p>
                <label for="txt_confirmar_contraseña">Confirmar Contraseña</label>
                <span class="password-container">
                    <input type="password" name="txt_confirmar_contraseña" id="txt_confirmar_contraseña" placeholder="Confirme la contraseña" required>
                    <i class="far fa-eye toggle-password" onclick="togglePassword('txt_confirmar_contraseña')"></i>
                </span>
            </p>

            <p>
                <button type="submit">Actualizar contraseña</button>
                <button type="reset">Limpiar los campos</button>
                <br>
                <?php if(!$es_forzado): ?>
                <a href="../panel.php">Volver</a>
                <?php endif; ?>
            </p>
        </form>
    </div>
    
    <script>
        function togglePassword(inputId) {
            const input = document.getElementById(inputId);
            const icon = event.currentTarget;
            
            if (input.type === "password") {
                input.type = "text";
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                input.type = "password";
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }
        
        <?php if($es_forzado): ?>
        alert("ATENCIÓN: Su contraseña es poco segura. Por seguridad, debe cambiarla ahora.");
        <?php endif; ?>
    </script>
</body>
</html>