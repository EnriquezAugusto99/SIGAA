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

// Verificar permisos - Preceptor, Admin y Secretario pueden editar horarios
if($_SESSION['rol'] != 'Preceptor' && $_SESSION['rol'] != 'Admin' && $_SESSION['rol'] != 'Secretario'){
    echo '<script>alert("No tiene permisos para editar horarios"); window.location="../../recursos/panel.php";</script>';
    exit();
}

include '../../recursos/conexion.php';

$error = '';
$success = '';
$curso_info = null;
$materias_del_curso = [];
$horario_existente = [];

$es_admin = ($_SESSION['rol'] == 'Admin');
$es_preceptor = ($_SESSION['rol'] == 'Preceptor');
$es_secretario = ($_SESSION['rol'] == 'Secretario');

// =====================================================
// OBTENER EL CURSO ACTIVO
// Para Admin: puede seleccionar curso via GET o usar selector
// Para Preceptor/Secretario: usa el curso de la sesion
// =====================================================
$id_curso = 0;

if($es_admin){
    // Admin: puede venir de GET (desde selector) o de sesion
    if(isset($_GET['id_curso']) && $_GET['id_curso'] > 0){
        $id_curso = (int)$_GET['id_curso'];
        // Guardar en sesion para mantener consistencia
        $_SESSION['curso_activo_preceptor'] = $id_curso;
    } else {
        $id_curso = $_SESSION['curso_activo_preceptor'] ?? 0;
    }
} else {
    // Preceptor o Secretario: usan el curso activo de la sesion
    $id_curso = $_SESSION['curso_activo_preceptor'] ?? 0;
}

// Lista de cursos para el selector (solo para Admin)
$lista_cursos = [];
if($es_admin){
    $query_cursos = "SELECT ID_curso, curso, division, turno FROM curso ORDER BY curso, division";
    $res_cursos = mysqli_query($con, $query_cursos);
    while($curso = mysqli_fetch_assoc($res_cursos)){
        $turno_texto = $curso['turno'] == 'M' ? 'Mañana' : 'Tarde';
        $lista_cursos[] = [
            'ID_curso' => $curso['ID_curso'],
            'nombre' => $curso['curso'] . '° "' . $curso['division'] . '" - ' . $turno_texto
        ];
    }
}

if($id_curso > 0){
    // Verificar permisos segun rol
    $tiene_permiso = true;
    
    if($es_preceptor){
        $dni_preceptor = $_SESSION["dni"];
        $query_verificar = "SELECT * FROM preceptorxcurso WHERE id_preceptor = '$dni_preceptor' AND id_curso = '$id_curso'";
        $res_verificar = mysqli_query($con, $query_verificar);
        if(mysqli_num_rows($res_verificar) == 0){
            $error = "No tiene permisos para editar el horario de este curso.";
            $tiene_permiso = false;
            $id_curso = 0;
        }
    } elseif($es_secretario){
        // Secretario puede editar todos los cursos
        $tiene_permiso = true;
    } elseif($es_admin){
        // Admin puede editar todos los cursos
        $tiene_permiso = true;
    }
    
    if($tiene_permiso && $id_curso > 0){
        // Obtener información del curso
        $query_curso = "SELECT ID_curso, curso, division, turno FROM curso WHERE ID_curso = '$id_curso'";
        $res_curso = mysqli_query($con, $query_curso);
        $curso_info = mysqli_fetch_array($res_curso);
        
        if($curso_info){
            // Obtener materias del curso desde la tabla materia
            $query_materias = "SELECT ID_materia, Nom_materia FROM materia WHERE id_curso = '$id_curso' ORDER BY Nom_materia";
            $res_materias = mysqli_query($con, $query_materias);
            if(mysqli_num_rows($res_materias) > 0){
                while($fila = mysqli_fetch_array($res_materias)){
                    // Obtener docentes de cada materia
                    $query_docentes = "SELECT u.DNI_U, u.Nombre, u.Apellido 
                                       FROM usuario u
                                       INNER JOIN docentemateriacurso dmc ON u.DNI_U = dmc.id_docente
                                       WHERE dmc.id_materia = '{$fila['ID_materia']}' AND dmc.id_curso = '$id_curso'";
                    $res_docentes = mysqli_query($con, $query_docentes);
                    $docentes = [];
                    while($docente = mysqli_fetch_array($res_docentes)){
                        $docentes[] = $docente['Apellido'] . ', ' . $docente['Nombre'];
                    }
                    $materias_del_curso[] = [
                        'ID_materia' => $fila['ID_materia'],
                        'Nom_materia' => $fila['Nom_materia'],
                        'docentes' => implode('; ', $docentes)
                    ];
                }
            }
            
            // Obtener horario existente del curso
            $query_horario = "SELECT h.id_horario, h.dia_semana, h.horario_inicio, h.horario_fin, 
                                     h.id_materia, h.id_docente,
                                     m.Nom_materia
                              FROM horarios h
                              INNER JOIN materia m ON h.id_materia = m.ID_materia
                              WHERE h.id_curso = '$id_curso'
                              ORDER BY FIELD(h.dia_semana, 'Lunes', 'Martes', 'Miercoles', 'Jueves', 'Viernes'), h.horario_inicio";
            $res_horario = mysqli_query($con, $query_horario);
            while($fila = mysqli_fetch_array($res_horario)){
                $key = $fila['dia_semana'] . '_' . $fila['horario_inicio'];
                $horario_existente[$key] = $fila;
            }
        } else {
            $error = "No se encontró el curso seleccionado.";
        }
    }
} else {
    if(!$es_admin){
        $error = "No hay un curso seleccionado. Por favor, seleccione un curso desde el panel principal.";
    }
}

