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
$es_profesor = ($_SESSION['rol'] == 'Profesor');

if(!$es_admin && !$es_profesor){
    echo '<script>alert("No tiene permisos para exportar planillas de talleres"); window.location="../../recursos/panel.php";</script>';
    exit();
}

include '../../recursos/conexion.php';

$anio_actual = date('Y');

// Obtener cursos con talleres
$query_cursos = "SELECT DISTINCT c.ID_curso, c.curso, c.division, c.turno
                 FROM curso c
                 INNER JOIN alumno_grupo_taller agt ON c.ID_curso = agt.ID_curso
                 WHERE agt.anio = '$anio_actual'
                 ORDER BY c.curso, c.division";
$res_cursos = mysqli_query($con, $query_cursos);
$cursos = [];
while($row = mysqli_fetch_array($res_cursos)){
    $cursos[] = $row;
}

// Generar token CSRF
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$generar_pdf = false;
$curso_seleccionado = null;
$datos_completos = [];

if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['exportar_pdf'])){
    if(!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']){
        $error = "Error de seguridad. Por favor, recargue la página.";
    } else {
        $curso_id = intval($_POST['curso_id']);
        
        // Obtener info del curso
        $query_curso = "SELECT c.ID_curso, c.curso, c.division, c.turno FROM curso c WHERE c.ID_curso = '$curso_id'";
        $res_curso = mysqli_query($con, $query_curso);
        $curso_seleccionado = mysqli_fetch_assoc($res_curso);
        
        if($curso_seleccionado){
            // ============================================
            // 1. Obtener los grupos del curso
            // ============================================
            $query_grupos = "SELECT DISTINCT numero_grupo
                            FROM alumno_grupo_taller
                            WHERE ID_curso = '$curso_id' AND anio = '$anio_actual'
                            ORDER BY numero_grupo";
            $res_grupos = mysqli_query($con, $query_grupos);
            $grupos = [];
            while($row = mysqli_fetch_array($res_grupos)){
                $grupos[] = $row['numero_grupo'];
            }
            
            // ============================================
            // 2. Obtener el orden de talleres para cada grupo (de planilla_rotaciones)
            // ============================================
            $orden_talleres_por_grupo = [];
            $query_orden = "SELECT pr.numero_grupo, r.numero_rotacion, r.nombre as rotacion_nombre, 
                                   t.ID_taller, t.nombre as taller_nombre, t.anio_taller
                            FROM planilla_rotaciones pr
                            INNER JOIN rotaciones r ON pr.ID_rotacion = r.ID_rotacion
                            INNER JOIN talleres t ON pr.ID_taller = t.ID_taller
                            WHERE pr.ID_curso = '$curso_id' AND r.anio = '$anio_actual'
                            ORDER BY pr.numero_grupo, r.numero_rotacion";
            $res_orden = mysqli_query($con, $query_orden);
            while($row = mysqli_fetch_array($res_orden)){
                $grupo = $row['numero_grupo'];
                if(!isset($orden_talleres_por_grupo[$grupo])){
                    $orden_talleres_por_grupo[$grupo] = [];
                }
                $orden_talleres_por_grupo[$grupo][] = [
                    'numero_rotacion' => $row['numero_rotacion'],
                    'rotacion_nombre' => $row['rotacion_nombre'],
                    'ID_taller' => $row['ID_taller'],
                    'taller_nombre' => $row['taller_nombre'],
                    'anio_taller' => $row['anio_taller']
                ];
            }
            
            // ============================================
            // 3. Obtener alumnos de cada grupo (con DISTINCT)
            // ============================================
            $alumnos_por_grupo = [];
            $todos_los_dnis = [];
            
            foreach($grupos as $grupo){
                $query_alumnos = "SELECT DISTINCT u.DNI_U, u.Nombre, u.Apellido
                                 FROM usuario u
                                 INNER JOIN alumno_grupo_taller agt ON u.DNI_U = agt.ID_alumno
                                 WHERE agt.ID_curso = '$curso_id'
                                 AND agt.numero_grupo = '$grupo'
                                 AND agt.anio = '$anio_actual'
                                 AND u.ID_rol = 3
                                 AND u.ID_Estado = 1
                                 ORDER BY u.Apellido, u.Nombre";
                $res_alumnos = mysqli_query($con, $query_alumnos);
                $alumnos_grupo = [];
                while($row = mysqli_fetch_array($res_alumnos)){
                    $alumnos_grupo[] = $row;
                    $todos_los_dnis[$row['DNI_U']] = $row;
                }
                $alumnos_por_grupo[$grupo] = $alumnos_grupo;
            }
            
            // ============================================
            // 4. Obtener todas las calificaciones
            // ============================================
            $calificaciones = [];
            $query_calif = "SELECT ID_alumno, ID_taller, ID_rotacion,
                                   nota1, nota2, nota3, nota4, nota5,
                                   recuperatorio, promedio, promedio_final, calificacion_definitiva
                            FROM calificaciones_taller
                            WHERE ID_rotacion IN (SELECT ID_rotacion FROM rotaciones WHERE anio = '$anio_actual')";
            $res_calif = mysqli_query($con, $query_calif);
            while($row = mysqli_fetch_array($res_calif)){
                $calificaciones[$row['ID_alumno']][$row['ID_rotacion']][$row['ID_taller']] = $row;
            }
            
            // ============================================
            // 5. Armar datos para el PDF
            // ============================================
            $datos_completos = [
                'curso' => $curso_seleccionado,
                'grupos' => $grupos,
                'orden_talleres_por_grupo' => $orden_talleres_por_grupo,
                'alumnos_por_grupo' => $alumnos_por_grupo,
                'calificaciones' => $calificaciones
            ];
            
            $generar_pdf = true;
        }
    }
}

