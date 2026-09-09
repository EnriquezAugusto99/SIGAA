<?php
date_default_timezone_set('America/Argentina/Buenos_Aires');
session_start();

if(isset($_SESSION["dni"]) && (time() - $_SESSION["ultima_actividad"] > 18000)){
    echo '<script>alert("Se venció el tiempo de la sesion"); window.location="../index.php";</script>';
    exit();
}

if(!isset($_SESSION["dni"])){
    echo '<script>alert("Se venció el tiempo de la sesion"); window.location="../index.php";</script>';
    exit();
}

include 'conexion.php';

include '../../recursos/funciones.php';

// Verificar si el profesor es profesor de taller
$es_profesor_taller = false;
if($_SESSION['rol'] == 'Profesor'){
    $dni = $_SESSION['dni'];
    $query_taller = "SELECT COUNT(*) as total FROM docente_taller_curso WHERE ID_docente = '$dni'";
    $res_taller = mysqli_query($con, $query_taller);
    if($res_taller && $fila_taller = mysqli_fetch_assoc($res_taller)){
        $es_profesor_taller = ($fila_taller['total'] > 0);
    }
}

function esClaveDefault($dni, $con){
    $q = "SELECT clave FROM usuario WHERE DNI_U = '$dni'";
    $r = mysqli_query($con, $q);
    if($r && $f = mysqli_fetch_assoc($r)){
        return ($f['clave'] == $dni);
    }
    return false;
}

if($_SESSION['rol'] == 'Preceptor'){
    if(esClaveDefault($_SESSION['dni'], $con)){
        $_SESSION['pendiente_cambio_clave'] = true;
        echo '<script>alert("Debes cambiar tu contraseña por seguridad."); window.location="cambiar_contraseña.php";</script>';
        exit();
    }
    if(!isset($_SESSION['curso_activo_preceptor'])){
        header("Location: ../modulos/cursos/seleccionar_curso.php");
        exit();
    }
    $id_curso = $_SESSION['curso_activo_preceptor'];
    $val_query = "SELECT 1 FROM preceptorxcurso WHERE id_preceptor = '{$_SESSION['dni']}' AND id_curso = '$id_curso'";
    $val_res = mysqli_query($con, $val_query);
    if(mysqli_num_rows($val_res) == 0){
        unset($_SESSION['curso_activo_preceptor']);
        header("Location: ../modulos/cursos/seleccionar_curso.php");
        exit();
    }
    $q_curso = "SELECT curso, division, turno FROM curso WHERE ID_curso = '$id_curso'";
    $r_curso = mysqli_query($con, $q_curso);
    $curso_data = mysqli_fetch_assoc($r_curso);
    $turno_txt = ($curso_data['turno'] == 'M') ? 'Mañana' : 'Tarde';
    $curso_activo_nombre = $curso_data['curso'] . '° "' . $curso_data['division'] . '" - ' . $turno_txt;
}

$injustificadas = 0;
$justificadas = 0;
if($_SESSION['rol'] == 'Estudiante'){
    $dni_alumno = $_SESSION['dni'];
    $query = "SELECT 
                SUM(CASE WHEN justificada = 0 THEN 1 ELSE 0 END) as injustificadas,
                SUM(CASE WHEN justificada = 1 THEN 1 ELSE 0 END) as justificadas
              FROM inasistencias 
              WHERE id_alumno = '$dni_alumno'";
    $res = mysqli_query($con, $query);
    if($res && $fila = mysqli_fetch_array($res)){
        $injustificadas = (int)$fila['injustificadas'];
        $justificadas = (int)$fila['justificadas'];
    }
}

// ==================== CONSULTA DE NOVEDADES ====================
$dni = $_SESSION['dni'];
$rol_usuario = $_SESSION['rol'];

// Obtener ID del rol
$queryRol = "SELECT ID_rol FROM usuario WHERE DNI_U = '$dni'";
$resRol = mysqli_query($con, $queryRol);
$id_rol = ($resRol && $fila = mysqli_fetch_assoc($resRol)) ? $fila['ID_rol'] : 0;

// Curso del estudiante
$id_curso_alumno = null;
if($rol_usuario == 'Estudiante'){
    $queryCurso = "SELECT id_curso FROM usuario WHERE DNI_U = '$dni'";
    $resCurso = mysqli_query($con, $queryCurso);
    if($resCurso && $fila = mysqli_fetch_assoc($resCurso)) $id_curso_alumno = $fila['id_curso'];
}

// Cursos que dicta (profesor)
$cursos_profesor = [];
if($rol_usuario == 'Profesor'){
    $queryCursosProf = "SELECT DISTINCT id_curso FROM docentemateriacurso WHERE id_docente = '$dni'";
    $resCursosProf = mysqli_query($con, $queryCursosProf);
    while($cp = mysqli_fetch_assoc($resCursosProf)) $cursos_profesor[] = $cp['id_curso'];
}

