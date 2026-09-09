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

include '../../recursos/conexion.php';

$es_admin = ($_SESSION['rol'] == 'Admin');
$es_profesor = ($_SESSION['rol'] == 'Profesor');
$es_equipo = ($_SESSION['rol'] == 'Equipo de Orientacion');

if($_SESSION['rol'] != 'Admin' && $_SESSION['rol'] != 'Preceptor' && $_SESSION['rol'] != 'Profesor' && $_SESSION['rol'] != 'Equipo de Orientacion'){
    echo '<script>alert("No tiene permisos para ver planillas"); window.location="../../recursos/panel.php";</script>';
    exit();
}

// Si es profesor, verificar que sea profesor de taller
if($es_profesor){
    $dni = $_SESSION['dni'];
    $query_verificar = "SELECT COUNT(*) as total FROM docente_taller_curso WHERE ID_docente = '$dni'";
    $res_verificar = mysqli_query($con, $query_verificar);
    $row_verificar = mysqli_fetch_assoc($res_verificar);
    if($row_verificar['total'] == 0){
        echo '<script>alert("No tiene permisos para ver planillas"); window.location="../../recursos/panel.php";</script>';
        exit();
    }
}


$anio_actual = date('Y');
$rotacion_filtro = $_GET['rotacion_id'] ?? '';

$where = "WHERE r.anio = '$anio_actual'";
if($rotacion_filtro){
    $where .= " AND pr.ID_rotacion = '$rotacion_filtro'";
}

$query = "SELECT 
            r.ID_rotacion,
            r.numero_rotacion,
            r.nombre as rotacion_nombre,
            c.ID_curso,
            c.curso,
            c.division,
            c.turno,
            pr.numero_grupo,
            t.ID_taller,
            t.nombre as taller_nombre,
            t.anio_taller
          FROM planilla_rotaciones pr
          INNER JOIN rotaciones r ON pr.ID_rotacion = r.ID_rotacion
          INNER JOIN curso c ON pr.ID_curso = c.ID_curso
          INNER JOIN talleres t ON pr.ID_taller = t.ID_taller
          $where
          ORDER BY r.numero_rotacion, c.curso, c.division, pr.numero_grupo";

$res = mysqli_query($con, $query);

$query_rotaciones = "SELECT ID_rotacion, nombre FROM rotaciones WHERE anio = '$anio_actual' ORDER BY numero_rotacion";
$res_rotaciones = mysqli_query($con, $query_rotaciones);

// Procesar eliminación
if(isset($_GET['eliminar']) && isset($_GET['rotacion_id']) && isset($_GET['curso_id']) && isset($_GET['grupo'])){
    $rotacion_id_eliminar = $_GET['rotacion_id'];
    $curso_id_eliminar = $_GET['curso_id'];
    $grupo_eliminar = $_GET['grupo'];
    
    // Verificar permisos (admin puede todo, preceptor solo sus cursos)
    $puede_eliminar = false;
    if($es_admin){
        $puede_eliminar = true;
    } elseif($_SESSION['rol'] == 'Preceptor'){
        $dni_preceptor = $_SESSION['dni'];
        $query_verificar = "SELECT * FROM preceptorxcurso WHERE id_preceptor = '$dni_preceptor' AND id_curso = '$curso_id_eliminar'";
        $res_verificar = mysqli_query($con, $query_verificar);
        $puede_eliminar = (mysqli_num_rows($res_verificar) > 0);
    }
    
    if($puede_eliminar){
        $query_delete = "DELETE FROM planilla_rotaciones 
                         WHERE ID_rotacion = '$rotacion_id_eliminar' 
                         AND ID_curso = '$curso_id_eliminar' 
                         AND numero_grupo = '$grupo_eliminar'";
        
        if(mysqli_query($con, $query_delete)){
            echo '<script>alert("Asignación eliminada correctamente"); window.location="planilla_rotaciones_listado.php";</script>';
        } else {
            echo '<script>alert("Error al eliminar la asignación");</script>';
        }
    } else {
        echo '<script>alert("No tiene permisos para eliminar esta asignación");</script>';
    }
}

?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../../recursos/styles.css">
    <title>Listado de Planilla de Rotaciones</title>
    <style>
        .container {
            max-width: 1200px;
            margin: 20px auto;
            padding: 25px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            overflow-x: auto;
        }
        h1 {
            color: #7a0000;
        }
        .filtros {
            background: #f5f5f5;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        .tabla {
            width: 100%;
            border-collapse: collapse;
        }
        .tabla th, .tabla td {
            border: 1px solid #ddd;
            padding: 10px;
            text-align: center;
        }
        .tabla th {
            background: #7a0000;
            color: white;
        }
        .btn-editar {
            background: #2196F3;
            color: white;
            padding: 5px 12px;
            border-radius: 4px;
            text-decoration: none;
            font-size: 12px;
        }
        .btn-editar:hover {
            background: #1976D2;
        }
