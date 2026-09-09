<?php
session_start();
header('Content-Type: application/json');

if(!isset($_SESSION['dni'])){
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit();
}

$es_admin = ($_SESSION['rol'] == 'Admin');
$es_preceptor = ($_SESSION['rol'] == 'Preceptor');
$es_secretario = ($_SESSION['rol'] == 'Secretario');
$es_equipo = ($_SESSION['rol'] == 'Equipo de Orientacion');

if(!$es_admin && !$es_preceptor && !$es_secretario && !$es_equipo){
    echo json_encode(['success' => false, 'message' => 'No tiene permisos']);
    exit();
}

include '../../recursos/conexion.php';

$action = isset($_POST['action']) ? $_POST['action'] : '';

// ============================================
// 1. APROBADOS VS DESAPROBADOS
// ============================================
if($action == 'aprobados_desaprobados'){
    $curso = isset($_POST['curso']) ? $_POST['curso'] : '';
    $materia = isset($_POST['materia']) ? $_POST['materia'] : '';
    $trimestre = isset($_POST['trimestre']) ? $_POST['trimestre'] : 0;
    
    $where = "WHERE 1=1";
    if($curso) $where .= " AND u.id_curso = '$curso'";
    if($materia) $where .= " AND c.id_materia = '$materia'";
    
    if($trimestre > 0){
        $where .= " AND c.trimestre = '$trimestre'";
        $query = "SELECT 
                    COUNT(DISTINCT CASE WHEN c.nota >= 6 THEN c.id_alumno END) as aprobados,
                    COUNT(DISTINCT CASE WHEN c.nota < 6 THEN c.id_alumno END) as desaprobados
                  FROM calificaciones c
                  INNER JOIN usuario u ON c.id_alumno = u.DNI_U
                  $where
                  AND u.ID_rol = 3 AND u.ID_Estado = 1";
    } else {
        $query = "SELECT 
                    COUNT(DISTINCT CASE WHEN prom.promedio >= 6 THEN prom.id_alumno END) as aprobados,
                    COUNT(DISTINCT CASE WHEN prom.promedio < 6 THEN prom.id_alumno END) as desaprobados
                  FROM (
                      SELECT c.id_alumno, AVG(c.nota) as promedio
                      FROM calificaciones c
                      INNER JOIN usuario u ON c.id_alumno = u.DNI_U
                      $where
                      AND u.ID_rol = 3 AND u.ID_Estado = 1
                      GROUP BY c.id_alumno
                  ) as prom";
    }
    
    $res = mysqli_query($con, $query);
    if($res){
        $data = mysqli_fetch_assoc($res);
        echo json_encode([
            'aprobados' => (int)($data['aprobados'] ?? 0),
            'desaprobados' => (int)($data['desaprobados'] ?? 0)
        ]);
    } else {
        echo json_encode(['aprobados' => 0, 'desaprobados' => 0]);
    }
    exit();
}

// ============================================
// 2. PROMEDIO GENERAL POR CURSO
// ============================================
if($action == 'promedio_general_curso'){
    $anio = isset($_POST['anio']) ? $_POST['anio'] : 0;
    $trimestre = isset($_POST['trimestre']) ? $_POST['trimestre'] : 0;
    
    $where_curso = "";
    if($anio > 0){
        $where_curso = " AND curso = '$anio'";
    }
    
    $cursos_query = "SELECT ID_curso, curso, division, turno 
                     FROM curso 
                     WHERE 1=1 $where_curso 
                     ORDER BY curso, division";
    $res_cursos = mysqli_query($con, $cursos_query);
    
    if(!$res_cursos){
        echo json_encode(['cursos' => [], 'promedios' => []]);
        exit();
    }
    
    $cursos = [];
    $promedios = [];
    
    while($curso = mysqli_fetch_assoc($res_cursos)){
        $curso_id = $curso['ID_curso'];
        $turno_texto = $curso['turno'] == 'M' ? 'Mañana' : 'Tarde';
        $nombre = $curso['curso'] . '° "' . $curso['division'] . '" (' . $turno_texto . ')';
        
        if($trimestre > 0){
            $query = "SELECT AVG(c.nota) as promedio
                      FROM calificaciones c
                      INNER JOIN usuario u ON c.id_alumno = u.DNI_U
                      WHERE u.id_curso = '$curso_id' 
                      AND u.ID_rol = 3 AND u.ID_Estado = 1
                      AND c.trimestre = '$trimestre'";
        } else {
            $query = "SELECT AVG(c.nota) as promedio
                      FROM calificaciones c
                      INNER JOIN usuario u ON c.id_alumno = u.DNI_U
                      WHERE u.id_curso = '$curso_id' 
                      AND u.ID_rol = 3 AND u.ID_Estado = 1";
        }
        
        $res = mysqli_query($con, $query);
        $promedio = 0;
        if($res && mysqli_num_rows($res) > 0){
            $row = mysqli_fetch_assoc($res);
            $promedio = round($row['promedio'] ?? 0, 2);
        }
        
        $cursos[] = $nombre;
        $promedios[] = $promedio;
    }
    
    echo json_encode(['cursos' => $cursos, 'promedios' => $promedios]);
    exit();
}

