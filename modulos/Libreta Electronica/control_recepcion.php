<?php
session_start();

// 1. CONTROL DE SESIÓN INSTITUCIONAL
if(isset($_SESSION["dni"]) && (time() - $_SESSION["ultima_actividad"] > 18000)){
    echo '<script>alert("Se venció el tiempo de la sesión"); window.location="../../index.php";</script>';
    exit();
}

if(!isset($_SESSION["dni"])){
    echo '<script>alert("Debe iniciar sesión para acceder"); window.location="../../index.php";</script>';
    exit();
}

$_SESSION["ultima_actividad"] = time(); // Actualizar actividad

// Verificar roles autorizados (Admin o Preceptor)
$es_admin = ($_SESSION['rol'] == 'Admin');
$es_preceptor = ($_SESSION['rol'] == 'Preceptor');
$es_equipo = ($_SESSION['rol'] == 'Equipo de Orientacion'); 

if(!$es_admin && !$es_preceptor && !$es_equipo){
    echo '<script>alert("No tiene permisos para acceder a esta sección"); window.location="../../recursos/panel.php";</script>';
    exit();
}

include '../../recursos/conexion.php';

// 2. CONFIGURACIÓN DE FILTROS DEL PERIODO
$periodo_seleccionado = isset($_GET['periodo']) ? $_GET['periodo'] : '1er Trimestre';
$periodo_esc = mysqli_real_escape_string($con, $periodo_seleccionado);
$preceptor_dni = $_SESSION["dni"];

// Filtros de curso y alumno
$filtro_curso = isset($_GET['filtro_curso']) ? $_GET['filtro_curso'] : '';
$filtro_alumno = isset($_GET['filtro_alumno']) ? $_GET['filtro_alumno'] : '';

// 3. CONSULTA ADAPTATIVA SEGÚN EL ROL
if($es_preceptor) {
    $query_control = "SELECT 
                        c.curso, c.division, c.turno,
                        t.DNI_U AS tutor_dni, CONCAT(t.Apellido, ', ', t.Nombre) AS tutor_nombre, t.email AS tutor_email,
                        a.DNI_U AS alumno_dni, CONCAT(a.Apellido, ', ', a.Nombre) AS alumno_nombre,
                        cl.fecha_envio, cl.confirmado, cl.fecha_confirmacion
                      FROM preceptorxcurso pxc
                      INNER JOIN curso c ON pxc.id_curso = c.ID_curso
                      INNER JOIN usuario a ON a.id_curso = c.ID_curso AND a.ID_rol = 3 AND a.ID_Estado = 1
                      INNER JOIN alumnoxtutor at ON at.id_alumno = a.DNI_U
                      INNER JOIN usuario t ON t.DNI_U = at.id_tutor
                      LEFT JOIN confirmacion_libreta cl ON cl.id_tutor = t.DNI_U 
                                                       AND cl.id_alumno = a.DNI_U 
                                                       AND cl.tipo_envio = '$periodo_esc'
                      WHERE pxc.id_preceptor = '$preceptor_dni'";
} else {
    $query_control = "SELECT 
                        c.curso, c.division, c.turno,
                        t.DNI_U AS tutor_dni, CONCAT(t.Apellido, ', ', t.Nombre) AS tutor_nombre, t.email AS tutor_email,
                        a.DNI_U AS alumno_dni, CONCAT(a.Apellido, ', ', a.Nombre) AS alumno_nombre,
                        cl.fecha_envio, cl.confirmado, cl.fecha_confirmacion
                      FROM curso c
                      INNER JOIN usuario a ON a.id_curso = c.ID_curso AND a.ID_rol = 3 AND a.ID_Estado = 1
                      INNER JOIN alumnoxtutor at ON at.id_alumno = a.DNI_U
                      INNER JOIN usuario t ON t.DNI_U = at.id_tutor
                      LEFT JOIN confirmacion_libreta cl ON cl.id_tutor = t.DNI_U 
                                                       AND cl.id_alumno = a.DNI_U 
                                                       AND cl.tipo_envio = '$periodo_esc'";
}

