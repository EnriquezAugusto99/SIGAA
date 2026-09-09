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

// Verificar permisos - Preceptor o Admin
if($_SESSION['rol'] != 'Preceptor' && $_SESSION['rol'] != 'Admin'){
    echo '<script>alert("No tiene permisos para acceder a esta seccion"); window.location="../../recursos/panel.php";</script>';
    exit();
}

include '../../recursos/conexion.php';

$error = '';
$curso_info = null;
$tiene_horario = false;
$materias_count = 0;

$es_admin = ($_SESSION['rol'] == 'Admin');
$es_preceptor = ($_SESSION['rol'] == 'Preceptor');
$dni_preceptor = $_SESSION["dni"];

// =====================================================
// OBTENER EL CURSO ACTIVO
// Para Admin: puede seleccionar curso via GET o usar selector
// Para Preceptor: usa el curso de la sesion
// =====================================================
$id_curso = 0;

if($es_admin){
    // Admin: puede venir de GET (desde selector en horario.php) o de sesion
    if(isset($_GET['id_curso']) && $_GET['id_curso'] > 0){
        $id_curso = (int)$_GET['id_curso'];
        // Guardar en sesion para mantener consistencia
        $_SESSION['curso_activo_preceptor'] = $id_curso;
    } else {
        $id_curso = $_SESSION['curso_activo_preceptor'] ?? 0;
    }
} else {
    // Preceptor: usa el curso activo de la sesion
    $id_curso = $_SESSION['curso_activo_preceptor'] ?? 0;
}

// Lista de cursos para el selector (solo para Admin)
$lista_cursos = [];
if($es_admin){
    $query_cursos = "SELECT ID_curso, curso, division, turno FROM curso ORDER BY curso, division";
    $res_cursos = mysqli_query($con, $query_cursos);
    while($curso = mysqli_fetch_assoc($res_cursos)){
        $turno_texto = $curso['turno'] == 'M' ? 'Mañana' : 'Tarde';
        $lista_cursos[] = [
            'ID_curso' => $curso['ID_curso'],
            'nombre' => $curso['curso'] . '° "' . $curso['division'] . '" - ' . $turno_texto
        ];
    }
}

if($id_curso > 0){
    // Obtener información del curso
    $query_curso = "SELECT ID_curso, curso, division, turno FROM curso WHERE ID_curso = '$id_curso'";
    $res_curso = mysqli_query($con, $query_curso);
    $curso_info = mysqli_fetch_array($res_curso);
    
    if($curso_info){
        // Verificar permisos: si es Preceptor, verificar asignación; si es Admin, puede ver todos
        if($es_preceptor){
            $query_permiso = "SELECT * FROM preceptorxcurso WHERE id_preceptor = '$dni_preceptor' AND id_curso = '$id_curso'";
            $res_permiso = mysqli_query($con, $query_permiso);
            
            if(mysqli_num_rows($res_permiso) == 0){
                $error = "No tiene permiso para acceder a este curso.";
                $curso_info = null;
            }
        }
        
        if($curso_info){
            // Contar módulos asignados en el horario
            $query_horario = "SELECT COUNT(*) as total FROM horarios WHERE id_curso = '$id_curso'";
            $res_horario = mysqli_query($con, $query_horario);
            $horario_data = mysqli_fetch_array($res_horario);
            $tiene_horario = $horario_data['total'] > 0;
            $materias_count = $horario_data['total'];
        }
    } else {
        $error = "No se encontró el curso seleccionado.";
    }
} else {
    if($es_preceptor){
        $error = "No hay un curso seleccionado. Por favor, seleccione un curso desde el panel principal.";
    }
    // Para Admin, no mostramos error si no hay curso seleccionado, solo mostramos el selector
}

