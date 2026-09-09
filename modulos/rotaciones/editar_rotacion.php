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

if($_SESSION['rol'] != 'Admin' && $_SESSION['rol'] != 'Preceptor'){
    echo '<script>alert("No tiene permisos para gestionar rotaciones"); window.location="../../recursos/panel.php";</script>';
    exit();
}

include '../../recursos/conexion.php';

$id = $_GET['id'];

$query = "SELECT * FROM rotaciones WHERE ID_rotacion = '$id'";
$res = mysqli_query($con, $query);

if(mysqli_num_rows($res) == 0){
    echo '<script>alert("ERROR: La rotación no existe"); window.location="rotaciones.php";</script>';
    exit();
}

$row = mysqli_fetch_array($res);

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
    <title>Editar Rotación</title>
    <style>
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            font-weight: 600;
            margin-bottom: 5px;
        }
        .form-group input, .form-group select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 6px;
        }
        .grid-2 {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }
    </style>
</head>
<body>
    <div class="caja" style="max-width: 800px;">
        <h1>Editar Rotación</h1>
        
        <form action="guardar_editar_rotacion.php" method="post">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
            <input type="hidden" name="id" value="<?php echo $row['ID_rotacion']; ?>">
            <input type="hidden" name="anio" value="<?php echo $row['anio']; ?>">
            
            <div class="grid-2">
                <div class="form-group">
                    <label>Número de Rotación</label>
                    <input type="number" name="numero_rotacion" value="<?php echo $row['numero_rotacion']; ?>" min="1" max="6" required>
                </div>
                <div class="form-group">
                    <label>Nombre</label>
                    <input type="text" name="nombre" value="<?php echo htmlspecialchars($row['nombre']); ?>" required>
                </div>
                <div class="form-group">
                    <label>Fecha Inicio</label>
                    <input type="date" name="fecha_inicio" value="<?php echo $row['fecha_inicio']; ?>" required>
                </div>
                <div class="form-group">
                    <label>Fecha Fin</label>
                    <input type="date" name="fecha_fin" value="<?php echo $row['fecha_fin']; ?>" required>
                </div>
                <div class="form-group">
                    <label>Duración (semanas)</label>
                    <input type="number" name="duracion_semanas" value="<?php echo $row['duracion_semanas']; ?>" min="1" max="12" placeholder="Opcional">
                </div>
                <div class="form-group">
                    <label>Estado</label>
                    <select name="activo">
                        <option value="1" <?php echo $row['activo'] == 1 ? 'selected' : ''; ?>>Activo</option>
                        <option value="0" <?php echo $row['activo'] == 0 ? 'selected' : ''; ?>>Inactivo</option>
                    </select>
                </div>
            </div>
            
            <p>
                <button type="submit">Guardar Cambios</button>
                <a href="rotaciones.php?filtro_anio=<?php echo $row['anio']; ?>"><button type="button">Cancelar</button></a>
            </p>
        </form>
    </div>
</body>
</html>
<?php mysqli_close($con); ?>