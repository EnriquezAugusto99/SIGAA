<?php
session_start();

if(!isset($_SESSION["dni"]) || $_SESSION['rol'] != 'Preceptor'){
    header("Location: ../../index.php");
    exit();
}

include '../../recursos/conexion.php';
$dni = $_SESSION["dni"];

$query = "SELECT c.ID_curso, c.curso, c.division, c.turno
          FROM curso c
          INNER JOIN preceptorxcurso pxc ON c.ID_curso = pxc.id_curso
          WHERE pxc.id_preceptor = '$dni'
          ORDER BY c.curso, c.division";
$result = mysqli_query($con, $query);
$cursos = [];
while($row = mysqli_fetch_assoc($result)){
    $cursos[] = $row;
}

if(empty($cursos)){
    $error = "No tienes cursos asignados. Contacta al administrador.";
}

if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['curso_id'])){
    $_SESSION['curso_activo_preceptor'] = $_POST['curso_id'];
    header("Location: ../../recursos/panel.php");
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
    <title>Seleccionar curso - Preceptor</title>
    <link rel="stylesheet" href="../../recursos/styles.css">
    <style>
        .btn-curso {
            width:100%;
            margin:8px 0;
            padding:12px;
            border-radius:8px;
            color:#E0F7FA;
            font-size:1.1rem;
            cursor:pointer;
            transition:0.3s;
        }
        .btn-curso:hover {
            background: #3F070B;
            transform:translateY(-2px);
        }
    </style>
</head>
<body>
<div class="caja" style="max-width:600px; margin:50px auto; text-align:center;">
    <h1>Bienvenido, <?php echo $_SESSION['nombre']; ?></h1>
    <h2>Seleccione el curso que desea gestionar</h2>
    <?php if(isset($error)): ?>
        <div class="mensaje-error"><?php echo $error; ?></div>
        <p><a href="../../recursos/cerrar_sesion.php">Cerrar sesión</a></p>
    <?php else: ?>
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
            <?php foreach($cursos as $c): 
                $turno = ($c['turno'] == 'M') ? 'Mañana' : 'Tarde';
                $nombre = $c['curso'] . '° "' . $c['division'] . '" - ' . $turno;
            ?>
                <button type="submit" name="curso_id" value="<?= $c['ID_curso'] ?>" class="btn-curso">
                    <?= htmlspecialchars($nombre) ?>
                </button>
            <?php endforeach; ?>
        </form>
        <p style="margin-top:20px;"><a href="../../recursos/cerrar_sesion.php">Cerrar sesión</a></p>
    <?php endif; ?>
</div>
</body>
</html>