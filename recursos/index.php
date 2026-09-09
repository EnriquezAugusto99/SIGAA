<?php session_start(); ?>
<?php
// Generar token CSRF si no existe
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="styles.css">
    <title>Inicio Sesion</title>
</head>
<body>
    <div class="caja">
        <h1>Modulo Inicio Sesion</h1>
        <form action="validar_login.php" method="post">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
            <p>
                <label for="txt_dni">DNI</label>
                <input type="number" name="txt_dni" id="txt_dni" placeholder="Ingrese su DNI" min="15000000" required>
            </p>

            <p>
                <label for="txt_contraseña">Contraseña</label>
                <input type="password" name="txt_contraseña" id="txt_contraseña" placeholder="Ingrese su contraseña" required>
            </p>

            <p>
                <button type="submit">Guardar</button>
                
                <button type="reset">Limpiar los campos</button>
            </p>
        </form>
        <p>
            <center>
                <a href="../index.html"><button>Volver</button></a>
            </center>
                
        </p>
    </div>
</body>
</html>