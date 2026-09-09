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

// Verificar permisos - Preceptor, Admin, Secretario y Equipo de Orientacion pueden ver horarios
$es_admin = ($_SESSION['rol'] == 'Admin');
$es_preceptor = ($_SESSION['rol'] == 'Preceptor');
$es_secretario = ($_SESSION['rol'] == 'Secretario');
$es_equipo = ($_SESSION['rol'] == 'Equipo de Orientacion');

if(!$es_admin && !$es_preceptor && !$es_secretario && !$es_equipo){
    echo '<script>alert("No tiene permisos para ver horarios"); window.location="../../recursos/panel.php";</script>';
    exit();
}

include '../../recursos/conexion.php';

$error = '';
$success = '';
$curso_info = null;
$horario_curso = [];
$tiene_horario = false;
$es_solo_lectura = $es_equipo;

// =====================================================
// OBTENER EL CURSO ACTIVO
// Admin y Equipo pueden seleccionar cualquier curso
// Preceptor y Secretario usan el curso de la sesion
// =====================================================
$id_curso = 0;

if($es_admin || $es_equipo){
    // Admin y Equipo: pueden venir de GET (desde selector) o de sesion
    if(isset($_GET['id_curso']) && $_GET['id_curso'] > 0){
        $id_curso = (int)$_GET['id_curso'];
        $_SESSION['curso_activo_preceptor'] = $id_curso;
    } else {
        $id_curso = $_SESSION['curso_activo_preceptor'] ?? 0;
    }
} else {
    // Preceptor y Secretario: usan el curso activo de la sesion
    $id_curso = $_SESSION['curso_activo_preceptor'] ?? 0;
}

// Lista de cursos para el selector (Admin y Equipo)
$lista_cursos = [];
if($es_admin || $es_equipo){
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
    // Verificar permisos segun rol (solo Preceptor necesita verificar asignación)
    $tiene_permiso = true;
    
    if($es_preceptor){
        $dni_preceptor = $_SESSION["dni"];
        $query_verificar = "SELECT * FROM preceptorxcurso WHERE id_preceptor = '$dni_preceptor' AND id_curso = '$id_curso'";
        $res_verificar = mysqli_query($con, $query_verificar);
        if(mysqli_num_rows($res_verificar) == 0){
            $error = "No tiene permisos para ver el horario de este curso.";
            $tiene_permiso = false;
            $id_curso = 0;
        }
    } elseif($es_secretario || $es_equipo || $es_admin){
        // Secretario, Equipo y Admin pueden ver todos los cursos
        $tiene_permiso = true;
    }
    
    if($tiene_permiso && $id_curso > 0){
        // Obtener información del curso
        $query_curso = "SELECT ID_curso, curso, division, turno FROM curso WHERE ID_curso = '$id_curso'";
        $res_curso = mysqli_query($con, $query_curso);
        $curso_info = mysqli_fetch_array($res_curso);
        
        if($curso_info){
            // Obtener horario del curso
            $query_horario = "SELECT h.id_horario, h.dia_semana, h.horario_inicio, h.horario_fin, 
                                     h.id_materia, h.id_docente,
                                     m.Nom_materia,
                                     u.Nombre as docente_nombre, u.Apellido as docente_apellido
                              FROM horarios h
                              INNER JOIN materia m ON h.id_materia = m.ID_materia
                              LEFT JOIN usuario u ON h.id_docente = u.DNI_U
                              WHERE h.id_curso = '$id_curso'
                              ORDER BY FIELD(h.dia_semana, 'Lunes', 'Martes', 'Miercoles', 'Jueves', 'Viernes'), h.horario_inicio";
            $res_horario = mysqli_query($con, $query_horario);
            
            while($fila = mysqli_fetch_array($res_horario)){
                $key = $fila['dia_semana'] . '_' . $fila['horario_inicio'];
                $horario_curso[$key] = $fila;
            }
            $tiene_horario = !empty($horario_curso);
        } else {
            $error = "No se encontró el curso seleccionado.";
        }
    }
} else {
    if(!$es_admin && !$es_equipo){
        $error = "No hay un curso seleccionado. Por favor, seleccione un curso desde el panel principal.";
    }
}

