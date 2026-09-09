<?php
session_start();
header('Content-Type: application/json');

if(!isset($_SESSION['dni'])){
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit();
}

$es_admin = ($_SESSION['rol'] == 'Admin');
$es_preceptor = ($_SESSION['rol'] == 'Preceptor');

if(!$es_admin && !$es_preceptor){
    echo json_encode(['success' => false, 'error' => 'No tiene permisos para esta acción']);
    exit();
}

include '../../recursos/conexion.php';

$anio = isset($_POST['anio']) ? $_POST['anio'] : date('Y');
$tipo_envio = isset($_POST['tipo_envio']) ? $_POST['tipo_envio'] : 'final';
$preceptor_dni = $_SESSION["dni"];

// Determinar qué trimestres verificar
$trimestres_a_verificar = [];
if($tipo_envio == 'trimestre1') $trimestres_a_verificar = [1];
elseif($tipo_envio == 'trimestre2') $trimestres_a_verificar = [1,2];
elseif($tipo_envio == 'trimestre3') $trimestres_a_verificar = [1,2,3];
else $trimestres_a_verificar = [1,2,3];

// ============================================
// FUNCIÓN: Verificar si un curso es de ciclo básico (1° o 2° año)
// ============================================
function esCicloBasico($curso_numero) {
    return in_array($curso_numero, [1, 2]);
}

// ============================================
// FUNCIÓN: Cantidad de talleres requeridos por trimestre (acumulados)
// ============================================
function talleresRequeridosPorTrimestre($trimestre_numero) {
    // 1er trimestre: 2 talleres
    // 2do trimestre: 4 talleres (acumulados)
    // 3er trimestre: 6 talleres (acumulados)
    $requeridos = [
        1 => 2,
        2 => 4,
        3 => 6
    ];
    return $requeridos[$trimestre_numero] ?? 6;
}

$action = isset($_POST['action']) ? $_POST['action'] : '';