// ============================================
// 3. PROMEDIO POR MATERIA
// ============================================
if($action == 'promedio_por_materia'){
    $anio = isset($_POST['anio']) ? $_POST['anio'] : 0;
    $division = isset($_POST['division']) ? $_POST['division'] : '';
    $trimestre = isset($_POST['trimestre']) ? $_POST['trimestre'] : 0;
    
    $where_curso = "";
    if($anio > 0){
        $where_curso .= " AND c.curso = '$anio'";
    }
    if($division){
        $where_curso .= " AND c.division = '$division'";
    }
    
    $materias_query = "SELECT DISTINCT m.ID_materia, m.Nom_materia, c.curso, c.division
                       FROM materia m
                       INNER JOIN curso c ON m.id_curso = c.ID_curso
                       WHERE m.Nom_materia NOT LIKE '%Taller%'
                       AND m.Nom_materia NOT LIKE '%Tutor%'
                       AND m.Nom_materia NOT LIKE '%Tutorias%'
                       $where_curso
                       ORDER BY c.curso, c.division, m.Nom_materia";
    $res_materias = mysqli_query($con, $materias_query);
    
    if(!$res_materias){
        echo json_encode(['materias' => [], 'promedios' => [], 'detalles' => []]);
        exit();
    }
    
    $materias = [];
    $promedios = [];
    $detalles = [];
    
    while($materia = mysqli_fetch_assoc($res_materias)){
        $materia_id = $materia['ID_materia'];
        $curso_info = $materia['curso'] . '° "' . $materia['division'] . '"';
        $nombre = $materia['Nom_materia'] . ' (' . $curso_info . ')';
        
        if($trimestre > 0){
            $query = "SELECT AVG(c.nota) as promedio
                      FROM calificaciones c
                      INNER JOIN usuario u ON c.id_alumno = u.DNI_U
                      INNER JOIN curso cu ON u.id_curso = cu.ID_curso
                      WHERE c.id_materia = '$materia_id'
                      AND u.ID_rol = 3 AND u.ID_Estado = 1
                      AND c.trimestre = '$trimestre'";
        } else {
            $query = "SELECT AVG(c.nota) as promedio
                      FROM calificaciones c
                      INNER JOIN usuario u ON c.id_alumno = u.DNI_U
                      INNER JOIN curso cu ON u.id_curso = cu.ID_curso
                      WHERE c.id_materia = '$materia_id'
                      AND u.ID_rol = 3 AND u.ID_Estado = 1";
        }
        
        $res = mysqli_query($con, $query);
        $promedio = 0;
        if($res && mysqli_num_rows($res) > 0){
            $row = mysqli_fetch_assoc($res);
            $promedio = round($row['promedio'] ?? 0, 2);
        }
        
        $materias[] = $nombre;
        $promedios[] = $promedio;
        $detalles[] = [
            'nombre' => $nombre,
            'promedio' => $promedio,
            'estado' => $promedio >= 6 ? 'Aprobado' : 'Desaprobado'
        ];
    }
    
    echo json_encode([
        'materias' => $materias, 
        'promedios' => $promedios,
        'detalles' => $detalles
    ]);
    exit();
}

// ============================================
// 4. EVOLUCIÓN DE DESEMPEÑO
// ============================================
if($action == 'evolucion_trimestres'){
    $curso = isset($_POST['curso']) ? $_POST['curso'] : '';
    $materia = isset($_POST['materia']) ? $_POST['materia'] : '';
    
    $where = "";
    if($curso) $where .= " AND u.id_curso = '$curso'";
    if($materia) $where .= " AND c.id_materia = '$materia'";
    
    $t1 = 0; $t2 = 0; $t3 = 0;
    
    for($trim = 1; $trim <= 3; $trim++){
        $query = "SELECT AVG(c.nota) as promedio
                  FROM calificaciones c
                  INNER JOIN usuario u ON c.id_alumno = u.DNI_U
                  WHERE u.ID_rol = 3 AND u.ID_Estado = 1
                  AND c.trimestre = '$trim'
                  $where";
        $res = mysqli_query($con, $query);
        if($res){
            $row = mysqli_fetch_assoc($res);
            $promedio = round($row['promedio'] ?? 0, 2);
        } else {
            $promedio = 0;
        }
        ${"t$trim"} = $promedio;
    }
    
    echo json_encode(['t1' => $t1, 't2' => $t2, 't3' => $t3]);
    exit();
}

