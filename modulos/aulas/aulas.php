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

    // Verificar permisos (solo Admin, Preceptor y Secretario pueden gestionar aulas)
    if($_SESSION['rol'] != 'Admin' && $_SESSION['rol'] != 'Preceptor' && $_SESSION['rol'] != 'Secretario'){
        echo '<script>alert("No tiene permisos para gestionar aulas"); window.location="../../recursos/panel.php";</script>';
        exit();
    }

    // Generar token CSRF si no existe
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../../recursos/styles.css">
    <title>ABM Aulas</title>
</head>
<body>
    <div class="caja">
        <h1>Módulo Aulas</h1>
        <form action="guardar_aula.php" method="post">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
            <p>
                <label for="txt_aula">Nombre del Aula</label>
                <input type="text" name="txt_aula" id="txt_aula" placeholder="Ej: Laboratorio 1, Aula 2A" required maxlength="50">
            </p>

            <p>
                <label for="txt_descripcion">Descripción</label>
                <input type="text" name="txt_descripcion" id="txt_descripcion" placeholder="Ingrese la descripción del aula">
            </p>

            <p>
                <button type="submit">Guardar</button>
                <button type="reset">Limpiar los campos</button>
            </p>
        </form>
        <p>
            <a href="listado_aulas.php"><button>Ver Listado de Aulas</button></a>
            <a href="../../recursos/panel.php"><button>Volver al Panel</button></a>
        </p>
    </div>
</body>
</html>