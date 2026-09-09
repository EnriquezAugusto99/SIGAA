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

$es_admin = ($_SESSION['rol'] == 'Admin');
$es_preceptor = ($_SESSION['rol'] == 'Preceptor');
$es_secretario = ($_SESSION['rol'] == 'Secretario');
$es_equipo = ($_SESSION['rol'] == 'Equipo de Orientacion');

if(!$es_admin && !$es_preceptor && !$es_secretario && !$es_equipo){
    echo '<script>alert("No tiene permisos para acceder a esta seccion"); window.location="../../recursos/panel.php";</script>';
    exit();
}

include '../../recursos/conexion.php';

$query_cursos = "SELECT ID_curso, curso, division, turno FROM curso ORDER BY curso, division";
$res_cursos = mysqli_query($con, $query_cursos);
$cursos = [];
while($row = mysqli_fetch_assoc($res_cursos)){
    $cursos[] = $row;
}

$query_trimestres = "SELECT id_trimestre, trimestre FROM trimestres WHERE trimestre NOT LIKE '%Diciembre%' AND trimestre NOT LIKE '%Febrero%' AND trimestre NOT LIKE '%Marzo%' ORDER BY id_trimestre";
$res_trimestres = mysqli_query($con, $query_trimestres);
$trimestres = [];
while($row = mysqli_fetch_assoc($res_trimestres)){
    $trimestres[] = $row;
}

// ============================================
// ESTADISTICAS GENERALES
// ============================================
// Total de alumnos (ID_rol = 3)
$query_alumnos = "SELECT COUNT(*) as total FROM usuario WHERE ID_rol = 3 AND ID_Estado = 1";
$res_alumnos = mysqli_query($con, $query_alumnos);
$total_alumnos = mysqli_fetch_assoc($res_alumnos)['total'] ?? 0;

// Total de cursos
$query_cursos_total = "SELECT COUNT(*) as total FROM curso";
$res_cursos_total = mysqli_query($con, $query_cursos_total);
$total_cursos = mysqli_fetch_assoc($res_cursos_total)['total'] ?? 0;