// Cursos que gestiona (preceptor)
$cursos_preceptor = [];
if($rol_usuario == 'Preceptor'){
    $queryCursosPre = "SELECT id_curso FROM preceptorxcurso WHERE id_preceptor = '$dni'";
    $resCursosPre = mysqli_query($con, $queryCursosPre);
    while($cp = mysqli_fetch_assoc($resCursosPre)) $cursos_preceptor[] = $cp['id_curso'];
}

// Cursos de los hijos (tutor)
$cursos_tutor = [];
if($rol_usuario == 'Tutor'){
    $queryCursosTutor = "SELECT u.id_curso FROM alumnoxtutor a
                         INNER JOIN usuario u ON a.id_alumno = u.DNI_U
                         WHERE a.id_tutor = '$dni' AND u.id_curso > 0";
    $resCursosTutor = mysqli_query($con, $queryCursosTutor);
    while($ct = mysqli_fetch_assoc($resCursosTutor)) $cursos_tutor[] = $ct['id_curso'];
    $cursos_tutor = array_unique($cursos_tutor);
}

$hoy = date('Y-m-d');
$sql_novedades = "SELECT n.*, u.Nombre as emisor_nombre, u.Apellido as emisor_apellido
                  FROM novedades n
                  INNER JOIN usuario u ON n.id_emisor = u.DNI_U
                  WHERE n.vencimiento_novedad > '$hoy'";

$condiciones = [];
// Para todos
$condiciones[] = "NOT EXISTS (SELECT 1 FROM novedades_roles nr WHERE nr.id_novedad = n.id_novedad)
                  AND NOT EXISTS (SELECT 1 FROM novedades_cursos nc WHERE nc.id_novedad = n.id_novedad)";
// Por rol
$condiciones[] = "EXISTS (SELECT 1 FROM novedades_roles nr WHERE nr.id_novedad = n.id_novedad AND nr.ID_rol = '$id_rol')";
// Por cursos según rol
if($rol_usuario == 'Estudiante' && $id_curso_alumno){
    $condiciones[] = "EXISTS (SELECT 1 FROM novedades_cursos nc WHERE nc.id_novedad = n.id_novedad AND nc.ID_curso = '$id_curso_alumno')";
}
if($rol_usuario == 'Profesor' && !empty($cursos_profesor)){
    $cursos_str = implode(',', $cursos_profesor);
    $condiciones[] = "EXISTS (SELECT 1 FROM novedades_cursos nc WHERE nc.id_novedad = n.id_novedad AND nc.ID_curso IN ($cursos_str))";
}
if($rol_usuario == 'Preceptor' && !empty($cursos_preceptor)){
    $cursos_str = implode(',', $cursos_preceptor);
    $condiciones[] = "EXISTS (SELECT 1 FROM novedades_cursos nc WHERE nc.id_novedad = n.id_novedad AND nc.ID_curso IN ($cursos_str))";
}
if($rol_usuario == 'Tutor' && !empty($cursos_tutor)){
    $cursos_str = implode(',', $cursos_tutor);
    $condiciones[] = "EXISTS (SELECT 1 FROM novedades_cursos nc WHERE nc.id_novedad = n.id_novedad AND nc.ID_curso IN ($cursos_str))";
}

