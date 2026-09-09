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
        body { 
            background: #f5f5f5;
            color: #2c2c2c; 
            padding: 20px; 
            font-family: 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            margin: 0;
            min-height: 100vh;
        }
        .container-abc { 
            max-width: 1200px; 
            margin: 0 auto; 
            background: #ffffff;
            border-radius: 12px; 
            padding: 30px; 
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            border: 1px solid #e0e0e0;
        }
        h1 { 
            color: #7a0000; 
            border-bottom: 2px solid #e0e0e0; 
            display: inline-block; 
            font-weight: 600;
            padding-bottom: 10px;
            letter-spacing: 0.5px;
            margin-top: 0;
        }
        .filtros { 
            background: #ffffff;
            padding: 20px; 
            border-radius: 10px; 
            margin: 25px 0; 
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            border-left: 5px solid #7a0000;
            border-top: 1px solid #e0e0e0;
            border-right: 1px solid #e0e0e0;
            border-bottom: 1px solid #e0e0e0;
        }
        .filtros select {
            background: #ffffff;
            border: 1px solid #cccccc;
            border-radius: 6px;
            color: #2c2c2c;
            padding: 8px 12px;
            margin: 4px 8px 4px 0;
            transition: all 0.2s ease;
            box-shadow: inset 0 1px 3px rgba(0,0,0,0.05);
        }
        .filtros select:focus {
            outline: none;
            border-color: #b71c1c;
            box-shadow: 0 0 0 3px rgba(183, 28, 28, 0.15), inset 0 1px 3px rgba(0,0,0,0.05);
            background: #ffffff;
        }
        .tabla { 
            width: 100%; 
            border-collapse: separate;
            border-spacing: 0;
            background: #ffffff; 
            border-radius: 10px; 
            overflow: hidden; 
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            border: 1px solid #e0e0e0;
            font-size: 0.9em;
        }
        .tabla th, .tabla td { 
            border-bottom: 1px solid #e0e0e0; 
            border-right: 1px solid #e0e0e0;
            padding: 14px 10px; 
            text-align: left; 
            color: #2c2c2c; 
        }
        .tabla th:last-child, .tabla td:last-child {
            border-right: none;
        }
        .tabla tr:last-child td {
            border-bottom: none;
        }
        .tabla th { 
            background: #7a0000;
            color: white; 
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 2px solid #8b0000;
        }
        .tabla tr:hover td {
            background: #fdf5f5;
        }
        .promedio-alto { color: #2e7d32; font-weight: bold; }
        .promedio-bajo { color: #f57c00; font-weight: bold; }
        .promedio-malo { color: #d32f2f; font-weight: bold; }
        .ranking { font-weight: bold; text-align: center; color: #7a0000; }
        .btn-volver { 
            background: #7a0000;
            color: white; 
            padding: 12px 24px; 
            border-radius: 8px; 
            text-decoration: none; 
            display: inline-flex;
            align-items: center;
            margin-top: 25px; 
            font-weight: 600;
            box-shadow: 0 4px 10px rgba(122, 0, 0, 0.2);
            transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
            letter-spacing: 0.5px;
        }
        .btn-volver:hover {
            background: #8b0000;
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(139, 0, 0, 0.3);
            color: white;
        }
        .btn-volver:active {
            transform: translateY(1px);
            box-shadow: 0 2px 5px rgba(122, 0, 0, 0.2);
        }
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