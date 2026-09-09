<?php
date_default_timezone_set('America/Argentina/Buenos_Aires');
session_start();

if(isset($_SESSION["dni"]) && (time() - $_SESSION["ultima_actividad"] > 18000)){
    echo '<script>alert("Se venció el tiempo de la sesion"); window.location="../../index.php";</script>';
    exit();
}

if(!isset($_SESSION["dni"])){
    echo '<script>alert("Se venció el tiempo de la sesion"); window.location="../../index.php";</script>';
    exit();
}

$rol = $_SESSION['rol'];
if($rol != 'Admin' && $rol != 'Preceptor'){
    echo '<script>alert("No tiene permisos para enviar novedades"); window.location="../../recursos/panel.php";</script>';
    exit();
}

include '../../recursos/conexion.php';

$es_admin = ($rol == 'Admin');
$dni_emisor = $_SESSION['dni'];
$error = '';
$exito = false;

$cursos_disponibles = [];
if($es_admin){
    $query = "SELECT ID_curso, CONCAT(curso, '° ', division, ' - ', IF(turno='M','Mañana','Tarde')) as nombre FROM curso ORDER BY curso, division";
    $res = mysqli_query($con, $query);
    while($row = mysqli_fetch_assoc($res)){
        $cursos_disponibles[] = $row;
    }
} else {
    $query = "SELECT c.ID_curso, CONCAT(c.curso, '° ', c.division, ' - ', IF(c.turno='M','Mañana','Tarde')) as nombre
              FROM curso c
              INNER JOIN preceptorxcurso pc ON c.ID_curso = pc.id_curso
              WHERE pc.id_preceptor = '$dni_emisor'";
    $res = mysqli_query($con, $query);
    while($row = mysqli_fetch_assoc($res)){
        $cursos_disponibles[] = $row;
    }
}

