<?php
session_start();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recuperar Contraseña - EPET N°34</title>
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
        .info {
            background: #e8f0fe;
            border-left: 4px solid #7a0000;
            padding: 12px 15px;
            margin-bottom: 25px;
            border-radius: 8px;
            font-size: 13px;
        }
        .campo {
            margin-bottom: 20px;
        }
        .campo label {
            display: block;
            font-weight: 600;
            margin-bottom: 8px;
            color: #333;
        }
        .campo input {
            width: 100%;
            padding: 12px;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
        }
        .campo input:focus {
            outline: none;
            border-color: #7a0000;
        }
        button {
            width: 100%;
            background: #7a0000;
            color: white;
            padding: 12px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
        }
        button:hover {
            background: #5a0000;
        }
        .links {
            text-align: center;
            margin-top: 20px;
        }
        .links a {
            color: #7a0000;
            text-decoration: none;
            font-size: 14px;
        }
        .mensaje {
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: center;
        }
        .mensaje-error {
            background: #f8d7da;
            color: #721c24;
        }
        .mensaje-exito {
            background: #d4edda;
            color: #155724;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Recuperar Contraseña</h1>
        </div>
        <div class="body">
            <div class="info">
                Ingresá tu <strong>DNI</strong>. Te enviaremos un enlace para restablecer tu contraseña al email registrado.
            </div>
            
            <?php if(isset($_SESSION['mensaje_error'])): ?>
                <div class="mensaje mensaje-error"><?= htmlspecialchars($_SESSION['mensaje_error']) ?></div>
                <?php unset($_SESSION['mensaje_error']); ?>
            <?php endif; ?>
            
            <?php if(isset($_SESSION['mensaje_exito'])): ?>
                <div class="mensaje mensaje-exito"><?= htmlspecialchars($_SESSION['mensaje_exito']) ?></div>
                <?php unset($_SESSION['mensaje_exito']); ?>
            <?php endif; ?>
            
            <form action="procesar_recuperacion.php" method="post">
                <div class="campo">
                    <label>DNI</label>
                    <input type="number" name="dni" placeholder="Ej: 12345678" required>
                </div>
                <button type="submit">Enviar enlace de recuperación</button>
            </form>
            
            <div class="links">
                <a href="index.php">← Volver al inicio de sesión</a>
            </div>
        </div>
    </div>
</body>
</html>