// Total de egresados (ID_Estado = 5)
$query_egresados = "SELECT COUNT(*) as total FROM usuario WHERE ID_rol = 3 AND ID_Estado = 5";
$res_egresados = mysqli_query($con, $query_egresados);
$total_egresados = mysqli_fetch_assoc($res_egresados)['total'] ?? 0;
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Estadisticas - EPET N°34</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Montserrat', sans-serif; background: #f5f5f5; padding: 20px; }
        .container { max-width: 1500px; margin: 0 auto; }
        
        .header { 
            background: linear-gradient(135deg, #3F070B 0%, #710A14 100%); 
            border-radius: 12px; 
            padding: 25px 35px; 
            margin-bottom: 30px; 
            color: white; 
        }
        .header h1 { font-size: 28px; font-weight: 600; }
        .header p { opacity: 0.9; font-size: 14px; margin-top: 5px; }
        
        /* SECCION DE ESTADISTICAS GENERALES */
        .stats-generales {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 25px;
            margin-bottom: 30px;
        }
        .stat-card-general {
            background: white;
            border-radius: 12px;
            padding: 20px 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            text-align: center;
            transition: transform 0.3s ease;
        }
        .stat-card-general:hover {
            transform: translateY(-3px);
        }
        .stat-card-general .stat-label {
            font-size: 14px;
            color: #666;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .stat-card-general .stat-number {
            font-size: 36px;
            font-weight: 700;
            color: #710A14;
            margin-top: 5px;
        }
        .stat-card-general .stat-number.verde { color: #2e7d32; }
        .stat-card-general .stat-number.azul { color: #1565C0; }
        .stat-card-general .stat-number.naranja { color: #e65100; }
        
        .grid-3col {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 25px;
            margin-bottom: 30px;
        }
        
        .seccion-grafico {
            background: white;
            border-radius: 12px;
            padding: 18px 20px 20px 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .seccion-grafico:hover {
            transform: translateY(-4px);
            box-shadow: 0 6px 25px rgba(0,0,0,0.12);
        }
        .seccion-grafico .titulo-seccion {
            color: #3F070B;
            font-size: 15px;
            font-weight: 600;
            margin-bottom: 12px;
            text-align: center;
            border-bottom: 2px solid #e0e0e0;
            padding-bottom: 8px;
        }
        
        .grafico-wrapper-mini {
            position: relative;
            height: 170px;
            min-height: 150px;
        }
        .grafico-wrapper-mini canvas {
            width: 100% !important;
            height: 100% !important;
        }
        
        .click-para-ampliar {
            text-align: center;
            font-size: 11px;
            color: #999;
            margin-top: 8px;
            border-top: 1px solid #f0f0f0;
            padding-top: 8px;
        }
        
        .loading-mini {
            display: inline-block;
            width: 24px;
            height: 24px;
            border: 3px solid #f3f3f3;
            border-top: 3px solid #710A14;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 10px auto;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .btn-volver {
            display: inline-block;
            padding: 12px 30px;
            background: #666;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 15px;
            transition: all 0.3s ease;
        }
        .btn-volver:hover {
            background: #555;
        }
        .text-center {
            text-align: center;
            margin-top: 25px;
        }
        
        /* MODAL */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.6);
            z-index: 2000;
            justify-content: center;
            align-items: center;
        }
        .modal-overlay.activo {
            display: flex;
        }
        .modal-content {
            background: white;
            border-radius: 16px;
            padding: 30px 35px;
            max-width: 1100px;
            width: 92%;
            max-height: 88vh;
            display: flex;
            flex-direction: column;
            box-shadow: 0 10px 40px rgba(0,0,0,0.3);
            animation: modalFadeIn 0.3s ease;
        }
        @keyframes modalFadeIn {
            from { opacity: 0; transform: translateY(-30px) scale(0.97); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }
        .modal-content .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 2px solid #f0f0f0;
            padding-bottom: 12px;
            margin-bottom: 18px;
        }
        .modal-content .modal-header h2 {
            font-size: 20px;
            color: #3F070B;
        }
        .modal-content .modal-header .btn-cerrar-modal {
            background: none;
            border: none;
            font-size: 30px;
            cursor: pointer;
            color: #999;
            transition: color 0.3s;
            line-height: 1;
            padding: 0 10px;
        }
        .modal-content .modal-header .btn-cerrar-modal:hover {
            color: #333;
        }
        .modal-content .modal-body {
            overflow-y: auto;
            flex: 1;
            padding-right: 5px;
        }
        .modal-content .modal-body::-webkit-scrollbar {
            width: 6px;
        }
        .modal-content .modal-body::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 10px;
        }
        .modal-content .modal-body::-webkit-scrollbar-thumb {
            background: #710A14;
            border-radius: 10px;
        }
        
        .filtros-modal {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 18px;
            align-items: center;
            background: #f8f8f8;
            padding: 12px 16px;
            border-radius: 8px;
        }
        .filtros-modal label {
            font-size: 13px;
            font-weight: 500;
            color: #555;
        }
        .filtros-modal select {
            padding: 6px 10px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 13px;
            font-family: 'Montserrat', sans-serif;
            background: white;
            min-width: 120px;
        }
        .filtros-modal select:focus {
            outline: none;
            border-color: #710A14;
        }
        .filtros-modal select:disabled {
            background: #f5f5f5;
            cursor: not-allowed;
        }
        
        .btn-filtrar-modal {
            background: #710A14;
            color: white;
            border: none;
            padding: 6px 16px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 13px;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        .btn-filtrar-modal:hover {
            background: #3F070B;
        }
        .btn-limpiar-modal {
            background: #666;
            color: white;
            border: none;
            padding: 6px 16px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 13px;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        .btn-limpiar-modal:hover {
            background: #555;
        }
        
        .grafico-container-modal {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 25px;
            align-items: start;
        }
        .grafico-wrapper-modal {
            position: relative;
            height: 300px;
            min-height: 250px;
        }
        .grafico-wrapper-modal canvas {
            width: 100% !important;
            height: 100% !important;
        }
        
        .tabla-mini-modal {
            width: 100%;
            font-size: 13px;
            border-collapse: collapse;
        }
        .tabla-mini-modal th, .tabla-mini-modal td {
            padding: 6px 10px;
            border: 1px solid #eee;
            text-align: center;
        }
        .tabla-mini-modal th {
            background: #f0f0f0;
            font-weight: 600;
            color: #333;
        }
        .tabla-mini-modal tr:hover {
            background: #fafafa;
        }
        
        .scroll-datos-modal {
            max-height: 280px;
            overflow-y: auto;
        }
        .scroll-datos-modal::-webkit-scrollbar {
            width: 5px;
        }
        .scroll-datos-modal::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 10px;
        }
        .scroll-datos-modal::-webkit-scrollbar-thumb {
            background: #710A14;
            border-radius: 10px;
        }
        
        .btn-cerrar-modal-footer {
            background: #666;
            color: white;
            border: none;
            padding: 8px 24px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 500;
            font-size: 14px;
            transition: background 0.3s;
            margin-top: 15px;
        }
        .btn-cerrar-modal-footer:hover {
            background: #555;
        }
        .text-right {
            text-align: right;
        }
        
        .sin-datos {
            text-align: center;
            padding: 30px;
            color: #999;
            font-size: 14px;
        }
        
        .definicion-riesgo {
            margin-top: 15px; 
            padding: 12px 16px; 
            background: #f8f8f8; 
            border-radius: 8px; 
            border-left: 4px solid #710A14; 
            font-size: 13px; 
            color: #555;
        }
        .definicion-riesgo strong {
            color: #710A14;
        }
        
        .definicion-rendimiento {
            margin-bottom: 12px; 
            padding: 10px 14px; 
            background: #e8f0fe; 
            border-radius: 8px; 
            border-left: 4px solid #2196F3; 
            font-size: 13px; 
            color: #333;
        }
        .definicion-rendimiento strong {
            color: #1565C0;
        }

.btn-ver-alumnos, .btn-ver-materias {
    background: none;
    border: none;
    cursor: pointer;
    font-size: 18px;
    padding: 4px 8px;
    border-radius: 4px;
    transition: background 0.2s;
}
.btn-ver-alumnos:hover, .btn-ver-materias:hover {
    background: #f0f0f0;
}
        
        @media (max-width: 1200px) {
            .grid-3col {
                grid-template-columns: 1fr 1fr;
            }
        }
        @media (max-width: 768px) {
            .grid-3col {
                grid-template-columns: 1fr;
            }
            .grafico-container-modal {
                grid-template-columns: 1fr;
            }
            .filtros-modal {
                flex-direction: column;
                align-items: stretch;
            }
            .filtros-modal select {
                min-width: 100%;
            }
            .header h1 { font-size: 20px; }
            .modal-content {
                padding: 20px;
                max-height: 92vh;
            }
            .grafico-wrapper-modal {
                height: 200px;
            }
            .stats-generales {
                grid-template-columns: 1fr;
                gap: 15px;
            }
        }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1>Panel de Estadisticas</h1>
        <p>Analisis de rendimiento academico por curso, materia y trimestre</p>
    </div>

    <!-- ESTADISTICAS GENERALES -->
    <div class="stats-generales">
        <div class="stat-card-general">
            <div class="stat-label">Alumnos Activos</div>
            <div class="stat-number"><?= number_format($total_alumnos) ?></div>
        </div>
        <div class="stat-card-general">
            <div class="stat-label">Cursos</div>
            <div class="stat-number azul"><?= number_format($total_cursos) ?></div>
        </div>
        <div class="stat-card-general">
            <div class="stat-label">Egresados</div>
            <div class="stat-number naranja"><?= number_format($total_egresados) ?></div>
        </div>
    </div>

    <!-- GRILLA DE GRAFICOS (3 COLUMNAS) -->
    <div class="grid-3col" id="gridGraficos">
        <!-- 1. APROBADOS VS DESAPROBADOS -->
        <div class="seccion-grafico" onclick="abrirModal('grafico1')">
            <div class="titulo-seccion">
                Aprobados vs Desaprobados
            </div>
            <div class="grafico-wrapper-mini">
                <canvas id="mini_grafico1"></canvas>
            </div>
            <div class="click-para-ampliar">Haga clic para ampliar</div>
        </div>

        <!-- 2. PROMEDIO GENERAL POR CURSO -->
        <div class="seccion-grafico" onclick="abrirModal('grafico2')">
            <div class="titulo-seccion">
                Promedio General por Curso
            </div>
            <div class="grafico-wrapper-mini">
                <canvas id="mini_grafico2"></canvas>
            </div>
            <div class="click-para-ampliar">Haga clic para ampliar</div>
        </div>

        <!-- 3. PROMEDIO POR MATERIA -->
        <div class="seccion-grafico" onclick="abrirModal('grafico3')">
            <div class="titulo-seccion">
                Promedio por Materia
            </div>
            <div class="grafico-wrapper-mini">
                <canvas id="mini_grafico3"></canvas>
            </div>
            <div class="click-para-ampliar">Haga clic para ampliar</div>
        </div>

        <!-- 4. EVOLUCION DE DESEMPEÑO -->
        <div class="seccion-grafico" onclick="abrirModal('grafico4')">
            <div class="titulo-seccion">
                Evolucion de Desempeño
            </div>
            <div class="grafico-wrapper-mini">
                <canvas id="mini_grafico4"></canvas>
            </div>
            <div class="click-para-ampliar">Haga clic para ampliar</div>
        </div>

        <!-- 5. ALUMNOS CON PROMEDIO INFERIOR A VALOR -->
        <div class="seccion-grafico" onclick="abrirModal('grafico5')">
            <div class="titulo-seccion">
                Alumnos con Promedio Bajo
            </div>
            <div class="grafico-wrapper-mini">
                <canvas id="mini_grafico5"></canvas>
            </div>
            <div class="click-para-ampliar">Haga clic para ampliar</div>
        </div>

        <!-- 6. RANKING DE CURSOS - ALUMNOS EN RIESGO -->
        <div class="seccion-grafico" onclick="abrirModal('grafico6')">
            <div class="titulo-seccion">
                Ranking - Alumnos en Riesgo
            </div>
            <div class="grafico-wrapper-mini">
                <canvas id="mini_grafico6"></canvas>
            </div>
            <div class="click-para-ampliar">Haga clic para ampliar</div>
        </div>

        <!-- 7. BAJA ASISTENCIA Y BAJO RENDIMIENTO -->
        <div class="seccion-grafico" onclick="abrirModal('grafico7')">
            <div class="titulo-seccion">
                Baja Asistencia y Rendimiento
            </div>
            <div class="grafico-wrapper-mini">
                <canvas id="mini_grafico7"></canvas>
            </div>
            <div class="click-para-ampliar">Haga clic para ampliar</div>
        </div>

        <!-- 8. ALUMNOS CON PREVIAS/EQUIVALENCIAS PENDIENTES -->
        <div class="seccion-grafico" onclick="abrirModal('grafico8')">
            <div class="titulo-seccion">
                Alumnos con Previas/Equivalencias
            </div>
            <div class="grafico-wrapper-mini">
                <canvas id="mini_grafico8"></canvas>
            </div>
            <div class="click-para-ampliar">Haga clic para ampliar</div>
        </div>

        <!-- 9. TOP 10 MATERIAS CON MAS DESAPROBADOS -->
        <div class="seccion-grafico" onclick="abrirModal('grafico9')">
            <div class="titulo-seccion">
                Top 10 Materias con mas Desaprobados
            </div>
            <div class="grafico-wrapper-mini">
                <canvas id="mini_grafico9"></canvas>
            </div>
            <div class="click-para-ampliar">Haga clic para ampliar</div>
        </div>

        <!-- 10. PORCENTAJE DE DESGRANAMIENTO ESCOLAR -->
        <div class="seccion-grafico" onclick="abrirModal('grafico10')">
            <div class="titulo-seccion">
                Desgranamiento Escolar
            </div>
            <div class="grafico-wrapper-mini">
                <canvas id="mini_grafico10"></canvas>
            </div>
            <div class="click-para-ampliar">Haga clic para ampliar</div>
        </div>
    </div>

    <div class="text-center">
        <a href="../../recursos/panel.php" class="btn-volver">Volver al Panel</a>
    </div>
</div>

<!-- MODAL UNICO -->
<div id="modalUnico" class="modal-overlay">
    <div class="modal-content">
        <div class="modal-header">
            <h2 id="modalTitulo">Estadistica</h2>
            <button class="btn-cerrar-modal" onclick="cerrarModal()">&times;</button>
        </div>
        <div class="modal-body">
            <div id="modalFiltros"></div>
            <div id="modalContenido">
                <div class="sin-datos">Cargando datos...</div>
            </div>
        </div>
        <div class="text-right">
            <button class="btn-cerrar-modal-footer" onclick="cerrarModal()">Cerrar</button>
        </div>
    </div>
</div>

<!-- MODAL PARA VER ALUMNOS EN RIESGO DE UN CURSO -->
<div id="modalAlumnosRiesgo" class="modal-overlay" style="z-index: 3000;">
    <div class="modal-content" style="max-width: 800px;">
        <div class="modal-header">
            <h2 id="modalRiesgoTitulo">Alumnos en Riesgo</h2>
            <button class="btn-cerrar-modal" onclick="cerrarModalAlumnosRiesgo()">&times;</button>
        </div>
        <div class="modal-body" id="modalRiesgoBody">
            <div style="text-align:center;padding:30px;">
                <div class="loading-mini"></div> Cargando datos...
            </div>
        </div>
        <div class="text-right">
            <button class="btn-cerrar-modal-footer" onclick="cerrarModalAlumnosRiesgo()">Cerrar</button>
        </div>
    </div>
</div>

<!-- MODAL PARA VER MATERIAS DE UN ALUMNO -->
<div id="modalMateriasAlumno" class="modal-overlay" style="z-index: 4000;">
    <div class="modal-content" style="max-width: 600px;">
        <div class="modal-header">
            <h2 id="modalMateriasTitulo">Materias con promedio bajo</h2>
            <button class="btn-cerrar-modal" onclick="cerrarModalMateriasAlumno()">&times;</button>
        </div>
        <div class="modal-body" id="modalMateriasBody">
            <div style="text-align:center;padding:30px;">
                <div class="loading-mini"></div> Cargando datos...
            </div>
        </div>
        <div class="text-right">
            <button class="btn-cerrar-modal-footer" onclick="cerrarModalMateriasAlumno()">Cerrar</button>
        </div>
    </div>
</div>

<!-- MODAL PARA VER ALUMNOS POR CATEGORIA -->
<div id="modalCategoriaAlumnos" class="modal-overlay" style="z-index: 3000;">
    <div class="modal-content" style="max-width: 800px;">
        <div class="modal-header">
            <h2 id="modalCategoriaTitulo">Alumnos - Categoria</h2>
            <button class="btn-cerrar-modal" onclick="cerrarModalCategoria()">&times;</button>
        </div>
        <div class="modal-body" id="modalCategoriaBody">
            <div style="text-align:center;padding:30px;">
                <div class="loading-mini"></div> Cargando datos...
            </div>
        </div>
        <div class="text-right">
            <button class="btn-cerrar-modal-footer" onclick="cerrarModalCategoria()">Cerrar</button>
        </div>
    </div>
</div>

<script>
// ============================================
// VARIABLES GLOBALES
// ============================================
let miniCharts = {};
let modalCharts = {};
let modalChartInstance = null;
let modalDatosDetalle = [];
let modalActual = '';

// ============================================
// ABRIR MODAL
// ============================================
function abrirModal(tipo) {
    modalActual = tipo;
    const modal = document.getElementById('modalUnico');
    const titulo = document.getElementById('modalTitulo');
    const filtrosDiv = document.getElementById('modalFiltros');
    const contenido = document.getElementById('modalContenido');
    
    const titulos = {
        'grafico1': 'Aprobados vs Desaprobados',
        'grafico2': 'Promedio General por Curso',
        'grafico3': 'Promedio por Materia',
        'grafico4': 'Evolucion de Desempeño',
        'grafico5': 'Alumnos con Promedio Inferior a Valor',
        'grafico6': 'Ranking de Cursos - Alumnos en Riesgo',
        'grafico7': 'Baja Asistencia y Bajo Rendimiento',
        'grafico8': 'Alumnos con Previas/Equivalencias Pendientes',
        'grafico9': 'Top 10 Materias con mas Desaprobados',
        'grafico10': 'Desgranamiento Escolar'
    };
    titulo.textContent = titulos[tipo] || 'Estadistica';
    
    filtrosDiv.innerHTML = generarFiltros(tipo);
    
    modal.classList.add('activo');
    contenido.innerHTML = '<div style="text-align:center;padding:30px;"><div class="loading-mini"></div> Cargando datos...</div>';
    
    if(tipo === 'grafico3') {
        setTimeout(function() {
            actualizarDivisiones();
            cargarModal(tipo);
        }, 150);
    } else {
        cargarModal(tipo);
    }
}

// ============================================
// GENERAR FILTROS
// ============================================
function generarFiltros(tipo) {
    const cursos = <?= json_encode($cursos) ?>;
    const trimestres = <?= json_encode($trimestres) ?>;
    
    let html = '<div class="filtros-modal">';
    
    const filtrosConTrimestre = ['grafico1', 'grafico2', 'grafico3', 'grafico6', 'grafico9'];
    const filtrosConAnio = ['grafico2', 'grafico3', 'grafico9', 'grafico10'];
    const filtrosConDivision = ['grafico3'];
    const filtrosConNota = ['grafico5'];
    const filtrosConFaltas = ['grafico7'];
    const filtrosConMateria = ['grafico1', 'grafico4', 'grafico5'];
    const filtrosConCurso = ['grafico1', 'grafico4', 'grafico5', 'grafico7'];
    const filtrosConPrevia = ['grafico8'];
    
    if(filtrosConCurso.includes(tipo)) {
        html += '<label>Curso:</label>';
        html += '<select id="modal_curso" onchange="cargarMateriasModal()">';
        html += '<option value="">Todos</option>';
        cursos.forEach(function(c) {
            var turno_texto = c.turno == 'M' ? 'Mañana' : 'Tarde';
            var nombre = c.curso + '° "' + c.division + '" - ' + turno_texto;
            html += '<option value="' + c.ID_curso + '">' + nombre + '</option>';
        });
        html += '</select>';
    }
    
    if(filtrosConMateria.includes(tipo)) {
        html += '<label>Materia:</label>';
        html += '<select id="modal_materia" disabled>';
        html += '<option value="">Todas</option>';
        html += '</select>';
    }
    
    if(filtrosConTrimestre.includes(tipo)) {
        html += '<label>Trimestre:</label>';
        html += '<select id="modal_trimestre">';
        html += '<option value="0">Anual</option>';
        trimestres.forEach(function(t) {
            html += '<option value="' + t.id_trimestre + '">' + t.trimestre + '</option>';
        });
        html += '</select>';
    }
    
    if(filtrosConAnio.includes(tipo)) {
        html += '<label>Año:</label>';
        html += '<select id="modal_anio">';
        html += '<option value="0">Todos</option>';
        for(var i=1; i<=6; i++) {
            html += '<option value="' + i + '">' + i + '°</option>';
        }
        html += '</select>';
    }
    
    if(filtrosConDivision.includes(tipo)) {
        html += '<label>Division:</label>';
        html += '<select id="modal_division">';
        html += '<option value="">Todas</option>';
        html += '</select>';
    }
    
    if(filtrosConNota.includes(tipo)) {
        html += '<label>Promedio menor a:</label>';
        html += '<select id="modal_nota">';
        html += '<option value="4">4</option>';
        html += '<option value="5">5</option>';
        html += '<option value="6" selected>6</option>';
        html += '<option value="7">7</option>';
        html += '</select>';
    }
    
    if(filtrosConFaltas.includes(tipo)) {
        html += '<label>Faltas injustificadas:</label>';
        html += '<select id="modal_faltas">';
        html += '<option value="15">15</option>';
        html += '<option value="20">20</option>';
        html += '<option value="25">25</option>';
        html += '<option value="30">30</option>';
        html += '<option value="31">+30</option>';
        html += '</select>';
    }

    if(filtrosConPrevia.includes(tipo)) {
        html += '<label>Año:</label>';
        html += '<select id="modal_anio_previa">';
        html += '<option value="0">Todos</option>';
        for(var i=2; i<=6; i++) {
            html += '<option value="' + i + '">' + i + '°</option>';
        }
        html += '</select>';
        
        html += '<label>Tipo:</label>';
        html += '<select id="modal_tipo_previa">';
        html += '<option value="">Todos</option>';
        html += '<option value="2">Solo Previas</option>';
        html += '<option value="1">Solo Equivalencias</option>';
        html += '</select>';
    }
    
    html += '<button class="btn-filtrar-modal" onclick="cargarModal(\'' + tipo + '\')">Actualizar</button>';
    html += '<button class="btn-limpiar-modal" onclick="limpiarFiltrosModal(\'' + tipo + '\')">Limpiar</button>';
    html += '</div>';
    
    return html;
}

// ============================================
// ACTUALIZAR DIVISIONES SEGUN AÑO
// ============================================
function actualizarDivisiones() {
    const anio = document.getElementById('modal_anio')?.value || '0';
    const divisionSelect = document.getElementById('modal_division');
    
    if(!divisionSelect) return;
    
    const valorActual = divisionSelect.value;
    const cursos = <?= json_encode($cursos) ?>;
    
    let divisiones = [];
    if(anio == '0') {
        var todas = ['A','B','C','D','E','F'];
        divisiones = todas.map(function(d) { return { division: d }; });
    } else {
        cursos.forEach(function(c) {
            if(c.curso == anio) {
                divisiones.push({ division: c.division });
            }
        });
    }
    
    var divisionesUnicas = [];
    var vistos = {};
    divisiones.forEach(function(d) {
        if(!vistos[d.division]) {
            vistos[d.division] = true;
            divisionesUnicas.push(d);
        }
    });
    
    divisionesUnicas.sort(function(a, b) {
        return a.division.localeCompare(b.division);
    });
    
    var html = '<option value="">Todas</option>';
    divisionesUnicas.forEach(function(d) {
        html += '<option value="' + d.division + '">' + d.division + '</option>';
    });
    divisionSelect.innerHTML = html;
    
    if(valorActual) {
        var existe = false;
        var options = divisionSelect.options;
        for(var i = 0; i < options.length; i++) {
            if(options[i].value == valorActual) {
                existe = true;
                break;
            }
        }
        if(existe) {
            divisionSelect.value = valorActual;
        } else {
            divisionSelect.value = '';
        }
    }
}

// ============================================
// CARGAR MATERIAS EN MODAL
// ============================================
function cargarMateriasModal() {
    const cursoId = document.getElementById('modal_curso')?.value || '';
    const materiaSelect = document.getElementById('modal_materia');
    
    if(!materiaSelect) return;
    
    materiaSelect.disabled = true;
    materiaSelect.innerHTML = '<option value="">Cargando...</option>';
    
    if(!cursoId) {
        materiaSelect.innerHTML = '<option value="">Todas</option>';
        materiaSelect.disabled = false;
        return;
    }
    
    $.ajax({
        url: 'ajax_estadisticas.php',
        type: 'POST',
        data: { 
            action: 'get_materias_por_curso',
            curso_id: cursoId
        },
        dataType: 'json',
        success: function(response) {
            if(response.success && response.materias.length > 0) {
                let html = '<option value="">Todas</option>';
                response.materias.forEach(function(m) {
                    html += '<option value="' + m.ID_materia + '">' + m.Nom_materia + '</option>';
                });
                materiaSelect.innerHTML = html;
                materiaSelect.disabled = false;
            } else {
                materiaSelect.innerHTML = '<option value="">No hay materias</option>';
                materiaSelect.disabled = true;
            }
        },
        error: function() {
            materiaSelect.innerHTML = '<option value="">Error al cargar</option>';
            materiaSelect.disabled = true;
        }
    });
}

// ============================================
// LIMPIAR FILTROS MODAL
// ============================================
function limpiarFiltrosModal(tipo) {
    const selects = document.querySelectorAll('#modalFiltros select');
    selects.forEach(function(select) {
        if(select.id.indexOf('trimestre') !== -1 || select.id.indexOf('anio') !== -1 || select.id.indexOf('anio_previa') !== -1) {
            select.value = '0';
        } else if(select.id.indexOf('materia') !== -1) {
            select.value = '';
            select.innerHTML = '<option value="">Todas</option>';
            select.disabled = false;
        } else if(select.id.indexOf('nota') !== -1) {
            select.value = '6';
        } else if(select.id.indexOf('faltas') !== -1) {
            select.value = '15';
        } else if(select.id.indexOf('division') !== -1) {
            // Se actualiza con actualizarDivisiones
        } else if(select.id.indexOf('tipo_previa') !== -1) {
            select.value = '';
        } else {
            select.value = '';
        }
    });
    
    if(tipo === 'grafico3') {
        actualizarDivisiones();
    }
    
    cargarModal(tipo);
}

// ============================================
// CARGAR MODAL
// ============================================
function cargarModal(tipo) {
    const contenido = document.getElementById('modalContenido');
    contenido.innerHTML = '<div style="text-align:center;padding:30px;"><div class="loading-mini"></div> Cargando datos...</div>';
    
    if(tipo === 'grafico3') {
        actualizarDivisiones();
    }
    
    const curso = document.getElementById('modal_curso')?.value || '';
    const materia = document.getElementById('modal_materia')?.value || '';
    const trimestre = document.getElementById('modal_trimestre')?.value || '0';
    const anio = document.getElementById('modal_anio')?.value || '0';
    const division = document.getElementById('modal_division')?.value || '';
    const nota = document.getElementById('modal_nota')?.value || '6';
    const faltas = document.getElementById('modal_faltas')?.value || '15';
    
    switch(tipo) {
        case 'grafico1':
            cargarModal1(curso, materia, trimestre);
            break;
        case 'grafico2':
            cargarModal2(anio, trimestre);
            break;
        case 'grafico3':
            cargarModal3(anio, division, trimestre);
            break;
        case 'grafico4':
            cargarModal4(curso, materia);
            break;
        case 'grafico5':
            cargarModal5(curso, materia, nota);
            break;
        case 'grafico6':
            cargarModal6(trimestre);
            break;
        case 'grafico7':
            cargarModal7(curso, faltas);
            break;
        case 'grafico8':
            cargarModal8();
            break;
        case 'grafico9':
            cargarModal9();
            break;
        case 'grafico10':
            cargarModal10();
            break;
        default:
            contenido.innerHTML = '<div class="sin-datos">Tipo de grafico no valido</div>';
    }
}

// ============================================
// 1. APROBADOS VS DESAPROBADOS (MODAL)
// ============================================
function cargarModal1(curso, materia, trimestre) {
    const contenido = document.getElementById('modalContenido');
    
    $.ajax({
        url: 'ajax_estadisticas.php',
        type: 'POST',
        data: { 
            action: 'aprobados_desaprobados',
            curso: curso,
            materia: materia,
            trimestre: trimestre
        },
        dataType: 'json',
        success: function(data) {
            if(modalCharts['grafico1']) {
                modalCharts['grafico1'].destroy();
                delete modalCharts['grafico1'];
            }
            
            const labels = ['Aprobados', 'Desaprobados'];
            const valores = [data.aprobados || 0, data.desaprobados || 0];
            const colores = ['#2e7d32', '#d32f2f'];
            
            const canvas = document.createElement('canvas');
            canvas.id = 'modal_canvas_1';
            
            let html = '<div class="grafico-container-modal">';
            html += '<div class="grafico-wrapper-modal">';
            html += canvas.outerHTML;
            html += '</div>';
            html += '<div>';
            
            const total = data.aprobados + data.desaprobados;
            const pAprob = total > 0 ? Math.round((data.aprobados / total) * 100) : 0;
            const pDesap = total > 0 ? Math.round((data.desaprobados / total) * 100) : 0;
            
            html += '<table class="tabla-mini-modal"><tr><th>Estado</th><th>Cantidad</th><th>%</th></tr>';
            html += '<tr><td style="color:#2e7d32;">Aprobados</td><td>' + data.aprobados + '</td><td>' + pAprob + '%</td></tr>';
            html += '<tr><td style="color:#d32f2f;">Desaprobados</td><td>' + data.desaprobados + '</td><td>' + pDesap + '%</td></tr>';
            html += '<tr><td><strong>Total</strong></td><td><strong>' + total + '</strong></td><td><strong>100%</strong></td></tr></table>';
            
            if(data.aprobados === 0 && data.desaprobados === 0) {
                html = '<div class="sin-datos">No hay datos para los filtros seleccionados</div>';
            }
            
            html += '</div></div>';
            contenido.innerHTML = html;
            
            const ctx = document.getElementById('modal_canvas_1').getContext('2d');
            modalCharts['grafico1'] = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: labels,
                    datasets: [{ data: valores, backgroundColor: colores, borderWidth: 2, borderColor: '#fff' }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { position: 'bottom', labels: { font: { size: 13 } } } }
                }
            });
        },
        error: function() {
            contenido.innerHTML = '<div class="sin-datos">Error al cargar los datos</div>';
        }
    });
}