if($_SERVER['REQUEST_METHOD'] == 'POST'){
    $titulo = trim($_POST['titulo']);
    $descripcion = trim($_POST['descripcion']);
    $fecha_vencimiento = $_POST['fecha_vencimiento'];
    $tipo = $_POST['tipo_destinatario'];
    $roles_seleccionados = isset($_POST['roles']) ? $_POST['roles'] : [];
    $cursos_seleccionados = isset($_POST['cursos']) ? $_POST['cursos'] : [];

    if(empty($titulo)) $error = "El título es obligatorio.";
    elseif(empty($descripcion)) $error = "La descripción es obligatoria.";
    elseif(empty($fecha_vencimiento)) $error = "La fecha de vencimiento es obligatoria.";
    elseif($fecha_vencimiento < date('Y-m-d')) $error = "La fecha de vencimiento no puede ser anterior a hoy.";

    if($tipo == 'roles' && empty($roles_seleccionados)) $error = "Debe seleccionar al menos un rol.";
    if($tipo == 'cursos' && empty($cursos_seleccionados)) $error = "Debe seleccionar al menos un curso.";

    if(!$es_admin){
        if($tipo == 'todos') $error = "El preceptor no puede enviar a 'todos los usuarios'.";
        if(in_array(6, $roles_seleccionados)) $error = "El preceptor no puede enviar mensajes al rol Administrador.";
    }

    if(empty($error)){
        $fecha_emision = date('Y-m-d H:i:s');
        $query = "INSERT INTO novedades (titulo_novedad, descripcion_novedad, id_emisor, emision_novedad, vencimiento_novedad)
                  VALUES ('$titulo', '$descripcion', '$dni_emisor', '$fecha_emision', '$fecha_vencimiento')";
        if(mysqli_query($con, $query)){
            $id_novedad = mysqli_insert_id($con);
            if($tipo == 'roles'){
                foreach($roles_seleccionados as $id_rol){
                    $ins = "INSERT INTO novedades_roles (id_novedad, ID_rol) VALUES ($id_novedad, $id_rol)";
                    mysqli_query($con, $ins);
                }
            } elseif($tipo == 'cursos'){
                foreach($cursos_seleccionados as $id_curso){
                    $ins = "INSERT INTO novedades_cursos (id_novedad, ID_curso) VALUES ($id_novedad, $id_curso)";
                    mysqli_query($con, $ins);
                }
            }
            $exito = true;
        } else {
            $error = "Error al guardar la novedad: " . mysqli_error($con);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Enviar Novedad - Sistema Escolar</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --dark-red: #3F070B;
            --deep-crimson: #710A14;
            --dark-burgundy: #180605;
            --dusty-rose: #818582;
            --rustic-red: #8F3C45;
            --light-bg: #f5f5f5;
            --card-bg: #ffffff;
            --border-color: #e0e0e0;
            --text-dark: #2c2c2c;
            --text-muted: #666666;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Montserrat', sans-serif;
            background: linear-gradient(135deg, var(--light-bg) 0%, #e8e8e8 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }

        .container {
            max-width: 700px;
            width: 100%;
            margin: 0 auto;
        }

        .card {
            background: var(--card-bg);
            border-radius: 20px;
            box-shadow: 0 15px 40px rgba(0,0,0,0.15);
            overflow: hidden;
            transition: transform 0.3s ease;
        }

        .card:hover {
            transform: translateY(-5px);
        }

        .card-header {
            background: linear-gradient(135deg, var(--deep-crimson) 0%, var(--dark-red) 100%);
            padding: 30px;
            text-align: center;
            color: white;
        }

        .card-header h1 {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
        }

        .card-header h1 i {
            font-size: 32px;
        }

        .card-header p {
            font-size: 14px;
            opacity: 0.9;
        }

        .card-body {
            padding: 30px;
        }

        .form-group {
            margin-bottom: 25px;
        }

        .form-group label {
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 600;
            color: var(--dark-burgundy);
            margin-bottom: 10px;
            font-size: 14px;
        }

        .form-group label i {
            color: var(--deep-crimson);
            font-size: 16px;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 14px 16px;
            border: 2px solid var(--border-color);
            border-radius: 12px;
            font-size: 15px;
            font-family: 'Montserrat', sans-serif;
            transition: all 0.3s ease;
            background: var(--card-bg);
            color: var(--text-dark);
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--deep-crimson);
            box-shadow: 0 0 0 3px rgba(113,10,20,0.1);
        }

        .form-group textarea {
            resize: vertical;
            min-height: 100px;
        }

        .checkbox-group {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin-top: 8px;
        }

        .checkbox-item {
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .checkbox-item input {
            width: auto;
            margin: 0;
            transform: scale(1.1);
            accent-color: var(--deep-crimson);
        }

        .checkbox-item label {
            margin: 0;
            font-weight: normal;
            color: var(--text-dark);
        }

        .multiple-select {
            background-color: var(--card-bg);
        }

        .multiple-select option {
            padding: 8px;
        }

        .ctrl-hint {
            background: var(--light-bg);
            padding: 8px 12px;
            border-radius: 8px;
            margin-top: 8px;
            font-size: 12px;
            color: var(--text-muted);
            display: inline-block;
        }

        .ctrl-hint i {
            margin-right: 5px;
            color: var(--deep-crimson);
        }

        .button-group {
            display: flex;
            gap: 15px;
            margin-top: 30px;
            flex-wrap: wrap;
        }

        .btn-primary {
            background: var(--deep-crimson);
            color: white;
            border: none;
            padding: 14px 28px;
            border-radius: 12px;
            font-weight: 600;
            font-size: 15px;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-family: 'Montserrat', sans-serif;
            flex: 1;
            justify-content: center;
        }

        .btn-primary:hover {
            background: var(--dark-red);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(63,7,11,0.3);
        }

        .btn-secondary {
            background: var(--dusty-rose);
            color: white;
            border: none;
            padding: 14px 28px;
            border-radius: 12px;
            font-weight: 600;
            font-size: 15px;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-family: 'Montserrat', sans-serif;
            flex: 1;
            justify-content: center;
        }

        .btn-secondary:hover {
            background: var(--deep-crimson);
            transform: translateY(-2px);
        }

        .btn-outline {
            background: transparent;
            color: var(--deep-crimson);
            border: 2px solid var(--deep-crimson);
            padding: 12px 24px;
            border-radius: 12px;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            font-family: 'Montserrat', sans-serif;
        }

        .btn-outline:hover {
            background: var(--deep-crimson);
            color: white;
            transform: translateY(-2px);
        }

        .card-footer {
            background: var(--light-bg);
            padding: 20px 30px;
            border-top: 1px solid var(--border-color);
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
        }

        .alert-success, .alert-danger {
            padding: 12px 16px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 14px;
        }

        .alert-success {
            background: #e8f5e9;
            border-left: 4px solid #2e7d32;
            color: #1b5e20;
        }

        .alert-danger {
            background: #ffebee;
            border-left: 4px solid var(--deep-crimson);
            color: #b71c1c;
        }

        @media (max-width: 640px) {
            body { padding: 15px; }
            .card-header { padding: 20px; }
            .card-header h1 { font-size: 22px; }
            .card-body { padding: 20px; }
            .button-group { flex-direction: column; }
            .card-footer { flex-direction: column; text-align: center; }
        }
    </style>
</head>
<body>
<div class="container">
    <div class="card">
        <div class="card-header">
            <h1><i class="fas fa-paper-plane"></i> Enviar Novedad</h1>
            <p>Comunícate con los usuarios del sistema</p>
        </div>

        <div class="card-body">
            <?php if($exito): ?>
                <div class="alert-success"><i class="fas fa-check-circle"></i> Novedad enviada exitosamente.</div>
            <?php endif; ?>
            <?php if(!empty($error)): ?>
                <div class="alert-danger"><i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="post">
                <div class="form-group">
                    <label><i class="fas fa-heading"></i> Título</label>
                    <input type="text" name="titulo" placeholder="Ej: Reunión de padres, Suspensión de clases..." required>
                </div>

                <div class="form-group">
                    <label><i class="fas fa-align-left"></i> Descripción</label>
                    <textarea name="descripcion" placeholder="Describa el contenido de la novedad..." required></textarea>
                </div>

                <div class="form-group">
                    <label><i class="fas fa-calendar-alt"></i> Fecha de vencimiento</label>
                    <input type="date" name="fecha_vencimiento" required>
                </div>

                <div class="form-group">
                    <label><i class="fas fa-users"></i> Tipo de destinatario</label>
                    <select name="tipo_destinatario" id="tipo_destinatario" required>
                        <option value="roles">Por roles</option>
                        <option value="cursos">Por cursos</option>
                        <?php if($es_admin): ?>
                            <option value="todos">Todos los usuarios</option>
                        <?php endif; ?>
                    </select>
                </div>

                <div class="form-group" id="div_roles" style="display:none;">
                    <label><i class="fas fa-user-tag"></i> Seleccione roles</label>
                    <div class="checkbox-group">
                        <?php
                        $queryRoles = "SELECT ID_rol, nom_rol FROM rol WHERE nom_rol NOT IN ('Invitado')";
                        if(!$es_admin) $queryRoles .= " AND nom_rol != 'Admin'";
                        $resRoles = mysqli_query($con, $queryRoles);
                        while($rol_op = mysqli_fetch_assoc($resRoles)):
                        ?>
                            <div class="checkbox-item">
                                <input type="checkbox" name="roles[]" value="<?= $rol_op['ID_rol'] ?>" id="rol_<?= $rol_op['ID_rol'] ?>">
                                <label for="rol_<?= $rol_op['ID_rol'] ?>"><?= htmlspecialchars($rol_op['nom_rol']) ?></label>
                            </div>
                        <?php endwhile; ?>
                    </div>
                </div>

                <div class="form-group" id="div_cursos" style="display:none;">
                    <label><i class="fas fa-school"></i> Seleccione cursos</label>
                    <select name="cursos[]" multiple class="multiple-select" size="6">
                        <?php foreach($cursos_disponibles as $curso): ?>
                            <option value="<?= $curso['ID_curso'] ?>"><?= htmlspecialchars($curso['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="ctrl-hint"><i class="fas fa-keyboard"></i> Mantenga presionada la tecla Ctrl (Windows) o Cmd (Mac) para seleccionar múltiples cursos.</div>
                </div>

                <div class="button-group">
                    <button type="submit" class="btn-primary"><i class="fas fa-paper-plane"></i> Enviar Novedad</button>
                    <button type="reset" class="btn-secondary"><i class="fas fa-eraser"></i> Limpiar</button>
                </div>
            </form>
        </div>

        <div class="card-footer">
            <a href="../../recursos/panel.php" class="btn-outline"><i class="fas fa-arrow-left"></i> Volver al Panel</a>
        </div>
    </div>
</div>
<script>
    const tipo = document.getElementById('tipo_destinatario');
    const divRoles = document.getElementById('div_roles');
    const divCursos = document.getElementById('div_cursos');
    function toggleDivs() {
        if(tipo.value === 'roles') {
            divRoles.style.display = 'block';
            divCursos.style.display = 'none';
        } else if(tipo.value === 'cursos') {
            divRoles.style.display = 'none';
            divCursos.style.display = 'block';
        } else {
            divRoles.style.display = 'none';
            divCursos.style.display = 'none';
        }
    }
    tipo.addEventListener('change', toggleDivs);
    toggleDivs();
</script>
</body>
</html>
<?php mysqli_close($con); ?>