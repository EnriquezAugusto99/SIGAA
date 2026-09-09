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
$es_equipo = ($_SESSION['rol'] == 'Equipo de Orientacion');

if(!$es_admin && !$es_preceptor  && !$es_equipo){
    echo '<script>alert("No tiene permisos para acceder a esta sección"); window.location="../../recursos/panel.php";</script>';
    exit();
}

include '../../recursos/conexion.php';

$preceptor_dni = $_SESSION["dni"];

// Obtener cursos según el rol
$cursos_disponibles = [];
$curso_seleccionado = isset($_GET['curso_id']) ? (int)$_GET['curso_id'] : 0;

if($es_admin || $es_equipo){
    $query_cursos = "SELECT ID_curso, curso, division, turno FROM curso ORDER BY curso, division";
    $res_cursos = mysqli_query($con, $query_cursos);
    while($row = mysqli_fetch_assoc($res_cursos)){
        $cursos_disponibles[] = $row;
    }
} else {
    $query_cursos = "SELECT c.ID_curso, c.curso, c.division, c.turno
                     FROM curso c
                     INNER JOIN preceptorxcurso pxc ON c.ID_curso = pxc.id_curso
                     WHERE pxc.id_preceptor = '$preceptor_dni'
                     ORDER BY c.curso, c.division";
    $res_cursos = mysqli_query($con, $query_cursos);
    while($row = mysqli_fetch_assoc($res_cursos)){
        $cursos_disponibles[] = $row;
    }
}

// Verificar curso seleccionado
$curso_valido = false;
if($curso_seleccionado > 0){
    foreach($cursos_disponibles as $c){
        if($c['ID_curso'] == $curso_seleccionado){
            $curso_valido = true;
            break;
        }
    }
    if(!$curso_valido){
        $curso_seleccionado = 0;
    }
}

