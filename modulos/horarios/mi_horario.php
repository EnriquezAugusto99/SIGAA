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

// Verificar permisos - Solo estudiantes (rol 3)
if($_SESSION['rol'] != 'Estudiante'){
    echo '<script>alert("No tiene permisos para acceder a esta seccion"); window.location="../../recursos/panel.php";</script>';
    exit();
}

include '../../recursos/conexion.php';

$error = '';
$estudiante_info = null;
$horario_curso = [];
$tiene_horario = false;

// Obtener el DNI del estudiante desde la sesion
$dni_estudiante = $_SESSION["dni"];

// Obtener informacion del estudiante y su curso
$query_estudiante = "SELECT u.DNI_U, u.Nombre, u.Apellido, u.id_curso,
                            c.curso, c.division, c.turno
                     FROM usuario u
                     LEFT JOIN curso c ON u.id_curso = c.ID_curso
                     WHERE u.DNI_U = '$dni_estudiante' AND u.ID_rol = 3 AND u.ID_Estado = 1";
$res_estudiante = mysqli_query($con, $query_estudiante);
$estudiante_info = mysqli_fetch_array($res_estudiante);

if(!$estudiante_info){
    $error = "No se encontró información del estudiante.";
} else {
    $id_curso = $estudiante_info['id_curso'];
    
    if($id_curso && $id_curso > 0){
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
        $error = "El estudiante no tiene un curso asignado.";
    }
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
if($estudiante_info){
    $turno_texto = $estudiante_info['turno'] == 'M' ? 'Mañana' : 'Tarde';
}

// Obtener materias del dia actual
$dias_espanol = ['Domingo', 'Lunes', 'Martes', 'Miercoles', 'Jueves', 'Viernes', 'Sabado'];
$hoy = new DateTime();
$dia_actual = $dias_espanol[$hoy->format('w')];

$materias_hoy = array_filter($horario_curso, function($clase) use ($dia_actual) {
    return $clase['dia_semana'] === $dia_actual;
});

// Agrupar por materia para evitar duplicados
$materias_unicas_hoy = [];
foreach ($materias_hoy as $clase) {
    $materias_unicas_hoy[$clase['id_materia']] = $clase;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../../recursos/styles.css">
    <title>Mi Horario - Estudiante</title>
</head>
<body>
<div class="container">
    <div class="header-actions">
        <a href="../../recursos/panel.php" class="btn-nav">Panel Principal</a>
    </div>

    <?php if($error): ?>
        <div class="mensaje-error">
            <p><?= htmlspecialchars($error) ?></p>
        </div>
    <?php elseif($estudiante_info): ?>
        <div class="estudiante-header">
            <h1>Mi Horario Escolar</h1>
            <div class="estudiante-info">
                <div class="info-badge">Estudiante: <?= htmlspecialchars($estudiante_info['Apellido'] . ', ' . $estudiante_info['Nombre']) ?></div>
                <div class="info-badge">Curso: <?= $estudiante_info['curso'] ?>° "<?= $estudiante_info['division'] ?>"</div>
                <div class="info-badge">Turno: <?= $turno_texto ?></div>
                <div class="info-badge">Módulos: <?= count($horario_curso) ?> modulos semanales</div>
            </div>
        </div>

        <?php if($tiene_horario): ?>
            <!-- Resumen de Materias del Dia Actual -->
            <div class="materias-resumen">
                <?php if (!empty($materias_unicas_hoy)): ?>
                    <h3 style="margin-bottom: 15px; text-align: center;">
                        Materias de Hoy - <?= $dia_actual ?>
                    </h3>
                    <?php foreach ($materias_unicas_hoy as $materia): ?>
                        <div class="materia-card">
                            <h4><?= htmlspecialchars($materia['Nom_materia']) ?></h4>
                            <p><strong>Profesor:</strong> 
                                <?php if(!empty($materia['docente_apellido'])): ?>
                                    <?= htmlspecialchars($materia['docente_apellido'] . ', ' . $materia['docente_nombre']) ?>
                                <?php else: ?>
                                    Sin docente asignado
                                <?php endif; ?>
                            </p>
                            <?php
                            // Obtener horarios especificos de hoy para esta materia
                            $horarios_hoy_materia = array_filter($materias_hoy, function($clase) use ($materia) {
                                return $clase['id_materia'] == $materia['id_materia'];
                            });
                            ?>
                            <p><strong>Horarios hoy:</strong></p>
                            <?php foreach ($horarios_hoy_materia as $clase): ?>
                                <span class="horario-clase">
                                    <?= formatearHora($clase['horario_inicio']) ?> - <?= formatearHora($clase['horario_fin']) ?>
                                </span>
                            <?php endforeach; ?>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="materia-card" style="text-align: center; grid-column: 1 / -1;">
                        <h4>No hay clases hoy</h4>
                        <p>No tienes materias programadas para <?= $dia_actual ?>.</p>
                    </div>
                <?php endif; ?>
            </div>

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
                <h3>Horario no disponible</h3>
                <p>Tu curso aun no tiene un horario asignado.</p>
                <p>Contacta con la administracion para mas informacion.</p>
            </div>
        <?php endif; ?>
        
    <?php else: ?>
        <div class="sin-horario">
            <h3>Error</h3>
            <p>No se pudo cargar la informacion del estudiante.</p>
            <a href="../../recursos/panel.php" class="btn-nav">Volver al Panel</a>
        </div>
    <?php endif; ?>
    
    <div style="text-align: center; margin-top: 30px;">
        <a href="../../recursos/panel.php" class="volver-btn">Volver al Panel</a>
    </div>
</div>

<script>
// Resaltar la clase actual segun el dia y hora
document.addEventListener('DOMContentLoaded', function() {
    const dias = ['Domingo', 'Lunes', 'Martes', 'Miercoles', 'Jueves', 'Viernes', 'Sabado'];
    const hoy = new Date();
    const diaActual = dias[hoy.getDay()];
    const horaActual = hoy.getHours() + ':' + (hoy.getMinutes() < 10 ? '0' : '') + hoy.getMinutes();
    
    // Resaltar columna del dia actual
    document.querySelectorAll('th').forEach(th => {
        if (th.textContent === diaActual) {
            th.style.background = '#710A14';
            th.style.color = 'white';
        }
    });
    
    // Resaltar clase actual (aproximado)
    const celdas = document.querySelectorAll('.celda-materia');
    celdas.forEach(celda => {
        const fila = celda.closest('tr');
        if (fila) {
            const horaColumna = fila.querySelector('.hora-columna');
            if (horaColumna) {
                const horarioTexto = horaColumna.textContent.split('\n')[0];
                const horaInicio = horarioTexto.trim();
                
                // Conversion simple para comparacion
                const [hora, minuto] = horaInicio.split(':');
                const horaNum = parseInt(hora) + parseInt(minuto) / 60;
                const [horaAct, minutoAct] = horaActual.split(':');
                const horaActualNum = parseInt(horaAct) + parseInt(minutoAct) / 60;
                
                // Si esta dentro de 1 hora de la clase actual
                if (Math.abs(horaActualNum - horaNum) < 1) {
                    celda.classList.add('current-class');
                }
            }
        }
    });
});
</script>
</body>
</html>