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

if(!$es_admin && !$es_preceptor){
    echo '<script>alert("No tiene permisos para gestionar inasistencias"); window.location="../../recursos/panel.php";</script>';
    exit();
}

include '../../recursos/conexion.php';

$preceptor_dni = $_SESSION["dni"];
$filtro_curso = $_GET['filtro_curso'] ?? '';
$filtro_alumno = $_GET['filtro_alumno'] ?? '';
$filtro_trimestre = $_GET['filtro_trimestre'] ?? '';
$filtro_justificada = $_GET['filtro_justificada'] ?? '';
$filtro_tipo_falta = $_GET['filtro_tipo_falta'] ?? '';
$fecha_desde = $_GET['fecha_desde'] ?? '';
$fecha_hasta = $_GET['fecha_hasta'] ?? '';

// Construir WHERE
$where = "WHERE 1=1";

if(!$es_admin){
    $where .= " AND i.id_curso IN (SELECT id_curso FROM preceptorxcurso WHERE id_preceptor = '$preceptor_dni')";
}

if(!empty($filtro_curso)){
    $where .= " AND i.id_curso = '$filtro_curso'";
}

if(!empty($filtro_alumno)){
    $filtro_alumno_esc = mysqli_real_escape_string($con, $filtro_alumno);
    $where .= " AND (u.Nombre LIKE '%$filtro_alumno_esc%' OR u.Apellido LIKE '%$filtro_alumno_esc%' OR u.DNI_U LIKE '%$filtro_alumno_esc%')";
}

if(!empty($filtro_trimestre)){
    $where .= " AND i.trimestre = '$filtro_trimestre'";
}

if($filtro_justificada !== ''){
    $where .= " AND i.justificada = '$filtro_justificada'";
}

if(!empty($filtro_tipo_falta)){
    $where .= " AND i.tipo_falta = '$filtro_tipo_falta'";
}

if(!empty($fecha_desde)){
    $where .= " AND i.fecha >= '$fecha_desde'";
}

if(!empty($fecha_hasta)){
    $where .= " AND i.fecha <= '$fecha_hasta'";
}

$query = "SELECT i.id_inasistencia, i.id_alumno, i.fecha, i.justificada, i.trimestre, i.id_preceptor, i.id_curso, i.tipo_falta,
                 u.Nombre, u.Apellido, u.DNI_U,
                 c.curso, c.division, c.turno,
                 p.Nombre as nom_preceptor, p.Apellido as ape_preceptor
          FROM inasistencias i
          INNER JOIN usuario u ON i.id_alumno = u.DNI_U
          INNER JOIN curso c ON i.id_curso = c.ID_curso
          INNER JOIN usuario p ON i.id_preceptor = p.DNI_U
          $where
          ORDER BY i.fecha DESC, u.Apellido ASC";

$res = mysqli_query($con, $query);

// Obtener cursos para filtro
$cursos_filtro = [];
$query_cursos = "SELECT ID_curso, curso, division, turno FROM curso ORDER BY curso, division";
$res_cursos = mysqli_query($con, $query_cursos);
while($row = mysqli_fetch_array($res_cursos)){
    $cursos_filtro[] = $row;
}

// Obtener trimestres
$trimestres = [];
$query_trimestres = "SELECT id_trimestre, trimestre FROM trimestres ORDER BY id_trimestre";
$res_trimestres = mysqli_query($con, $query_trimestres);
while($row = mysqli_fetch_array($res_trimestres)){
    $trimestres[] = $row;
}

// Contar totales (considerando valores de falta)
$total_registros = 0;
$total_justificadas = 0;
$total_injustificadas = 0;
$total_completas = 0;
$total_medias = 0;
$total_cuartos = 0;

