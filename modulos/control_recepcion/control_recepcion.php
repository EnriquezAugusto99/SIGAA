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

$_SESSION["ultima_actividad"] = time();

// Verificar roles autorizados (Admin o Preceptor)
$es_admin = ($_SESSION['rol'] == 'Admin');
$es_preceptor = ($_SESSION['rol'] == 'Preceptor');
$es_equipo = ($_SESSION['rol'] == 'Equipo de Orientacion');

if(!$es_admin && !$es_preceptor && !$es_equipo){
    echo '<script>alert("No tiene permisos para acceder a esta sección"); window.location="../../recursos/panel.php";</script>';
    exit();
}

include '../../recursos/conexion.php';

// 2. CONFIGURACIÓN DE FILTROS
$periodo_seleccionado = isset($_GET['periodo']) ? $_GET['periodo'] : '1er Trimestre';
$periodo_esc = mysqli_real_escape_string($con, $periodo_seleccionado);
$preceptor_dni = $_SESSION["dni"];

// Obtener filtros de curso y alumno
$filtro_curso = isset($_GET['filtro_curso']) ? $_GET['filtro_curso'] : '';
$filtro_alumno = isset($_GET['filtro_alumno']) ? $_GET['filtro_alumno'] : '';

// 3. CONSTRUIR LA CONSULTA CON FILTROS
// Construir las condiciones WHERE
$where_condiciones = [];

// Siempre buscar alumnos activos con tutores
$where_condiciones[] = "a.ID_rol = 3";
$where_condiciones[] = "a.ID_Estado = 1";

// Filtro de curso
if(!empty($filtro_curso)){
    $where_condiciones[] = "c.ID_curso = '$filtro_curso'";
}

// Filtro de alumno
if(!empty($filtro_alumno)){
    $where_condiciones[] = "a.DNI_U = '$filtro_alumno'";
}

// Si es preceptor, solo sus cursos
if($es_preceptor){
    $where_condiciones[] = "c.ID_curso IN (SELECT id_curso FROM preceptorxcurso WHERE id_preceptor = '$preceptor_dni')";
}

$where_clause = implode(" AND ", $where_condiciones);

// CONSULTA PRINCIPAL CON FILTROS
$query_control = "SELECT 
                    c.curso, c.division, c.turno,
                    t.DNI_U AS tutor_dni, CONCAT(t.Apellido, ', ', t.Nombre) AS tutor_nombre, t.email AS tutor_email,
                    a.DNI_U AS alumno_dni, CONCAT(a.Apellido, ', ', a.Nombre) AS alumno_nombre,
                    cl.fecha_envio, cl.confirmado, cl.fecha_confirmacion
                  FROM curso c
                  INNER JOIN usuario a ON a.id_curso = c.ID_curso
                  INNER JOIN alumnoxtutor at ON at.id_alumno = a.DNI_U
                  INNER JOIN usuario t ON t.DNI_U = at.id_tutor
                  LEFT JOIN confirmacion_libreta cl ON cl.id_tutor = t.DNI_U 
                                                   AND cl.id_alumno = a.DNI_U 
                                                   AND cl.tipo_envio = '$periodo_esc'
                  WHERE $where_clause
                  ORDER BY c.curso, c.division, a.Apellido, a.Nombre";

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
$cursos_filtro = [];
while($curso_filtro = mysqli_fetch_assoc($res_cursos_filtro)){
    $cursos_filtro[] = $curso_filtro;
}

// Obtener alumnos para el filtro (si hay curso seleccionado)
$alumnos_filtro = [];
if(!empty($filtro_curso)){
    $query_alumnos_filtro = "SELECT DNI_U, Nombre, Apellido 
                             FROM usuario 
                             WHERE id_curso = '$filtro_curso' 
                             AND ID_rol = 3 
                             AND ID_Estado = 1
                             ORDER BY Apellido, Nombre";
    $res_alumnos_filtro = mysqli_query($con, $query_alumnos_filtro);
    while($alumno_filtro = mysqli_fetch_assoc($res_alumnos_filtro)){
        $alumnos_filtro[] = $alumno_filtro;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Control de Recepción de Libretas - EPET N°34</title>
    <link rel="stylesheet" href="../../recursos/css/estilos.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
        .bar-herramientas { display: flex; flex-wrap: wrap; gap: 15px; margin-bottom: 20px; background: #fdfdfd; padding: 15px; border: 1px solid #eef0f5; border-radius: 8px; align-items: flex-end; }
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
                    <?php foreach($cursos_filtro as $curso_filtro):
                        $turno_texto = $curso_filtro['turno'] == 'M' ? 'Mañana' : 'Tarde';
                        $curso_nombre_filtro = $curso_filtro['curso'] . '° "' . $curso_filtro['division'] . '" - ' . $turno_texto;
                        $selected = ($filtro_curso == $curso_filtro['ID_curso']) ? 'selected' : '';
                    ?>
                        <option value="<?= $curso_filtro['ID_curso'] ?>" <?= $selected ?>>
                            <?= htmlspecialchars($curso_nombre_filtro) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-grupo-filtro">
                <label for="filtro_alumno">Alumno:</label>
                <select name="filtro_alumno" id="filtro_alumno" class="select-control" <?= empty($filtro_curso) ? 'disabled' : '' ?> onchange="this.form.submit()">
                    <option value="">-- Todos los alumnos --</option>
                    <?php foreach($alumnos_filtro as $alumno_filtro):
                        $selected = ($filtro_alumno == $alumno_filtro['DNI_U']) ? 'selected' : '';
                    ?>
                        <option value="<?= $alumno_filtro['DNI_U'] ?>" <?= $selected ?>>
                            <?= htmlspecialchars($alumno_filtro['Apellido'] . ', ' . $alumno_filtro['Nombre']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-grupo-filtro" style="flex-direction: row; gap: 8px; align-items: center; padding-bottom: 1px;">
                <button type="submit" class="btn-aplicar-filtros"><i class="fas fa-filter"></i> Aplicar</button>
                <a href="control_recepcion.php?periodo=<?= urlencode($periodo_seleccionado) ?>" class="btn-limpiar-filtros"><i class="fas fa-times"></i> Limpiar</a>
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
</script>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</body>
</html>
<?php mysqli_close($con); ?>