// Procesar guardado de horario editado
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['guardar_horario'])){
    $id_curso_post = $_POST['id_curso'];
    $horario_modificado = json_decode($_POST['horario_modificado'], true);
    
    // Verificar permisos
    $tiene_permiso_guardar = false;
    if($es_admin){
        $tiene_permiso_guardar = true;
    } elseif($es_preceptor){
        $dni_preceptor = $_SESSION["dni"];
        $query_verificar = "SELECT * FROM preceptorxcurso WHERE id_preceptor = '$dni_preceptor' AND id_curso = '$id_curso_post'";
        $res_verificar = mysqli_query($con, $query_verificar);
        $tiene_permiso_guardar = (mysqli_num_rows($res_verificar) > 0);
    } elseif($es_secretario){
        $tiene_permiso_guardar = true;
    }
    
    if($tiene_permiso_guardar){
        $modificados = 0;
        
        foreach($horario_modificado as $key => $data){
            if($data['accion'] == 'eliminar'){
                // Eliminar horario existente
                $query_delete = "DELETE FROM horarios WHERE id_horario = '{$data['id_horario']}'";
                mysqli_query($con, $query_delete);
                $modificados++;
            } elseif($data['accion'] == 'agregar' || $data['accion'] == 'modificar'){
                $dia = $data['dia'];
                $hora_inicio = $data['hora_inicio'];
                $hora_fin = $data['hora_fin'];
                $id_materia = $data['id_materia'];
                
                // Obtener docente de la materia para este curso
                $query_docente = "SELECT id_docente FROM docentemateriacurso 
                                  WHERE id_materia = '$id_materia' AND id_curso = '$id_curso_post' LIMIT 1";
                $res_docente = mysqli_query($con, $query_docente);
                $id_docente = null;
                if(mysqli_num_rows($res_docente) > 0){
                    $docente_row = mysqli_fetch_array($res_docente);
                    $id_docente = $docente_row['id_docente'];
                }
                
                if($data['accion'] == 'modificar' && isset($data['id_horario'])){
                    // Actualizar horario existente
                    $query_update = "UPDATE horarios 
                                    SET id_materia = '$id_materia', id_docente = " . ($id_docente ? "'$id_docente'" : "NULL") . "
                                    WHERE id_horario = '{$data['id_horario']}'";
                    mysqli_query($con, $query_update);
                } else {
                    // Insertar nuevo horario
                    $query_insert = "INSERT INTO horarios (id_curso, id_materia, id_docente, dia_semana, horario_inicio, horario_fin) 
                                    VALUES ('$id_curso_post', '$id_materia', " . ($id_docente ? "'$id_docente'" : "NULL") . ", '$dia', '$hora_inicio', '$hora_fin')";
                    mysqli_query($con, $query_insert);
                }
                $modificados++;
            }
        }
        
        $_SESSION['notificacion'] = [
            'tipo' => 'exito',
            'mensaje' => "Horario actualizado exitosamente. Se realizaron $modificados modificaciones."
        ];
    } else {
        $_SESSION['notificacion'] = [
            'tipo' => 'error',
            'mensaje' => "No tiene permisos para modificar el horario de este curso."
        ];
    }
    header("Location: horario_editar.php" . ($es_admin && $id_curso_post ? "?id_curso=$id_curso_post" : ""));
    exit();
}