// ============================================
// 5. ALUMNOS CON PROMEDIO INFERIOR A VALOR
// ============================================
if($action == 'alumnos_bajo_promedio'){
    $curso = isset($_POST['curso']) ? $_POST['curso'] : '';
    $materia = isset($_POST['materia']) ? $_POST['materia'] : '';
    $nota = isset($_POST['nota']) ? $_POST['nota'] : 6;
    
    function truncarNota($nota, $decimales = 2) {
        if (!is_numeric($nota)) return '-';
        $factor = pow(10, $decimales);
        return floor($nota * $factor) / $factor;
    }
    
    $where_curso = "";
    if($curso){
        $where_curso = " AND u.id_curso = '$curso'";
    }
    
    $where_materia = "";
    if($materia){
        $where_materia = " AND m.ID_materia = '$materia'";
    }
    
    $excluir_materias = " AND m.Nom_materia NOT LIKE '%Taller%'
                          AND m.Nom_materia NOT LIKE '%Tutor%'
                          AND m.Nom_materia NOT LIKE '%Tutorías%'";
    
    $query = "SELECT 
                prom.DNI_U,
                prom.Nombre,
                prom.Apellido,
                prom.curso,
                prom.division,
                AVG(prom.promedio_materia) as promedio
              FROM (
                  SELECT 
                      u.DNI_U,
                      u.Nombre,
                      u.Apellido,
                      cu.curso,
                      cu.division,
                      AVG(c.nota) as promedio_materia
                  FROM calificaciones c
                  INNER JOIN usuario u ON c.id_alumno = u.DNI_U
                  INNER JOIN curso cu ON u.id_curso = cu.ID_curso
                  INNER JOIN materia m ON c.id_materia = m.ID_materia
                  WHERE u.ID_rol = 3 
                  AND u.ID_Estado = 1
                  $where_curso
                  $where_materia
                  $excluir_materias
                  GROUP BY u.DNI_U, u.Nombre, u.Apellido, cu.curso, cu.division, m.ID_materia
              ) AS prom
              GROUP BY prom.DNI_U, prom.Nombre, prom.Apellido, prom.curso, prom.division
              HAVING AVG(prom.promedio_materia) < $nota
              ORDER BY promedio ASC";
    
    $res = mysqli_query($con, $query);
    $alumnos = [];
    while($row = mysqli_fetch_assoc($res)){
        $alumnos[] = [
            'DNI_U' => $row['DNI_U'],
            'Nombre' => $row['Nombre'],
            'Apellido' => $row['Apellido'],
            'curso' => $row['curso'],
            'division' => $row['division'],
            'promedio' => truncarNota($row['promedio'], 2)
        ];
    }
    
    $total_query = "SELECT COUNT(DISTINCT u.DNI_U) as total
                    FROM usuario u
                    WHERE u.ID_rol = 3 AND u.ID_Estado = 1";
    if($curso) $total_query .= " AND u.id_curso = '$curso'";
    $res_total = mysqli_query($con, $total_query);
    $total = 0;
    if($res_total){
        $total = mysqli_fetch_assoc($res_total)['total'] ?? 0;
    }
    
    $alumnos_riesgo = count($alumnos);
    $porcentaje = $total > 0 ? round(($alumnos_riesgo / $total) * 100) : 0;
    
    echo json_encode([
        'total_alumnos' => $total,
        'alumnos_riesgo' => $alumnos_riesgo,
        'porcentaje' => $porcentaje,
        'alumnos_lista' => $alumnos
    ]);
    exit();
}

