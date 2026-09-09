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

if(!$es_admin && !$es_preceptor){
    echo '<script>alert("No tiene permisos para gestionar inasistencias"); window.location="../../recursos/panel.php";</script>';
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

// Obtener trimestres
$trimestres = [];
$query_trimestres = "SELECT id_trimestre, trimestre FROM trimestres ORDER BY id_trimestre";
$res_trimestres = mysqli_query($con, $query_trimestres);
while($row = mysqli_fetch_array($res_trimestres)){
    $trimestres[] = $row;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Inasistencias - EPET N° 34</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Montserrat', sans-serif; background: #f5f5f5; padding: 30px 20px; }
        .container { max-width: 1400px; margin: 0 auto; }
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
        .info-text { background: #e8f0fe; padding: 12px; border-radius: 8px; font-size: 13px; color: #3F070B; margin-top: 20px; }
        .alumnos-table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        .alumnos-table th { background: #710A14; color: white; padding: 12px; text-align: left; }
        .alumnos-table td { padding: 12px; border-bottom: 1px solid #e0e0e0; vertical-align: middle; }
        .alumnos-table tr:hover td { background: #fdf5f5; }
        .cantidad-input { width: 80px; padding: 8px; text-align: center; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 14px; }
        .radio-group { display: flex; gap: 15px; align-items: center; flex-wrap: wrap; }
        .radio-group label { display: flex; align-items: center; gap: 5px; font-weight: normal; cursor: pointer; }
        .tipo-falta-group { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; }
        .tipo-falta-group label { display: flex; align-items: center; gap: 6px; font-weight: normal; cursor: pointer; padding: 6px 12px; border-radius: 20px; transition: all 0.2s ease; background: #f0f0f0; }
        .tipo-falta-group label:hover { background: #e0e0e0; }
        .tipo-falta-group input[type="radio"] { margin: 0; width: 16px; height: 16px; }
        .btn-cargar { margin-top: 20px; width: 100%; justify-content: center; }
        .loading { display: inline-block; width: 20px; height: 20px; border: 3px solid #f3f3f3; border-top: 3px solid #710A14; border-radius: 50%; animation: spin 1s linear infinite; margin-left: 10px; }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
        .alumno-row { transition: all 0.3s ease; }
        .alumno-row.seleccionado { background: #fff3cd !important; }
        .badge-completa { background: #dc3545; color: white; padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; display: inline-flex; align-items: center; gap: 5px; }
        .badge-media { background: #ff9800; color: white; padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; display: inline-flex; align-items: center; gap: 5px; }
        .badge-cuarto { background: #2196f3; color: white; padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; display: inline-flex; align-items: center; gap: 5px; }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1><i class="fas fa-calendar-times"></i> Gestión de Inasistencias</h1>
        <p><i class="fas fa-graduation-cap"></i> Registro de inasistencias (completas, medias y cuartos de falta)</p>
    </div>

    <div id="mensajeContainer"></div>

    <div class="card">
        <h2><i class="fas fa-filter"></i> Seleccionar Curso y Trimestre</h2>
        
        <div class="form-grid">
            <div class="form-group">
                <label><i class="fas fa-school"></i> Curso</label>
                <select id="curso_select">
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
                <label><i class="fas fa-chart-line"></i> Trimestre</label>
                <select id="trimestre_select">
                    <option value="">-- Seleccione un trimestre --</option>
                    <?php foreach($trimestres as $trimestre): ?>
                        <option value="<?= $trimestre['id_trimestre'] ?>"><?= htmlspecialchars($trimestre['trimestre']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
    </div>

    <div class="card" id="alumnosCard" style="display: none;">
        <h2><i class="fas fa-users"></i> Registrar Inasistencias</h2>
        <div style="overflow-x: auto;">
            <table class="alumnos-table">
                <thead>
                    <tr>
                        <th>Alumno</th>
                        <th>DNI</th>
                        <th>Cantidad</th>
                        <th>Tipo de Falta</th>
                        <th>Justificada</th>
                    </tr>
                </thead>
                <tbody id="alumnos_tbody">
                </tbody>
            </table>
        </div>
        
        <button type="button" class="btn-primary btn-cargar" id="btnRegistrarInasistencias" disabled>
            <i class="fas fa-save"></i> Registrar Inasistencias
        </button>

        <div class="info-text">
            <i class="fas fa-info-circle"></i> 
            <strong>Falta completa:</strong> 1 falta | <strong>Media falta:</strong> 0.5 falta | <strong>Cuarto de falta:</strong> 0.25 falta<br>
            Seleccione la cantidad y el tipo de falta para cada alumno.
        </div>
    </div>

    <div class="btn-group">
        <a href="listado_inasistencias.php" class="btn-secondary">
            <i class="fas fa-list"></i> Ver Listado de Inasistencias
        </a>
        <a href="../../recursos/panel.php" class="btn-secondary">
            <i class="fas fa-arrow-left"></i> Volver al Panel
        </a>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
$(document).ready(function() {
    $('#curso_select, #trimestre_select').change(function() {
        cargarAlumnos();
    });
    
    function cargarAlumnos() {
        var cursoId = $('#curso_select').val();
        var trimestreId = $('#trimestre_select').val();
        
        if(cursoId && trimestreId) {
            $('#alumnosCard').show();
            $('#alumnos_tbody').html('<tr><td colspan="5" style="text-align: center;"><i class="fas fa-spinner fa-pulse"></i> Cargando alumnos...</td></tr>');
            $('#btnRegistrarInasistencias').prop('disabled', true);
            
            $.ajax({
                url: 'ajax_inasistencias.php',
                type: 'POST',
                data: { action: 'get_alumnos_curso', curso_id: cursoId },
                dataType: 'json',
                success: function(response) {
                    if(response.success) {
                        if(response.alumnos.length > 0) {
                            var html = '';
                            $.each(response.alumnos, function(i, alumno) {
                                html += '<tr class="alumno-row" data-dni="' + alumno.DNI_U + '">';
                                html += '<td><strong>' + alumno.Apellido + '</strong>, ' + alumno.Nombre + '</td>';
                                html += '<td>' + alumno.DNI_U + '</td>';
                                html += '<td><input type="number" class="cantidad-input" id="cant_' + alumno.DNI_U + '" min="0" max="50" value="0" style="width: 80px;"></td>';
                                html += '<td>';
                                html += '<div class="tipo-falta-group">';
                                html += '<label><input type="radio" name="tipo_falta_' + alumno.DNI_U + '" value="completa" checked> <i class="fas fa-circle"></i> Completa (1)</label>';
                                html += '<label><input type="radio" name="tipo_falta_' + alumno.DNI_U + '" value="media"> <i class="fas fa-half-alt"></i> Media (0.5)</label>';
                                html += '<label><input type="radio" name="tipo_falta_' + alumno.DNI_U + '" value="cuarto"> <i class="fas fa-chart-simple"></i> Cuarto (0.25)</label>';
                                html += '</div>';
                                html += '</td>';
                                html += '<td>';
                                html += '<div class="radio-group">';
                                html += '<label><input type="radio" name="justificada_' + alumno.DNI_U + '" value="0" checked> <i class="fas fa-clock"></i> Injustificada</label>';
                                html += '<label><input type="radio" name="justificada_' + alumno.DNI_U + '" value="1"> <i class="fas fa-check-circle"></i> Justificada</label>';
                                html += '</div>';
                                html += '</td>';
                                html += '</tr>';
                            });
                            $('#alumnos_tbody').html(html);
                            
                            $('.cantidad-input').on('input', function() {
                                verificarSiHayInasistencias();
                            });
                            
                            verificarSiHayInasistencias();
                        } else {
                            $('#alumnos_tbody').html('<tr><td colspan="5" style="text-align: center;">No hay alumnos en este curso</td></tr>');
                            $('#btnRegistrarInasistencias').prop('disabled', true);
                        }
                    } else {
                        $('#alumnos_tbody').html('<tr><td colspan="5" style="text-align: center;">' + response.message + '</td></tr>');
                        $('#btnRegistrarInasistencias').prop('disabled', true);
                    }
                },
                error: function() {
                    $('#alumnos_tbody').html('<tr><td colspan="5" style="text-align: center;">Error al cargar alumnos</td></tr>');
                    $('#btnRegistrarInasistencias').prop('disabled', true);
                }
            });
        } else {
            $('#alumnosCard').hide();
        }
    }
    
    function verificarSiHayInasistencias() {
        var hayInasistencias = false;
        $('.cantidad-input').each(function() {
            if(parseInt($(this).val()) > 0) {
                hayInasistencias = true;
                $(this).closest('tr').addClass('seleccionado');
            } else {
                $(this).closest('tr').removeClass('seleccionado');
            }
        });
        $('#btnRegistrarInasistencias').prop('disabled', !hayInasistencias);
    }
    
    $('#btnRegistrarInasistencias').click(function() {
        var cursoId = $('#curso_select').val();
        var trimestreId = $('#trimestre_select').val();
        var inasistencias = [];
        
        $('.alumno-row').each(function() {
            var dni = $(this).data('dni');
            var cantidad = parseInt($('#cant_' + dni).val());
            var tipoFalta = $('input[name="tipo_falta_' + dni + '"]:checked').val();
            var justificada = $('input[name="justificada_' + dni + '"]:checked').val();
            
            if(cantidad > 0) {
                inasistencias.push({
                    dni: dni,
                    cantidad: cantidad,
                    tipo_falta: tipoFalta,
                    justificada: justificada,
                    curso_id: cursoId
                });
            }
        });
        
        if(inasistencias.length === 0) {
            mostrarMensaje('error', 'No hay inasistencias para registrar');
            return;
        }
        
        $('#btnRegistrarInasistencias').prop('disabled', true).html('<i class="fas fa-spinner fa-pulse"></i> Registrando...');
        
        $.ajax({
            url: 'ajax_inasistencias.php',
            type: 'POST',
            data: {
                action: 'registrar_inasistencias',
                inasistencias: JSON.stringify(inasistencias),
                trimestre: trimestreId
            },
            dataType: 'json',
            success: function(response) {
                if(response.success) {
                    mostrarMensaje('success', response.message);
                    $('.cantidad-input').val(0);
                    $('input[name^="tipo_falta_"]').prop('checked', function() {
                        return $(this).val() === 'completa';
                    });
                    $('input[name^="justificada_"]').prop('checked', function() {
                        return $(this).val() === '0';
                    });
                    $('.alumno-row').removeClass('seleccionado');
                    $('#btnRegistrarInasistencias').prop('disabled', true);
                } else {
                    mostrarMensaje('error', response.message);
                    $('#btnRegistrarInasistencias').prop('disabled', false).html('<i class="fas fa-save"></i> Registrar Inasistencias');
                }
            },
            error: function(xhr, status, error) {
                mostrarMensaje('error', 'Error al registrar: ' + error);
                $('#btnRegistrarInasistencias').prop('disabled', false).html('<i class="fas fa-save"></i> Registrar Inasistencias');
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