// Obtener alumnos con sus tutores
$alumnos = [];
if($curso_seleccionado > 0){
    $query_alumnos = "SELECT u.DNI_U, u.Nombre, u.Apellido,
                      (SELECT COUNT(*) FROM alumnoxtutor WHERE id_alumno = u.DNI_U) as total_tutores
                      FROM usuario u
                      WHERE u.id_curso = '$curso_seleccionado'
                      AND u.ID_rol = 3
                      AND u.ID_Estado = 1
                      ORDER BY u.Apellido, u.Nombre";
    $res_alumnos = mysqli_query($con, $query_alumnos);
    while($row = mysqli_fetch_assoc($res_alumnos)){
        $alumnos[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <title>Alumnos con Tutores</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Montserrat', sans-serif; background: #f5f5f5; padding: 20px; }
        .container { max-width: 1200px; margin: 0 auto; }
        .header { background: linear-gradient(135deg, #3F070B 0%, #710A14 100%); border-radius: 12px; padding: 25px 30px; margin-bottom: 30px; color: white; }
        .header h1 { font-size: 24px; margin-bottom: 8px; display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }
        .header p { opacity: 0.9; font-size: 14px; }
        .card { background: white; border-radius: 12px; padding: 25px; margin-bottom: 30px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
        .card h2 { font-size: 18px; color: #3F070B; margin-bottom: 20px; border-left: 4px solid #710A14; padding-left: 15px; }
        .filtros-container { display: flex; flex-wrap: wrap; gap: 20px; align-items: flex-end; margin-bottom: 20px; }
        .filtro-group { display: flex; flex-direction: column; gap: 8px; min-width: 200px; }
        .filtro-group label { font-weight: 600; color: #333; font-size: 13px; }
        .filtro-group label i { color: #710A14; margin-right: 6px; }
        .filtro-group select { padding: 12px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 14px; font-family: 'Montserrat', sans-serif; background: white; cursor: pointer; }
        .filtro-group select:focus { outline: none; border-color: #710A14; }
        .btn-primary { background: #710A14; color: white; border: none; padding: 12px 24px; border-radius: 8px; font-weight: 600; cursor: pointer; transition: all 0.3s ease; display: inline-flex; align-items: center; gap: 8px; }
        .btn-primary:hover { background: #3F070B; transform: translateY(-2px); }
        .btn-secondary { background: #666; color: white; border: none; padding: 12px 24px; border-radius: 8px; font-weight: 600; cursor: pointer; transition: all 0.3s ease; display: inline-flex; align-items: center; gap: 8px; text-decoration: none; }
        .btn-secondary:hover { background: #555; transform: translateY(-2px); }
        .btn-group { display: flex; gap: 15px; margin-top: 20px; flex-wrap: wrap; }
        
        .tabla-container { overflow-x: auto; margin-top: 15px; }
        .tabla { width: 100%; border-collapse: collapse; font-size: 13px; }
        .tabla th, .tabla td { border: 1px solid #ddd; padding: 12px; text-align: left; vertical-align: middle; }
        .tabla th { background: #710A14; color: white; }
        .tabla tr:hover td { background: #fdf5f5; }
        .badge-tutores { background: #2196F3; color: white; padding: 2px 10px; border-radius: 20px; font-size: 12px; }
        .badge-sin-tutores { background: #ff9800; color: white; padding: 2px 10px; border-radius: 20px; font-size: 12px; }
        .btn-ver-tutores { background: #2196F3; color: white; border: none; padding: 6px 12px; border-radius: 6px; cursor: pointer; font-size: 12px; display: inline-flex; align-items: center; gap: 5px; }
        .btn-ver-tutores:hover { background: #1976D2; }
        .sin-datos { text-align: center; padding: 40px; color: #999; }

        /* Modal */
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); justify-content: center; align-items: center; }
        .modal-content { background: white; border-radius: 12px; width: 90%; max-width: 500px; max-height: 80vh; overflow-y: auto; box-shadow: 0 4px 20px rgba(0,0,0,0.2); animation: modalFadeIn 0.3s; }
        @keyframes modalFadeIn { from { opacity: 0; transform: translateY(-30px); } to { opacity: 1; transform: translateY(0); } }
        .modal-header { background: #710A14; color: white; padding: 15px 20px; border-radius: 12px 12px 0 0; display: flex; justify-content: space-between; align-items: center; }
        .modal-header h3 { margin: 0; font-size: 18px; }
        .modal-header .close { font-size: 28px; cursor: pointer; background: none; border: none; color: white; }
        .modal-header .close:hover { opacity: 0.8; }
        .modal-body { padding: 20px; }
        .tutor-item { padding: 12px; border-bottom: 1px solid #e0e0e0; display: flex; align-items: center; gap: 12px; }
        .tutor-item:last-child { border-bottom: none; }
        .tutor-item i { color: #710A14; font-size: 20px; }
        .tutor-item .tutor-nombre { font-weight: 600; color: #333; }
        .tutor-item .tutor-dni { font-size: 12px; color: #666; }
        .tutor-item .tutor-email { font-size: 12px; color: #666; }
        .sin-tutores { text-align: center; padding: 30px; color: #999; }

        @media (max-width: 768px) {
            .filtros-container { flex-direction: column; }
            .filtro-group { width: 100%; }
            .tabla { font-size: 11px; }
            .tabla th, .tabla td { padding: 8px 6px; }
            .btn-group { flex-direction: column; }
            .btn-group .btn-primary, .btn-group .btn-secondary { width: 100%; justify-content: center; }
            .modal-content { width: 95%; }
        }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1>
            <i class="fas fa-user-graduate"></i> Alumnos con Tutores
            <?php if($es_admin): ?>
                <span style="background: #ff9800; color: #1a2a3a; padding: 4px 12px; border-radius: 20px; font-size: 12px;">Administrador</span>
            <?php else: ?>
                <span style="background: #ff9800; color: #1a2a3a; padding: 4px 12px; border-radius: 20px; font-size: 12px;">Preceptor</span>
            <?php endif; ?>
        </h1>
        <p><i class="fas fa-info-circle"></i> Visualiza qué alumnos tienen tutores asignados y cuántos</p>
    </div>

    <div class="card">
        <div class="filtros-container">
            <div class="filtro-group">
                <label><i class="fas fa-school"></i> Curso</label>
                <select id="curso_id" onchange="window.location='?curso_id='+this.value">
                    <option value="">-- Seleccione un curso --</option>
                    <?php foreach($cursos_disponibles as $curso):
                        $turno_texto = $curso['turno'] == 'M' ? 'Mañana' : 'Tarde';
                        $curso_nombre = $curso['curso'] . '° "' . $curso['division'] . '" - ' . $turno_texto;
                        $selected = ($curso_seleccionado == $curso['ID_curso']) ? 'selected' : '';
                    ?>
                        <option value="<?= $curso['ID_curso'] ?>" <?= $selected ?>>
                            <?= htmlspecialchars($curso_nombre) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <?php if($curso_seleccionado > 0): ?>
            <div class="tabla-container">
                <table class="tabla">
                    <thead>
                        <tr>
                            <th>Alumno</th>
                            <th>DNI</th>
                            <th>Total Tutores</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(!empty($alumnos)): 
                            foreach($alumnos as $alumno): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($alumno['Apellido'] . ', ' . $alumno['Nombre']) ?></strong></td>
                                    <td><?= $alumno['DNI_U'] ?></td>
                                    <td>
                                        <?php if($alumno['total_tutores'] > 0): ?>
                                            <span class="badge-tutores"><?= $alumno['total_tutores'] ?> tutor(es)</span>
                                        <?php else: ?>
                                            <span class="badge-sin-tutores">Sin tutores</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if($alumno['total_tutores'] > 0): ?>
                                            <button class="btn-ver-tutores" onclick="verTutores(<?= $alumno['DNI_U'] ?>, '<?= htmlspecialchars($alumno['Apellido'] . ', ' . $alumno['Nombre']) ?>')">
                                                <i class="fas fa-eye"></i> Ver tutores
                                            </button>
                                        <?php else: ?>
                                            <button class="btn-ver-tutores" style="background: #999; cursor: not-allowed;" disabled>
                                                <i class="fas fa-eye"></i> Sin tutores
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; 
                        else: ?>
                            <tr><td colspan="4" class="sin-datos">No hay alumnos activos en este curso.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="sin-datos">
                <i class="fas fa-hand-pointer" style="font-size: 48px; margin-bottom: 15px; display: block;"></i>
                Seleccione un curso para ver los alumnos y sus tutores
            </div>
        <?php endif; ?>
    </div>

    <div class="btn-group">
        <a href="../../recursos/panel.php" class="btn-secondary">
            <i class="fas fa-arrow-left"></i> Volver al Panel
        </a>
    </div>
</div>

<!-- Modal para ver tutores -->
<div id="modalTutores" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="modalTitulo">Tutores</h3>
            <button class="close" onclick="cerrarModal()">&times;</button>
        </div>
        <div class="modal-body" id="modalBody">
            <div style="text-align: center; padding: 20px;">
                <div class="spinner" style="display: inline-block; width: 30px; height: 30px; border: 3px solid #f3f3f3; border-top: 3px solid #710A14; border-radius: 50%; animation: spin 1s linear infinite;"></div>
                <p>Cargando...</p>
            </div>
        </div>
    </div>
</div>

<style>
    .spinner { display: inline-block; width: 30px; height: 30px; border: 3px solid #f3f3f3; border-top: 3px solid #710A14; border-radius: 50%; animation: spin 1s linear infinite; }
    @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
</style>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
function verTutores(alumnoDni, alumnoNombre) {
    $('#modalTitulo').text('Tutores de ' + alumnoNombre);
    $('#modalBody').html('<div style="text-align: center; padding: 20px;"><div class="spinner"></div><p>Cargando...</p></div>');
    $('#modalTutores').css('display', 'flex');
    
    $.ajax({
        url: 'ajax_tutores_alumno.php',
        type: 'POST',
        data: { alumno_dni: alumnoDni },
        dataType: 'json',
        success: function(response) {
            if(response.success && response.tutores.length > 0) {
                let html = '';
                response.tutores.forEach(function(tutor) {
                    html += `
                        <div class="tutor-item">
                            <i class="fas fa-user-tie"></i>
                            <div>
                                <div class="tutor-nombre">${tutor.Apellido}, ${tutor.Nombre}</div>
                                <div class="tutor-dni">DNI: ${tutor.DNI_U}</div>
                                <div class="tutor-email">${tutor.email || 'Sin email'}</div>
                            </div>
                        </div>
                    `;
                });
                $('#modalBody').html(html);
            } else {
                $('#modalBody').html('<div class="sin-tutores"><i class="fas fa-user-graduate" style="font-size: 48px; margin-bottom: 15px; display: block;"></i>Este alumno no tiene tutores asignados.</div>');
            }
        },
        error: function() {
            $('#modalBody').html('<div class="sin-tutores"><i class="fas fa-exclamation-triangle" style="font-size: 48px; margin-bottom: 15px; display: block;"></i>Error al cargar los tutores.</div>');
        }
    });
}

function cerrarModal() {
    $('#modalTutores').css('display', 'none');
}

$(window).click(function(event) {
    if ($(event.target).is('#modalTutores')) {
        cerrarModal();
    }
});
</script>
</body>
</html>
<?php mysqli_close($con); ?>