if(!empty($condiciones)){
    $sql_novedades .= " AND (" . implode(" OR ", $condiciones) . ")";
} else {
    $sql_novedades .= " AND 1=0";
}
$sql_novedades .= " ORDER BY n.emision_novedad DESC LIMIT 10";
$res_novedades = mysqli_query($con, $sql_novedades);
$novedades = [];
while($row = mysqli_fetch_assoc($res_novedades)){
    $novedades[] = $row;
}
// ==================== FIN NOVEDADES ====================
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Sistema Escolar - Panel Principal</title>
    <link rel="stylesheet" href="styles_panel.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --dark-red: #3F070B;
            --deep-crimson: #710A14;
            --dark-burgundy: #180605;
            --dusty-rose: #818582;
            --rustic-red: #8F3C45;
            --light-bg: #f5f5f5;
            --card-bg: #ffffff;
            --border-color: #e0e0e0;
            --text-dark: #2c2c2c;
            --text-muted: #666666;
        }

        .btn-cambiar-curso {
            background-color: #FF9800;
            color: #1a2a3a;
            border: none;
            padding: 4px 10px;
            border-radius: 4px;
            text-decoration: none;
            font-size: 0.75rem;
            transition: 0.3s;
            display: inline-block;
            margin-left: 8px;
        }
        .btn-cambiar-curso:hover {
            background-color: #FFC107;
            color: #000;
            transform: scale(1.02);
        }
        
        .user-info {
            background: rgba(64, 224, 208, 0.1);
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 15px;
            text-align: center;
            border: 1px solid rgba(64, 224, 208, 0.3);
        }
        .user-info h6 {
            color: #40E0D0;
            margin-bottom: 5px;
        }
        .user-info small {
            color: #E0F7FA;
            font-size: 0.8rem;
        }
        .btn-outline-info {
            margin-top: 8px;
            font-size: 0.75rem;
            padding: 4px 8px;
        }
        .alert-info {
            background: rgba(33, 150, 243, 0.2);
            border-color: #2196F3;
            color: #E0F7FA;
        }
        .estadisticas-inasistencias {
            font-size: 0.8rem;
            padding: 5px 0 5px 20px;
        }
        .estadisticas-inasistencias span {
            display: block;
            padding: 2px 0;
        }
        .badge-injustificadas {
            color: #ff9999;
        }
        .badge-justificadas {
            color: #a5d6a7;
        }

        .content-area {
            background: #ffffff;
        }
        .welcome-card {
            background: linear-gradient(135deg, var(--deep-crimson) 0%, var(--dark-red) 100%);
            color: white;
            border-radius: 16px;
        }
        .module-card {
            background: var(--card-bg);
            border-radius: 16px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.08);
            margin-bottom: 25px;
        }
        
        /* Novedades - estilos responsivos */
        .novedades-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
            border-bottom: 2px solid var(--deep-crimson);
            padding-bottom: 12px;
            margin-bottom: 20px;
        }
        .novedades-header h4 {
            font-weight: 700;
            color: var(--dark-burgundy);
            margin: 0;
            font-size: 1.2rem;
        }
        .btn-nueva-novedad {
            background: transparent;
            border: 2px solid var(--deep-crimson);
            color: var(--deep-crimson);
            padding: 6px 16px;
            border-radius: 30px;
            font-weight: 600;
            font-size: 0.8rem;
            transition: 0.2s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            white-space: nowrap;
        }
        .btn-nueva-novedad:hover {
            background: var(--deep-crimson);
            color: white;
            transform: translateY(-2px);
        }
        .novedades-lista {
            display: flex;
            flex-direction: column;
            gap: 16px;
        }
        .novedad-item {
            background: #fef9f9;
            border-left: 4px solid var(--deep-crimson);
            border-radius: 12px;
            padding: 16px;
            transition: 0.2s;
            width: 100%;
            box-sizing: border-box;
            overflow-x: auto;
        }
        .novedad-item:hover {
            background: #fff5f5;
            transform: translateX(3px);
        }
        .novedad-titulo {
            font-weight: 700;
            color: var(--dark-red);
            font-size: 1rem;
            margin-bottom: 6px;
            word-break: break-word;
            overflow-wrap: break-word;
        }
        .novedad-descripcion {
            color: var(--text-dark);
            font-size: 0.85rem;
            line-height: 1.4;
            margin-bottom: 10px;
            word-break: break-word;
            overflow-wrap: break-word;
        }
        .novedad-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            font-size: 0.8rem;
            color: var(--text-muted);
            margin-top: 8px;
        }
        .novedad-meta span {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            white-space: normal;
            word-break: break-word;
        }
        .novedad-meta i {
            width: 18px;
            color: var(--deep-crimson);
            flex-shrink: 0;
        }
        .no-novedades {
            text-align: center;
            color: var(--text-muted);
            padding: 30px;
            background: #fafafa;
            border-radius: 12px;
        }
        
        /* Acordeón más compacto */
        .accordion-item {
            margin-bottom: 8px;
        }
        .accordion-button {
            padding: 10px 16px;
            background: #f8f9fa;
            color: var(--dark-burgundy);
            font-weight: 500;
        }
        .accordion-button:not(.collapsed) {
            background: #f0f0f0;
            color: var(--deep-crimson);
        }
        .accordion-body {
            padding: 12px 16px;
        }
        #panelSistema {
            margin-top: 10px;
        }
        
        .btn-panel {
            background: none;
            border: none;
            font-weight: 600;
            font-size: 1.1rem;
            color: var(--dark-burgundy);
        }

        /* Responsive para móviles */
        @media (max-width: 768px) {
            .novedades-header {
                flex-direction: column;
                align-items: flex-start;
            }
            .btn-nueva-novedad {
                white-space: normal;
                font-size: 0.75rem;
                padding: 4px 12px;
            }
            .novedad-meta {
                flex-direction: column;
                gap: 6px;
                font-size: 0.7rem;
            }
            .novedad-item {
                padding: 12px;
            }
            .novedad-titulo {
                font-size: 0.95rem;
            }
            .novedad-descripcion {
                font-size: 0.8rem;
            }
            .module-card {
                padding: 16px !important;
            }
            .welcome-card h2 {
                font-size: 1.4rem;
            }
            .welcome-card h4 {
                font-size: 1rem;
            }
            .btn-custom {
                font-size: 0.75rem;
                padding: 6px 12px;
            }
        }
    </style>
