<?php
session_start();
header('Content-Type: text/html; charset=UTF-8');

// Verificar permisos
$es_admin = ($_SESSION['rol'] == 'Admin');
$es_preceptor = ($_SESSION['rol'] == 'Preceptor');

if(!isset($_SESSION['dni']) || (!$es_admin && !$es_preceptor)){
    echo "No autorizado";
    exit();
}

include '../../recursos/conexion.php';

$alumno_id = isset($_POST['alumno_id']) ? $_POST['alumno_id'] : 0;
$anio_actual = isset($_POST['anio']) ? $_POST['anio'] : date('Y');
$tipo_envio = isset($_POST['tipo_envio']) ? $_POST['tipo_envio'] : 'final';

if(!$alumno_id) {
    echo "No se especificó alumno";
    exit();
}

// Función para truncar (no redondear)
function truncarNota($nota, $decimales = 2) {
    if (!is_numeric($nota)) return '-';
    $factor = pow(10, $decimales);
    return floor($nota * $factor) / $factor;
}

function getValorFalta($tipo_falta) {
    switch($tipo_falta){
        case 'completa': return 1;
        case 'media': return 0.5;
        case 'cuarto': return 0.25;
        default: return 1;
    }
}

$titulo_adicional = '';
if($tipo_envio == 'trimestre1') $titulo_adicional = ' - 1er Trimestre';
elseif($tipo_envio == 'trimestre2') $titulo_adicional = ' - 2do Trimestre';
elseif($tipo_envio == 'trimestre3') $titulo_adicional = ' - 3er Trimestre';

// Obtener datos del alumno
$query_alumno = "SELECT Nombre, Apellido, id_curso FROM usuario WHERE DNI_U = '$alumno_id'";
$res_alumno = mysqli_query($con, $query_alumno);
$alumno = mysqli_fetch_assoc($res_alumno);

// Obtener curso
$query_curso = "SELECT curso, division, turno FROM curso WHERE ID_curso = '{$alumno['id_curso']}'";
$res_curso = mysqli_query($con, $query_curso);
$curso = mysqli_fetch_assoc($res_curso);
$turno_texto = $curso['turno'] == 'M' ? 'Mañana' : 'Tarde';
$curso_nombre = $curso['curso'] . '° "' . $curso['division'] . '" - ' . $turno_texto;

// Obtener materias
$query_materias = "SELECT DISTINCT m.ID_materia, m.Nom_materia 
                   FROM materia m 
                   WHERE m.id_curso = '{$alumno['id_curso']}' 
                   AND m.Nom_materia NOT LIKE '%Taller%'
                   AND m.Nom_materia NOT LIKE '%Tutor%'
                   AND m.Nom_materia NOT LIKE '%Tutorías%'
                   ORDER BY m.Nom_materia";
$res_materias = mysqli_query($con, $query_materias);
$materias = mysqli_fetch_all($res_materias, MYSQLI_ASSOC);

$nombres_materias = [];
foreach ($materias as $m) {
    $nombres_materias[] = $m['Nom_materia'];
}

// Obtener calificaciones
$query_notas = "SELECT c.id_materia, c.trimestre, c.nota, m.Nom_materia
                FROM calificaciones c
                INNER JOIN materia m ON c.id_materia = m.ID_materia
                WHERE c.id_alumno = '$alumno_id'
                ORDER BY c.trimestre, m.Nom_materia";
$res_notas = mysqli_query($con, $query_notas);

$notas_acumuladas = [];
while ($row = mysqli_fetch_assoc($res_notas)) {
    $trim = $row['trimestre'];
    $materia = $row['Nom_materia'];
    $nota = $row['nota'];
    if(!isset($notas_acumuladas[$trim][$materia])) $notas_acumuladas[$trim][$materia] = [];
    $notas_acumuladas[$trim][$materia][] = $nota;
}

$notas_organizadas = [];
foreach($notas_acumuladas as $trim => $materias_temp){
    foreach($materias_temp as $materia => $notas_array){
        if(count($notas_array) > 0){
            $promedio = array_sum($notas_array) / count($notas_array);
            $notas_organizadas[$trim][$materia] = truncarNota($promedio, 2);
        }
    }
}