// ============================================
// 2. PROMEDIO GENERAL POR CURSO (MODAL)
// ============================================
function cargarModal2(anio, trimestre) {
    const contenido = document.getElementById('modalContenido');
    
    $.ajax({
        url: 'ajax_estadisticas.php',
        type: 'POST',
        data: { 
            action: 'promedio_general_curso',
            anio: anio,
            trimestre: trimestre
        },
        dataType: 'json',
        success: function(data) {
            if(modalCharts['grafico2']) {
                modalCharts['grafico2'].destroy();
                delete modalCharts['grafico2'];
            }
            
            const labels = data.cursos || [];
            const valores = data.promedios || [];
            
            if(labels.length === 0) {
                contenido.innerHTML = '<div class="sin-datos">No hay datos para los filtros seleccionados</div>';
                return;
            }
            
            const colores = valores.map(function(v) { return v >= 6 ? '#2e7d32' : '#d32f2f'; });
            
            const canvas = document.createElement('canvas');
            canvas.id = 'modal_canvas_2';
            
            let html = '<div class="grafico-container-modal">';
            html += '<div class="grafico-wrapper-modal">';
            html += canvas.outerHTML;
            html += '</div>';
            html += '<div>';
            
            html += '<div class="scroll-datos-modal"><table class="tabla-mini-modal"><tr><th>Curso</th><th>Promedio</th><th>Estado</th></tr>';
            var totalProm = 0;
            var cont = 0;
            data.cursos.forEach(function(curso, i) {
                var prom = data.promedios[i] || 0;
                totalProm += prom;
                cont++;
                var estado = prom >= 6 ? 'Aprobado' : 'Desaprobado';
                var color = prom >= 6 ? '#2e7d32' : '#d32f2f';
                html += '<tr><td>' + curso + '</td><td style="color:' + color + ';font-weight:bold;">' + prom.toFixed(2) + '</td><td>' + estado + '</td></tr>';
            });
            var promedioGeneral = cont > 0 ? (totalProm / cont) : 0;
            html += '<tr style="font-weight:bold;"><td>PROMEDIO GENERAL</td><td style="color:' + (promedioGeneral >= 6 ? '#2e7d32' : '#d32f2f') + ';">' + promedioGeneral.toFixed(2) + '</td><td>' + (promedioGeneral >= 6 ? 'Aprobado' : 'Desaprobado') + '</td></tr></table></div>';
            
            html += '</div></div>';
            contenido.innerHTML = html;
            
            const ctx = document.getElementById('modal_canvas_2').getContext('2d');
            modalCharts['grafico2'] = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [{ label: 'Promedio General', data: valores, backgroundColor: colores, borderRadius: 4 }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: {
                        y: { min: 0, max: 10, ticks: { font: { size: 11 } } },
                        x: { ticks: { font: { size: 10 } } }
                    }
                }
            });
        },
        error: function() {
            contenido.innerHTML = '<div class="sin-datos">Error al cargar los datos</div>';
        }
    });
}

