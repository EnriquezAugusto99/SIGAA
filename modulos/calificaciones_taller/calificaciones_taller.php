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
$es_profesor = ($_SESSION['rol'] == 'Profesor');

if(!$es_admin && !$es_profesor){
    echo '<script>alert("No tiene permisos para gestionar calificaciones de talleres"); window.location="../../recursos/panel.php";</script>';
    exit();
}

include '../../recursos/conexion.php';

$docente_dni = $_SESSION["dni"];
$error = '';
$success = '';

$anio_actual = date('Y');

// Obtener rotaciones del año actual
$query_rotaciones = "SELECT ID_rotacion, numero_rotacion, nombre, fecha_inicio, fecha_fin 
                     FROM rotaciones 
                     WHERE anio = '$anio_actual' AND activo = 1 
                     ORDER BY numero_rotacion";
$res_rotaciones = mysqli_query($con, $query_rotaciones);
$rotaciones = [];
while($row = mysqli_fetch_array($res_rotaciones)){
    $rotaciones[] = $row;
}

// Obtener talleres del profesor (o todos si es admin)
$talleres_profesor = [];
if($es_admin){
    $query_talleres = "SELECT ID_taller, nombre, anio_taller FROM talleres WHERE activo = 1 ORDER BY anio_taller, nombre";
} else {
    $query_talleres = "SELECT DISTINCT t.ID_taller, t.nombre, t.anio_taller
                       FROM talleres t
                       INNER JOIN docente_taller_curso dtc ON t.ID_taller = dtc.ID_taller
                       WHERE dtc.ID_docente = '$docente_dni' AND dtc.anio = '$anio_actual'
                       ORDER BY t.anio_taller, t.nombre";
}
$res_talleres = mysqli_query($con, $query_talleres);
while($row = mysqli_fetch_array($res_talleres)){
    $talleres_profesor[] = $row;
}

