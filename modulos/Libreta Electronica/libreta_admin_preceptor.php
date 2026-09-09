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

// Verificar permisos - Solo Admin y Preceptor
$es_admin = ($_SESSION['rol'] == 'Admin');
$es_preceptor = ($_SESSION['rol'] == 'Preceptor');
$es_invitado = ($_SESSION['rol'] == 'Invitado');
$es_equipo = ($_SESSION['rol'] == 'Equipo de Orientacion');

if(!$es_admin && !$es_preceptor && !$es_invitado && !$es_equipo){
    echo '<script>alert("No tiene permisos para acceder a esta seccion"); window.location="../../recursos/panel.php";</script>';
    exit();
}

include '../../recursos/conexion.php';

$error = '';
$curso_seleccionado = isset($_GET['curso_id']) ? (int)$_GET['curso_id'] : 0;
$alumno_seleccionado = isset($_GET['alumno_id']) ? (int)$_GET['alumno_id'] : 0;
$anio_actual = date('Y');

// Función para truncar (no redondear)
function truncarNota($nota, $decimales = 2) {
    if (!is_numeric($nota)) return '-';
    $factor = pow(10, $decimales);
    return floor($nota * $factor) / $factor;
}

// ============================================
// OBTENER LISTA DE CURSOS SEGÚN ROL
// ============================================
$cursos_disponibles = [];

if($es_admin || $es_invitado || $es_equipo){
    // Admin: todos los cursos
    $query_cursos = "SELECT ID_curso, curso, division, turno FROM curso ORDER BY curso, division";
    $res_cursos = mysqli_query($con, $query_cursos);
    while($row = mysqli_fetch_assoc($res_cursos)){
        $cursos_disponibles[] = $row;
    }
} else {
    // Preceptor: solo cursos asignados en preceptorxcurso
    $preceptor_dni = $_SESSION["dni"];
    $query_cursos = "SELECT c.ID_curso, c.curso, c.division, c.turno
                     FROM curso c
                     INNER JOIN preceptorxcurso pxc ON c.ID_curso = pxc.id_curso
                     WHERE pxc.id_preceptor = '$preceptor_dni'
                     ORDER BY c.curso, c.division";
    $res_cursos = mysqli_query($con, $query_cursos);
    while($row = mysqli_fetch_assoc($res_cursos)){
        $cursos_disponibles[] = $row;
    }
}

// ============================================
// VERIFICAR QUE EL CURSO SELECCIONADO SEA VÁLIDO
// ============================================
$curso_valido = false;
$curso_info = null;
$alumnos_del_curso = [];

if($curso_seleccionado > 0){
    foreach($cursos_disponibles as $curso){
        if($curso['ID_curso'] == $curso_seleccionado){
            $curso_valido = true;
            $curso_info = $curso;
            break;
        }
    }
    
    if($curso_valido){
        // Obtener alumnos del curso (ID_rol = 3 = Estudiante, ID_Estado = 1 = Activo)
        $query_alumnos = "SELECT DNI_U, Nombre, Apellido 
                          FROM usuario 
                          WHERE id_curso = '$curso_seleccionado' 
                          AND ID_rol = 3 
                          AND ID_Estado = 1
                          ORDER BY Apellido, Nombre";
        $res_alumnos = mysqli_query($con, $query_alumnos);
        while($row = mysqli_fetch_assoc($res_alumnos)){
            $alumnos_del_curso[] = $row;
        }
    } else {
        $error = "Curso no válido o no autorizado.";
        $curso_seleccionado = 0;
    }
}

// ============================================
// OBTENER DATOS DEL ALUMNO SELECCIONADO
// ============================================
$alumno_info = null;
$materias = [];
$notas_organizadas = [];
$justificadas = 0;
$injustificadas = 0;
$tiene_talleres = false;
$talleres_alumno = [];
$notas_taller_trimestre = [];