// ============================================
// 3. PROMEDIO POR MATERIA (MODAL)
// ============================================
function cargarModal3(anio, division, trimestre) {
    const contenido = document.getElementById('modalContenido');
    
    $.ajax({
        url: 'ajax_estadisticas.php',
        type: 'POST',
        data: { 
            action: 'promedio_por_materia',
            anio: anio,
            division: division,
            trimestre: trimestre
        },
        dataType: 'json',
        success: function(data) {
            if(modalCharts['grafico3']) {
                modalCharts['grafico3'].destroy();
                delete modalCharts['grafico3'];
            }
            
            const labels = data.materias || [];
            const valores = data.promedios || [];
            
            if(labels.length === 0) {
                contenido.innerHTML = '<div class="sin-datos">No hay datos para los filtros seleccionados</div>';
                return;
            }
            
            const colores = valores.map(function(v) { return v >= 6 ? '#2e7d32' : '#d32f2f'; });
            
            const canvas = document.createElement('canvas');
            canvas.id = 'modal_canvas_3';
            
            let html = '<div class="grafico-container-modal">';
            html += '<div class="grafico-wrapper-modal">';
            html += canvas.outerHTML;
            html += '</div>';
            html += '<div>';
            
            var detalles = data.detalles || [];
            html += '<div class="scroll-datos-modal"><table class="tabla-mini-modal"><tr><th>Materia</th><th>Promedio</th><th>Estado</th></tr>';
            var totalProm = 0;
            var cont = 0;
            detalles.forEach(function(item) {
                var color = item.promedio >= 6 ? '#2e7d32' : '#d32f2f';
                var estado = item.promedio >= 6 ? 'Aprobado' : 'Desaprobado';
                html += '<tr><td>' + item.nombre + '</td><td style="color:' + color + ';font-weight:bold;">' + item.promedio.toFixed(2) + '</td><td>' + estado + '</td></tr>';
                totalProm += item.promedio;
                cont++;
            });
            var promedioGeneral = cont > 0 ? (totalProm / cont) : 0;
            html += '<tr style="font-weight:bold;"><td>PROMEDIO GENERAL</td><td style="color:' + (promedioGeneral >= 6 ? '#2e7d32' : '#d32f2f') + ';">' + promedioGeneral.toFixed(2) + '</td><td>' + (promedioGeneral >= 6 ? 'Aprobado' : 'Desaprobado') + '</td></tr></table></div>';
            
            html += '</div></div>';
            contenido.innerHTML = html;
            
            const ctx = document.getElementById('modal_canvas_3').getContext('2d');
            modalCharts['grafico3'] = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [{ label: 'Promedio', data: valores, backgroundColor: colores, borderRadius: 4 }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: {
                        y: { min: 0, max: 10, ticks: { font: { size: 11 } } },
                        x: { ticks: { font: { size: 9 } } }
                    }
                }
            });
        },
        error: function() {
            contenido.innerHTML = '<div class="sin-datos">Error al cargar los datos</div>';
        }
    });
}

// ============================================
// 4. EVOLUCION DEL ALUMNO (MODAL)
// ============================================
function cargarModal4(curso, materia) {
    const contenido = document.getElementById('modalContenido');
    
    $.ajax({
        url: 'ajax_estadisticas.php',
        type: 'POST',
        data: { 
            action: 'evolucion_trimestres',
            curso: curso,
            materia: materia
        },
        dataType: 'json',
        success: function(data) {
            if(modalCharts['grafico4']) {
                modalCharts['grafico4'].destroy();
                delete modalCharts['grafico4'];
            }
            
            const canvas = document.createElement('canvas');
            canvas.id = 'modal_canvas_4';
            
            var t1 = data.t1 || 0, t2 = data.t2 || 0, t3 = data.t3 || 0;
            var evolucion = t1 < t2 ? 'Mejorando' : (t1 > t2 ? 'Bajando' : 'Estable');
            var colorEvol = t1 < t2 ? '#2e7d32' : (t1 > t2 ? '#d32f2f' : '#ff9800');
            
            let html = '<div class="grafico-container-modal">';
            html += '<div class="grafico-wrapper-modal">';
            html += canvas.outerHTML;
            html += '</div>';
            html += '<div>';
            
            html += '<table class="tabla-mini-modal"><tr><th>Trimestre</th><th>Promedio</th><th>Estado</th></tr>';
            html += '<tr><td>1er Trimestre</td><td style="color:' + (t1 >= 6 ? '#2e7d32' : '#d32f2f') + ';font-weight:bold;">' + t1.toFixed(2) + '</td><td>' + (t1 >= 6 ? 'Aprobado' : 'Desaprobado') + '</td></tr>';
            html += '<tr><td>2do Trimestre</td><td style="color:' + (t2 >= 6 ? '#2e7d32' : '#d32f2f') + ';font-weight:bold;">' + t2.toFixed(2) + '</td><td>' + (t2 >= 6 ? 'Aprobado' : 'Desaprobado') + '</td></tr>';
            html += '<tr><td>3er Trimestre</td><td style="color:' + (t3 >= 6 ? '#2e7d32' : '#d32f2f') + ';font-weight:bold;">' + t3.toFixed(2) + '</td><td>' + (t3 >= 6 ? 'Aprobado' : 'Desaprobado') + '</td></tr>';
            html += '<tr style="font-weight:bold;"><td>EVOLUCION</td><td colspan="2" style="text-align:center;color:' + colorEvol + ';">' + evolucion + '</td></tr></table>';
            
            if(t1 === 0 && t2 === 0 && t3 === 0) {
                html = '<div class="sin-datos">No hay datos para los filtros seleccionados</div>';
            }
            
            html += '</div></div>';
            contenido.innerHTML = html;
            
            const ctx = document.getElementById('modal_canvas_4').getContext('2d');
            modalCharts['grafico4'] = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: ['1er Trimestre', '2do Trimestre', '3er Trimestre'],
                    datasets: [{
                        label: 'Promedio General',
                        data: [t1, t2, t3],
                        borderColor: '#710A14',
                        backgroundColor: 'rgba(113,10,20,0.1)',
                        fill: true,
                        tension: 0.3,
                        pointBackgroundColor: '#710A14',
                        pointRadius: 5
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: {
                        y: { min: 0, max: 10, ticks: { font: { size: 11 } } },
                        x: { ticks: { font: { size: 11 } } }
                    }
                }
            });
        },
        error: function() {
            contenido.innerHTML = '<div class="sin-datos">Error al cargar los datos</div>';
        }
    });
}

// ============================================
// 5. ALUMNOS CON PROMEDIO INFERIOR A VALOR (MODAL)
// ============================================
function cargarModal5(curso, materia, nota) {
    const contenido = document.getElementById('modalContenido');
    
    $.ajax({
        url: 'ajax_estadisticas.php',
        type: 'POST',
        data: { 
            action: 'alumnos_bajo_promedio',
            curso: curso,
            materia: materia,
            nota: nota
        },
        dataType: 'json',
        success: function(data) {
            var html = '<div style="display:flex; justify-content:space-between; flex-wrap:wrap; margin-bottom:15px; font-size:14px;">';
            html += '<span><strong>Total alumnos:</strong> ' + data.total_alumnos + '</span>';
            html += '<span><strong>Alumnos con promedio menor a ese valor:</strong> <span style="color:#d32f2f;font-weight:bold;">' + data.alumnos_riesgo + '</span></span>';
            html += '<span><strong>Porcentaje:</strong> <span style="color:' + (data.porcentaje > 50 ? '#d32f2f' : '#ff9800') + ';font-weight:bold;">' + data.porcentaje + '%</span></span>';
            html += '</div>';
            
            if(data.alumnos_lista && data.alumnos_lista.length > 0) {
                html += '<div class="scroll-datos-modal"><table class="tabla-mini-modal"><tr><th>#</th><th>Alumno</th><th>Curso</th><th>Promedio</th></tr>';
                data.alumnos_lista.forEach(function(al, i) {
                    html += '<tr><td>' + (i+1) + '</td><td>' + al.Apellido + ', ' + al.Nombre + '</td><td>' + al.curso + '° "' + al.division + '"</td><td style="color:#d32f2f;font-weight:bold;">' + al.promedio.toFixed(2) + '</td></tr>';
                });
                html += '</table></div>';
            } else {
                html += '<div class="sin-datos">No hay alumnos con promedio inferior a ' + nota + '</div>';
            }
            contenido.innerHTML = html;
        },
        error: function() {
            contenido.innerHTML = '<div class="sin-datos">Error al cargar los datos</div>';
        }
    });
}

// ============================================
// 6. RANKING DE CURSOS (MODAL)
// ============================================
function cargarModal6(trimestre) {
    const contenido = document.getElementById('modalContenido');
    contenido.innerHTML = '<div style="text-align:center;padding:30px;"><div class="loading-mini"></div> Cargando datos...</div>';
    
    $.ajax({
        url: 'ajax_estadisticas.php',
        type: 'POST',
        data: { 
            action: 'ranking_riesgo',
            trimestre: trimestre
        },
        dataType: 'json',
        success: function(data) {
            var html = '<div style="display:flex; flex-wrap:wrap; gap:15px; margin-bottom:15px; font-size:14px;">';
            html += '<span><strong>Total cursos:</strong> ' + data.total_cursos + '</span>';
            html += '<span><strong>Total alumnos en riesgo:</strong> <span style="color:#d32f2f;font-weight:bold;">' + data.total_riesgo + '</span></span>';
            html += '</div>';
            
            if(data.ranking && data.ranking.length > 0) {
                html += '<div class="scroll-datos-modal"><table class="tabla-mini-modal" id="tabla-ranking-riesgo">';
                html += '<tr><th>#</th><th>Curso</th><th>Total Alumnos</th><th>En Riesgo</th><th>%</th><th>Ver</th></tr>';
                data.ranking.forEach(function(item, i) {
                    var badge = item.porcentaje > 50 ? 'Alto' : (item.porcentaje > 25 ? 'Medio' : 'Bajo');
                    var color = item.porcentaje > 50 ? '#d32f2f' : (item.porcentaje > 25 ? '#ff9800' : '#2e7d32');
                    html += '<tr>';
                    html += '<td>' + (i+1) + '</td>';
                    html += '<td><strong>' + item.curso + '</strong></td>';
                    html += '<td>' + item.total_alumnos + '</td>';
                    html += '<td style="color:#d32f2f;font-weight:bold;">' + item.alumnos_riesgo + '</td>';
                    html += '<td style="color:' + color + ';font-weight:bold;">' + item.porcentaje + '%</td>';
                    html += '<td><button class="btn-ver-alumnos" onclick="verAlumnosRiesgo(' + i + ')" style="background:none;border:none;cursor:pointer;font-size:18px;">&#128065;</button></td>';
                    html += '</tr>';
                });
                html += '</table></div>';
                
                // Guardar los datos de ranking para usarlos en el modal de alumnos
                window.datosRanking = data.ranking;
                
                html += '<div class="definicion-riesgo">';
                html += '<strong>*</strong> Alumno en Riesgo: Es aquel que tiene promedio menor a 6 en al menos 3 materias.';
                html += '</div>';
                
            } else {
                html += '<div class="sin-datos">No hay datos para mostrar</div>';
            }
            contenido.innerHTML = html;
        },
        error: function() {
            contenido.innerHTML = '<div class="sin-datos">Error al cargar los datos</div>';
        }
    });
}