// Generar token CSRF
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Procesar guardado de calificaciones desde el envío del formulario
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['guardar_calificaciones'])){
    $taller_id = $_POST['taller_id'];
    $rotacion_id = $_POST['rotacion_id'];
    $total_guardados = 0;
    
    foreach($_POST['notas'] as $alumno_id => $data){
        // Sanatización de comas a puntos en PHP para evitar truncados numéricos no deseados en floatval()
        $nota1 = (!empty($data['nota1']) && $data['nota1'] !== '') ? round(floatval(str_replace(',', '.', $data['nota1'])), 2) : null;
        $nota2 = (!empty($data['nota2']) && $data['nota2'] !== '') ? round(floatval(str_replace(',', '.', $data['nota2'])), 2) : null;
        $nota3 = (!empty($data['nota3']) && $data['nota3'] !== '') ? round(floatval(str_replace(',', '.', $data['nota3'])), 2) : null;
        $nota4 = (!empty($data['nota4']) && $data['nota4'] !== '') ? round(floatval(str_replace(',', '.', $data['nota4'])), 2) : null;
        $nota5 = (!empty($data['nota5']) && $data['nota5'] !== '') ? round(floatval(str_replace(',', '.', $data['nota5'])), 2) : null;
        $recuperatorio = (!empty($data['recuperatorio']) && $data['recuperatorio'] !== '') ? round(floatval(str_replace(',', '.', $data['recuperatorio'])), 2) : null;
        $promedio = (!empty($data['promedio']) && $data['promedio'] != '-') ? round(floatval(str_replace(',', '.', $data['promedio'])), 2) : null;
        $promedio_final = (!empty($data['promedio_final']) && $data['promedio_final'] != '-') ? round(floatval(str_replace(',', '.', $data['promedio_final'])), 2) : null;
        $calificacion_definitiva = (!empty($data['calificacion_definitiva']) && $data['calificacion_definitiva'] != '-') ? round(floatval(str_replace(',', '.', $data['calificacion_definitiva'])), 2) : null;
        
        $query_check = "SELECT ID_calif_taller FROM calificaciones_taller 
                        WHERE ID_alumno = '$alumno_id' 
                        AND ID_taller = '$taller_id' 
                        AND ID_rotacion = '$rotacion_id'";
        $res_check = mysqli_query($con, $query_check);
        
        if(mysqli_num_rows($res_check) > 0){
            $query = "UPDATE calificaciones_taller 
                      SET nota1 = " . ($nota1 !== null ? "'$nota1'" : "NULL") . ",
                          nota2 = " . ($nota2 !== null ? "'$nota2'" : "NULL") . ",
                          nota3 = " . ($nota3 !== null ? "'$nota3'" : "NULL") . ",
                          nota4 = " . ($nota4 !== null ? "'$nota4'" : "NULL") . ",
                          nota5 = " . ($nota5 !== null ? "'$nota5'" : "NULL") . ",
                          recuperatorio = " . ($recuperatorio !== null ? "'$recuperatorio'" : "NULL") . ",
                          promedio = " . ($promedio !== null ? "'$promedio'" : "NULL") . ",
                          promedio_final = " . ($promedio_final !== null ? "'$promedio_final'" : "NULL") . ",
                          calificacion_definitiva = " . ($calificacion_definitiva !== null ? "'$calificacion_definitiva'" : "NULL") . ",
                          fecha = CURDATE()
                      WHERE ID_alumno = '$alumno_id' 
                      AND ID_taller = '$taller_id' 
                      AND ID_rotacion = '$rotacion_id'";
        } else {
            $query = "INSERT INTO calificaciones_taller (ID_alumno, ID_taller, ID_rotacion, nota1, nota2, nota3, nota4, nota5, recuperatorio, promedio, promedio_final, calificacion_definitiva, fecha) 
                      VALUES ('$alumno_id', '$taller_id', '$rotacion_id', " . 
                      ($nota1 !== null ? "'$nota1'" : "NULL") . ", " .
                      ($nota2 !== null ? "'$nota2'" : "NULL") . ", " .
                      ($nota3 !== null ? "'$nota3'" : "NULL") . ", " .
                      ($nota4 !== null ? "'$nota4'" : "NULL") . ", " .
                      ($nota5 !== null ? "'$nota5'" : "NULL") . ", " .
                      ($recuperatorio !== null ? "'$recuperatorio'" : "NULL") . ", " .
                      ($promedio !== null ? "'$promedio'" : "NULL") . ", " .
                      ($promedio_final !== null ? "'$promedio_final'" : "NULL") . ", " .
                      ($calificacion_definitiva !== null ? "'$calificacion_definitiva'" : "NULL") . ", CURDATE())";
        }
        
        if(mysqli_query($con, $query)){
            $total_guardados++;
        }
    }
    
    $success = "Calificaciones guardadas correctamente. ($total_guardados alumnos)";
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../../recursos/styles.css">
    <title>Planilla de Calificaciones - Talleres</title>
    <style>
        .container {
            max-width: 98%;
            margin: 20px auto;
            padding: 25px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            overflow-x: auto;
        }
        h1, h2 { color: #7a0000; }
        .filtros {
            background: #f5f5f5;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 25px;
        }
        .filtros-grid {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            align-items: flex-end;
        }
        .filtro-group {
            flex: 1;
            min-width: 180px;
        }
        .filtro-group label {
            display: block;
            font-weight: 600;
            margin-bottom: 5px;
            font-size: 13px;
            color: #555;
        }
        .filtro-group select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
        }
        .mensaje-error {
            background: #f8d7da;
            color: #721c24;
            padding: 12px;
            border-radius: 6px;
            margin: 15px 0;
        }
        .mensaje-success {
            background: #d4edda;
            color: #155724;
            padding: 12px;
            border-radius: 6px;
            margin: 15px 0;
        }
        .info-rotacion {
            background: #e2f3ff;
            padding: 10px 15px;
            border-radius: 6px;
            margin-bottom: 20px;
            border-left: 4px solid #2196F3;
        }
        .admin-badge {
            background: #ff9800;
            color: #1a2a3a;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.7em;
            font-weight: bold;
            margin-left: 10px;
        }
        .loading {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid #f3f3f3;
            border-top: 3px solid #7a0000;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin-left: 10px;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
<div class="container">
    <h1>
        Planilla de Calificaciones - Talleres
        <?php if($es_admin): ?>
            <span class="admin-badge">👑 Modo Administrador</span>
        <?php endif; ?>
    </h1>
    
    <div id="mensajes">
        <?php if($error): ?>
            <div class="mensaje-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <?php if($success): ?>
            <div class="mensaje-success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>
    </div>
    
    <div class="filtros">
        <div class="filtros-grid">
            <div class="filtro-group">
                <label>Taller</label>
                <select id="taller_id" name="taller_id">
                    <option value="">-- Seleccione un taller --</option>
                    <?php foreach($talleres_profesor as $taller): ?>
                        <option value="<?= $taller['ID_taller'] ?>">
                            <?= htmlspecialchars($taller['nombre'] . ' (' . $taller['anio_taller'] . ')') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filtro-group">
                <label>Rotación</label>
                <select id="rotacion_id" name="rotacion_id" disabled>
                    <option value="">-- Primero seleccione un taller --</option>
                </select>
            </div>
            <div class="filtro-group">
                <label>Curso</label>
                <select id="curso_id" name="curso_id" disabled>
                    <option value="">-- Primero seleccione una rotación --</option>
                </select>
            </div>
            <div class="filtro-group">
                <label>Grupo</label>
                <select id="grupo_id" name="grupo_id" disabled>
                    <option value="">-- Primero seleccione un curso --</option>
                </select>
            </div>
            <div class="filtro-group">
                <label>&nbsp;</label>
                <div id="loading_indicator" style="display: none;">
                    <div class="loading"></div> Cargando...
                </div>
            </div>
        </div>
    </div>
    
    <div id="info_rotacion" style="display: none;" class="info-rotacion"></div>
    <div id="planilla_container"></div>
    
    <p style="margin-top: 20px;">
        <a href="../../recursos/panel.php"><button>Volver al Panel</button></a>
    </p>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
$(document).ready(function() {
    $('#taller_id').change(function() {
        var taller_id = $(this).val();
        if(taller_id) {
            $('#loading_indicator').show();
            $.ajax({
                url: 'ajax_rotaciones.php',
                type: 'POST',
                data: { action: 'get_rotaciones', taller_id: taller_id },
                dataType: 'json',
                success: function(response) {
                    $('#loading_indicator').hide();
                    if(response.success) {
                        var $rotacion = $('#rotacion_id');
                        $rotacion.empty().append('<option value="">-- Seleccione una rotación --</option>');
                        $.each(response.rotaciones, function(i, rot) {
                            $rotacion.append('<option value="' + rot.ID_rotacion + '">' + rot.nombre + ' (' + rot.fechas + ')</option>');
                        });
                        $rotacion.prop('disabled', false);
                        $('#curso_id').prop('disabled', true).empty().append('<option value="">-- Primero seleccione una rotación --</option>');
                        $('#grupo_id').prop('disabled', true).empty().append('<option value="">-- Primero seleccione un curso --</option>');
                        $('#planilla_container').empty();
                        $('#info_rotacion').hide();
                    } else {
                        alert(response.message);
                    }
                },
                error: function() {
                    $('#loading_indicator').hide();
                    alert('Error al cargar rotaciones');
                }
            });
        } else {
            $('#rotacion_id').prop('disabled', true).empty().append('<option value="">-- Primero seleccione un taller --</option>');
            $('#curso_id').prop('disabled', true).empty().append('<option value="">-- Primero seleccione una rotación --</option>');
            $('#grupo_id').prop('disabled', true).empty().append('<option value="">-- Primero seleccione un curso --</option>');
            $('#planilla_container').empty();
            $('#info_rotacion').hide();
        }
    });
    
    $('#rotacion_id').change(function() {
        var rotacion_id = $(this).val();
        var taller_id = $('#taller_id').val();
        if(rotacion_id && taller_id) {
            $('#loading_indicator').show();
            $.ajax({
                url: 'ajax_rotaciones.php',
                type: 'POST',
                data: { action: 'get_cursos', rotacion_id: rotacion_id, taller_id: taller_id },
                dataType: 'json',
                success: function(response) {
                    $('#loading_indicator').hide();
                    if(response.success) {
                        var $curso = $('#curso_id');
                        $curso.empty().append('<option value="">-- Seleccione un curso --</option>');
                        $.each(response.cursos, function(i, curso) {
                            $curso.append('<option value="' + curso.ID_curso + '">' + curso.nombre + '</option>');
                        });
                        $curso.prop('disabled', false);
                        $('#grupo_id').prop('disabled', true).empty().append('<option value="">-- Primero seleccione un curso --</option>');
                        $('#planilla_container').empty();
                        
                        if(response.rotacion_info) {
                            $('#info_rotacion').html('<strong>' + response.rotacion_info.nombre + '</strong> | Fechas: ' + response.rotacion_info.fecha_inicio + ' al ' + response.rotacion_info.fecha_fin).show();
                        }
                    } else {
                        alert(response.message);
                    }
                },
                error: function() {
                    $('#loading_indicator').hide();
                    alert('Error al cargar cursos');
                }
            });
        }
    });
    
    $('#curso_id').change(function() {
        var curso_id = $(this).val();
        var rotacion_id = $('#rotacion_id').val();
        var taller_id = $('#taller_id').val();
        if(curso_id && rotacion_id && taller_id) {
            $('#loading_indicator').show();
            $.ajax({
                url: 'ajax_rotaciones.php',
                type: 'POST',
                data: { action: 'get_grupos', curso_id: curso_id, rotacion_id: rotacion_id, taller_id: taller_id },
                dataType: 'json',
                success: function(response) {
                    $('#loading_indicator').hide();
                    if(response.success) {
                        var $grupo = $('#grupo_id');
                        $grupo.empty().append('<option value="">-- Seleccione un grupo --</option>');
                        $.each(response.grupos, function(i, grupo) {
                            $grupo.append('<option value="' + grupo + '">Grupo ' + grupo + '</option>');
                        });
                        $grupo.prop('disabled', false);
                    } else {
                        alert(response.message);
                    }
                },
                error: function() {
                    $('#loading_indicator').hide();
                    alert('Error al cargar grupos');
                }
            });
        }
    });
    
    $('#grupo_id').change(function() {
        var grupo_id = $(this).val();
        var curso_id = $('#curso_id').val();
        var rotacion_id = $('#rotacion_id').val();
        var taller_id = $('#taller_id').val();
        
        if(grupo_id && curso_id && rotacion_id && taller_id) {
            $('#loading_indicator').show();
            $.ajax({
                url: 'ajax_planilla_taller.php',
                type: 'POST',
                data: {
                    taller_id: taller_id,
                    rotacion_id: rotacion_id,
                    curso_id: curso_id,
                    grupo_id: grupo_id
                },
                dataType: 'html',
                success: function(html) {
                    $('#loading_indicator').hide();
                    $('#planilla_container').html(html);
                },
                error: function() {
                    $('#loading_indicator').hide();
                    alert('Error al cargar la planilla');
                }
            });
        }
    });
});
</script>
</body>
</html>
<?php mysqli_close($con); ?>