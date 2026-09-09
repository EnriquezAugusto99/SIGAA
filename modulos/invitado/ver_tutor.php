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

// Verificar permisos - Admin o Invitado
$es_admin = ($_SESSION['rol'] == 'Admin');
$es_invitado = ($_SESSION['rol'] == 'Invitado');

if(!$es_admin && !$es_invitado){
    echo '<script>alert("No tiene permisos para acceder a esta sección"); window.location="../../recursos/panel.php";</script>';
    exit();
}

include '../../recursos/conexion.php';

$tutor_id = $_GET['id'] ?? 0;

if(!$tutor_id){
    echo '<script>alert("ID de tutor no válido"); window.location="listado_tutores.php";</script>';
    exit();
}

// Obtener datos del tutor
$query_tutor = "SELECT u.DNI_U, u.Nombre, u.Apellido, u.email
                FROM usuario u
                WHERE u.DNI_U = '$tutor_id' AND u.ID_rol = 8";
$res_tutor = mysqli_query($con, $query_tutor);
$tutor = mysqli_fetch_assoc($res_tutor);

if(!$tutor){
    echo '<script>alert("Tutor no encontrado"); window.location="listado_tutores.php";</script>';
    exit();
}

// Obtener alumnos asignados
$query_alumnos = "SELECT u.DNI_U, u.Nombre, u.Apellido, u.id_curso, c.curso, c.division, c.turno
                  FROM alumnoxtutor at
                  INNER JOIN usuario u ON at.id_alumno = u.DNI_U
                  LEFT JOIN curso c ON u.id_curso = c.ID_curso
                  WHERE at.id_tutor = '$tutor_id'
                  ORDER BY u.Apellido, u.Nombre";
$res_alumnos = mysqli_query($con, $query_alumnos);
$alumnos = [];
while($row = mysqli_fetch_assoc($res_alumnos)){
    $alumnos[] = $row;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <title>Detalles del Tutor</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Montserrat', sans-serif; background: #f5f5f5; padding: 20px; }
        .container { max-width: 900px; margin: 0 auto; }
        .header { background: linear-gradient(135deg, #3F070B 0%, #710A14 100%); border-radius: 12px; padding: 25px 30px; margin-bottom: 30px; color: white; }
        .header h1 { font-size: 24px; margin-bottom: 8px; display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }
        .header p { opacity: 0.9; font-size: 14px; }
        .card { background: white; border-radius: 12px; padding: 25px; margin-bottom: 30px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
        .card h2 { font-size: 18px; color: #3F070B; margin-bottom: 20px; border-left: 4px solid #710A14; padding-left: 15px; }
        .info-tutor { background: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 20px; }
        .info-tutor p { margin: 8px 0; }
        .info-tutor strong { color: #710A14; }
        .badge-role { background: #ff9800; color: white; padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: bold; margin-left: 10px; }
        .alumno-item { background: #f8f9fa; padding: 15px; border-radius: 8px; margin-bottom: 10px; border-left: 4px solid #710A14; }
        .alumno-item .curso { font-size: 12px; color: #666; }
        .btn-secondary { background: #666; color: white; border: none; padding: 12px 24px; border-radius: 8px; font-weight: 600; cursor: pointer; transition: all 0.3s ease; display: inline-flex; align-items: center; gap: 8px; text-decoration: none; }
        .btn-secondary:hover { background: #555; transform: translateY(-2px); }
        .btn-group { display: flex; gap: 15px; margin-top: 20px; flex-wrap: wrap; }
        .sin-datos { text-align: center; padding: 40px; color: #999; }
        @media (max-width: 768px) {
            .btn-group { flex-direction: column; }
            .btn-group .btn-secondary { width: 100%; justify-content: center; }
        }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1>
            <i class="fas fa-user-graduate"></i> Detalles del Tutor
            <?php if($es_admin): ?>
                <span class="badge-role">Administrador</span>
            <?php else: ?>
                <span class="badge-role">Mesa de Entrada</span>
            <?php endif; ?>
        </h1>
        <p><i class="fas fa-info-circle"></i> Información del tutor y sus hijos asignados</p>
    </div>

    <div class="card">
        <h2><i class="fas fa-id-card"></i> Datos del Tutor</h2>
        
        <div class="info-tutor">
            <p><strong>DNI:</strong> <?= $tutor['DNI_U'] ?></p>
            <p><strong>Nombre completo:</strong> <?= htmlspecialchars($tutor['Apellido'] . ', ' . $tutor['Nombre']) ?></p>
            <p><strong>Email:</strong> <?= htmlspecialchars($tutor['email'] ?: 'No registrado') ?></p>
            <p><strong>Hijos asignados:</strong> <span class="badge-role" style="background: #28a745;"><?= count($alumnos) ?> hijo(s)</span></p>
        </div>
    </div>

    <div class="card">
        <h2><i class="fas fa-child"></i> Hijos Asignados</h2>
        
        <?php if(!empty($alumnos)): ?>
            <?php foreach($alumnos as $alumno): 
                $turno_texto = $alumno['turno'] == 'M' ? 'Mañana' : 'Tarde';
                $curso_texto = $alumno['curso'] ? $alumno['curso'] . '° "' . $alumno['division'] . '" - ' . $turno_texto : 'Sin curso asignado';
            ?>
                <div class="alumno-item">
                    <strong><?= htmlspecialchars($alumno['Apellido'] . ', ' . $alumno['Nombre']) ?></strong>
                    <span class="curso">| DNI: <?= $alumno['DNI_U'] ?> | Curso: <?= $curso_texto ?></span>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="sin-datos">
                <i class="fas fa-user-graduate" style="font-size: 48px; margin-bottom: 15px; display: block;"></i>
                Este tutor no tiene hijos asignados aún.
            </div>
        <?php endif; ?>
    </div>

    <div class="btn-group">
        <a href="listado_tutores.php" class="btn-secondary">
            <i class="fas fa-arrow-left"></i> Volver al Listado
        </a>
        <a href="../../recursos/panel.php" class="btn-secondary">
            <i class="fas fa-home"></i> Volver al Panel
        </a>
    </div>
</div>
</body>
</html>
<?php mysqli_close($con); ?>