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

if($_SESSION['rol'] != 'Admin' && $_SESSION['rol'] != 'Preceptor'){
    echo '<script>alert("No tiene permisos para gestionar grupos"); window.location="../../recursos/panel.php";</script>';
    exit();
}

include '../../recursos/conexion.php';

$anio_actual = date('Y');
$curso_seleccionado = $_GET['curso_id'] ?? '';
$mensaje = '';

// Obtener cursos disponibles (SOLO 1° y 2° año)
$es_preceptor = ($_SESSION['rol'] == 'Preceptor');
$cursos_disponibles = [];

if($es_preceptor){
    $preceptor_dni = $_SESSION["dni"];
    $query_cursos = "SELECT c.ID_curso, c.curso, c.division, c.turno
                     FROM curso c
                     INNER JOIN preceptorxcurso pxc ON c.ID_curso = pxc.id_curso
                     WHERE pxc.id_preceptor = '$preceptor_dni'
                     AND c.curso IN (1, 2)
                     ORDER BY c.curso, c.division";
} else {
    $query_cursos = "SELECT ID_curso, curso, division, turno 
                     FROM curso 
                     WHERE curso IN (1, 2)
                     ORDER BY curso, division";
}
$res_cursos = mysqli_query($con, $query_cursos);

// Obtener alumnos del curso seleccionado (solo 1° y 2° año)
$alumnos_curso = [];
$grupos_asignados = [];

if(!empty($curso_seleccionado)){
    // Verificar que el curso sea de 1° o 2° año
    $check_curso = "SELECT curso FROM curso WHERE ID_curso = '$curso_seleccionado'";
    $res_check = mysqli_query($con, $check_curso);
    $curso_data = mysqli_fetch_array($res_check);
    
    if($curso_data && ($curso_data['curso'] == 1 || $curso_data['curso'] == 2)){
        // Obtener alumnos del curso
        $query_alumnos = "SELECT DNI_U, Nombre, Apellido 
                          FROM usuario 
                          WHERE id_curso = '$curso_seleccionado' 
                          AND ID_rol = 3 
                          AND ID_Estado = 1
                          ORDER BY Apellido, Nombre";
        $res_alumnos = mysqli_query($con, $query_alumnos);
        while($alumno = mysqli_fetch_array($res_alumnos)){
            $alumnos_curso[] = $alumno;
        }
        
        // Obtener grupos ya asignados para este curso y año
        $query_grupos = "SELECT ID_alumno, numero_grupo 
                         FROM alumno_grupo_taller 
                         WHERE ID_curso = '$curso_seleccionado' AND anio = '$anio_actual'";
        $res_grupos = mysqli_query($con, $query_grupos);
        while($grupo = mysqli_fetch_array($res_grupos)){
            $grupos_asignados[$grupo['ID_alumno']] = $grupo['numero_grupo'];
        }
    } else {
        $curso_seleccionado = '';
        $mensaje = '<div class="mensaje-error" style="background:#f8d7da; color:#721c24; padding:12px; border-radius:8px; margin-bottom:15px;">⚠️ Los talleres solo están disponibles para 1° y 2° año. Seleccione un curso válido.</div>';
    }
}

