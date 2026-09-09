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

// Verificar permisos (solo Admin y Preceptor pueden gestionar talleres)
if($_SESSION['rol'] != 'Admin' && $_SESSION['rol'] != 'Preceptor'){
    echo '<script>alert("No tiene permisos para gestionar talleres"); window.location="../../recursos/panel.php";</script>';
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
    <title>ABM Talleres</title>
</head>
<body>
    <div class="caja">
        <h1>Módulo Talleres</h1>
        <form action="guardar_taller.php" method="post">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
            
            <p>
                <label for="txt_nombre">Nombre del Taller</label>
                <input type="text" name="txt_nombre" id="txt_nombre" placeholder="Ej: Electricidad, Carpintería, Informática..." required maxlength="100">
            </p>

            <p>
                <label for="txt_anio">Año del Taller</label>
                <select name="txt_anio" id="txt_anio" required>
                    <option value="">Seleccione el nivel</option>
                    <option value="I">I (Primer año)</option>
                    <option value="II">II (Segundo año)</option>
                </select>
            </p>

            <p>
                <label for="txt_descripcion">Descripción</label>
                <textarea name="txt_descripcion" id="txt_descripcion" rows="3" placeholder="Descripción del taller (opcional)"></textarea>
            </p>

            <p>
                <label for="txt_activo">Estado</label>
                <select name="txt_activo" id="txt_activo">
                    <option value="1">Activo</option>
                    <option value="0">Inactivo</option>
                </select>
            </p>

            <p>
                <button type="submit">Guardar</button>
                <button type="reset">Limpiar los campos</button>
            </p>
        </form>
        <p>
            <a href="listado_talleres.php"><button>Ver Listado de Talleres</button></a>
            <a href="../../recursos/panel.php"><button>Volver al Panel</button></a>
        </p>
    </div>
</body>
</html>