</head>
<body>
    <div class="main-container">
        <div class="container-fluid">
            <div class="row">
                <div class="col-md-3 col-lg-2 sidebar p-0">
                    <div class="p-4">
                        <div class="logo">
                            <img src="logoepet.png" alt="Logo institución">
                        </div>
                        <div class="user-info">
                            <h6 class="mb-1"><?php echo $_SESSION['nombre'] . ' ' . $_SESSION['apellido']; ?></h6>
                            <small><?php echo $_SESSION['rol']; ?></small>
                            <a href="cambiar_contraseña.php" class="btn btn-sm btn-outline-info w-100" style="font-size: 0.8rem;">
                                Cambiar Contraseña
                            </a>
                        </div>
                        <?php if($_SESSION['rol'] == 'Preceptor'): ?>
                            <div class="alert alert-info text-center p-2 mt-2" style="font-size:0.8rem; background: rgba(33, 150, 243, 0.2); border-color: #2196F3; color: #E0F7FA;">
                                <strong>Curso activo:</strong> <?= $curso_activo_nombre ?>
                                <a href="../modulos/cursos/seleccionar_curso.php" class="btn-cambiar-curso">Cambiar curso</a>
                            </div>
                        <?php endif; ?>

                        <nav class="nav flex-column">
                            <?php if($_SESSION['rol'] == 'Admin'): ?>
                                <a class="nav-link" href="#gestion-academica" data-bs-toggle="collapse">📚 Gestión Académica</a>
                                <div class="collapse show" id="gestion-academica">
                                    <div class="nav flex-column ms-3">
                                        <a class="nav-link" href="../modulos/materias/materia.php">Materias</a>
                                        <a class="nav-link" href="../modulos/cursos/cursos.php">Cursos</a>
                                        <a class="nav-link" href="../modulos/horarios/horario.php">Horarios</a>
                                        <a class="nav-link" href="../modulos/calificaciones/calificaciones.php">Calificaciones</a>
                                        <a class="nav-link" href="../modulos/usuarios/usuario.php">Usuarios</a>
                                        <a class="nav-link" href="../modulos/aulas/aulas.php">Aulas</a>
                                        <a class="nav-link" href="../modulos/previas/listado_previas.php">Previas</a>
                                        <a class="nav-link" href="../modulos/inasistencias/inasistencias.php">Inasistencias</a>
                                        <a class="nav-link" href="../modulos/Libreta Electronica/control_recepcion.php">Libretas Recibidas</a>
<a class="nav-link" href="../modulos/Libreta Electronica/configurar_libreta.php">Configuracion Visualizacion Libreta</a>
                                    </div>
                                </div>
                                <a class="nav-link" href="#novedades-admin" data-bs-toggle="collapse">📢 Novedades</a>
                                <div class="collapse show" id="novedades-admin">
                                    <div class="nav flex-column ms-3">
                                        <a class="nav-link" href="../modulos/novedades/enviar_novedad.php">Enviar Novedad</a>
                                    </div>
                                </div>                            
                                <a class="nav-link" href="#gestion-personas" data-bs-toggle="collapse">👥 Talleres</a>
                                <div class="collapse show" id="gestion-personas">
                                    <div class="nav flex-column ms-3">
                                        <a class="nav-link" href="../modulos/talleres/talleres.php">Talleres</a>
                                        <a class="nav-link" href="../modulos/DocentexTallerxCurso/docente_taller_curso.php">Docente x Taller x Curso</a>
                                        <a class="nav-link" href="../modulos/rotaciones/rotaciones.php">Rotaciones</a>
                                        <a class="nav-link" href="../modulos/Grupo_Taller/grupos_taller.php">Grupos Talleres</a>
                                        <a class="nav-link" href="../modulos/calificaciones_taller/calificaciones_taller.php">Calificaciones Taller</a>
                                        <a class="nav-link" href="../modulos/planilla_rotacion/planilla_rotaciones_config.php">Planilla Rotaciones</a>
                                    </div>
                                </div>
                                <a class="nav-link" href="#relaciones" data-bs-toggle="collapse">🔗 Relaciones</a>
                                <div class="collapse show" id="relaciones">
                                    <div class="nav flex-column ms-3">
                                        <a class="nav-link" href="../modulos/tutorxalumno/tutorxalumno.php">Alumno x Tutor</a>
                                        <a class="nav-link" href="../modulos/DocentexMateriaxCurso/docentemateriacurso.php">Docente x Materia x Curso</a>
                                        <a class="nav-link" href="../modulos/preceptorxcurso/preceptorxcurso.php"> Preceptor x Curso</a>
                                        <a class="nav-link" href="../modulos/usuario_rol/usuario_rol.php">Roles Adicionales</a>
                                        <a class="nav-link" href="../modulos/tutorxalumno/listado_tutores_alumnos.php">Tutores</a>
                                    </div>
                                </div>
                                <a class="nav-link" href="#seguimiento" data-bs-toggle="collapse">📊 Seguimiento</a>
                                <div class="collapse show" id="seguimiento">
                                    <div class="nav flex-column ms-3">
                                        <a class="nav-link" href="../modulos/inasistencias/inasistencias.php">Inasistencias</a>
                                        <a class="nav-link" href="../modulos/Libreta Electronica/libreta_admin_preceptor.php">Libreta Electrónica</a>
                                        <a class="nav-link" href="../modulos/calificaciones/abc_promedio.php">📊 ABC de Promedios</a>
                                        <a class="nav-link" href="../modulos/Libreta Electronica/enviar_libreta_masiva.php" style="color: #FFD700;">📧 Envío Masivo de Libretas</a>
                                        <a class="nav-link" href="../modulos/verificar_notas/verificar_notas_por_curso.php">Seguimiento Notas</a>     
                                    </div>
                                    <a class="nav-link" href="#novedades-admin" data-bs-toggle="collapse">🙋🏻‍♂️ Invitado</a>
                                    <div class="collapse show" id="novedades-admin">
                                        <div class="nav flex-column ms-3">
                                            <a class="nav-link" href="../modulos/invitado/registrar_tutor.php">Invitado</a>
                                        </div>
                                    </div> 
                                </div>

                            <?php elseif($_SESSION['rol'] == 'Estudiante'): ?>
                                <a class="nav-link active" href="../modulos/Libreta Electronica/libreta_alumno.php">📓 Mi Libreta Electrónica</a>
                                <a class="nav-link" href="../modulos/horarios/mi_horario.php">🕒 Ver mis horarios</a>
