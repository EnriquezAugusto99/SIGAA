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

// Verificar permisos - Solo Profesor (rol 2)
if($_SESSION['rol'] != 'Profesor'){
    echo '<script>alert("No tiene permisos para acceder a esta seccion"); window.location="../../recursos/panel.php";</script>';
    exit();
}

include '../../recursos/conexion.php';

$error = '';
$docente_info = null;
$horario_docente = [];
$tiene_horario = false;

// Obtener el DNI del docente desde la sesion
$dni_docente = $_SESSION["dni"];

// Obtener informacion del docente
$query_docente = "SELECT DNI_U, Nombre, Apellido FROM usuario WHERE DNI_U = '$dni_docente' AND ID_rol = 2 AND ID_Estado = 1";
$res_docente = mysqli_query($con, $query_docente);
$docente_info = mysqli_fetch_array($res_docente);

if(!$docente_info){
    $error = "No se encontró información del docente.";
} else {
    // Obtener horario del docente (todas las materias que dicta en todos los cursos)
    $query_horario = "SELECT h.id_horario, h.dia_semana, h.horario_inicio, h.horario_fin, 
                             h.id_materia, h.id_curso,
                             m.Nom_materia,
                             c.curso, c.division, c.turno
                      FROM horarios h
                      INNER JOIN materia m ON h.id_materia = m.ID_materia
                      INNER JOIN curso c ON h.id_curso = c.ID_curso
                      WHERE h.id_docente = '$dni_docente'
                      ORDER BY FIELD(h.dia_semana, 'Lunes', 'Martes', 'Miercoles', 'Jueves', 'Viernes'), h.horario_inicio";
    $res_horario = mysqli_query($con, $query_horario);
    
    if(mysqli_error($con)){
        $error = "Error en la consulta: " . mysqli_error($con);
    } else {
        while($fila = mysqli_fetch_array($res_horario)){
            $key = $fila['dia_semana'] . '_' . $fila['horario_inicio'];
            $horario_docente[$key] = $fila;
        }
        $tiene_horario = !empty($horario_docente);
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

// Contar materias por turno y obtener estadisticas
$materias_manana = 0;
$materias_tarde = 0;
$materias_unicas = [];
$cursos_unicos = [];
$dias_con_clases = [];

foreach($horario_docente as $clase){
    $hora = (int)substr($clase['horario_inicio'], 0, 2);
    if($hora >= 7 && $hora < 14){
        $materias_manana++;
    } else {
        $materias_tarde++;
    }
    
    $materias_unicas[$clase['Nom_materia']] = true;
    $cursos_unicos[$clase['id_curso']] = $clase['curso'] . '° "' . $clase['division'] . '"';
    $dias_con_clases[$clase['dia_semana']] = true;
}

$total_horas = count($horario_docente);
$total_materias = count($materias_unicas);
$total_cursos = count($cursos_unicos);
$total_dias = count($dias_con_clases);

// Obtener resumen de materias por curso
$resumen_materias = [];
foreach($horario_docente as $clase){
    $key = $clase['Nom_materia'] . '_' . $clase['id_curso'];
    if(!isset($resumen_materias[$key])){
        $turno_texto = $clase['turno'] == 'M' ? 'Mañana' : 'Tarde';
        $resumen_materias[$key] = [
            'materia' => $clase['Nom_materia'],
            'curso' => $clase['curso'] . '° "' . $clase['division'] . '"',
            'turno' => $turno_texto,
            'horarios' => []
        ];
    }
    $resumen_materias[$key]['horarios'][] = [
        'dia' => $clase['dia_semana'],
        'hora_inicio' => $clase['horario_inicio'],
        'hora_fin' => $clase['horario_fin']
    ];
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../../recursos/styles.css">
    <title>Mi Horario - Docente</title>
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
    <?php elseif($docente_info): ?>
        <div class="docente-header">
            <h1>Mi Horario</h1>
            <div class="docente-info">
                <h2><?= htmlspecialchars($docente_info['Apellido'] . ', ' . $docente_info['Nombre']) ?></h2>
                <p class="docente-subtitle">Horario Academico</p>
            </div>
            
            <?php if($tiene_horario): ?>
            <div class="estadisticas-rapidas">
                <div class="stat-item">
                    <span class="stat-number"><?= $total_horas ?></span>
                    <span class="stat-label">Modulos semanales</span>
                </div>
                <div class="stat-item">
                    <span class="stat-number"><?= $total_materias ?></span>
                    <span class="stat-label">Materias</span>
                </div>
                <div class="stat-item">
                    <span class="stat-number"><?= $total_cursos ?></span>
                    <span class="stat-label">Cursos</span>
                </div>
                <div class="stat-item">
                    <span class="stat-number"><?= $total_dias ?></span>
                    <span class="stat-label">Dias con clases</span>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <?php if($tiene_horario): ?>
            <!-- Turno Mañana -->
            <div class="seccion-turno">
                <div class="titulo-turno">
                    <span>Turno Mañana (7:00 - 12:00)</span>
                    <small><?= $materias_manana ?> modulos</small>
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
                                    $clase = isset($horario_docente[$key]) ? $horario_docente[$key] : null;
                                    ?>
                                    <td>
                                        <?php if($clase): 
                                            $turno_texto = $clase['turno'] == 'M' ? 'Mañana' : 'Tarde';
                                        ?>
                                            <div class="celda-clase" title="<?= htmlspecialchars($clase['Nom_materia']) . ' - ' . $clase['curso'] . '° "' . $clase['division'] . '"' ?>">
                                                <div class="clase-materia"><?= htmlspecialchars($clase['Nom_materia']) ?></div>
                                                <div class="clase-curso"><?= $clase['curso'] ?>° "<?= $clase['division'] ?>" - <?= $turno_texto ?></div>
                                            </div>
                                        <?php else: ?>
                                            <div class="celda-libre">-</div>
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
                    <small><?= $materias_tarde ?> modulos</small>
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
                                    $clase = isset($horario_docente[$key]) ? $horario_docente[$key] : null;
                                    ?>
                                    <td>
                                        <?php if($clase): 
                                            $turno_texto = $clase['turno'] == 'M' ? 'Mañana' : 'Tarde';
                                        ?>
                                            <div class="celda-clase" title="<?= htmlspecialchars($clase['Nom_materia']) . ' - ' . $clase['curso'] . '° "' . $clase['division'] . '"' ?>">
                                                <div class="clase-materia"><?= htmlspecialchars($clase['Nom_materia']) ?></div>
                                                <div class="clase-curso"><?= $clase['curso'] ?>° "<?= $clase['division'] ?>" - <?= $turno_texto ?></div>
                                            </div>
                                        <?php else: ?>
                                            <div class="celda-libre">-</div>
                                        <?php endif; ?>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Resumen de Materias -->
            <div class="resumen-materias">
                <h3>Resumen de Materias</h3>
                <div class="materias-grid">
                    <?php foreach($resumen_materias as $resumen): ?>
                        <div class="materia-resumen">
                            <div class="materia-header">
                                <h4><?= htmlspecialchars($resumen['materia']) ?></h4>
                                <span class="horas-totales"><?= count($resumen['horarios']) ?> modulos</span>
                            </div>
                            <div class="materia-detalle">
                                <p><strong>Curso:</strong> <?= htmlspecialchars($resumen['curso']) ?></p>
                                <p><strong>Turno:</strong> <?= $resumen['turno'] ?></p>
                                <div class="horarios-lista">
                                    <strong>Horarios:</strong>
                                    <?php foreach($resumen['horarios'] as $horario): ?>
                                        <span class="horario-item">
                                            <?= $horario['dia'] ?> <?= formatearHora($horario['hora_inicio']) ?>-<?= formatearHora($horario['hora_fin']) ?>
                                        </span>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php else: ?>
            <div class="sin-horario">
                <h3>Horario no asignado</h3>
                <p>Actualmente no tienes un horario asignado.</p>
                <p>Contacta con la administracion para que te asignen materias y horarios.</p>
            </div>
        <?php endif; ?>
        
    <?php else: ?>
        <div class="sin-horario">
            <h3>Error</h3>
            <p>No se pudo cargar la informacion del docente.</p>
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
    const celdas = document.querySelectorAll('.celda-clase');
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
                    celda.style.background = '#40E0D0';
                    celda.style.color = '#1a2a3a';
                    const materiaElem = celda.querySelector('.clase-materia');
                    const cursoElem = celda.querySelector('.clase-curso');
                    if(materiaElem) materiaElem.style.color = '#1a2a3a';
                    if(cursoElem) cursoElem.style.color = '#1a2a3a';
                }
            }
        }
    });
});
</script>
</body>
</html>