// Agregar filtros de curso y alumno
if(!empty($filtro_curso)){
    $query_control .= " AND c.ID_curso = '$filtro_curso'";
}
if(!empty($filtro_alumno)){
    $query_control .= " AND a.DNI_U = '$filtro_alumno'";
}

$query_control .= " ORDER BY c.curso, c.division, a.Apellido, a.Nombre";

$res_control = mysqli_query($con, $query_control);

// 4. PROCESAMIENTO PREVIO PARA MÉTRICAS RÁPIDAS
$filas = [];
$total_alumnos_tutores = 0;
$total_enviados = 0;
$total_confirmados = 0;

while($row = mysqli_fetch_assoc($res_control)) {
    $filas[] = $row;
    $total_alumnos_tutores++;
    if(!empty($row['fecha_envio'])) {
        $total_enviados++;
    }
    if($row['confirmado'] == 1) {
        $total_confirmados++;
    }
}
$total_pendientes = $total_enviados - $total_confirmados;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Control de Recepción de Libretas - EPET N°34</title>
    <link rel="stylesheet" href="../../estilo.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        :root { --primary-color: #7a0000; --primary-dark: #5a0000; }
        body { font-family: 'Segoe UI', Arial, sans-serif; background-color: #f5f6fa; margin: 0; padding: 20px; color: #333; }
        .container { max-width: 1200px; margin: 0 auto; background: white; padding: 30px; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.08); }
        
        /* Encabezado */
        .header-modulo { display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #eee; padding-bottom: 15px; margin-bottom: 25px; }
        .header-modulo h1 { color: var(--primary-color); margin: 0; font-size: 24px; display: flex; align-items: center; gap: 10px; }
        .btn-volver { background: #6c757d; color: white; padding: 8px 16px; text-decoration: none; border-radius: 6px; font-weight: bold; font-size: 14px; }
        .btn-volver:hover { background: #5a6268; }

        /* Tarjetas de Estadísticas */
        .dashboard-cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 15px; margin-bottom: 25px; }
        .card-stat { background: #fafafa; border-radius: 8px; padding: 15px 20px; border-left: 5px solid #ccc; display: flex; flex-direction: column; justify-content: center; }
        .card-stat.total { border-left-color: #2196F3; }
        .card-stat.enviado { border-left-color: #ff9800; }
        .card-stat.confirmado { border-left-color: #4CAF50; }
        .card-stat.pendiente { border-left-color: #f44336; }
        .card-stat .num { font-size: 26px; font-weight: bold; color: #222; }
        .card-stat .label { font-size: 13px; color: #666; font-weight: 500; margin-top: 2px; }

        /* Filtros y Buscador */
        .bar-herramientas { display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 15px; margin-bottom: 20px; background: #fdfdfd; padding: 15px; border: 1px solid #eef0f5; border-radius: 8px; }
        .form-filtros { display: flex; flex-wrap: wrap; gap: 15px; align-items: flex-end; }
        .form-grupo-filtro { display: flex; flex-direction: column; gap: 4px; }
        .form-grupo-filtro label { font-weight: bold; font-size: 13px; color: #555; }
        .select-control, .search-control { padding: 8px 12px; border: 1px solid #ccc; border-radius: 6px; font-size: 14px; outline: none; min-width: 180px; }
        .select-control:focus, .search-control:focus { border-color: var(--primary-color); }
        .select-control:disabled { background: #f5f5f5; cursor: not-allowed; }
        .search-control { width: 300px; }
        
        .btn-aplicar-filtros {
            background: #710A14;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: bold;
            font-size: 13px;
            transition: all 0.3s ease;
        }
        .btn-aplicar-filtros:hover {
            background: #3F070B;
            transform: translateY(-2px);
        }
        .btn-limpiar-filtros {
            background: #6c757d;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: bold;
            font-size: 13px;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s ease;
        }
        .btn-limpiar-filtros:hover {
            background: #5a6268;
            transform: translateY(-2px);
        }

        /* Tabla de Datos */
        .contenedor-tabla { overflow-x: auto; border: 1px solid #eef0f5; border-radius: 8px; }
        .tabla-institucional { width: 100%; border-collapse: collapse; text-align: left; font-size: 14px; }
        .tabla-institucional th { background-color: var(--primary-color); color: white; padding: 12px 15px; font-weight: 600; }
        .tabla-institucional td { padding: 12px 15px; border-bottom: 1px solid #eee; vertical-align: middle; }
        .tabla-institucional tr:hover { background-color: #f9f9fc; }

        /* Badges/Etiquetas de Estado */
        .badge { display: inline-block; padding: 4px 8px; font-size: 12px; font-weight: bold; border-radius: 4px; text-align: center; }
        .bg-success { background-color: #e8f5e9; color: #2e7d32; border: 1px solid #c8e6c9; }
        .bg-warning { background-color: #fff3e0; color: #ef6c00; border: 1px solid #ffe0b2; }
        .bg-danger { background-color: #ffebee; color: #c62828; border: 1px solid #ffcdd2; }
        
        small { color: #777; font-size: 11px; display: block; margin-top: 2px; }
        .no-data { text-align: center; padding: 30px; color: #888; font-weight: 500; }

        /* Botón de envío individual */
        .btn-enviar-individual {
            background: #ff9800;
            color: white;
            border: none;
            padding: 5px 12px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 11px;
            margin-top: 5px;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        .btn-enviar-individual:hover {
            background: #e68900;
            transform: scale(1.02);
        }
        .btn-enviar-individual:disabled {
            background: #ccc;
            cursor: not-allowed;
            transform: none;
        }
        .btn-enviar-individual i {
            font-size: 12px;
        }

        @media (max-width: 768px) {
            .form-filtros { flex-direction: column; align-items: stretch; }
            .form-grupo-filtro { width: 100%; }
            .select-control { width: 100%; }
            .search-control { width: 100%; }
            .bar-herramientas { flex-direction: column; }
        }
    </style>
</head>
<body>

<div class="container">
    <div class="header-modulo">
        <h1><i class="fas fa-envelope-open-text"></i> Control de Recepción de Libretas Digitales</h1>
        <a href="../../recursos/panel.php" class="btn-volver">↩ Volver al Panel</a>
    </div>

    <div class="dashboard-cards">
        <div class="card-stat total">
            <span class="num"><?= $total_alumnos_tutores ?></span>
            <span class="label">Total Vínculos (Hijo/Tutor)</span>
        </div>
        <div class="card-stat enviado">
            <span class="num"><?= $total_enviados ?></span>
            <span class="label">Libretas Enviadas</span>
        </div>
        <div class="card-stat confirmado">
            <span class="num"><?= $total_confirmados ?></span>
            <span class="label">Confirmadas por Tutor</span>
        </div>
        <div class="card-stat pendiente">
            <span class="num"><?= $total_pendientes ?></span>
            <span class="label">Pendientes de Firma</span>
        </div>
    </div>

    <div class="bar-herramientas">
        <form method="GET" action="" id="formFiltros" class="form-filtros">
            <div class="form-grupo-filtro">
                <label for="periodo">Instancia de Evaluación:</label>
                <select name="periodo" id="periodo" class="select-control" onchange="this.form.submit()">
                    <option value="1er Trimestre" <?= $periodo_seleccionado == '1er Trimestre' ? 'selected' : '' ?>>1er Trimestre</option>
                    <option value="2do Trimestre" <?= $periodo_seleccionado == '2do Trimestre' ? 'selected' : '' ?>>2do Trimestre</option>
                    <option value="3er Trimestre" <?= $periodo_seleccionado == '3er Trimestre' ? 'selected' : '' ?>>3er Trimestre</option>
                    <option value="Final" <?= $periodo_seleccionado == 'Final' ? 'selected' : '' ?>>Instancia Final</option>
                </select>
            </div>

            <div class="form-grupo-filtro">
                <label for="filtro_curso">Curso:</label>
                <select name="filtro_curso" id="filtro_curso" class="select-control" onchange="cargarAlumnos(this.value)">
                    <option value="">-- Todos los cursos --</option>
                    <?php 
                    // Obtener cursos para el filtro (según rol)
                    if($es_preceptor){
                        $query_cursos_filtro = "SELECT c.ID_curso, c.curso, c.division, c.turno
                                                FROM curso c
                                                INNER JOIN preceptorxcurso pxc ON c.ID_curso = pxc.id_curso
                                                WHERE pxc.id_preceptor = '$preceptor_dni'
                                                ORDER BY c.curso, c.division";
                    } else {
                        $query_cursos_filtro = "SELECT ID_curso, curso, division, turno FROM curso ORDER BY curso, division";
                    }
                    $res_cursos_filtro = mysqli_query($con, $query_cursos_filtro);
                    while($curso_filtro = mysqli_fetch_assoc($res_cursos_filtro)):
                        $turno_texto = $curso_filtro['turno'] == 'M' ? 'Mañana' : 'Tarde';
                        $curso_nombre_filtro = $curso_filtro['curso'] . '° "' . $curso_filtro['division'] . '" - ' . $turno_texto;
                        $selected = (isset($_GET['filtro_curso']) && $_GET['filtro_curso'] == $curso_filtro['ID_curso']) ? 'selected' : '';
                    ?>
                        <option value="<?= $curso_filtro['ID_curso'] ?>" <?= $selected ?>>
                            <?= htmlspecialchars($curso_nombre_filtro) ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>

            <div class="form-grupo-filtro">
                <label for="filtro_alumno">Alumno:</label>
                <select name="filtro_alumno" id="filtro_alumno" class="select-control" <?= empty($filtro_curso) ? 'disabled' : '' ?>>
                    <option value="">-- Todos los alumnos --</option>
                    <?php 
                    if(!empty($filtro_curso)){
                        $query_alumnos_filtro = "SELECT DNI_U, Nombre, Apellido 
                                                 FROM usuario 
                                                 WHERE id_curso = '$filtro_curso' 
                                                 AND ID_rol = 3 
                                                 AND ID_Estado = 1
                                                 ORDER BY Apellido, Nombre";
                        $res_alumnos_filtro = mysqli_query($con, $query_alumnos_filtro);
                        while($alumno_filtro = mysqli_fetch_assoc($res_alumnos_filtro)){
                            $selected = (isset($_GET['filtro_alumno']) && $_GET['filtro_alumno'] == $alumno_filtro['DNI_U']) ? 'selected' : '';
                    ?>
                            <option value="<?= $alumno_filtro['DNI_U'] ?>" <?= $selected ?>>
                                <?= htmlspecialchars($alumno_filtro['Apellido'] . ', ' . $alumno_filtro['Nombre']) ?>
                            </option>
                    <?php 
                        }
                    }
                    ?>
                </select>
            </div>

            <div class="form-grupo-filtro" style="flex-direction: row; gap: 8px; align-items: center; padding-bottom: 1px;">
                <button type="submit" class="btn-aplicar-filtros"><i class="fas fa-filter"></i> Aplicar</button>
                <a href="control_recepcion.php" class="btn-limpiar-filtros"><i class="fas fa-times"></i> Limpiar</a>
            </div>
        </form>

        <div class="form-grupo" style="display: flex; align-items: center; gap: 10px;">
            <input type="text" id="buscadorInput" class="search-control" placeholder="🔍 Buscar por Alumno, Tutor o Curso...">
        </div>
    </div>

    <div class="contenedor-tabla">
        <table class="tabla-institucional" id="tablaControl">
            <thead>
                <tr>
                    <th>Curso/División</th>
                    <th>Alumno (Estudiante)</th>
                    <th>Tutor Responsable</th>
                    <th>Estado Envío</th>
                    <th>Firma del Tutor</th>
                </tr>
            </thead>
            <tbody>
                <?php if(empty($filas)): ?>
                    <tr>
                        <td colspan="5" class="no-data">No se encontraron alumnos activos ni tutores asignados a sus cursos.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach($filas as $r): ?>
                        <tr class="fila-datos">
                            <td>
                                <strong><?= $r['curso'] ?>° "<?= $r['division'] ?>"</strong>
                                <small><?= $r['turno'] == 'M' ? 'Turno Mañana' : ($r['turno'] == 'T' ? 'Turno Tarde' : 'Turno Noche') ?></small>
                            </td>
                            
                            <td>
                                <strong><?= htmlspecialchars($r['alumno_nombre']) ?></strong>
                                <small>DNI: <?= $r['alumno_dni'] ?></small>
                            </td>
                            
                            <td>
                                <?= htmlspecialchars($r['tutor_nombre']) ?>
                                <small><?= htmlspecialchars($r['tutor_email']) ?></small>
                            </td>
                            
                            <td>
                                <?php if(!empty($r['fecha_envio'])): ?>
                                    <span class="badge bg-warning"><i class="fas fa-paper-plane"></i> Enviado</span>
                                    <small>El: <?= date('d/m/Y H:i', strtotime($r['fecha_envio'])) ?></small>
                                <?php else: ?>
                                    <span class="badge bg-danger"><i class="fas fa-times-circle"></i> No Enviado</span>
                                    <small>Falta procesar masivo</small>
                                    <?php if(!$es_equipo): ?>
                                        <br>
                                        <button class="btn-enviar-individual" 
                                                onclick='enviarLibretaIndividual(
                                                    <?= (int)$r['tutor_dni'] ?>, 
                                                    <?= (int)$r['alumno_dni'] ?>, 
                                                    <?= json_encode($r['tutor_nombre']) ?>, 
                                                    <?= json_encode($r['alumno_nombre']) ?>, 
                                                    <?= json_encode($r['curso'] . '° '.$r['division']) ?>
                                                )'>
                                            <i class="fas fa-envelope"></i> Enviar ahora
                                        </button>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if($r['confirmado'] == 1): ?>
                                    <span class="badge bg-success"><i class="fas fa-check-circle"></i> Notificado</span>
                                    <small>Leído: <?= date('d/m/Y H:i', strtotime($r['fecha_confirmacion'])) ?> hs</small>
                                <?php else: ?>
                                    <span class="badge bg-danger" style="background-color: #fff0f0; color: #d32f2f;"><i class="fas fa-clock"></i> Sin Confirmar</span>
                                    <small>Aún no presionó el botón</small>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
// ============================================
// BUSCADOR EN TIEMPO REAL
// ============================================
document.getElementById('buscadorInput').addEventListener('keyup', function() {
    let filtro = this.value.toLowerCase().trim();
    let filas = document.querySelectorAll('.fila-datos');
    
    filas.forEach(function(fila) {
        let textoFila = fila.textContent.toLowerCase();
        if(textoFila.includes(filtro)) {
            fila.style.display = '';
        } else {
            fila.style.display = 'none';
        }
    });
});

// ============================================
// AJAX PARA CARGAR ALUMNOS POR CURSO
// ============================================
function cargarAlumnos(cursoId) {
    var $alumnoSelect = $('#filtro_alumno');
    
    if(cursoId) {
        $alumnoSelect.prop('disabled', true).html('<option value="">Cargando alumnos...</option>');
        
        $.ajax({
            url: 'ajax_control_recepcion.php',
            type: 'POST',
            data: { action: 'get_alumnos_curso', curso_id: cursoId },
            dataType: 'json',
            success: function(response) {
                if(response.success) {
                    $alumnoSelect.html('<option value="">-- Todos los alumnos --</option>');
                    $.each(response.alumnos, function(i, alumno) {
                        $alumnoSelect.append('<option value="' + alumno.DNI_U + '">' + alumno.Apellido + ', ' + alumno.Nombre + '</option>');
                    });
                    $alumnoSelect.prop('disabled', false);
                    
                    // Si hay un alumno seleccionado en la URL, seleccionarlo
                    <?php if(!empty($filtro_alumno)): ?>
                    $alumnoSelect.val('<?= $filtro_alumno ?>');
                    <?php endif; ?>
                } else {
                    $alumnoSelect.html('<option value="">' + response.message + '</option>');
                    $alumnoSelect.prop('disabled', true);
                }
            },
            error: function() {
                $alumnoSelect.html('<option value="">Error al cargar alumnos</option>');
                $alumnoSelect.prop('disabled', true);
            }
        });
    } else {
        $alumnoSelect.prop('disabled', true).html('<option value="">-- Todos los alumnos --</option>');
    }
}

// ============================================
// ENVÍO INDIVIDUAL DE LIBRETA
// ============================================
function enviarLibretaIndividual(tutorDni, alumnoDni, tutorNombre, alumnoNombre, cursoNombre) {
    if(!confirm(`⚠️ ¿Estás seguro de enviar la libreta a ${tutorNombre} (tutor de ${alumnoNombre}) del curso ${cursoNombre}?\n\nEsto enviará un email con el PDF adjunto.`)) {
        return;
    }
    
    // Obtener el tipo de envío seleccionado
    const periodo = document.getElementById('periodo').value;
    let tipoEnvio = 'final';
    if(periodo == '1er Trimestre') tipoEnvio = 'trimestre1';
    else if(periodo == '2do Trimestre') tipoEnvio = 'trimestre2';
    else if(periodo == '3er Trimestre') tipoEnvio = 'trimestre3';
    
    const btn = event.target;
    const textoOriginal = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-spinner fa-pulse"></i> Enviando...';
    btn.disabled = true;
    
    // Obtener el año actual
    const anio = new Date().getFullYear();
    
    // Primero obtener el HTML de la libreta
    $.ajax({
        url: 'obtener_html_libreta.php',
        type: 'POST',
        data: {
            alumno_id: alumnoDni,
            anio: anio,
            tipo_envio: tipoEnvio
        },
        dataType: 'text',
        success: function(htmlContent) {
            generarPDFIndividual(htmlContent, alumnoDni, alumnoNombre, tutorDni, tutorNombre, tipoEnvio, btn, textoOriginal);
        },
        error: function(xhr, status, error) {
            alert('❌ Error al generar la libreta: ' + error);
            btn.innerHTML = textoOriginal;
            btn.disabled = false;
        }
    });
}

function generarPDFIndividual(htmlContent, alumnoDni, alumnoNombre, tutorDni, tutorNombre, tipoEnvio, btn, textoOriginal) {
    console.log('=== INICIO generarPDFIndividual ===');
    
    // Crear contenedor temporal
    const contenedor = document.createElement('div');
    contenedor.style.position = 'absolute';
    contenedor.style.left = '-9999px';
    contenedor.style.top = '0';
    contenedor.style.width = '1400px';
    contenedor.style.backgroundColor = '#ffffff';
    contenedor.style.padding = '20px';
    contenedor.style.zIndex = '99999';
    contenedor.innerHTML = htmlContent;
    document.body.appendChild(contenedor);
    console.log('Contenedor creado y agregado al DOM');
    
    setTimeout(async function() {
        console.log('Esperando renderizado...');
        await document.fonts.ready;
        console.log('Fuentes cargadas');
        
        try {
            const domContainer = contenedor.querySelector('.libreta-container');
            if (!domContainer) {
                throw new Error('No se encontró el contenedor de la libreta');
            }
            console.log('Contenedor de libreta encontrado');
            
            console.log('Iniciando captura con html2canvas...');
            // Opciones de html2canvas
            const canvas = await html2canvas(domContainer, {
                scale: 2,
                useCORS: true,
                backgroundColor: '#ffffff',
                windowWidth: 1400,
                logging: true,
                onclone: function(clonedDoc) {
                    // Mismo parche del masivo: Texto Vertical en HD
                    const verticales = clonedDoc.querySelectorAll('.tabla-libreta th.vertical-text');
                    verticales.forEach(th => {
                        const text = th.innerText.trim();
                        const tempCanvas = document.createElement('canvas');
                        const ctx = tempCanvas.getContext('2d');
                        ctx.font = 'bold 12px Arial, sans-serif';
                        const textWidth = ctx.measureText(text).width;
                        const textHeight = 16;
                        const logicalWidth = textHeight;
                        const logicalHeight = textWidth + 16;
                        const scaleFactor = 4;
                        
                        tempCanvas.width = logicalWidth * scaleFactor;
                        tempCanvas.height = logicalHeight * scaleFactor;
                        ctx.scale(scaleFactor, scaleFactor);
                        ctx.font = 'bold 12px Arial, sans-serif';
                        ctx.translate(logicalWidth / 2, logicalHeight / 2);
                        ctx.rotate(-Math.PI / 2);
                        ctx.textAlign = 'center';
                        ctx.textBaseline = 'middle';
                        ctx.fillStyle = '#ffffff';
                        ctx.fillText(text, 0, 0);
                        
                        const img = document.createElement('img');
                        img.src = tempCanvas.toDataURL('image/png', 1.0);
                        img.style.display = 'block';
                        img.style.margin = '0 auto';
                        img.style.width = logicalWidth + 'px';
                        img.style.height = logicalHeight + 'px';
                        
                        th.style.writingMode = 'initial';
                        th.style.transform = 'none';
                        th.style.padding = '8px 4px';
                        th.innerHTML = '';
                        th.appendChild(img);
                    });
                }
            });
            console.log('Canvas generado:', canvas.width, 'x', canvas.height);
            
            if (!canvas || canvas.width === 0) {
                throw new Error('El canvas está vacío');
            }
            
            // Convertir a PDF
            console.log('Generando PDF...');
            const { jsPDF } = window.jspdf;
            const doc = new jsPDF('l', 'mm', 'a4');
            const pageWidth = doc.internal.pageSize.getWidth();
            const margin = 10;
            const imgWidth = pageWidth - (margin * 2);
            
            const imgData = canvas.toDataURL('image/jpeg', 0.95);
            const imgHeight = (canvas.height * imgWidth) / canvas.width;
            
            doc.addImage(imgData, 'JPEG', margin, margin, imgWidth, imgHeight);
            
            const pdfOutput = doc.output('datauristring');
            const base64Data = pdfOutput.split('base64,')[1];
            console.log('PDF generado, tamaño base64:', base64Data ? base64Data.length : 0);
            
            // Limpiar contenedor
            document.body.removeChild(contenedor);
            console.log('Contenedor eliminado');
            
            if (!base64Data || base64Data.length < 1000) {
                throw new Error('El PDF generado está vacío o corrupto (tamaño: ' + (base64Data ? base64Data.length : 0) + ')');
            }
            
            // Enviar por email
            let nombreTrimestre = '';
            if(tipoEnvio == 'trimestre1') nombreTrimestre = '1er Trimestre';
            else if(tipoEnvio == 'trimestre2') nombreTrimestre = '2do Trimestre';
            else if(tipoEnvio == 'trimestre3') nombreTrimestre = '3er Trimestre';
            else nombreTrimestre = 'Año Completo';
            
            console.log('Enviando email...');
            $.ajax({
                url: 'procesar_envio_masivo.php',
                type: 'POST',
                data: {
                    action: 'enviar_email_individual',
                    alumno_id: alumnoDni,
                    tutor_dni: tutorDni,
                    pdf_base64: base64Data,
                    tipo_envio_nombre: nombreTrimestre
                },
                dataType: 'json',
                success: function(response) {
                    console.log('Respuesta del servidor:', response);
                    if(response.success) {
                        alert('✅ Libreta enviada correctamente a ' + tutorNombre);
                        location.reload();
                    } else {
                        alert('❌ Error al enviar: ' + (response.error || 'Error desconocido'));
                        btn.innerHTML = textoOriginal;
                        btn.disabled = false;
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Error en AJAX enviar_email_individual:', error);
                    console.error('Status:', status);
                    console.error('Response:', xhr.responseText);
                    alert('❌ Error al enviar el email: ' + error);
                    btn.innerHTML = textoOriginal;
                    btn.disabled = false;
                }
            });
            
        } catch(error) {
            console.error('Error en generarPDFIndividual:', error);
            if (document.body.contains(contenedor)) {
                document.body.removeChild(contenedor);
            }
            alert('❌ Error al generar el PDF: ' + error.message);
            btn.innerHTML = textoOriginal;
            btn.disabled = false;
        }
    }, 500);
}
</script>

</body>
</html>
<?php mysqli_close($con); ?>