if($action == 'verificar_cursos_trimestre'){
    
    // ============================================
    // OBTENER CURSOS SEGÚN ROL
    // ============================================
    $cursos_preceptor = [];
    
    if($es_preceptor){
        $query_cursos_pre = "SELECT id_curso FROM preceptorxcurso WHERE id_preceptor = '$preceptor_dni'";
        $res_cursos_pre = mysqli_query($con, $query_cursos_pre);
        while($row = mysqli_fetch_assoc($res_cursos_pre)){
            $cursos_preceptor[] = $row['id_curso'];
        }
        
        if(empty($cursos_preceptor)){
            echo json_encode(['success' => true, 'cursos' => []]);
            exit();
        }
        
        $cursos_str = implode(',', $cursos_preceptor);
        $query_cursos = "SELECT c.ID_curso, c.curso, c.division, c.turno
                         FROM curso c
                         WHERE c.ID_curso IN ($cursos_str)
                         ORDER BY c.curso, c.division";
    } else {
        $query_cursos = "SELECT c.ID_curso, c.curso, c.division, c.turno
                         FROM curso c
                         ORDER BY c.curso, c.division";
    }
    
    $res_cursos = mysqli_query($con, $query_cursos);
    
    // Obtener todas las rotaciones del año
    $query_rotaciones = "SELECT ID_rotacion, numero_rotacion FROM rotaciones WHERE anio = '$anio' AND activo = 1 ORDER BY numero_rotacion";
    $res_rotaciones = mysqli_query($con, $query_rotaciones);
    $rotaciones = [];
    while($row = mysqli_fetch_assoc($res_rotaciones)){
        $rotaciones[$row['numero_rotacion']] = $row['ID_rotacion'];
    }
    
    // Obtener grupos de taller para ciclo básico
    $grupos_taller = [];
    $query_grupos = "SELECT ID_alumno, numero_grupo, ID_curso as curso_taller FROM alumno_grupo_taller WHERE anio = '$anio'";
    $res_grupos = mysqli_query($con, $query_grupos);
    while($row = mysqli_fetch_assoc($res_grupos)){
        $grupos_taller[$row['ID_alumno']] = [
            'numero_grupo' => $row['numero_grupo'],
            'curso_taller' => $row['curso_taller']
        ];
    }
    
    $cursos = [];
    while($curso = mysqli_fetch_assoc($res_cursos)){
        
        $curso_numero = $curso['curso'];
        $es_basico = esCicloBasico($curso_numero);
        
        // Obtener alumnos del curso (activos)
        $query_alumnos = "SELECT COUNT(*) as total FROM usuario 
                          WHERE id_curso = '{$curso['ID_curso']}' 
                          AND ID_rol = 3 AND ID_Estado = 1";
        $res_alumnos = mysqli_query($con, $query_alumnos);
        $total_alumnos = mysqli_fetch_assoc($res_alumnos)['total'];
        
        if($total_alumnos == 0) continue;
        
        // Obtener materias del curso (EXCLUYENDO "Taller" y "Tutorías")
        $query_materias = "SELECT ID_materia FROM materia 
                          WHERE id_curso = '{$curso['ID_curso']}' 
                          AND Nom_materia NOT LIKE '%Taller%'
                          AND Nom_materia NOT LIKE '%Tutor%'
                          AND Nom_materia NOT LIKE '%Tutorías%'";
        $res_materias = mysqli_query($con, $query_materias);
        $materias = [];
        while($m = mysqli_fetch_assoc($res_materias)){
            $materias[] = $m['ID_materia'];
        }
        
        // Contar alumnos con calificaciones completas
        $alumnos_completos = 0;
        $t1_completo = false;
        $t2_completo = false;
        $t3_completo = false;
        
        $query_alumnos_lista = "SELECT DNI_U as id FROM usuario 
                                WHERE id_curso = '{$curso['ID_curso']}' 
                                AND ID_rol = 3 AND ID_Estado = 1";
        $res_alumnos_lista = mysqli_query($con, $query_alumnos_lista);
        
        while($alumno = mysqli_fetch_assoc($res_alumnos_lista)){
            $alumno_id = $alumno['id'];
            $completo = true;
            $alumno_t1_completo = true;
            $alumno_t2_completo = true;
            $alumno_t3_completo = true;
            
            // ============================================
            // 1. VERIFICAR MATERIAS (para TODOS los cursos)
            // ============================================
            foreach($trimestres_a_verificar as $trimestre){
                foreach($materias as $id_materia){
                    $query_notas = "SELECT COUNT(*) as total_notas FROM calificaciones 
                                    WHERE id_alumno = '$alumno_id' 
                                    AND id_materia = '$id_materia' 
                                    AND trimestre = '$trimestre'";
                    $res_notas = mysqli_query($con, $query_notas);
                    $total_notas = mysqli_fetch_assoc($res_notas)['total_notas'];
                    
                    if($total_notas < 3){
                        if($trimestre == 1) $alumno_t1_completo = false;
                        if($trimestre == 2) $alumno_t2_completo = false;
                        if($trimestre == 3) $alumno_t3_completo = false;
                        $completo = false;
                    }
                }
            }
            
            // ============================================
            // 2. VERIFICAR TALLERES (SOLO para ciclo básico 1° y 2°)
            // ============================================
            if($es_basico && isset($grupos_taller[$alumno_id])){
                $grupo = $grupos_taller[$alumno_id];
                $numero_grupo = $grupo['numero_grupo'];
                $curso_taller = $grupo['curso_taller'];
                
                // Para cada trimestre, contar talleres con promedio NO NULL
                foreach($trimestres_a_verificar as $trimestre){
                    $talleres_necesarios = talleresRequeridosPorTrimestre($trimestre);
                    $talleres_aprobados = 0;
                    
                    // Determinar rotaciones según el trimestre
                    $rotaciones_trimestre = [];
                    if($trimestre == 1){
                        $rotaciones_trimestre = [1, 2];
                    } elseif($trimestre == 2){
                        $rotaciones_trimestre = [1, 2, 3, 4];
                    } else {
                        $rotaciones_trimestre = [1, 2, 3, 4, 5, 6];
                    }
                    
                    foreach($rotaciones_trimestre as $num_rotacion){
                        if(isset($rotaciones[$num_rotacion])){
                            $id_rotacion = $rotaciones[$num_rotacion];
                            
                            // Verificar si el alumno tiene calificación en este taller (promedio NO NULL)
                            $query_taller = "SELECT ct.promedio 
                                             FROM calificaciones_taller ct
                                             INNER JOIN planilla_rotaciones pr ON ct.ID_taller = pr.ID_taller 
                                                AND ct.ID_rotacion = pr.ID_rotacion
                                             WHERE ct.ID_alumno = '$alumno_id'
                                             AND pr.ID_curso = '$curso_taller'
                                             AND pr.numero_grupo = '$numero_grupo'
                                             AND ct.ID_rotacion = '$id_rotacion'
                                             AND ct.promedio IS NOT NULL";
                            $res_taller = mysqli_query($con, $query_taller);
                            
                            if(mysqli_num_rows($res_taller) > 0){
                                $talleres_aprobados++;
                            }
                        }
                    }
                    
                    if($talleres_aprobados < $talleres_necesarios){
                        if($trimestre == 1) $alumno_t1_completo = false;
                        if($trimestre == 2) $alumno_t2_completo = false;
                        if($trimestre == 3) $alumno_t3_completo = false;
                        $completo = false;
                    }
                }
            } elseif($es_basico && !isset($grupos_taller[$alumno_id])){
                // Si es ciclo básico pero no tiene grupo de taller asignado, no está completo
                $completo = false;
                $alumno_t1_completo = false;
                $alumno_t2_completo = false;
                $alumno_t3_completo = false;
            }
            
            // Actualizar estado de trimestres del curso
            if($alumno_t1_completo) $t1_completo = true;
            if($alumno_t2_completo) $t2_completo = true;
            if($alumno_t3_completo) $t3_completo = true;
            
            if($completo){
                $alumnos_completos++;
            }
        }
        
        // Determinar si el curso está completo
        $curso_completo = ($alumnos_completos == $total_alumnos && $total_alumnos > 0);
        
        $cursos[] = [
            'id_curso' => $curso['ID_curso'],
            'curso' => $curso['curso'],
            'division' => $curso['division'],
            'turno' => $curso['turno'],
            'anio' => $anio,
            'total_alumnos' => $total_alumnos,
            'alumnos_completos' => $alumnos_completos,
            'completo' => $curso_completo,
            'parcial' => ($alumnos_completos > 0 && !$curso_completo),
            'trimestres' => [
                't1' => $t1_completo,
                't2' => $t2_completo,
                't3' => $t3_completo
            ],
            'es_basico' => $es_basico
        ];
    }
    
    echo json_encode(['success' => true, 'cursos' => $cursos]);
    exit();
}

echo json_encode(['success' => false, 'error' => 'Acción no válida']);
?>