// Obtener Inasistencias
$inasistencias_por_trimestre = [
    1 => ['justificadas' => 0, 'injustificadas' => 0],
    2 => ['justificadas' => 0, 'injustificadas' => 0],
    3 => ['justificadas' => 0, 'injustificadas' => 0]
];
$query_inasistencias = "SELECT i.justificada, i.trimestre, i.tipo_falta
                        FROM inasistencias i
                        WHERE i.id_alumno = '$alumno_id' 
                        AND i.id_curso = '{$alumno['id_curso']}'
                        AND YEAR(i.fecha) = '$anio_actual'";
$res_inasistencias = mysqli_query($con, $query_inasistencias);
if($res_inasistencias){
    while($row_inas = mysqli_fetch_assoc($res_inasistencias)){
        $trim = $row_inas['trimestre'];
        $valor_falta = getValorFalta($row_inas['tipo_falta']);
        if(isset($inasistencias_por_trimestre[$trim])){
            if($row_inas['justificada'] == 1) $inasistencias_por_trimestre[$trim]['justificadas'] += $valor_falta;
            else $inasistencias_por_trimestre[$trim]['injustificadas'] += $valor_falta;
        }
    }
}

// Obtener Talleres
$rotacion_a_trimestre = [1 => 1, 2 => 1, 3 => 2, 4 => 2, 5 => 3, 6 => 3];
$query_grupo_taller = "SELECT ID_curso, numero_grupo FROM alumno_grupo_taller 
                       WHERE ID_alumno = '$alumno_id' AND anio = '$anio_actual' LIMIT 1";
$res_grupo_taller = mysqli_query($con, $query_grupo_taller);
$tiene_talleres = false;
$talleres_alumno = [];
$notas_taller_trimestre = [];

if (mysqli_num_rows($res_grupo_taller) > 0) {
    $grupo_taller = mysqli_fetch_assoc($res_grupo_taller);
    $tiene_talleres = true;
    $numero_grupo = $grupo_taller['numero_grupo'];
    $curso_taller = $grupo_taller['ID_curso'];

    $query_talleres = "SELECT pr.ID_rotacion, pr.ID_taller, t.nombre, t.anio_taller, r.numero_rotacion
        FROM planilla_rotaciones pr
        INNER JOIN talleres t ON pr.ID_taller = t.ID_taller
        INNER JOIN rotaciones r ON pr.ID_rotacion = r.ID_rotacion
        WHERE pr.ID_curso = '$curso_taller' AND pr.numero_grupo = '$numero_grupo' AND r.anio = '$anio_actual'
        ORDER BY r.numero_rotacion";
    $res_talleres = mysqli_query($con, $query_talleres);

    $rotaciones = [];
    while ($row = mysqli_fetch_assoc($res_talleres)) {
        $talleres_alumno[$row['ID_taller']] = $row['nombre'] . ' ' . $row['anio_taller'];
        $rotaciones[$row['ID_rotacion']] = ['id_taller' => $row['ID_taller'], 'trimestre' => $rotacion_a_trimestre[$row['numero_rotacion']]];
    }

    if (!empty($talleres_alumno)) {
        $ids_talleres = implode(',', array_keys($talleres_alumno));
        $ids_rotaciones = implode(',', array_keys($rotaciones));
        $query_notas_taller = "SELECT ID_taller, ID_rotacion, promedio as nota
                               FROM calificaciones_taller
                               WHERE ID_alumno = '$alumno_id' AND ID_taller IN ($ids_talleres) AND ID_rotacion IN ($ids_rotaciones)";
        $res_notas_taller = mysqli_query($con, $query_notas_taller);
        while ($row = mysqli_fetch_assoc($res_notas_taller)) {
            $trimestre = $rotaciones[$row['ID_rotacion']]['trimestre'];
            $notas_taller_trimestre[$trimestre][$row['ID_taller']] = truncarNota($row['nota'], 2);
        }
    }
}

