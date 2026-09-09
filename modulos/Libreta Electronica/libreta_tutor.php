<?php
session_start();

// Verificar sesión
if (!isset($_SESSION['dni'])) {
    header("Location: ../../index.php");
    exit();
}

// Verificar que sea un tutor (rol 8)
if ($_SESSION['rol'] != 'Tutor') {
    echo '<script>alert("No tiene permisos para acceder a esta sección"); window.location="../../recursos/panel.php";</script>';
    exit();
}

include '../../recursos/conexion.php';

$tutor_id = $_SESSION['dni'];
$alumno_seleccionado = isset($_GET['alumno_id']) ? (int)$_GET['alumno_id'] : 0;
$anio_actual = date('Y');

// Función para truncar (no redondear)
function truncarNota($nota, $decimales = 2) {
    if (!is_numeric($nota)) return '-';
    $factor = pow(10, $decimales);
    return floor($nota * $factor) / $factor;
}

// Función para obtener valor de falta
function getValorFalta($tipo_falta) {
    switch($tipo_falta){
        case 'completa': return 1;
        case 'media': return 0.5;
        case 'cuarto': return 0.25;
        default: return 1;
    }
}

// ============================================
// FUNCIÓN: Verificar si un trimestre está habilitado
// ============================================
function trimestreHabilitado($trimestre) {
    global $con;
    $query = "SELECT fecha_apertura, activo 
              FROM configuracion_libreta 
              WHERE trimestre = $trimestre AND activo = 1";
    $res = mysqli_query($con, $query);
    
    if(mysqli_num_rows($res) == 0) return false;
    
    $config = mysqli_fetch_assoc($res);
    $fecha_actual = date('Y-m-d');
    
    return $fecha_actual >= $config['fecha_apertura'];
}

// ============================================
// VERIFICAR SI ES CICLO BÁSICO
// ============================================
function esCicloBasico($curso, $division) {
    $cursos_basicos = [1, 2];
    $divisiones_basico = ['A', 'B', 'C', 'D', 'E', 'F'];
    
    if (in_array($curso, $cursos_basicos) && in_array($division, $divisiones_basico)) {
        return false;
    }
    return false;
}

// 1. Obtener la lista de alumnos vinculados a este tutor
$alumnos_tutor = [];
$query_vinculo = "SELECT u.DNI_U, u.Nombre, u.Apellido 
                  FROM alumnoxtutor at
                  INNER JOIN usuario u ON at.id_alumno = u.DNI_U
                  WHERE at.id_tutor = '$tutor_id' AND u.ID_Estado = 1
                  ORDER BY u.Apellido, u.Nombre";
$res_vinculo = mysqli_query($con, $query_vinculo);
while ($row = mysqli_fetch_assoc($res_vinculo)) {
    $alumnos_tutor[] = $row;
}

// Si no se seleccionó un alumno y hay disponibles, tomamos el primero
if ($alumno_seleccionado == 0 && count($alumnos_tutor) > 0) {
    $alumno_seleccionado = $alumnos_tutor[0]['DNI_U'];
}

// Variables del alumno seleccionado
$id_curso = 0;
$nombre_curso = '';
$division_curso = '';
$es_basico = false;
$materias = [];
$notas_trimestre = [1 => [], 2 => [], 3 => []];
$inasistencias = [1 => ['justificadas' => 0, 'injustificadas' => 0], 
                  2 => ['justificadas' => 0, 'injustificadas' => 0], 
                  3 => ['justificadas' => 0, 'injustificadas' => 0]];
$previas_pendientes = [];
$equivalencias_pendientes = [];
$talleres_alumno = [];
$notas_taller_trimestre = [1 => [], 2 => [], 3 => []];
$alumno_info = null;