if($alumno_seleccionado > 0 && $curso_valido){
    // Verificar que el alumno pertenezca al curso seleccionado
    $query_check_alumno = "SELECT DNI_U, Nombre, Apellido, id_curso 
                           FROM usuario 
                           WHERE DNI_U = '$alumno_seleccionado' 
                           AND ID_rol = 3 
                           AND id_curso = '$curso_seleccionado'";
    $res_check = mysqli_query($con, $query_check_alumno);
    if(mysqli_num_rows($res_check) > 0){
        $alumno_info = mysqli_fetch_assoc($res_check);
        
       // Obtener materias del curso (EXCLUYENDO "Taller" y "Tutorías")
       $query_materias = "SELECT DISTINCT m.ID_materia, m.Nom_materia 
                   FROM materia m 
                   WHERE m.id_curso = '$curso_seleccionado' 
                   AND m.Nom_materia NOT LIKE '%Taller%'
                   AND m.Nom_materia NOT LIKE '%Tutor%'
                   AND m.Nom_materia NOT LIKE '%Tutorías%'
                   GROUP BY m.Nom_materia
                   ORDER BY m.Nom_materia";
        $res_materias = mysqli_query($con, $query_materias);
        $materias = mysqli_fetch_all($res_materias, MYSQLI_ASSOC);
        
        // Obtener calificaciones del alumno (excluyendo Taller y Tutorías)
        $query_notas = "SELECT c.id_materia, c.trimestre, c.nota, m.Nom_materia, c.fecha
                FROM calificaciones c
                INNER JOIN materia m ON c.id_materia = m.ID_materia
                WHERE c.id_alumno = '$alumno_seleccionado'
                AND m.Nom_materia NOT LIKE '%Taller%'
                AND m.Nom_materia NOT LIKE '%Tutor%'
                AND m.Nom_materia NOT LIKE '%Tutorías%'
                ORDER BY c.fecha DESC, c.trimestre, m.Nom_materia";
        $res_notas = mysqli_query($con, $query_notas);
        
        $notas_acumuladas = [];
        
        while ($row = mysqli_fetch_assoc($res_notas)) {
            $trim = $row['trimestre'];
            $materia = $row['Nom_materia'];
            $nota = $row['nota'];
            
            if(!isset($notas_acumuladas[$trim][$materia])){
                $notas_acumuladas[$trim][$materia] = [];
            }
            $notas_acumuladas[$trim][$materia][] = $nota;
        }
        
        // Calcular el promedio para cada materia y trimestre
        $notas_organizadas = [];
        foreach($notas_acumuladas as $trim => $materias_temp){
            foreach($materias_temp as $materia => $notas_array){
                if(count($notas_array) > 0){
                    $promedio = array_sum($notas_array) / count($notas_array);
                    $notas_organizadas[$trim][$materia] = truncarNota($promedio, 2);
                }
            }
        }
        
        // ============================================
// INASISTENCIAS - Obtener por trimestre (con valores: completa=1, media=0.5, cuarto=0.25)
// ============================================
$inasistencias_por_trimestre = [
    1 => ['justificadas' => 0, 'injustificadas' => 0],
    2 => ['justificadas' => 0, 'injustificadas' => 0],
    3 => ['justificadas' => 0, 'injustificadas' => 0]
];

// Función para obtener el valor numérico de una falta
function getValorFalta($tipo_falta) {
    switch($tipo_falta){
        case 'completa': return 1;
        case 'media': return 0.5;
        case 'cuarto': return 0.25;
        default: return 1;
    }
}

if($alumno_seleccionado > 0 && $curso_valido){
    $query_inasistencias = "SELECT i.justificada, i.trimestre, i.tipo_falta
                            FROM inasistencias i
                            WHERE i.id_alumno = '$alumno_seleccionado' 
                            AND i.id_curso = '$curso_seleccionado'
                            AND YEAR(i.fecha) = '$anio_actual'";
    
    $res_inasistencias = mysqli_query($con, $query_inasistencias);
    
    if($res_inasistencias){
        while($row_inas = mysqli_fetch_assoc($res_inasistencias)){
            $trim = $row_inas['trimestre'];
            $valor_falta = getValorFalta($row_inas['tipo_falta']);
            
            if(isset($inasistencias_por_trimestre[$trim])){
                if($row_inas['justificada'] == 1){
                    $inasistencias_por_trimestre[$trim]['justificadas'] += $valor_falta;
                } else {
                    $inasistencias_por_trimestre[$trim]['injustificadas'] += $valor_falta;
                }
            }
        }
    }
}
        
        // ============================================
        // TALLERES
        // ============================================
        $rotacion_a_trimestre = [1 => 1, 2 => 1, 3 => 2, 4 => 2, 5 => 3, 6 => 3];
        
        $query_grupo_taller = "SELECT ID_curso, numero_grupo FROM alumno_grupo_taller 
                               WHERE ID_alumno = '$alumno_seleccionado' AND anio = '$anio_actual' LIMIT 1";
        $res_grupo_taller = mysqli_query($con, $query_grupo_taller);
        
        if (mysqli_num_rows($res_grupo_taller) > 0) {
            $grupo_taller = mysqli_fetch_assoc($res_grupo_taller);
            $tiene_talleres = true;
            $numero_grupo = $grupo_taller['numero_grupo'];
            $curso_taller = $grupo_taller['ID_curso'];
        
            $query_talleres = "
                SELECT pr.ID_rotacion, pr.ID_taller, t.nombre, t.anio_taller, r.numero_rotacion
                FROM planilla_rotaciones pr
                INNER JOIN talleres t ON pr.ID_taller = t.ID_taller
                INNER JOIN rotaciones r ON pr.ID_rotacion = r.ID_rotacion
                WHERE pr.ID_curso = '$curso_taller' AND pr.numero_grupo = '$numero_grupo' AND r.anio = '$anio_actual'
                ORDER BY r.numero_rotacion";
            $res_talleres = mysqli_query($con, $query_talleres);
        
            $rotaciones = [];
            while ($row = mysqli_fetch_assoc($res_talleres)) {
                $nombre_taller = $row['nombre'] . ' ' . $row['anio_taller'];
                $talleres_alumno[$row['ID_taller']] = $nombre_taller;
                $rotaciones[$row['ID_rotacion']] = [
                    'id_taller' => $row['ID_taller'],
                    'trimestre' => $rotacion_a_trimestre[$row['numero_rotacion']]
                ];
            }
        
            if (!empty($talleres_alumno)) {
                $ids_talleres = implode(',', array_keys($talleres_alumno));
                $ids_rotaciones = implode(',', array_keys($rotaciones));
                
                $query_notas_taller = "SELECT ID_taller, ID_rotacion, promedio as nota
                                       FROM calificaciones_taller
                                       WHERE ID_alumno = '$alumno_seleccionado'
                                       AND ID_taller IN ($ids_talleres)
                                       AND ID_rotacion IN ($ids_rotaciones)";
                $res_notas_taller = mysqli_query($con, $query_notas_taller);
                
                while ($row = mysqli_fetch_assoc($res_notas_taller)) {
                    $trimestre = $rotaciones[$row['ID_rotacion']]['trimestre'];
                    $notas_taller_trimestre[$trimestre][$row['ID_taller']] = truncarNota($row['nota'], 2);
                }
            }
        }
        
    } else {
        $error = "Alumno no válido o no pertenece al curso seleccionado.";
        $alumno_seleccionado = 0;
    }
}

