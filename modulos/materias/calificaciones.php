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

// Verificar permisos (solo Admin, Preceptor, Secretario y Profesor)
$es_admin = ($_SESSION['rol'] == 'Admin');
$es_preceptor = ($_SESSION['rol'] == 'Preceptor');
$es_secretario = ($_SESSION['rol'] == 'Secretario');
$es_profesor = ($_SESSION['rol'] == 'Profesor');

if(!$es_admin && !$es_preceptor && !$es_secretario && !$es_profesor){
    echo '<script>alert("No tiene permisos para gestionar calificaciones"); window.location="../../recursos/panel.php";</script>';
    exit();
}

include '../../recursos/conexion.php';

$docente_dni = $_SESSION["dni"];
$error = '';
$success = '';

// Obtener materias según el rol
$materias_docente = [];

if($es_admin){
    // Admin ve TODAS las materias de todos los cursos
    $query_materias = "SELECT DISTINCT m.ID_materia, m.Nom_materia, c.curso, c.division, c.turno 
                        FROM materia m 
                        INNER JOIN curso c ON m.id_curso = c.ID_curso 
                        ORDER BY c.curso, c.division, m.Nom_materia";
} elseif($es_preceptor) {
    // Preceptor: ve TODAS las materias de los cursos que tiene asignados
    $query_materias = "SELECT DISTINCT m.ID_materia, m.Nom_materia, c.curso, c.division, c.turno 
                        FROM materia m 
                        INNER JOIN curso c ON m.id_curso = c.ID_curso 
                        INNER JOIN preceptorxcurso pc ON c.ID_curso = pc.id_curso 
                        WHERE pc.id_preceptor = '$docente_dni' 
                        ORDER BY c.curso, c.division, m.Nom_materia";
} else {
    // Para Profesor y Secretario: solo las materias que dicta
    $query_materias = "SELECT DISTINCT m.ID_materia, m.Nom_materia, c.curso, c.division, c.turno 
                        FROM materia m 
                        INNER JOIN curso c ON m.id_curso = c.ID_curso 
                        INNER JOIN docentemateriacurso dmc ON m.ID_materia = dmc.id_materia 
                        WHERE dmc.id_docente = '$docente_dni' 
                        ORDER BY c.curso, c.division, m.Nom_materia";
}

$res_materias = mysqli_query($con, $query_materias);

// Generar token CSRF si no existe
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if(mysqli_num_rows($res_materias) > 0){
    while($fila = mysqli_fetch_array($res_materias)){
        $materias_docente[] = $fila;
    }
}

// Obtener materia seleccionada
$materia_seleccionada = $_GET['materia_id'] ?? '';
$curso_info = null;
$alumnos = [];
$calificaciones = [];

if($materia_seleccionada){
    // Verificar permisos sobre la materia
   // Verificar permisos sobre la materia
$tiene_permiso = false;

if($es_admin){
    $tiene_permiso = true; // Admin tiene permiso para todo
} elseif($es_preceptor) {
    // Preceptor: verificar si tiene asignado el curso de esa materia
    $query_permiso = "SELECT pc.* 
                      FROM preceptorxcurso pc 
                      INNER JOIN materia m ON m.id_curso = pc.id_curso 
                      WHERE pc.id_preceptor = '$docente_dni' 
                      AND m.ID_materia = '$materia_seleccionada'";
    $res_permiso = mysqli_query($con, $query_permiso);
    $tiene_permiso = (mysqli_num_rows($res_permiso) > 0);
} else {
    // Profesor y Secretario: verificar si dicta la materia
    $query_permiso = "SELECT * FROM docentemateriacurso WHERE id_docente = '$docente_dni' AND id_materia = '$materia_seleccionada'";
    $res_permiso = mysqli_query($con, $query_permiso);
    $tiene_permiso = (mysqli_num_rows($res_permiso) > 0);
}
    
    if($tiene_permiso){
        // Obtener información del curso
        $query_curso = "SELECT c.curso, c.division, c.turno, m.Nom_materia
                        FROM materia m
                        INNER JOIN curso c ON m.id_curso = c.ID_curso
                        WHERE m.ID_materia = '$materia_seleccionada'";
        $res_curso = mysqli_query($con, $query_curso);
        $curso_info = mysqli_fetch_array($res_curso);
        
        // Obtener alumnos del curso (solo activos, ID_Estado = 1)
        $query_alumnos = "SELECT u.DNI_U, u.Nombre, u.Apellido
                          FROM usuario u
                          WHERE u.id_curso = (SELECT id_curso FROM materia WHERE ID_materia = '$materia_seleccionada')
                          AND u.ID_rol = 3
                          AND u.ID_Estado = 1
                          ORDER BY u.Apellido, u.Nombre";
        $res_alumnos = mysqli_query($con, $query_alumnos);
        while($fila = mysqli_fetch_array($res_alumnos)){
            $alumnos[] = $fila;
        }
        
        // Obtener calificaciones existentes para esta materia
        $query_calif = "SELECT id_alumno, trimestre, nota 
                        FROM calificaciones 
                        WHERE id_materia = '$materia_seleccionada'
                        ORDER BY id_alumno, trimestre";
        $res_calif = mysqli_query($con, $query_calif);
        while($fila = mysqli_fetch_array($res_calif)){
            $calificaciones[$fila['id_alumno']][$fila['trimestre']][] = round($fila['nota'], 2);
        }
        
        // Obtener calificaciones de diciembre y febrero-marzo (trimestre 4 y 5)
        $calificaciones_especiales = [];
        $query_especiales = "SELECT id_alumno, trimestre, nota 
                             FROM calificaciones 
                             WHERE id_materia = '$materia_seleccionada' AND trimestre IN (4,5)
                             ORDER BY id_alumno, trimestre";
        $res_especiales = mysqli_query($con, $query_especiales);
        while($fila = mysqli_fetch_array($res_especiales)){
            $calificaciones_especiales[$fila['id_alumno']][$fila['trimestre']] = $fila['nota'];
        }
    } else {
        $error = "No tiene permisos para acceder a esta materia.";
    }
}

