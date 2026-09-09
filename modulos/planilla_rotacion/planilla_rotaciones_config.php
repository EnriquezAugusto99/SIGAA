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

// Solo Admin y Preceptor pueden configurar
if($_SESSION['rol'] != 'Admin' && $_SESSION['rol'] != 'Preceptor'){
    echo '<script>alert("No tiene permisos para configurar rotaciones"); window.location="../../recursos/panel.php";</script>';
    exit();
}

include '../../recursos/conexion.php';

$anio_actual = date('Y');
$error = '';
$success = '';

// Obtener rotaciones disponibles
$query_rotaciones = "SELECT ID_rotacion, numero_rotacion, nombre, fecha_inicio, fecha_fin 
                     FROM rotaciones WHERE anio = '$anio_actual' AND activo = 1 
                     ORDER BY numero_rotacion";
$res_rotaciones = mysqli_query($con, $query_rotaciones);

// Obtener cursos de 1° y 2° año
$query_cursos = "SELECT ID_curso, curso, division, turno 
                 FROM curso 
                 WHERE curso IN (1, 2) 
                 ORDER BY curso, division";
$res_cursos = mysqli_query($con, $query_cursos);

// Obtener talleres
$query_talleres = "SELECT ID_taller, nombre, anio_taller FROM talleres WHERE activo = 1 ORDER BY anio_taller, nombre";
$res_talleres = mysqli_query($con, $query_talleres);

// Variables de filtro
$rotacion_id = $_GET['rotacion_id'] ?? '';
$curso_id = $_GET['curso_id'] ?? '';
$grupo_seleccionado = $_GET['grupo'] ?? '';

// Obtener información del curso seleccionado y sus grupos
$grupos_disponibles = [];
$turno_curso = '';

if($curso_id){
    $q_curso = "SELECT turno, curso, division FROM curso WHERE ID_curso = '$curso_id'";
    $r_curso = mysqli_query($con, $q_curso);
    if($r_curso && mysqli_num_rows($r_curso) > 0){
        $curso_data = mysqli_fetch_array($r_curso);
        $turno_curso = $curso_data['turno'];
        
        // DEFINIR GRUPOS POR DEFECTO SEGÚN EL CURSO (SIEMPRE DISPONIBLES)
        switch($curso_id){
            // 1° año
            case 1: $grupos_disponibles = [1, 2]; break;
            case 2: $grupos_disponibles = [3, 4]; break;
            case 3: $grupos_disponibles = [5, 6]; break;
            case 4: $grupos_disponibles = [7, 8]; break;
            case 5: $grupos_disponibles = [9, 10]; break;
            case 6: $grupos_disponibles = [11, 12]; break;
            // 2° año
            case 7: $grupos_disponibles = [1, 2]; break;
            case 8: $grupos_disponibles = [3, 4]; break;
            case 9: $grupos_disponibles = [5, 6]; break;
            case 20: $grupos_disponibles = [7, 8, 9]; break;
            case 19: $grupos_disponibles = [10, 11, 12]; break;
            // Fallback genérico
            default:
                if($turno_curso == 'T') $grupos_disponibles = range(1, 6);
                else $grupos_disponibles = range(7, 12);
        }
    }
}

// Obtener taller actual si existe configuración
$taller_actual = '';
if($rotacion_id && $curso_id && $grupo_seleccionado){
    $query_config = "SELECT ID_taller FROM planilla_rotaciones 
                     WHERE ID_rotacion = '$rotacion_id' 
                     AND ID_curso = '$curso_id' 
                     AND numero_grupo = '$grupo_seleccionado'";
    $res_config = mysqli_query($con, $query_config);
    if(mysqli_num_rows($res_config) > 0){
        $row = mysqli_fetch_array($res_config);
        $taller_actual = $row['ID_taller'];
    }
}