// Obtener Previas y Equivalencias
$previas_pendientes = [];
$equivalencias_pendientes = [];
$query_previas = "SELECT p.ID_previas, p.Aprobado, p.ID_tp,
                         CASE WHEN p.ID_materia IS NOT NULL THEN m.Nom_materia WHEN p.ID_taller IS NOT NULL THEN CONCAT('Taller: ', t.nombre) END as nombre_item,
                         CASE WHEN p.ID_materia IS NOT NULL THEN (SELECT curso FROM curso WHERE ID_curso = (SELECT id_curso FROM materia WHERE ID_materia = p.ID_materia)) WHEN p.ID_taller IS NOT NULL THEN CASE WHEN t.anio_taller = 'I' THEN 1 ELSE 2 END END as anio_item,
                         tp.nom_tp as tipo_previa
                  FROM previas p
                  INNER JOIN tipo_previa tp ON p.ID_tp = tp.ID_tp
                  LEFT JOIN materia m ON p.ID_materia = m.ID_materia
                  LEFT JOIN talleres t ON p.ID_taller = t.ID_taller
                  WHERE p.DNI_U = '$alumno_id' AND p.Aprobado = 0
                  ORDER BY tp.ID_tp, anio_item, nombre_item";
$res_previas = mysqli_query($con, $query_previas);
while($row = mysqli_fetch_assoc($res_previas)){
    $item_texto = $row['nombre_item'] . ' (' . $row['anio_item'] . '° Año)';
    if($row['tipo_previa'] == 'Previa') $previas_pendientes[] = $item_texto;
    else $equivalencias_pendientes[] = $item_texto;
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { font-family: Arial, sans-serif; background: #ffffff; padding: 20px; margin: 0; }
        .libreta-container { width: 100%; max-width: 1400px; margin: 0 auto; background: #ffffff; padding: 20px; box-sizing: border-box; }
        .info-alumno { background: #f5f5f5; padding: 15px 20px; border-radius: 12px; margin-bottom: 25px; border-left: 4px solid #7a0000; font-size: 14px; }
        .info-alumno strong { color: #7a0000; }
        .tabla-libreta { width: 100%; border-collapse: collapse; font-size: 12px; margin-bottom: 20px; }
        .tabla-libreta th, .tabla-libreta td { border: 1px solid #ddd; padding: 10px 8px; text-align: center; vertical-align: middle; }
        .tabla-libreta th { background: #7a0000; color: white; font-weight: 600; }
        .tabla-libreta th.vertical-text { writing-mode: vertical-lr; transform: rotate(180deg); width: 45px; min-width: 45px; padding: 8px 4px; white-space: nowrap; font-size: 11px; }
        .bg-header { background-color: #f0f0f0; }
        .aprobado { color: #2e7d32; font-weight: bold; }
        .desaprobado { color: #d32f2f; font-weight: bold; }
        .badge-justificadas { color: #2e7d32; font-weight: bold; }
        .badge-injustificadas { color: #d32f2f; font-weight: bold; }
        h2 { color: #7a0000; text-align: center; margin-top: 0; margin-bottom: 20px; }
    </style>
</head>
<body>
<div class="libreta-container">
    <h2>Libreta de Calificaciones<?php echo $titulo_adicional; ?></h2>
    
    <div class="info-alumno">
        <strong><?php echo htmlspecialchars($alumno['Apellido'] . ', ' . $alumno['Nombre']); ?></strong> | 
        DNI: <?php echo $alumno_id; ?> | 
        Curso: <?php echo $curso_nombre; ?>
    </div>

    <table class="tabla-libreta">
        <thead>
            <tr>
                <th rowspan="2">Trimestres</th>
                <?php foreach($nombres_materias as $nombre_mat): ?>
                    <th class="vertical-text" rowspan="2"><?php echo htmlspecialchars($nombre_mat); ?></th>
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
            $trimestres = [1 => '1er Trimestre', 2 => '2do Trimestre', 3 => '3er Trimestre'];
            $promedios_materias_acum = [];
            $trimestres_con_notas = [];
            
            foreach($trimestres as $id_trim => $nombre_trim): 
                // Aplicar filtros visuales si es un envio parcial (pero mantener la tabla estructurada)
                if($tipo_envio == 'trimestre1' && $id_trim != 1) continue;
                if($tipo_envio == 'trimestre2' && $id_trim > 2) continue;
                if($tipo_envio == 'trimestre3' && $id_trim > 3) continue;

                $suma_notas_trimestre = 0; $cont_materias_trimestre = 0; $tiene_notas = false;
                $just_trim = $inasistencias_por_trimestre[$id_trim]['justificadas'];
                $injust_trim = $inasistencias_por_trimestre[$id_trim]['injustificadas'];
            ?>
            <tr>
                <td class="bg-header"><strong><?php echo $nombre_trim; ?></strong></td>
                <?php foreach($nombres_materias as $nombre_mat): 
                    $nota = isset($notas_organizadas[$id_trim][$nombre_mat]) ? $notas_organizadas[$id_trim][$nombre_mat] : '-';
                    if(is_numeric($nota)){
                        $suma_notas_trimestre += $nota; $cont_materias_trimestre++;
                        $promedios_materias_acum[$nombre_mat]['suma'] = ($promedios_materias_acum[$nombre_mat]['suma'] ?? 0) + $nota;
                        $promedios_materias_acum[$nombre_mat]['count'] = ($promedios_materias_acum[$nombre_mat]['count'] ?? 0) + 1;
                        $tiene_notas = true;
                        $clase_color = $nota >= 6 ? 'aprobado' : 'desaprobado';
                        echo '<td class="' . $clase_color . '">' . number_format($nota, 2, '.', '') . '</td>';
                    } else { echo '<td>-</td>'; }
                endforeach; 
                
                if($tiene_notas) $trimestres_con_notas[$id_trim] = true;
                $todas_tienen_notas = true;
                foreach ($nombres_materias as $nombre_mat) {
                    $nota = isset($notas_organizadas[$id_trim][$nombre_mat]) ? $notas_organizadas[$id_trim][$nombre_mat] : '-';
                    if (!is_numeric($nota)) { $todas_tienen_notas = false; break; }
                }
                
                $promedio_trimestre = ($todas_tienen_notas && $cont_materias_trimestre == count($nombres_materias)) ? truncarNota($suma_notas_trimestre / $cont_materias_trimestre, 2) : '-';
                $clase_promedio = is_numeric($promedio_trimestre) ? ($promedio_trimestre >= 6 ? 'aprobado' : 'desaprobado') : '';
                ?>
                <td class="promedio <?php echo $clase_promedio; ?>"><strong><?php echo $promedio_trimestre; ?></strong></td>
                <td class="badge-justificadas"><?php echo $just_trim; ?></td>
                <td class="badge-injustificadas"><?php echo $injust_trim; ?></td>
            </tr>
            <?php endforeach; ?>
            
            <?php if($tipo_envio == 'final'): ?>
            <tr class="bg-header">
                <td><strong>Promedio Anual</strong></td>
                <?php 
                $suma_general_total = 0; $cont_general_total = 0;
                $tiene_tres = isset($trimestres_con_notas[1]) && isset($trimestres_con_notas[2]) && isset($trimestres_con_notas[3]);
                
                foreach($nombres_materias as $nombre_mat): 
                    $promedio_materia = '-';
                    if(isset($promedios_materias_acum[$nombre_mat]) && $promedios_materias_acum[$nombre_mat]['count'] > 0 && $tiene_tres){
                        $promedio_materia = truncarNota($promedios_materias_acum[$nombre_mat]['suma'] / $promedios_materias_acum[$nombre_mat]['count'], 2);
                        $suma_general_total += $promedio_materia; $cont_general_total++;
                    }
                    $clase_materia = is_numeric($promedio_materia) ? ($promedio_materia >= 6 ? 'aprobado' : 'desaprobado') : '';
                    echo "<td class='promedio $clase_materia'><strong>$promedio_materia</strong></td>";
                endforeach; 
                
                $promedio_general = ($cont_general_total > 0 && $tiene_tres) ? truncarNota($suma_general_total / $cont_general_total, 2) : '-';
                $clase_general = is_numeric($promedio_general) ? ($promedio_general >= 6 ? 'aprobado' : 'desaprobado') : '';
                echo "<td class='promedio $clase_general'><strong>$promedio_general</strong></td>";
                
                $total_just_anual = $inasistencias_por_trimestre[1]['justificadas'] + $inasistencias_por_trimestre[2]['justificadas'] + $inasistencias_por_trimestre[3]['justificadas'];
                $total_injust_anual = $inasistencias_por_trimestre[1]['injustificadas'] + $inasistencias_por_trimestre[2]['injustificadas'] + $inasistencias_por_trimestre[3]['injustificadas'];
                ?>
                <td class="badge-justificadas"><strong><?php echo $total_just_anual; ?></strong></td>
                <td class="badge-injustificadas"><strong><?php echo $total_injust_anual; ?></strong></td>
            </tr>
            <?php endif; ?>
        </tbody>
    </table>

    <?php if ($tiene_talleres && !empty($talleres_alumno)): ?>
        <h2 style="margin-top: 40px; margin-bottom: 20px;">Talleres</h2>
        <table class="tabla-libreta" id="tabla-talleres">
            <thead>
                <tr>
                    <th>Trimestres</th>
                    <?php foreach ($talleres_alumno as $id_t => $nombre_t): ?>
                        <th><?php echo htmlspecialchars($nombre_t); ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($trimestres as $id_trim => $nombre_trim): 
                    if($tipo_envio == 'trimestre1' && $id_trim != 1) continue;
                    if($tipo_envio == 'trimestre2' && $id_trim > 2) continue;
                    if($tipo_envio == 'trimestre3' && $id_trim > 3) continue;
                ?>
                <tr>
                    <td class="bg-header"><strong><?php echo $nombre_trim; ?></strong></td>
                    <?php foreach ($talleres_alumno as $id_t => $nombre_t):
                        $nota_t = isset($notas_taller_trimestre[$id_trim][$id_t]) ? $notas_taller_trimestre[$id_trim][$id_t] : '-';
                        if (is_numeric($nota_t)) {
                            $clase_color_t = $nota_t >= 6 ? 'aprobado' : 'desaprobado';
                            echo '<td class="' . $clase_color_t . '">' . number_format($nota_t, 2, '.', '') . '</td>';
                        } else { echo '<td>-</td>'; }
                    endforeach; ?>
                </tr>
                <?php endforeach; ?>
                
                <?php if($tipo_envio == 'final'): ?>
                <tr class="bg-header">
                    <td><strong>Promedio Anual</strong></td>
                    <?php foreach ($talleres_alumno as $id_t => $nombre_t):
                        $nota_taller = '-'; $clase_nota = '';
                        for($trim = 1; $trim <= 3; $trim++){
                            if(isset($notas_taller_trimestre[$trim][$id_t])){
                                $nota_taller = $notas_taller_trimestre[$trim][$id_t];
                                $clase_nota = $nota_taller >= 6 ? 'aprobado' : 'desaprobado';
                                break;
                            }
                        }
                    ?>
                        <td class="promedio <?php echo $clase_nota; ?>"><strong><?php echo is_numeric($nota_taller) ? number_format($nota_taller, 2) : '-'; ?></strong></td>
                    <?php endforeach; ?>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <div style="margin-top: 40px; text-align:center;">
        <h2 style="margin-bottom: 20px;">Previas y Equivalencias</h2>
        <div style="display: flex; flex-wrap: wrap; gap: 25px; justify-content: center;">
            
            <div style="flex: 1; min-width: 280px; background: #f5f5f5; border-radius: 12px; padding: 20px; text-align:left;">
                <h3 style="color: #7a0000; border-bottom: 2px solid #7a0000; padding-bottom:10px;">Previas</h3>
                <?php if(!empty($previas_pendientes)): ?>
                    <ul style="list-style: none; padding: 0;">
                        <?php foreach($previas_pendientes as $previa): ?>
                            <li style="padding: 10px; margin-bottom: 8px; background: #8F3C45; color:white; border-radius: 8px;">
                                <?php echo htmlspecialchars($previa); ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p style="color: #666; text-align:center;">No adeuda previas</p>
                <?php endif; ?>
            </div>
            
            <div style="flex: 1; min-width: 280px; background: #f5f5f5; border-radius: 12px; padding: 20px; text-align:left;">
                <h3 style="color: #2196f3; border-bottom: 2px solid #2196f3; padding-bottom:10px;">Equivalencias</h3>
                <?php if(!empty($equivalencias_pendientes)): ?>
                    <ul style="list-style: none; padding: 0;">
                        <?php foreach($equivalencias_pendientes as $equivalencia): ?>
                            <li style="padding: 10px; margin-bottom: 8px; background: #e3f2fd; border-left: 4px solid #2196f3; border-radius: 8px;">
                                <?php echo htmlspecialchars($equivalencia); ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <?php if(isset($alumno['DNI_U']) && $alumno['DNI_U'] == 48952162): ?>
                        <p style="color:#7a0000; font-weight: bold; text-align:center;">Equivalencias pendientes hasta traer el pase definitivo</p>
                    <?php else: ?>
                        <p style="color: #666; text-align:center;">No adeuda equivalencias</p>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
</body>
</html>
<?php mysqli_close($con); ?>