$turno_texto = '';
if($curso_info){
    $turno_texto = $curso_info['turno'] == 'M' ? 'Mañana' : 'Tarde';
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../../recursos/styles.css">
    <title>Mi Curso - <?= $_SESSION['rol'] == 'Admin' ? 'Administrador' : 'Preceptor' ?></title>
</head>
<body>
<div class="container">
    <div class="header-actions">
        <a href="../../recursos/panel.php" class="btn-nav">Panel Principal</a>
        <a href="horario.php" class="btn-nav">Gestionar Horario</a>
        <?php if(!$es_admin): ?>
            <a href="../cursos/seleccionar_curso.php" class="btn-nav btn-cambiar-curso">Cambiar Curso</a>
        <?php endif; ?>
    </div>
    
    <h1>Mi Curso
        <?php if($es_admin): ?>
            <span class="admin-badge">Modo Administrador</span>
        <?php endif; ?>
    </h1>
    
    <!-- Selector de curso (solo para Admin) -->
    <?php if($es_admin && !empty($lista_cursos)): ?>
        <div class="selector-curso">
            <h3>Seleccionar Curso</h3>
            <div style="display: flex; gap: 10px; flex-wrap: wrap; align-items: center;">
                <select id="selector-curso" class="curso-select">
                    <option value="">-- Seleccione un curso --</option>
                    <?php foreach($lista_cursos as $curso): ?>
                        <option value="<?= $curso['ID_curso'] ?>" <?= ($id_curso == $curso['ID_curso']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($curso['nombre']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button onclick="irACurso()" class="btn-ir-curso">Ver Curso</button>
            </div>
        </div>
    <?php elseif($es_admin && empty($lista_cursos)): ?>
        <div class="mensaje-error">
            No hay cursos disponibles en el sistema.
        </div>
    <?php endif; ?>
    
    <?php if($error): ?>
        <div class="mensaje-error">
            <p><?= htmlspecialchars($error) ?></p>
            <?php if(!$es_admin): ?>
                <div class="cambiar-curso-link">
                    <a href="../cursos/seleccionar_curso.php" class="volver-btn">Seleccionar Curso</a>
                </div>
            <?php endif; ?>
        </div>
    <?php elseif($curso_info): ?>
        <div class="curso-card">
            <div class="curso-card-header">
                <h2><?= $curso_info['curso'] ?>° "<?= $curso_info['division'] ?>"</h2>
                <h3><?= $turno_texto ?></h3>
                <div class="estado-horario <?= $tiene_horario ? 'estado-cargado' : 'estado-sin-cargar' ?>">
                    <?php if($tiene_horario): ?>
                        Horario Cargado
                    <?php else: ?>
                        Sin Horario Cargado
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="curso-card-body">
                <div class="curso-info">
                    <div class="info-item">
                        <span class="info-label">Turno:</span>
                        <span class="info-value"><?= $turno_texto ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Modulos diarios:</span>
                        <span class="info-value">14 modulos (Mañana/Tarde)</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Módulos asignados en horario:</span>
                        <span class="info-value"><?= $materias_count ?> módulos</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Horarios disponibles:</span>
                        <span class="info-value">Mañana (7:00-12:00) y Tarde (14:00-19:00)</span>
                    </div>
                </div>
                
                <div class="curso-actions">
                    <a href="horario_detalle.php<?= $es_admin && $id_curso ? '?id_curso=' . $id_curso : '' ?>" class="btn-action btn-ver">
                        Ver Horario Completo
                    </a>
                    <a href="horario.php<?= $es_admin && $id_curso ? '?id_curso=' . $id_curso : '' ?>" class="btn-action btn-editar">
                        <?= $tiene_horario ? 'Cargar Horario' : 'Cargar Horario' ?>
                    </a>
                </div>
                
                <?php if(!$es_admin): ?>
                <div class="cambiar-curso-link" style="margin-top: 20px;">
                    <a href="../cursos/seleccionar_curso.php" class="btn-action btn-cambiar-curso" style="background: #FF9800;">
                        Cambiar Curso
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="mensaje-info" style="margin-top: 20px;">
            <p><strong>Informacion:</strong> El horario se muestra para todos los dias de la semana (Lunes a Viernes).</p>
            <p>Puede asignar hasta 7 módulos por turno (Mañana o Tarde).</p>
        </div>
        
    <?php elseif($es_admin && !$curso_info): ?>
        <div class="mensaje-info">
            <p>Seleccione un curso del menú desplegable para ver su información.</p>
        </div>
    <?php else: ?>
        <div class="mensaje-info">
            <p>No hay un curso seleccionado.</p>
            <div class="cambiar-curso-link">
                <a href="../cursos/seleccionar_curso.php" class="volver-btn">Seleccionar Curso</a>
            </div>
        </div>
    <?php endif; ?>
    
    <div style="text-align: center; margin-top: 30px;">
        <a href="../../recursos/panel.php" class="volver-btn">Volver al Panel</a>
    </div>
</div>

<script>
function irACurso() {
    const selector = document.getElementById('selector-curso');
    const cursoId = selector.value;
    if(cursoId) {
        window.location.href = 'tarjeta_horario.php?id_curso=' + cursoId;
    } else {
        alert('Por favor, seleccione un curso.');
    }
}

<?php if(isset($_SESSION['notificacion'])): ?>
document.addEventListener('DOMContentLoaded', function() {
    const notificacion = document.createElement('div');
    notificacion.style.cssText = `
        position: fixed;
        bottom: 20px;
        right: 20px;
        background: <?= $_SESSION['notificacion']['tipo'] === 'exito' ? '#4CAF50' : '#F44336' ?>;
        color: white;
        padding: 12px 20px;
        border-radius: 8px;
        z-index: 9999;
        box-shadow: 0 4px 12px rgba(0,0,0,0.3);
        transform: translateX(400px);
        transition: transform 0.3s ease;
    `;
    notificacion.innerHTML = '<?= addslashes($_SESSION['notificacion']['mensaje']) ?>';
    document.body.appendChild(notificacion);
    
    setTimeout(() => notificacion.style.transform = 'translateX(0)', 100);
    setTimeout(() => {
        notificacion.style.transform = 'translateX(400px)';
        setTimeout(() => notificacion.remove(), 400);
    }, 5000);
});
<?php unset($_SESSION['notificacion']); ?>
<?php endif; ?>
</script>

</body>
</html>