// Procesar guardado
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['guardar_config'])){
    $ID_rotacion = $_POST['ID_rotacion'];
    $ID_curso = $_POST['ID_curso'];
    $numero_grupo = $_POST['numero_grupo'];
    $ID_taller = $_POST['ID_taller'];
    
    if(empty($ID_taller)){
        $error = "Debe seleccionar un taller";
    } else {
        $query_check = "SELECT ID_planilla FROM planilla_rotaciones 
                        WHERE ID_rotacion = '$ID_rotacion' 
                        AND ID_curso = '$ID_curso' 
                        AND numero_grupo = '$numero_grupo'";
        $res_check = mysqli_query($con, $query_check);
        
        if(mysqli_num_rows($res_check) > 0){
            $query = "UPDATE planilla_rotaciones 
                      SET ID_taller = '$ID_taller' 
                      WHERE ID_rotacion = '$ID_rotacion' 
                      AND ID_curso = '$ID_curso' 
                      AND numero_grupo = '$numero_grupo'";
        } else {
            $query = "INSERT INTO planilla_rotaciones (ID_rotacion, ID_curso, numero_grupo, ID_taller) 
                      VALUES ('$ID_rotacion', '$ID_curso', '$numero_grupo', '$ID_taller')";
        }
        
        if(mysqli_query($con, $query)){
            $success = "Configuración guardada correctamente";
            $taller_actual = $ID_taller;
        } else {
            $error = "Error al guardar: " . mysqli_error($con);
        }
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
    <title>Configurar Planilla de Rotaciones</title>
    <style>
        .container {
            max-width: 900px;
            margin: 20px auto;
            padding: 25px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
        }
        h1 {
            color: #7a0000;
            margin-bottom: 25px;
        }
        .filtros-config {
            background: #f5f5f5;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 25px;
        }
        .filtros-grid {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            align-items: flex-end;
        }
        .filtro-group {
            flex: 1;
            min-width: 180px;
        }
        .filtro-group label {
            display: block;
            font-weight: 600;
            margin-bottom: 5px;
            font-size: 13px;
            color: #555;
        }
        .filtro-group select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
        }
        .filtro-group select:disabled {
            background: #e9ecef;
            cursor: not-allowed;
        }
        .btn-filtrar {
            background: #7a0000;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
        }
        .btn-filtrar:hover {
            background: #8b0000;
        }
        .form-config {
            background: #f5f5f5;
            padding: 20px;
            border-radius: 10px;
            margin-top: 20px;
        }
        .info-curso {
            background: #e2f3ff;
            padding: 12px 15px;
            border-radius: 6px;
            margin-bottom: 20px;
            border-left: 4px solid #2196F3;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            font-weight: 600;
            margin-bottom: 8px;
            color: #333;
        }
        .form-group select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
        }
        .btn-guardar {
            background: #4CAF50;
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            width: 100%;
            font-size: 16px;
        }
        .btn-guardar:hover {
            background: #45a049;
        }
        .mensaje-error {
            background: #f8d7da;
            color: #721c24;
            padding: 12px;
            border-radius: 6px;
            margin: 15px 0;
        }
        .mensaje-success {
            background: #d4edda;
            color: #155724;
            padding: 12px;
            border-radius: 6px;
            margin: 15px 0;
        }
        .volver-btn {
            display: inline-block;
            margin-top: 20px;
            padding: 10px 20px;
            background: #6c757d;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            text-align: center;
        }
        .volver-btn:hover {
            background: #5a6268;
        }
        .grupo-info {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
        }
    </style>
