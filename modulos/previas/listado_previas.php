<?php
session_start();

if(isset($_SESSION["dni"]) && (time() - $_SESSION["ultima_actividad"] > 18000)){
    echo '<script>alert("Se venció el tiempo de la sesion"); window.location="../../index.php";</script>';
    exit();
}

if(!isset($_SESSION["dni"])){
    echo '<script>alert("Se venció el tiempo de la sesion"); window.location="../../index.php";</script>';
    exit();
}

$es_admin = ($_SESSION['rol'] == 'Admin');
$es_preceptor = ($_SESSION['rol'] == 'Preceptor');
$es_equipo = ($_SESSION['rol'] == 'Equipo de Orientacion');

if(!$es_admin && !$es_preceptor && !$es_equipo){
    echo '<script>alert("No tiene permisos para gestionar previas"); window.location="../../recursos/panel.php";</script>';
    exit();
}

include '../../recursos/conexion.php';

$preceptor_dni = $_SESSION["dni"];
$filtro_curso = $_GET['filtro_curso'] ?? '';
$filtro_alumno = $_GET['filtro_alumno'] ?? '';
$filtro_aprobado = $_GET['filtro_aprobado'] ?? '';

// Construir WHERE
$where = "WHERE 1=1";

if(!$es_admin && !$es_equipo){
    $where .= " AND u.id_curso IN (SELECT id_curso FROM preceptorxcurso WHERE id_preceptor = '$preceptor_dni')";
}

if(!empty($filtro_curso)){
    $where .= " AND u.id_curso = '$filtro_curso'";
}

if(!empty($filtro_alumno)){
    $filtro_alumno_esc = mysqli_real_escape_string($con, $filtro_alumno);
    $where .= " AND (u.Nombre LIKE '%$filtro_alumno_esc%' OR u.Apellido LIKE '%$filtro_alumno_esc%' OR u.DNI_U LIKE '%$filtro_alumno_esc%')";
}

if($filtro_aprobado !== ''){
    $where .= " AND p.Aprobado = '$filtro_aprobado'";
}

$query = "SELECT p.ID_previas, p.DNI_U, p.ID_materia, p.ID_taller, p.Aprobado, p.ID_tp,
                 u.Nombre, u.Apellido, u.id_curso,
                 m.Nom_materia,
                 t.nombre as nom_taller,
                 tp.nom_tp,
                 c.curso as curso_alumno, c.division, c.turno,
                 CASE 
                     WHEN p.ID_materia IS NOT NULL THEN (SELECT curso FROM curso WHERE ID_curso = (SELECT id_curso FROM materia WHERE ID_materia = p.ID_materia))
                     WHEN p.ID_taller IS NOT NULL THEN 
                         CASE WHEN t.anio_taller = 'I' THEN 1 ELSE 2 END
                 END as anio_item,
                 CASE 
                     WHEN p.ID_materia IS NOT NULL THEN 'materia'
                     ELSE 'taller'
                 END as tipo_item
          FROM previas p
          INNER JOIN usuario u ON p.DNI_U = u.DNI_U
          INNER JOIN tipo_previa tp ON p.ID_tp = tp.ID_tp
          INNER JOIN curso c ON u.id_curso = c.ID_curso
          LEFT JOIN materia m ON p.ID_materia = m.ID_materia
          LEFT JOIN talleres t ON p.ID_taller = t.ID_taller
          $where
          ORDER BY p.Aprobado ASC, u.Apellido ASC, 
                   CASE WHEN p.ID_materia IS NOT NULL THEN m.Nom_materia ELSE t.nombre END ASC";

$res = mysqli_query($con, $query);