// Generar token CSRF
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
    <title>Asignar Grupos - Talleres</title>
    <style>
        .selector-curso {
            background: #f5f5f5;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 25px;
        }
        .curso-grid {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-top: 15px;
        }
        .curso-card {
            background: white;
            border: 2px solid #ddd;
            border-radius: 10px;
            padding: 12px 20px;
            text-decoration: none;
            color: #333;
            transition: all 0.3s;
            font-weight: 600;
        }
        .curso-card:hover, .curso-card.active {
            background: var(--deep-crimson);
            border-color: var(--deep-crimson);
            color: white;
        }
        .tabla-grupos {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        .tabla-grupos th, .tabla-grupos td {
            border: 1px solid #ddd;
            padding: 10px;
            text-align: left;
        }
        .tabla-grupos th {
            background: var(--dark-red);
            color: white;
        }
        .select-grupo {
            padding: 6px 10px;
            border-radius: 6px;
            border: 1px solid #ddd;
            min-width: 80px;
        }
        .btn-guardar {
            background: var(--deep-crimson);
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            margin-top: 20px;
        }
        .btn-guardar:hover {
            background: var(--dark-red);
        }
        .info-talleres {
            background: #e2f3ff;
            color: #004085;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #2196F3;
        }
    </style>
</head>
<body>
    <div class="caja" style="max-width: 900px;">
        <h1>Asignar Grupos para Talleres</h1>
        
        <?php echo $mensaje; ?>
        
        <!-- Selector de curso -->
        <div class="selector-curso">
            <h3>📚 Seleccionar Curso (1° o 2° año)</h3>
            <div class="curso-grid">
                <?php if(mysqli_num_rows($res_cursos) > 0):
                    while($curso = mysqli_fetch_array($res_cursos)):
                        $turno_texto = $curso['turno'] == 'M' ? 'Mañana' : 'Tarde';
                        $curso_nombre = $curso['curso'] . '° "' . $curso['division'] . '" - ' . $turno_texto;
                        $active = ($curso_seleccionado == $curso['ID_curso']) ? 'active' : '';
                ?>
                    <a href="grupos_taller.php?curso_id=<?php echo $curso['ID_curso']; ?>" class="curso-card <?php echo $active; ?>">
                        <?php echo htmlspecialchars($curso_nombre); ?>
                    </a>
                <?php 
                    endwhile;
                else: ?>
                    <div style="color: #999;">No hay cursos de 1° o 2° año disponibles.</div>
                <?php endif; ?>
            </div>
        </div>
        
        <?php if(!empty($curso_seleccionado) && !empty($alumnos_curso)): 
            $curso_info = null;
            $q_curso = "SELECT curso, division, turno FROM curso WHERE ID_curso = '$curso_seleccionado'";
            $r_curso = mysqli_query($con, $q_curso);
            $info = mysqli_fetch_array($r_curso);
            $turno_texto = $info['turno'] == 'M' ? 'Mañana' : 'Tarde';
        ?>
            <h2>Curso: <?php echo $info['curso']; ?>° "<?php echo $info['division']; ?>" - <?php echo $turno_texto; ?></h2>
            <p><strong>Año lectivo:</strong> <?php echo $anio_actual; ?></p>
            
            <form method="POST" action="guardar_grupo_taller.php">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <input type="hidden" name="ID_curso" value="<?php echo $curso_seleccionado; ?>">
                <input type="hidden" name="anio" value="<?php echo $anio_actual; ?>">
                
                <table class="tabla-grupos">
                    <thead>
                        <tr>
                            <th>DNI</th>
                            <th>Apellido y Nombre</th>
                            <th>Grupo (1-12)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($alumnos_curso as $alumno): 
                            $grupo_actual = $grupos_asignados[$alumno['DNI_U']] ?? '';
                        ?>
                            <tr>
                                <td><?php echo $alumno['DNI_U']; ?></td>
                                <td><?php echo htmlspecialchars($alumno['Apellido'] . ', ' . $alumno['Nombre']); ?></td>
                                <td>
                                    <select name="grupo[<?php echo $alumno['DNI_U']; ?>]" class="select-grupo">
                                        <option value="">-- Sin grupo --</option>
                                        <?php for($g = 1; $g <= 12; $g++): ?>
                                            <option value="<?php echo $g; ?>" <?php echo $grupo_actual == $g ? 'selected' : ''; ?>>
                                                Grupo <?php echo $g; ?>
                                            </option>
                                        <?php endfor; ?>
                                    </select>
                                 </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <button type="submit" class="btn-guardar">💾 Guardar Grupos</button>
            </form>
            
        <?php elseif(!empty($curso_seleccionado) && empty($alumnos_curso)): ?>
            <div class="mensaje-error" style="background:#f8d7da; color:#721c24; padding:15px; border-radius:8px;">
                No hay alumnos activos en este curso.
            </div>
        <?php elseif(empty($curso_seleccionado) && mysqli_num_rows($res_cursos) > 0): ?>
        <?php endif; ?>
        
        <p style="margin-top: 20px;">
            <a href="../../recursos/panel.php"><button>Volver al Panel</button></a>
        </p>
    </div>
</body>
</html>
<?php mysqli_close($con);