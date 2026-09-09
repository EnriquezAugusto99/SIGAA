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
if($_SESSION['rol'] != 'Admin' && $_SESSION['rol'] != 'Preceptor'){
    echo '<script>alert("No tiene permisos para gestionar cursos"); window.location="../../recursos/panel.php";</script>';
    exit();
}

include '../../recursos/conexion.php';

$id = $_GET['id'];

// Verificar que el curso existe
$query_verificar = "SELECT * FROM curso WHERE ID_curso = '$id'";
$res_verificar = mysqli_query($con, $query_verificar);

if(mysqli_num_rows($res_verificar) == 0){
    echo '<script>alert("El curso no existe"); window.location="listado_curso.php";</script>';
    exit();
}

$fila = mysqli_fetch_array($res_verificar);

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
    <title>Editar Curso</title>
</head>
<body>
    <div class="caja">
        <h1>Editar Curso</h1>
        <form action="guardar_editarCurso.php" method="post">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
            <input type="hidden" name="txt_id" value="<?php echo $fila['ID_curso']; ?>">
            
            <p>
                <label for="txt_curso">Año:</label>
                <input type="number" name="txt_curso" id="txt_curso" value="<?php echo $fila['curso']; ?>" min="1" max="7" required>
            </p>

            <p>
                <label for="txt_division">División:</label>
                <select name="txt_division" id="txt_division" required>
                    <option value="-1">Seleccione una división</option>
                    <?php
                    $divisiones = ['A', 'B', 'C', 'D', 'E', 'F'];
                    foreach($divisiones as $div){
                        $selected = ($div == $fila['division']) ? 'selected' : '';
                        echo '<option value="'.$div.'" '.$selected.'>'.$div.'</option>';
                    }
                    ?>
                </select>
            </p>

            <p>
                <label for="txt_turno">Turno:</label>
                <select name="txt_turno" id="txt_turno" required>
                    <option value="-1">Seleccione un turno</option>
                    <option value="M" <?php echo ($fila['turno'] == 'M') ? 'selected' : ''; ?>>Mañana</option>
                    <option value="T" <?php echo ($fila['turno'] == 'T') ? 'selected' : ''; ?>>Tarde</option>
                </select>
            </p>

            <p>
                <button type="submit">Guardar Cambios</button>
                <a href="listado_curso.php"><button type="button">Cancelar</button></a>
            </p>
        </form>
    </div>
</body>
</html>
<?php
mysqli_close($con);
?>