// ============================================
// 6. RANKING DE CURSOS CON ALUMNOS EN RIESGO
// ============================================
if($action == 'ranking_riesgo'){
    $trimestre = isset($_POST['trimestre']) ? $_POST['trimestre'] : 0;
    
    $where_trim = "";
    if($trimestre > 0) $where_trim = " AND cal.trimestre = '$trimestre'";
    
    $cursos_query = "SELECT ID_curso, curso, division, turno FROM curso ORDER BY curso, division";
    $res_cursos = mysqli_query($con, $cursos_query);
    
    $ranking = [];
    $total_riesgo = 0;
    
    while($curso = mysqli_fetch_assoc($res_cursos)){
        $curso_id = $curso['ID_curso'];
        $turno_texto = $curso['turno'] == 'M' ? 'Mañana' : 'Tarde';
        $nombre = $curso['curso'] . '° "' . $curso['division'] . '" (' . $turno_texto . ')';
        
        $query_total = "SELECT COUNT(*) as total FROM usuario WHERE id_curso = '$curso_id' AND ID_rol = 3 AND ID_Estado = 1";
        $res_total = mysqli_query($con, $query_total);
        $total = mysqli_fetch_assoc($res_total)['total'] ?? 0;
        
        if($total == 0) continue;
        
        $query_riesgo = "SELECT 
                            COUNT(*) as riesgo
                        FROM (
                            SELECT 
                                sub.DNI_U
                            FROM (
                                SELECT 
                                    u.DNI_U,
                                    m.ID_materia,
                                    AVG(cal.nota) as promedio_materia
                                FROM usuario u
                                INNER JOIN calificaciones cal ON u.DNI_U = cal.id_alumno
                                INNER JOIN materia m ON cal.id_materia = m.ID_materia
                                WHERE u.id_curso = '$curso_id'
                                AND u.ID_rol = 3
                                AND u.ID_Estado = 1
                                $where_trim
                                GROUP BY u.DNI_U, m.ID_materia
                                HAVING AVG(cal.nota) < 6
                            ) AS sub
                            GROUP BY sub.DNI_U
                            HAVING COUNT(*) >= 3
                        ) AS riesgo_por_alumno";
        
        $res_riesgo = mysqli_query($con, $query_riesgo);
        $alumnos_riesgo = 0;
        if($res_riesgo){
            $row = mysqli_fetch_assoc($res_riesgo);
            $alumnos_riesgo = $row['riesgo'] ?? 0;
        }
        
        $total_riesgo += $alumnos_riesgo;
        $porcentaje = $total > 0 ? round(($alumnos_riesgo / $total) * 100) : 0;
        
        $ranking[] = [
            'curso' => $nombre,
            'total_alumnos' => $total,
            'alumnos_riesgo' => $alumnos_riesgo,
            'porcentaje' => $porcentaje
        ];
    }
    
    usort($ranking, function($a, $b) {
        return $b['porcentaje'] - $a['porcentaje'];
    });
    
    echo json_encode([
        'total_cursos' => count($ranking),
        'total_riesgo' => $total_riesgo,
        'ranking' => $ranking
    ]);
    exit();
}

// ============================================
// 7. BAJA ASISTENCIA Y BAJO RENDIMIENTO
// ============================================
if($action == 'baja_asistencia_rendimiento'){
    $curso = isset($_POST['curso']) ? $_POST['curso'] : '';
    $faltas_limite = isset($_POST['faltas']) ? $_POST['faltas'] : 15;
    
    $where_curso = "";
    if($curso) $where_curso = " AND u.id_curso = '$curso'";
    
    $query_alumnos = "SELECT u.DNI_U, u.Nombre, u.Apellido, u.id_curso, cu.curso, cu.division
                      FROM usuario u
                      INNER JOIN curso cu ON u.id_curso = cu.ID_curso
                      WHERE u.ID_rol = 3 AND u.ID_Estado = 1
                      $where_curso";
    $res_alumnos = mysqli_query($con, $query_alumnos);
    
    $sin_problemas = 0;
    $solo_asistencia = 0;
    $solo_rendimiento = 0;
    $ambos = 0;
    
    while($alumno = mysqli_fetch_assoc($res_alumnos)){
        $dni = $alumno['DNI_U'];
        
        $query_faltas = "SELECT COUNT(*) as total FROM inasistencias 
                         WHERE id_alumno = '$dni' AND (justificada = 0 OR justificada IS NULL)";
        $res_faltas = mysqli_query($con, $query_faltas);
        $total_faltas = 0;
        if($res_faltas){
            $total_faltas = mysqli_fetch_assoc($res_faltas)['total'] ?? 0;
        }
        $baja_asistencia = $total_faltas > $faltas_limite;
        
        $query_rendimiento = "SELECT COUNT(*) as materias_bajas
                              FROM (
                                  SELECT 
                                      m.ID_materia,
                                      AVG(cal.nota) as promedio_materia
                                  FROM calificaciones cal
                                  INNER JOIN materia m ON cal.id_materia = m.ID_materia
                                  WHERE cal.id_alumno = '$dni'
                                  GROUP BY m.ID_materia
                                  HAVING AVG(cal.nota) < 6
                              ) AS materias_con_promedio_bajo";
        
        $res_rendimiento = mysqli_query($con, $query_rendimiento);
        $materias_bajas = 0;
        if($res_rendimiento){
            $row = mysqli_fetch_assoc($res_rendimiento);
            $materias_bajas = $row['materias_bajas'] ?? 0;
        }
        $bajo_rendimiento = $materias_bajas >= 3;
        
        if(!$baja_asistencia && !$bajo_rendimiento){
            $sin_problemas++;
        } elseif($baja_asistencia && !$bajo_rendimiento){
            $solo_asistencia++;
        } elseif(!$baja_asistencia && $bajo_rendimiento){
            $solo_rendimiento++;
        } else {
            $ambos++;
        }
    }
    
    $total = $sin_problemas + $solo_asistencia + $solo_rendimiento + $ambos;
    $porcentajes = [
        'sin_problemas' => $total > 0 ? round(($sin_problemas / $total) * 100) : 0,
        'solo_asistencia' => $total > 0 ? round(($solo_asistencia / $total) * 100) : 0,
        'solo_rendimiento' => $total > 0 ? round(($solo_rendimiento / $total) * 100) : 0,
        'ambos' => $total > 0 ? round(($ambos / $total) * 100) : 0
    ];
    
    echo json_encode([
        'total' => $total,
        'sin_problemas' => $sin_problemas,
        'solo_asistencia' => $solo_asistencia,
        'solo_rendimiento' => $solo_rendimiento,
        'ambos' => $ambos,
        'porcentajes' => $porcentajes
    ]);
    exit();
}

