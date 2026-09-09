<?php
session_start();

if(isset($_SESSION["dni"]) && (time() - $_SESSION["ultima_actividad"] > 1800)){
    echo '<script>alert("Se venció el tiempo de la sesion"); window.location="../../recursos/index.php";</script>';
    exit();
}

if(!isset($_SESSION["dni"])){
    echo '<script>alert("Se venció el tiempo de la sesion"); window.location="../../recursos/index.php";</script>';
    exit();
}

$rol = $_SESSION['rol'];
if($rol != 'Admin' && $rol != 'Secretario' && $rol != 'Preceptor'){
    echo '<script>alert("No tiene permisos para acceder a ABC Promedio"); window.location="../../recursos/panel.php";</script>';
    exit();
}

include '../../recursos/conexion.php';

function promedioDefinitivoEstudiante($id_alumno, $con){
    $sql = "SELECT DISTINCT id_materia FROM calificaciones WHERE id_alumno = $id_alumno";
    $res_mat = mysqli_query($con, $sql);
    if(!$res_mat || mysqli_num_rows($res_mat) == 0) return null;
    
    $suma_definitivos = 0;
    $cant_materias = 0;
    
    while($mat = mysqli_fetch_array($res_mat)){
        $id_materia = $mat['id_materia'];
        $sql_notas = "SELECT trimestre, nota FROM calificaciones WHERE id_alumno = $id_alumno AND id_materia = $id_materia";
        $res_notas = mysqli_query($con, $sql_notas);
        $notas_t1 = []; $notas_t2 = []; $notas_t3 = [];
        $nota_diciembre = null; $nota_febrero = null;
        while($n = mysqli_fetch_array($res_notas)){
            $trim = $n['trimestre'];
            $nota = $n['nota'];
            if($trim >= 1 && $trim <= 3){
                ${"notas_t$trim"}[] = $nota;
            } elseif($trim == 4){
                $nota_diciembre = ($nota == -1) ? 'Aus' : $nota;
            } elseif($trim == 5){
                $nota_febrero = ($nota == -1) ? 'Aus' : $nota;
            }
        }
        $promedio_t1 = (count($notas_t1) >= 3) ? array_sum($notas_t1)/count($notas_t1) : null;
        $promedio_t2 = (count($notas_t2) >= 3) ? array_sum($notas_t2)/count($notas_t2) : null;
        $promedio_t3 = (count($notas_t3) >= 3) ? array_sum($notas_t3)/count($notas_t3) : null;
        $promedio_final = null;
        if($promedio_t1 !== null && $promedio_t2 !== null && $promedio_t3 !== null){
            $promedio_final = ($promedio_t1 + $promedio_t2 + $promedio_t3) / 3;
        }
        $definitiva = null;
        if($promedio_final !== null && $promedio_final >= 6){
            $definitiva = $promedio_final;
        } elseif(is_numeric($nota_diciembre) && $nota_diciembre >= 6){
            $definitiva = $nota_diciembre;
        } elseif(is_numeric($nota_febrero) && $nota_febrero >= 6){
            $definitiva = $nota_febrero;
        } else {
            $definitiva = $promedio_final;
        }
        if($definitiva !== null){
            $suma_definitivos += $definitiva;
            $cant_materias++;
        }
    }
    if($cant_materias == 0) return null;
    return round($suma_definitivos / $cant_materias, 2);
}

$cursos_disponibles = [];
$curso_seleccionado = 0;

if($rol == 'Preceptor'){
    if(!isset($_SESSION['curso_activo_preceptor'])){
        echo '<script>alert("No hay curso activo."); window.location="../cursos/seleccionar_curso.php";</script>';
        exit();
    }
    $curso_seleccionado = $_SESSION['curso_activo_preceptor'];
    $dni_prec = $_SESSION['dni'];
    $q_val = "SELECT 1 FROM preceptorxcurso WHERE id_preceptor='$dni_prec' AND id_curso='$curso_seleccionado'";
    if(mysqli_num_rows(mysqli_query($con, $q_val)) == 0){
        unset($_SESSION['curso_activo_preceptor']);
        echo '<script>alert("Curso no autorizado."); window.location="../cursos/seleccionar_curso.php";</script>';
        exit();
    }
    $q_c = "SELECT ID_curso, curso, division, turno FROM curso WHERE ID_curso='$curso_seleccionado'";
    $r_c = mysqli_query($con, $q_c);
    while($f = mysqli_fetch_array($r_c)) $cursos_disponibles[] = $f;
} else {
    $q = "SELECT ID_curso, curso, division, turno FROM curso ORDER BY curso, division";
    $res_c = mysqli_query($con, $q);
    while($f = mysqli_fetch_array($res_c)) $cursos_disponibles[] = $f;
    $curso_seleccionado = isset($_GET['curso_id']) ? (int)$_GET['curso_id'] : 0;
}

$where_estudiante = "WHERE u.ID_rol = 3 AND u.ID_Estado = 1";
if($curso_seleccionado > 0){
    $where_estudiante .= " AND u.id_curso = $curso_seleccionado";
}
if($rol == 'Preceptor'){
    $where_estudiante .= " AND u.id_curso = $curso_seleccionado";
}