// ============================================
// VER ALUMNOS EN RIESGO DE UN CURSO
// ============================================
function verAlumnosRiesgo(index) {
    const cursoData = window.datosRanking[index];
    if (!cursoData) return;
    
    // Extraer el ID del curso del nombre (formato: "2° E (Mañana)")
    var cursoNombre = cursoData.curso;
    var cursoId = null;
    
    // Buscar el ID del curso en el array de cursos
    var cursos = <?= json_encode($cursos) ?>;
    for (var i = 0; i < cursos.length; i++) {
        var turno_texto = cursos[i].turno == 'M' ? 'Mañana' : 'Tarde';
        var nombre = cursos[i].curso + '° "' + cursos[i].division + '" (' + turno_texto + ')';
        if (nombre === cursoNombre) {
            cursoId = cursos[i].ID_curso;
            break;
        }
    }
    
    if (!cursoId) {
        alert('No se pudo identificar el curso');
        return;
    }
    
    // Abrir modal
    document.getElementById('modalAlumnosRiesgo').classList.add('activo');
    document.getElementById('modalRiesgoTitulo').textContent = 'Alumnos en Riesgo - ' + cursoNombre;
    document.getElementById('modalRiesgoBody').innerHTML = '<div style="text-align:center;padding:30px;"><div class="loading-mini"></div> Cargando alumnos...</div>';
    
    // Obtener alumnos en riesgo del curso
    var trimestre = document.getElementById('modal_trimestre')?.value || '0';
    
    $.ajax({
        url: 'ajax_estadisticas.php',
        type: 'POST',
        data: { 
            action: 'alumnos_riesgo_por_curso',
            curso_id: cursoId,
            trimestre: trimestre
        },
        dataType: 'json',
        success: function(data) {
            if (data.success && data.alumnos && data.alumnos.length > 0) {
                var html = '<table class="tabla-mini-modal">';
                html += '<tr><th>Alumno</th><th>Materias con promedio bajo</th><th>Ver materias</th></tr>';
                data.alumnos.forEach(function(alumno) {
                    html += '<tr>';
                    html += '<td>' + alumno.Apellido + ', ' + alumno.Nombre + '</td>';
                    html += '<td style="color:#d32f2f;font-weight:bold;">' + alumno.materias_bajas + '</td>';
                    html += '<td><button class="btn-ver-materias" onclick="verMateriasAlumno(' + alumno.DNI_U + ')" style="background:none;border:none;cursor:pointer;font-size:16px;">&#128065;</button></td>';
                    html += '</tr>';
                });
                html += '</table>';
                document.getElementById('modalRiesgoBody').innerHTML = html;
            } else {
                document.getElementById('modalRiesgoBody').innerHTML = '<div class="sin-datos">No hay alumnos en riesgo en este curso</div>';
            }
        },
        error: function() {
            document.getElementById('modalRiesgoBody').innerHTML = '<div class="sin-datos">Error al cargar los datos</div>';
        }
    });
}

function cerrarModalAlumnosRiesgo() {
    document.getElementById('modalAlumnosRiesgo').classList.remove('activo');
}

// ============================================
// VER MATERIAS DE UN ALUMNO
// ============================================
function verMateriasAlumno(dni) {
    document.getElementById('modalMateriasAlumno').classList.add('activo');
    document.getElementById('modalMateriasTitulo').textContent = 'Materias con promedio bajo';
    document.getElementById('modalMateriasBody').innerHTML = '<div style="text-align:center;padding:30px;"><div class="loading-mini"></div> Cargando materias...</div>';
    
    var trimestre = document.getElementById('modal_trimestre')?.value || '0';
    
    $.ajax({
        url: 'ajax_estadisticas.php',
        type: 'POST',
        data: { 
            action: 'materias_bajas_alumno',
            dni: dni,
            trimestre: trimestre
        },
        dataType: 'json',
        success: function(data) {
            if (data.success && data.materias && data.materias.length > 0) {
                var html = '<table class="tabla-mini-modal">';
                html += '<tr><th>Materia</th><th>Promedio</th><th>Estado</th></tr>';
                data.materias.forEach(function(materia) {
                    var estado = materia.promedio >= 6 ? 'Aprobado' : 'Desaprobado';
                    var color = materia.promedio >= 6 ? '#2e7d32' : '#d32f2f';
                    html += '<tr>';
                    html += '<td>' + materia.Nom_materia + '</td>';
                    html += '<td style="color:' + color + ';font-weight:bold;">' + materia.promedio.toFixed(2) + '</td>';
                    html += '<td style="color:' + color + ';">' + estado + '</td>';
                    html += '</tr>';
                });
                html += '</table>';
                document.getElementById('modalMateriasBody').innerHTML = html;
            } else {
                document.getElementById('modalMateriasBody').innerHTML = '<div class="sin-datos">No hay materias con promedio bajo</div>';
            }
        },
        error: function() {
            document.getElementById('modalMateriasBody').innerHTML = '<div class="sin-datos">Error al cargar los datos</div>';
        }
    });
}

function cerrarModalMateriasAlumno() {
    document.getElementById('modalMateriasAlumno').classList.remove('activo');
}

// ============================================
// 7. BAJA ASISTENCIA Y BAJO RENDIMIENTO (MODAL)
// ============================================
function cargarModal7(curso, faltas) {
    const contenido = document.getElementById('modalContenido');
    contenido.innerHTML = '<div style="text-align:center;padding:30px;"><div class="loading-mini"></div> Cargando datos...</div>';
    
    // Guardar filtros para usar en los clicks
    window.categoriaFiltros = {
        curso: curso,
        faltas: faltas
    };
    
    $.ajax({
        url: 'ajax_estadisticas.php',
        type: 'POST',
        data: { 
            action: 'baja_asistencia_rendimiento',
            curso: curso,
            faltas: faltas
        },
        dataType: 'json',
        success: function(data) {
            if(modalCharts['grafico7']) {
                modalCharts['grafico7'].destroy();
                delete modalCharts['grafico7'];
            }
            
            const canvas = document.createElement('canvas');
            canvas.id = 'modal_canvas_7';
            
            let html = '<div class="grafico-container-modal">';
            html += '<div class="grafico-wrapper-modal">';
            html += canvas.outerHTML;
            html += '</div>';
            html += '<div>';
            
            html += '<div class="definicion-rendimiento">';
            html += '<strong>Bajo Rendimiento:</strong> Alumno con 3 o más materias desaprobadas (nota menor a 6).';
            html += '</div>';
            
            // Tabla con categorías clickeables
            html += '<table class="tabla-mini-modal" id="tabla-categorias">';
            html += '<tr><th>Categoria</th><th>Cantidad</th><th>%</th></tr>';
            
            var categorias = [
                { key: 'sin_problemas', label: 'Sin problemas', color: '#2e7d32', cantidad: data.sin_problemas || 0 },
                { key: 'solo_asistencia', label: 'Solo baja asistencia', color: '#ff9800', cantidad: data.solo_asistencia || 0 },
                { key: 'solo_rendimiento', label: 'Solo bajo rendimiento', color: '#2196F3', cantidad: data.solo_rendimiento || 0 },
                { key: 'ambos', label: 'Ambos problemas', color: '#d32f2f', cantidad: data.ambos || 0 }
            ];
            
            categorias.forEach(function(cat) {
                var porcentaje = data.porcentajes?.[cat.key] || 0;
                html += '<tr style="cursor:pointer;" onclick="verAlumnosCategoria(\'' + cat.key + '\')" onmouseover="this.style.backgroundColor=\'#f0f0f0\'" onmouseout="this.style.backgroundColor=\'\'">';
                html += '<td style="color:' + cat.color + ';font-weight:bold;">' + cat.label + '</td>';
                html += '<td>' + cat.cantidad + '</td>';
                html += '<td>' + porcentaje + '%</td>';
                html += '</tr>';
            });
            html += '</table>';
            
            if(data.total === 0) {
                html = '<div class="sin-datos">No hay datos para los filtros seleccionados</div>';
            }
            
            html += '</div></div>';
            contenido.innerHTML = html;
            
            // Crear gráfico con click en barras
            const ctx = document.getElementById('modal_canvas_7').getContext('2d');
            var categoriasLabels = ['Sin problemas', 'Solo asistencia', 'Solo rendimiento', 'Ambos problemas'];
            var categoriasKeys = ['sin_problemas', 'solo_asistencia', 'solo_rendimiento', 'ambos'];
            var categoriasData = [data.sin_problemas || 0, data.solo_asistencia || 0, data.solo_rendimiento || 0, data.ambos || 0];
            var categoriasColores = ['#2e7d32', '#ff9800', '#2196F3', '#d32f2f'];
            
            modalCharts['grafico7'] = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: categoriasLabels,
                    datasets: [{
                        label: 'Alumnos',
                        data: categoriasData,
                        backgroundColor: categoriasColores,
                        borderRadius: 4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    var key = categoriasKeys[context.dataIndex];
                                    var porcentaje = data.porcentajes?.[key] || 0;
                                    return context.parsed.y + ' alumnos (' + porcentaje + '%)';
                                }
                            }
                        }
                    },
                    onClick: function(e, elements) {
                        if (elements.length > 0) {
                            var index = elements[0].index;
                            var key = categoriasKeys[index];
                            if (key && categoriasData[index] > 0) {
                                verAlumnosCategoria(key);
                            }
                        }
                    },
                    scales: {
                        y: { beginAtZero: true, ticks: { font: { size: 11 } } },
                        x: { ticks: { font: { size: 10 } } }
                    }
                }
            });
        },
        error: function() {
            contenido.innerHTML = '<div class="sin-datos">Error al cargar los datos</div>';
        }
    });
}

// ============================================
// VER ALUMNOS POR CATEGORIA
// ============================================
function verAlumnosCategoria(categoria) {
    var filtros = window.categoriaFiltros || { curso: '', faltas: '15' };
    var titulos = {
        'sin_problemas': 'Sin problemas',
        'solo_asistencia': 'Solo baja asistencia',
        'solo_rendimiento': 'Solo bajo rendimiento',
        'ambos': 'Ambos problemas'
    };
    
    document.getElementById('modalCategoriaTitulo').textContent = 'Alumnos - ' + (titulos[categoria] || categoria);
    document.getElementById('modalCategoriaBody').innerHTML = '<div style="text-align:center;padding:30px;"><div class="loading-mini"></div> Cargando alumnos...</div>';
    document.getElementById('modalCategoriaAlumnos').classList.add('activo');
    
    $.ajax({
        url: 'ajax_estadisticas.php',
        type: 'POST',
        data: { 
            action: 'alumnos_por_categoria',
            categoria: categoria,
            curso: filtros.curso,
            faltas: filtros.faltas
        },
        dataType: 'json',
        success: function(data) {
            if (data.success && data.alumnos && data.alumnos.length > 0) {
                var html = '<table class="tabla-mini-modal">';
                html += '<tr><th>#</th><th>Alumno</th><th>Curso</th><th>Faltas</th><th>Materias bajas</th></tr>';
                data.alumnos.forEach(function(al, i) {
                    html += '<tr>';
                    html += '<td>' + (i+1) + '</td>';
                    html += '<td>' + al.Apellido + ', ' + al.Nombre + '</td>';
                    html += '<td>' + al.curso + '° "' + al.division + '"</td>';
                    html += '<td>' + (al.faltas || 0) + '</td>';
                    html += '<td style="color:#d32f2f;font-weight:bold;">' + (al.materias_bajas || 0) + '</td>';
                    html += '</tr>';
                });
                html += '</table>';
                document.getElementById('modalCategoriaBody').innerHTML = html;
            } else {
                document.getElementById('modalCategoriaBody').innerHTML = '<div class="sin-datos">No hay alumnos en esta categoria</div>';
            }
        },
        error: function() {
            document.getElementById('modalCategoriaBody').innerHTML = '<div class="sin-datos">Error al cargar los datos</div>';
        }
    });
}

function cerrarModalCategoria() {
    document.getElementById('modalCategoriaAlumnos').classList.remove('activo');
}