// ============================================
// 8. ALUMNOS EN RIESGO POR CURSO (para el ojito)
// ============================================
if($action == 'alumnos_riesgo_por_curso'){
    $curso_id = isset($_POST['curso_id']) ? $_POST['curso_id'] : 0;
    $trimestre = isset($_POST['trimestre']) ? $_POST['trimestre'] : 0;
    
    if(!$curso_id){
        echo json_encode(['success' => false, 'message' => 'Curso no valido']);
        exit();
    }
    
    $where_trim = "";
    if($trimestre > 0) $where_trim = " AND cal.trimestre = '$trimestre'";
    
    $query = "SELECT 
                sub.DNI_U,
                sub.Nombre,
                sub.Apellido,
                COUNT(*) as materias_bajas
              FROM (
                  SELECT 
                      u.DNI_U,
                      u.Nombre,
                      u.Apellido,
                      m.ID_materia,
                      AVG(cal.nota) as promedio_materia
                  FROM usuario u
                  INNER JOIN calificaciones cal ON u.DNI_U = cal.id_alumno
                  INNER JOIN materia m ON cal.id_materia = m.ID_materia
                  WHERE u.id_curso = '$curso_id'
                  AND u.ID_rol = 3
                  AND u.ID_Estado = 1
                  $where_trim
                  GROUP BY u.DNI_U, u.Nombre, u.Apellido, m.ID_materia
                  HAVING AVG(cal.nota) < 6
              ) AS sub
              GROUP BY sub.DNI_U, sub.Nombre, sub.Apellido
              HAVING COUNT(*) >= 3
              ORDER BY sub.Apellido, sub.Nombre";
    
    $res = mysqli_query($con, $query);
    $alumnos = [];
    while($row = mysqli_fetch_assoc($res)){
        $alumnos[] = $row;
    }
    
    echo json_encode(['success' => true, 'alumnos' => $alumnos]);
    exit();
}

// ============================================
// 9. MATERIAS BAJAS DE UN ALUMNO
// ============================================
if($action == 'materias_bajas_alumno'){
    $dni = isset($_POST['dni']) ? $_POST['dni'] : 0;
    $trimestre = isset($_POST['trimestre']) ? $_POST['trimestre'] : 0;
    
    if(!$dni){
        echo json_encode(['success' => false, 'message' => 'DNI no valido']);
        exit();
    }
    
    $where_trim = "";
    if($trimestre > 0) $where_trim = " AND cal.trimestre = '$trimestre'";
    
    $query = "SELECT 
                m.Nom_materia,
                AVG(cal.nota) as promedio
              FROM calificaciones cal
              INNER JOIN materia m ON cal.id_materia = m.ID_materia
              WHERE cal.id_alumno = '$dni'
              $where_trim
              GROUP BY m.ID_materia, m.Nom_materia
              HAVING AVG(cal.nota) < 6
              ORDER BY promedio ASC";
    
    $res = mysqli_query($con, $query);
    $materias = [];
    while($row = mysqli_fetch_assoc($res)){
        $materias[] = [
            'Nom_materia' => $row['Nom_materia'],
            'promedio' => round($row['promedio'], 2)
        ];
    }
    
    echo json_encode(['success' => true, 'materias' => $materias]);
    exit();
}