<a class="nav-link" href="../modulos/qr_asistencia/qr_asistencia.php">📱 QR de Asistencia</a> 
                                <div class="collapse" id="inasistencias-alumno">
                                    <div class="estadisticas-inasistencias">
                                        <span class="badge-injustificadas">❌ Injustificadas: <?php echo $injustificadas; ?></span>
                                        <span class="badge-justificadas">✅ Justificadas: <?php echo $justificadas; ?></span>
                                    </div>
                                </div>
                                <?php if($dni == 48740876): ?>
                                     <a class="nav-link" href="../modulos/qr_asistencia/qr_asistencia.php">📱 QR de Asistencia</a> 
                                <?php endif; ?>

                            <?php elseif($_SESSION['rol'] == 'Preceptor'): ?>
                                <a class="nav-link" href="#gestion-preceptor" data-bs-toggle="collapse">📚 Gestión Académica</a>
                                <div class="collapse show" id="gestion-preceptor">
                                    <div class="nav flex-column ms-3">
                                        <a class="nav-link" href="../modulos/materias/listado_materia.php">Materias</a>
                                        <a class="nav-link" href="../modulos/usuarios/listado_usuario.php">Alumnos</a>
                                        <a class="nav-link" href="../modulos/cursos/listado_curso.php">Cursos</a>
                                        <a class="nav-link" href="../modulos/horarios/horario.php">Horarios</a>
                                        <a class="nav-link" href="../modulos/calificaciones/calificaciones.php">Planillas</a>
                                        <a class="nav-link" href="../modulos/previas/listado_previas.php">Previas</a>
                                        <a class="nav-link" href="../modulos/Libreta Electronica/control_recepcion.php">Libretas Recibidas</a>
                                        <a class="nav-link" href="../modulos/tutorxalumno/listado_tutores_alumnos.php">Tutores</a>
                                    </div>
                                </div>
                                <a class="nav-link" href="#novedades-preceptor" data-bs-toggle="collapse">📢 Novedades</a>
                                <div class="collapse show" id="novedades-preceptor">
                                    <div class="nav flex-column ms-3">
                                        <a class="nav-link" href="../modulos/novedades/enviar_novedad.php">Enviar Novedad</a>
                                    </div>
                                </div>
                                <a class="nav-link" href="#seguimiento-preceptor" data-bs-toggle="collapse">📊 Seguimiento</a>
                                <div class="collapse show" id="seguimiento-preceptor">
                                    <div class="nav flex-column ms-3">
                                        <a class="nav-link" href="../modulos/Libreta Electronica/libreta_admin_preceptor.php">Libreta Electrónica</a>
                                       <!-- INASISTENCIAS NO IMPLEMENTADO AUN  <a class="nav-link" href="../modulos/inasistencias/inasistencia.php">Inasistencias</a> --> 
                                        <a class="nav-link" href="../modulos/calificaciones/abc_promedio.php">📈 ABC de Promedios</a>
                                        <a class="nav-link" href="../modulos/Libreta Electronica/enviar_libreta_masiva.php" style="color: #FFD700;">📧 Envío Masivo de Libretas</a>
                                        <a class="nav-link" href="../modulos/verificar_notas/verificar_notas_por_curso.php">Seguimiento Notas</a>
                                    </div>
                                </div>

                            <?php elseif($_SESSION['rol'] == 'Profesor'): ?>
                                <a class="nav-link active" href="../modulos/calificaciones/calificaciones.php">📝 Cargar Calificaciones</a>
                                <a class="nav-link active" href="../modulos/horarios/horario_docente.php">🕒 Ver mis horarios</a>
                                <?php if($es_profesor_taller): ?>
                                    <a class="nav-link" href="#gestion-taller" data-bs-toggle="collapse">🔧 Talleres</a>
                                    <div class="collapse show" id="gestion-taller">
                                        <div class="nav flex-column ms-3">
                                            <a class="nav-link" href="../modulos/calificaciones_taller/calificaciones_taller.php">Calificaciones Taller</a>
                                            <a class="nav-link" href="../modulos/rotaciones/rotaciones_listado.php">Rotaciones</a>
                                            <a class="nav-link" href="../modulos/planilla_rotacion/planilla_rotaciones_listadoProfes.php">Planilla Rotaciones</a>
                                        </div>
                                    </div>
                                <?php endif; ?>

                            <?php elseif($_SESSION['rol'] == 'Secretario'): ?>
                                <a class="nav-link" href="../modulos/usuarios/usuario.php">👥 Gestión de Usuarios</a>
                                <a class="nav-link" href="../modulos/calificaciones/abc_promedio.php">📈 ABC de Promedios</a>
                                
                            <?php elseif($_SESSION['rol'] == 'Invitado'): ?>
                                <a class="nav-link" href="../modulos/invitado/registrar_tutor.php">Tutores</a>
                                <a class="nav-link" href="../modulos/Libreta Electronica/libreta_admin_preceptor.php">Libreta Electrónica</a>
                                
                            <?php elseif($_SESSION['rol'] == 'Tutor'): ?>
                                <a class="nav-link active" href="../modulos/Libreta Electronica/libreta_tutor.php">📓 Ver Libreta del Alumno</a>

                            <?php elseif($_SESSION['rol'] == 'Equipo de Orientacion'): ?>
                                <a class="nav-link" href="#gestion-academica" data-bs-toggle="collapse">📚 Gestión Académica</a>
                                <div class="collapse show" id="gestion-academica">
                                    <div class="nav flex-column ms-3">
                                        <a class="nav-link" href="../modulos/horarios/horario_detalle.php">Horarios</a>
                                        <a class="nav-link" href="../modulos/previas/listado_previas.php">Previas</a>
                                        <a class="nav-link" href="../modulos/Libreta Electronica/control_recepcion.php">Libretas Recibidas</a>
                                    </div>
                                </div>

                                <a class="nav-link" href="#seguimiento" data-bs-toggle="collapse">📊 Seguimiento</a>
                                <div class="collapse show" id="seguimiento">
                                    <div class="nav flex-column ms-3">
                                        <a class="nav-link" href="../modulos/Libreta Electronica/libreta_admin_preceptor.php">Libreta Electrónica</a>
                                    </div>

                                <a class="nav-link" href="#gestion-personas" data-bs-toggle="collapse">👥 Talleres</a>
                                <div class="collapse show" id="gestion-personas">
                                    <div class="nav flex-column ms-3">
                                        <a class="nav-link" href="../modulos/Grupo_Taller/listado_grupos_taller.php">Grupos Talleres</a>
                                        <a class="nav-link" href="../modulos/planilla_rotacion/planilla_rotaciones_listado.php">Planilla Rotaciones</a>
                                    </div>
                                </div>  
                                
                                <a class="nav-link" href="#relaciones" data-bs-toggle="collapse">🔗 Relaciones</a>
                                <div class="collapse show" id="relaciones">
                                    <div class="nav flex-column ms-3">
                                        <a class="nav-link" href="../modulos/tutorxalumno/listado_tutores_alumnos.php">Tutores</a>
                                    </div>
                                </div>
                                
                            <?php else: ?>
                                <a class="nav-link active" href="#">⚠️ Rol no configurado</a>
                            <?php endif; ?>

                            <div class="mt-4">
                                <a class="nav-link text-warning" href="cerrar_sesion.php">🚪 Cerrar Sesión</a>
                            </div>
                        </nav>
                    </div>
                </div>

                <div class="col-md-9 col-lg-10 content-area">
                    <div class="welcome-card p-4 mb-4">
    <div class="row align-items-center">
        <div class="col-md-8">
            <h2>Sigaa</h2>
            <p class="mb-0" style="color: white;">Sistema integral de gestión académica y administrativa</p>
        </div>
        <div class="col-md-4 text-end">
            <h4><?php echo $_SESSION['nombre'] . ' ' . $_SESSION['apellido']; ?></h4>
            <small style="color: white;"><?php echo $_SESSION['rol_activo']; ?></small>
        </div>
    </div>