// ============================================
// 8. ALUMNOS CON PREVIAS/EQUIVALENCIAS PENDIENTES
// ============================================
function cargarModal8() {
    const contenido = document.getElementById('modalContenido');
    contenido.innerHTML = '<div style="text-align:center;padding:30px;"><div class="loading-mini"></div> Cargando datos...</div>';
    
    const anio = document.getElementById('modal_anio_previa')?.value || '0';
    const tipo = document.getElementById('modal_tipo_previa')?.value || '';
    
    $.ajax({
        url: 'ajax_estadisticas.php',
        type: 'POST',
        data: { 
            action: 'previas_por_anio',
            anio: anio,
            tipo: tipo
        },
        dataType: 'json',
        success: function(data) {
            if(modalCharts['grafico8']) {
                modalCharts['grafico8'].destroy();
                delete modalCharts['grafico8'];
            }
            
            const labels = data.cursos || [];
            const valores = data.alumnos || [];
            
            if(labels.length === 0) {
                contenido.innerHTML = '<div class="sin-datos">No hay alumnos con previas/equivalencias pendientes para este año</div>';
                return;
            }
            
            const colores = valores.map(function(v) { 
                return v > 10 ? '#d32f2f' : (v > 5 ? '#ff9800' : '#2e7d32');
            });
            
            const canvas = document.createElement('canvas');
            canvas.id = 'modal_canvas_8';
            
            let html = '<div class="grafico-container-modal">';
            html += '<div class="grafico-wrapper-modal">';
            html += canvas.outerHTML;
            html += '</div>';
            html += '<div>';
            
            // Tabla con detalle
            html += '<div class="scroll-datos-modal"><table class="tabla-mini-modal">';
            html += '<tr><th>Curso</th><th>Alumnos con previas/equiv.</th><th>% del curso</th><th>Ver alumnos</th></tr>';
            
            var totalAlumnos = 0;
            var totalConPrevia = 0;
            
            data.cursos.forEach(function(curso, i) {
                var valor = data.alumnos[i] || 0;
                var totalCurso = data.totales[i] || 0;
                var porcentaje = totalCurso > 0 ? Math.round((valor / totalCurso) * 100) : 0;
                totalAlumnos += totalCurso;
                totalConPrevia += valor;
                
                var color = valor > 10 ? '#d32f2f' : (valor > 5 ? '#ff9800' : '#2e7d32');
                html += '<tr>';
                html += '<td><strong>' + curso + '</strong></td>';
                html += '<td style="color:' + color + ';font-weight:bold;">' + valor + '</td>';
                html += '<td>' + porcentaje + '%</td>';
                html += '<td><button class="btn-ver-alumnos" onclick="verAlumnosPrevia(\'' + curso + '\')" style="background:none;border:none;cursor:pointer;font-size:16px;">&#128065;</button></td>';
                html += '</tr>';
            });
            
            html += '<tr style="font-weight:bold;background:#f0f0f0;">';
            html += '<td>TOTAL</td>';
            html += '<td style="color:#d32f2f;">' + totalConPrevia + '</td>';
            html += '<td>' + (totalAlumnos > 0 ? Math.round((totalConPrevia / totalAlumnos) * 100) : 0) + '%</td>';
            html += '<td></td>';
            html += '</tr>';
            html += '</table></div>';
            
            html += '</div></div>';
            contenido.innerHTML = html;
            
            // Guardar datos para el modal de alumnos
            window.datosPrevia = data;
            
            const ctx = document.getElementById('modal_canvas_8').getContext('2d');
            modalCharts['grafico8'] = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [{ 
                        label: 'Alumnos con previas/equiv.', 
                        data: valores, 
                        backgroundColor: colores, 
                        borderRadius: 4 
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { 
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    var idx = context.dataIndex;
                                    var total = data.totales[idx] || 0;
                                    var porc = total > 0 ? Math.round((context.parsed.y / total) * 100) : 0;
                                    return context.parsed.y + ' alumnos (' + porc + '% del curso)';
                                }
                            }
                        }
                    },
                    scales: {
                        y: { 
                            beginAtZero: true, 
                            ticks: { font: { size: 11 } } 
                        },
                        x: { 
                            ticks: { font: { size: 9 } } 
                        }
                    }
                }
            });
        },
        error: function() {
            contenido.innerHTML = '<div class="sin-datos">Error al cargar los datos</div>';
        }
    });
}

// ============================================
// VER ALUMNOS CON PREVIAS DE UN CURSO
// ============================================
function verAlumnosPrevia(cursoNombre) {
    var datos = window.datosPrevia;
    if (!datos) return;
    
    // Encontrar el índice del curso
    var idx = -1;
    for (var i = 0; i < datos.cursos.length; i++) {
        if (datos.cursos[i] === cursoNombre) {
            idx = i;
            break;
        }
    }
    
    if (idx === -1) return;
    
    var cursoId = null;
    var cursos = <?= json_encode($cursos) ?>;
    for (var j = 0; j < cursos.length; j++) {
        // EXCLUIR 1° AÑO
        if(cursos[j].curso == 1) continue;
        var turno_texto = cursos[j].turno == 'M' ? 'Mañana' : 'Tarde';
        var nombre = cursos[j].curso + '° "' + cursos[j].division + '" (' + turno_texto + ')';
        if (nombre === cursoNombre) {
            cursoId = cursos[j].ID_curso;
            break;
        }
    }
    
    if (!cursoId) {
        alert('No se pudo identificar el curso');
        return;
    }
    
    var tipo = document.getElementById('modal_tipo_previa')?.value || '';
    
    document.getElementById('modalAlumnosRiesgo').classList.add('activo');
    document.getElementById('modalRiesgoTitulo').textContent = 'Alumnos con previas/equiv. - ' + cursoNombre;
    document.getElementById('modalRiesgoBody').innerHTML = '<div style="text-align:center;padding:30px;"><div class="loading-mini"></div> Cargando alumnos...</div>';
    
    $.ajax({
        url: 'ajax_estadisticas.php',
        type: 'POST',
        data: { 
            action: 'alumnos_previas_por_curso',
            curso_id: cursoId,
            tipo: tipo
        },
        dataType: 'json',
        success: function(data) {
            if (data.success && data.alumnos && data.alumnos.length > 0) {
                var html = '<table class="tabla-mini-modal">';
                html += '<tr><th>Alumno</th><th>Cantidad previas</th><th>Tipo</th><th>Detalle</th></tr>';
                data.alumnos.forEach(function(alumno) {
                    var tipoTexto = alumno.tipo_previa == 2 ? 'Previa' : (alumno.tipo_previa == 1 ? 'Equivalencia' : 'Ambas');
                    html += '<tr>';
                    html += '<td>' + alumno.Apellido + ', ' + alumno.Nombre + '</td>';
                    html += '<td style="color:#d32f2f;font-weight:bold;">' + alumno.cantidad + '</td>';
                    html += '<td>' + tipoTexto + '</td>';
                    html += '<td><button class="btn-ver-materias" onclick="verDetallePrevia(' + alumno.DNI_U + ')" style="background:none;border:none;cursor:pointer;font-size:16px;">&#128065;</button></td>';
                    html += '</tr>';
                });
                html += '</table>';
                document.getElementById('modalRiesgoBody').innerHTML = html;
            } else {
                document.getElementById('modalRiesgoBody').innerHTML = '<div class="sin-datos">No hay alumnos con previas/equivalencias en este curso</div>';
            }
        },
        error: function() {
            document.getElementById('modalRiesgoBody').innerHTML = '<div class="sin-datos">Error al cargar los datos</div>';
        }
    });
}

// ============================================
// VER DETALLE DE PREVIAS DE UN ALUMNO
// ============================================
function verDetallePrevia(dni) {
    document.getElementById('modalMateriasAlumno').classList.add('activo');
    document.getElementById('modalMateriasTitulo').textContent = 'Detalle de previas/equivalencias';
    document.getElementById('modalMateriasBody').innerHTML = '<div style="text-align:center;padding:30px;"><div class="loading-mini"></div> Cargando datos...</div>';
    
    $.ajax({
        url: 'ajax_estadisticas.php',
        type: 'POST',
        data: { 
            action: 'detalle_previas_alumno',
            dni: dni
        },
        dataType: 'json',
        success: function(data) {
            if (data.success && data.previas && data.previas.length > 0) {
                var html = '<table class="tabla-mini-modal">';
                html += '<tr><th>Materia/Taller</th><th>Tipo</th><th>Año</th><th>Estado</th></tr>';
                data.previas.forEach(function(item) {
                    var tipo = item.ID_tp == 2 ? 'Previa' : 'Equivalencia';
                    var estado = item.Aprobado == 0 ? 'Pendiente' : 'Aprobado';
                    var color = item.Aprobado == 0 ? '#d32f2f' : '#2e7d32';
                    html += '<tr>';
                    html += '<td>' + item.nombre_item + '</td>';
                    html += '<td>' + tipo + '</td>';
                    html += '<td>' + item.anio_item + '°</td>';
                    html += '<td style="color:' + color + ';font-weight:bold;">' + estado + '</td>';
                    html += '</tr>';
                });
                html += '</table>';
                document.getElementById('modalMateriasBody').innerHTML = html;
            } else {
                document.getElementById('modalMateriasBody').innerHTML = '<div class="sin-datos">No hay previas/equivalencias para este alumno</div>';
            }
        },
        error: function() {
            document.getElementById('modalMateriasBody').innerHTML = '<div class="sin-datos">Error al cargar los datos</div>';
        }
    });
}

function cerrarModalCategoria() {
    document.getElementById('modalCategoriaAlumnos').classList.remove('activo');
}

// ============================================
// 9. TOP 10 MATERIAS CON MAS DESAPROBADOS
// ============================================
function cargarModal9() {
    const contenido = document.getElementById('modalContenido');
    contenido.innerHTML = '<div style="text-align:center;padding:30px;"><div class="loading-mini"></div> Cargando datos...</div>';
    
    const anio = document.getElementById('modal_anio')?.value || '0';
    const trimestre = document.getElementById('modal_trimestre')?.value || '0';
    
    $.ajax({
        url: 'ajax_estadisticas.php',
        type: 'POST',
        data: { 
            action: 'top_materias_desaprobados',
            anio: anio,
            trimestre: trimestre
        },
        dataType: 'json',
        success: function(data) {
            if(modalCharts['grafico9']) {
                modalCharts['grafico9'].destroy();
                delete modalCharts['grafico9'];
            }
            
            const labels = data.materias || [];
            const valores = data.desaprobados || [];
            
            if(labels.length === 0) {
                contenido.innerHTML = '<div class="sin-datos">No hay datos para los filtros seleccionados</div>';
                return;
            }
            
            var max = Math.max.apply(null, valores);
            if(max === 0) max = 1;
            const colores = valores.map(function(v) { 
                var intensidad = v / max;
                if(intensidad > 0.7) return '#d32f2f';
                if(intensidad > 0.4) return '#ff9800';
                return '#f44336';
            });
            
            const canvas = document.createElement('canvas');
            canvas.id = 'modal_canvas_9';
            
            let html = '<div class="grafico-container-modal">';
            html += '<div class="grafico-wrapper-modal">';
            html += canvas.outerHTML;
            html += '</div>';
            html += '<div>';
            
            html += '<div class="scroll-datos-modal"><table class="tabla-mini-modal">';
            html += '<tr><th>#</th><th>Materia</th><th>Cantidad de Desaprobados</th><th>% del total</th></tr>';
            
            var totalDesaprobados = data.total_desaprobados || 0;
            data.materias.forEach(function(materia, i) {
                var valor = data.desaprobados[i] || 0;
                var porcentaje = totalDesaprobados > 0 ? Math.round((valor / totalDesaprobados) * 100) : 0;
                html += '<tr>';
                html += '<td>' + (i+1) + '</td>';
                html += '<td><strong>' + materia + '</strong></td>';
                html += '<td style="color:#d32f2f;font-weight:bold;">' + valor + '</td>';
                html += '<td>' + porcentaje + '%</td>';
                html += '</tr>';
            });
            html += '<tr style="font-weight:bold;background:#f0f0f0;">';
            html += '<td colspan="2">TOTAL DESAPROBADOS</td>';
            html += '<td style="color:#d32f2f;">' + totalDesaprobados + '</td>';
            html += '<td>100%</td>';
            html += '</tr>';
            html += '</table></div>';
            
            html += '</div></div>';
            contenido.innerHTML = html;
            
            const ctx = document.getElementById('modal_canvas_9').getContext('2d');
            modalCharts['grafico9'] = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [{ 
                        label: 'Desaprobados', 
                        data: valores, 
                        backgroundColor: colores, 
                        borderRadius: 4 
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { 
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    var total = data.total_desaprobados || 0;
                                    var porc = total > 0 ? Math.round((context.parsed.y / total) * 100) : 0;
                                    return context.parsed.y + ' alumnos (' + porc + '% del total)';
                                }
                            }
                        }
                    },
                    scales: {
                        y: { 
                            beginAtZero: true, 
                            ticks: { font: { size: 11 } } 
                        },
                        x: { 
                            ticks: { font: { size: 9 } } 
                        }
                    }
                }
            });
        },
        error: function() {
            contenido.innerHTML = '<div class="sin-datos">Error al cargar los datos</div>';
        }
    });
}