// ============================================
// 10. ALUMNOS POR CATEGORIA (para el gráfico 7)
// ============================================
if($action == 'alumnos_por_categoria'){
    $categoria = isset($_POST['categoria']) ? $_POST['categoria'] : '';
    $curso = isset($_POST['curso']) ? $_POST['curso'] : '';
    $faltas_limite = isset($_POST['faltas']) ? $_POST['faltas'] : 15;
    
    $where_curso = "";
    if($curso) $where_curso = " AND u.id_curso = '$curso'";
    
    $query_alumnos = "SELECT u.DNI_U, u.Nombre, u.Apellido, u.id_curso, cu.curso, cu.division
                      FROM usuario u
                      INNER JOIN curso cu ON u.id_curso = cu.ID_curso
                      WHERE u.ID_rol = 3 AND u.ID_Estado = 1
                      $where_curso";
    $res_alumnos = mysqli_query($con, $query_alumnos);
    
    $alumnos_filtrados = [];
    
    while($alumno = mysqli_fetch_assoc($res_alumnos)){
        $dni = $alumno['DNI_U'];
        
        $query_faltas = "SELECT COUNT(*) as total FROM inasistencias 
                         WHERE id_alumno = '$dni' AND (justificada = 0 OR justificada IS NULL)";
        $res_faltas = mysqli_query($con, $query_faltas);
        $total_faltas = 0;
        if($res_faltas){
            $total_faltas = mysqli_fetch_assoc($res_faltas)['total'] ?? 0;
        }
        $baja_asistencia = $total_faltas > $faltas_limite;
        
        $query_rendimiento = "SELECT COUNT(*) as materias_bajas
                              FROM (
                                  SELECT 
                                      m.ID_materia,
                                      AVG(cal.nota) as promedio_materia
                                  FROM calificaciones cal
                                  INNER JOIN materia m ON cal.id_materia = m.ID_materia
                                  WHERE cal.id_alumno = '$dni'
                                  GROUP BY m.ID_materia
                                  HAVING AVG(cal.nota) < 6
                              ) AS materias_con_promedio_bajo";
        
        $res_rendimiento = mysqli_query($con, $query_rendimiento);
        $materias_bajas = 0;
        if($res_rendimiento){
            $row = mysqli_fetch_assoc($res_rendimiento);
            $materias_bajas = $row['materias_bajas'] ?? 0;
        }
        $bajo_rendimiento = $materias_bajas >= 3;
        
        $categoria_alumno = '';
        if(!$baja_asistencia && !$bajo_rendimiento){
            $categoria_alumno = 'sin_problemas';
        } elseif($baja_asistencia && !$bajo_rendimiento){
            $categoria_alumno = 'solo_asistencia';
        } elseif(!$baja_asistencia && $bajo_rendimiento){
            $categoria_alumno = 'solo_rendimiento';
        } else {
            $categoria_alumno = 'ambos';
        }
        
        if($categoria_alumno == $categoria){
            $alumnos_filtrados[] = [
                'DNI_U' => $dni,
                'Nombre' => $alumno['Nombre'],
                'Apellido' => $alumno['Apellido'],
                'curso' => $alumno['curso'],
                'division' => $alumno['division'],
                'faltas' => $total_faltas,
                'materias_bajas' => $materias_bajas
            ];
        }
    }
    
    echo json_encode(['success' => true, 'alumnos' => $alumnos_filtrados]);
    exit();
}

// ============================================
// 11. PREVIAS/EQUIVALENCIAS POR AÑO (EXCLUYENDO 1° AÑO)
// ============================================
if($action == 'previas_por_anio'){
    $anio = isset($_POST['anio']) ? $_POST['anio'] : 0;
    $tipo = isset($_POST['tipo']) ? $_POST['tipo'] : '';
    
    $where_anio = "";
    if($anio > 0){
        $where_anio = " AND c.curso = '$anio'";
    }
    
    $where_tipo = "";
    if($tipo == '1' || $tipo == '2'){
        $where_tipo = " AND p.ID_tp = '$tipo'";
    }
    
    $cursos_query = "SELECT ID_curso, curso, division, turno 
                     FROM curso 
                     WHERE curso != 1
                     $where_anio
                     ORDER BY curso, division";
    $res_cursos = mysqli_query($con, $cursos_query);
    
    $cursos = [];
    $alumnos_con_previa = [];
    $totales_curso = [];
    
    while($curso_row = mysqli_fetch_assoc($res_cursos)){
        $curso_id = $curso_row['ID_curso'];
        $turno_texto = $curso_row['turno'] == 'M' ? 'Mañana' : 'Tarde';
        $nombre = $curso_row['curso'] . '° "' . $curso_row['division'] . '" (' . $turno_texto . ')';
        
        $query_total = "SELECT COUNT(*) as total FROM usuario 
                        WHERE id_curso = '$curso_id' AND ID_rol = 3 AND ID_Estado = 1";
        $res_total = mysqli_query($con, $query_total);
        $total = mysqli_fetch_assoc($res_total)['total'] ?? 0;
        
        if($total == 0) continue;
        
        $query_previa = "SELECT COUNT(DISTINCT u.DNI_U) as total
                         FROM usuario u
                         INNER JOIN previas p ON u.DNI_U = p.DNI_U
                         WHERE u.id_curso = '$curso_id'
                         AND u.ID_rol = 3 AND u.ID_Estado = 1
                         AND p.Aprobado = 0
                         $where_tipo
                         GROUP BY u.DNI_U
                         HAVING COUNT(*) >= 2";
        
        $res_previa = mysqli_query($con, $query_previa);
        $alumnos = mysqli_num_rows($res_previa);
        
        $cursos[] = $nombre;
        $alumnos_con_previa[] = $alumnos;
        $totales_curso[] = $total;
    }
    
    echo json_encode([
        'cursos' => $cursos,
        'alumnos' => $alumnos_con_previa,
        'totales' => $totales_curso
    ]);
    exit();
}

