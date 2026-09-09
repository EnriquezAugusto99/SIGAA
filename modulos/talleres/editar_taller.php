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
    echo '<script>alert("No tiene permisos para gestionar talleres"); window.location="../../recursos/panel.php";</script>';
    exit();
}

include '../../recursos/conexion.php';

$id = $_GET['id'];

// Verificar que el taller existe
$query_verificar = "SELECT * FROM talleres WHERE ID_taller = '$id'";
$res_verificar = mysqli_query($con, $query_verificar);

if(mysqli_num_rows($res_verificar) == 0){
    echo '<script>alert("ERROR: El taller no existe"); window.location="listado_talleres.php";</script>';
    exit();
}

$fila = mysqli_fetch_array($res_verificar);

// Verificar si el taller tiene referencias (para advertencia)
$query_docentes = "SELECT COUNT(*) as total FROM docente_taller WHERE ID_taller = '$id'";
$res_docentes = mysqli_query($con, $query_docentes);
$fila_docentes = mysqli_fetch_array($res_docentes);
$tiene_docentes = $fila_docentes['total'] > 0;

$query_cursos = "SELECT COUNT(*) as total FROM tallerxcurso WHERE ID_taller = '$id'";
$res_cursos = mysqli_query($con, $query_cursos);
$fila_cursos = mysqli_fetch_array($res_cursos);
$tiene_cursos = $fila_cursos['total'] > 0;

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
    <title>Editar Taller</title>
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
        <h1>Editar Taller</h1>
        
        <?php if($tiene_docentes || $tiene_cursos): ?>
        <div class="advertencia">
            <strong>ADVERTENCIA:</strong> 
            <?php if($tiene_docentes): ?>Este taller tiene <?php echo $fila_docentes['total']; ?> docente(s) asignado(s).<?php endif; ?>
            <?php if($tiene_cursos): ?>Este taller tiene <?php echo $fila_cursos['total']; ?> curso(s) asignado(s).<?php endif; ?>
            Cualquier cambio afectará a las asignaciones existentes.
        </div>
        <?php endif; ?>
        
        <form action="guardar_editarTaller.php" method="post">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
            <input type="hidden" name="txt_id" value="<?php echo $fila['ID_taller']; ?>">
            
            <p>
                <label for="txt_nombre">Nombre del Taller:</label>
                <input type="text" name="txt_nombre" id="txt_nombre" value="<?php echo htmlspecialchars($fila['nombre']); ?>" required maxlength="100">
            </p>

            <p>
                <label for="txt_anio">Año del Taller:</label>
                <select name="txt_anio" id="txt_anio" required>
                    <option value="">Seleccione el nivel</option>
                    <option value="I" <?php echo $fila['anio_taller'] == 'I' ? 'selected' : ''; ?>>I (Primer año)</option>
                    <option value="II" <?php echo $fila['anio_taller'] == 'II' ? 'selected' : ''; ?>>II (Segundo año)</option>
                </select>
            </p>

            <p>
                <label for="txt_descripcion">Descripción:</label>
                <textarea name="txt_descripcion" id="txt_descripcion" rows="3"><?php echo htmlspecialchars($fila['descripcion']); ?></textarea>
            </p>

            <p>
                <label for="txt_activo">Estado:</label>
                <select name="txt_activo" id="txt_activo">
                    <option value="1" <?php echo $fila['activo'] == 1 ? 'selected' : ''; ?>>Activo</option>
                    <option value="0" <?php echo $fila['activo'] == 0 ? 'selected' : ''; ?>>Inactivo</option>
                </select>
            </p>

            <p>
                <button type="submit">Guardar Cambios</button>
                <a href="listado_talleres.php"><button type="button">Cancelar</button></a>
            </p>
        </form>
    </div>
</body>
</html>
<?php
mysqli_close($con);
?>