// ============================================
// 10. PORCENTAJE DE DESGRANAMIENTO ESCOLAR
// ============================================
function cargarModal10() {
    const contenido = document.getElementById('modalContenido');
    contenido.innerHTML = '<div style="text-align:center;padding:30px;"><div class="loading-mini"></div> Cargando datos...</div>';
    
    const anio = document.getElementById('modal_anio')?.value || '0';
    
    $.ajax({
        url: 'ajax_estadisticas.php',
        type: 'POST',
        data: { 
            action: 'desgranamiento_escolar',
            anio: anio
        },
        dataType: 'json',
        success: function(data) {
            if(modalCharts['grafico10']) {
                modalCharts['grafico10'].destroy();
                delete modalCharts['grafico10'];
            }
            
            const labels = data.cursos || [];
            const valores = data.porcentaje_desgranamiento || [];
            
            if(labels.length === 0) {
                contenido.innerHTML = '<div class="sin-datos">No hay datos para los filtros seleccionados</div>';
                return;
            }
            
            const colores = valores.map(function(v) { 
                if(v > 30) return '#d32f2f';
                if(v > 15) return '#ff9800';
                return '#2e7d32';
            });
            
            const canvas = document.createElement('canvas');
            canvas.id = 'modal_canvas_10';
            
            let html = '<div class="grafico-container-modal">';
            html += '<div class="grafico-wrapper-modal">';
            html += canvas.outerHTML;
            html += '</div>';
            html += '<div>';
            
            html += '<div class="scroll-datos-modal"><table class="tabla-mini-modal">';
            html += '<tr><th>Curso</th><th>Alumnos Iniciales</th><th>Alumnos Actuales</th><th>Desgranamiento</th><th>%</th><th>Estado</th></tr>';
            
            var totalIniciales = 0;
            var totalActuales = 0;
            
            data.cursos.forEach(function(curso, i) {
                var inicial = data.alumnos_iniciales[i] || 0;
                var actual = data.alumnos_actuales[i] || 0;
                var porcentaje = data.porcentaje_desgranamiento[i] || 0;
                totalIniciales += inicial;
                totalActuales += actual;
                
                var estado = porcentaje > 30 ? 'Alto' : (porcentaje > 15 ? 'Medio' : 'Bajo');
                var color = porcentaje > 30 ? '#d32f2f' : (porcentaje > 15 ? '#ff9800' : '#2e7d32');
                html += '<tr>';
                html += '<td><strong>' + curso + '</strong></td>';
                html += '<td>' + inicial + '</td>';
                html += '<td>' + actual + '</td>';
                html += '<td style="color:#d32f2f;">' + (inicial - actual) + '</td>';
                html += '<td style="color:' + color + ';font-weight:bold;">' + porcentaje + '%</td>';
                html += '<td style="color:' + color + ';">' + estado + '</td>';
                html += '</tr>';
            });
            var totalDesgranamiento = totalIniciales - totalActuales;
            var porcentajeGeneral = totalIniciales > 0 ? Math.round((totalDesgranamiento / totalIniciales) * 100) : 0;
            var estadoGeneral = porcentajeGeneral > 30 ? 'Alto' : (porcentajeGeneral > 15 ? 'Medio' : 'Bajo');
            var colorGeneral = porcentajeGeneral > 30 ? '#d32f2f' : (porcentajeGeneral > 15 ? '#ff9800' : '#2e7d32');
            html += '<tr style="font-weight:bold;background:#f0f0f0;">';
            html += '<td>TOTAL</td>';
            html += '<td>' + totalIniciales + '</td>';
            html += '<td>' + totalActuales + '</td>';
            html += '<td style="color:#d32f2f;">' + totalDesgranamiento + '</td>';
            html += '<td style="color:' + colorGeneral + ';">' + porcentajeGeneral + '%</td>';
            html += '<td style="color:' + colorGeneral + ';">' + estadoGeneral + '</td>';
            html += '</tr>';
            html += '</table></div>';
            
            // Definicion
            html += '<div class="definicion-riesgo" style="margin-top:15px;">';
            html += '<strong>Desgranamiento Escolar:</strong> Porcentaje de alumnos que abandonaron el curso durante el ciclo lectivo. ';
            html += 'Se calcula comparando los alumnos al inicio del ciclo con los alumnos actuales.';
            html += '</div>';
            
            html += '</div></div>';
            contenido.innerHTML = html;
            
            const ctx = document.getElementById('modal_canvas_10').getContext('2d');
            modalCharts['grafico10'] = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [{ 
                        label: 'Desgranamiento %', 
                        data: valores, 
                        backgroundColor: colores, 
                        borderRadius: 4 
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { 
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return context.parsed.y + '% de desgranamiento';
                                }
                            }
                        }
                    },
                    scales: {
                        y: { 
                            beginAtZero: true, 
                            max: 100,
                            ticks: { font: { size: 11 } } 
                        },
                        x: { 
                            ticks: { font: { size: 9 } } 
                        }
                    }
                }
            });
        },
        error: function() {
            contenido.innerHTML = '<div class="sin-datos">Error al cargar los datos</div>';
        }
    });
}

// ============================================
// CERRAR MODAL
// ============================================
function cerrarModal() {
    document.getElementById('modalUnico').classList.remove('activo');
    Object.keys(modalCharts).forEach(function(key) {
        if(modalCharts[key]) {
            modalCharts[key].destroy();
            delete modalCharts[key];
        }
    });
}

// ============================================
// INICIALIZACION - GRAFICOS MINI
// ============================================
$(document).ready(function() {
    cargarMiniGraficos();
});

