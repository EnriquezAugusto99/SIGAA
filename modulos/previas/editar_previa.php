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
    echo '<script>alert("No tiene permisos para gestionar previas"); window.location="../../recursos/panel.php";</script>';
    exit();
}

include '../../recursos/conexion.php';

$previa_id = $_GET['id'] ?? 0;

if(!$previa_id){
    echo '<script>alert("ID de previa no válido"); window.location="listado_previas.php";</script>';
    exit();
}

// Obtener datos de la previa
$query = "SELECT p.*, u.Nombre, u.Apellido, u.id_curso, 
                 m.Nom_materia, t.nombre as nom_taller, tp.nom_tp,
                 CASE 
                     WHEN p.ID_materia IS NOT NULL THEN (SELECT curso FROM curso WHERE ID_curso = (SELECT id_curso FROM materia WHERE ID_materia = p.ID_materia))
                     WHEN p.ID_taller IS NOT NULL THEN 
                         CASE WHEN t.anio_taller = 'I' THEN 1 ELSE 2 END
                 END as anio_item,
                 CASE 
                     WHEN p.ID_materia IS NOT NULL THEN 'materia'
                     ELSE 'taller'
                 END as tipo_item
          FROM previas p
          INNER JOIN usuario u ON p.DNI_U = u.DNI_U
          INNER JOIN tipo_previa tp ON p.ID_tp = tp.ID_tp
          LEFT JOIN materia m ON p.ID_materia = m.ID_materia
          LEFT JOIN talleres t ON p.ID_taller = t.ID_taller
          WHERE p.ID_previas = '$previa_id'";
$res = mysqli_query($con, $query);
$previa = mysqli_fetch_array($res);

if(!$previa){
    echo '<script>alert("Previa no encontrada"); window.location="listado_previas.php";</script>';
    exit();
}

// Verificar permisos
if(!$es_admin){
    $preceptor_dni = $_SESSION["dni"];
    $query_permiso = "SELECT * FROM preceptorxcurso WHERE id_preceptor = '$preceptor_dni' AND id_curso = '{$previa['id_curso']}'";
    $res_permiso = mysqli_query($con, $query_permiso);
    if(mysqli_num_rows($res_permiso) == 0){
        echo '<script>alert("No tiene permisos para editar esta previa"); window.location="listado_previas.php";</script>';
        exit();
    }
}

