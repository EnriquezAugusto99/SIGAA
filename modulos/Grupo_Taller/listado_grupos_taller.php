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

if($_SESSION['rol'] != 'Admin' && $_SESSION['rol'] != 'Preceptor' && $_SESSION['rol'] != 'Profesor' && $_SESSION['rol'] != 'Equipo de Orientacion'){
    echo '<script>alert("No tiene permisos para ver grupos"); window.location="../../recursos/panel.php";</script>';
    exit();
}

include '../../recursos/conexion.php';

$curso_id = $_GET['curso_id'] ?? '';
$anio = $_GET['anio'] ?? date('Y');
$grupo_filtro = $_GET['grupo'] ?? '';

$where = "WHERE agt.anio = '$anio'";
if(!empty($curso_id)){
    $where .= " AND agt.ID_curso = '$curso_id'";
}
if(!empty($grupo_filtro)){
    $where .= " AND agt.numero_grupo = '$grupo_filtro'";
}

$query = "SELECT
            DISTINCT agt.numero_grupo,
            u.DNI_U,
            u.Nombre,
            u.Apellido,
            c.curso,
            c.division,
            c.turno
          FROM alumno_grupo_taller agt
          INNER JOIN usuario u ON agt.ID_alumno = u.DNI_U
          INNER JOIN curso c ON agt.ID_curso = c.ID_curso
          $where
          ORDER BY c.curso, c.division, agt.numero_grupo, u.Apellido";

$res = mysqli_query($con, $query);

// Obtener cursos para el filtro
$query_cursos = "SELECT ID_curso, curso, division, turno FROM curso ORDER BY curso, division";
$res_cursos = mysqli_query($con, $query_cursos);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../../recursos/styles.css">
    <title>Listado de Grupos</title>
    <style>
        .btn-grupo { display: inline-block; margin: 2px; }
        .grupo-badge { 
            display: inline-block; 
            background: var(--deep-crimson); 
            color: white; 
            padding: 2px 8px; 
            border-radius: 12px; 
            font-size: 11px; 
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="caja" style="max-width: 1000px;">
        <h1>Listado de Grupos por Curso</h1>
        
        <div class="filtros">
            <form method="GET" action="" style="display: flex; flex-wrap: wrap; gap: 15px; align-items: flex-end;">
                <div>
                    <label><strong>Curso:</strong></label><br>
                    <select name="curso_id">
                        <option value="">Todos los cursos</option>
                        <?php while($curso = mysqli_fetch_array($res_cursos)):
                            $turno_texto = $curso['turno'] == 'M' ? 'Mañana' : 'Tarde';
                            $curso_nombre = $curso['curso'] . '° "' . $curso['division'] . '" - ' . $turno_texto;
                            $selected = ($curso_id == $curso['ID_curso']) ? 'selected' : '';
                        ?>
                            <option value="<?php echo $curso['ID_curso']; ?>" <?php echo $selected; ?>>
                                <?php echo htmlspecialchars($curso_nombre); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div>
                    <label><strong>Año:</strong></label><br>
                    <select name="anio">
                        <option value="2025" <?php echo $anio == '2025' ? 'selected' : ''; ?>>2025</option>
                        <option value="2026" <?php echo $anio == '2026' ? 'selected' : ''; ?>>2026</option>
                        <option value="2027" <?php echo $anio == '2027' ? 'selected' : ''; ?>>2027</option>
                    </select>
                </div>
                <div>
                    <label><strong>Grupo:</strong></label><br>
                    <select name="grupo">
                        <option value="">Todos</option>
                        <?php for($g = 1; $g <= 12; $g++): ?>
                            <option value="<?php echo $g; ?>" <?php echo $grupo_filtro == $g ? 'selected' : ''; ?>>Grupo <?php echo $g; ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div>
                    <button type="submit">Filtrar</button>
                    <a href="listado_grupos_taller.php"><button type="button">Limpiar</button></a>
                </div>
            </form>
        </div>
        
        <table class="tabla">
            <thead>
                <tr>
                    <th>DNI</th>
                    <th>Alumno</th>
                    <th>Curso</th>
                    <th>Grupo</th>
                </tr>
            </thead>
            <tbody>
                <?php
                if(mysqli_num_rows($res) > 0){
                    while($row = mysqli_fetch_array($res)){
                        $turno_texto = $row['turno'] == 'M' ? 'Mañana' : 'Tarde';
                        $curso_texto = $row['curso'] . '° "' . $row['division'] . '" - ' . $turno_texto;
                        echo '<tr>';
                        echo '<td>' . $row['DNI_U'] . '</td>';
                        echo '<td>' . htmlspecialchars($row['Apellido'] . ', ' . $row['Nombre']) . '</td>';
                        echo '<td>' . $curso_texto . '</td>';
                        echo '<td><span class="grupo-badge">Grupo ' . $row['numero_grupo'] . '</span></td>';
                        echo '</tr>';
                    }
                } else {
                    echo '<tr><td colspan="4">No hay alumnos asignados a grupos.</td></tr>';
                }
                ?>
            </tbody>
        </table>
        
        <p>
            <?php if($_SESSION['rol'] != 'Equipo de Orientacion'): ?>
                <a href="grupos_taller.php"><button>Asignar Grupos</button></a>
            <?php endif; ?>
            <a href="../../recursos/panel.php"><button>Volver al Panel</button></a>
        </p>
    </div>
</body>
</html>
<?php mysqli_close($con); ?>