if ($alumno_seleccionado > 0) {
    // Verificar que el alumno realmente corresponda al tutor
    $verificar = false;
    foreach ($alumnos_tutor as $al) {
        if ($al['DNI_U'] == $alumno_seleccionado) {
            $verificar = true;
            $alumno_info = $al;
            break;
        }
    }
    
    if ($verificar) {
        // Obtenemos el curso del alumno
        $query_curso = "SELECT u.id_curso, c.curso, c.division 
                        FROM usuario u
                        INNER JOIN curso c ON u.id_curso = c.ID_curso
                        WHERE u.DNI_U = '$alumno_seleccionado'";
        $res_curso = mysqli_query($con, $query_curso);
        
        if ($res_curso && mysqli_num_rows($res_curso) > 0) {
            $datos_curso = mysqli_fetch_assoc($res_curso);
            $id_curso = $datos_curso['id_curso'] ?? 0;
            $nombre_curso = $datos_curso['curso'] ?? '';
            $division_curso = $datos_curso['division'] ?? '';
            
            $es_basico = esCicloBasico($nombre_curso, $division_curso);
            
            if (!$es_basico && $id_curso > 0) {
                // Materias comunes
                $query_materias = "SELECT ID_materia, Nom_materia 
                                   FROM materia 
                                   WHERE id_curso = '$id_curso'
                                   AND Nom_materia NOT LIKE '%Taller%'
                                   AND Nom_materia NOT LIKE '%Tutor%'
                                   AND Nom_materia NOT LIKE '%Tutorías%'
                                   ORDER BY Nom_materia";
                $res_materias = mysqli_query($con, $query_materias);
                while ($row = mysqli_fetch_assoc($res_materias)) {
                    $materias[$row['ID_materia']] = $row['Nom_materia'];
                }
                
                // Notas materias comunes
                $notas_acumuladas = [];
                $query_notas = "SELECT id_materia, trimestre, nota 
                                FROM calificaciones 
                                WHERE id_alumno = '$alumno_seleccionado' 
                                AND YEAR(fecha) = '$anio_actual'";
                $res_notas = mysqli_query($con, $query_notas);
                while ($row = mysqli_fetch_assoc($res_notas)) {
                    $trim = (int)$row['trimestre'];
                    $id_materia = (int)$row['id_materia'];
                    $nota = $row['nota'];
                    
                    if (!isset($notas_acumuladas[$trim][$id_materia])) {
                        $notas_acumuladas[$trim][$id_materia] = [];
                    }
                    $notas_acumuladas[$trim][$id_materia][] = $nota;
                }
                
                $notas_trimestre = [1 => [], 2 => [], 3 => []];
                foreach ($notas_acumuladas as $trim => $materias_temp) {
                    foreach ($materias_temp as $id_materia => $notas_array) {
                        if (count($notas_array) > 0) {
                            $promedio = array_sum($notas_array) / count($notas_array);
                            $notas_trimestre[$trim][$id_materia] = truncarNota($promedio, 2);
                        }
                    }
                }
                
                // Inasistencias
                $query_asistencia = "SELECT trimestre, justificada, tipo_falta 
                                     FROM inasistencias 
                                     WHERE id_alumno = '$alumno_seleccionado' 
                                     AND YEAR(fecha) = '$anio_actual'";
                $res_asistencia = mysqli_query($con, $query_asistencia);
                while ($row = mysqli_fetch_assoc($res_asistencia)) {
                    $trim = (int)$row['trimestre'];
                    $valor_falta = getValorFalta($row['tipo_falta']);
                    
                    if ($row['justificada'] == 1) {
                        $inasistencias[$trim]['justificadas'] += $valor_falta;
                    } else {
                        $inasistencias[$trim]['injustificadas'] += $valor_falta;
                    }
                }
                
                // Previas y Equivalencias
                $query_previas = "SELECT p.ID_previas,
                                         CASE 
                                             WHEN p.ID_materia IS NOT NULL THEN m.Nom_materia
                                             WHEN p.ID_taller IS NOT NULL THEN CONCAT('Taller: ', t.nombre)
                                         END as nombre_item,
                                         tp.nom_tp as tipo_previa
                                  FROM previas p
                                  INNER JOIN tipo_previa tp ON p.ID_tp = tp.ID_tp
                                  LEFT JOIN materia m ON p.ID_materia = m.ID_materia
                                  LEFT JOIN talleres t ON p.ID_taller = t.ID_taller
                                  WHERE p.DNI_U = '$alumno_seleccionado'
                                  AND p.Aprobado = 0";
                $res_previas = mysqli_query($con, $query_previas);
                while ($row = mysqli_fetch_assoc($res_previas)) {
                    if ($row['tipo_previa'] == 'Previa') {
                        $previas_pendientes[] = $row['nombre_item'];
                    } else {
                        $equivalencias_pendientes[] = $row['nombre_item'];
                    }
                }
                
                // Talleres
                $rotacion_a_trimestre = [1 => 1, 2 => 1, 3 => 2, 4 => 2, 5 => 3, 6 => 3];
                
                $query_grupo = "SELECT numero_grupo, ID_curso as curso_taller 
                                FROM alumno_grupo_taller 
                                WHERE ID_alumno = '$alumno_seleccionado' AND anio = '$anio_actual' LIMIT 1";
                $res_grupo = mysqli_query($con, $query_grupo);
                
                if (mysqli_num_rows($res_grupo) > 0) {
                    $grupo_info = mysqli_fetch_assoc($res_grupo);
                    $numero_grupo = $grupo_info['numero_grupo'];
                    $curso_taller = $grupo_info['curso_taller'];
                    
                    $query_talleres = "SELECT t.ID_taller, t.nombre, t.anio_taller
                                       FROM planilla_rotaciones pr
                                       INNER JOIN talleres t ON pr.ID_taller = t.ID_taller
                                       WHERE pr.ID_curso = '$curso_taller' AND pr.numero_grupo = '$numero_grupo'
                                       GROUP BY t.ID_taller
                                       ORDER BY t.nombre";
                    $res_talleres = mysqli_query($con, $query_talleres);
                    while ($row = mysqli_fetch_assoc($res_talleres)) {
                        $talleres_alumno[$row['ID_taller']] = $row['nombre'] . ' ' . $row['anio_taller'];
                    }
                    
                    if (!empty($talleres_alumno)) {
                        $ids_talleres = implode(',', array_keys($talleres_alumno));
                        $query_notas_taller = "SELECT ID_taller, ID_rotacion, calificacion_definitiva as nota
                                               FROM calificaciones_taller
                                               WHERE ID_alumno = '$alumno_seleccionado'
                                               AND ID_taller IN ($ids_talleres)";
                        $res_notas_taller = mysqli_query($con, $query_notas_taller);
                        
                        $notas_taller_acumuladas = [];
                        while ($row = mysqli_fetch_assoc($res_notas_taller)) {
                            $id_taller = (int)$row['ID_taller'];
                            $nota = $row['nota'];
                            
                            if (!isset($notas_taller_acumuladas[$id_taller])) {
                                $notas_taller_acumuladas[$id_taller] = [];
                            }
                            $notas_taller_acumuladas[$id_taller][] = $nota;
                        }
                        
                        foreach ($notas_taller_acumuladas as $id_taller => $notas_array) {
                            if (count($notas_array) > 0) {
                                $promedio = array_sum($notas_array) / count($notas_array);
                                $notas_taller_trimestre[1][$id_taller] = truncarNota($promedio, 2);
                            }
                        }
                    }
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>Libreta de Calificaciones - Tutor</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Montserrat', sans-serif; background: #f5f5f5; padding: 20px; }
        .libreta-container { max-width: 1400px; margin: 0 auto; background: white; border-radius: 16px; padding: 25px; box-shadow: 0 4px 20px rgba(0,0,0,0.1); }
        .encabezado-libreta { text-align: center; border-bottom: 2px solid #710A14; padding-bottom: 15px; margin-bottom: 25px; }
        .encabezado-libreta h1 { color: #710A14; font-size: 28px; }
        .selector-alumno { background: #f8f9fa; padding: 15px; border-radius: 12px; margin-bottom: 20px; display: flex; align-items: center; gap: 15px; flex-wrap: wrap; }
        .selector-alumno label { font-weight: 600; color: #710A14; }
        .selector-alumno select { padding: 10px; border-radius: 8px; border: 1px solid #ddd; font-family: 'Montserrat', sans-serif; min-width: 250px; }
        .info-alumno { background: #f5f5f5; padding: 15px; border-radius: 12px; margin-bottom: 25px; border-left: 4px solid #710A14; }
        .tabla-scroll { width: 100%; overflow-x: auto; margin-bottom: 30px; -webkit-overflow-scrolling: touch; }
        .tabla-libreta { width: 100%; min-width: 800px; border-collapse: collapse; font-size: 12px; }
        .tabla-libreta th, .tabla-libreta td { border: 1px solid #ddd; padding: 10px 8px; text-align: center; vertical-align: middle; }
        .tabla-libreta th { background: #3F070B; color: white; font-weight: 600; }
        .tabla-libreta th.vertical-text { writing-mode: vertical-lr; transform: rotate(180deg); width: 50px; padding: 8px 4px; }
        .aprobado { color: #2e7d32; font-weight: bold; }
        .desaprobado { color: #d32f2f; font-weight: bold; }
        .promedio { font-weight: bold; background: #f8f9fa; }
        .mensaje-alerta { background: #fff3e0; border-left: 4px solid #ff9800; padding: 20px; text-align: center; border-radius: 8px; margin: 20px 0; }
        .volver-btn { display: inline-block; margin-top: 20px; padding: 10px 24px; background: #710A14; color: white; text-decoration: none; border-radius: 8px; font-weight: 600; }
        .volver-btn:hover { background: #3F070B; }
        .seccion-previas-container { display: flex; flex-wrap: wrap; gap: 25px; margin-top: 30px; }
        .columna-previas { flex: 1; min-width: 280px; background: #f8f9fa; border-radius: 12px; padding: 20px; }
        .columna-previas h3 { color: #710A14; margin-bottom: 15px; border-bottom: 2px solid #ddd; padding-bottom: 8px; }
        .lista-previas { list-style: none; padding: 0; }
        .lista-previas li { padding: 8px 12px; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; }
        .badge-pendiente { background: #ff9800; color: white; padding: 2px 8px; border-radius: 12px; font-size: 11px; }
        .badge-justificadas { color: #2e7d32; font-weight: bold; }
        .badge-injustificadas { color: #d32f2f; font-weight: bold; }
        .celda-no-habilitada { color: #ccc; font-style: italic; font-size: 11px; }
        .trimestre-no-habilitado { background: #fafafa !important; }
    </style>
</head>
<body>

<div class="libreta-container">
    <div class="encabezado-libreta">
        <h1>Libreta de Calificaciones</h1>
    </div>

    <?php if (count($alumnos_tutor) > 1): ?>
        <div class="selector-alumno">
            <label>Seleccionar Alumno:</label>
            <select name="alumno_id" id="alumno_id" onchange="location = '?alumno_id=' + this.value;">
                <?php foreach ($alumnos_tutor as $al): ?>
                    <option value="<?php echo $al['DNI_U']; ?>" <?php echo ($alumno_seleccionado == $al['DNI_U']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($al['Apellido'] . ', ' . $al['Nombre']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
    <?php endif; ?>

    <?php if ($alumno_seleccionado > 0 && $alumno_info): ?>
        <div class="info-alumno">
            <strong>Alumno:</strong> <?php echo htmlspecialchars($alumno_info['Apellido'] . ', ' . $alumno_info['Nombre']); ?> | 
            <strong>DNI:</strong> <?php echo htmlspecialchars($alumno_seleccionado); ?> | 
            <strong>Curso:</strong> <?php echo htmlspecialchars($nombre_curso . '° "' . $division_curso . '"'); ?>
        </div>

        <?php if ($es_basico): ?>
            <div class="mensaje-alerta">
                <i class="fas fa-clock" style="font-size: 32px; margin-bottom: 10px; display: block;"></i>
                <strong>Aviso importante</strong>
                <p>Las libretas para ciclo básico estarán disponibles en unos días.</p>
            </div>
        <?php else: ?>

            <?php if (!empty($materias)): ?>
            <div class="tabla-scroll">
                <table class="tabla-libreta">
                    <thead>
                        <tr>
                            <th rowspan="2">Trimestres</th>
                            <?php foreach($materias as $id_mat => $nom_mat): ?>
                                <th class="vertical-text" rowspan="2"><?php echo htmlspecialchars($nom_mat); ?></th>
                            <?php endforeach; ?>
                            <th rowspan="2">Promedio General</th>
                            <th colspan="2">Inasistencias</th>
                        </tr>
                        <tr>
                            <th>Justif.</th>
                            <th>Injustif.</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $promedios_materias_acum = [];
                        $trimestres_con_notas = [];
                        
                        for ($trim = 1; $trim <= 3; $trim++):
                            $trim_habilitado = trimestreHabilitado($trim);
                            $suma_notas = 0;
                            $cont_materias = 0;
                            $tiene_notas = false;
                            $just_trim = $inasistencias[$trim]['justificadas'];
                            $injust_trim = $inasistencias[$trim]['injustificadas'];
                        ?>
                            <tr class="<?= $trim_habilitado ? '' : 'trimestre-no-habilitado' ?>">
                                <td><strong><?php echo $trim; ?>er Trimestre</strong></td>
                                <?php foreach($materias as $id_mat => $nom_mat): 
                                    if($trim_habilitado){
                                        $nota = $notas_trimestre[$trim][$id_mat] ?? '-';
                                        
                                        if (is_numeric($nota)) {
                                            $suma_notas += $nota;
                                            $cont_materias++;
                                            $promedios_materias_acum[$id_mat]['suma'] = ($promedios_materias_acum[$id_mat]['suma'] ?? 0) + $nota;
                                            $promedios_materias_acum[$id_mat]['count'] = ($promedios_materias_acum[$id_mat]['count'] ?? 0) + 1;
                                            $tiene_notas = true;
                                            $clase_color = $nota >= 6 ? 'aprobado' : 'desaprobado';
                                            echo '<td class="' . $clase_color . '">' . number_format($nota, 2, '.', '') . '</td>';
                                        } else {
                                            echo '<td>-</td>';
                                        }
                                    } else {
                                        echo '<td class="celda-no-habilitada">-</td>';
                                    }
                                endforeach; ?>
                                
                                <?php 
                                if ($trim_habilitado && $tiene_notas) {
                                    $trimestres_con_notas[$trim] = true;
                                }
                                
                                if ($trim_habilitado) {
                                    $todas_tienen = true;
                                    foreach($materias as $id_mat => $nom_mat) {
                                        $nota = $notas_trimestre[$trim][$id_mat] ?? '-';
                                        if (!is_numeric($nota)) {
                                            $todas_tienen = false;
                                            break;
                                        }
                                    }
                                    
                                    if ($todas_tienen && $cont_materias == count($materias)) {
                                        $promedio_trim = truncarNota($suma_notas / $cont_materias, 2);
                                        $clase_prom = $promedio_trim >= 6 ? 'aprobado' : 'desaprobado';
                                    } else {
                                        $promedio_trim = '-';
                                        $clase_prom = '';
                                    }
                                } else {
                                    $promedio_trim = '-';
                                    $clase_prom = '';
                                }
                                ?>
                                <td class="promedio <?php echo $clase_prom; ?>"><strong><?php echo $promedio_trim; ?></strong></td>
                                <td><?php echo $just_trim; ?></td>
                                <td><?php echo $injust_trim; ?></td>
                            </tr>
                        <?php endfor; ?>
                        
                        <!-- Promedio Anual - Solo si los 3 trimestres están habilitados -->
                        <tr style="background: #f0f0f0;">
                            <td><strong>Promedio Anual</strong></td>
                            <?php 
                            $todos_habilitados = trimestreHabilitado(1) && trimestreHabilitado(2) && trimestreHabilitado(3);
                            $suma_general = 0;
                            $cont_general = 0;
                            $tiene_tres = isset($trimestres_con_notas[1]) && isset($trimestres_con_notas[2]) && isset($trimestres_con_notas[3]);
                            
                            foreach($materias as $id_mat => $nom_mat):
                                $prom_mat = '-';
                                if ($todos_habilitados && isset($promedios_materias_acum[$id_mat]) && $promedios_materias_acum[$id_mat]['count'] > 0 && $tiene_tres) {
                                    $prom_mat = truncarNota($promedios_materias_acum[$id_mat]['suma'] / $promedios_materias_acum[$id_mat]['count'], 2);
                                    $suma_general += $prom_mat;
                                    $cont_general++;
                                }
                                $clase_mat = is_numeric($prom_mat) ? ($prom_mat >= 6 ? 'aprobado' : 'desaprobado') : '';
                            ?>
                                <td class="promedio <?php echo $clase_mat; ?>"><strong><?php echo $prom_mat; ?></strong></td>
                            <?php endforeach; ?>
                            
                            <?php 
                            $prom_general = ($cont_general > 0 && $todos_habilitados && $tiene_tres) ? truncarNota($suma_general / $cont_general, 2) : '-';
                            $clase_gral = is_numeric($prom_general) ? ($prom_general >= 6 ? 'aprobado' : 'desaprobado') : '';
                            
                            $total_just_anual = $inasistencias[1]['justificadas'] + $inasistencias[2]['justificadas'] + $inasistencias[3]['justificadas'];
                            $total_injust_anual = $inasistencias[1]['injustificadas'] + $inasistencias[2]['injustificadas'] + $inasistencias[3]['injustificadas'];
                            ?>
                            <td class="promedio <?php echo $clase_gral; ?>"><strong><?php echo $prom_general; ?></strong></td>
                            <td class="badge-justificadas"><strong><?php echo $total_just_anual; ?></strong></td>
                            <td class="badge-injustificadas"><strong><?php echo $total_injust_anual; ?></strong></td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
                <div class="mensaje-alerta">No hay materias cargadas para este curso.</div>
            <?php endif; ?>

            <!-- Talleres - Solo si al menos el trimestre 1 está habilitado -->
            <?php if (!empty($talleres_alumno) && trimestreHabilitado(1)): ?>
                <h2 style="color: #710A14; margin: 30px 0 15px 0;">Talleres</h2>
                <div class="tabla-scroll">
                    <table class="tabla-libreta">
                        <thead>
                            <tr>
                                <th>Trimestres</th>
                                <?php foreach ($talleres_alumno as $id_t => $nom_t): ?>
                                    <th><?php echo htmlspecialchars($nom_t); ?></th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php for ($trim = 1; $trim <= 3; $trim++): 
                                $trim_habilitado = trimestreHabilitado($trim);
                            ?>
                                <tr class="<?= $trim_habilitado ? '' : 'trimestre-no-habilitado' ?>">
                                    <td><strong><?php echo $trim; ?>er Trimestre</strong></td>
                                    <?php foreach ($talleres_alumno as $id_t => $nom_t): 
                                        if($trim_habilitado){
                                            $nota_t = $notas_taller_trimestre[$trim][$id_t] ?? '-';
                                            $clase_t = is_numeric($nota_t) ? ($nota_t >= 6 ? 'aprobado' : 'desaprobado') : '';
                                        } else {
                                            $nota_t = '-';
                                            $clase_t = '';
                                        }
                                    ?>
                                        <td class="<?php echo $clase_t; ?>"><?php echo is_numeric($nota_t) ? number_format($nota_t, 2) : '-'; ?></td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endfor; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

            <!-- Previas y Equivalencias (siempre visibles) -->
            <center><h2 style="color: #710A14; margin: 30px 0 15px 0;">Previas y Equivalencias</h2></center>
            <div class="seccion-previas-container">
                <div class="columna-previas">
                    <h3>Previas</h3>
                    <?php if (empty($previas_pendientes)): ?>
                        <p>No adeuda previas</p>
                    <?php else: ?>
                        <ul class="lista-previas">
                            <?php foreach($previas_pendientes as $previa): ?>
                                <li>
                                    <span><?php echo htmlspecialchars($previa); ?></span>
                                    <span class="badge-pendiente">Pendiente</span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>

                <div class="columna-previas">
                    <h3>Equivalencias</h3>
                    <?php if (empty($equivalencias_pendientes)): ?>
                        <p>No adeuda equivalencias</p>
                    <?php else: ?>
                        <ul class="lista-previas">
                            <?php foreach($equivalencias_pendientes as $equiv): ?>
                                <li>
                                    <span><?php echo htmlspecialchars($equiv); ?></span>
                                    <span class="badge-pendiente">Pendiente</span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>

        <?php endif; ?>
    <?php else: ?>
        <div class="mensaje-alerta">No hay ningún alumno asignado o seleccionado.</div>
    <?php endif; ?>
    
    <div style="text-align: center; margin-top: 35px;">
        <a href="../../recursos/panel.php" class="volver-btn">← Volver al Panel</a>
    </div>
</div>

</body>
</html>
<?php mysqli_close($con); ?>