// Resetear puntero
mysqli_data_seek($res, 0);
while($row = mysqli_fetch_array($res)){
    $total_registros++;
    if($row['justificada'] == 1){
        $total_justificadas++;
    } else {
        $total_injustificadas++;
    }
    
    switch($row['tipo_falta']){
        case 'completa': $total_completas++; break;
        case 'media': $total_medias++; break;
        case 'cuarto': $total_cuartos++; break;
    }
}
mysqli_data_seek($res, 0);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Listado de Inasistencias - EPET N° 34</title>
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
        .badge-justificada { background: #28a745; color: white; padding: 4px 10px; border-radius: 20px; font-size: 12px; font-weight: 600; display: inline-block; }
        .badge-injustificada { background: #dc3545; color: white; padding: 4px 10px; border-radius: 20px; font-size: 12px; font-weight: 600; display: inline-block; }
        .badge-curso { background: #e0e0e0; color: #333; padding: 4px 10px; border-radius: 20px; font-size: 12px; display: inline-block; }
        .badge-trimestre { background: #17a2b8; color: white; padding: 4px 10px; border-radius: 20px; font-size: 12px; display: inline-block; }
        .badge-completa { background: #dc3545; color: white; padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; display: inline-flex; align-items: center; gap: 5px; }
        .badge-media { background: #ff9800; color: white; padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; display: inline-flex; align-items: center; gap: 5px; }
        .badge-cuarto { background: #2196f3; color: white; padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; display: inline-flex; align-items: center; gap: 5px; }
        .btn-delete { background: #dc3545; color: white; border: none; padding: 6px 12px; border-radius: 6px; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; font-size: 12px; }
        .btn-delete:hover { background: #c82333; }
        .empty-state { text-align: center; padding: 60px; color: #999; }
        .btn-group { display: flex; gap: 15px; flex-wrap: wrap; margin-top: 20px; }
        .stats-card { display: inline-block; background: #f8f9fa; padding: 15px 25px; border-radius: 8px; margin-right: 15px; margin-bottom: 15px; }
        .stats-card h3 { font-size: 14px; color: #666; margin-bottom: 5px; }
        .stats-card .numero { font-size: 28px; font-weight: bold; color: #3F070B; }
        .stats-container { display: flex; flex-wrap: wrap; margin-bottom: 20px; }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1><i class="fas fa-list"></i> Listado de Inasistencias</h1>
        <p><i class="fas fa-graduation-cap"></i> Historial de inasistencias (completas, medias y cuartos)</p>
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
                    <label><i class="fas fa-chart-line"></i> Trimestre</label>
                    <select name="filtro_trimestre">
                        <option value="">Todos</option>
                        <?php foreach($trimestres as $trimestre): ?>
                            <option value="<?= $trimestre['id_trimestre'] ?>" <?= ($filtro_trimestre == $trimestre['id_trimestre']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($trimestre['trimestre']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filtro-group">
                    <label><i class="fas fa-check-circle"></i> Justificación</label>
                    <select name="filtro_justificada">
                        <option value="">Todos</option>
                        <option value="0" <?= ($filtro_justificada === '0') ? 'selected' : '' ?>>Injustificada</option>
                        <option value="1" <?= ($filtro_justificada === '1') ? 'selected' : '' ?>>Justificada</option>
                    </select>
                </div>
                <div class="filtro-group">
                    <label><i class="fas fa-tag"></i> Tipo de Falta</label>
                    <select name="filtro_tipo_falta">
                        <option value="">Todos</option>
                        <option value="completa" <?= ($filtro_tipo_falta == 'completa') ? 'selected' : '' ?>>Completa (1)</option>
                        <option value="media" <?= ($filtro_tipo_falta == 'media') ? 'selected' : '' ?>>Media (0.5)</option>
                        <option value="cuarto" <?= ($filtro_tipo_falta == 'cuarto') ? 'selected' : '' ?>>Cuarto (0.25)</option>
                    </select>
                </div>
                <div class="filtro-group">
                    <label><i class="fas fa-calendar-alt"></i> Fecha Desde</label>
                    <input type="date" name="fecha_desde" value="<?= htmlspecialchars($fecha_desde) ?>">
                </div>
                <div class="filtro-group">
                    <label><i class="fas fa-calendar-alt"></i> Fecha Hasta</label>
                    <input type="date" name="fecha_hasta" value="<?= htmlspecialchars($fecha_hasta) ?>">
                </div>
                <div class="filtro-group" style="justify-content: flex-end;">
                    <button type="submit" class="btn-primary"><i class="fas fa-search"></i> Filtrar</button>
                    <a href="listado_inasistencias.php" class="btn-secondary"><i class="fas fa-times"></i> Limpiar</a>
                </div>
            </div>
        </form>
    </div>

    <div class="card">
        <div class="stats-container">
            <div class="stats-card">
                <h3><i class="fas fa-chart-bar"></i> Total de Inasistencias</h3>
                <div class="numero"><?= $total_registros ?></div>
            </div>
            <div class="stats-card">
                <h3><i class="fas fa-circle" style="color: #dc3545;"></i> Completas (1)</h3>
                <div class="numero" style="color: #dc3545;"><?= $total_completas ?></div>
            </div>
            <div class="stats-card">
                <h3><i class="fas fa-half-alt" style="color: #ff9800;"></i> Medias (0.5)</h3>
                <div class="numero" style="color: #ff9800;"><?= $total_medias ?></div>
            </div>
            <div class="stats-card">
                <h3><i class="fas fa-chart-simple" style="color: #2196f3;"></i> Cuartos (0.25)</h3>
                <div class="numero" style="color: #2196f3;"><?= $total_cuartos ?></div>
            </div>
            <div class="stats-card">
                <h3><i class="fas fa-check-circle" style="color: #28a745;"></i> Justificadas</h3>
                <div class="numero" style="color: #28a745;"><?= $total_justificadas ?></div>
            </div>
            <div class="stats-card">
                <h3><i class="fas fa-times-circle" style="color: #dc3545;"></i> Injustificadas</h3>
                <div class="numero" style="color: #dc3545;"><?= $total_injustificadas ?></div>
            </div>
        </div>

        <div style="overflow-x: auto;">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Fecha</th>
                        <th>Alumno</th>
                        <th>Curso</th>
                        <th>Trimestre</th>
                        <th>Tipo de Falta</th>
                        <th>Justificación</th>
                        <th>Registrado por</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(mysqli_num_rows($res) > 0): 
                        while($row = mysqli_fetch_array($res)):
                            $turno_texto = ($row['turno'] == 'M') ? 'Mañana' : 'Tarde';
                            $curso_texto = $row['curso'] . '° "' . $row['division'] . '" - ' . $turno_texto;
                            
                            // Determinar badge de tipo de falta
                            switch($row['tipo_falta']){
                                case 'completa':
                                    $badge_tipo = '<span class="badge-completa"><i class="fas fa-circle"></i> Completa (1)</span>';
                                    break;
                                case 'media':
                                    $badge_tipo = '<span class="badge-media"><i class="fas fa-half-alt"></i> Media (0.5)</span>';
                                    break;
                                case 'cuarto':
                                    $badge_tipo = '<span class="badge-cuarto"><i class="fas fa-chart-simple"></i> Cuarto (0.25)</span>';
                                    break;
                                default:
                                    $badge_tipo = '<span class="badge-completa">Completa</span>';
                            }
                    ?>
                        <tr>
                            <td><?= $row['id_inasistencia'] ?></td>
                            <td><?= date('d/m/Y', strtotime($row['fecha'])) ?></div>
                            <td>
                                <strong><?= htmlspecialchars($row['Apellido']) ?></strong>, <?= htmlspecialchars($row['Nombre']) ?><br>
                                <small>DNI: <?= $row['DNI_U'] ?></small>
                             </div>
                            <td><span class="badge-curso"><?= htmlspecialchars($curso_texto) ?></span></div>
                            <td><span class="badge-trimestre"><?= $row['trimestre'] ?>° Trimestre</span></div>
                            <td><?= $badge_tipo ?></div>
                            <td>
                                <?php if($row['justificada'] == 1): ?>
                                    <span class="badge-justificada"><i class="fas fa-check"></i> Justificada</span>
                                <?php else: ?>
                                    <span class="badge-injustificada"><i class="fas fa-times"></i> Injustificada</span>
                                <?php endif; ?>
                            </div>
                            <td><?= htmlspecialchars($row['ape_preceptor'] . ', ' . $row['nom_preceptor']) ?></div>
                            <td>
                                <button class="btn-delete" onclick="eliminarInasistencia(<?= $row['id_inasistencia'] ?>)">
                                    <i class="fas fa-trash"></i> Eliminar
                                </button>
                            </div>
                        </tr>
                    <?php endwhile; else: ?>
                        <tr>
                            <td colspan="9" class="empty-state">
                                <i class="fas fa-inbox" style="font-size: 48px; margin-bottom: 15px; display: block;"></i>
                                No hay inasistencias registradas
                            </div>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <div class="btn-group">
            <a href="inasistencias.php" class="btn-primary"><i class="fas fa-plus"></i> Registrar Inasistencias</a>
            <a href="../../recursos/panel.php" class="btn-secondary"><i class="fas fa-arrow-left"></i> Volver al Panel</a>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
function eliminarInasistencia(id) {
    if(confirm('¿Está seguro de eliminar esta inasistencia? Esta acción no se puede deshacer.')) {
        $.ajax({
            url: 'ajax_inasistencias.php',
            type: 'POST',
            data: { action: 'eliminar_inasistencia', id_inasistencia: id },
            dataType: 'json',
            success: function(response) {
                if(response.success) {
                    alert(response.message);
                    location.reload();
                } else {
                    alert('Error: ' + response.message);
                }
            },
            error: function() {
                alert('Error al eliminar la inasistencia');
            }
        });
    }
}
</script>
</body>
</html>
<?php mysqli_close($con); ?>