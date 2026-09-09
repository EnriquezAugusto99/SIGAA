<?php
session_start();
header('Content-Type: application/json');

if(isset($_SESSION["dni"]) && (time() - $_SESSION["ultima_actividad"] > 18000)){
    echo json_encode(['success' => false, 'message' => 'Sesión expirada']);
    exit();
}

if(!isset($_SESSION["dni"])){
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit();
}

if($_SESSION['rol'] != 'Admin' && $_SESSION['rol'] != 'Preceptor'){
    echo json_encode(['success' => false, 'message' => 'No tiene permisos']);
    exit();
}

include '../../recursos/conexion.php';

$action = $_POST['action'] ?? '';

// ============================================
// VERIFICAR NOTAS POR CURSO
// ============================================
if($action == 'verificar_notas_curso'){
    $curso_id = $_POST['curso_id'] ?? 0;
    $trimestre = $_POST['trimestre'] ?? 1;
    $anio = $_POST['anio'] ?? date('Y');
    
    if(!$curso_id){
        echo json_encode(['success' => false, 'message' => 'Curso no válido']);
        exit();
    }
    
    // Información del curso
    $query_curso = "SELECT curso, division, turno FROM curso WHERE ID_curso = '$curso_id'";
    $res_curso = mysqli_query($con, $query_curso);
    $curso = mysqli_fetch_assoc($res_curso);
    $turno_texto = $curso['turno'] == 'M' ? 'Mañana' : 'Tarde';
    $curso_nombre = $curso['curso'] . '° "' . $curso['division'] . '" - ' . $turno_texto;
    
    // Materias (excluyendo Taller y Tutorías)
    $query_materias = "SELECT ID_materia, Nom_materia 
                       FROM materia 
                       WHERE id_curso = '$curso_id' 
                       AND Nom_materia NOT LIKE '%Taller%'
                       AND Nom_materia NOT LIKE '%Tutor%'
                       ORDER BY Nom_materia";
    $res_materias = mysqli_query($con, $query_materias);
    $materias = [];
    while($row = mysqli_fetch_assoc($res_materias)){
        $materias[] = [
            'id' => $row['ID_materia'],
            'nombre' => $row['Nom_materia']
        ];
    }
    
    // Alumnos
    $query_alumnos = "SELECT DNI_U, Nombre, Apellido 
                      FROM usuario 
                      WHERE id_curso = '$curso_id' 
                      AND ID_rol = 3 
                      AND ID_Estado = 1
                      ORDER BY Apellido, Nombre";
    $res_alumnos = mysqli_query($con, $query_alumnos);
    $alumnos = [];
    while($row = mysqli_fetch_assoc($res_alumnos)){
        $alumnos[$row['DNI_U']] = [
            'dni' => $row['DNI_U'],
            'nombre' => $row['Nombre'],
            'apellido' => $row['Apellido'],
            'notas' => []
        ];
    }
    
    // Contar notas por alumno y materia
    foreach($alumnos as $dni => $alumno){
        foreach($materias as $materia){
            $query_notas = "SELECT COUNT(*) as cantidad 
                            FROM calificaciones 
                            WHERE id_alumno = '$dni' 
                            AND id_materia = '{$materia['id']}' 
                            AND trimestre = '$trimestre'
                            AND YEAR(fecha) = '$anio'";
            $res_notas = mysqli_query($con, $query_notas);
            $row = mysqli_fetch_assoc($res_notas);
            $cantidad = $row['cantidad'];
            
            $alumnos[$dni]['notas'][$materia['id']] = [
                'tiene_notas' => $cantidad >= 3,
                'cantidad' => $cantidad
            ];
        }
    }
    
    // Contar completos
    $alumnos_completos = 0;
    $alumnos_incompletos = 0;
    $total_materias = count($materias);
    
    foreach($alumnos as $alumno){
        $materias_completas = 0;
        foreach($alumno['notas'] as $nota){
            if($nota['tiene_notas']){
                $materias_completas++;
            }
        }
        if($materias_completas == $total_materias && $total_materias > 0){
            $alumnos_completos++;
        } else {
            $alumnos_incompletos++;
        }
    }
    
    echo json_encode([
        'success' => true,
        'curso_nombre' => $curso_nombre,
        'total_alumnos' => count($alumnos),
        'alumnos_completos' => $alumnos_completos,
        'alumnos_incompletos' => $alumnos_incompletos,
        'materias' => $materias,
        'alumnos' => array_values($alumnos)
    ]);
    exit();
}

// ============================================
// OBTENER PROFESORES DE UNA MATERIA
// ============================================
if($action == 'obtener_profesores_materia'){
    $materia_id = $_POST['materia_id'] ?? 0;
    $curso_id = $_POST['curso_id'] ?? 0;
    
    if(!$materia_id){
        echo json_encode(['success' => false, 'message' => 'Materia no válida']);
        exit();
    }
    
    $query_profesores = "SELECT u.DNI_U, u.Nombre, u.Apellido
                         FROM usuario u
                         INNER JOIN docentemateriacurso dmc ON u.DNI_U = dmc.id_docente
                         WHERE dmc.id_materia = '$materia_id'
                         AND u.ID_rol = 2
                         AND u.ID_Estado = 1";
    
    if($curso_id){
        $query_profesores .= " AND dmc.id_curso = '$curso_id'";
    }
    
    $query_profesores .= " GROUP BY u.DNI_U ORDER BY u.Apellido";
    
    $res_profesores = mysqli_query($con, $query_profesores);
    $profesores = [];
    
    while($row = mysqli_fetch_assoc($res_profesores)){
        $profesores[] = [
            'dni' => $row['DNI_U'],
            'nombre' => $row['Nombre'],
            'apellido' => $row['Apellido']
        ];
    }
    
    echo json_encode([
        'success' => true,
        'profesores' => $profesores
    ]);
    exit();
}

echo json_encode(['success' => false, 'message' => 'Acción no válida']);
?>