// ============================================
// 12. ALUMNOS CON PREVIAS POR CURSO (DETALLE)
// ============================================
if($action == 'alumnos_previas_por_curso'){
    $curso_id = isset($_POST['curso_id']) ? $_POST['curso_id'] : 0;
    $tipo = isset($_POST['tipo']) ? $_POST['tipo'] : '';
    
    if(!$curso_id){
        echo json_encode(['success' => false, 'message' => 'Curso no valido']);
        exit();
    }
    
    $query_verificar = "SELECT curso FROM curso WHERE ID_curso = '$curso_id'";
    $res_verificar = mysqli_query($con, $query_verificar);
    $curso_info = mysqli_fetch_assoc($res_verificar);
    
    if($curso_info['curso'] == 1){
        echo json_encode(['success' => true, 'alumnos' => []]);
        exit();
    }
    
    $where_tipo = "";
    if($tipo == '1' || $tipo == '2'){
        $where_tipo = " AND p.ID_tp = '$tipo'";
    }
    
    $query = "SELECT 
                u.DNI_U,
                u.Nombre,
                u.Apellido,
                COUNT(*) as cantidad,
                GROUP_CONCAT(DISTINCT tp.nom_tp SEPARATOR ', ') as tipos_previa
              FROM usuario u
              INNER JOIN previas p ON u.DNI_U = p.DNI_U
              INNER JOIN tipo_previa tp ON p.ID_tp = tp.ID_tp
              WHERE u.id_curso = '$curso_id'
              AND u.ID_rol = 3 AND u.ID_Estado = 1
              AND p.Aprobado = 0
              $where_tipo
              GROUP BY u.DNI_U, u.Nombre, u.Apellido
              HAVING COUNT(*) >= 2
              ORDER BY u.Apellido, u.Nombre";
    
    $res = mysqli_query($con, $query);
    $alumnos = [];
    while($row = mysqli_fetch_assoc($res)){
        $alumnos[] = [
            'DNI_U' => $row['DNI_U'],
            'Nombre' => $row['Nombre'],
            'Apellido' => $row['Apellido'],
            'cantidad' => $row['cantidad'],
            'tipo_previa' => $tipo ? (int)$tipo : 0
        ];
    }
    
    echo json_encode(['success' => true, 'alumnos' => $alumnos]);
    exit();
}

// ============================================
// 13. DETALLE DE PREVIAS DE UN ALUMNO
// ============================================
if($action == 'detalle_previas_alumno'){
    $dni = isset($_POST['dni']) ? $_POST['dni'] : 0;
    
    if(!$dni){
        echo json_encode(['success' => false, 'message' => 'DNI no valido']);
        exit();
    }
    
    $query = "SELECT 
                p.ID_previas,
                p.ID_tp,
                p.Aprobado,
                tp.nom_tp as tipo_previa,
                CASE 
                    WHEN p.ID_materia IS NOT NULL THEN m.Nom_materia
                    WHEN p.ID_taller IS NOT NULL THEN CONCAT('Taller: ', t.nombre)
                END as nombre_item,
                CASE 
                    WHEN p.ID_materia IS NOT NULL THEN (
                        SELECT curso FROM curso WHERE ID_curso = (
                            SELECT id_curso FROM materia WHERE ID_materia = p.ID_materia
                        )
                    )
                    WHEN p.ID_taller IS NOT NULL THEN 
                        CASE WHEN t.anio_taller = 'I' THEN 1 ELSE 2 END
                END as anio_item
              FROM previas p
              INNER JOIN tipo_previa tp ON p.ID_tp = tp.ID_tp
              LEFT JOIN materia m ON p.ID_materia = m.ID_materia
              LEFT JOIN talleres t ON p.ID_taller = t.ID_taller
              WHERE p.DNI_U = '$dni'
              AND p.Aprobado = 0
              ORDER BY p.ID_tp, anio_item, nombre_item";
    
    $res = mysqli_query($con, $query);
    $previas = [];
    while($row = mysqli_fetch_assoc($res)){
        $previas[] = $row;
    }
    
    echo json_encode(['success' => true, 'previas' => $previas]);
    exit();
}

// ============================================
// 14. TOP 10 MATERIAS CON MAS DESAPROBADOS
// ============================================
if($action == 'top_materias_desaprobados'){
    $anio = isset($_POST['anio']) ? $_POST['anio'] : 0;
    $trimestre = isset($_POST['trimestre']) ? $_POST['trimestre'] : 0;
    
    $where_anio = "";
    if($anio > 0){
        $where_anio = " AND c.curso = '$anio'";
    }
    
    $where_trim = "";
    if($trimestre > 0){
        $where_trim = " AND cal.trimestre = '$trimestre'";
    }
    
    $query = "SELECT 
                m.Nom_materia,
                COUNT(DISTINCT cal.id_alumno) as desaprobados
              FROM calificaciones cal
              INNER JOIN materia m ON cal.id_materia = m.ID_materia
              INNER JOIN curso c ON m.id_curso = c.ID_curso
              INNER JOIN usuario u ON cal.id_alumno = u.DNI_U
              WHERE u.ID_rol = 3 
              AND u.ID_Estado = 1
              AND cal.nota < 6
              AND m.Nom_materia NOT LIKE '%Taller%'
              AND m.Nom_materia NOT LIKE '%Tutor%'
              AND m.Nom_materia NOT LIKE '%Tutorias%'
              $where_anio
              $where_trim
              GROUP BY m.ID_materia, m.Nom_materia
              ORDER BY desaprobados DESC
              LIMIT 10";
    
    $res = mysqli_query($con, $query);
    $materias = [];
    $desaprobados = [];
    $total_desaprobados = 0;
    
    while($row = mysqli_fetch_assoc($res)){
        $materias[] = $row['Nom_materia'];
        $desaprobados[] = (int)$row['desaprobados'];
        $total_desaprobados += (int)$row['desaprobados'];
    }
    
    echo json_encode([
        'materias' => $materias,
        'desaprobados' => $desaprobados,
        'total_desaprobados' => $total_desaprobados
    ]);
    exit();
}