// Obtener cursos para filtro (EXCLUYENDO 1° AÑO)
$cursos_filtro = [];
// Si es Admin o Equipo, ve todos los cursos excepto 1°
// Si es Preceptor, solo los suyos excepto 1°
if($es_admin || $es_equipo){
    $query_cursos = "SELECT ID_curso, curso, division, turno FROM curso WHERE curso != 1 ORDER BY curso, division";
} else {
    // Preceptor: solo cursos asignados, excluyendo 1°
    $query_cursos = "SELECT c.ID_curso, c.curso, c.division, c.turno
                     FROM curso c
                     INNER JOIN preceptorxcurso pxc ON c.ID_curso = pxc.id_curso
                     WHERE pxc.id_preceptor = '$preceptor_dni'
                     AND c.curso != 1
                     ORDER BY c.curso, c.division";
}
$res_cursos = mysqli_query($con, $query_cursos);
while($row = mysqli_fetch_array($res_cursos)){
    $cursos_filtro[] = $row;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Listado de Previas - EPET N° 34</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Montserrat', sans-serif; background: #f5f5f5; padding: 30px 20px; }
        .container { max-width: 1400px; margin: 0 auto; }
        .header { background: linear-gradient(135deg, #3F070B 0%, #710A14 100%); border-radius: 12px; padding: 25px 30px; margin-bottom: 30px; color: white; }
        .header h1 { font-size: 28px; margin-bottom: 8px; display: flex; align-items: center; gap: 12px; }
        .card { background: white; border-radius: 12px; padding: 25px; margin-bottom: 30px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
        .filtros-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 20px; }
        .filtro-group { display: flex; flex-direction: column; gap: 8px; }
        .filtro-group label { font-weight: 600; color: #333; font-size: 13px; }
        .filtro-group select, .filtro-group input { padding: 10px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 14px; font-family: 'Montserrat', sans-serif; }
        .btn-primary { background: #710A14; color: white; border: none; padding: 10px 20px; border-radius: 8px; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 8px; }
        .btn-secondary { background: #666; color: white; border: none; padding: 10px 20px; border-radius: 8px; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 8px; text-decoration: none; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #710A14; color: white; padding: 14px; text-align: left; }
        td { padding: 12px 14px; border-bottom: 1px solid #e0e0e0; }
        tr:hover td { background: #fdf5f5; }
        .badge-pendiente { background: #ffc107; color: #856404; padding: 4px 10px; border-radius: 20px; font-size: 12px; font-weight: 600; display: inline-block; }
        .badge-aprobado { background: #28a745; color: white; padding: 4px 10px; border-radius: 20px; font-size: 12px; font-weight: 600; display: inline-block; }
        .badge-anio { background: #710A14; color: white; padding: 2px 8px; border-radius: 12px; font-size: 11px; font-weight: 500; display: inline-block; margin-top: 4px; }
        .badge-curso { background: #e0e0e0; color: #333; padding: 4px 10px; border-radius: 20px; font-size: 12px; display: inline-block; }
        .badge-taller { background: #2196F3; color: white; padding: 2px 8px; border-radius: 12px; font-size: 10px; font-weight: 500; display: inline-block; margin-left: 8px; }
        .btn-edit { background: #2196F3; color: white; border: none; padding: 6px 12px; border-radius: 6px; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; font-size: 12px; text-decoration: none; }
        .btn-edit:hover { background: #1976D2; }
        .empty-state { text-align: center; padding: 60px; color: #999; }
        .btn-group { display: flex; gap: 15px; flex-wrap: wrap; margin-top: 20px; }
        .materia-cell { display: flex; flex-direction: column; gap: 4px; }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1><i class="fas fa-list"></i> Listado de Previas</h1>
        <p><i class="fas fa-graduation-cap"></i> Gestión de materias previas y equivalencias</p>
    </div>

    <div class="card">
        <h2 style="margin-bottom: 20px;"><i class="fas fa-filter"></i> Filtros de Búsqueda</h2>
        
        <form method="GET" action="">
            <div class="filtros-grid">
                <div class="filtro-group">
                    <label><i class="fas fa-school"></i> Curso</label>
                    <select name="filtro_curso">
                        <option value="">Todos los cursos</option>
                        <?php foreach($cursos_filtro as $curso): 
                            $turno_texto = ($curso['turno'] == 'M') ? 'Mañana' : 'Tarde';
                            $curso_nombre = $curso['curso'] . '° "' . $curso['division'] . '" - ' . $turno_texto;
                        ?>
                            <option value="<?= $curso['ID_curso'] ?>" <?= ($filtro_curso == $curso['ID_curso']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($curso_nombre) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filtro-group">
                    <label><i class="fas fa-user"></i> Alumno</label>
                    <input type="text" name="filtro_alumno" placeholder="Nombre, apellido o DNI..." value="<?= htmlspecialchars($filtro_alumno) ?>">
                </div>
                <div class="filtro-group">
                    <label><i class="fas fa-check-circle"></i> Estado</label>
                    <select name="filtro_aprobado">
                        <option value="">Todos</option>
                        <option value="0" <?= ($filtro_aprobado === '0') ? 'selected' : '' ?>>Pendiente</option>
                        <option value="1" <?= ($filtro_aprobado === '1') ? 'selected' : '' ?>>Aprobado</option>
                    </select>
                </div>
                <div class="filtro-group" style="justify-content: flex-end;">
                    <button type="submit" class="btn-primary"><i class="fas fa-search"></i> Filtrar</button>
                    <a href="listado_previas.php" class="btn-secondary"><i class="fas fa-times"></i> Limpiar</a>
                </div>
            </div>
        </form>
    </div>

    <div class="card">
        <div style="overflow-x: auto;">
            <table>
                <thead>
                    <tr>
                        <th>Alumno</th>
                        <th>Curso Actual</th>
                        <th>Materia/Taller que Adeuda</th>
                        <th>Tipo</th>
                        <th>Estado</th>
                        <?php if($es_admin || $es_preceptor): ?><th>Acciones</th><?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php if(mysqli_num_rows($res) > 0): 
                        while($row = mysqli_fetch_array($res)):
                            $turno_texto = ($row['turno'] == 'M') ? 'Mañana' : 'Tarde';
                            $curso_texto = $row['curso_alumno'] . '° "' . $row['division'] . '" - ' . $turno_texto;
                            $nombre_item = $row['Nom_materia'] ?? $row['nom_taller'];
                            $badge_taller = ($row['tipo_item'] == 'taller') ? '<span class="badge-taller">Taller</span>' : '';
                    ?>
                        <tr>
                            <td>
                                <strong><?= htmlspecialchars($row['Apellido']) ?></strong>, <?= htmlspecialchars($row['Nombre']) ?><br>
                                <small>DNI: <?= $row['DNI_U'] ?></small>
                            </td>
                            <td><span class="badge-curso"><?= htmlspecialchars($curso_texto) ?></span></td>
                            <td>
                                <div class="materia-cell">
                                    <strong><?= htmlspecialchars($nombre_item) ?> <?= $badge_taller ?></strong>
                                    <span class="badge-anio"><?= $row['anio_item'] ?>° Año</span>
                                </div>
                            </td>
                            <td>
                                <?php if($row['nom_tp'] == "Previa"): ?>
                                    <span>P</span>
                                <?php else: ?>
                                    <span>E</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if($row['Aprobado'] == 1): ?>
                                    <span class="badge-aprobado"><i class="fas fa-check"></i> Aprobado</span>
                                <?php else: ?>
                                    <span class="badge-pendiente"><i class="fas fa-clock"></i> Pendiente</span>
                                <?php endif; ?>
                            </div>
                            </td>
<?php if($es_admin || $es_preceptor): ?>
                            <td>
                                <a href="editar_previa.php?id=<?= $row['ID_previas'] ?>" class="btn-edit">
                                    <i class="fas fa-edit"></i> Editar
                                </a>
                             </div>
<?php endif; ?>
                            </td>
                        </tr>
                    <?php endwhile; else: ?>
                        <tr>
                            <td colspan="7" class="empty-state">
                                <i class="fas fa-inbox" style="font-size: 48px; margin-bottom: 15px; display: block;"></i>
                                No hay previas registradas
                             </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <div class="btn-group">
            <!--<a href="previas.php" class="btn-primary"><i class="fas fa-plus"></i> Registrar Nueva Previa</a>-->
            <a href="../../recursos/panel.php" class="btn-secondary"><i class="fas fa-arrow-left"></i> Volver al Panel</a>
        </div>
    </div>
</div>
</body>
</html>
<?php mysqli_close($con); ?>