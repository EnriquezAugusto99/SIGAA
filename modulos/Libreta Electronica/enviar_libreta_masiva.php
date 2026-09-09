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

// Verificar permisos - Admin o Preceptor
$es_admin = ($_SESSION['rol'] == 'Admin');
$es_preceptor = ($_SESSION['rol'] == 'Preceptor');

if(!$es_admin && !$es_preceptor){
    echo '<script>alert("No tiene permisos para acceder a esta sección"); window.location="../../recursos/panel.php";</script>';
    exit();
}

include '../../recursos/conexion.php';

// Obtener años disponibles
$query_anios = "SELECT DISTINCT anio FROM rotaciones ORDER BY anio DESC";
$res_anios = mysqli_query($con, $query_anios);
$anios = [];
while($row = mysqli_fetch_array($res_anios)){
    $anios[] = $row['anio'];
}

if(empty($anios)){
    $anios = [date('Y')];
}

$anio_seleccionado = isset($_GET['anio']) ? $_GET['anio'] : date('Y');

// ============================================
// OBTENER CURSOS SEGÚN ROL
// ============================================
$cursos_usuario = [];
$preceptor_dni = $_SESSION["dni"];

if($es_admin){
    // Admin: todos los cursos
    $query_cursos = "SELECT ID_curso, curso, division, turno FROM curso ORDER BY curso, division";
    $res_cursos = mysqli_query($con, $query_cursos);
    while($row = mysqli_fetch_assoc($res_cursos)){
        $cursos_usuario[] = $row;
    }
} else {
    // Preceptor: solo cursos asignados
    $query_cursos = "SELECT c.ID_curso, c.curso, c.division, c.turno
                     FROM curso c
                     INNER JOIN preceptorxcurso pxc ON c.ID_curso = pxc.id_curso
                     WHERE pxc.id_preceptor = '$preceptor_dni'
                     ORDER BY c.curso, c.division";
    $res_cursos = mysqli_query($con, $query_cursos);
    while($row = mysqli_fetch_assoc($res_cursos)){
        $cursos_usuario[] = $row;
    }
}