</div>

<!-- Selector de roles múltiples - AHORA FUERA DEL welcome-card -->
<?php if(isset($_SESSION['tiene_multiples_roles']) && $_SESSION['tiene_multiples_roles'] == 1 && !empty($_SESSION['roles_adicionales'])): ?>
<div style="background: #f0f0f0; padding: 12px 15px; margin: 0 0 20px 0; border-radius: 8px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px;">
    <div style="display: flex; align-items: center; gap: 10px;">
        <span style="font-weight: 600;"> Actuando como:</span>
        <select id="selector_rol" style="padding: 6px 12px; border-radius: 5px; border: 1px solid #ccc;">
            <option value="<?= $_SESSION['rol_principal'] ?>" <?= ($_SESSION['rol_activo'] == $_SESSION['rol_principal']) ? 'selected' : '' ?>>
                <?= $_SESSION['rol_principal'] ?> (Principal)
            </option>
            <?php foreach($_SESSION['roles_adicionales'] as $rol): ?>
                <option value="<?= $rol ?>" <?= ($_SESSION['rol_activo'] == $rol) ? 'selected' : '' ?>>
                    <?= $rol ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <span style="font-size: 15px; color: #666;"> Cambia tu rol para ver diferentes módulos</span>
</div>

<script>
document.getElementById('selector_rol')?.addEventListener('change', function() {
    const rol = this.value;
    fetch('../recursos/cambiar_rol_activo.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'rol=' + encodeURIComponent(rol)
    })
    .then(res => res.json())
    .then(data => {
        if(data.success) location.reload();
        else alert('Error: ' + data.message);
    })
    .catch(err => alert('Error al cambiar de rol'));
});
</script>
<?php endif; ?>

                    <div class="row mt-4">
                        <div class="col-12">
                            <div class="module-card p-4">
                                <h5>Accesos Rápidos</h5>
                                <div class="d-flex flex-wrap gap-2">
                                    <?php if($_SESSION['rol'] == 'Admin'): ?>
                                        <a href="../modulos/Libreta Electronica/libreta_admin_preceptor.php" class="btn-custom">Ver Libretas</a>
                                        <a href="../modulos/calificaciones/calificaciones.php" class="btn-custom">Calificaciones</a>
                                        <a href="../modulos/usuarios/usuario.php" class="btn-custom">Usuarios</a>
                                        <a href="../modulos/novedades/enviar_novedad.php" class="btn-custom">Nueva Novedad</a>
