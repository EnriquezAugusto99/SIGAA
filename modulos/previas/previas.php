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

// Solo Admin y Preceptor pueden acceder
$es_admin = ($_SESSION['rol'] == 'Admin');
$es_preceptor = ($_SESSION['rol'] == 'Preceptor');

if(!$es_admin && !$es_preceptor){
    echo '<script>alert("No tiene permisos para gestionar previas"); window.location="../../recursos/panel.php";</script>';
    exit();
}

include '../../recursos/conexion.php';

// Obtener cursos del preceptor si no es admin
$cursos_disponibles = [];
if($es_admin){
    $query_cursos = "SELECT DISTINCT ID_curso, curso, division, turno FROM curso ORDER BY curso, division";
    $res_cursos = mysqli_query($con, $query_cursos);
    while($row = mysqli_fetch_array($res_cursos)){
        $cursos_disponibles[] = $row;
    }
} else {
    $preceptor_dni = $_SESSION["dni"];
    $query_cursos = "SELECT c.ID_curso, c.curso, c.division, c.turno
                     FROM curso c
                     INNER JOIN preceptorxcurso pc ON c.ID_curso = pc.id_curso
                     WHERE pc.id_preceptor = '$preceptor_dni'
                     ORDER BY c.curso, c.division";
    $res_cursos = mysqli_query($con, $query_cursos);
    while($row = mysqli_fetch_array($res_cursos)){
        $cursos_disponibles[] = $row;
    }
}