// Procesar guardado de calificaciones (para los 3 trimestres simultáneamente)
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['guardar_calificaciones'])){
    $materia_id = $_POST['materia_id'];
    
    // Verificar permisos nuevamente
$tiene_permiso = false;
if($es_admin){
    $tiene_permiso = true;
} elseif($es_preceptor) {
    // Preceptor: verificar si tiene asignado el curso de esa materia
    $query_permiso = "SELECT pc.* 
                      FROM preceptorxcurso pc 
                      INNER JOIN materia m ON m.id_curso = pc.id_curso 
                      WHERE pc.id_preceptor = '$docente_dni' 
                      AND m.ID_materia = '$materia_id'";
    $res_permiso = mysqli_query($con, $query_permiso);
    $tiene_permiso = (mysqli_num_rows($res_permiso) > 0);
} else {
    // Profesor y Secretario: verificar si dicta la materia
    $query_permiso = "SELECT * FROM docentemateriacurso WHERE id_docente = '$docente_dni' AND id_materia = '$materia_id'";
    $res_permiso = mysqli_query($con, $query_permiso);
    $tiene_permiso = (mysqli_num_rows($res_permiso) > 0);
}
    
    if($tiene_permiso){
        $total_insertados = 0;
        $errores_validacion = [];
        
        // Procesar cada trimestre
        for($trimestre = 1; $trimestre <= 3; $trimestre++){
            $campo_trimestre = "calificaciones_t{$trimestre}";
            if(isset($_POST[$campo_trimestre]) && is_array($_POST[$campo_trimestre])){
                
                // Eliminar calificaciones existentes para este trimestre y materia
                $query_delete = "DELETE FROM calificaciones WHERE id_materia = '$materia_id' AND trimestre = '$trimestre'";
                mysqli_query($con, $query_delete);
                
                // Insertar nuevas calificaciones
                foreach($_POST[$campo_trimestre] as $alumno_id => $notas){
                    if(is_array($notas)){
                        $notas_validas = [];
                        foreach($notas as $nota){
                            if(!empty(trim($nota)) && is_numeric($nota)){
                                $nota_valor = round(floatval($nota), 2);
                                if($nota_valor >= 1 && $nota_valor <= 10){
                                    $notas_validas[] = $nota_valor;
                                }
                            }
                        }
                        
                        // Validar mínimo 3 notas si hay al menos una nota ingresada
                        if(count($notas_validas) > 0 && count($notas_validas) < 3){
                            // Buscar nombre del alumno para el mensaje de error
                            $nombre_alumno = '';
                            foreach($alumnos as $alumno){
                                if($alumno['DNI_U'] == $alumno_id){
                                    $nombre_alumno = $alumno['Apellido'] . ', ' . $alumno['Nombre'];
                                    break;
                                }
                            }
                            $errores_validacion[] = "El alumno $nombre_alumno tiene menos de 3 notas en el " . ($trimestre == 1 ? '1er' : ($trimestre == 2 ? '2do' : '3er')) . " trimestre.";
                        }
                        
                        // Insertar notas válidas
                        foreach($notas_validas as $nota_valor){
                            $query_insert = "INSERT INTO calificaciones (id_alumno, id_materia, trimestre, nota, fecha) 
                                            VALUES ('$alumno_id', '$materia_id', '$trimestre', '$nota_valor', CURDATE())";
                            if(mysqli_query($con, $query_insert)){
                                $total_insertados++;
                            }
                        }
                    }
                }
            }
        }
        // Procesar diciembre (trimestre 4)
        if(isset($_POST['calificaciones_t4']) && is_array($_POST['calificaciones_t4'])){
            // Eliminar calificaciones existentes para diciembre
            $query_delete = "DELETE FROM calificaciones WHERE id_materia = '$materia_id' AND trimestre = 4";
            mysqli_query($con, $query_delete);
            
            foreach($_POST['calificaciones_t4'] as $alumno_id => $nota){
                if(!empty(trim($nota))){
                    $nota_upper = strtoupper(trim($nota));
                    if($nota_upper == 'AUS'){
                        $nota_valor = -1;
                        $query_insert = "INSERT INTO calificaciones (id_alumno, id_materia, trimestre, nota, fecha) 
                                        VALUES ('$alumno_id', '$materia_id', 4, '$nota_valor', CURDATE())";
                        mysqli_query($con, $query_insert);
                    } elseif(is_numeric($nota)){
                        $nota_valor = round(floatval($nota), 2);
                        if($nota_valor >= 1 && $nota_valor <= 10){
                            $query_insert = "INSERT INTO calificaciones (id_alumno, id_materia, trimestre, nota, fecha) 
                                            VALUES ('$alumno_id', '$materia_id', 4, '$nota_valor', CURDATE())";
                            mysqli_query($con, $query_insert);
                        }
                    }
                }
            }
        }
        
        // Procesar febrero-marzo (trimestre 5)
        if(isset($_POST['calificaciones_t5']) && is_array($_POST['calificaciones_t5'])){
            // Eliminar calificaciones existentes para febrero-marzo
            $query_delete = "DELETE FROM calificaciones WHERE id_materia = '$materia_id' AND trimestre = 5";
            mysqli_query($con, $query_delete);
            
            foreach($_POST['calificaciones_t5'] as $alumno_id => $nota){
                if(!empty(trim($nota))){
                    $nota_upper = strtoupper(trim($nota));
                    if($nota_upper == 'AUS'){
                        $nota_valor = -1;
                        $query_insert = "INSERT INTO calificaciones (id_alumno, id_materia, trimestre, nota, fecha) 
                                        VALUES ('$alumno_id', '$materia_id', 5, '$nota_valor', CURDATE())";
                        mysqli_query($con, $query_insert);
                    } elseif(is_numeric($nota)){
                        $nota_valor = round(floatval($nota), 2);
                        if($nota_valor >= 1 && $nota_valor <= 10){
                            $query_insert = "INSERT INTO calificaciones (id_alumno, id_materia, trimestre, nota, fecha) 
                                            VALUES ('$alumno_id', '$materia_id', 5, '$nota_valor', CURDATE())";
                            mysqli_query($con, $query_insert);
                        }
                    }
                }
            }
        }
        
        if(count($errores_validacion) > 0){
            $error = implode("<br>", $errores_validacion);
        } else {
            $success = "Calificaciones guardadas correctamente. ($total_insertados registros)";
        }
        
    } else {
        $error = "No tiene permisos para modificar calificaciones de esta materia.";
    }
    
    // Recargar calificaciones después de guardar
    $calificaciones = [];
    $query_calif = "SELECT id_alumno, trimestre, nota 
                    FROM calificaciones 
                    WHERE id_materia = '$materia_seleccionada'
                    ORDER BY id_alumno, trimestre";
    $res_calif = mysqli_query($con, $query_calif);
    while($fila = mysqli_fetch_array($res_calif)){
        $calificaciones[$fila['id_alumno']][$fila['trimestre']][] = round($fila['nota'], 2);
    }
    
    $calificaciones_especiales = [];
    $query_especiales = "SELECT id_alumno, trimestre, nota 
                         FROM calificaciones 
                         WHERE id_materia = '$materia_seleccionada' AND trimestre IN (4,5)
                         ORDER BY id_alumno, trimestre";
    $res_especiales = mysqli_query($con, $query_especiales);
    while($fila = mysqli_fetch_array($res_especiales)){
        $calificaciones_especiales[$fila['id_alumno']][$fila['trimestre']] = $fila['nota'];
    }
}

function calcularPromedio($notas) {
    if(empty($notas) || count($notas) < 3){
        return null;
    }
    $suma = 0;
    foreach($notas as $n){
        $suma += $n;
    }
    $promedio = $suma / count($notas);
    
    // Agregar un epsilon para evitar errores de precisión
    $epsilon = 0.0000001;
    $promedio_ajustado = $promedio + $epsilon;
    
    // Truncar a 2 decimales
    $resultado = floor($promedio_ajustado * 100) / 100;
    
    // Si el resultado es 7.739999... por algún motivo, ajustar
    $resultado = round($resultado, 2);
    
    return $resultado;
}

function getPromedioClass($promedio) {
    if($promedio === null) return '';
    return $promedio >= 6 ? 'aprobado' : 'desaprobado';
}


$dias_restantes = null;
$trimestre_actual_nombre = '';
$trimestre_actual_num = 0;
$fecha_actual = new DateTime();
$anio = (int)$fecha_actual->format('Y');

$inicioT1 = new DateTime("$anio-03-02");
$finT1    = new DateTime("$anio-06-15");
$inicioT2 = new DateTime("$anio-06-08");
$finT2    = new DateTime("$anio-09-18");
$inicioT3 = new DateTime("$anio-09-22");
$finT3    = new DateTime("$anio-12-04");

if($fecha_actual >= $inicioT1 && $fecha_actual <= $finT1) {
    $trimestre_actual_nombre = '1er Trimestre';
    $trimestre_actual_num = 1;
    $fecha_fin = $finT1;
} elseif($fecha_actual >= $inicioT2 && $fecha_actual <= $finT2) {
    $trimestre_actual_nombre = '2do Trimestre';
    $trimestre_actual_num = 2;
    $fecha_fin = $finT2;
} elseif($fecha_actual >= $inicioT3 && $fecha_actual <= $finT3) {
    $trimestre_actual_nombre = '3er Trimestre';
    $trimestre_actual_num = 3;
    $fecha_fin = $finT3;
} else {
    // Determinar si es antes del primer trimestre o después del último
    if($fecha_actual < $inicioT1) {
        $trimestre_actual_nombre = '1er Trimestre';
        $trimestre_actual_num = 1;
        $dias_restantes = 'próximamente';
    } elseif($fecha_actual < $inicioT2) {
        $trimestre_actual_nombre = '2do Trimestre';
        $trimestre_actual_num = 2;
        $dias_restantes = 'próximamente';
    } elseif($fecha_actual < $inicioT3) {
        $trimestre_actual_nombre = '3er Trimestre';
        $trimestre_actual_num = 3;
        $dias_restantes = 'próximamente';
    } else {
        $dias_restantes = 'ciclo lectivo finalizado';
    }
}

if(isset($fecha_fin) && $dias_restantes === null) {
    $intervalo = $fecha_actual->diff($fecha_fin);
    $dias_restantes = $intervalo->days;
}