$estudiantes = [];
$q_est = "SELECT u.DNI_U, u.Nombre, u.Apellido, c.curso as curso_numero, c.division as curso_division, c.turno as curso_turno
          FROM usuario u
          LEFT JOIN curso c ON u.id_curso = c.ID_curso
          $where_estudiante
          ORDER BY u.Apellido, u.Nombre";
$res_est = mysqli_query($con, $q_est);
while($est = mysqli_fetch_array($res_est)){
    $estudiantes[] = $est;
}

$datos = [];
foreach($estudiantes as $e){
    $prom = promedioDefinitivoEstudiante($e['DNI_U'], $con);
    if($prom !== null){
        $datos[] = [
            'dni' => $e['DNI_U'],
            'nombre' => $e['Nombre'],
            'apellido' => $e['Apellido'],
            'curso' => $e['curso_numero'] . '° "' . $e['curso_division'] . '" - ' . ($e['curso_turno'] == 'M' ? 'Mañana' : 'Tarde'),
            'promedio' => $prom
        ];
    }
}
usort($datos, function($a, $b) { return $b['promedio'] <=> $a['promedio']; });
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ABC Promedio - Ranking de Estudiantes</title>
    <link rel="stylesheet" href="../../recursos/styles.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #0C1A2A; color: #E0F7FA; padding: 20px; }
        .container-abc { max-width: 1200px; margin: 0 auto; background: #1A3A5F; border-radius: 12px; padding: 20px; }
        h1 { color: #40E0D0; border-bottom: 2px solid #40E0D0; display: inline-block; }
        .filtros { background: #2D5A8C; padding: 15px; border-radius: 8px; margin: 20px 0; }
        .tabla { width: 100%; border-collapse: collapse; background: #2D5A8C; border-radius: 8px; overflow: hidden; }
        .tabla th, .tabla td { border: 1px solid #40E0D0; padding: 10px; text-align: left; color: #E0F7FA; }
        .tabla th { background: #1A3A5F; color: #40E0D0; }
        .promedio-alto { color: #4CAF50; font-weight: bold; }
        .promedio-bajo { color: #FF9800; font-weight: bold; }
        .promedio-malo { color: #F44336; font-weight: bold; }
        .ranking { font-weight: bold; text-align: center; }
        .btn-volver { background: #40E0D0; color: #1a2a3a; padding: 8px 16px; border-radius: 4px; text-decoration: none; display: inline-block; margin-top: 20px; }
    </style>
</head>
<body>
<div class="container-abc">
    <h1>📊 ABC de Promedios</h1>
    <p>Ranking de estudiantes según su promedio definitivo general (todas las materias).</p>
    
    <?php if($rol != 'Preceptor'): ?>
    <div class="filtros">
        <form method="GET" action="">
            <label for="curso_id"><strong>Filtrar por curso:</strong></label>
            <select name="curso_id" id="curso_id" onchange="this.form.submit()">
                <option value="0">-- Todos los cursos --</option>
                <?php foreach($cursos_disponibles as $c):
                    $turno = ($c['turno'] == 'M') ? 'Mañana' : 'Tarde';
                    $nombre_curso = $c['curso'] . '° "' . $c['division'] . '" - ' . $turno;
                    $selected = ($curso_seleccionado == $c['ID_curso']) ? 'selected' : '';
                ?>
                    <option value="<?= $c['ID_curso'] ?>" <?= $selected ?>><?= htmlspecialchars($nombre_curso) ?></option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>
    <?php else: ?>
        <div class="alert alert-info">
            Mostrando ranking para el curso activo:
            <strong><?php 
                $cur_act = $cursos_disponibles[0] ?? null;
                if($cur_act){
                    $turno = ($cur_act['turno']=='M')?'Mañana':'Tarde';
                    echo $cur_act['curso'].'° "'.$cur_act['division'].'" - '.$turno;
                } else {
                    echo "Sin curso";
                }
            ?></strong>
            <a href="../cursos/listado_curso.php" class="btn btn-sm btn-warning">Cambiar curso</a>
        </div>
    <?php endif; ?>
    
    <?php if(empty($datos)): ?>
        <div class="alert alert-info">No hay estudiantes con calificaciones cargadas para los filtros seleccionados.</div>
    <?php else: ?>
        <table class="tabla">
            <thead><tr><th>#</th><th>Apellido y Nombre</th><th>Curso</th><th>Promedio Definitivo General</th></tr></thead>
            <tbody>
                <?php $posicion=1; foreach($datos as $d): 
                    $prom = $d['promedio'];
                    $clase = ($prom>=7) ? 'promedio-alto' : (($prom>=6) ? 'promedio-bajo' : 'promedio-malo');
                ?>
                <tr>
                    <td class="ranking"><?= $posicion++ ?></td>
                    <td><?= htmlspecialchars($d['apellido'].', '.$d['nombre']) ?></td>
                    <td><?= htmlspecialchars($d['curso']) ?></td>
                    <td class="<?= $clase ?>"><?= number_format($prom,2) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
    <a href="../../recursos/panel.php" class="btn-volver">← Volver al Panel</a>
</div>
</body>
</html>
<?php mysqli_close($con); ?>