// Definir módulos horarios
$modulos_manana = [
    ['inicio' => '07:00:00', 'fin' => '07:40:00', 'modulo' => 1],
    ['inicio' => '07:40:00', 'fin' => '08:20:00', 'modulo' => 2],
    ['inicio' => '08:30:00', 'fin' => '09:10:00', 'modulo' => 3],
    ['inicio' => '09:10:00', 'fin' => '09:50:00', 'modulo' => 4],
    ['inicio' => '10:00:00', 'fin' => '10:40:00', 'modulo' => 5],
    ['inicio' => '10:40:00', 'fin' => '11:20:00', 'modulo' => 6],
    ['inicio' => '11:20:00', 'fin' => '12:00:00', 'modulo' => 7]
];

$modulos_tarde = [
    ['inicio' => '14:00:00', 'fin' => '14:40:00', 'modulo' => 1],
    ['inicio' => '14:40:00', 'fin' => '15:20:00', 'modulo' => 2],
    ['inicio' => '15:30:00', 'fin' => '16:10:00', 'modulo' => 3],
    ['inicio' => '16:10:00', 'fin' => '16:50:00', 'modulo' => 4],
    ['inicio' => '17:00:00', 'fin' => '17:40:00', 'modulo' => 5],
    ['inicio' => '17:40:00', 'fin' => '18:20:00', 'modulo' => 6],
    ['inicio' => '18:20:00', 'fin' => '19:00:00', 'modulo' => 7]
];

$dias_semana = ['Lunes', 'Martes', 'Miercoles', 'Jueves', 'Viernes'];

function formatearHora($hora){
    return substr($hora, 0, 5);
}