<a class="btn-custom" href="../modulos/previas/listado_previas.php">Previas</a>
<a class="btn-custom" href="../modulos/estadisticas/estadisticas.php">Estadisticas</a>
                                    <?php elseif($_SESSION['rol'] == 'Estudiante'): ?>
                                        <a href="../modulos/Libreta Electronica/libreta_alumno.php" class="btn-custom">Mi Libreta</a>
                                    <?php elseif($_SESSION['rol'] == 'Preceptor'): ?>
                                        <a href="../modulos/Libreta Electronica/libreta_admin_preceptor.php" class="btn-custom">Libretas</a>
                                        <!-- INASISTENCIAS NO IMPLEMENTADO AUN <a href="../modulos/inasistencias/inasistencia.php" class="btn-custom">Inasistencias</a> -->
                                        <a href="../modulos/novedades/enviar_novedad.php" class="btn-custom">Nueva Novedad</a>
<a class="btn-custom" href="../modulos/previas/listado_previas.php">Previas</a>
                                    <?php elseif($_SESSION['rol'] == 'Profesor'): ?>
                                        <a href="../modulos/calificaciones/calificaciones.php" class="btn-custom">Cargar Calificaciones</a>
                                        <?php if($es_profesor_taller): ?>
                                            <a href="../modulos/calificaciones_taller/calificaciones_taller.php" class="btn-custom">Calificaciones Taller</a>
                                        <?php endif; ?>
                                    <?php elseif($_SESSION['rol'] == 'Secretario'): ?>
                                        <a href="../modulos/usuarios/usuario.php" class="btn-custom">Usuarios</a>
                                    <?php elseif($_SESSION['rol'] == 'Tutor'): ?>
                                        <a href="../modulos/Libreta Electronica/libreta_tutor.php" class="btn-custom">Ver Libreta</a>
                                    <?php endif; ?>

<?php if($_SESSION['dni'] == 48265827 || 28084714 || 28065416 || 29801405): ?>
                                        <a href="../modulos/qr_asistencia/verificar_biometria.php" class="btn-custom">Verificar QR</a>

<?php endif; ?>

<?php
// Verificar email para mostrar color correcto
$dni_actual = $_SESSION['dni'];
$query_email_check = "SELECT email FROM usuario WHERE DNI_U = '$dni_actual'";
$res_email_check = mysqli_query($con, $query_email_check);
$tiene_email = false;
if($res_email_check && $fila_email = mysqli_fetch_assoc($res_email_check)){
    $tiene_email = !empty($fila_email['email']);
}
?>
<a href="../modulos/gestion_email/gestion_email.php" class="btn-custom" style="background: <?= $tiene_email ? '#28a745' : '#dc3545' ?>; color: white;">
    <i class="fas fa-envelope"></i> <?= $tiene_email ? 'Email' : '⚠️ Sin email' ?>