$anios_materia = range(1, 6);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registrar Previa - EPET N° 34</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Montserrat', sans-serif; background: #f5f5f5; padding: 30px 20px; }
        .container { max-width: 1200px; margin: 0 auto; }
        .header { background: linear-gradient(135deg, #3F070B 0%, #710A14 100%); border-radius: 12px; padding: 25px 30px; margin-bottom: 30px; color: white; }
        .header h1 { font-size: 28px; margin-bottom: 8px; display: flex; align-items: center; gap: 12px; }
        .header p { opacity: 0.9; font-size: 14px; }
        .card { background: white; border-radius: 12px; padding: 25px; margin-bottom: 30px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
        .card h2 { font-size: 20px; color: #3F070B; margin-bottom: 20px; border-left: 4px solid #710A14; padding-left: 15px; }
        .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; }
        .form-group { display: flex; flex-direction: column; gap: 8px; }
        .form-group label { font-weight: 600; color: #333; font-size: 14px; }
        .form-group label i { color: #710A14; margin-right: 6px; }
        .form-group select, .form-group input { padding: 12px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 14px; font-family: 'Montserrat', sans-serif; transition: all 0.3s ease; }
        .form-group select:focus, .form-group input:focus { outline: none; border-color: #710A14; box-shadow: 0 0 0 3px rgba(113,10,20,0.1); }
        .form-group select:disabled, .form-group input:disabled { background: #f5f5f5; cursor: not-allowed; }
        .btn-primary { background: #710A14; color: white; border: none; padding: 12px 24px; border-radius: 8px; font-weight: 600; cursor: pointer; transition: all 0.3s ease; display: inline-flex; align-items: center; gap: 8px; }
        .btn-primary:hover { background: #3F070B; transform: translateY(-2px); }
        .btn-secondary { background: #666; color: white; border: none; padding: 12px 24px; border-radius: 8px; font-weight: 600; cursor: pointer; transition: all 0.3s ease; display: inline-flex; align-items: center; gap: 8px; text-decoration: none; }
        .btn-secondary:hover { background: #555; transform: translateY(-2px); }
        .btn-group { display: flex; gap: 15px; margin-top: 20px; flex-wrap: wrap; }
        .mensaje-exito, .mensaje-error { padding: 12px 15px; border-radius: 8px; margin: 15px 0; display: none; }
        .mensaje-exito { background: #d4edda; color: #155724; border-left: 4px solid #28a745; }
        .mensaje-error { background: #f8d7da; color: #721c24; border-left: 4px solid #dc3545; }
        .loading { display: inline-block; width: 20px; height: 20px; border: 3px solid #f3f3f3; border-top: 3px solid #710A14; border-radius: 50%; animation: spin 1s linear infinite; margin-left: 10px; }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
        .info-text { background: #e8f0fe; padding: 12px; border-radius: 8px; font-size: 13px; color: #3F070B; margin-top: 20px; }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1><i class="fas fa-book"></i> Gestión de Previas</h1>
        <p><i class="fas fa-graduation-cap"></i> Registro y gestión de materias previas y equivalencias</p>
    </div>

    <div id="mensajeContainer"></div>

    <div class="card">
        <h2><i class="fas fa-plus-circle"></i> Registrar Nueva Previa</h2>
        
        <div class="form-grid">
            <div class="form-group">
                <label><i class="fas fa-school"></i> Curso del Alumno</label>
                <select id="curso_alumno">
                    <option value="">-- Seleccione un curso --</option>
                    <?php foreach($cursos_disponibles as $curso): 
                        $turno_texto = ($curso['turno'] == 'M') ? 'Mañana' : 'Tarde';
                        $curso_nombre = $curso['curso'] . '° "' . $curso['division'] . '" - ' . $turno_texto;
                    ?>
                        <option value="<?= $curso['ID_curso'] ?>"><?= htmlspecialchars($curso_nombre) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label><i class="fas fa-user-graduate"></i> Alumno</label>
                <select id="alumno_id" disabled>
                    <option value="">-- Primero seleccione un curso --</option>
                </select>
            </div>

            <div class="form-group">
                <label><i class="fas fa-calendar-alt"></i> Año de la Materia/Taller</label>
                <select id="anio_materia">
                    <option value="">-- Seleccione un año --</option>
                    <?php foreach($anios_materia as $anio): ?>
                        <option value="<?= $anio ?>"><?= $anio ?>° Año</option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label><i class="fas fa-book"></i> Materia/Taller</label>
                <select id="item_id" disabled>
                    <option value="">-- Primero seleccione un año --</option>
                </select>
            </div>

            <div class="form-group">
                <label><i class="fas fa-tag"></i> Tipo de Previa</label>
                <select id="tipo_previa">
                    <option value="">-- Seleccione un tipo --</option>
                    <?php
                    $query_tp = "SELECT ID_tp, nom_tp FROM tipo_previa";
                    $res_tp = mysqli_query($con, $query_tp);
                    while($tp = mysqli_fetch_array($res_tp)){
                        echo '<option value="' . $tp['ID_tp'] . '">' . htmlspecialchars($tp['nom_tp']) . '</option>';
                    }
                    ?>
                </select>
            </div>
        </div>

        <div class="btn-group">
            <button type="button" class="btn-primary" id="btnGuardar" disabled>
                <i class="fas fa-save"></i> Registrar Previa
            </button>
            <a href="listado_previas.php" class="btn-secondary">
                <i class="fas fa-list"></i> Ver Listado de Previas
            </a>
            <a href="../../recursos/panel.php" class="btn-secondary">
                <i class="fas fa-arrow-left"></i> Volver al Panel
            </a>
        </div>

        <div class="info-text">
            <i class="fas fa-info-circle"></i> 
            Las previas se registran para que los alumnos puedan rendir materias o talleres de años anteriores.
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
$(document).ready(function() {
    $('#curso_alumno').change(function() {
        var cursoId = $(this).val();
        var $alumnoSelect = $('#alumno_id');
        
        if(cursoId) {
            $alumnoSelect.prop('disabled', true).html('<option value="">Cargando alumnos...</option>');
            
            $.ajax({
                url: 'ajax_previas.php',
                type: 'POST',
                data: { action: 'get_alumnos', curso_id: cursoId },
                dataType: 'json',
                success: function(response) {
                    if(response.success) {
                        $alumnoSelect.html('<option value="">-- Seleccione un alumno --</option>');
                        $.each(response.alumnos, function(i, alumno) {
                            $alumnoSelect.append('<option value="' + alumno.DNI_U + '">' + alumno.Apellido + ', ' + alumno.Nombre + '</option>');
                        });
                        $alumnoSelect.prop('disabled', false);
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
            $alumnoSelect.prop('disabled', true).html('<option value="">-- Primero seleccione un curso --</option>');
        }
        verificarFormularioCompleto();
    });
    
    $('#anio_materia').change(function() {
        var anio = $(this).val();
        var $itemSelect = $('#item_id');
        
        if(anio) {
            $itemSelect.prop('disabled', true).html('<option value="">Cargando...</option>');
            
            $.ajax({
                url: 'ajax_previas.php',
                type: 'POST',
                data: { action: 'get_materias_y_talleres_por_anio', anio: anio },
                dataType: 'json',
                success: function(response) {
                    if(response.success) {
                        $itemSelect.html('<option value="">-- Seleccione una materia o taller --</option>');
                        $.each(response.items, function(i, item) {
                            var text = item.nombre;
                            var value = (item.tipo === 'materia') ? 'mat_' + item.id_materia : 'tal_' + item.id_taller;
                            $itemSelect.append('<option value="' + value + '" data-id-materia="' + (item.id_materia || '') + '" data-id-taller="' + (item.id_taller || '') + '" data-tipo="' + item.tipo + '">' + text + '</option>');
                        });
                        $itemSelect.prop('disabled', false);
                    } else {
                        $itemSelect.html('<option value="">' + response.message + '</option>');
                        $itemSelect.prop('disabled', true);
                    }
                },
                error: function() {
                    $itemSelect.html('<option value="">Error al cargar</option>');
                    $itemSelect.prop('disabled', true);
                }
            });
        } else {
            $itemSelect.prop('disabled', true).html('<option value="">-- Primero seleccione un año --</option>');
        }
        verificarFormularioCompleto();
    });
    
    $('#alumno_id, #item_id, #tipo_previa').change(function() {
        verificarFormularioCompleto();
    });
    
    function verificarFormularioCompleto() {
        var cursoOk = $('#curso_alumno').val() !== '';
        var alumnoOk = $('#alumno_id').val() !== '' && $('#alumno_id').prop('disabled') === false;
        var anioOk = $('#anio_materia').val() !== '';
        var itemOk = $('#item_id').val() !== '' && $('#item_id').prop('disabled') === false;
        var tipoOk = $('#tipo_previa').val() !== '';
        
        var completo = cursoOk && alumnoOk && anioOk && itemOk && tipoOk;
        $('#btnGuardar').prop('disabled', !completo);
    }
    
    $('#btnGuardar').click(function() {
        var selectedOption = $('#item_id option:selected');
        var tipoItem = selectedOption.data('tipo');
        var idMateria = selectedOption.data('id-materia');
        var idTaller = selectedOption.data('id-taller');
        
        var data = {
            action: 'guardar_previa',
            alumno_id: $('#alumno_id').val(),
            tipo_item: tipoItem,
            tipo_previa: $('#tipo_previa').val()
        };
        
        if(tipoItem === 'materia') {
            data.id_materia = idMateria;
        } else {
            data.id_taller = idTaller;
        }
        
        $('#btnGuardar').prop('disabled', true).html('<i class="fas fa-spinner fa-pulse"></i> Guardando...');
        
        $.ajax({
            url: 'ajax_previas.php',
            type: 'POST',
            data: data,
            dataType: 'json',
            success: function(response) {
                if(response.success) {
                    mostrarMensaje('success', response.message);
                    $('#alumno_id').val('');
                    $('#item_id').val('');
                    $('#tipo_previa').val('');
                    $('#anio_materia').val('');
                    $('#curso_alumno').val('');
                    $('#alumno_id').prop('disabled', true).html('<option value="">-- Primero seleccione un curso --</option>');
                    $('#item_id').prop('disabled', true).html('<option value="">-- Primero seleccione un año --</option>');
                } else {
                    mostrarMensaje('error', response.message);
                }
                $('#btnGuardar').prop('disabled', false).html('<i class="fas fa-save"></i> Registrar Previa');
            },
            error: function() {
                mostrarMensaje('error', 'Error al guardar la previa');
                $('#btnGuardar').prop('disabled', false).html('<i class="fas fa-save"></i> Registrar Previa');
            }
        });
    });
    
    function mostrarMensaje(tipo, mensaje) {
        var html = '<div class="mensaje-' + tipo + '" style="display: block;">' +
                   '<i class="fas fa-' + (tipo === 'success' ? 'check-circle' : 'exclamation-triangle') + '"></i> ' +
                   mensaje + '</div>';
        $('#mensajeContainer').html(html);
        setTimeout(function() { $('.mensaje-' + tipo).fadeOut(); }, 5000);
    }
});
</script>
</body>
</html>
<?php mysqli_close($con); ?>