// Procesar eliminación de horario (solo para Preceptor, Admin y Secretario - NO para Equipo)
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['eliminar_horario']) && !$es_equipo){
    $id_curso_eliminar = $_POST['id_curso'];
    $tiene_permiso_eliminar = false;
    
    if($es_admin){
        $tiene_permiso_eliminar = true;
    } elseif($es_preceptor){
        $dni_preceptor = $_SESSION["dni"];
        $query_verificar = "SELECT * FROM preceptorxcurso WHERE id_preceptor = '$dni_preceptor' AND id_curso = '$id_curso_eliminar'";
        $res_verificar = mysqli_query($con, $query_verificar);
        $tiene_permiso_eliminar = (mysqli_num_rows($res_verificar) > 0);
    } elseif($es_secretario){
        $tiene_permiso_eliminar = true;
    }
    
    if($tiene_permiso_eliminar){
        $query_delete = "DELETE FROM horarios WHERE id_curso = '$id_curso_eliminar'";
        if(mysqli_query($con, $query_delete)){
            $_SESSION['notificacion'] = [
                'tipo' => 'exito',
                'mensaje' => "Horario eliminado exitosamente."
            ];
        } else {
            $_SESSION['notificacion'] = [
                'tipo' => 'error',
                'mensaje' => "Error al eliminar el horario."
            ];
        }
    } else {
        $_SESSION['notificacion'] = [
            'tipo' => 'error',
            'mensaje' => "No tiene permisos para eliminar el horario de este curso."
        ];
    }
    header("Location: horario_detalle.php" . ($es_admin && $id_curso_eliminar ? "?id_curso=$id_curso_eliminar" : ""));
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

// Contar materias por turno
$materias_manana = 0;
$materias_tarde = 0;
foreach($horario_curso as $materia){
    $hora = (int)substr($materia['horario_inicio'], 0, 2);
    if($hora >= 7 && $hora < 14){
        $materias_manana++;
    } else {
        $materias_tarde++;
    }
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
    <title>Detalle de Horario - <?= $curso_info ? $curso_info['curso'] . '° "' . $curso_info['division'] . '"' : 'Seleccionar Curso' ?></title>
</head>
<body>
<div class="container">
    <div class="header-actions">
        <a href="../../recursos/panel.php" class="btn-nav">Panel Principal</a>
        <?php if($es_admin): ?>
            <a href="tarjeta_horario.php" class="btn-nav">Tarjeta de Curso</a>
        <?php elseif(!$es_admin && !$es_equipo): ?>
            <a href="../cursos/seleccionar_curso.php" class="btn-nav">Cambiar Curso</a>
        <?php elseif($es_equipo): ?>
            <span class="rol-badge" style="background: #710A14; color: white; padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: bold;">Equipo de Orientación</span>
        <?php endif; ?>
    </div>

    <?php if(isset($_SESSION['notificacion'])): ?>
        <div id="notificacion" class="mensaje-<?= $_SESSION['notificacion']['tipo'] ?>" style="position: fixed; top: 20px; right: 20px; z-index: 9999; background: <?= $_SESSION['notificacion']['tipo'] == 'exito' ? '#4CAF50' : '#F44336'; ?>; color: white; padding: 12px 20px; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.3);">
            <?= htmlspecialchars($_SESSION['notificacion']['mensaje']) ?>
        </div>
        <script>
            setTimeout(function() {
                var notif = document.getElementById('notificacion');
                if(notif) notif.style.display = 'none';
            }, 4000);
        </script>
        <?php unset($_SESSION['notificacion']); ?>
    <?php endif; ?>

    <!-- Selector de curso (Admin y Equipo) -->
    <?php if(($es_admin || $es_equipo) && !empty($lista_cursos)): ?>
        <div class="selector-curso">
            <h3>📚 Seleccionar Curso</h3>
            <div style="display: flex; gap: 10px; flex-wrap: wrap; align-items: center;">
                <select id="selector-curso" class="curso-select">
                    <option value="">-- Seleccione un curso --</option>
                    <?php foreach($lista_cursos as $curso): ?>
                        <option value="<?= $curso['ID_curso'] ?>" <?= ($id_curso == $curso['ID_curso']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($curso['nombre']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button onclick="irACurso()" class="btn-ir-curso">Ver Horario</button>
            </div>
        </div>
    <?php elseif(($es_admin || $es_equipo) && empty($lista_cursos)): ?>
        <div class="mensaje-error">
            No hay cursos disponibles en el sistema.
        </div>
    <?php endif; ?>

    <?php if($error): ?>
        <div class="mensaje-error">
            <p><?= htmlspecialchars($error) ?></p>
            <?php if(!$es_admin && !$es_equipo && strpos($error, 'curso seleccionado') !== false): ?>
                <p><a href="../cursos/seleccionar_curso.php" style="color: #40E0D0;">Seleccionar un curso</a></p>
            <?php endif; ?>
        </div>
    <?php elseif($curso_info): ?>
        <div class="curso-header">
            <h1>Horario de <?= $curso_info['curso'] ?>° "<?= $curso_info['division'] ?>"</h1>
            <div class="curso-info">
                <div class="info-badge">Turno: <?= $turno_texto ?></div>
                <div class="info-badge">Módulos: <?= count($horario_curso) ?> asignados</div>
                <div class="info-badge">Horarios: Mañana (7:00-12:00) y Tarde (14:00-19:00)</div>
            </div>
        </div>

        <?php if($tiene_horario): ?>
            <!-- Turno Mañana -->
            <div class="seccion-turno">
                <div class="titulo-turno">
                    <span>Turno Mañana (7:00 - 12:00)</span>
                    <small><?= $materias_manana ?> materias</small>
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
                    <tbody>
                        <?php foreach($modulos_manana as $modulo): ?>
                            <tr>
                                <td class="hora-columna">
                                    <?= formatearHora($modulo['inicio']) ?><br>
                                    <?= formatearHora($modulo['fin']) ?>
                                    <div class="modulo-info">M<?= $modulo['modulo'] ?></div>
                                </td>
                                <?php foreach($dias_semana as $dia): ?>
                                    <?php
                                    $key = $dia . '_' . $modulo['inicio'];
                                    $materia = $horario_curso[$key] ?? null;
                                    ?>
                                    <td>
                                        <?php if($materia): ?>
                                            <div class="celda-materia" title="<?= htmlspecialchars($materia['Nom_materia']) ?>">
                                                <div class="nombre-materia"><?= htmlspecialchars($materia['Nom_materia']) ?></div>
                                                <?php if(!empty($materia['docente_apellido'])): ?>
                                                    <div class="docente-materia"><?= htmlspecialchars($materia['docente_apellido'] . ', ' . $materia['docente_nombre']) ?></div>
                                                <?php else: ?>
                                                    <div class="docente-materia">Sin docente</div>
                                                <?php endif; ?>
                                            </div>
                                        <?php else: ?>
                                            <div class="celda-vacia">-</div>
                                        <?php endif; ?>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Turno Tarde -->
            <div class="seccion-turno">
                <div class="titulo-turno">
                    <span>Turno Tarde (14:00 - 19:00)</span>
                    <small><?= $materias_tarde ?> materias</small>
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
                    <tbody>
                        <?php foreach($modulos_tarde as $modulo): ?>
                            <tr>
                                <td class="hora-columna">
                                    <?= formatearHora($modulo['inicio']) ?><br>
                                    <?= formatearHora($modulo['fin']) ?>
                                    <div class="modulo-info">M<?= $modulo['modulo'] ?></div>
                                </td>
                                <?php foreach($dias_semana as $dia): ?>
                                    <?php
                                    $key = $dia . '_' . $modulo['inicio'];
                                    $materia = $horario_curso[$key] ?? null;
                                    ?>
                                    <td>
                                        <?php if($materia): ?>
                                            <div class="celda-materia" title="<?= htmlspecialchars($materia['Nom_materia']) ?>">
                                                <div class="nombre-materia"><?= htmlspecialchars($materia['Nom_materia']) ?></div>
                                                <?php if(!empty($materia['docente_apellido'])): ?>
                                                    <div class="docente-materia"><?= htmlspecialchars($materia['docente_apellido'] . ', ' . $materia['docente_nombre']) ?></div>
                                                <?php else: ?>
                                                    <div class="docente-materia">Sin docente</div>
                                                <?php endif; ?>
                                            </div>
                                        <?php else: ?>
                                            <div class="celda-vacia">-</div>
                                        <?php endif; ?>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="sin-horario">
                <h3>Horario sin cargar</h3>
                <p>Este curso aun no tiene un horario asignado.</p>
                <?php if(!$es_equipo): ?>
                    <a href="horario.php<?= ($es_admin || $es_equipo) && $id_curso ? '?id_curso=' . $id_curso : '' ?>" class="btn-nav btn-editar" style="margin-top: 15px; display: inline-block;">
                        Cargar Horario
                    </a>
                <?php else: ?>
                    <p style="color: #999; font-size: 12px; margin-top: 10px;">Contacte al preceptor para cargar el horario.</p>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <div class="acciones-detalle">
            <?php if(!$es_equipo): ?>
                <a href="horario_editar.php<?= ($es_admin) && $id_curso ? '?id_curso=' . $id_curso : '' ?>" class="btn-nav btn-editar">
                    <?= $tiene_horario ? '✏️ Editar Horario' : '📝 Cargar Horario' ?>
                </a>
                <?php if($tiene_horario && ($es_admin || $es_preceptor || $es_secretario)): ?>
                <form method="POST" action="horario_detalle.php<?= ($es_admin) && $id_curso ? '?id_curso=' . $id_curso : '' ?>" onsubmit="return confirm('¿Está seguro de que desea eliminar TODO el horario? Esta acción no se puede deshacer.');" style="display: inline;">
                    <input type="hidden" name="id_curso" value="<?= $id_curso ?>">
                    <input type="hidden" name="eliminar_horario" value="1">
                    <button type="submit" class="btn-nav btn-eliminar">🗑️ Eliminar Horario</button>
                </form>
                <?php endif; ?>
            <?php endif; ?>

            <?php if($es_admin): ?>
                <a href="tarjeta_horario.php<?= $es_admin && $id_curso ? '?id_curso=' . $id_curso : '' ?>" class="btn-nav">📋 Información del Curso</a>
            <?php endif; ?>
        </div>
        
    <?php elseif(($es_admin || $es_equipo) && !$curso_info && !$error): ?>
        <div class="sin-horario">
            <h3>Seleccione un curso</h3>
            <p>Utilice el selector de cursos para ver el horario de un curso.</p>
        </div>
    <?php else: ?>
        <div class="sin-horario">
            <h3>No hay curso seleccionado</h3>
            <p>Por favor, seleccione un curso desde el panel principal.</p>
            <?php if(!$es_admin && !$es_equipo): ?>
                <a href="../cursos/seleccionar_curso.php" class="btn-nav" style="margin-top: 15px; display: inline-block;">
                    Seleccionar Curso
                </a>
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
        window.location.href = 'horario_detalle.php?id_curso=' + cursoId;
    } else {
        alert('Por favor, seleccione un curso.');
    }
}
</script>
</body>
</html>