if($generar_pdf && $curso_seleccionado){
    $_SESSION['pdf_taller_curso_data'] = $datos_completos;
    header('Location: generar_pdf_taller_curso.php');
    exit();
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Exportar Planilla de Talleres por Curso</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }
        .container {
            max-width: 500px;
            width: 100%;
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
        }
        .header {
            background: #7a0000;
            color: white;
            padding: 25px;
            text-align: center;
        }
        .header h1 { font-size: 22px; margin-bottom: 5px; }
        .header p { font-size: 13px; opacity: 0.9; }
        .form-container { padding: 30px; }
        .form-group { margin-bottom: 25px; }
        .form-group label { display: block; font-weight: 600; margin-bottom: 8px; color: #333; font-size: 14px; }
        .form-group select {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 10px;
            font-size: 14px;
            background: #f9f9f9;
            transition: all 0.3s;
        }
        .form-group select:focus {
            outline: none;
            border-color: #7a0000;
            background: white;
        }
        .btn-exportar {
            width: 100%;
            background: #7a0000;
            color: white;
            padding: 14px;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }
        .btn-exportar:hover {
            background: #8b0000;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(122,0,0,0.3);
        }
        .btn-volver {
            display: inline-block;
            text-align: center;
            width: 100%;
            margin-top: 15px;
            padding: 12px;
            background: #6c757d;
            color: white;
            text-decoration: none;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.3s;
        }
        .btn-volver:hover { background: #5a6268; }
        .info {
            background: #e2f3ff;
            padding: 12px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-size: 12px;
            color: #0056b3;
            text-align: center;
        }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1>📄 Exportar Planilla de Talleres</h1>
        <p>Genera una planilla completa por curso</p>
    </div>
    <div class="form-container">
        <div class="info">
            ℹ️ La planilla incluirá TODOS los grupos del curso seleccionado<br>
            Cada grupo muestra sus talleres en el orden de rotación correcto
        </div>
        
        <form method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            
            <div class="form-group">
                <label>📚 Seleccionar Curso</label>
                <select name="curso_id" required>
                    <option value="">-- Seleccione un curso --</option>
                    <?php foreach($cursos as $curso): ?>
                        <option value="<?= $curso['ID_curso'] ?>">
                            <?= $curso['curso'] ?>° "<?= $curso['division'] ?>" - Turno: <?= $curso['turno'] == 'T' ? 'Tarde' : 'Mañana' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <button type="submit" name="exportar_pdf" class="btn-exportar">
                📥 Generar y Descargar PDF
            </button>
        </form>
        
        <a href="../../recursos/panel.php" class="btn-volver">← Volver al Panel</a>
    </div>
</div>
</body>
</html>
<?php mysqli_close($con); ?>