</a>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- SECCIÓN DE NOVEDADES VIGENTES -->
                    <?php if(!empty($novedades)): ?>
                    <div class="row mt-4">
                        <div class="col-12">
                            <div class="module-card p-4">
                                <div class="novedades-header">
                                    <h4><i class="fas fa-bullhorn" style="color: var(--deep-crimson); margin-right: 8px;"></i> Novedades vigentes</h4>
                                    <?php if($_SESSION['rol'] == 'Admin' || $_SESSION['rol'] == 'Preceptor'): ?>
                                        <a href="../modulos/novedades/enviar_novedad.php" class="btn-nueva-novedad"><i class="fas fa-plus"></i> Nueva Novedad</a>
                                    <?php endif; ?>
                                </div>
                                <div class="novedades-lista">
                                    <?php foreach($novedades as $nov): ?>
                                        <div class="novedad-item">
                                            <div class="novedad-titulo"><?= htmlspecialchars($nov['titulo_novedad']) ?></div>
                                            <div class="novedad-descripcion"><?= nl2br(htmlspecialchars($nov['descripcion_novedad'])) ?></div>
                                            <div class="novedad-meta">
                                                <span><i class="fas fa-user"></i> <?= htmlspecialchars($nov['emisor_nombre'] . ' ' . $nov['emisor_apellido']) ?></span>
                                                <span><i class="fas fa-calendar-alt"></i> Publicado: <?= date('d/m/Y H:i', strtotime($nov['emision_novedad'])) ?></span>
                                                <span><i class="fas fa-hourglass-end"></i> Vence: <?= date('d/m/Y', strtotime($nov['vencimiento_novedad'])) ?></span>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <div class="row mb-4">
                        <div class="col-12">
                           <div class="module-card p-4">
                                <h4>
                                    <button class="btn-panel w-100 text-start" type="button" data-bs-toggle="collapse" data-bs-target="#panelSistema">
                                        ¿Qué puedes hacer en el sistema?
                                    </button>
                                </h4>
                                <div id="panelSistema" class="collapse">
                                    <div class="accordion mt-2" id="systemInfo">

<?php if($_SESSION['rol'] == 'Admin'): ?>                                        

                                           <div class="accordion-item" style="background: transparent; border: 1px solid #e0e0e0;">
                                            <h2 class="accordion-header">
                                                 <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#info1">
                                                    Gestión Académica
                                                </button> 

                                            </h2>
                                            <div id="info1" class="accordion-collapse collapse" data-bs-parent="#systemInfo">
                                                <div class="accordion-body" style="color: #2c2c2c;">
                                                    Administra materias, cursos, horarios y calificaciones del sistema educativo.
                                                </div>
                                            </div>
                                        </div>
<?php endif; ?>

<?php if($_SESSION['rol'] == 'Admin'): ?> 
                                        <div class="accordion-item" style="background: transparent; border: 1px solid #e0e0e0;">
                                            <h2 class="accordion-header">
                                                 <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#info2">
                                                    Gestión de Personas
                                                </button>

                                            </h2>
                                            <div id="info2" class="accordion-collapse collapse" data-bs-parent="#systemInfo">
                                                <div class="accordion-body" style="color: #2c2c2c;">
                                                    Gestiona todos los usuarios del sistema en una sola tabla unificada.
                                                </div>
                                            </div>
                                        </div>
<?php endif; ?>

                                        <div class="accordion-item" style="background: transparent; border: 1px solid #e0e0e0;">
                                            <h2 class="accordion-header">
                                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#info3">
                                                    Seguimiento Académico
                                                </button>
                                            </h2>
                                            <div id="info3" class="accordion-collapse collapse" data-bs-parent="#systemInfo">
                                                <div class="accordion-body" style="color: #2c2c2c;">
                                                    Control de previas, inasistencias y libretas electrónicas para el seguimiento estudiantil.
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <?php if($_SESSION['rol'] == 'Admin'): ?>
                        <div class="col-md-4">
                            <div class="module-card text-center p-4">
                                <h5>🎓</h5>
                                <h6>Gestión Académica</h6>
                                <small>Completa y organizada</small>
                            </div>
                        </div>
                        
                        <div class="col-md-4">
                            <div class="module-card text-center p-4">
                                <h5>👥</h5>
                                <h6>Usuarios Unificados</h6>
                                <small>Todos en una tabla</small>
                            </div>
                        </div>
                        <?php endif; ?>
                        <div class="col-md-4">
                            <div class="module-card text-center p-4">
                                <h5>📈</h5>
                                <h6>Seguimiento Continuo</h6>
                                <small>Monitoreo en tiempo real</small>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>