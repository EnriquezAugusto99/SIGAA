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

// Verificar permisos
if($_SESSION['rol'] != 'Admin' && $_SESSION['rol'] != 'Preceptor' && $_SESSION['rol'] != 'Secretario'){
    echo '<script>alert("No tiene permisos para gestionar aulas"); window.location="../../recursos/panel.php";</script>';
    exit();
}

include '../../recursos/conexion.php';

$id = $_GET['id'];

// Verificar que el aula existe
$query_verificar = "SELECT * FROM aulas WHERE id_aula = '$id'";
$res_verificar = mysqli_query($con, $query_verificar);

if(mysqli_num_rows($res_verificar) == 0){
    echo '<script>alert("ERROR: El aula no existe"); window.location="listado_aulas.php";</script>';
    exit();
}

$fila = mysqli_fetch_array($res_verificar);

// Verificar si el aula tiene horarios asignados (para advertencia)
$query_horarios = "SELECT COUNT(*) as total FROM horarios WHERE id_aula = '$id'";
$res_horarios = mysqli_query($con, $query_horarios);
$fila_horarios = mysqli_fetch_array($res_horarios);
$tiene_horarios = $fila_horarios['total'] > 0;

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
    <title>Editar Aula</title>
    <style>
        .advertencia {
            background: #FFEB3B;
            color: #333;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 15px;
            border-left: 5px solid #FFC107;
        }
    </style>
</head>
<body>
    <div class="caja">
        <h1> Editar Aula</h1>
        
        <?php if($tiene_horarios): ?>
        <div class="advertencia">
             <strong>ADVERTENCIA:</strong> Esta aula tiene <?php echo $fila_horarios['total']; ?> horario(s) asignado(s). 
            Cualquier cambio afectará a los horarios asociados.
        </div>
        <?php endif; ?>
        
        <form action="guardar_editarAula.php" method="post">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
            <input type="hidden" name="txt_id" value="<?php echo $fila['id_aula']; ?>">
            
            <p>
                <label for="txt_aula">Nombre del Aula:</label>
                <input type="text" name="txt_aula" id="txt_aula" value="<?php echo htmlspecialchars($fila['aula']); ?>" required maxlength="50">
            </p>

            <p>
                <label for="txt_descripcion">Descripción:</label>
                <textarea name="txt_descripcion" id="txt_descripcion" rows="3"><?php echo htmlspecialchars($fila['descripcion']); ?></textarea>
            </p>

            <p>
                <button type="submit"> Guardar Cambios</button>
                <a href="listado_aulas.php"><button type="button"> Cancelar</button></a>
            </p>
        </form>
    </div>
</body>
</html>
<?php
mysqli_close($con);
?>