// Obtener lista de cursos del preceptor (para el filtro)
$cursos_preceptor_lista = [];
if($es_preceptor){
    $query_cursos_pre = "SELECT c.ID_curso, c.curso, c.division, c.turno
                         FROM curso c
                         INNER JOIN preceptorxcurso pxc ON c.ID_curso = pxc.id_curso
                         WHERE pxc.id_preceptor = '$preceptor_dni'
                         ORDER BY c.curso, c.division";
    $res_cursos_pre = mysqli_query($con, $query_cursos_pre);
    while($row = mysqli_fetch_assoc($res_cursos_pre)){
        $cursos_preceptor_lista[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <title>Envío Masivo de Libretas - <?= $es_admin ? 'Administrador' : 'Preceptor' ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link href="../../recursos/styles.css" rel="stylesheet">
    <style>
        .container-envio {
            max-width: 1400px;
            margin: 20px auto;
            padding: 25px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
        }
        h1 {
            color: #7a0000;
            border-bottom: 2px solid #e0e0e0;
            padding-bottom: 15px;
            margin-bottom: 25px;
        }
        .filtro-anio {
            background: #f5f5f5;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 25px;
        }
        .btn-enviar {
            background: #2e7d32;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: bold;
        }
        .btn-enviar:disabled {
            background: #ccc;
            cursor: not-allowed;
        }
        .btn-enviar-curso {
            background: #2196F3;
            color: white;
            padding: 6px 12px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: bold;
            font-size: 12px;
        }
        .btn-verificar {
            background: #2196F3;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: bold;
        }
        .tabla-cursos {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            font-size: 13px;
        }
        .tabla-cursos th, .tabla-cursos td {
            border: 1px solid #ddd;
            padding: 10px;
            text-align: center;
            vertical-align: middle;
        }
        .tabla-cursos th {
            background: #7a0000;
            color: white;
            position: sticky;
            top: 0;
        }
        .curso-completo {
            background-color: #d4edda;
        }
        .curso-incompleto {
            background-color: #f8d7da;
        }
        .badge-completo {
            background: #2e7d32;
            color: white;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 11px;
        }
        .badge-incompleto {
            background: #d32f2f;
            color: white;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 11px;
        }
        .badge-parcial {
            background: #ff9800;
            color: white;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 11px;
        }
        .loading {
            display: none;
            text-align: center;
            padding: 20px;
        }
        .spinner {
            width: 40px;
            height: 40px;
            border: 4px solid #f3f3f3;
            border-top: 4px solid #7a0000;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 0 auto;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        .resultado-envio {
            margin-top: 20px;
            padding: 15px;
            border-radius: 8px;
            display: none;
        }
        .resultado-exito {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .resultado-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .progreso {
            margin-top: 20px;
            margin-bottom: 20px;
            display: none;
        }
        .progress-bar {
            height: 30px;
            background: #7a0000;
            color: white;
            text-align: center;
            line-height: 30px;
            border-radius: 5px;
            transition: width 0.3s;
        }
        .btn-trimestre {
            background: #ff9800;
            color: white;
            border: none;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 11px;
            cursor: pointer;
            margin: 2px;
        }
        .btn-trimestre:hover {
            background: #f57c00;
        }
        .resumen-curso {
            font-size: 11px;
            margin-top: 5px;
            color: #666;
        }
        .filtro-rapido {
            margin-bottom: 15px;
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        .btn-filtro {
            background: #e0e0e0;
            color: black;
            border: none;
            padding: 5px 12px;
            border-radius: 20px;
            cursor: pointer;
            font-size: 12px;
        }
        .btn-filtro:hover {
            color: white;
        }
        .btn-filtro.activo {
            background: #7a0000;
            color: white;
        }
        .preceptor-badge {
            background: #ff9800;
            color: #1a2a3a;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.8em;
            font-weight: bold;
            margin-left: 15px;
        }
        .curso-selector {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border: 1px solid #dee2e6;
        }
        .curso-selector select {
            width: 100%;
            max-width: 400px;
            padding: 10px;
            border-radius: 6px;
            border: 1px solid #ddd;
        }
        .info-preceptor {
            background: #e8f0fe;
            padding: 12px 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #2196F3;
        }
        .btn-volver {
            margin-top: 10px;
        }

        /* Estilos responsive */
        @media (max-width: 992px) {
            .container-envio {
                padding: 15px;
                margin: 10px;
            }
            .filtro-anio .row,
            .curso-selector .row {
                flex-direction: column;
                gap: 12px;
            }
            .filtro-anio .col-md-3,
            .curso-selector .col-md-8,
            .curso-selector .col-md-4 {
                width: 100%;
                text-align: center;
            }
            .btn-verificar, .btn-enviar {
                width: 100%;
                margin-top: 5px;
            }
            .curso-selector select {
                max-width: 100%;
            }
            .btn-enviar-curso {
                margin-top: 0 !important;
                width: 100%;
            }
        }

        @media (max-width: 768px) {
            h1 {
                font-size: 1.4rem;
                text-align: center;
            }
            .preceptor-badge {
                display: inline-block;
                margin-top: 8px;
                font-size: 0.7rem;
            }
            .info-preceptor {
                font-size: 0.75rem;
                padding: 8px 12px;
            }
            .filtro-anio, .filtro-curso, .curso-selector {
                padding: 12px;
            }
            .filtro-anio label, .curso-selector label {
                font-size: 0.8rem;
            }
            .filtro-anio select, .curso-selector select {
                padding: 8px;
                font-size: 0.85rem;
            }
            .btn-verificar, .btn-enviar, .btn-enviar-curso {
                padding: 8px 12px;
                font-size: 0.8rem;
            }
            .tabla-cursos {
                display: block;
                width: 100%;
            }
            .tabla-cursos thead {
                display: none;
            }
            .tabla-cursos tbody {
                display: block;
                width: 100%;
            }
            .tabla-cursos tr {
                display: block;
                width: 100%;
                margin-bottom: 15px;
                border: 1px solid #ddd;
                border-radius: 8px;
                background: white;
                padding: 10px;
                box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            }
            .tabla-cursos td {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 8px 10px;
                border: none;
                border-bottom: 1px solid #eee;
                text-align: right;
            }
            .tabla-cursos td:last-child {
                border-bottom: none;
            }
            .tabla-cursos td::before {
                content: attr(data-label);
                font-weight: bold;
                text-align: left;
                flex: 1;
                font-size: 0.75rem;
                color: #7a0000;
            }
            .resumen-curso {
                margin-top: 5px;
                font-size: 0.7rem;
            }
            .badge-completo, .badge-incompleto, .badge-parcial {
                padding: 3px 8px;
                font-size: 0.7rem;
            }
            .btn-trimestre {
                padding: 6px 10px;
                font-size: 0.7rem;
                width: 100%;
            }
            .progress-bar {
                font-size: 0.7rem;
                height: 25px;
                line-height: 25px;
            }
            .resultado-exito, .resultado-error {
                font-size: 0.75rem;
                padding: 10px;
            }
            .filtro-rapido {
                justify-content: center;
            }
            .btn-filtro {
                padding: 5px 12px;
                font-size: 0.7rem;
            }
            .btn-volver {
                padding: 8px 16px;
                font-size: 0.8rem;
            }
        }

        @media (max-width: 480px) {
            h1 {
                font-size: 1.2rem;
            }
            .preceptor-badge {
                font-size: 0.6rem;
                padding: 3px 8px;
            }
            .tabla-cursos td {
                flex-direction: column;
                align-items: flex-start;
                text-align: left;
                padding: 6px 8px;
            }
            .tabla-cursos td::before {
                margin-bottom: 4px;
                font-size: 0.7rem;
            }
            .tabla-cursos td {
                font-size: 0.75rem;
            }
            .btn-trimestre {
                font-size: 0.65rem;
                padding: 5px 8px;
            }
        }
    </style>
</head>
<body>
    <div class="container-envio">
        <h1>
             Envío Masivo de Libretas Electrónicas
            <?php if($es_preceptor): ?>
                <span class="preceptor-badge"> Modo Preceptor - Solo tus cursos</span>
            <?php else: ?>
                <span class="preceptor-badge" style="background: #7a0000; color: white;"> Modo Administrador</span>
            <?php endif; ?>
        </h1>
        
        <?php if($es_preceptor): ?>
        <div class="info-preceptor">
            <strong> Preceptor:</strong> <?= $_SESSION['nombre'] . ' ' . $_SESSION['apellido'] ?> | 
            <strong> Cursos asignados:</strong> <?= count($cursos_usuario) ?>
        </div>
        <?php endif; ?>
        
        <div class="filtro-anio">
            <div class="row align-items-center">
                <div class="col-md-3">
                    <label class="form-label fw-bold">Año Lectivo:</label>
                    <select id="anio" class="form-select">
                        <?php foreach($anios as $a): ?>
                            <option value="<?= $a ?>" <?= $a == $anio_seleccionado ? 'selected' : '' ?>><?= $a ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-bold">Tipo de Envío:</label>
                    <select id="tipo_envio" class="form-select">
                        <option value="final"> Libreta Final (3 trimestres completos)</option>
                        <option value="trimestre1"> 1er Trimestre</option>
                        <option value="trimestre2"> 2do Trimestre</option>
                        <option value="trimestre3"> 3er Trimestre</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <button class="btn-verificar" onclick="verificarCursos()"> Verificar Calificaciones</button>
                </div>
                <div class="col-md-3 text-end">
                    <button class="btn-enviar" onclick="enviarMasivo()" id="btnEnviar" disabled> Enviar a Cursos Completos</button>
                </div>
            </div>
        </div>
        
        <!-- Selector de curso específico para Preceptor -->
        <?php if($es_preceptor && !empty($cursos_preceptor_lista)): ?>
        <div class="curso-selector">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <label class="form-label fw-bold"> Enviar libretas de un curso específico:</label>
                    <select id="curso_especifico" class="form-select">
                        <option value="">-- Seleccione un curso --</option>
                        <?php foreach($cursos_preceptor_lista as $curso): 
                            $turno_texto = $curso['turno'] == 'M' ? 'Mañana' : 'Tarde';
                            $curso_nombre = $curso['curso'] . '° "' . $curso['division'] . '" - ' . $turno_texto;
                        ?>
                            <option value="<?= $curso['ID_curso'] ?>"><?= htmlspecialchars($curso_nombre) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <button class="btn-enviar-curso" onclick="enviarCursoEspecifico()" style="margin-top: 24px;"> Enviar este curso</button>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <div class="filtro-rapido" id="filtrosRapidos" style="display: none;">
            <button class="btn-filtro" onclick="filtrarPorEstado('todos')"> Todos</button>
            <button class="btn-filtro" onclick="filtrarPorEstado('completos')"> Cursos Completos</button>
            <button class="btn-filtro" onclick="filtrarPorEstado('incompletos')"> Cursos Incompletos</button>
        </div>
        
        <div id="loading" class="loading">
            <div class="spinner"></div>
            <p>Verificando calificaciones...</p>
        </div>
        
        <div id="resultado" class="resultado-envio"></div>
        
        <div id="progreso" class="progreso">
            <div class="progress-bar" style="width: 0%">0%</div>
        </div>
        
        <div id="contenedor-cursos"></div>
        
        <center>
            <a href="../../recursos/panel.php"><button class="btn-volver">Volver</button></a>
        </center>
    </div>
    
    <!-- Contenedor oculto para generar PDFs -->
    <div id="contenedor-pdf" style="position: absolute; left: -9999px; top: -9999px;"></div>
    
    <script>
        let cursosData = [];
        let tipoEnvioActual = 'final';
        let filtroActual = 'todos';
        let alumnosProcesados = [];
        <?php if($es_preceptor): ?>
        let esPreceptor = true;
        let cursosPreceptor = <?= json_encode($cursos_preceptor_lista) ?>;
        <?php else: ?>
        let esPreceptor = false;
        <?php endif; ?>
        
        function verificarCursos() {
            let anio = $('#anio').val();
            tipoEnvioActual = $('#tipo_envio').val();
            
            $('#loading').show();
            $('#contenedor-cursos').empty();
            $('#resultado').hide();
            $('#btnEnviar').prop('disabled', true);
            $('#filtrosRapidos').hide();
            
            $.ajax({
                url: 'verificar_calificaciones.php',
                type: 'POST',
                data: { 
                    anio: anio, 
                    tipo_envio: tipoEnvioActual,
                    action: 'verificar_cursos_trimestre',
                    rol: <?= $es_preceptor ? "'preceptor'" : "'admin'" ?>,
                    preceptor_dni: <?= $es_preceptor ? "'$preceptor_dni'" : "''" ?>
                },
                dataType: 'json',
                success: function(response) {
                    $('#loading').hide();
                    if(response.success) {
                        cursosData = response.cursos;
                        mostrarTablaCursos(response.cursos, tipoEnvioActual);
                        $('#filtrosRapidos').show();
                        
                        let hayCompletos = response.cursos.some(curso => curso.completo);
                        if(hayCompletos) {
                            $('#btnEnviar').prop('disabled', false);
                        }
                    } else {
                        $('#resultado').html('<div class="resultado-error">❌ Error: ' + response.error + '</div>').show();
                    }
                },
                error: function(xhr, status, error) {
                    $('#loading').hide();
                    $('#resultado').html('<div class="resultado-error">❌ Error al verificar: ' + error + '</div>').show();
                }
            });
        }
        
        function mostrarTablaCursos(cursos, tipoEnvio) {
            let tituloTrimestre = '';
            if(tipoEnvio == 'trimestre1') tituloTrimestre = '1er Trimestre';
            else if(tipoEnvio == 'trimestre2') tituloTrimestre = '2do Trimestre';
            else if(tipoEnvio == 'trimestre3') tituloTrimestre = '3er Trimestre';
            else tituloTrimestre = 'Año Completo';
            
            let html = `<h3 class="mt-4">📋 Estado de Calificaciones - ${tituloTrimestre}</h3>`;
            html += '<table class="tabla-cursos" id="tablaCursos">';
            html += '<thead><tr>';
            html += '<th>Curso</th>';
            html += '<th>Año</th>';
            html += '<th>Turno</th>';
            html += '<th>Total Alumnos</th>';
            html += '<th>Alumnos Completos</th>';
            html += '<th>Estado</th>';
            html += '<th>Acciones</th>';
            html += '</thead><tbody>';
            
            for(let curso of cursos) {
                let estadoClass = curso.completo ? 'curso-completo' : 'curso-incompleto';
                let estadoBadge = '';
                if(curso.completo) {
                    estadoBadge = '<span class="badge-completo">✅ Completo</span>';
                } else if(curso.parcial) {
                    estadoBadge = '<span class="badge-parcial">🟡 Parcial</span>';
                } else {
                    estadoBadge = '<span class="badge-incompleto">❌ Incompleto</span>';
                }
                
                let accion = '';
                if(curso.completo) {
                    accion = `<button class="btn-trimestre" onclick="enviarCurso(${curso.id_curso})">📧 Enviar este curso</button>`;
                }
                
                let trimestresDisponibles = '';
                if(curso.trimestres) {
                    let t = curso.trimestres;
                    trimestresDisponibles = `<div class="resumen-curso">
                        ${t.t1 ? '✅ 1er Trim' : '❌ 1er Trim'} | 
                        ${t.t2 ? '✅ 2do Trim' : '❌ 2do Trim'} | 
                        ${t.t3 ? '✅ 3er Trim' : '❌ 3er Trim'}
                    </div>`;
                }
                
                html += `<tr class="${estadoClass}" data-completo="${curso.completo}">
                    <td data-label="Curso">${curso.curso}° "${curso.division}" ${trimestresDisponibles}</td>
                    <td data-label="Año">${curso.anio}</td>
                    <td data-label="Turno">${curso.turno == 'M' ? 'Mañana' : 'Tarde'}</td>
                    <td data-label="Total">${curso.total_alumnos}</td>
                    <td data-label="Completos">${curso.alumnos_completos} / ${curso.total_alumnos}</td>
                    <td data-label="Estado">${estadoBadge}</td>
                    <td data-label="Acciones">${accion}</td>
                </tr>`;
            }
            
            html += '</tbody></tr>';
            $('#contenedor-cursos').html(html);
        }
        
        function filtrarPorEstado(estado) {
            filtroActual = estado;
            
            $('.btn-filtro').removeClass('activo');
            $(event.target).addClass('activo');
            
            let filas = $('#tablaCursos tbody tr');
            filas.each(function() {
                let estaCompleto = $(this).data('completo') === true;
                if(estado == 'todos') {
                    $(this).show();
                } else if(estado == 'completos' && estaCompleto) {
                    $(this).show();
                } else if(estado == 'incompletos' && !estaCompleto) {
                    $(this).show();
                } else {
                    $(this).hide();
                }
            });
        }
        
        function enviarCursoEspecifico() {
            let cursoId = $('#curso_especifico').val();
            if(!cursoId) {
                alert('Seleccione un curso primero');
                return;
            }
            enviarCurso(parseInt(cursoId));
        }
        
        // ============================================
// FUNCIÓN PARA GENERAR PDF DESDE HTML CORREGIDA (IGUAL A LA DEL ADMIN)
// ============================================
async function generarPDFDesdeHTML(htmlContent, alumnoId, alumnoNombre) {
    // 1. Crear un contenedor con fondo blanco y dimensiones fijas
    const contenedor = document.createElement('div');
    contenedor.style.position = 'absolute';
    contenedor.style.left = '-9999px'; // Oculto fuera de pantalla
    contenedor.style.top = '0';
    contenedor.style.width = '1400px'; // Forzar ancho ancho para landscape
    contenedor.style.backgroundColor = '#ffffff';
    contenedor.innerHTML = htmlContent;
    document.body.appendChild(contenedor);
    
    // Esperar a que el DOM y las fuentes se rendericen
    await new Promise(resolve => setTimeout(resolve, 500));
    await document.fonts.ready;
    
    try {
        const domContainer = contenedor.querySelector('.libreta-container');

        // --- CALCULAR SI SEPARAR PREVIAS COMO EN EL ARCHIVO IDEAL ---
        let previasContenedor = null;
        domContainer.querySelectorAll('h2').forEach(h2 => {
            if(h2.innerText.includes('Previas y Equivalencias')) {
                previasContenedor = h2.parentElement;
            }
        });

        let numPrevias = 0; let numEquivalencias = 0;
        if (previasContenedor) {
            previasContenedor.querySelectorAll('h3').forEach(h3 => {
                const columna = h3.parentElement.parentElement;
                if (h3.innerText.includes('Previas')) {
                    numPrevias = columna.querySelectorAll('li').length;
                } else if (h3.innerText.includes('Equivalencias')) {
                    numEquivalencias = columna.querySelectorAll('li').length;
                }
            });
        }

        const tieneTalleres = domContainer.querySelector('#tabla-talleres') !== null;
        const tieneAlgunaPrevia = (numPrevias > 0 || numEquivalencias > 0);
        const separarPrevias = (numPrevias > 2 || numEquivalencias > 2) || (tieneTalleres && tieneAlgunaPrevia);

        // --- OPCIONES PARA RENDERIZADO ALTA CALIDAD ---
        const getOpcionesHtml2Canvas = (esSegundaParte) => {
            return {
                scale: 2,
                useCORS: true,
                backgroundColor: '#ffffff',
                windowWidth: 1400,
                onclone: function(clonedDoc) {
                    // Texto Vertical en HD
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

                    // Separación de hojas
                    if (separarPrevias) {
                        const clonedContenedor = clonedDoc.querySelector('.libreta-container');
                        let clonedPrevias = null;
                        clonedContenedor.querySelectorAll('h2').forEach(h2 => {
                            if(h2.innerText.includes('Previas y Equivalencias')) {
                                clonedPrevias = h2.parentElement;
                            }
                        });

                        if (clonedPrevias) {
                            if (!esSegundaParte) {
                                clonedPrevias.style.display = 'none'; // Hoja 1
                            } else {
                                Array.from(clonedContenedor.children).forEach(child => {
                                    if (child !== clonedPrevias) {
                                        child.style.display = 'none'; // Hoja 2
                                    }
                                });
                            }
                        }
                    }
                }
            };
        };

        // 2. Crear instancia de jsPDF en formato Apaisado (Landscape 'l')
        const { jsPDF } = window.jspdf;
        const doc = new jsPDF('l', 'mm', 'a4');
        const pageWidth = doc.internal.pageSize.getWidth();
        const pageHeight = doc.internal.pageSize.getHeight();
        const margin = 10;
        const imgWidth = pageWidth - (margin * 2);

        const agregarCanvasAlPDF = (canvas) => {
            const imgData = canvas.toDataURL('image/jpeg', 0.95);
            const imgHeight = (canvas.height * imgWidth) / canvas.width;
            let heightLeft = imgHeight;
            let position = margin;

            doc.addImage(imgData, 'JPEG', margin, position, imgWidth, imgHeight);
            heightLeft -= (pageHeight - margin * 2);

            while (heightLeft > 0) {
                position = heightLeft - imgHeight + margin;
                doc.addPage();
                doc.addImage(imgData, 'JPEG', margin, position, imgWidth, imgHeight);
                heightLeft -= (pageHeight - margin * 2);
            }
        };

        // Captura Hoja 1
        const canvas1 = await html2canvas(domContainer, getOpcionesHtml2Canvas(false));
        agregarCanvasAlPDF(canvas1);

        // Captura Hoja 2 (si aplica)
        if (separarPrevias) {
            doc.addPage();
            const canvas2 = await html2canvas(domContainer, getOpcionesHtml2Canvas(true));
            agregarCanvasAlPDF(canvas2);
        }
        
        // 3. Extraer los datos Base64 LIMPIOS 
        // Esta es la clave por la que el correo enviaba archivos corruptos.
        // jsPDF adjunta encabezados (data:application/pdf;base64,JV...) que php no entiende bien
        const pdfOutput = doc.output('datauristring');
        const base64Data = pdfOutput.split('base64,')[1];
        
        if (!base64Data || base64Data.length < 1000) {
            throw new Error('El PDF generado está vacío o corrupto');
        }

        // Limpiar DOM temporal
        document.body.removeChild(contenedor);
        
        return base64Data;
        
    } catch(error) {
        if (document.body.contains(contenedor)) {
            document.body.removeChild(contenedor);
        }
        console.error('Error generando PDF:', error);
        throw error;
    }
}
        
        async function cargarHTMLAlumno(alumnoId, anio, tipoEnvio) {
            return new Promise((resolve, reject) => {
                $.ajax({
                    url: 'obtener_html_libreta.php',
                    type: 'POST',
                    data: {
                        alumno_id: alumnoId,
                        anio: anio,
                        tipo_envio: tipoEnvio
                    },
                    dataType: 'text',
                    success: function(html) {
                        resolve(html);
                    },
                    error: function(xhr, status, error) {
                        reject(error);
                    }
                });
            });
        }
        
        async function enviarPDFAlumno(alumnoId, pdfBase64, tipoEnvioNombre, alumnoNombre) {
            return new Promise((resolve, reject) => {
                $.ajax({
                    url: 'procesar_envio_masivo.php',
                    type: 'POST',
                    data: {
                        action: 'enviar_email',
                        alumno_id: alumnoId,
                        pdf_base64: pdfBase64,
                        tipo_envio_nombre: tipoEnvioNombre
                    },
                    dataType: 'json',
                    success: function(response) {
                        if(response.success) {
                            resolve(response);
                        } else {
                            reject(response.error || 'Error desconocido');
                        }
                    },
                    error: function(xhr, status, error) {
                        reject(error);
                    }
                });
            });
        }
        
        async function enviarCurso(cursoId) {
            let anio = $('#anio').val();
            let tipoEnvio = $('#tipo_envio').val();
            let nombreTrimestre = '';
            if(tipoEnvio == 'trimestre1') nombreTrimestre = '1er Trimestre';
            else if(tipoEnvio == 'trimestre2') nombreTrimestre = '2do Trimestre';
            else if(tipoEnvio == 'trimestre3') nombreTrimestre = '3er Trimestre';
            else nombreTrimestre = 'Año Completo';
            
            let cursoNombre = '';
            for(let curso of cursosData) {
                if(curso.id_curso == cursoId) {
                    cursoNombre = curso.curso + '° "' + curso.division + '"';
                    break;
                }
            }
            
            if(!confirm(`⚠️ ¿Estás seguro de enviar las libretas de ${nombreTrimestre} a los tutores del curso ${cursoNombre}?\n\nEsto puede tomar varios minutos.`)) {
                return;
            }
            
            $('#resultado').hide();
            $('#progreso').show();
            $('.progress-bar').css('width', '0%').text('Iniciando...');
            
            try {
                const alumnosResponse = await $.ajax({
                    url: 'procesar_envio_masivo.php',
                    type: 'POST',
                    data: {
                        action: 'obtener_alumnos_curso',
                        curso_id: cursoId
                    },
                    dataType: 'json'
                });
                
                if(!alumnosResponse.success) {
                    throw new Error(alumnosResponse.error || 'No se pudieron obtener los alumnos');
                }
                
                const alumnos = alumnosResponse.alumnos;
                
                if(alumnos.length === 0) {
                    throw new Error('No hay alumnos activos en este curso');
                }
                
                let enviados = 0;
                let fallidos = 0;
                const errores = [];
                
                for(let i = 0; i < alumnos.length; i++) {
                    const alumno = alumnos[i];
                    const progreso = Math.round(((i + 1) / alumnos.length) * 100);
                    $('.progress-bar').css('width', progreso + '%').text(progreso + '% - ' + alumno.Apellido + ', ' + alumno.Nombre);
                    
                    try {
                        const htmlContent = await cargarHTMLAlumno(alumno.id, anio, tipoEnvio);
                        const pdfBase64 = await generarPDFDesdeHTML(htmlContent, alumno.id, alumno.Apellido + ', ' + alumno.Nombre);
                        const resultado = await enviarPDFAlumno(alumno.id, pdfBase64, nombreTrimestre, alumno.Apellido + ', ' + alumno.Nombre);
                        enviados += resultado.enviados;
                        fallidos += resultado.fallidos;
                        if(resultado.errores && resultado.errores.length > 0) {
                            errores.push(...resultado.errores);
                        }
                        
                        await new Promise(resolve => setTimeout(resolve, 500));
                        
                    } catch(error) {
                        fallidos++;
                        errores.push(`${alumno.Apellido}, ${alumno.Nombre}: ${error.message || error}`);
                        console.error('Error con alumno', alumno.Apellido, error);
                    }
                }
                
                $('#progreso').hide();
                let mensajeHtml = `<div class="resultado-exito">
                    ✅ Envío completado para el curso ${cursoNombre}<br>
                    📊 Enviados: ${enviados} | Fallidos: ${fallidos}
                </div>`;
                if(errores.length > 0) {
                    mensajeHtml += `<div class="resultado-error mt-2">
                        <strong>⚠️ Errores:</strong><br>
                        ${errores.slice(0, 10).join('<br>')}
                        ${errores.length > 10 ? `<br>... y ${errores.length - 10} más` : ''}
                    </div>`;
                }
                $('#resultado').html(mensajeHtml).show();
                verificarCursos();
                
            } catch(error) {
                $('#progreso').hide();
                $('#resultado').html(`<div class="resultado-error">❌ Error: ${error.message || error}</div>`).show();
                console.error('Error general:', error);
            }
        }
        
        async function enviarMasivo() {
            let anio = $('#anio').val();
            let tipoEnvio = $('#tipo_envio').val();
            let nombreTrimestre = '';
            if(tipoEnvio == 'trimestre1') nombreTrimestre = '1er Trimestre';
            else if(tipoEnvio == 'trimestre2') nombreTrimestre = '2do Trimestre';
            else if(tipoEnvio == 'trimestre3') nombreTrimestre = '3er Trimestre';
            else nombreTrimestre = 'Año Completo';
            
            if(!confirm(`⚠️ ¿Estás seguro de enviar las libretas de ${nombreTrimestre} a TODOS los tutores de los cursos COMPLETOS?\n\nEsto puede tomar varios minutos.`)) {
                return;
            }
            
            let cursosCompletos = cursosData.filter(curso => curso.completo);
            if(cursosCompletos.length === 0) {
                alert('No hay cursos completos para enviar');
                return;
            }
            
            $('#btnEnviar').prop('disabled', true).text('⏳ Enviando...');
            $('#resultado').hide();
            $('#progreso').show();
            
            let totalEnviados = 0;
            let totalFallidos = 0;
            let todosErrores = [];
            
            for(let c = 0; c < cursosCompletos.length; c++) {
                const curso = cursosCompletos[c];
                $('.progress-bar').css('width', Math.round((c / cursosCompletos.length) * 100) + '%')
                    .text(`Curso ${c+1}/${cursosCompletos.length}: ${curso.curso}° "${curso.division}"`);
                
                try {
                    const alumnosResponse = await $.ajax({
                        url: 'procesar_envio_masivo.php',
                        type: 'POST',
                        data: {
                            action: 'obtener_alumnos_curso',
                            curso_id: curso.id_curso
                        },
                        dataType: 'json'
                    });
                    
                    if(!alumnosResponse.success || !alumnosResponse.alumnos.length) continue;
                    
                    const alumnos = alumnosResponse.alumnos;
                    
                    for(let i = 0; i < alumnos.length; i++) {
                        const alumno = alumnos[i];
                        const progresoCurso = Math.round(((i + 1) / alumnos.length) * 100);
                        $('.progress-bar').css('width', Math.round(((c + (i+1)/alumnos.length) / cursosCompletos.length) * 100) + '%')
                            .text(`Curso ${curso.curso}° "${curso.division}" - ${progresoCurso}% - ${alumno.Apellido}, ${alumno.Nombre}`);
                        
                        try {
                            const htmlContent = await cargarHTMLAlumno(alumno.id, anio, tipoEnvio);
                            const pdfBase64 = await generarPDFDesdeHTML(htmlContent, alumno.id, alumno.Apellido + ', ' + alumno.Nombre);
                            const resultado = await enviarPDFAlumno(alumno.id, pdfBase64, nombreTrimestre, alumno.Apellido + ', ' + alumno.Nombre);
                            totalEnviados += resultado.enviados;
                            totalFallidos += resultado.fallidos;
                            if(resultado.errores) todosErrores.push(...resultado.errores);
                        } catch(error) {
                            totalFallidos++;
                            todosErrores.push(`${curso.curso}° "${curso.division}" - ${alumno.Apellido}, ${alumno.Nombre}: ${error}`);
                        }
                    }
                } catch(error) {
                    totalFallidos++;
                    todosErrores.push(`Curso ${curso.curso}° "${curso.division}": ${error}`);
                }
            }
            
            $('#btnEnviar').prop('disabled', false).text('📧 Enviar a Cursos Completos');
            $('#progreso').hide();
            
            let mensajeHtml = `<div class="resultado-exito">
                ✅ Envío masivo completado<br>
                📊 Enviados: ${totalEnviados} | Fallidos: ${totalFallidos}
            </div>`;
            if(todosErrores.length > 0) {
                mensajeHtml += `<div class="resultado-error mt-2">
                    <strong>⚠️ Errores:</strong><br>
                    ${todosErrores.slice(0, 10).join('<br>')}
                    ${todosErrores.length > 10 ? `<br>... y ${todosErrores.length - 10} más` : ''}
                </div>`;
            }
            $('#resultado').html(mensajeHtml).show();
            verificarCursos();
        }
        
        $(document).ready(function() {
            verificarCursos();
        });
    </script>
</body>
</html>
<?php mysqli_close($con); ?>