function cargarMiniGraficos() {
    // Grafico 1
    $.ajax({
        url: 'ajax_estadisticas.php',
        type: 'POST',
        data: { action: 'aprobados_desaprobados', curso: '', materia: '', trimestre: '0' },
        dataType: 'json',
        success: function(data) {
            if(miniCharts['grafico1']) miniCharts['grafico1'].destroy();
            const ctx = document.getElementById('mini_grafico1').getContext('2d');
            miniCharts['grafico1'] = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: ['Aprobados', 'Desaprobados'],
                    datasets: [{ data: [data.aprobados || 0, data.desaprobados || 0], backgroundColor: ['#2e7d32', '#d32f2f'], borderWidth: 1 }]
                },
                options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } } }
            });
        }
    });
    
    // Grafico 2
    $.ajax({
        url: 'ajax_estadisticas.php',
        type: 'POST',
        data: { action: 'promedio_general_curso', anio: '0', trimestre: '0' },
        dataType: 'json',
        success: function(data) {
            if(miniCharts['grafico2']) miniCharts['grafico2'].destroy();
            const ctx = document.getElementById('mini_grafico2').getContext('2d');
            const valores = data.promedios || [];
            const labels = data.cursos || [];
            const colores = valores.map(function(v) { return v >= 6 ? '#2e7d32' : '#d32f2f'; });
            
            if(labels.length === 0) {
                ctx.clearRect(0, 0, ctx.canvas.width, ctx.canvas.height);
                ctx.fillStyle = '#ccc';
                ctx.font = '12px Arial';
                ctx.textAlign = 'center';
                ctx.fillText('Sin datos', ctx.canvas.width/2, ctx.canvas.height/2 + 4);
                return;
            }
            
            miniCharts['grafico2'] = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [{ data: valores, backgroundColor: colores, borderRadius: 3 }]
                },
                options: { 
                    responsive: true, 
                    maintainAspectRatio: false, 
                    plugins: { legend: { display: false } }, 
                    scales: { 
                        y: { min: 0, max: 10, ticks: { font: { size: 8 } } }, 
                        x: { ticks: { font: { size: 7 } } } 
                    } 
                }
            });
        },
        error: function() {
            const ctx = document.getElementById('mini_grafico2').getContext('2d');
            ctx.clearRect(0, 0, ctx.canvas.width, ctx.canvas.height);
            ctx.fillStyle = '#ccc';
            ctx.font = '12px Arial';
            ctx.textAlign = 'center';
            ctx.fillText('Error', ctx.canvas.width/2, ctx.canvas.height/2 + 4);
        }
    });
    
    // Grafico 3
    $.ajax({
        url: 'ajax_estadisticas.php',
        type: 'POST',
        data: { action: 'promedio_por_materia', anio: '0', division: '', trimestre: '0' },
        dataType: 'json',
        success: function(data) {
            if(miniCharts['grafico3']) miniCharts['grafico3'].destroy();
            const ctx = document.getElementById('mini_grafico3').getContext('2d');
            const valores = data.promedios || [];
            const labels = data.materias || [];
            const colores = valores.map(function(v) { return v >= 6 ? '#2e7d32' : '#d32f2f'; });
            
            if(labels.length === 0) {
                ctx.clearRect(0, 0, ctx.canvas.width, ctx.canvas.height);
                ctx.fillStyle = '#ccc';
                ctx.font = '12px Arial';
                ctx.textAlign = 'center';
                ctx.fillText('Sin datos', ctx.canvas.width/2, ctx.canvas.height/2 + 4);
                return;
            }
            
            var labelsMostrar = labels.slice(0, 10);
            var valoresMostrar = valores.slice(0, 10);
            var coloresMostrar = colores.slice(0, 10);
            
            miniCharts['grafico3'] = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: labelsMostrar,
                    datasets: [{ 
                        data: valoresMostrar, 
                        backgroundColor: coloresMostrar, 
                        borderRadius: 3 
                    }]
                },
                options: { 
                    responsive: true, 
                    maintainAspectRatio: false, 
                    plugins: { 
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return 'Promedio: ' + context.parsed.y.toFixed(2);
                                }
                            }
                        }
                    }, 
                    scales: { 
                        y: { 
                            min: 0, 
                            max: 10, 
                            ticks: { font: { size: 8 } } 
                        }, 
                        x: { 
                            ticks: { 
                                font: { size: 7 },
                                maxRotation: 45,
                                minRotation: 30
                            } 
                        } 
                    } 
                }
            });
        },
        error: function() {
            const ctx = document.getElementById('mini_grafico3').getContext('2d');
            ctx.clearRect(0, 0, ctx.canvas.width, ctx.canvas.height);
            ctx.fillStyle = '#ccc';
            ctx.font = '12px Arial';
            ctx.textAlign = 'center';
            ctx.fillText('Error', ctx.canvas.width/2, ctx.canvas.height/2 + 4);
        }
    });
    
    // Grafico 4
    $.ajax({
        url: 'ajax_estadisticas.php',
        type: 'POST',
        data: { action: 'evolucion_trimestres', curso: '', materia: '' },
        dataType: 'json',
        success: function(data) {
            if(miniCharts['grafico4']) miniCharts['grafico4'].destroy();
            const ctx = document.getElementById('mini_grafico4').getContext('2d');
            miniCharts['grafico4'] = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: ['T1', 'T2', 'T3'],
                    datasets: [{ data: [data.t1 || 0, data.t2 || 0, data.t3 || 0], borderColor: '#710A14', backgroundColor: 'rgba(113,10,20,0.1)', fill: true, tension: 0.3, pointBackgroundColor: '#710A14', pointRadius: 3 }]
                },
                options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { min: 0, max: 10, ticks: { font: { size: 8 } } }, x: { ticks: { font: { size: 8 } } } } }
            });
        }
    });
    
    // Grafico 5
    $.ajax({
        url: 'ajax_estadisticas.php',
        type: 'POST',
        data: { action: 'alumnos_bajo_promedio', curso: '', materia: '', nota: '6' },
        dataType: 'json',
        success: function(data) {
            const canvas = document.getElementById('mini_grafico5');
            const ctx = canvas.getContext('2d');
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            const total = data.total_alumnos || 0;
            const riesgo = data.alumnos_riesgo || 0;
            const seguro = total - riesgo;
            if(total > 0) {
                if(miniCharts['grafico5']) miniCharts['grafico5'].destroy();
                miniCharts['grafico5'] = new Chart(ctx, {
                    type: 'doughnut',
                    data: {
                        labels: ['Bajos', 'Seguros'],
                        datasets: [{ data: [riesgo, seguro], backgroundColor: ['#d32f2f', '#2e7d32'], borderWidth: 1 }]
                    },
                    options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } } }
                });
            } else {
                ctx.fillStyle = '#ccc';
                ctx.font = '12px Arial';
                ctx.textAlign = 'center';
                ctx.fillText('Sin datos', canvas.width/2, canvas.height/2 + 4);
            }
        }
    });
    
    // Grafico 6
    $.ajax({
        url: 'ajax_estadisticas.php',
        type: 'POST',
        data: { action: 'ranking_riesgo', trimestre: '0' },
        dataType: 'json',
        success: function(data) {
            if(miniCharts['grafico6']) miniCharts['grafico6'].destroy();
            const ctx = document.getElementById('mini_grafico6').getContext('2d');
            
            if(data.ranking && data.ranking.length > 0) {
                var rankingOrdenado = data.ranking.slice().sort(function(a, b) {
                    return b.alumnos_riesgo - a.alumnos_riesgo;
                });
                
                var topCursos = rankingOrdenado.slice(0, 20);
                var labels = topCursos.map(function(item) { return item.curso; });
                var valores = topCursos.map(function(item) { return item.alumnos_riesgo; });
                var colores = topCursos.map(function(item) { 
                    return item.porcentaje > 50 ? '#d32f2f' : (item.porcentaje > 25 ? '#ff9800' : '#2e7d32');
                });
                
                miniCharts['grafico6'] = new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels: labels,
                        datasets: [{ 
                            data: valores, 
                            backgroundColor: colores, 
                            borderRadius: 3 
                        }]
                    },
                    options: { 
                        responsive: true, 
                        maintainAspectRatio: false, 
                        plugins: { 
                            legend: { display: false },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        var item = topCursos[context.dataIndex];
                                        return 'En riesgo: ' + context.parsed.y + ' (' + item.porcentaje + '%)';
                                    }
                                }
                            }
                        }, 
                        scales: { 
                            y: { 
                                beginAtZero: true,
                                ticks: { font: { size: 8 } } 
                            }, 
                            x: { 
                                ticks: { font: { size: 6 } } 
                            } 
                        } 
                    }
                });
            } else {
                ctx.clearRect(0, 0, ctx.canvas.width, ctx.canvas.height);
                ctx.fillStyle = '#ccc';
                ctx.font = '12px Arial';
                ctx.textAlign = 'center';
                ctx.fillText('Sin datos', ctx.canvas.width/2, ctx.canvas.height/2 + 4);
            }
        },
        error: function() {
            const ctx = document.getElementById('mini_grafico6').getContext('2d');
            ctx.clearRect(0, 0, ctx.canvas.width, ctx.canvas.height);
            ctx.fillStyle = '#ccc';
            ctx.font = '12px Arial';
            ctx.textAlign = 'center';
            ctx.fillText('Error', ctx.canvas.width/2, ctx.canvas.height/2 + 4);
        }
    });
    
    // Grafico 7
    $.ajax({
        url: 'ajax_estadisticas.php',
        type: 'POST',
        data: { action: 'baja_asistencia_rendimiento', curso: '', faltas: '15' },
        dataType: 'json',
        success: function(data) {
            if(miniCharts['grafico7']) miniCharts['grafico7'].destroy();
            const ctx = document.getElementById('mini_grafico7').getContext('2d');
            miniCharts['grafico7'] = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: ['Sin', 'Asist.', 'Rend.', 'Ambos'],
                    datasets: [{ data: [data.sin_problemas || 0, data.solo_asistencia || 0, data.solo_rendimiento || 0, data.ambos || 0], backgroundColor: ['#2e7d32', '#ff9800', '#2196F3', '#d32f2f'], borderRadius: 3 }]
                },
                options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { font: { size: 8 } } }, x: { ticks: { font: { size: 7 } } } } }
            });
        }
    });

    // Grafico 8 - Previas/Equivalencias
    $.ajax({
        url: 'ajax_estadisticas.php',
        type: 'POST',
        data: { action: 'previas_por_anio', anio: '0', tipo: '' },
        dataType: 'json',
        success: function(data) {
            if(miniCharts['grafico8']) miniCharts['grafico8'].destroy();
            const ctx = document.getElementById('mini_grafico8').getContext('2d');
            const labels = data.cursos || [];
            const valores = data.alumnos || [];
            const colores = valores.map(function(v) { 
                return v > 10 ? '#d32f2f' : (v > 5 ? '#ff9800' : '#2e7d32');
            });
            
            if(labels.length === 0) {
                ctx.clearRect(0, 0, ctx.canvas.width, ctx.canvas.height);
                ctx.fillStyle = '#ccc';
                ctx.font = '12px Arial';
                ctx.textAlign = 'center';
                ctx.fillText('Sin datos', ctx.canvas.width/2, ctx.canvas.height/2 + 4);
                return;
            }
            
            var labelsMostrar = labels.slice(0, 15);
            var valoresMostrar = valores.slice(0, 15);
            var coloresMostrar = colores.slice(0, 15);
            
            miniCharts['grafico8'] = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: labelsMostrar,
                    datasets: [{ 
                        data: valoresMostrar, 
                        backgroundColor: coloresMostrar, 
                        borderRadius: 3 
                    }]
                },
                options: { 
                    responsive: true, 
                    maintainAspectRatio: false, 
                    plugins: { 
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    var idx = context.dataIndex;
                                    var total = data.totales[idx] || 0;
                                    var porc = total > 0 ? Math.round((context.parsed.y / total) * 100) : 0;
                                    return context.parsed.y + ' alumnos (' + porc + '%)';
                                }
                            }
                        }
                    }, 
                    scales: { 
                        y: { 
                            beginAtZero: true,
                            ticks: { font: { size: 8 } } 
                        }, 
                        x: { 
                            ticks: { font: { size: 6 } } 
                        } 
                    } 
                }
            });
        },
        error: function() {
            const ctx = document.getElementById('mini_grafico8').getContext('2d');
            ctx.clearRect(0, 0, ctx.canvas.width, ctx.canvas.height);
            ctx.fillStyle = '#ccc';
            ctx.font = '12px Arial';
            ctx.textAlign = 'center';
            ctx.fillText('Error', ctx.canvas.width/2, ctx.canvas.height/2 + 4);
        }
    });

    // Grafico 9 - Top 10 materias con mas desaprobados
    $.ajax({
        url: 'ajax_estadisticas.php',
        type: 'POST',
        data: { action: 'top_materias_desaprobados', anio: '0', trimestre: '0' },
        dataType: 'json',
        success: function(data) {
            if(miniCharts['grafico9']) miniCharts['grafico9'].destroy();
            const ctx = document.getElementById('mini_grafico9').getContext('2d');
            const labels = data.materias || [];
            const valores = data.desaprobados || [];
            
            if(labels.length === 0) {
                ctx.clearRect(0, 0, ctx.canvas.width, ctx.canvas.height);
                ctx.fillStyle = '#ccc';
                ctx.font = '12px Arial';
                ctx.textAlign = 'center';
                ctx.fillText('Sin datos', ctx.canvas.width/2, ctx.canvas.height/2 + 4);
                return;
            }
            
            var max = Math.max.apply(null, valores);
            if(max === 0) max = 1;
            var colores = valores.map(function(v) { 
                var intensidad = v / max;
                if(intensidad > 0.7) return '#d32f2f';
                if(intensidad > 0.4) return '#ff9800';
                return '#f44336';
            });
            
            var labelsMostrar = labels.slice(0, 10);
            var valoresMostrar = valores.slice(0, 10);
            var coloresMostrar = colores.slice(0, 10);
            
            miniCharts['grafico9'] = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: labelsMostrar,
                    datasets: [{ 
                        data: valoresMostrar, 
                        backgroundColor: coloresMostrar, 
                        borderRadius: 3 
                    }]
                },
                options: { 
                    responsive: true, 
                    maintainAspectRatio: false, 
                    plugins: { 
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    var total = data.total_desaprobados || 0;
                                    var porc = total > 0 ? Math.round((context.parsed.y / total) * 100) : 0;
                                    return context.parsed.y + ' alumnos (' + porc + '% del total)';
                                }
                            }
                        }
                    }, 
                    scales: { 
                        y: { 
                            beginAtZero: true,
                            ticks: { font: { size: 8 } } 
                        }, 
                        x: { 
                            ticks: { font: { size: 6 } } 
                        } 
                    } 
                }
            });
        },
        error: function() {
            const ctx = document.getElementById('mini_grafico9').getContext('2d');
            ctx.clearRect(0, 0, ctx.canvas.width, ctx.canvas.height);
            ctx.fillStyle = '#ccc';
            ctx.font = '12px Arial';
            ctx.textAlign = 'center';
            ctx.fillText('Error', ctx.canvas.width/2, ctx.canvas.height/2 + 4);
        }
    });

    // Grafico 10 - Desgranamiento Escolar
    $.ajax({
        url: 'ajax_estadisticas.php',
        type: 'POST',
        data: { action: 'desgranamiento_escolar', anio: '0' },
        dataType: 'json',
        success: function(data) {
            if(miniCharts['grafico10']) miniCharts['grafico10'].destroy();
            const ctx = document.getElementById('mini_grafico10').getContext('2d');
            const labels = data.cursos || [];
            const valores = data.porcentaje_desgranamiento || [];
            const colores = valores.map(function(v) { 
                if(v > 30) return '#d32f2f';
                if(v > 15) return '#ff9800';
                return '#2e7d32';
            });
            
            if(labels.length === 0) {
                ctx.clearRect(0, 0, ctx.canvas.width, ctx.canvas.height);
                ctx.fillStyle = '#ccc';
                ctx.font = '12px Arial';
                ctx.textAlign = 'center';
                ctx.fillText('Sin datos', ctx.canvas.width/2, ctx.canvas.height/2 + 4);
                return;
            }
            
            var labelsMostrar = labels.slice(0, 15);
            var valoresMostrar = valores.slice(0, 15);
            var coloresMostrar = colores.slice(0, 15);
            
            miniCharts['grafico10'] = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: labelsMostrar,
                    datasets: [{ 
                        data: valoresMostrar, 
                        backgroundColor: coloresMostrar, 
                        borderRadius: 3 
                    }]
                },
                options: { 
                    responsive: true, 
                    maintainAspectRatio: false, 
                    plugins: { 
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return context.parsed.y + '% de desgranamiento';
                                }
                            }
                        }
                    }, 
                    scales: { 
                        y: { 
                            beginAtZero: true,
                            max: 100,
                            ticks: { font: { size: 8 } } 
                        }, 
                        x: { 
                            ticks: { font: { size: 6 } } 
                        } 
                    } 
                }
            });
        },
        error: function() {
            const ctx = document.getElementById('mini_grafico10').getContext('2d');
            ctx.clearRect(0, 0, ctx.canvas.width, ctx.canvas.height);
            ctx.fillStyle = '#ccc';
            ctx.font = '12px Arial';
            ctx.textAlign = 'center';
            ctx.fillText('Error', ctx.canvas.width/2, ctx.canvas.height/2 + 4);
        }
    });
}

// Cerrar modal con Escape
document.addEventListener('keydown', function(e) {
    if(e.key === 'Escape') {
        cerrarModal();
    }
});

// Cerrar modal al hacer clic fuera
document.getElementById('modalUnico').addEventListener('click', function(e) {
    if(e.target === this) {
        cerrarModal();
    }
});
</script>
</body>
</html>
<?php mysqli_close($con); ?>