$tipo_previa_actual = $previa['ID_tp'];
$aprobado_actual = $previa['Aprobado'];
$nombre_item = $previa['Nom_materia'] ?? $previa['nom_taller'];
$badge_taller = ($previa['tipo_item'] == 'taller') ? '<span class="badge-taller">Taller</span>' : '';
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Previa - EPET N° 34</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Montserrat', sans-serif; background: #f5f5f5; padding: 30px 20px; }
        .container { max-width: 800px; margin: 0 auto; }
        .header { background: linear-gradient(135deg, #3F070B 0%, #710A14 100%); border-radius: 12px; padding: 25px 30px; margin-bottom: 30px; color: white; }
        .header h1 { font-size: 28px; margin-bottom: 8px; display: flex; align-items: center; gap: 12px; }
        .card { background: white; border-radius: 12px; padding: 25px; margin-bottom: 30px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
        .info-alumno { background: #f0f0f0; padding: 15px; border-radius: 8px; margin-bottom: 20px; }
        .info-alumno p { margin: 8px 0; }
        .info-materia { background: #f0f0f0; padding: 15px; border-radius: 8px; margin-bottom: 20px; }
        .info-materia p { margin: 8px 0; }
        .badge-anio { background: #710A14; color: white; padding: 2px 10px; border-radius: 20px; font-size: 12px; display: inline-block; margin-left: 8px; }
        .badge-taller { background: #2196F3; color: white; padding: 2px 8px; border-radius: 12px; font-size: 10px; font-weight: 500; display: inline-block; margin-left: 8px; vertical-align: middle; }
        .form-group { display: flex; flex-direction: column; gap: 8px; margin-bottom: 20px; }
        .form-group label { font-weight: 600; color: #333; font-size: 14px; }
        .form-group label i { color: #710A14; margin-right: 6px; }
        .form-group select { padding: 12px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 14px; font-family: 'Montserrat', sans-serif; }
        .form-group select:focus { outline: none; border-color: #710A14; }
        .btn-primary { background: #710A14; color: white; border: none; padding: 12px 24px; border-radius: 8px; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 8px; }
        .btn-primary:hover { background: #3F070B; }
        .btn-secondary { background: #666; color: white; border: none; padding: 12px 24px; border-radius: 8px; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 8px; text-decoration: none; }
        .btn-group { display: flex; gap: 15px; margin-top: 20px; flex-wrap: wrap; }
        .mensaje-exito, .mensaje-error { padding: 12px 15px; border-radius: 8px; margin: 15px 0; display: none; }
        .mensaje-exito { background: #d4edda; color: #155724; border-left: 4px solid #28a745; }
        .mensaje-error { background: #f8d7da; color: #721c24; border-left: 4px solid #dc3545; }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1><i class="fas fa-edit"></i> Editar Previa</h1>
        <p><i class="fas fa-graduation-cap"></i> Modificar información de la previa</p>
    </div>

    <div id="mensajeContainer"></div>

    <div class="card">
        <div class="info-alumno">
            <h3 style="margin-bottom: 12px; color: #3F070B;"><i class="fas fa-user-graduate"></i> Datos del Alumno</h3>
            <p><strong><i class="fas fa-user"></i> Alumno:</strong> <?= htmlspecialchars($previa['Apellido'] . ', ' . $previa['Nombre']) ?></p>
            <p><strong><i class="fas fa-id-card"></i> DNI:</strong> <?= $previa['DNI_U'] ?></p>
        </div>

        <div class="info-materia">
            <h3 style="margin-bottom: 12px; color: #3F070B;"><i class="fas fa-book"></i> Ítem que Adeuda</h3>
            <p>
                <strong><?= htmlspecialchars($nombre_item) ?> <?= $badge_taller ?></strong>
                <span class="badge-anio"><?= $previa['anio_item'] ?>° Año</span>
            </p>
        </div>

        <form id="formEditarPrevia">
            <input type="hidden" name="previa_id" value="<?= $previa_id ?>">
            <input type="hidden" name="action" value="editar_previa">
            
            <div class="form-group">
                <label><i class="fas fa-tag"></i> Tipo de Previa</label>
                <select name="tipo_previa" id="tipo_previa">
                    <?php
                    $query_tp = "SELECT ID_tp, nom_tp FROM tipo_previa";
                    $res_tp = mysqli_query($con, $query_tp);
                    while($tp = mysqli_fetch_array($res_tp)){
                        $selected = ($tp['ID_tp'] == $tipo_previa_actual) ? 'selected' : '';
                        echo '<option value="' . $tp['ID_tp'] . '" ' . $selected . '>' . htmlspecialchars($tp['nom_tp']) . '</option>';
                    }
                    ?>
                </select>
            </div>

            <div class="form-group">
                <label><i class="fas fa-check-circle"></i> Estado</label>
                <select name="aprobado" id="aprobado">
                    <option value="0" <?= ($aprobado_actual == 0) ? 'selected' : '' ?>>Pendiente</option>
                    <option value="1" <?= ($aprobado_actual == 1) ? 'selected' : '' ?>>Aprobado</option>
                </select>
            </div>

            <div class="btn-group">
                <button type="submit" class="btn-primary"><i class="fas fa-save"></i> Guardar Cambios</button>
                <a href="listado_previas.php" class="btn-secondary"><i class="fas fa-arrow-left"></i> Cancelar</a>
            </div>
        </form>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
$(document).ready(function() {
    $('#formEditarPrevia').submit(function(e) {
        e.preventDefault();
        
        var data = {
            action: 'editar_previa',
            previa_id: $('input[name="previa_id"]').val(),
            tipo_previa: $('#tipo_previa').val(),
            aprobado: $('#aprobado').val()
        };
        
        console.log("Enviando datos:", data);
        
        $('button[type="submit"]').prop('disabled', true).html('<i class="fas fa-spinner fa-pulse"></i> Guardando...');
        
        $.ajax({
            url: 'ajax_previas.php',
            type: 'POST',
            data: data,
            dataType: 'json',
            success: function(response) {
                console.log("Respuesta:", response);
                if(response.success) {
                    mostrarMensaje('success', response.message);
                    setTimeout(function() {
                        window.location.href = 'listado_previas.php';
                    }, 1500);
                } else {
                    mostrarMensaje('error', response.message);
                    $('button[type="submit"]').prop('disabled', false).html('<i class="fas fa-save"></i> Guardar Cambios');
                }
            },
            error: function(xhr, status, error) {
                console.error("Error:", error);
                console.error("Respuesta:", xhr.responseText);
                mostrarMensaje('error', 'Error al guardar los cambios: ' + error);
                $('button[type="submit"]').prop('disabled', false).html('<i class="fas fa-save"></i> Guardar Cambios');
            }
        });
    });
    
    function mostrarMensaje(tipo, mensaje) {
        var html = '<div class="mensaje-' + tipo + '" style="display: block;">' +
                   '<i class="fas fa-' + (tipo === 'success' ? 'check-circle' : 'exclamation-triangle') + '"></i> ' +
                   mensaje + '</div>';
        $('#mensajeContainer').html(html);
        setTimeout(function() {
            $('.mensaje-' + tipo).fadeOut();
        }, 5000);
    }
});
</script>
</body>
</html>
<?php mysqli_close($con); ?>