/* Contenedor con scroll horizontal */
.tabla-container {
    width: 100%;
    overflow-x: auto;
    overflow-y: visible;
    -webkit-overflow-scrolling: touch;
    margin-bottom: 20px;
}

/* Ajustes responsive */
@media (max-width: 992px) {
    .caja { padding: 15px; margin: 10px; }
    h1 { font-size: 1.4rem; text-align: center; }
    .filtro-rapido form { display: flex; flex-direction: column; gap: 8px; }
    .filtro-rapido input, .filtro-rapido button, .filtro-rapido a button { width: 100%; }
    .filtros form { display: flex; flex-direction: column; }
    .filtros input, .filtros select, .filtros button { width: 100%; margin: 5px 0; }
    p { display: flex; flex-direction: column; gap: 8px; }
    p button, p a button { width: 100%; }
}

@media (max-width: 768px) {
    h1 { font-size: 1.2rem; }
    .resumen { font-size: 0.8rem; text-align: center; }
    .tabla th, .tabla td { padding: 8px 6px; font-size: 0.75rem; }
}

@media (max-width: 480px) {
    h1 { font-size: 1rem; }
    .tabla th, .tabla td { padding: 6px 4px; font-size: 0.7rem; }
}
    </style>
</head>
<body>
<div class="container">
    <h1>Planilla de Rotaciones</h1>
    
    <div class="filtros">
        <form method="GET" action="">
            <label>Filtrar por Rotación:</label>
            <select name="rotacion_id">
                <option value="">-- Todas --</option>
                <?php while($rot = mysqli_fetch_array($res_rotaciones)): ?>
                    <option value="<?= $rot['ID_rotacion'] ?>" <?= $rotacion_filtro == $rot['ID_rotacion'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($rot['nombre']) ?>
                    </option>
                <?php endwhile; ?>
            </select>
            <button type="submit">Filtrar</button>
            <a href="planilla_rotaciones_listado.php"><button type="button">Limpiar</button></a>
        </form>
    </div>
    
<div class="tabla-container">
    <table class="tabla">
        <thead>
                        <tr>
                <th>Rotación</th>
                <th>Curso</th>
                <th>Grupo</th>
                <th>Taller</th>
                <?php if(!$es_equipo): ?>
                    <th>Editar</th>
                    <th>Eliminar</th>
                <?php endif; ?>
            </tr>
        </thead>
        <tbody>
            <?php if(mysqli_num_rows($res) > 0):
                while($row = mysqli_fetch_array($res)):
                    $turno_texto = $row['turno'] == 'M' ? 'Mañana' : 'Tarde';
                    $curso_nombre = $row['curso'] . '° "' . $row['division'] . '" - ' . $turno_texto;
            ?>
                <tr>
                    <td><?= htmlspecialchars($row['rotacion_nombre']) ?></td>
                    <td><?= $curso_nombre ?></td>
                    <td>Grupo <?= $row['numero_grupo'] ?></td>
                        <td><?= htmlspecialchars($row['taller_nombre'] . ' (' . $row['anio_taller'] . ')') ?></td>
                        <?php if(!$es_equipo): ?>
                        <td>
                            <a href="planilla_rotaciones_config.php?rotacion_id=<?= $row['ID_rotacion'] ?>&curso_id=<?= $row['ID_curso'] ?>&grupo=<?= $row['numero_grupo'] ?>" class="btn-editar">Editar</a>
                        </td>
                        <td>
                            <a href="planilla_rotaciones_listado.php?eliminar=1&rotacion_id=<?= $row['ID_rotacion'] ?>&curso_id=<?= $row['ID_curso'] ?>&grupo=<?= $row['numero_grupo'] ?>" 
                            class="btn-editar" 
                            style="background: #F44336;" 
                            onclick="return confirm('¿Está seguro de eliminar esta asignación?')">
                            Eliminar
                            </a>
                        </td>
                    <?php endif; ?>
                </tr>
            <?php endwhile; else: ?>
                <tr><td colspan="6">No hay configuraciones cargadas. <a href="planilla_rotaciones_config.php">Configurar ahora</a></td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>
    
    <p style="margin-top: 20px;">
        <?php if(!$es_equipo): ?>
            <a href="planilla_rotaciones_config.php"><button>Configurar Nueva Asignación</button></a>
        <?php endif; ?>
        <a href="../../recursos/panel.php"><button>Volver al Panel</button></a>
    </p>
</div>
</body>
</html>
<?php mysqli_close($con); ?>