</head>
<body>
<div class="container">
    <h1>Configurar Planilla de Rotaciones</h1>
    
    <?php if($error): ?>
        <div class="mensaje-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php if($success): ?>
        <div class="mensaje-success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>
    
    <!-- Filtros para seleccionar qué configurar -->
    <div class="filtros-config">
        <form method="GET" action="" id="filtroForm">
            <div class="filtros-grid">
                <div class="filtro-group">
                    <label>Rotación</label>
                    <select name="rotacion_id" required>
                        <option value="">-- Seleccione una rotación --</option>
                        <?php 
                        mysqli_data_seek($res_rotaciones, 0);
                        while($rot = mysqli_fetch_array($res_rotaciones)): ?>
                            <option value="<?= $rot['ID_rotacion'] ?>" <?= $rotacion_id == $rot['ID_rotacion'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($rot['nombre']) ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="filtro-group">
                    <label>Curso</label>
                    <select name="curso_id" id="curso_select" required>
                        <option value="">-- Seleccione un curso --</option>
                        <?php 
                        mysqli_data_seek($res_cursos, 0);
                        while($curso = mysqli_fetch_array($res_cursos)):
                            $turno_texto = $curso['turno'] == 'M' ? 'Mañana' : 'Tarde';
                            $curso_nombre = $curso['curso'] . '° "' . $curso['division'] . '" - ' . $turno_texto;
                        ?>
                            <option value="<?= $curso['ID_curso'] ?>" <?= $curso_id == $curso['ID_curso'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($curso_nombre) ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="filtro-group">
                    <label>Grupo</label>
                    <select name="grupo" id="grupo_select" required <?= empty($grupos_disponibles) ? 'disabled' : '' ?>>
                        <option value="">-- Seleccione un grupo --</option>
                        <?php foreach($grupos_disponibles as $g): ?>
                            <option value="<?= $g ?>" <?= $grupo_seleccionado == $g ? 'selected' : '' ?>>
                                Grupo <?= $g ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if(empty($grupos_disponibles) && $curso_id): ?>
                        <div class="grupo-info">⚠️ No hay grupos asignados. Primero asigne alumnos a grupos en "Asignar Grupos".</div>
                    <?php endif; ?>
                </div>
                <div class="filtro-group">
                    <button type="submit" class="btn-filtrar">Seleccionar</button>
                </div>
            </div>
        </form>
    </div>
    
    <!-- Formulario de configuración (se muestra solo cuando hay selección completa) -->
    <?php if($rotacion_id && $curso_id && $grupo_seleccionado): ?>
        <div class="form-config">
            <div class="info-curso">
                <strong>Configurando:</strong> 
                <?php 
                $q_rot_nombre = "SELECT nombre FROM rotaciones WHERE ID_rotacion = '$rotacion_id'";
                $r_rot_nombre = mysqli_query($con, $q_rot_nombre);
                $rot_nombre = mysqli_fetch_array($r_rot_nombre)['nombre'];
                
                $q_curso_nombre = "SELECT CONCAT(curso, '° \"', division, '\" - ', IF(turno='M','Mañana','Tarde')) as nombre 
                                  FROM curso WHERE ID_curso = '$curso_id'";
                $r_curso_nombre = mysqli_query($con, $q_curso_nombre);
                $curso_nombre = mysqli_fetch_array($r_curso_nombre)['nombre'];
                ?>
                Rotación: <strong><?= htmlspecialchars($rot_nombre) ?></strong> | 
                Curso: <strong><?= htmlspecialchars($curso_nombre) ?></strong> | 
                Grupo: <strong><?= $grupo_seleccionado ?></strong>
            </div>
            
            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <input type="hidden" name="guardar_config" value="1">
                <input type="hidden" name="ID_rotacion" value="<?= $rotacion_id ?>">
                <input type="hidden" name="ID_curso" value="<?= $curso_id ?>">
                <input type="hidden" name="numero_grupo" value="<?= $grupo_seleccionado ?>">
                
                <div class="form-group">
                    <label>Asignar Taller para este Grupo en esta Rotación</label>
                    <select name="ID_taller" required>
                        <option value="">-- Seleccione un taller --</option>
                        <?php 
                        mysqli_data_seek($res_talleres, 0);
                        while($taller = mysqli_fetch_array($res_talleres)): 
                            $selected = ($taller_actual == $taller['ID_taller']) ? 'selected' : '';
                        ?>
                            <option value="<?= $taller['ID_taller'] ?>" <?= $selected ?>>
                                <?= htmlspecialchars($taller['nombre'] . ' (' . $taller['anio_taller'] . ')') ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                
                <button type="submit" class="btn-guardar">💾 Guardar Configuración</button>
            </form>
        </div>
    <?php elseif($rotacion_id && $curso_id && empty($grupos_disponibles)): ?>
        <div class="mensaje-error" style="margin-top: 15px;">
            ⚠️ No hay grupos disponibles para este curso. 
            <a href="grupos_taller.php?curso_id=<?= $curso_id ?>">Asignar grupos primero</a>
        </div>
    <?php elseif($rotacion_id || $curso_id): ?>
        <div class="mensaje-info" style="background:#e2f3ff; padding:12px; border-radius:6px; margin-top:15px;">
            Complete todos los campos (Rotación, Curso y Grupo) para configurar.
        </div>
    <?php endif; ?>
    
    <div style="margin-top: 25px; text-align: center;">
        <a href="planilla_rotaciones_listado.php" class="volver-btn">Ver Listado de Configuraciones</a>
        <a href="../../recursos/panel.php" class="volver-btn" style="background: #7a0000;">Volver al Panel</a>
    </div>
</div>

<script>
// Hacer que el selector de grupo se actualice automáticamente cuando cambia el curso
document.getElementById('curso_select')?.addEventListener('change', function() {
    document.getElementById('filtroForm').submit();
});
</script>
</body>
</html>
<?php mysqli_close($con);