$turno_texto = '';
if($curso_info){
    $turno_texto = $curso_info['turno'] == 'M' ? 'Mañana' : 'Tarde';
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../../recursos/styles.css">
    <title>Editar Horario - <?= $curso_info ? $curso_info['curso'] . '° "' . $curso_info['division'] . '"' : 'Seleccionar Curso' ?></title>
    <style>
        .container {
            max-width: 100%;
            margin: 0 auto;
            padding: 20px;
            background: #1a2a3a;
            color: #E0F7FA;
            overflow-x: auto;
        }
        
        .header-actions {
            display: flex;
            gap: 12px;
            margin-bottom: 20px;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
        }
        
        .btn-nav {
            background: #2D5A8C;
            color: #E0F7FA;
            padding: 10px 20px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
            border: 1px solid #40E0D0;
            display: inline-block;
        }
        
        .btn-nav:hover {
            background: #40E0D0;
            color: #1a2a3a;
            transform: translateY(-2px);
        }
        
        .selector-curso {
            background: #1e3a5a;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 25px;
            border: 1px solid #40E0D0;
        }
        
        .selector-curso h3 {
            margin-top: 0;
            margin-bottom: 15px;
            color: #40E0D0;
            font-size: 1.1em;
        }
        
        .curso-select {
            width: 100%;
            max-width: 400px;
            padding: 12px;
            border-radius: 8px;
            border: 1px solid #40E0D0;
            background: #1a2a3a;
            color: #E0F7FA;
            font-size: 14px;
            cursor: pointer;
        }
        
        .curso-select option {
            background: #1a2a3a;
            color: #E0F7FA;
        }
        
        .btn-ir-curso {
            background: #4CAF50;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            margin-left: 10px;
            transition: all 0.3s ease;
        }
        
        .btn-ir-curso:hover {
            background: #45a049;
            transform: translateY(-2px);
        }
        
        .curso-header {
            background: #2D5A8C;
            padding: 25px;
            border-radius: 12px;
            margin-bottom: 25px;
            text-align: center;
            border: 1px solid #40E0D0;
        }
        
        .curso-header h1 {
            margin: 0 0 10px 0;
            font-size: 1.8em;
            color: #40E0D0;
        }
        
        .curso-info {
            display: flex;
            justify-content: center;
            gap: 30px;
            flex-wrap: wrap;
            margin-top: 15px;
        }
        
        .info-badge {
            background: #1a2a3a;
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: 600;
            border: 1px solid #40E0D0;
        }
        
        .info-edicion {
            background: #1e3a5a;
            padding: 15px;
            border-radius: 8px;
            margin: 15px 0;
            border-left: 4px solid #40E0D0;
        }
        
        .info-edicion ul {
            margin: 10px 0 0 20px;
        }
        
        .info-edicion li {
            margin: 5px 0;
        }
        
        .seccion-turno {
            margin-bottom: 35px;
        }
        
        .titulo-turno {
            background: #2D5A8C;
            padding: 15px 25px;
            border-radius: 8px;
            margin: 0 0 20px 0;
            font-weight: 600;
            font-size: 1.2em;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border: 1px solid #40E0D0;
        }
        
        .tabla-horario {
            width: 100%;
            border-collapse: collapse;
            background: #2D5A8C;
            border-radius: 10px;
            overflow: hidden;
            table-layout: fixed;
        }
        
        .tabla-horario th {
            background: #1a2a3a;
            color: #40E0D0;
            padding: 12px 8px;
            text-align: center;
            font-weight: 600;
            border: 1px solid #40E0D0;
            font-size: 13px;
        }
        
        .tabla-horario td {
            padding: 8px;
            border: 1px solid #40E0D0;
            text-align: center;
            vertical-align: middle;
        }
        
        .hora-columna {
            background: #1a2a3a;
            font-weight: 600;
            color: #40E0D0;
            width: 90px;
            font-size: 11px;
        }
        
        .celda-horario {
            cursor: pointer;
            min-height: 70px;
            transition: all 0.3s ease;
        }
        
        .celda-horario:hover {
            background: #1e3a5a;
            transform: scale(1.02);
        }
        
        .celda-horario.vacio {
            background: #1a2a3a;
        }
        
        .celda-horario.ocupado {
            background: #1e3a5a;
        }
        
        .contenido-celda {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 4px;
        }
        
        .contenido-celda strong {
            font-size: 11px;
            color: #40E0D0;
            word-break: break-word;
        }
        
        .info-materia {
            font-size: 9px;
            opacity: 0.8;
            word-break: break-word;
        }
        
        .select-container {
            width: 100%;
        }
        
        .select-materia {
            width: 100%;
            padding: 6px;
            border-radius: 4px;
            border: 1px solid #40E0D0;
            background: #1a2a3a;
            color: #E0F7FA;
            font-size: 10px;
        }
        
        .select-materia option {
            background: #1a2a3a;
            color: #E0F7FA;
        }
        
        .celda-horario.materia-seleccionada {
            background: #40E0D0;
        }
        
        .celda-horario.materia-seleccionada .contenido-celda {
            display: none;
        }
        
        .modulo-info {
            font-size: 8px;
            color: #40E0D0;
            margin-top: 3px;
        }
        
        .btn-guardar {
            background: #4CAF50;
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 600;
            margin-top: 20px;
            width: 100%;
            transition: all 0.3s ease;
        }
        
        .btn-guardar:hover {
            background: #45a049;
            transform: translateY(-2px);
        }
        
        .preview-horario {
            background: #1e3a5a;
            padding: 12px;
            border-radius: 8px;
            margin: 15px 0;
            border-left: 4px solid #40E0D0;
        }
        
        .volver-btn {
            display: inline-block;
            margin-top: 20px;
            padding: 8px 16px;
            background: #2D5A8C;
            color: #E0F7FA;
            text-decoration: none;
            border-radius: 4px;
            border: 1px solid #40E0D0;
            font-size: 0.9em;
        }
        
        .volver-btn:hover {
            background: #40E0D0;
            color: #1a2a3a;
        }
        
        .mensaje-error {
            background: rgba(244, 67, 54, 0.2);
            border: 1px solid #F44336;
            padding: 15px;
            border-radius: 8px;
            margin: 20px 0;
            color: #ff9999;
            text-align: center;
        }
        
        .admin-badge {
            background: #ff9800;
            color: #1a2a3a;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.75em;
            font-weight: bold;
            margin-left: 10px;
        }
        
        @media (max-width: 768px) {
            .container {
                padding: 10px;
            }
            
            .curso-info {
                flex-direction: column;
                gap: 8px;
                align-items: center;
            }
            
            .titulo-turno {
                flex-direction: column;
                gap: 8px;
                text-align: center;
                padding: 10px;
                font-size: 1em;
            }
            
            .tabla-horario {
                font-size: 10px;
            }
            
            .tabla-horario th,
            .tabla-horario td {
                padding: 4px 2px;
            }
            
            .hora-columna {
                width: 60px;
                font-size: 9px;
            }
            
            .contenido-celda strong {
                font-size: 9px;
            }
            
            .info-materia {
                font-size: 8px;
            }
            
            .btn-nav, .btn-guardar {
                padding: 8px 16px;
                font-size: 12px;
            }
            
            .selector-curso {
                padding: 15px;
            }
            
            .curso-select {
                width: 100%;
                margin-bottom: 10px;
            }
            
            .btn-ir-curso {
                width: 100%;
                margin-left: 0;
            }
        }
        
        @media (max-width: 480px) {
            .hora-columna {
                width: 50px;
                font-size: 8px;
            }
            
            .contenido-celda strong {
                font-size: 8px;
            }
            
            .tabla-horario th {
                font-size: 9px;
                padding: 6px 2px;
            }
        }
    </style>
</head>
<body>
<div class="container">
    <div class="header-actions">
        <a href="../../recursos/panel.php" class="btn-nav">Panel Principal</a>
        <a href="tarjeta_horario.php" class="btn-nav">Tarjeta de Curso</a>
        <a href="horario_detalle.php" class="btn-nav">Ver Horario</a>
    </div>

    <h1>Editar Horario
        <?php if($es_admin): ?>
            <span class="admin-badge">Modo Administrador</span>
        <?php endif; ?>
    </h1>

    <!-- Selector de curso (solo para Admin) -->
    <?php if($es_admin && !empty($lista_cursos)): ?>
        <div class="selector-curso">
            <h3>Seleccionar Curso</h3>
            <div style="display: flex; gap: 10px; flex-wrap: wrap; align-items: center;">
                <select id="selector-curso" class="curso-select">
                    <option value="">-- Seleccione un curso --</option>
                    <?php foreach($lista_cursos as $curso): ?>
                        <option value="<?= $curso['ID_curso'] ?>" <?= ($id_curso == $curso['ID_curso']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($curso['nombre']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button onclick="irACurso()" class="btn-ir-curso">Editar Horario</button>
            </div>
        </div>
    <?php elseif($es_admin && empty($lista_cursos)): ?>
        <div class="mensaje-error">
            No hay cursos disponibles en el sistema.
        </div>
    <?php endif; ?>

    <?php if($error): ?>
        <div class="mensaje-error">
            <p><?= htmlspecialchars($error) ?></p>
            <?php if(!$es_admin && strpos($error, 'curso seleccionado') !== false): ?>
                <p><a href="../cursos/seleccionar_curso.php" style="color: #40E0D0;">Seleccionar un curso</a></p>
            <?php endif; ?>
        </div>
    <?php elseif($curso_info): ?>
        <div class="curso-header">
            <h1>Editar Horario de <?= $curso_info['curso'] ?>° "<?= $curso_info['division'] ?>"</h1>
            <div class="curso-info">
                <div class="info-badge">Turno: <?= $turno_texto ?></div>
                <div class="info-badge">Materias disponibles: <?= count($materias_del_curso) ?></div>
                <div class="info-badge">Modulos asignados: <?= count($horario_existente) ?></div>
            </div>
        </div>

        <div class="info-edicion">
            <strong>Instrucciones:</strong>
            <ul>
                <li>Haz clic en cualquier celda para asignar o modificar una materia</li>
                <li>Las materias existentes se muestran en color celeste</li>
                <li>Selecciona "Sin materia" para eliminar una asignacion existente</li>
                <li>Los cambios se guardaran manteniendo las asignaciones no modificadas</li>
            </ul>
        </div>

        <?php if(empty($materias_del_curso)): ?>
            <div class="mensaje-error">
                <p>Este curso no tiene materias asignadas. Primero debe asignar materias al curso para poder crear un horario.</p>
            </div>
        <?php else: ?>
            <form method="POST" action="horario_editar.php<?= $es_admin && $id_curso ? '?id_curso=' . $id_curso : '' ?>" id="formHorario">
                <input type="hidden" name="id_curso" value="<?= $id_curso ?>">
                <input type="hidden" name="guardar_horario" value="1">
                <input type="hidden" name="horario_modificado" id="horario_modificado">
                
                <div class="preview-horario" id="preview-horario">
                    <strong>Estado:</strong> <span id="preview-texto"><?= count($horario_existente) ?> módulos asignados. Haz clic en las celdas para modificarlas.</span>
                </div>
                
                <!-- Turno Mañana -->
                <div class="seccion-turno">
                    <div class="titulo-turno">
                        <span>Turno Mañana (7:00 - 12:00)</span>
                        <small>7 modulos</small>
                    </div>
                    <table class="tabla-horario">
                        <thead>
                            <tr>
                                <th>Hora</th>
                                <?php foreach($dias_semana as $dia): ?>
                                    <th><?= $dia ?></th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody id="cuerpo-horario-manana">
                            <!-- Generado por JavaScript -->
                        </tbody>
                    </table>
                </div>
                
                <!-- Turno Tarde -->
                <div class="seccion-turno">
                    <div class="titulo-turno">
                        <span>Turno Tarde (14:00 - 19:00)</span>
                        <small>7 modulos</small>
                    </div>
                    <table class="tabla-horario">
                        <thead>
                            <tr>
                                <th>Hora</th>
                                <?php foreach($dias_semana as $dia): ?>
                                    <th><?= $dia ?></th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody id="cuerpo-horario-tarde">
                            <!-- Generado por JavaScript -->
                        </tbody>
                    </table>
                </div>
                
                <button type="submit" class="btn-guardar" id="btn-guardar">
                    Guardar Cambios
                </button>
            </form>
        <?php endif; ?>
        
    <?php elseif($es_admin && !$curso_info && !$error): ?>
        <div class="mensaje-error">
            <p>Seleccione un curso del menú desplegable para editar su horario.</p>
        </div>
    <?php else: ?>
        <div class="mensaje-error">
            <p>No hay un curso seleccionado. Por favor, seleccione un curso desde el panel principal.</p>
            <?php if(!$es_admin): ?>
                <p><a href="../cursos/seleccionar_curso.php" style="color: #40E0D0;">Seleccionar Curso</a></p>
            <?php endif; ?>
        </div>
    <?php endif; ?>
    
    <div style="text-align: center; margin-top: 30px;">
        <a href="../../recursos/panel.php" class="volver-btn">Volver al Panel</a>
    </div>
</div>

<script>
function irACurso() {
    const selector = document.getElementById('selector-curso');
    const cursoId = selector.value;
    if(cursoId) {
        window.location.href = 'horario_editar.php?id_curso=' + cursoId;
    } else {
        alert('Por favor, seleccione un curso.');
    }
}

// Script principal para edición de horarios
const modulosManana = <?= json_encode($modulos_manana) ?>;
const modulosTarde = <?= json_encode($modulos_tarde) ?>;
const diasSemana = <?= json_encode($dias_semana) ?>;
const materiasCurso = <?= json_encode($materias_del_curso) ?>;
const horarioExistente = <?= json_encode($horario_existente) ?>;

let horarioModificado = {};
let cambiosRealizados = false;

function formatearHora(hora) {
    return hora.substring(0, 5);
}

function generarOpcionesMaterias() {
    if(!materiasCurso || materiasCurso.length === 0){
        return '<option value="">-- No hay materias disponibles --</option>';
    }
    return materiasCurso.map(m => 
        `<option value="${m.ID_materia}">
            ${m.Nom_materia} ${m.docentes ? '- ' + m.docentes : ''}
        </option>`
    ).join('');
}

function generarTablaHorario(turno) {
    const cuerpoId = turno === 'manana' ? 'cuerpo-horario-manana' : 'cuerpo-horario-tarde';
    const cuerpo = document.getElementById(cuerpoId);
    if(!cuerpo) return;
    
    cuerpo.innerHTML = '';
    const modulos = turno === 'manana' ? modulosManana : modulosTarde;
    
    modulos.forEach((modulo, index) => {
        const fila = document.createElement('tr');
        
        const tdHora = document.createElement('td');
        tdHora.className = 'hora-columna';
        tdHora.innerHTML = `
            ${formatearHora(modulo.inicio)}<br>
            ${formatearHora(modulo.fin)}
            <div class="modulo-info">M${modulo.modulo}</div>
        `;
        fila.appendChild(tdHora);
        
        diasSemana.forEach(dia => {
            const td = document.createElement('td');
            td.className = 'celda-horario vacio';
            td.dataset.dia = dia;
            td.dataset.turno = turno;
            td.dataset.modulo = modulo.modulo;
            td.dataset.horaInicio = modulo.inicio;
            td.dataset.horaFin = modulo.fin;
            
            td.innerHTML = `
                <div class="select-container" style="display: none;">
                    <select class="select-materia" onchange="seleccionarMateria(this)">
                        <option value="">-- Sin materia --</option>
                        ${generarOpcionesMaterias()}
                    </select>
                </div>
                <div class="contenido-celda"></div>
            `;
            
            td.addEventListener('click', function(e) {
                if(!e.target.classList?.contains('select-materia')){
                    if(materiasCurso.length > 0){
                        activarSeleccionMateria(this);
                    } else {
                        alert('Este curso no tiene materias asignadas.');
                    }
                }
            });
            
            fila.appendChild(td);
        });
        
        cuerpo.appendChild(fila);
    });
}

function activarSeleccionMateria(celda) {
    document.querySelectorAll('.select-container').forEach(container => {
        container.style.display = 'none';
    });
    document.querySelectorAll('.celda-horario').forEach(c => {
        c.classList.remove('materia-seleccionada');
    });
    
    celda.classList.add('materia-seleccionada');
    const selectContainer = celda.querySelector('.select-container');
    selectContainer.style.display = 'block';
    celda.querySelector('.contenido-celda').style.display = 'none';
    const select = celda.querySelector('.select-materia');
    select.focus();
}

function seleccionarMateria(select) {
    const celda = select.closest('.celda-horario');
    const contenidoCelda = celda.querySelector('.contenido-celda');
    const selectContainer = celda.querySelector('.select-container');
    
    const key = `${celda.dataset.dia}_${celda.dataset.horaInicio}`;
    const materiaExistente = horarioExistente[key] || null;
    
    if(select.value){
        const materiaSeleccionada = materiasCurso.find(m => m.ID_materia == select.value);
        if(materiaSeleccionada){
            contenidoCelda.innerHTML = `
                <strong>${materiaSeleccionada.Nom_materia}</strong>
                ${materiaSeleccionada.docentes ? `<div class="info-materia">${materiaSeleccionada.docentes}</div>` : ''}
            `;
            celda.classList.remove('vacio');
            celda.classList.add('ocupado');
            
            if(materiaExistente){
                // Modificar materia existente
                horarioModificado[key] = {
                    id_horario: materiaExistente.id_horario,
                    id_materia: select.value,
                    dia: celda.dataset.dia,
                    hora_inicio: celda.dataset.horaInicio,
                    hora_fin: celda.dataset.horaFin,
                    accion: 'modificar'
                };
            } else {
                // Agregar nueva materia
                horarioModificado[key] = {
                    id_materia: select.value,
                    dia: celda.dataset.dia,
                    hora_inicio: celda.dataset.horaInicio,
                    hora_fin: celda.dataset.horaFin,
                    accion: 'agregar'
                };
            }
        }
    } else {
        contenidoCelda.innerHTML = '';
        celda.classList.add('vacio');
        celda.classList.remove('ocupado');
        
        if(materiaExistente){
            // Eliminar materia existente
            horarioModificado[key] = {
                id_horario: materiaExistente.id_horario,
                accion: 'eliminar'
            };
        } else {
            // Si no habia materia, eliminar del objeto de cambios
            delete horarioModificado[key];
        }
    }
    
    selectContainer.style.display = 'none';
    contenidoCelda.style.display = 'block';
    celda.classList.remove('materia-seleccionada');
    cambiosRealizados = Object.keys(horarioModificado).length > 0;
    actualizarPreview();
}

function cargarHorarioExistente() {
    Object.keys(horarioExistente).forEach(key => {
        const materia = horarioExistente[key];
        const [dia, horaInicio] = key.split('_');
        
        const hora = parseInt(horaInicio.split(':')[0]);
        let turno = 'manana';
        if(hora >= 14 && hora <= 18){
            turno = 'tarde';
        }
        
        const celda = document.querySelector(`[data-dia="${dia}"][data-turno="${turno}"][data-hora-inicio="${horaInicio}"]`);
        if(celda){
            const contenidoCelda = celda.querySelector('.contenido-celda');
            const select = celda.querySelector('.select-materia');
            
            contenidoCelda.innerHTML = `
                <strong>${materia.Nom_materia}</strong>
                <div class="info-materia">Materia asignada</div>
            `;
            celda.classList.remove('vacio');
            celda.classList.add('ocupado');
            select.value = materia.id_materia;
        }
    });
}

function actualizarPreview() {
    const previewTexto = document.getElementById('preview-texto');
    const totalCambios = Object.keys(horarioModificado).length;
    
    if(totalCambios > 0){
        const agregadas = Object.values(horarioModificado).filter(c => c.accion === 'agregar').length;
        const modificadas = Object.values(horarioModificado).filter(c => c.accion === 'modificar').length;
        const eliminadas = Object.values(horarioModificado).filter(c => c.accion === 'eliminar').length;
        
        let texto = `Listo para guardar ${totalCambios} cambio(s): `;
        const cambios = [];
        if(agregadas > 0) cambios.push(`${agregadas} nueva(s)`);
        if(modificadas > 0) cambios.push(`${modificadas} modificada(s)`);
        if(eliminadas > 0) cambios.push(`${eliminadas} eliminada(s)`);
        
        previewTexto.textContent = texto + cambios.join(', ');
    } else {
        previewTexto.textContent = 'No hay cambios pendientes. Haz clic en las celdas para modificar el horario.';
    }
}

// Inicializar
if(document.getElementById('cuerpo-horario-manana')){
    generarTablaHorario('manana');
    generarTablaHorario('tarde');
    if(Object.keys(horarioExistente).length > 0){
        cargarHorarioExistente();
    }
}

// Enviar formulario
const formHorario = document.getElementById('formHorario');
if(formHorario){
    formHorario.addEventListener('submit', function(e) {
        if(!cambiosRealizados){
            e.preventDefault();
            alert('No has realizado ningun cambio en el horario.');
            return false;
        }
        document.getElementById('horario_modificado').value = JSON.stringify(horarioModificado);
    });
}

// Notificaciones
document.addEventListener('DOMContentLoaded', function() {
    <?php if(isset($_SESSION['notificacion'])): ?>
        const notificacion = document.createElement('div');
        notificacion.style.cssText = `
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: <?= $_SESSION['notificacion']['tipo'] === 'exito' ? '#4CAF50' : '#F44336' ?>;
            color: white;
            padding: 12px 20px;
            border-radius: 8px;
            z-index: 9999;
            box-shadow: 0 4px 12px rgba(0,0,0,0.3);
            transform: translateX(400px);
            transition: transform 0.3s ease;
        `;
        notificacion.innerHTML = '<?= addslashes($_SESSION['notificacion']['mensaje']) ?>';
        document.body.appendChild(notificacion);
        
        setTimeout(() => notificacion.style.transform = 'translateX(0)', 100);
        setTimeout(() => {
            notificacion.style.transform = 'translateX(400px)';
            setTimeout(() => notificacion.remove(), 400);
        }, 5000);
        <?php unset($_SESSION['notificacion']); ?>
    <?php endif; ?>
});
</script>
</body>
</html>