// Preparar arrays para las tablas
$nombres_materias = [];
foreach ($materias as $m) {
    $nombres_materias[] = $m['Nom_materia'];
}
$trimestres = [1 => '1er Trimestre', 2 => '2do Trimestre', 3 => '3er Trimestre'];
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Libreta de Calificaciones - <?= $es_admin ? 'Administrador' : 'Preceptor' ?></title>
    <link rel="stylesheet" href="../../recursos/styles.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.28/jspdf.plugin.autotable.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <style>
        .selector-container {
            background: var(--card-bg);
            border-radius: 16px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .selector-titulo {
            color: var(--dark-burgundy);
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid var(--deep-crimson);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .selector-filas {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            align-items: flex-end;
        }
        .selector-grupo {
            flex: 1;
            min-width: 200px;
        }
        .selector-grupo label {
            display: block;
            font-weight: 600;
            color: var(--dark-burgundy);
            margin-bottom: 8px;
            font-size: 14px;
        }
        .selector-grupo select {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid var(--border-color);
            border-radius: 10px;
            font-size: 14px;
            font-family: 'Montserrat', sans-serif;
            background: var(--card-bg);
            color: var(--text-dark);
        }
        .selector-grupo select:focus {
            outline: none;
            border-color: var(--deep-crimson);
        }
        .libreta-container {
            background: var(--card-bg);
            border-radius: 16px;
            padding: 25px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            overflow-x: auto;
        }
        .info-alumno {
            background: var(--light-bg);
            padding: 15px 20px;
            border-radius: 12px;
            margin-bottom: 25px;
            border-left: 4px solid var(--deep-crimson);
        }
        .info-alumno strong {
            color: var(--deep-crimson);
        }
        .tabla-scroll {
            width: 100%;
            overflow-x: auto;
            overflow-y: visible;
            margin-bottom: 20px;
            -webkit-overflow-scrolling: touch;
        }
        .tabla-libreta {
            width: 100%;
            min-width: 600px;
            border-collapse: collapse;
            font-size: 0.85rem;
        }
        .tabla-libreta th,
        .tabla-libreta td {
            border: 1px solid var(--border-color);
            padding: 10px 8px;
            text-align: center;
            vertical-align: middle;
        }
        .tabla-libreta th {
            background: var(--dark-red);
            color: white;
            font-weight: 600;
        }
        .tabla-libreta th.vertical-text {
            writing-mode: vertical-lr;
            transform: rotate(180deg);
            width: 50px;
            min-width: 50px;
            padding: 8px 4px;
            white-space: nowrap;
            font-size: 0.75rem;
        }
        .promedio {
            font-weight: 700;
        }
        .promedio.aprobado {
            color: var(--success);
        }
        .promedio.desaprobado {
            color: var(--error);
        }
        .badge-justificadas {
            color: var(--success);
            font-weight: 600;
        }
        .badge-injustificadas {
            color: var(--error);
            font-weight: 600;
        }
        .volver-btn {
            display: inline-block;
            margin-top: 20px;
            padding: 10px 24px;
            background: var(--deep-crimson);
            color: white;
            text-decoration: none;
            border-radius: 10px;
            transition: all 0.3s ease;
            font-weight: 600;
        }
        .volver-btn:hover {
            background: var(--dark-red);
            transform: translateY(-2px);
        }
        .mensaje-error {
            background: rgba(211,47,47,0.1);
            border-left: 4px solid var(--error);
            padding: 15px 20px;
            border-radius: 10px;
            color: var(--error);
            font-weight: 500;
            margin-bottom: 20px;
        }
        .sin-datos {
            text-align: center;
            padding: 60px 20px;
            color: var(--text-muted);
        }
        .btn-pdf {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 18px;
            background: #7a0000;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 14px;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .btn-pdf:hover {
            background: #5a0000;
            transform: translateY(-1px);
        }
        @media (max-width: 768px) {
            .selector-filas {
                flex-direction: column;
            }
            .tabla-libreta th.vertical-text {
                width: 35px;
                font-size: 0.65rem;
            }
            .tabla-libreta th,
            .tabla-libreta td {
                padding: 6px 4px;
                font-size: 0.7rem;
            }
        }
    </style>
</head>
<body>
<div class="container" style="max-width: 1400px;">
    <div class="card" style="margin-bottom: 25px;">
        <div class="card-header">
            <h1>
                <?= $es_admin ? ' Administración' : ' Preceptor' ?>
                - Libreta de Calificaciones
            </h1>
        </div>
    </div>
    
    <div class="selector-container">
        <div class="selector-titulo">
             Seleccionar Alumno
        </div>
        
        <form method="GET" action="">
            <div class="selector-filas">
                <div class="selector-grupo">
                    <label for="curso_id">Curso</label>
                    <select name="curso_id" id="curso_id" onchange="this.form.submit()">
                        <option value="">-- Seleccione un curso --</option>
                        <?php foreach($cursos_disponibles as $curso):
                            $turno_texto = $curso['turno'] == 'M' ? 'Mañana' : 'Tarde';
                            $curso_nombre = $curso['curso'] . '° "' . $curso['division'] . '" - ' . $turno_texto;
                            $selected = ($curso_seleccionado == $curso['ID_curso']) ? 'selected' : '';
                        ?>
                            <option value="<?= $curso['ID_curso'] ?>" <?= $selected ?>>
                                <?= htmlspecialchars($curso_nombre) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="selector-grupo">
                    <label for="alumno_id">Alumno</label>
                    <select name="alumno_id" id="alumno_id" <?= empty($alumnos_del_curso) ? 'disabled' : '' ?> onchange="this.form.submit()">
                        <option value="">-- Seleccione un alumno --</option>
                        <?php foreach($alumnos_del_curso as $alumno):
                            $selected = ($alumno_seleccionado == $alumno['DNI_U']) ? 'selected' : '';
                        ?>
                            <option value="<?= $alumno['DNI_U'] ?>" <?= $selected ?>>
                                <?= htmlspecialchars($alumno['Apellido'] . ', ' . $alumno['Nombre']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </form>
    </div>
    
    <?php if($error): ?>
        <div class="mensaje-error">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>
    
    <?php if($alumno_info && !empty($materias)): ?>
        <div class="libreta-container">
            
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; flex-wrap: wrap; gap: 15px;">
                <div style="width: 40px;"></div>
                <h2 style="color: var(--dark-red); margin: 0; text-align: center; flex: 1;"> Libreta de Calificaciones</h2>
                
                <button id="btn-pdf" class="btn-pdf">
                     Descargar PDF
                </button>
            </div>
            
            <div class="info-alumno">
                <strong><?= htmlspecialchars($alumno_info['Apellido'] . ', ' . $alumno_info['Nombre']) ?></strong> | 
                DNI: <?= $alumno_info['DNI_U'] ?> | 
                Curso: <?php 
                    $turno_texto = $curso_info['turno'] == 'M' ? 'Mañana' : 'Tarde';
                    echo $curso_info['curso'] . '° "' . $curso_info['division'] . '" - ' . $turno_texto;
                ?>
            </div>
            
            <div class="tabla-scroll">
                <table class="tabla-libreta">
                    <thead>
                        <tr>
                            <th rowspan="2">Trimestres</th>
                            <?php foreach($materias as $m): ?>
                                <th class="vertical-text" rowspan="2"><?= htmlspecialchars($m['Nom_materia']) ?></th>
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
    
    foreach($trimestres as $id_trim => $nombre_trim): 
        $suma_notas_trimestre = 0;
        $cont_materias_trimestre = 0;
        $tiene_notas = false;
        
        // Obtener inasistencias de este trimestre
        $just_trim = $inasistencias_por_trimestre[$id_trim]['justificadas'];
        $injust_trim = $inasistencias_por_trimestre[$id_trim]['injustificadas'];
    ?>
        <tr>
            <td class="bg-header"><strong><?= $nombre_trim ?></strong></td>
            <?php foreach($nombres_materias as $nombre_mat): 
                $nota = isset($notas_organizadas[$id_trim][$nombre_mat]) ? $notas_organizadas[$id_trim][$nombre_mat] : '-';
                
                if(is_numeric($nota)){
                    $suma_notas_trimestre += $nota;
                    $cont_materias_trimestre++;
                    $promedios_materias_acum[$nombre_mat]['suma'] = ($promedios_materias_acum[$nombre_mat]['suma'] ?? 0) + $nota;
                    $promedios_materias_acum[$nombre_mat]['count'] = ($promedios_materias_acum[$nombre_mat]['count'] ?? 0) + 1;
                    $tiene_notas = true;
                    $clase_color = $nota >= 6 ? 'aprobado' : 'desaprobado';
                    echo '<td class="' . $clase_color . '">' . number_format($nota, 2, '.', '') . '</td>';
                } else {
                    echo '<td>-</td>';
                }
            endforeach; ?>
            
            <?php 
            if($tiene_notas){
                $trimestres_con_notas[$id_trim] = true;
            }
            $todas_tienen_notas = true;
            foreach ($nombres_materias as $nombre_mat) {
                $nota = isset($notas_organizadas[$id_trim][$nombre_mat]) ? $notas_organizadas[$id_trim][$nombre_mat] : '-';
                if (!is_numeric($nota)) {
                    $todas_tienen_notas = false;
                    break;
                }
            }
            
            if ($todas_tienen_notas && $cont_materias_trimestre == count($nombres_materias)) {
                $promedio_trimestre = truncarNota($suma_notas_trimestre / $cont_materias_trimestre, 2);
                $clase_promedio = $promedio_trimestre >= 6 ? 'aprobado' : 'desaprobado';
            } else {
                $promedio_trimestre = '-';
                $clase_promedio = '';
            }
            ?>
            <td class="promedio <?= $clase_promedio ?>"><strong><?= $promedio_trimestre ?></strong></td>
            
            <!-- Inasistencias del trimestre -->
            <td class="badge-justificadas"><?= $just_trim ?></td>
            <td class="badge-injustificadas"><?= $injust_trim ?></td>
        </tr>
    <?php endforeach; ?>
    
    <!-- Fila de Promedio Anual (acumula solo notas, NO inasistencias) -->
    <tr class="bg-header">
        <td><strong>Promedio Anual</strong></td>
        <?php 
        $suma_general_total = 0;
        $cont_general_total = 0;
        $tiene_tres = isset($trimestres_con_notas[1]) && isset($trimestres_con_notas[2]) && isset($trimestres_con_notas[3]);
        
        foreach($nombres_materias as $nombre_mat): 
            $promedio_materia = '-';
            if(isset($promedios_materias_acum[$nombre_mat]) && $promedios_materias_acum[$nombre_mat]['count'] > 0 && $tiene_tres){
                $promedio_materia = truncarNota($promedios_materias_acum[$nombre_mat]['suma'] / $promedios_materias_acum[$nombre_mat]['count'], 2);
                $suma_general_total += $promedio_materia;
                $cont_general_total++;
            }
            $clase_materia = '';
            if(is_numeric($promedio_materia)){
                $clase_materia = $promedio_materia >= 6 ? 'aprobado' : 'desaprobado';
            }
        ?>
            <td class="promedio <?= $clase_materia ?>"><strong><?= $promedio_materia ?></strong></td>
        <?php endforeach; ?>
        
        <?php 
        $promedio_general = ($cont_general_total > 0 && $tiene_tres) ? truncarNota($suma_general_total / $cont_general_total, 2) : '-';
        $clase_general = '';
        if(is_numeric($promedio_general)){
            $clase_general = $promedio_general >= 6 ? 'aprobado' : 'desaprobado';
        }
        ?>
        <td class="promedio <?= $clase_general ?>"><strong><?= $promedio_general ?></strong></td>
        
        <!-- Totales anuales de inasistencias (suma de los 3 trimestres) -->
        <?php 
        $total_just_anual = $inasistencias_por_trimestre[1]['justificadas'] + 
                            $inasistencias_por_trimestre[2]['justificadas'] + 
                            $inasistencias_por_trimestre[3]['justificadas'];
        $total_injust_anual = $inasistencias_por_trimestre[1]['injustificadas'] + 
                              $inasistencias_por_trimestre[2]['injustificadas'] + 
                              $inasistencias_por_trimestre[3]['injustificadas'];
        ?>
        <td class="badge-justificadas"><strong><?= $total_just_anual ?></strong></td>
        <td class="badge-injustificadas"><strong><?= $total_injust_anual ?></strong></td>
    </tr>
</tbody>
                </table>
            </div>
            
            <?php if ($tiene_talleres && !empty($talleres_alumno)): ?>
                <h2 style="color: var(--dark-red); text-align: center; margin-top: 40px; margin-bottom: 20px;"> Talleres</h2>
                <div class="tabla-scroll">
                    <table class="tabla-libreta" id="tabla-talleres">
                        <thead>
                            <tr>
                                <th>Trimestres</th>
                                <?php foreach ($talleres_alumno as $id_t => $nombre_t): ?>
                                    <th><?= htmlspecialchars($nombre_t) ?></th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $trimestres_taller = [1 => '1er Trimestre', 2 => '2do Trimestre', 3 => '3er Trimestre'];
                            foreach ($trimestres_taller as $id_trim => $nombre_trim):
                                $suma_trim_t = 0;
                                $cont_trim_t = 0;
                            ?>
                            <tr>
                                <td class="bg-header"><strong><?= $nombre_trim ?></strong></td>
                                <?php foreach ($talleres_alumno as $id_t => $nombre_t):
                                    $nota_t = isset($notas_taller_trimestre[$id_trim][$id_t]) ? $notas_taller_trimestre[$id_trim][$id_t] : '-';
                                    if (is_numeric($nota_t)) {
                                        $suma_trim_t += $nota_t;
                                        $cont_trim_t++;
                                        $clase_color_t = $nota_t >= 6 ? 'aprobado' : 'desaprobado';
                                        echo '<td class="' . $clase_color_t . '">' . number_format($nota_t, 2, '.', '') . '</td>';
                                    } else {
                                        echo '<td>-</td>';
                                    }
                                endforeach; ?>
                            <tr>
                            <?php endforeach; ?>
                            
                            <tr class="bg-header">
                                <td><strong>Promedio Anual</strong></td>
                                <?php foreach ($talleres_alumno as $id_t => $nombre_t):
                                    $nota_taller = '-';
                                    $clase_nota = '';
                                    for($trim = 1; $trim <= 3; $trim++){
                                        if(isset($notas_taller_trimestre[$trim][$id_t])){
                                            $nota_taller = $notas_taller_trimestre[$trim][$id_t];
                                            $clase_nota = $nota_taller >= 6 ? 'aprobado' : 'desaprobado';
                                            break;
                                        }
                                    }
                                ?>
                                    <td class="promedio <?= $clase_nota ?>"><strong><?= is_numeric($nota_taller) ? number_format($nota_taller, 2) : '-' ?></strong></td>
                                <?php endforeach; ?>
                            </tr>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>



<!-- ============================================ -->
<!-- SECCIÓN DE PREVIAS Y EQUIVALENCIAS -->
<!-- ============================================ -->
<?php
// Obtener previas del alumno
$previas_pendientes = [];
$equivalencias_pendientes = [];

$query_previas = "SELECT p.ID_previas, p.Aprobado, p.ID_tp,
                         CASE 
                             WHEN p.ID_materia IS NOT NULL THEN m.Nom_materia
                             WHEN p.ID_taller IS NOT NULL THEN CONCAT('Taller: ', t.nombre)
                         END as nombre_item,
                         CASE 
                             WHEN p.ID_materia IS NOT NULL THEN (SELECT curso FROM curso WHERE ID_curso = (SELECT id_curso FROM materia WHERE ID_materia = p.ID_materia))
                             WHEN p.ID_taller IS NOT NULL THEN 
                                 CASE WHEN t.anio_taller = 'I' THEN 1 ELSE 2 END
                         END as anio_item,
                         tp.nom_tp as tipo_previa
                  FROM previas p
                  INNER JOIN tipo_previa tp ON p.ID_tp = tp.ID_tp
                  LEFT JOIN materia m ON p.ID_materia = m.ID_materia
                  LEFT JOIN talleres t ON p.ID_taller = t.ID_taller
                  WHERE p.DNI_U = '$alumno_seleccionado'
                  AND p.Aprobado = 0
                  ORDER BY tp.ID_tp, anio_item, nombre_item";

$res_previas = mysqli_query($con, $query_previas);

while($row = mysqli_fetch_assoc($res_previas)){
    $item_texto = $row['nombre_item'] . ' (' . $row['anio_item'] . '° Año)';
    
    if($row['tipo_previa'] == 'Previa'){
        $previas_pendientes[] = $item_texto;
    } else {
        $equivalencias_pendientes[] = $item_texto;
    }
}
?>

<?php if(!empty($previas_pendientes) || !empty($equivalencias_pendientes)): ?>
<div style="margin-top: 40px;">
    <h2 style="color: var(--dark-red); text-align: center; margin-bottom: 20px;">Previas y Equivalencias</h2>
    
    <div style="display: flex; flex-wrap: wrap; gap: 25px; justify-content: center;">
        
        <!-- Columna de Previas -->
        <div style="flex: 1; min-width: 280px; background: var(--light-bg); border-radius: 12px; padding: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
            <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 20px; border-bottom: 2px solid var(--deep-crimson); padding-bottom: 10px;">
                <i class="fas fa-exclamation-triangle" style="color: #ff9800; font-size: 28px;"></i>
                <h3 style="color: var(--dark-burgundy); margin: 0;">Previas</h3>
                <span style="background: var(--deep-crimson); color: white; padding: 2px 10px; border-radius: 20px; font-size: 12px; font-weight: 600;">
                    <?= count($previas_pendientes) ?> materia(s)
                </span>
            </div>
            
            <?php if(!empty($previas_pendientes)): ?>
                <ul style="list-style: none; padding: 0; margin: 0;">
                    <?php foreach($previas_pendientes as $previa): ?>
                        <li style="padding: 12px 15px; margin-bottom: 8px; background: #8F3C45; border-radius: 8px; border-left: 4px solid #710A14; display: flex; align-items: center; gap: 10px;">
                            <i class="fas fa-book" style="color: #ff9800; font-size: 14px;"></i>
                            <span style="flex: 1; color:white;"><?= htmlspecialchars($previa) ?></span>
                            <span style="font-size: 11px; background: #710A14; color: white; padding: 2px 8px; border-radius: 12px;">Pendiente</span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <div style="text-align: center; padding: 40px 20px; color: var(--text-muted);">
                    <i class="fas fa-check-circle" style="font-size: 48px; color: #4caf50; margin-bottom: 10px; display: block;"></i>
                    <p style="margin: 0;"> No adeuda previas</p>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Columna de Equivalencias -->
        <div style="flex: 1; min-width: 280px; background: var(--light-bg); border-radius: 12px; padding: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
            <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 20px; border-bottom: 2px solid #2196f3; padding-bottom: 10px;">
                <i class="fas fa-exchange-alt" style="color: #2196f3; font-size: 28px;"></i>
                <h3 style="color: var(--dark-burgundy); margin: 0;">Equivalencias</h3>
                <span style="background: #2196f3; color: white; padding: 2px 10px; border-radius: 20px; font-size: 12px; font-weight: 600;">
                    <?= count($equivalencias_pendientes) ?> materia(s)
                </span>
            </div> 
            
            <?php if(!empty($equivalencias_pendientes)): ?>
                <ul style="list-style: none; padding: 0; margin: 0;">
                    <?php foreach($equivalencias_pendientes as $equivalencia): ?>
                        <li style="padding: 12px 15px; margin-bottom: 8px; background: #e3f2fd; border-radius: 8px; border-left: 4px solid #2196f3; display: flex; align-items: center; gap: 10px;">
                            <i class="fas fa-handshake" style="color: #2196f3; font-size: 14px;"></i>
                            <span style="flex: 1;"><?= htmlspecialchars($equivalencia) ?></span>
                            <span style="font-size: 11px; background: #2196f3; color: white; padding: 2px 8px; border-radius: 12px;">Pendiente</span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <div style="text-align: center; padding: 40px 20px; color: var(--text-muted);">
                    <i class="fas fa-check-circle" style="font-size: 48px; color: #4caf50; margin-bottom: 10px; display: block;"></i>
                    <p style="margin: 0;"> No adeuda equivalencias</p>
                </div>
            <?php endif; ?>
        
    </div>
</div>
<?php else: ?>
<!-- Si no hay previas ni equivalencias, mostrar un mensaje unificado -->
<div style="margin-top: 40px;">
    <h2 style="color: var(--dark-red); text-align: center; margin-bottom: 20px;"> Previas y Equivalencias</h2>
    
    <div style="display: flex; flex-wrap: wrap; gap: 25px; justify-content: center;">
        
        <div style="flex: 1; min-width: 280px; background: var(--light-bg); border-radius: 12px; padding: 20px; text-align: center;">
            <i class="fas fa-check-circle" style="font-size: 48px; color: #4caf50; margin-bottom: 10px; display: block;"></i>
            <p style="margin: 0; color: var(--text-muted);"> No adeuda previas</p>
        </div>
        
        <div style="flex: 1; min-width: 280px; background: var(--light-bg); border-radius: 12px; padding: 20px; text-align: center;">
            <i class="fas fa-check-circle" style="font-size: 48px; color: #4caf50; margin-bottom: 10px; display: block;"></i>
<?php if(isset($alumno_info['DNI_U']) && $alumno_info['DNI_U'] == 48952162): ?>
   <p style="margin: 0; color:#7a0000; font-weight: bold;">Equivalencias pendientes hasta traer el pase definitivo</p>
<?php else: ?>
            <p style="margin: 0; color: var(--text-muted);"> No adeuda equivalencias</p>
<?php endif; ?>
        </div>
        
    </div>
</div>
<?php endif; ?>






            
        </div>
    <?php elseif($curso_seleccionado > 0 && $curso_valido && empty($alumnos_del_curso)): ?>
        <div class="sin-datos">
            No hay alumnos activos en este curso.
        </div>
    <?php elseif($curso_seleccionado > 0 && $curso_valido && $alumno_seleccionado > 0 && empty($materias)): ?>
        <div class="sin-datos">
            No hay materias cargadas para este curso.
        </div>
    <?php endif; ?>


    
    <div style="text-align: center; margin-top: 30px;">
        <a href="../../recursos/panel.php" class="volver-btn">← Volver al Panel</a>
    </div>
</div>

<script>
async function generarPDF() {
    const { jsPDF } = window.jspdf;
    
    const contenedor = document.querySelector('.libreta-container');
    if (!contenedor) return;

    // 1. Ocultar el botón temporalmente
    const btnPdf = document.getElementById('btn-pdf');
    if (btnPdf) btnPdf.style.display = 'none';

    // 2. Ajustar scroll
    const elementosScroll = document.querySelectorAll('.tabla-scroll');
    elementosScroll.forEach(el => el.style.overflow = 'visible');

    // 3. IDENTIFICAR TALLERES, PREVIAS Y EQUIVALENCIAS
    let previasContenedor = null;
    document.querySelectorAll('.libreta-container h2').forEach(h2 => {
        if(h2.innerText.includes('Previas y Equivalencias')) {
            previasContenedor = h2.parentElement;
        }
    });

    let numPrevias = 0;
    let numEquivalencias = 0;
    if (previasContenedor) {
        previasContenedor.querySelectorAll('h3').forEach(h3 => {
            const columna = h3.parentElement.parentElement;
            if (h3.innerText.includes('Previas')) {
                numPrevias = columna.querySelectorAll('li').length;
            } else if (h3.innerText.includes('Equivalencias')) {
                numEquivalencias = columna.querySelectorAll('li').length;
            }
        });
    }

    // Verificamos si existe la tabla de talleres y si hay al menos 1 previa/equivalencia
    const tieneTalleres = document.getElementById('tabla-talleres') !== null;
    const tieneAlgunaPrevia = (numPrevias > 0 || numEquivalencias > 0);

    // NUEVA CONDICIÓN: 
    // Separamos si (hay más de 3 previas/equiv) O (tiene tabla de talleres Y al menos 1 previa/equiv)
    const separarPrevias = (numPrevias > 2 || numEquivalencias > 2) || (tieneTalleres && tieneAlgunaPrevia);

    // 4. Forzar al navegador a esperar que las fuentes estén cargadas
    await document.fonts.ready;

    // Función que genera las opciones de html2canvas (reutilizable)
    const getOpcionesHtml2Canvas = (esSegundaParte) => {
        return {
            scale: 2, 
            useCORS: true, 
            backgroundColor: '#ffffff',
            onclone: function(clonedDoc) {
                // A) Lógica de texto vertical en Alta Definición (HD)
                const verticales = clonedDoc.querySelectorAll('.tabla-libreta th.vertical-text');
                verticales.forEach(th => {
                    const text = th.innerText.trim();
                    const tempCanvas = document.createElement('canvas');
                    const ctx = tempCanvas.getContext('2d');
                    
                    ctx.font = 'bold 12px Arial, sans-serif';
                    const textWidth = ctx.measureText(text).width;
                    const textHeight = 16; 
                    
                    const logicalWidth = textHeight;
                    const logicalHeight = textWidth + 16; 
                    const scaleFactor = 4; // HD
                    
                    tempCanvas.width = logicalWidth * scaleFactor;
                    tempCanvas.height = logicalHeight * scaleFactor;
                    
                    ctx.scale(scaleFactor, scaleFactor);
                    ctx.font = 'bold 12px Arial, sans-serif';
                    ctx.translate(logicalWidth / 2, logicalHeight / 2);
                    ctx.rotate(-Math.PI / 2); 
                    ctx.textAlign = 'center';
                    ctx.textBaseline = 'middle';
                    ctx.fillStyle = '#ffffff'; 
                    
                    ctx.fillText(text, 0, 0);
                    
                    const img = document.createElement('img');
                    img.src = tempCanvas.toDataURL('image/png', 1.0);
                    img.style.display = 'block';
                    img.style.margin = '0 auto';
                    img.style.width = logicalWidth + 'px';
                    img.style.height = logicalHeight + 'px';
                    
                    th.style.writingMode = 'initial';
                    th.style.transform = 'none';
                    th.style.padding = '8px 4px';
                    th.innerHTML = '';
                    th.appendChild(img);
                });

                // B) Lógica de salto de página inteligente
                if (separarPrevias) {
                    const clonedContenedor = clonedDoc.querySelector('.libreta-container');
                    let clonedPrevias = null;
                    clonedContenedor.querySelectorAll('h2').forEach(h2 => {
                        if(h2.innerText.includes('Previas y Equivalencias')) {
                            clonedPrevias = h2.parentElement;
                        }
                    });

                    if (clonedPrevias) {
                        if (!esSegundaParte) {
                            // En la foto de la Hoja 1, ESCONDEMOS las previas
                            clonedPrevias.style.display = 'none';
                        } else {
                            // En la foto de la Hoja 2, ESCONDEMOS TODO menos las previas
                            Array.from(clonedContenedor.children).forEach(child => {
                                if (child !== clonedPrevias) {
                                    child.style.display = 'none';
                                }
                            });
                        }
                    }
                }
            }
        };
    };

    try {
        const doc = new jsPDF('l', 'mm', 'a4');
        const pageWidth = doc.internal.pageSize.getWidth();
        const pageHeight = doc.internal.pageSize.getHeight();
        const margin = 10;
        const imgWidth = pageWidth - (margin * 2);

        // Función para inyectar un canvas al PDF controlando si desborda
        const agregarCanvasAlPDF = (canvas) => {
            const imgData = canvas.toDataURL('image/png', 1.0);
            const imgHeight = (canvas.height * imgWidth) / canvas.width;
            let heightLeft = imgHeight;
            let position = margin;

            doc.addImage(imgData, 'PNG', margin, position, imgWidth, imgHeight);
            heightLeft -= (pageHeight - margin * 2);

            while (heightLeft > 0) {
                position = heightLeft - imgHeight + margin; 
                doc.addPage();
                doc.addImage(imgData, 'PNG', margin, position, imgWidth, imgHeight);
                heightLeft -= (pageHeight - margin * 2);
            }
        };

        // Capturamos e insertamos la primera parte
        const canvas1 = await html2canvas(contenedor, getOpcionesHtml2Canvas(false));
        agregarCanvasAlPDF(canvas1);

        // Si se cumple la condición, capturamos e insertamos la segunda parte en una nueva hoja
        if (separarPrevias) {
            doc.addPage();
            const canvas2 = await html2canvas(contenedor, getOpcionesHtml2Canvas(true));
            agregarCanvasAlPDF(canvas2);
        }

        // Obtener DNI y guardar
        const infoAlumno = document.querySelector('.info-alumno')?.innerText || '';
        const dniMatch = infoAlumno.match(/DNI:\s*(\d+)/);
        const dniAlumno = dniMatch ? dniMatch[1] : 'alumno';
        doc.save(`libreta_calificaciones_${dniAlumno}.pdf`);

    } catch(err) {
        console.error("Error al generar el PDF: ", err);
        alert("Ocurrió un error al intentar generar el archivo PDF.");
    } finally {
        // Restaurar estado visual original siempre
        if (btnPdf) btnPdf.style.display = 'inline-flex';
        elementosScroll.forEach(el => el.style.overflow = 'auto');
    }
}

document.addEventListener('DOMContentLoaded', function() {
    const btnPdf = document.getElementById('btn-pdf');
    if (btnPdf) {
        btnPdf.addEventListener('click', generarPDF);
    }
});
</script>
</body>
</html>
<?php mysqli_close($con); ?>