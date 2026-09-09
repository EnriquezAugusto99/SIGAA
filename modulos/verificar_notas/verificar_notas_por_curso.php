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
    echo '<script>alert("No tiene permisos para acceder a esta seccion"); window.location="../../recursos/panel.php";</script>';
    exit();
}

include '../../recursos/conexion.php';

$preceptor_dni = $_SESSION["dni"];

// Obtener cursos según el rol
$cursos = [];

if($es_admin){
    // Admin: todos los cursos
    $query_cursos = "SELECT ID_curso, curso, division, turno FROM curso ORDER BY curso, division";
    $res_cursos = mysqli_query($con, $query_cursos);
    while($row = mysqli_fetch_assoc($res_cursos)){
        $cursos[] = $row;
    }
} else {
    // Preceptor: solo cursos asignados en preceptorxcurso
    $query_cursos = "SELECT c.ID_curso, c.curso, c.division, c.turno
                     FROM curso c
                     INNER JOIN preceptorxcurso pxc ON c.ID_curso = pxc.id_curso
                     WHERE pxc.id_preceptor = '$preceptor_dni'
                     ORDER BY c.curso, c.division";
    $res_cursos = mysqli_query($con, $query_cursos);
    while($row = mysqli_fetch_assoc($res_cursos)){
        $cursos[] = $row;
    }
}

// Trimestres disponibles
$trimestres = [
    1 => '1er Trimestre',
    2 => '2do Trimestre', 
    3 => '3er Trimestre'
];