?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <link rel="stylesheet" href="../../recursos/styles.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.28/jspdf.plugin.autotable.min.js"></script>
    <title>Planilla de Calificaciones</title>
    <style>
        * {
            box-sizing: border-box;
        }
        
        body {
            background: #f5f5f5;
            font-family: 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            margin: 0;
            padding: 20px;
            color: #2c2c2c;
        }
        
        .container {
            max-width: 98%;
            margin: 0 auto;
            padding: 25px 30px;
            background: #ffffff;
            color: #2c2c2c;
            overflow-x: auto;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            border: 1px solid #e0e0e0;
        }
        
        h1, h2, h3 {
            font-weight: 600;
            letter-spacing: 0.5px;
            margin-top: 0;
            color: #7a0000;
        }
        
        h1 {
            font-size: 1.8em;
            margin-bottom: 25px;
            border-bottom: 2px solid #e0e0e0;
            padding-bottom: 12px;
        }
        
        h2 {
            font-size: 1.3em;
            margin-bottom: 15px;
        }
        
        .selector-materia {
            margin-bottom: 30px;
        }
        
        .materias-grid {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin-top: 15px;
        }
        
        .materia-card {
            background: #fdf5f5;
            padding: 16px 20px;
            border-radius: 10px;
            text-decoration: none;
            color: #2c2c2c;
            transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
            border: 1px solid #e0e0e0;
            border-left: 5px solid #7a0000;
            min-width: 200px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            position: relative;
            overflow: hidden;
        }
        
        .materia-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 25px rgba(122,0,0,0.15);
            border-color: #b71c1c;
            background: #ffffff;
        }
        
        .materia-card.active {
            background: #7a0000;
            color: #ffffff;
            border-color: #7a0000;
            border-left: 5px solid #b71c1c;
            box-shadow: 0 6px 15px rgba(122,0,0,0.2);
        }
        
        .materia-card.active h3, .materia-card.active p {
            color: #ffffff;
        }
        
        .materia-card h3 {
            margin: 0 0 8px 0;
            font-size: 1.05em;
            color: #7a0000;
        }
        
        .materia-card p {
            margin: 0;
            font-size: 0.85em;
            opacity: 0.85;
        }
        
        .info-header {
            background: #ffffff;
            padding: 22px;
            border-radius: 10px;
            margin-bottom: 25px;
            border-left: 5px solid #7a0000;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            border-top: 1px solid #e0e0e0;
            border-right: 1px solid #e0e0e0;
            border-bottom: 1px solid #e0e0e0;
        }
        
        .info-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 12px;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .info-item {
            font-size: 0.95em;
            color: #555555;
        }
        
        .info-item strong {
            color: #2c2c2c;
            font-weight: 600;
        }
        
        .orientacion {
            font-size: 1.15em;
            font-weight: 700;
            text-transform: uppercase;
            margin: 18px 0 12px 0;
            color: #7a0000;
            letter-spacing: 1px;
        }
        
        .tabla-calificaciones {
            overflow-x: auto;
            margin-top: 20px;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
        }
        
        table {
            width: 100%;
            min-width: 1300px;
            border-collapse: separate;
            border-spacing: 0;
            background: #ffffff;
            overflow: hidden;
            font-size: 0.85em;
            border: 1px solid #e0e0e0;
        }
        
        th, td {
            border-bottom: 1px solid #e0e0e0;
            padding: 12px 8px;
            text-align: center;
            vertical-align: middle;
            color: #2c2c2c;
        }
        
        tr:last-child td {
            border-bottom: none;
        }
        
        th {
            background: #7a0000;
            color: white;
            font-weight: 600;
            padding: 14px 8px;
            text-transform: uppercase;
            font-size: 0.85em;
            letter-spacing: 0.5px;
            border-bottom: 2px solid #8b0000;
        }
        
        tr:hover td {
            background: #fdf5f5;
        }
        
        .alumno-nombre {
            text-align: left;
            min-width: 180px;
            font-size: 0.9em;
            font-weight: 500;
        }
        
        .numero-col {
            width: 45px;
            text-align: center;
            color: #7a0000;
            font-weight: bold;
        }
        
        .nota-input {
            width: 55px;
            padding: 6px 4px;
            border-radius: 6px;
            border: 1px solid #cccccc;
            background: #ffffff;
            color: #2c2c2c;
            text-align: center;
            font-size: 0.9em;
            font-weight: 600;
            transition: all 0.2s ease;
            box-shadow: inset 0 1px 3px rgba(0,0,0,0.05);
        }
        
        .nota-input:focus {
            outline: none;
            border-color: #b71c1c;
            box-shadow: 0 0 0 3px rgba(183, 28, 28, 0.15), inset 0 1px 3px rgba(0,0,0,0.05);
            background: #ffffff;
        }
        
        .nota-input:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            background: #f5f5f5;
            color: #777777;
            box-shadow: none;
        }
        
        .nota-input.invalid {
            border-color: #d32f2f;
            background-color: #ffebee;
        }
        
        .promedio {
            font-weight: bold;
            font-size: 0.9em;
        }
        
        .promedio.aprobado {
            color: #2e7d32;
        }
        
        .promedio.desaprobado {
            color: #d32f2f;
        }
        
        .btn-guardar, .volver-btn {
            background: #7a0000;
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 0.95em;
            font-weight: 600;
            margin-top: 25px;
            transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
            box-shadow: 0 4px 10px rgba(122,0,0,0.2);
            letter-spacing: 0.5px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        
        .btn-guardar:hover, .volver-btn:hover {
            background: #8b0000;
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(139,0,0,0.3);
            color: white;
        }
        
        .btn-guardar:active, .volver-btn:active {
            transform: translateY(1px);
            box-shadow: 0 2px 5px rgba(122,0,0,0.2);
        }
        
        .volver-btn {
            background: #ffffff;
            color: #b71c1c;
            border: 1px solid #b71c1c;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.05);
        }
        
        .volver-btn:hover {
            background: #fdf5f5;
            border-color: #8b0000;
            color: #8b0000;
            box-shadow: 0 6px 12px rgba(183, 28, 28, 0.15);
        }
        
        .mensaje-error {
            background: rgba(211, 47, 47, 0.1);
            border-left: 4px solid #d32f2f;
            padding: 12px 15px;
            border-radius: 4px;
            margin: 15px 0;
            color: #c62828;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }
        
        .mensaje-success {
            background: rgba(46, 125, 50, 0.1);
            border-left: 4px solid #2e7d32;
            padding: 12px 15px;
            border-radius: 4px;
            margin: 15px 0;
            color: #1b5e20;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }
        
        .btn-group {
            display: flex;
            gap: 12px;
            align-items: center;
            flex-wrap: wrap;
        }
        
        .leyenda-acordeon {
            display: flex;
            gap: 20px;
            margin: 15px 0;
            padding: 10px 15px;
            background: #f9f9f9;
            border-radius: 8px;
            font-size: 0.8em;
            color: #666;
            flex-wrap: wrap;
        }
        
        .leyenda-dot {
            display: inline-block;
            width: 12px;
            height: 12px;
            border-radius: 2px;
            margin-right: 6px;
        }
        
        /* Estilos para el acordeón de trimestres - CORREGIDO */
        .th-nota-t1, .th-nota-t2, .th-nota-t3 {
            transition: all 0.2s ease;
        }
        
        .nota-td-t1, .nota-td-t2, .nota-td-t3 {
            transition: all 0.2s ease;
        }
        
        /* Ocultar columnas de notas y encabezados cuando el trimestre está colapsado */
        .tabla-calificaciones.t1-collapsed .th-nota-t1,
        .tabla-calificaciones.t1-collapsed .nota-td-t1 {
            display: none;
        }
        
        .tabla-calificaciones.t2-collapsed .th-nota-t2,
        .tabla-calificaciones.t2-collapsed .nota-td-t2 {
            display: none;
        }
        
        .tabla-calificaciones.t3-collapsed .th-nota-t3,
        .tabla-calificaciones.t3-collapsed .nota-td-t3 {
            display: none;
        }
        
        /* Estilo para el cursor del promedio */
        .th-promedio {
            cursor: pointer;
            position: relative;
            transition: background-color 0.2s;
        }
        
        .th-promedio:hover {
            background-color: #8b0000;
        }
        
        .th-promedio .arrow {
            font-size: 0.7em;
            margin-left: 5px;
            display: inline-block;
            transition: transform 0.2s;
        }
        
        .th-promedio.open .arrow {
            transform: rotate(180deg);
        }
        
        .badge-activo {
            display: inline-block;
            background: #FFD700;
            color: #7a0000;
            font-size: 0.7em;
            padding: 2px 6px;
            border-radius: 12px;
            margin-left: 8px;
            font-weight: bold;
        }
        
        .admin-badge {
            font-size: 0.5em;
            background: #ff9800;
            color: #fff;
            padding: 4px 10px;
            border-radius: 20px;
            margin-left: 15px;
            display: inline-block;
            vertical-align: middle;
        }
        
        #definitiva-header {
            cursor: pointer;
        }
        
        #definitiva-header:hover {
            background-color: #8b0000;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1 style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
    <span>Planilla de Calificaciones</span>
    <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
        <?php if(!$es_admin && $trimestre_actual_nombre): ?>
            <span class="admin-badge" style="background: #FFD700; color: #7a0000;">
                📅 <?= htmlspecialchars($trimestre_actual_nombre) ?> 
                <?php if(is_numeric($dias_restantes) && $dias_restantes > 0): ?>
                    - Faltan <?= $dias_restantes ?> día(s)
                <?php elseif($dias_restantes == 'próximamente'): ?>
                    - Próximamente
                <?php elseif($dias_restantes == 'ciclo lectivo finalizado'): ?>
                    - Ciclo finalizado
                <?php endif; ?>
            </span>
        <?php endif; ?>
        <?php if($es_admin): ?>
    <span class="admin-badge">Modo Administrador - Todas las materias</span>
<?php elseif($es_preceptor): ?>
    <span class="admin-badge">Modo Preceptor - Materias de tus cursos</span>
<?php endif; ?>
    </div>
</h1>
        
        <?php if($success): ?>
            <div class="mensaje-success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <!-- Selector de materia -->
        <div class="selector-materia">
            <h2>Seleccionar Materia</h2>
            <div class="materias-grid">
                <?php if(empty($materias_docente)): ?>
                    <p>No hay materias disponibles.</p>
                <?php endif; ?>
                <?php foreach($materias_docente as $materia): 
                    $turno_texto = $materia['turno'] == 'M' ? 'Mañana' : 'Tarde';
                ?>
                    <a href="calificaciones.php?materia_id=<?= $materia['ID_materia'] ?>" 
                       class="materia-card <?= $materia_seleccionada == $materia['ID_materia'] ? 'active' : '' ?>">
                        <h3><?= htmlspecialchars($materia['Nom_materia']) ?></h3>
                        <p><?= $materia['curso'] ?>° "<?= $materia['division'] ?>" - <?= $turno_texto ?></p>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
        
        <?php if($materia_seleccionada && $curso_info && !empty($alumnos)): ?>
            
            <div class="leyenda-acordeon">
                <span><span class="leyenda-dot" style="background: #7a0000;"></span> ▼ Clic en "Promedio" para colapsar/expandir notas</span>
                <?php if(!$es_admin && $trimestre_actual_num > 0): ?>
                <span><span class="leyenda-dot" style="background: #FFD700;"></span> Trimestre activo según fecha</span>
                <?php endif; ?>
            </div>
        
            <form method="POST" action="calificaciones.php?materia_id=<?= $materia_seleccionada ?>" id="form-calificaciones">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <input type="hidden" name="materia_id" value="<?= $materia_seleccionada ?>">
                <input type="hidden" name="guardar_calificaciones" value="1">
                
                <div class="tabla-calificaciones" id="tabla-calificaciones-container">
                    <table id="tabla-calificaciones">
                        <thead>
                            <tr>
                                <th rowspan="2">Nº</th>
                                <th rowspan="2">APELLIDO Y NOMBRES</th>

                                <!-- 1er Trimestre -->
                                <th class="th-nota-t1">Nota 1</th>
                                <th class="th-nota-t1">Nota 2</th>
                                <th class="th-nota-t1">Nota 3</th>
                                <th class="th-nota-t1">Nota 4</th>
                                <th class="th-nota-t1">Nota 5</th>
                                <th rowspan="2" class="th-promedio" data-trimestre="1">
                                    Promedio 1º T
                                    <?php if($trimestre_actual_num == 1 && !$es_admin): ?>
                                        <span class="badge-activo">ACTIVO</span>
                                    <?php endif; ?>
                                    <span class="arrow">▼</span>
                                </th>

                                <!-- 2do Trimestre -->
                                <th class="th-nota-t2">Nota 1</th>
                                <th class="th-nota-t2">Nota 2</th>
                                <th class="th-nota-t2">Nota 3</th>
                                <th class="th-nota-t2">Nota 4</th>
                                <th class="th-nota-t2">Nota 5</th>
                                <th rowspan="2" class="th-promedio" data-trimestre="2">
                                    Promedio 2º T
                                    <?php if($trimestre_actual_num == 2 && !$es_admin): ?>
                                        <span class="badge-activo">ACTIVO</span>
                                    <?php endif; ?>
                                    <span class="arrow">▼</span>
                                </th>

                                <!-- 3er Trimestre -->
                                <th class="th-nota-t3">Nota 1</th>
                                <th class="th-nota-t3">Nota 2</th>
                                <th class="th-nota-t3">Nota 3</th>
                                <th class="th-nota-t3">Nota 4</th>
                                <th class="th-nota-t3">Nota 5</th>
                                <th rowspan="2" class="th-promedio" data-trimestre="3">
                                    Promedio 3º T
                                    <?php if($trimestre_actual_num == 3 && !$es_admin): ?>
                                        <span class="badge-activo">ACTIVO</span>
                                    <?php endif; ?>
                                    <span class="arrow">▼</span>
                                </th>

                                <th rowspan="2">Promedio Final</th>
                                <th rowspan="2">Diciembre</th>
                                <th rowspan="2">Febrero-Marzo</th>
                                <th rowspan="2" id="definitiva-header" title="Haz clic para ordenar">
                                    Calif. Definitiva
                                    <span id="orden-indicador" style="font-size: 0.7em;"></span>
                                </th>
                            </tr>
                            <tr class="subheader">
                                <!-- Las notas ya están en la fila superior, esta fila es solo para mantener estructura -->
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $contador = 1;
                            foreach($alumnos as $alumno):
                                $dni_alumno = $alumno['DNI_U'];
                                
                                $notas_t1 = $calificaciones[$dni_alumno][1] ?? [];
                                $notas_t2 = $calificaciones[$dni_alumno][2] ?? [];
                                $notas_t3 = $calificaciones[$dni_alumno][3] ?? [];
                                
                                $promedio_t1 = calcularPromedio($notas_t1);
                                $promedio_t2 = calcularPromedio($notas_t2);
                                $promedio_t3 = calcularPromedio($notas_t3);
                                
                                $promedio_final = null;
                                if($promedio_t1 !== null && $promedio_t2 !== null && $promedio_t3 !== null){
                                    $promedio_final = round(($promedio_t1 + $promedio_t2 + $promedio_t3) / 3, 2);
                                }
                                
                                $nota_diciembre_raw = $calificaciones_especiales[$dni_alumno][4] ?? '';
                                $nota_diciembre = ($nota_diciembre_raw == -1) ? 'Aus' : $nota_diciembre_raw;
                                $nota_febrero_raw = $calificaciones_especiales[$dni_alumno][5] ?? '';
                                $nota_febrero = ($nota_febrero_raw == -1) ? 'Aus' : $nota_febrero_raw;
                                
                                $calificacion_definitiva = null;
                                if($promedio_final !== null && $promedio_final >= 6){
                                    $calificacion_definitiva = $promedio_final;
                                } elseif(!empty($nota_diciembre) && is_numeric($nota_diciembre) && $nota_diciembre >= 6){
                                    $calificacion_definitiva = round($nota_diciembre, 2);
                                } elseif(!empty($nota_febrero) && is_numeric($nota_febrero) && $nota_febrero >= 6){
                                    $calificacion_definitiva = round($nota_febrero, 2);
                                } else {
                                    $calificacion_definitiva = $promedio_final;
                                }

                                $definitiva_valor = ($calificacion_definitiva !== null) ? $calificacion_definitiva : '';
                                $orden_original = $contador;
                            ?>
                            <tr data-definitiva="<?= $definitiva_valor ?>" data-original-order="<?= $orden_original ?>">
                                <td class="numero-col"><?= $contador++ ?></td>
                                <td class="alumno-nombre"><?= htmlspecialchars($alumno['Apellido'] . ', ' . $alumno['Nombre']) ?></td>
                                
                                <!-- 1er Trimestre: 5 notas -->
<?php for($i = 0; $i < 5; $i++): ?>
<td class="nota-td-t1">
    <input type="text" 
           name="calificaciones_t1[<?= $dni_alumno ?>][]" 
           value="<?= isset($notas_t1[$i]) ? number_format($notas_t1[$i], 2) : '' ?>" 
           class="nota-input" 
           data-trimestre="1" 
           data-alumno="<?= $dni_alumno ?>"
           inputmode="decimal"
           placeholder="1-10"
           oninput="this.value = this.value.replace(/[^0-9.,]/g, '')">
</td>
<?php endfor; ?>
 <td class="promedio <?= getPromedioClass($promedio_t1) ?>"><?= $promedio_t1 !== null ? number_format($promedio_t1, 2) : '-' ?></td>
                                
                                <!-- 2do Trimestre: 5 notas -->
<?php for($i = 0; $i < 5; $i++): ?>
<td class="nota-td-t2">
    <input type="text" 
           name="calificaciones_t2[<?= $dni_alumno ?>][]" 
           value="<?= isset($notas_t2[$i]) ? number_format($notas_t2[$i], 2) : '' ?>" 
           class="nota-input" 
           data-trimestre="2" 
           data-alumno="<?= $dni_alumno ?>"
           inputmode="decimal"
           placeholder="1-10"
           oninput="this.value = this.value.replace(/[^0-9.,]/g, '')">
</td>
<?php endfor; ?>
<td class="promedio <?= getPromedioClass($promedio_t2) ?>"><?= $promedio_t2 !== null ? number_format($promedio_t2, 2) : '-' ?></td>
                                
                                <!-- 3er Trimestre: 5 notas -->
<?php for($i = 0; $i < 5; $i++): ?>
<td class="nota-td-t3">
    <input type="text" 
           name="calificaciones_t3[<?= $dni_alumno ?>][]" 
           value="<?= isset($notas_t3[$i]) ? number_format($notas_t3[$i], 2) : '' ?>" 
           class="nota-input" 
           data-trimestre="3" 
           data-alumno="<?= $dni_alumno ?>"
           inputmode="decimal"
           placeholder="1-10"
           oninput="this.value = this.value.replace(/[^0-9.,]/g, '')">
</td>
<?php endfor; ?>
<td class="promedio <?= getPromedioClass($promedio_t3) ?>"><?= $promedio_t3 !== null ? number_format($promedio_t3, 2) : '-' ?></td>
                                
                                <!-- Promedio Final -->
                                <td class="promedio <?= getPromedioClass($promedio_final) ?>"><?= $promedio_final !== null ? number_format($promedio_final, 2) : '-' ?></td>
                                
                                <!-- Diciembre - ACEPTA "Aus" -->
                                <td class="nota-col">
                                    <input type="text" name="calificaciones_t4[<?= $dni_alumno ?>]" value="<?= htmlspecialchars($nota_diciembre) ?>" 
                                           class="nota-input" data-trimestre="4" data-alumno="<?= $dni_alumno ?>" 
                                           placeholder="Nota o Aus" oninput="validarNotaEspecial(this)">
                                </td>
                                
                                <!-- Febrero-Marzo - ACEPTA "Aus" -->
                                <td class="nota-col">
                                    <input type="text" name="calificaciones_t5[<?= $dni_alumno ?>]" value="<?= htmlspecialchars($nota_febrero) ?>" 
                                           class="nota-input" data-trimestre="5" data-alumno="<?= $dni_alumno ?>" 
                                           placeholder="Nota o Aus" oninput="validarNotaEspecial(this)">
                                </td>
                                
                                <!-- Calif. Definitiva -->
                                <td class="promedio <?= ($calificacion_definitiva !== null) ? getPromedioClass($calificacion_definitiva) : '' ?>">
                                    <?= ($calificacion_definitiva !== null) ? number_format($calificacion_definitiva, 2) : '-' ?>
                                  </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <div class="btn-group">
    <button type="submit" class="btn-guardar">💾 Guardar Todas las Calificaciones</button>
    <button type="button" class="btn-guardar" onclick="generarPDF()" style="background: #2196F3;">📄 Exportar a PDF</button>
</div>
            </form>
            
        <?php elseif($materia_seleccionada && empty($alumnos)): ?>
            <div class="mensaje-error">No hay alumnos activos en esta materia o el curso no tiene estudiantes asignados.</div>
        <?php endif; ?>
        
        <a href="../../recursos/panel.php" class="volver-btn">← Volver al Panel</a>
    </div>
    
    <script>
    // ============================================
    // VARIABLES GLOBALES
    // ============================================
    let alumnosConNotasIncompletas = {
        1: 0,  // trimestre 1
        2: 0,  // trimestre 2
        3: 0   // trimestre 3
    };
    
    // ============================================
    // FUNCIÓN PARA VALIDAR NOTAS (1-10, máximo 2 decimales)
    // ============================================
    function validarNota(input) {
    let valor = input.value.trim();
    
    // Reemplazar coma por punto para cálculo
    let valorNumerico = valor.replace(',', '.');
    let num = parseFloat(valorNumerico);
    
    if (valor === '') {
        input.classList.remove('invalid');
        let trimestre = parseInt(input.getAttribute('data-trimestre'));
        let fila = input.closest('tr');
        if (trimestre >= 1 && trimestre <= 3) {
            actualizarPromedio(fila, trimestre);
        }
        return true;
    }
    
    // Validar que sea un número
    if (isNaN(num)) {
        input.classList.add('invalid');
        return false;
    }
    
    // Solo mostrar advertencia visual si está fuera de rango
    // Pero NO corregir automáticamente para no interferir con la escritura
    if (num < 1 || num > 10) {
        input.classList.add('invalid');
    } else {
        input.classList.remove('invalid');
    }
    
    // Actualizar el promedio en tiempo real SOLO si es un número válido
    if (!isNaN(num)) {
        let trimestre = parseInt(input.getAttribute('data-trimestre'));
        let fila = input.closest('tr');
        if (trimestre >= 1 && trimestre <= 3) {
            actualizarPromedio(fila, trimestre);
        }
    }
    
    return true;
}
    
    // ============================================
    // FUNCIÓN PARA VALIDAR NOTAS ESPECIALES (Diciembre y Febrero-Marzo)
    // ============================================
    function validarNotaEspecial(input) {
        let valor = input.value.trim();
        
        if (valor === '') {
            input.classList.remove('invalid');
            let fila = input.closest('tr');
            let promedioFinalText = fila.cells[20]?.textContent;
            let promedioFinal = promedioFinalText !== '-' ? parseFloat(promedioFinalText) : null;
            <?php if(!$es_admin): ?>
            actualizarEspeciales(fila, promedioFinal);
            <?php endif; ?>
            calcularCalifDefinitiva(fila, promedioFinal);
            return true;
        }
        
        if (valor.toUpperCase() === 'AUS') {
            input.value = 'Aus';
            input.classList.remove('invalid');
            let fila = input.closest('tr');
            let promedioFinalText = fila.cells[20]?.textContent;
            let promedioFinal = promedioFinalText !== '-' ? parseFloat(promedioFinalText) : null;
            <?php if(!$es_admin): ?>
            actualizarEspeciales(fila, promedioFinal);
            <?php endif; ?>
            calcularCalifDefinitiva(fila, promedioFinal);
            return true;
        }
        
        let num = parseFloat(valor);
        
        if (isNaN(num)) {
            input.value = '';
            input.classList.remove('invalid');
            return false;
        }
        
        if (num < 1) {
            input.value = 1;
            input.classList.add('invalid');
            setTimeout(() => input.classList.remove('invalid'), 1000);
            num = 1;
        } else if (num > 10) {
            input.value = 10;
            input.classList.add('invalid');
            setTimeout(() => input.classList.remove('invalid'), 1000);
            num = 10;
        }
        
        num = Math.round(num * 100) / 100;
        
        if (num === Math.floor(num)) {
            input.value = num;
        } else {
            input.value = num.toFixed(2);
        }
        
        input.classList.remove('invalid');
        
        let fila = input.closest('tr');
        let promedioFinalText = fila.cells[20]?.textContent;
        let promedioFinal = promedioFinalText !== '-' ? parseFloat(promedioFinalText) : null;
        <?php if(!$es_admin): ?>
        actualizarEspeciales(fila, promedioFinal);
        <?php endif; ?>
        calcularCalifDefinitiva(fila, promedioFinal);
        
        return true;
    }
    
    // ============================================
// FUNCIÓN PARA CALCULAR EL PROMEDIO DE NOTAS DE UN TRIMESTRE (JAVASCRIPT)
// ============================================
function calcularPromedioNotas(notasInputs) {
    let valores = [];
    for(let i = 0; i < notasInputs.length; i++){
        let valor = parseFloat(notasInputs[i].value);
        if(!isNaN(valor) && valor >= 1 && valor <= 10){
            valores.push(valor);
        }
    }
    
    // Solo calcular si hay al menos 3 notas
    if(valores.length >= 3){
        let suma = valores.reduce((a, b) => a + b, 0);
        let promedio = suma / valores.length;
        // Truncar a 2 decimales (sin redondear)
        return Math.floor(promedio * 100) / 100;
    }
    return null;
}
 
    // ============================================
    // FUNCIÓN PARA CONTAR ALUMNOS CON NOTAS INCOMPLETAS
    // ============================================
    function contarAlumnosConNotasIncompletas() {
        let conteo = {1: 0, 2: 0, 3: 0};
        let filas = document.querySelectorAll('#tabla-calificaciones tbody tr');
        
        filas.forEach(fila => {
            for(let trimestre = 1; trimestre <= 3; trimestre++) {
                let inicioColumna = 0;
                if(trimestre === 1) inicioColumna = 2;
                else if(trimestre === 2) inicioColumna = 2 + 6;
                else if(trimestre === 3) inicioColumna = 2 + 12;
                
                let notasInputs = [];
                for(let i = 0; i < 5; i++){
                    let input = fila.cells[inicioColumna + i]?.querySelector('.nota-input');
                    if(input) notasInputs.push(input);
                }
                
                let valores = [];
                let tieneNota = false;
                for(let i = 0; i < notasInputs.length; i++){
                    let valor = parseFloat(notasInputs[i].value);
                    if(!isNaN(valor) && valor >= 1 && valor <= 10){
                        valores.push(valor);
                        tieneNota = true;
                    }
                }
                
                // Si tiene al menos una nota pero menos de 3, cuenta como incompleto
                if(tieneNota && valores.length > 0 && valores.length < 3){
                    conteo[trimestre]++;
                }
            }
        });
        
        return conteo;
    }
    
    // ============================================
    // FUNCIÓN PARA ACTUALIZAR EL PROMEDIO DE UN TRIMESTRE
    // ============================================
    function actualizarPromedio(fila, trimestre) {
        let inicioColumna = 0;
        
        if(trimestre === 1){
            inicioColumna = 2;
        } else if(trimestre === 2){
            inicioColumna = 2 + 6;
        } else if(trimestre === 3){
            inicioColumna = 2 + 12;
        }
        
        let notasInputs = [];
        for(let i = 0; i < 5; i++){
            let input = fila.cells[inicioColumna + i]?.querySelector('.nota-input');
            if(input) notasInputs.push(input);
        }
        
        let promedio = calcularPromedioNotas(notasInputs);
        let celdaPromedio = fila.cells[inicioColumna + 5];
        
        if(promedio !== null){
            if (promedio === Math.floor(promedio)) {
                celdaPromedio.textContent = promedio;
            } else {
                celdaPromedio.textContent = promedio.toFixed(2);
            }
            celdaPromedio.className = 'promedio ' + (promedio >= 6 ? 'aprobado' : 'desaprobado');
        } else {
            celdaPromedio.textContent = '-';
            celdaPromedio.className = 'promedio';
        }
        
        calcularPromedioFinal(fila);
    }
    
    // ============================================
    // FUNCIÓN PARA CALCULAR EL PROMEDIO FINAL
    // ============================================
    function calcularPromedioFinal(fila) {
        let promT1 = parseFloat(fila.cells[7]?.textContent);
        let promT2 = parseFloat(fila.cells[13]?.textContent);
        let promT3 = parseFloat(fila.cells[19]?.textContent);
        
        let promedios = [];
        if(!isNaN(promT1) && fila.cells[7]?.textContent !== '-') promedios.push(promT1);
        if(!isNaN(promT2) && fila.cells[13]?.textContent !== '-') promedios.push(promT2);
        if(!isNaN(promT3) && fila.cells[19]?.textContent !== '-') promedios.push(promT3);
        
        let celdaPromedioFinal = fila.cells[20];
        
        let promedioFinal = null;
        if(promedios.length === 3){
            let suma = promedios.reduce((a, b) => a + b, 0);
            promedioFinal = Math.round((suma / 3) * 100) / 100;
            if (promedioFinal === Math.floor(promedioFinal)) {
                celdaPromedioFinal.textContent = promedioFinal;
            } else {
                celdaPromedioFinal.textContent = promedioFinal.toFixed(2);
            }
            celdaPromedioFinal.className = 'promedio ' + (promedioFinal >= 6 ? 'aprobado' : 'desaprobado');
        } else {
            celdaPromedioFinal.textContent = '-';
            celdaPromedioFinal.className = 'promedio';
        }
        
        <?php if(!$es_admin): ?>
        actualizarEspeciales(fila, promedioFinal);
        <?php endif; ?>
        
        calcularCalifDefinitiva(fila, promedioFinal);
    }
    
    // ============================================
    // FUNCIÓN PARA ACTUALIZAR HABILITACIÓN DE ESPECIALES
    // ============================================
    function actualizarEspeciales(fila, promedioFinal) {
        let celdaDiciembre = fila.cells[21]?.querySelector('.nota-input');
        let celdaFebrero = fila.cells[22]?.querySelector('.nota-input');
        
        if(celdaDiciembre) {
            if(promedioFinal !== null && promedioFinal < 6) {
                celdaDiciembre.disabled = false;
            } else {
                celdaDiciembre.disabled = true;
                celdaDiciembre.value = '';
            }
        }
        
        if(celdaFebrero) {
            let diciembreVal = celdaDiciembre ? celdaDiciembre.value.trim().toUpperCase() : '';
            let diciembreNum = parseFloat(diciembreVal);

            let habilitarFeb = (promedioFinal !== null && promedioFinal < 6) && 
                               (diciembreVal === 'AUS' || (!isNaN(diciembreNum) && diciembreNum < 6));
                                       
            celdaFebrero.disabled = !habilitarFeb;
            if (celdaFebrero.disabled) celdaFebrero.value = '';
        }
    }
    
    // ============================================
    // FUNCIÓN PARA CALCULAR CALIFICACIÓN DEFINITIVA
    // ============================================
    function calcularCalifDefinitiva(fila, promedioFinal) {
        let celdaCalifDefinitiva = fila.cells[23];
        let celdaDiciembre = fila.cells[21]?.querySelector('.nota-input');
        let celdaFebrero = fila.cells[22]?.querySelector('.nota-input');
        
        let diciembreVal = celdaDiciembre ? celdaDiciembre.value.trim() : '';
        let febreroVal = celdaFebrero ? celdaFebrero.value.trim() : '';
        
        let definitiva = null;
        
        if(promedioFinal !== null && !isNaN(promedioFinal) && promedioFinal >= 6) {
            definitiva = promedioFinal;
        } 
        else if(diciembreVal !== '' && diciembreVal.toUpperCase() !== 'AUS') {
            let dicNum = parseFloat(diciembreVal);
            if(!isNaN(dicNum) && dicNum >= 6) {
                definitiva = dicNum;
            }
        }
        else if(febreroVal !== '' && febreroVal.toUpperCase() !== 'AUS') {
            let febNum = parseFloat(febreroVal);
            if(!isNaN(febNum) && febNum >= 6) {
                definitiva = febNum;
            }
        }
        else {
            let textoPromedio = fila.cells[20]?.textContent;
            if(textoPromedio && textoPromedio !== '-') {
                let temp = parseFloat(textoPromedio);
                definitiva = !isNaN(temp) ? temp : null;
            } else {
                definitiva = null;
            }
        }
        
        if(definitiva !== null && !isNaN(definitiva)) {
            if (definitiva === Math.floor(definitiva)) {
                celdaCalifDefinitiva.textContent = definitiva;
            } else {
                celdaCalifDefinitiva.textContent = definitiva.toFixed(2);
            }
            celdaCalifDefinitiva.className = 'promedio ' + (definitiva >= 6 ? 'aprobado' : 'desaprobado');
            fila.setAttribute('data-definitiva', definitiva);
        } else {
            celdaCalifDefinitiva.textContent = '-';
            celdaCalifDefinitiva.className = 'promedio';
            fila.setAttribute('data-definitiva', '');
        }
    }
    
    // ============================================
    // FUNCIÓN PARA OBTENER TRIMESTRE ACTUAL
    // ============================================
    function obtenerTrimestreActual() {
        <?php if($es_admin): ?>
        return 1;
        <?php else: ?>
        const hoy = new Date();
        const anio = hoy.getFullYear();
        const fecha = new Date(anio, hoy.getMonth(), hoy.getDate());

        const inicioT1 = new Date(anio, 2, 2);
        const finT1    = new Date(anio, 5, 5);
        const inicioT2 = new Date(anio, 5, 8);
        const finT2    = new Date(anio, 8, 18);
        const inicioT3 = new Date(anio, 8, 22);
        const finT3    = new Date(anio, 11, 4);

        if(fecha >= inicioT1 && fecha <= finT1) return 1;
        if(fecha >= inicioT2 && fecha <= finT2) return 2;
        if(fecha >= inicioT3 && fecha <= finT3) return 3;
        return null;
        <?php endif; ?>
    }
    
    // ============================================
    // FUNCIÓN PARA VALIDAR FECHA DEL TRIMESTRE
    // ============================================
    function validarFechaTrimestre(event) {
        <?php if(!$es_admin): ?>
        const trimestreActual = obtenerTrimestreActual();
        if(trimestreActual === null) {
            alert('No es posible cargar calificaciones fuera de las fechas habilitadas para los trimestres.');
            event.preventDefault();
            return false;
        }
        
        let hayNotasInvalidas = false;
        document.querySelectorAll('.nota-input').forEach(input => {
            let trimestreInput = parseInt(input.getAttribute('data-trimestre'));
            if(!input.disabled && input.value.trim() !== '' && trimestreInput >= 1 && trimestreInput <= 3 && trimestreInput !== trimestreActual) {
                hayNotasInvalidas = true;
            }
        });

        if(hayNotasInvalidas){
            alert('Solo se pueden cargar notas en el trimestre activo según la fecha actual.');
            event.preventDefault();
            return false;
        }
        <?php endif; ?>
        return true;
    }
    
    // ============================================
    // FUNCIÓN PARA APLICAR RESTRICCIONES DE TRIMESTRE
    // ============================================
    function aplicarRestriccionesTrimestre() {
        <?php if($es_admin): ?>
        document.querySelectorAll('.nota-input').forEach(input => {
            input.disabled = false;
            input.removeAttribute('title');
        });
        <?php else: ?>
        const trimestreActual = obtenerTrimestreActual();
        document.querySelectorAll('.nota-input').forEach(input => {
            const trimestreInput = parseInt(input.getAttribute('data-trimestre'));
            if(trimestreActual === null || (trimestreInput >= 1 && trimestreInput <= 3 && trimestreInput !== trimestreActual)){
                input.disabled = true;
                input.setAttribute('title', 'Solo se puede cargar notas en el trimestre activo');
            } else {
                input.disabled = false;
                input.removeAttribute('title');
            }
        });

        if(trimestreActual === null){
            const container = document.querySelector('.container');
            if(container && !document.getElementById('mensaje-fecha-trimestre')){
                const mensaje = document.createElement('div');
                mensaje.id = 'mensaje-fecha-trimestre';
                mensaje.className = 'mensaje-error';
                mensaje.textContent = 'No es posible cargar calificaciones fuera de las fechas habilitadas para los trimestres.';
                container.insertBefore(mensaje, container.firstChild.nextSibling);
            }
        }
        <?php endif; ?>
    }
    
    // ============================================
    // FUNCIÓN PARA MOSTRAR ADVERTENCIA DE NOTAS INCOMPLETAS (SOLO CANTIDAD)
    // ============================================
    function mostrarAdvertenciaNotasIncompletas() {
        let conteo = contarAlumnosConNotasIncompletas();
        let mensajes = [];
        
        for(let trimestre = 1; trimestre <= 3; trimestre++) {
            if(conteo[trimestre] > 0) {
                let nombreTrimestre = trimestre === 1 ? '1er' : (trimestre === 2 ? '2do' : '3er');
                mensajes.push(`⚠️ ${conteo[trimestre]} alumno(s) tienen menos de 3 notas en el ${nombreTrimestre} trimestre.`);
            }
        }
        
        // Mostrar solo si hay advertencias
        if(mensajes.length > 0) {
            // Eliminar mensaje anterior si existe
            let mensajeExistente = document.getElementById('advertencia-notas');
            if(mensajeExistente) {
                mensajeExistente.remove();
            }
            
            // Crear nuevo mensaje
            let divAdvertencia = document.createElement('div');
            divAdvertencia.id = 'advertencia-notas';
            divAdvertencia.className = 'mensaje-warning';
            divAdvertencia.style.cssText = 'background: #fff3cd; border-left: 4px solid #ffc107; padding: 12px 15px; margin: 15px 0; border-radius: 4px; color: #856404;';
            divAdvertencia.innerHTML = mensajes.join('<br>');
            
            // Insertar después del info-header
            const infoHeader = document.querySelector('.info-header');
            if(infoHeader && !document.getElementById('advertencia-notas')) {
                infoHeader.insertAdjacentElement('afterend', divAdvertencia);
            }
        } else {
            let mensajeExistente = document.getElementById('advertencia-notas');
            if(mensajeExistente) {
                mensajeExistente.remove();
            }
        }
    }
    
    // ============================================
    // FUNCIONALIDAD DE ACORDEÓN PARA TRIMESTRES
    // ============================================
    (function() {
        const tablaContainer = document.getElementById('tabla-calificaciones-container');
        if (!tablaContainer) return;

        let trimestreActivo = <?= $trimestre_actual_num ?? 0 ?>;
        
        <?php if($es_admin): ?>
        trimestreActivo = 1;
        <?php endif; ?>
        
        let trimestreAbierto = trimestreActivo > 0 ? trimestreActivo : 1;

        function abrirTrimestre(t) {
            tablaContainer.classList.remove('t' + t + '-collapsed');
            const th = tablaContainer.querySelector('.th-promedio[data-trimestre="' + t + '"]');
            if (th) th.classList.add('open');
        }

        function cerrarTrimestre(t) {
            tablaContainer.classList.add('t' + t + '-collapsed');
            const th = tablaContainer.querySelector('.th-promedio[data-trimestre="' + t + '"]');
            if (th) th.classList.remove('open');
        }

        function toggleTrimestre(t) {
            if (trimestreAbierto === t) {
                cerrarTrimestre(t);
                trimestreAbierto = 0;
            } else {
                [1, 2, 3].forEach(n => cerrarTrimestre(n));
                abrirTrimestre(t);
                trimestreAbierto = t;
            }
        }

        [1, 2, 3].forEach(t => {
            if (t === trimestreAbierto) {
                abrirTrimestre(t);
            } else {
                cerrarTrimestre(t);
            }
        });

        const promedios = tablaContainer.querySelectorAll('.th-promedio');
        promedios.forEach(th => {
            th.removeEventListener('click', th._listener);
            const listener = function(e) {
                e.stopPropagation();
                const trimestreNum = parseInt(this.getAttribute('data-trimestre'));
                toggleTrimestre(trimestreNum);
            };
            th.addEventListener('click', listener);
            th._listener = listener;
        });
    })();
    
    // ============================================
    // INICIALIZACIÓN
    // ============================================
    const formCalificaciones = document.getElementById('form-calificaciones');
    if(formCalificaciones) {
        formCalificaciones.addEventListener('submit', function(event) {
            validarFechaTrimestre(event);
            // Mostrar advertencia de notas incompletas ANTES de enviar
            mostrarAdvertenciaNotasIncompletas();
        });
    }

    aplicarRestriccionesTrimestre();
    
    <?php if(!$es_admin): ?>
    document.querySelectorAll('tbody tr').forEach(fila => {
        let promedioFinalText = fila.cells[20]?.textContent;
        let promedioFinal = promedioFinalText !== '-' ? parseFloat(promedioFinalText) : null;
        actualizarEspeciales(fila, promedioFinal);
        calcularCalifDefinitiva(fila, promedioFinal);
    });
    <?php else: ?>
    document.querySelectorAll('tbody tr').forEach(fila => {
        let promedioFinalText = fila.cells[20]?.textContent;
        let promedioFinal = promedioFinalText !== '-' ? parseFloat(promedioFinalText) : null;
        calcularCalifDefinitiva(fila, promedioFinal);
    });
    <?php endif; ?>

    document.querySelectorAll('.nota-input').forEach(input => {
        input.addEventListener('input', function() {
            let trimestre = parseInt(this.getAttribute('data-trimestre'));
            let fila = this.closest('tr');
            if(trimestre >= 1 && trimestre <= 3) {
                actualizarPromedio(fila, trimestre);
            } else if(trimestre === 4 || trimestre === 5) {
                let promedioFinalText = fila.cells[20]?.textContent;
                let promedioFinal = promedioFinalText !== '-' ? parseFloat(promedioFinalText) : null;
                <?php if(!$es_admin): ?>
                actualizarEspeciales(fila, promedioFinal);
                <?php endif; ?>
                calcularCalifDefinitiva(fila, promedioFinal);
            }
            // Actualizar contador de notas incompletas
            mostrarAdvertenciaNotasIncompletas();
        });
    });
    
    // Mostrar advertencia inicial al cargar la página
    mostrarAdvertenciaNotasIncompletas();

    // ============================================
    // ORDENAMIENTO CLICKEABLE (Profesor o Admin)
    // ============================================
    <?php if($_SESSION['rol'] == 'Profesor' || $es_admin): ?>
    document.addEventListener('DOMContentLoaded', function() {
        const header = document.getElementById('definitiva-header');
        if (!header) return;
        
        const table = document.querySelector('#tabla-calificaciones');
        const tbody = table.querySelector('tbody');
        const rows = Array.from(tbody.querySelectorAll('tr'));
        
        let ordenActual = 'original';
        
        function ordenarPorDefinitiva(direccion) {
            const filasOrdenadas = [...rows];
            filasOrdenadas.sort((a, b) => {
                let valA = parseFloat(a.getAttribute('data-definitiva'));
                let valB = parseFloat(b.getAttribute('data-definitiva'));
                if (isNaN(valA)) valA = (direccion === 'desc') ? -Infinity : Infinity;
                if (isNaN(valB)) valB = (direccion === 'desc') ? -Infinity : Infinity;
                if (direccion === 'desc') return valB - valA;
                else return valA - valB;
            });
            filasOrdenadas.forEach(fila => tbody.appendChild(fila));
        }
        
        function restaurarOrdenOriginal() {
            rows.sort((a, b) => {
                let orderA = parseInt(a.getAttribute('data-original-order'));
                let orderB = parseInt(b.getAttribute('data-original-order'));
                return orderA - orderB;
            });
            rows.forEach(fila => tbody.appendChild(fila));
        }
        
        let clickCount = 0;
        header.addEventListener('click', function() {
            clickCount = (clickCount + 1) % 3;
            const indicador = document.getElementById('orden-indicador');
            if (clickCount === 1) {
                ordenarPorDefinitiva('desc');
                indicador.innerHTML = ' ▼';
                ordenActual = 'desc';
            } else if (clickCount === 2) {
                ordenarPorDefinitiva('asc');
                indicador.innerHTML = ' ▲';
                ordenActual = 'asc';
            } else {
                restaurarOrdenOriginal();
                indicador.innerHTML = '';
                ordenActual = 'original';
            }
        });
    });
    <?php endif; ?>


// ============================================
// FUNCIÓN PARA EXPORTAR PLANILLA A PDF
// ============================================
function generarPDF() {
    const { jsPDF } = window.jspdf;
    const doc = new jsPDF('landscape', 'mm', 'a4');
    
    // Título
    doc.setFontSize(16);
    doc.setFont(undefined, 'bold');
    doc.text('Planilla de Calificaciones', 14, 15);
    
    // Información de materia y curso
    doc.setFontSize(10);
    doc.setFont(undefined, 'normal');
    
    let materiaInfo = '';
    <?php if($materia_seleccionada && $curso_info): ?>
        materiaInfo = 'Materia: <?php echo htmlspecialchars($curso_info['Nom_materia']); ?> | Curso: <?php echo $curso_info['curso']; ?>° "<?php echo $curso_info['division']; ?>" | Turno: <?php echo ($curso_info['turno'] == 'M') ? 'Mañana' : 'Tarde'; ?>';
    <?php else: ?>
        materiaInfo = 'No hay materia seleccionada';
    <?php endif; ?>
    
    doc.text(materiaInfo, 14, 25);
    
    // Fecha de generación
    const fecha = new Date().toLocaleString();
    doc.text('Generado el: ' + fecha, 14, 32);
    
    // Obtener la tabla
    const table = document.getElementById('tabla-calificaciones');
    if(!table) {
        alert('No hay datos para exportar');
        return;
    }
    
    const rows = table.querySelectorAll('tr');
    const data = [];
    let headers = [];
    let isFirstRow = true;
    
    rows.forEach(row => {
        const rowData = [];
        const cells = row.querySelectorAll('th, td');
        
        cells.forEach((cell, index) => {
            // Obtener texto sin iconos ni elementos extra
            let text = cell.innerText.trim();
            
            // Limpiar símbolos de flechas y otros caracteres especiales
            text = text.replace(/▼|▲|arrow/gi, '').trim();
            
            // Excluir columnas de acciones o que no queremos exportar
            if(text === 'Editar' || text === 'Borrar' || text === 'Eliminar' || 
               text === 'Acciones' || text === '📧 Enviar' ||
               cell.classList.contains('no-exportar')) {
                return;
            }
            
            // Si es la primera fila (encabezados), guardar
            if(isFirstRow && text !== '') {
                rowData.push(text);
            } else if(!isFirstRow) {
                // Para datos, reemplazar guiones y limpiar
                if(text === '-' || text === '') {
                    rowData.push('-');
                } else {
                    rowData.push(text);
                }
            }
        });
        
        if(rowData.length > 0) {
            if(isFirstRow) {
                headers = rowData;
                isFirstRow = false;
            } else {
                data.push(rowData);
            }
        }
    });
    
    if(data.length === 0) {
        alert('No hay datos para exportar');
        return;
    }
    
    // Generar tabla en PDF
    doc.autoTable({
        head: [headers],
        body: data,
        startY: 40,
        theme: 'grid',
        styles: {
            fontSize: 7,
            cellPadding: 2,
            halign: 'center',
            valign: 'middle',
            lineWidth: 0.1
        },
        headStyles: {
            fillColor: [122, 0, 0],
            textColor: [255, 255, 255],
            fontStyle: 'bold',
            fontSize: 7
        },
        alternateRowStyles: {
            fillColor: [245, 245, 245]
        },
        margin: { left: 8, right: 8, top: 35 },
        didDrawPage: function(data) {
            // Pie de página
            const pageCount = doc.internal.getNumberOfPages();
            for(let i = 1; i <= pageCount; i++) {
                doc.setPage(i);
                doc.setFontSize(7);
                doc.setTextColor(100, 100, 100);
                doc.text(
                    `Página ${i} de ${pageCount} | Sistema Escolar - EPET N°34`,
                    doc.internal.pageSize.getWidth() / 2,
                    doc.internal.pageSize.getHeight() - 10,
                    { align: 'center' }
                );
            }
        }
    });
    
    // Guardar PDF
    const nombreArchivo = `planilla_calificaciones_<?php echo date('Y-m-d_H-i'); ?>.pdf`;
    doc.save(nombreArchivo);
}


</script>
</body>
</html>