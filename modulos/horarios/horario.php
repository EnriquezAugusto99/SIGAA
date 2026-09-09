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

// Verificar permisos - Admin, Preceptor y Secretario pueden gestionar horarios
$es_admin = ($_SESSION['rol'] == 'Admin');
$es_preceptor = ($_SESSION['rol'] == 'Preceptor');
$es_secretario = ($_SESSION['rol'] == 'Secretario');

if(!$es_admin && !$es_preceptor && !$es_secretario){
    echo '<script>alert("No tiene permisos para gestionar horarios"); window.location="../../recursos/panel.php";</script>';
    exit();
}

include '../../recursos/conexion.php';

$error = '';
$success = '';

// =====================================================
// PARA ADMIN: Puede seleccionar cualquier curso via GET
// PARA PRECEPTOR: Usa el curso activo de la sesion
// =====================================================
$id_curso_seleccionado = 0;

if($es_admin){
    // Admin: puede seleccionar curso via GET
    $id_curso_seleccionado = isset($_GET['id_curso']) ? (int)$_GET['id_curso'] : 0;
    
    // Si no hay curso seleccionado en GET, intentar con la sesion
    if($id_curso_seleccionado == 0 && isset($_SESSION['curso_activo_preceptor'])){
        $id_curso_seleccionado = $_SESSION['curso_activo_preceptor'];
    }
} else {
    // Preceptor o Secretario: usan el curso activo de la sesion
    $id_curso_seleccionado = $_SESSION['curso_activo_preceptor'] ?? 0;
    
    // Verificar que el preceptor tenga asignado este curso
    if($id_curso_seleccionado > 0 && $es_preceptor){
        $dni_preceptor = $_SESSION["dni"];
        $query_verificar = "SELECT * FROM preceptorxcurso WHERE id_preceptor = '$dni_preceptor' AND id_curso = '$id_curso_seleccionado'";
        $res_verificar = mysqli_query($con, $query_verificar);
        if(mysqli_num_rows($res_verificar) == 0){
            $error = "No tiene permisos para gestionar el horario de este curso.";
            $id_curso_seleccionado = 0;
        }
    }
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

// Obtener materias del curso seleccionado
$materias_del_curso = [];
$curso_info = null;
$horario_existente = [];

if($id_curso_seleccionado > 0){
    // Obtener información del curso
    $query_curso_info = "SELECT ID_curso, curso, division, turno FROM curso WHERE ID_curso = '$id_curso_seleccionado'";
    $res_curso_info = mysqli_query($con, $query_curso_info);
    $curso_info = mysqli_fetch_array($res_curso_info);
    
    if($curso_info){
        // Obtener materias del curso desde la tabla materia
        $query_materias = "SELECT ID_materia, Nom_materia FROM materia WHERE id_curso = '$id_curso_seleccionado' ORDER BY Nom_materia";
        $res_materias = mysqli_query($con, $query_materias);
        if(mysqli_num_rows($res_materias) > 0){
            while($fila = mysqli_fetch_array($res_materias)){
                // Obtener docentes de cada materia
                $query_docentes = "SELECT u.DNI_U, u.Nombre, u.Apellido 
                                   FROM usuario u
                                   INNER JOIN docentemateriacurso dmc ON u.DNI_U = dmc.id_docente
                                   WHERE dmc.id_materia = '{$fila['ID_materia']}' AND dmc.id_curso = '$id_curso_seleccionado'";
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
        
        // Obtener horario existente del curso desde la tabla horarios
        $query_horario = "SELECT h.id_horario, h.dia_semana, h.horario_inicio, h.horario_fin, 
                                 h.id_materia, h.id_docente, h.id_aula,
                                 m.Nom_materia
                          FROM horarios h
                          INNER JOIN materia m ON h.id_materia = m.ID_materia
                          WHERE h.id_curso = '$id_curso_seleccionado'
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

// Procesar guardado de horario
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['guardar_horario'])){
    $id_curso = $_POST['id_curso'];
    $horario_data = json_decode($_POST['horario_data'], true);
    
    // Verificar permisos
    $tiene_permiso = false;
    if($es_admin){
        $tiene_permiso = true;
    } elseif($es_preceptor){
        $dni_preceptor = $_SESSION["dni"];
        $query_verificar = "SELECT * FROM preceptorxcurso WHERE id_preceptor = '$dni_preceptor' AND id_curso = '$id_curso'";
        $res_verificar = mysqli_query($con, $query_verificar);
        $tiene_permiso = (mysqli_num_rows($res_verificar) > 0);
    } elseif($es_secretario){
        $tiene_permiso = true;
    }
    
    if($tiene_permiso && !empty($horario_data)){
        // Eliminar horario existente del curso
        $query_delete = "DELETE FROM horarios WHERE id_curso = '$id_curso'";
        mysqli_query($con, $query_delete);
        
        $insertados = 0;
        foreach($horario_data as $key => $data){
            $dia = $data['dia'];
            $hora_inicio = $data['hora_inicio'];
            $hora_fin = $data['hora_fin'];
            $id_materia = $data['id_materia'];
            
            // Obtener docente de la materia para este curso
            $query_docente = "SELECT id_docente FROM docentemateriacurso 
                              WHERE id_materia = '$id_materia' AND id_curso = '$id_curso' LIMIT 1";
            $res_docente = mysqli_query($con, $query_docente);
            $id_docente = null;
            if(mysqli_num_rows($res_docente) > 0){
                $docente_row = mysqli_fetch_array($res_docente);
                $id_docente = $docente_row['id_docente'];
            }
            
            $query_insert = "INSERT INTO horarios (id_curso, id_materia, id_docente, dia_semana, horario_inicio, horario_fin) 
                            VALUES ('$id_curso', '$id_materia', " . ($id_docente ? "'$id_docente'" : "NULL") . ", '$dia', '$hora_inicio', '$hora_fin')";
            if(mysqli_query($con, $query_insert)){
                $insertados++;
            }
        }
        
        $_SESSION['notificacion'] = [
            'tipo' => 'exito',
            'mensaje' => "Horario guardado exitosamente. Se asignaron $insertados materias."
        ];
        
        // Redirigir para evitar reenvío del formulario
        header("Location: horario.php" . ($id_curso ? "?id_curso=$id_curso" : ""));
        exit();
    } else {
        $_SESSION['notificacion'] = [
            'tipo' => 'error',
            'mensaje' => "No se enviaron datos de horario para guardar o no tiene permisos."
        ];
        header("Location: horario.php" . ($id_curso ? "?id_curso=$id_curso" : ""));
        exit();
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
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../../recursos/styles.css">
    <title>Gestión de Horarios - <?= $curso_info ? $curso_info['curso'] . '° "' . $curso_info['division'] . '"' : 'Seleccionar Curso' ?></title>
</head>
<body>
<div class="container">
    <div class="header-actions">
        <a href="../../recursos/panel.php" class="btn-nav">Panel Principal</a>
        <a href="tarjeta_horario.php" class="btn-nav">Tarjeta de Curso</a>
        <?php if(!$es_admin): ?>
            <a href="../cursos/seleccionar_curso.php" class="btn-nav btn-cambiar-curso">Cambiar Curso</a>
        <?php endif; ?>
    </div>
    
    <h1>Gestión de Horarios
        <?php if($es_admin): ?>
            <span class="admin-badge">👑 Modo Administrador</span>
        <?php endif; ?>
    </h1>
    
    <!-- Selector de curso (solo para Admin) -->
    <?php if($es_admin && !empty($lista_cursos)): ?>
        <div class="selector-curso">
            <h3>📚 Seleccionar Curso</h3>
            <div style="display: flex; gap: 10px; flex-wrap: wrap; align-items: center;">
                <select id="selector-curso" class="curso-select">
                    <option value="">-- Seleccione un curso --</option>
                    <?php foreach($lista_cursos as $curso): ?>
                        <option value="<?= $curso['ID_curso'] ?>" <?= $id_curso_seleccionado == $curso['ID_curso'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($curso['nombre']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button onclick="irACurso()" class="btn-ir-curso">Ir al Curso</button>
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
            <?php if(!$es_admin): ?>
                <p><a href="../cursos/seleccionar_curso.php" style="color: #40E0D0;">Seleccionar otro curso</a></p>
            <?php endif; ?>
        </div>
    <?php elseif($id_curso_seleccionado > 0 && $curso_info): 
        $turno_texto = $curso_info['turno'] == 'M' ? 'Mañana' : 'Tarde';
    ?>
        <div class="info-curso">
            <div class="info-grid">
                <div class="info-item">
                    <label>Curso:</label>
                    <span><?= $curso_info['curso'] ?>° "<?= $curso_info['division'] ?>" - <?= $turno_texto ?></span>
                </div>
                <div class="info-item">
                    <label>Materias disponibles:</label>
                    <span><?= count($materias_del_curso) ?> materias</span>
                </div>
                <div class="info-item">
                    <label>Horarios:</label>
                    <span>Mañana (7:00-12:00) y Tarde (14:00-19:00)</span>
                </div>
            </div>
            <?php if(empty($materias_del_curso)): ?>
                <div class="info-materias-disponibles" style="color: #ff9999;">
                    Este curso no tiene materias asignadas. Primero debe asignar materias al curso.
                </div>
            <?php endif; ?>
            
            <?php if(!empty($horario_existente)): ?>
                <div class="info-materias-disponibles" style="border-left-color: #4CAF50;">
                    Horario existente: <?= count($horario_existente) ?> módulos asignados. Puede modificarlas haciendo clic en cada celda.
                </div>
            <?php endif; ?>
        </div>
        
        <?php if(!empty($materias_del_curso)): ?>
            <form method="POST" action="horario.php<?= $id_curso_seleccionado ? '?id_curso=' . $id_curso_seleccionado : '' ?>" id="formHorario">
                <input type="hidden" name="id_curso" value="<?= $id_curso_seleccionado ?>">
                <input type="hidden" name="guardar_horario" value="1">
                <input type="hidden" name="horario_data" id="horario_data">
                
                <div class="preview-horario" id="preview-horario">
                    <strong>Estado:</strong> <span id="preview-texto"></span>
                </div>
                
                <!-- Turno Mañana -->
                <div class="seccion-turno">
                    <div class="titulo-turno">
                        <span>Turno Mañana (7:00 - 12:00)</span>
                        <small>7 módulos</small>
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
                        <small>7 módulos</small>
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
                    💾 Guardar Horario Completo
                </button>
            </form>
        <?php endif; ?>
        
    <?php elseif($id_curso_seleccionado == 0 && !$es_admin): ?>
        <div class="info-curso">
            <div class="info-materias-disponibles" style="color: #ff9999;">
                No hay un curso seleccionado. Por favor, seleccione un curso desde el panel principal.
            </div>
            <div style="margin-top: 15px;">
                <a href="../cursos/seleccionar_curso.php" class="btn-nav btn-cambiar-curso" style="display: inline-block;">Seleccionar Curso</a>
            </div>
        </div>
    <?php elseif($id_curso_seleccionado == 0 && $es_admin): ?>
        <div class="info-curso">
            <div class="info-materias-disponibles" style="color: #ff9999;">
                Seleccione un curso del menú desplegable para comenzar a gestionar su horario.
            </div>
        </div>
    <?php endif; ?>
    
    <a href="../../recursos/panel.php" class="volver-btn">← Volver al Panel</a>
</div>

<script>
function irACurso() {
    const selector = document.getElementById('selector-curso');
    const cursoId = selector.value;
    if(cursoId) {
        window.location.href = 'horario.php?id_curso=' + cursoId;
    } else {
        alert('Por favor, seleccione un curso.');
    }
}

// Script principal para gestión de horarios
const modulosManana = <?= json_encode($modulos_manana) ?>;
const modulosTarde = <?= json_encode($modulos_tarde) ?>;
const diasSemana = <?= json_encode($dias_semana) ?>;
const materiasCurso = <?= json_encode($materias_del_curso) ?>;
const horarioExistente = <?= json_encode($horario_existente) ?>;

let horarioSeleccionado = {};

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
            <div class="modulo-info">Módulo ${modulo.modulo}</div>
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
    
    if(select.value){
        const materiaSeleccionada = materiasCurso.find(m => m.ID_materia == select.value);
        if(materiaSeleccionada){
            contenidoCelda.innerHTML = `
                <strong>${materiaSeleccionada.Nom_materia}</strong>
                ${materiaSeleccionada.docentes ? `<div class="info-materia">${materiaSeleccionada.docentes}</div>` : ''}
            `;
            celda.classList.remove('vacio');
            celda.classList.add('ocupado');
            
            const key = `${celda.dataset.turno}_${celda.dataset.dia}_${celda.dataset.modulo}`;
            horarioSeleccionado[key] = {
                id_materia: select.value,
                nombre_materia: materiaSeleccionada.Nom_materia,
                docentes: materiaSeleccionada.docentes || 'Sin docente asignado',
                dia: celda.dataset.dia,
                turno: celda.dataset.turno,
                modulo: celda.dataset.modulo,
                hora_inicio: celda.dataset.horaInicio,
                hora_fin: celda.dataset.horaFin
            };
        }
    } else {
        contenidoCelda.innerHTML = '';
        celda.classList.add('vacio');
        celda.classList.remove('ocupado');
        
        const key = `${celda.dataset.turno}_${celda.dataset.dia}_${celda.dataset.modulo}`;
        delete horarioSeleccionado[key];
    }
    
    selectContainer.style.display = 'none';
    contenidoCelda.style.display = 'block';
    celda.classList.remove('materia-seleccionada');
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
            if(select) select.value = materia.id_materia;
            
            const modulo = celda.dataset.modulo;
            const nuevoKey = `${turno}_${dia}_${modulo}`;
            horarioSeleccionado[nuevoKey] = {
                id_materia: materia.id_materia,
                nombre_materia: materia.Nom_materia,
                docentes: 'Docente asignado',
                dia: dia,
                turno: turno,
                modulo: modulo,
                hora_inicio: horaInicio,
                hora_fin: celda.dataset.horaFin
            };
        }
    });
    actualizarPreview();
}

function actualizarPreview() {
    const totalMaterias = Object.keys(horarioSeleccionado).length;
    const preview = document.getElementById('preview-horario');
    const previewTexto = document.getElementById('preview-texto');
    
    if(preview && previewTexto){
        if(totalMaterias > 0){
            preview.style.display = 'block';
            previewTexto.textContent = `Has asignado ${totalMaterias} materia(s) en el horario.`;
        } else {
            preview.style.display = 'none';
        }
    }
}

// Inicializar
if(document.getElementById('cuerpo-horario-manana')){
    generarTablaHorario('manana');
    generarTablaHorario('tarde');
    if(Object.keys(horarioExistente).length > 0){
        setTimeout(() => cargarHorarioExistente(), 100);
    }
}

// Enviar formulario
const formHorario = document.getElementById('formHorario');
if(formHorario){
    formHorario.addEventListener('submit', function(e) {
        if(Object.keys(horarioSeleccionado).length === 0){
            e.preventDefault();
            alert('Debe asignar al menos una materia al horario.');
            return false;
        }
        document.getElementById('horario_data').value = JSON.stringify(horarioSeleccionado);
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