// Años disponibles
$query_anios = "SELECT DISTINCT YEAR(fecha) as anio FROM calificaciones UNION SELECT DISTINCT anio FROM rotaciones ORDER BY anio DESC";
$res_anios = mysqli_query($con, $query_anios);
$anios = [];
while($row = mysqli_fetch_assoc($res_anios)){
    $anios[] = $row['anio'];
}
if(empty($anios)){
    $anios = [date('Y')];
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verificar Notas por Curso - <?= $es_admin ? 'Administrador' : 'Preceptor' ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Montserrat', sans-serif; background: #f5f5f5; padding: 30px 20px; }
        .container { max-width: 1400px; margin: 0 auto; }
        .header { background: linear-gradient(135deg, #3F070B 0%, #710A14 100%); border-radius: 12px; padding: 25px 30px; margin-bottom: 30px; color: white; }
        .header h1 { font-size: 28px; margin-bottom: 8px; display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }
        .header p { opacity: 0.9; font-size: 14px; }
        .card { background: white; border-radius: 12px; padding: 25px; margin-bottom: 30px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
        .card h2 { font-size: 20px; color: #3F070B; margin-bottom: 20px; border-left: 4px solid #710A14; padding-left: 15px; }
        .filtros-container {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            align-items: flex-end;
            margin-bottom: 25px;
        }
        .filtro-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
            min-width: 180px;
        }
        .filtro-group label {
            font-weight: 600;
            color: #333;
            font-size: 13px;
        }
        .filtro-group label i {
            color: #710A14;
            margin-right: 6px;
        }
        .filtro-group select {
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
            font-family: 'Montserrat', sans-serif;
            background: white;
            cursor: pointer;
        }
        .filtro-group select:focus {
            outline: none;
            border-color: #710A14;
        }
        .filtro-group select:disabled {
            background: #f5f5f5;
            cursor: not-allowed;
        }
        .btn-verificar {
            background: #710A14;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .btn-verificar:hover {
            background: #3F070B;
            transform: translateY(-2px);
        }
        .btn-verificar:disabled {
            background: #999;
            cursor: not-allowed;
            transform: none;
        }
        .btn-secondary {
            background: #666;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
        }
        .btn-secondary:hover {
            background: #555;
            transform: translateY(-2px);
        }
        
        /* Tabla */
        .tabla-container {
            overflow-x: auto;
            margin-top: 20px;
            max-height: 70vh;
            overflow-y: auto;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
        }
        .tabla-verificacion {
            width: 100%;
            border-collapse: collapse;
            font-size: 12px;
            min-width: 600px;
        }
        .tabla-verificacion th,
        .tabla-verificacion td {
            border: 1px solid #ddd;
            padding: 10px 8px;
            text-align: center;
            vertical-align: middle;
        }
        .tabla-verificacion th {
            background: #710A14;
            color: white;
            font-weight: 600;
            position: sticky;
            top: 0;
            z-index: 10;
        }
        .tabla-verificacion th.materia-nombre {
            cursor: pointer;
            transition: background 0.2s;
        }
        .tabla-verificacion th.materia-nombre:hover {
            background: #8b1a1a;
        }
        .tabla-verificacion td.alumno-nombre {
            text-align: left;
            font-weight: 500;
            background: #f8f9fa;
            position: sticky;
            left: 0;
            z-index: 5;
            min-width: 180px;
        }
        .check-ok {
            color: #2e7d32;
            font-size: 20px;
        }
        .check-no {
            color: #d32f2f;
            font-size: 20px;
        }
        .total-row {
            background: #f0f0f0;
            font-weight: bold;
        }
        .total-row td {
            border-top: 2px solid #710A14;
        }
        .loading {
            display: inline-block;
            width: 24px;
            height: 24px;
            border: 3px solid #f3f3f3;
            border-top: 3px solid #710A14;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin-left: 10px;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        .resumen-card {
            background: #e8f0fe;
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            gap: 30px;
            flex-wrap: wrap;
            align-items: center;
        }
        .resumen-item {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .resumen-numero {
            font-size: 24px;
            font-weight: bold;
            color: #710A14;
        }
        .resumen-texto {
            font-size: 13px;
            color: #555;
        }
        .leyenda {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
            margin-bottom: 15px;
            padding: 10px 15px;
            background: #f5f5f5;
            border-radius: 8px;
        }
        .leyenda-item {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 12px;
        }
        .sin-datos {
            text-align: center;
            padding: 60px;
            color: #999;
        }
        .info-curso {
            background: #fff3e0;
            padding: 10px 15px;
            border-radius: 8px;
            margin-bottom: 15px;
            border-left: 4px solid #ff9800;
            font-size: 13px;
        }
        .badge-trimestre {
            background: #ff9800;
            color: #333;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
            margin-left: 10px;
        }
        .rol-badge {
            background: #2196F3;
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
            margin-left: 10px;
        }
        
        /* Modal */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            justify-content: center;
            align-items: center;
        }
        .modal-content {
            background-color: white;
            border-radius: 12px;
            width: 90%;
            max-width: 500px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.2);
            animation: modalFadeIn 0.3s;
        }
        @keyframes modalFadeIn {
            from { opacity: 0; transform: translateY(-50px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .modal-header {
            background: #710A14;
            color: white;
            padding: 15px 20px;
            border-radius: 12px 12px 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .modal-header h3 {
            margin: 0;
            font-size: 18px;
        }
        .modal-header .close {
            font-size: 24px;
            cursor: pointer;
            background: none;
            border: none;
            color: white;
        }
        .modal-header .close:hover {
            opacity: 0.8;
        }
        .modal-body {
            padding: 20px;
        }
        .profesor-item {
            padding: 12px;
            border-bottom: 1px solid #e0e0e0;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .profesor-item:last-child {
            border-bottom: none;
        }
        .profesor-nombre {
            font-weight: 600;
            color: #333;
        }
        .profesor-info {
            font-size: 12px;
            color: #666;
        }
        .sin-profesores {
            text-align: center;
            padding: 30px;
            color: #999;
        }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1>
            <i class="fas fa-clipboard-list"></i> Verificar Notas por Curso
            <?php if($es_admin): ?>
                <span class="rol-badge"><i class="fas fa-crown"></i> Administrador</span>
            <?php else: ?>
                <span class="rol-badge"><i class="fas fa-chalkboard-user"></i> Preceptor</span>
            <?php endif; ?>
            <span class="badge-trimestre" id="badge-trimestre-activo"></span>
        </h1>
        <p><i class="fas fa-graduation-cap"></i> Control de cantidad de notas cargadas por materia (mínimo 3 notas por trimestre)</p>
        <?php if($es_preceptor): ?>
        <p style="font-size: 12px; margin-top: 8px; opacity: 0.8;">
            <i class="fas fa-info-circle"></i> Visualizando solo los cursos que tienes asignados como Preceptor
        </p>
        <?php endif; ?>
    </div>

    <div class="card">
        <h2><i class="fas fa-filter"></i> Filtros</h2>
        
        <div class="filtros-container">
            <div class="filtro-group">
                <label><i class="fas fa-calendar-alt"></i> Año</label>
                <select id="anio">
                    <?php foreach($anios as $a): ?>
                        <option value="<?= $a ?>" <?= $a == date('Y') ? 'selected' : '' ?>><?= $a ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="filtro-group">
                <label><i class="fas fa-chart-line"></i> Trimestre</label>
                <select id="trimestre">
                    <option value="1">1er Trimestre</option>
                    <option value="2">2do Trimestre</option>
                    <option value="3">3er Trimestre</option>
                </select>
            </div>
            
            <div class="filtro-group">
                <label><i class="fas fa-school"></i> Curso</label>
                <select id="curso_id">
                    <option value="">-- Seleccione un curso --</option>
                    <?php foreach($cursos as $curso): 
                        $turno_texto = $curso['turno'] == 'M' ? 'Mañana' : 'Tarde';
                    ?>
                        <option value="<?= $curso['ID_curso'] ?>">
                            <?= $curso['curso'] ?>° "<?= $curso['division'] ?>" - <?= $turno_texto ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="filtro-group">
                <button id="btnVerificar" class="btn-verificar" disabled>
                    <i class="fas fa-search"></i> Verificar
                </button>
            </div>
        </div>
    </div>

    <div id="resultadoContainer"></div>
    
    <div class="btn-group">
        <a href="../../recursos/panel.php" class="btn-secondary">
            <i class="fas fa-arrow-left"></i> Volver al Panel
        </a>
    </div>
</div>

<!-- Modal para profesores -->
<div id="modalProfesores" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="modalTitulo">Profesores</h3>
            <button class="close" onclick="cerrarModal()">&times;</button>
        </div>
        <div class="modal-body" id="modalBody">
            <div style="text-align: center; padding: 20px;">Cargando...</div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
$(document).ready(function() {
    let cursoSeleccionado = false;
    
    // Habilitar botón cuando se selecciona curso
    $('#curso_id').change(function() {
        cursoSeleccionado = $(this).val() !== '';
        $('#btnVerificar').prop('disabled', !cursoSeleccionado);
    });
    
    // Actualizar badge del trimestre
    function actualizarBadgeTrimestre() {
        let trimestre = $('#trimestre').val();
        let nombre = '';
        if(trimestre == 1) nombre = '1er Trimestre';
        else if(trimestre == 2) nombre = '2do Trimestre';
        else if(trimestre == 3) nombre = '3er Trimestre';
        $('#badge-trimestre-activo').text(nombre);
    }
    
    $('#trimestre').change(function() {
        actualizarBadgeTrimestre();
    });
    
    actualizarBadgeTrimestre();
    
    // Verificar
    $('#btnVerificar').click(function() {
        let cursoId = $('#curso_id').val();
        let trimestre = $('#trimestre').val();
        let anio = $('#anio').val();
        
        if(!cursoId) {
            alert('Seleccione un curso primero');
            return;
        }
        
        $(this).prop('disabled', true).html('<i class="fas fa-spinner fa-pulse"></i> Verificando...');
        
        $('#resultadoContainer').html('<div style="text-align: center; padding: 50px;"><div class="loading"></div><p>Cargando...</p></div>');
        
        $.ajax({
            url: 'ajax_verificar_notas.php',
            type: 'POST',
            data: {
                action: 'verificar_notas_curso',
                curso_id: cursoId,
                trimestre: trimestre,
                anio: anio,
                rol: '<?= $es_admin ? "admin" : "preceptor" ?>',
                preceptor_dni: '<?= $es_preceptor ? $preceptor_dni : "" ?>'
            },
            dataType: 'json',
            success: function(response) {
                if(response.success) {
                    mostrarResultados(response);
                } else {
                    $('#resultadoContainer').html('<div class="card"><div class="sin-datos"><i class="fas fa-exclamation-triangle" style="font-size: 48px; margin-bottom: 15px;"></i><p>' + response.message + '</p></div></div>');
                }
                $('#btnVerificar').prop('disabled', false).html('<i class="fas fa-search"></i> Verificar');
            },
            error: function(xhr, status, error) {
                $('#resultadoContainer').html('<div class="card"><div class="sin-datos"><i class="fas fa-exclamation-triangle" style="font-size: 48px; margin-bottom: 15px;"></i><p>Error: ' + error + '</p></div></div>');
                $('#btnVerificar').prop('disabled', false).html('<i class="fas fa-search"></i> Verificar');
            }
        });
    });
    
    function mostrarResultados(data) {
        let trimestre = $('#trimestre').val();
        let nombreTrimestre = trimestre == 1 ? '1er Trimestre' : (trimestre == 2 ? '2do Trimestre' : '3er Trimestre');
        
        let html = `
            <div class="card">
                <div class="resumen-card">
                    <div class="resumen-item">
                        <div>
                            <div class="resumen-numero">${data.total_alumnos}</div>
                            <div class="resumen-texto">Total Alumnos</div>
                        </div>
                    </div>
                    <div class="resumen-item">
                        <div>
                            <div class="resumen-numero">${data.alumnos_completos}</div>
                            <div class="resumen-texto">Completos (3+ notas)</div>
                        </div>
                    </div>
                    <div class="resumen-item">
                        <div>
                            <div class="resumen-numero">${data.alumnos_incompletos}</div>
                            <div class="resumen-texto">Incompletos</div>
                        </div>
                    </div>
                </div>
                
                <div class="info-curso">
                    <i class="fas fa-info-circle"></i> 
                    <strong>Curso:</strong> ${data.curso_nombre} | 
                    <strong>Trimestre:</strong> ${nombreTrimestre}
                </div>
                
                <div class="leyenda">
                    <div class="leyenda-item"><i class="fas fa-check-circle check-ok"></i> <span>3+ notas</span></div>
                    <div class="leyenda-item"><i class="fas fa-times-circle check-no"></i> <span>Menos de 3 notas</span></div>
                    <div class="leyenda-item"><i class="fas fa-chalkboard-user"></i> <span>Click en materia → ver profesores</span></div>
                </div>
        `;
        
        if(data.materias.length === 0) {
            html += `<div class="sin-datos"><p>No hay materias cargadas</p></div>`;
        } else {
            html += `<div class="tabla-container">
                        <table class="tabla-verificacion">
                            <thead>
                                <tr>
                                    <th style="position: sticky; left: 0; z-index: 20;">Alumno</th>
                                    ${data.materias.map(m => `<th class="materia-nombre" data-materia-id="${m.id}" data-materia-nombre="${m.nombre}">${m.nombre}</th>`).join('')}
                                    <th>Completas</th>
                                    <th>Total</th>
                                </tr>
                            </thead>
                            <tbody>
            `;
            
            for(let alumno of data.alumnos) {
                let completas = 0;
                let totalMaterias = data.materias.length;
                
                html += `<tr>
                            <td class="alumno-nombre"><strong>${alumno.apellido}, ${alumno.nombre}</strong><br><small>DNI: ${alumno.dni}</small></td>`;
                
                for(let materia of data.materias) {
                    let notaInfo = alumno.notas[materia.id] || { tiene_notas: false };
                    let tieneNotas = notaInfo.tiene_notas;
                    
                    if(tieneNotas) {
                        completas++;
                        html += `<td><i class="fas fa-check-circle check-ok"></i></td>`;
                    } else {
                        html += `<td><i class="fas fa-times-circle check-no"></i></td>`;
                    }
                }
                
                let porcentaje = totalMaterias > 0 ? Math.round((completas / totalMaterias) * 100) : 0;
                let colorPorcentaje = porcentaje >= 70 ? '#2e7d32' : (porcentaje >= 40 ? '#ff9800' : '#d32f2f');
                
                html += `<td><strong style="color: ${colorPorcentaje};">${completas}</strong></td>
                         <td><strong>${totalMaterias}</strong></td>
                        </tr>`;
            }
            
            // Fila de resumen
            html += `<tr class="total-row">
                        <td class="alumno-nombre"><strong>✔️ COMPLETAS</strong></td>`;
            for(let materia of data.materias) {
                let completasMateria = data.alumnos.filter(a => a.notas[materia.id]?.tiene_notas).length;
                html += `<td><strong>${completasMateria}/${data.alumnos.length}</strong></td>`;
            }
            html += `<td colspan="2"></td></tr>`;
            
            html += `</tbody>}</div>`;
        }
        
        html += `</div>`;
        $('#resultadoContainer').html(html);
        
        // Agregar evento click a las materias
        $('.materia-nombre').click(function() {
            let materiaId = $(this).data('materia-id');
            let materiaNombre = $(this).data('materia-nombre');
            let cursoId = $('#curso_id').val();
            
            abrirModalProfesores(materiaId, materiaNombre, cursoId);
        });
    }
    
    function abrirModalProfesores(materiaId, materiaNombre, cursoId) {
        $('#modalTitulo').text('Profesores - ' + materiaNombre);
        $('#modalBody').html('<div style="text-align: center; padding: 20px;"><div class="loading"></div><p>Cargando...</p></div>');
        $('#modalProfesores').css('display', 'flex');
        
        $.ajax({
            url: 'ajax_verificar_notas.php',
            type: 'POST',
            data: {
                action: 'obtener_profesores_materia',
                materia_id: materiaId,
                curso_id: cursoId
            },
            dataType: 'json',
            success: function(response) {
                if(response.success && response.profesores.length > 0) {
                    let html = '';
                    for(let prof of response.profesores) {
                        html += `
                            <div class="profesor-item">
                                <i class="fas fa-chalkboard-user" style="color: #710A14; font-size: 24px;"></i>
                                <div>
                                    <div class="profesor-nombre">${prof.apellido}, ${prof.nombre}</div>
                                    <div class="profesor-info">DNI: ${prof.dni}</div>
                                </div>
                            </div>
                        `;
                    }
                    $('#modalBody').html(html);
                } else {
                    $('#modalBody').html(`<div class="sin-profesores"><i class="fas fa-user-graduate" style="font-size: 48px; margin-bottom: 15px;"></i><p>No hay profesores asignados a esta materia</p></div>`);
                }
            },
            error: function() {
                $('#modalBody').html(`<div class="sin-profesores"><i class="fas fa-exclamation-triangle" style="font-size: 48px; margin-bottom: 15px;"></i><p>Error al cargar los profesores</p></div>`);
            }
        });
    }
    
    window.cerrarModal = function() {
        $('#modalProfesores').css('display', 'none');
    }
    
    // Cerrar modal al hacer click fuera
    $(window).click(function(event) {
        if ($(event.target).is('#modalProfesores')) {
            cerrarModal();
        }
    });
});
</script>
</body>
</html>
<?php mysqli_close($con); ?>