// ============================================
// 15. DESGRANAMIENTO ESCOLAR
// ============================================
if($action == 'desgranamiento_escolar'){
    $anio = isset($_POST['anio']) ? $_POST['anio'] : 0;
    
    $where_anio = "";
    if($anio > 0){
        $where_anio = " AND curso = '$anio'";
    }
    
    // Obtener todos los cursos
    $cursos_query = "SELECT ID_curso, curso, division, turno 
                     FROM curso 
                     WHERE 1=1 $where_anio
                     ORDER BY curso, division";
    $res_cursos = mysqli_query($con, $cursos_query);
    
    $cursos = [];
    $alumnos_iniciales = [];
    $alumnos_actuales = [];
    $porcentaje_desgranamiento = [];
    
    while($curso = mysqli_fetch_assoc($res_cursos)){
        $curso_id = $curso['ID_curso'];
        $turno_texto = $curso['turno'] == 'M' ? 'Mañana' : 'Tarde';
        $nombre = $curso['curso'] . '° "' . $curso['division'] . '" (' . $turno_texto . ')';
        
        // Alumnos actuales (activos)
        $query_actuales = "SELECT COUNT(*) as total FROM usuario 
                           WHERE id_curso = '$curso_id' 
                           AND ID_rol = 3 
                           AND ID_Estado = 1";
        $res_actuales = mysqli_query($con, $query_actuales);
        $actuales = mysqli_fetch_assoc($res_actuales)['total'] ?? 0;
        
        // Alumnos que comenzaron el ciclo (todos los que estuvieron en el curso, incluyendo egresados y dados de baja)
        // Asumimos que los que están en estado 1 (Activo) o 5 (Egresado) o 6 (De baja) son los que comenzaron
        $query_iniciales = "SELECT COUNT(*) as total FROM usuario 
                            WHERE id_curso = '$curso_id' 
                            AND ID_rol = 3 
                            AND ID_Estado IN (1, 5, 6)";
        $res_iniciales = mysqli_query($con, $query_iniciales);
        $iniciales = mysqli_fetch_assoc($res_iniciales)['total'] ?? 0;
        
        // Si no hay iniciales, usar actuales
        if($iniciales == 0) $iniciales = $actuales;
        
        $desgranamiento = $iniciales - $actuales;
        $porcentaje = $iniciales > 0 ? round(($desgranamiento / $iniciales) * 100) : 0;
        
        $cursos[] = $nombre;
        $alumnos_iniciales[] = $iniciales;
        $alumnos_actuales[] = $actuales;
        $porcentaje_desgranamiento[] = $porcentaje;
    }
    
    echo json_encode([
        'cursos' => $cursos,
        'alumnos_iniciales' => $alumnos_iniciales,
        'alumnos_actuales' => $alumnos_actuales,
        'porcentaje_desgranamiento' => $porcentaje_desgranamiento
    ]);
    exit();
}

// ============================================
// OBTENER MATERIAS POR CURSO
// ============================================
if($action == 'get_materias_por_curso'){
    $curso_id = isset($_POST['curso_id']) ? $_POST['curso_id'] : 0;
    
    if(!$curso_id){
        echo json_encode(['success' => false, 'message' => 'Curso no valido']);
        exit();
    }
    
    $query = "SELECT ID_materia, Nom_materia 
              FROM materia 
              WHERE id_curso = '$curso_id'
              AND Nom_materia NOT LIKE '%Taller%'
              AND Nom_materia NOT LIKE '%Tutor%'
              AND Nom_materia NOT LIKE '%Tutorias%'
              ORDER BY Nom_materia";
    $res = mysqli_query($con, $query);
    
    $materias = [];
    while($row = mysqli_fetch_assoc($res)){
        $materias[] = $row;
    }
    
    echo json_encode(['success' => true, 'materias' => $materias]);
    exit();
}

echo json_encode(['success' => false, 'message' => 'Acción no válida']);
?>