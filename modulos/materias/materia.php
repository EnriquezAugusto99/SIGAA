<?php
session_start();

if(isset($_SESSION["dni"]) && (time() - $_SESSION["ultima_actividad"] > 1800)){
    echo '<script>alert("Se venció el tiempo de la sesion"); window.location="../../index.php";</script>';
    exit();
}

if(!isset($_SESSION["dni"])){
    echo '<script>alert("Se venció el tiempo de la sesion"); window.location="../../index.php";</script>';
    exit();
}

if($_SESSION['rol'] != 'Admin' && $_SESSION['rol'] != 'Preceptor'){
    echo '<script>alert("No tiene permisos para gestionar materias"); window.location="../../recursos/panel.php";</script>';
    exit();
}

include '../../recursos/conexion.php';

$preselect_curso = $_GET['curso_id'] ?? '';
$es_preceptor = ($_SESSION['rol'] == 'Preceptor');

// Generar token CSRF si no existe
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agregar Materia - Sistema Escolar</title>
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
            max-width: 600px;
            width: 100%;
            margin: 0 auto;
        }

        /* Tarjeta principal */
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

        /* Header de la tarjeta */
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

        /* Cuerpo de la tarjeta */
        .card-body {
            padding: 30px;
        }

        /* Grupos de formulario */
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
        .form-group select {
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
        .form-group select:focus {
            outline: none;
            border-color: var(--deep-crimson);
            box-shadow: 0 0 0 3px rgba(113,10,20,0.1);
        }

        .form-group input::placeholder {
            color: var(--text-muted);
        }

        /* Grupo de botones */
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

        /* Footer de la tarjeta */
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

        /* Mensajes */
        .info-message {
            background: #e8f0fe;
            padding: 12px 16px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 13px;
            color: var(--deep-crimson);
            border-left: 3px solid var(--deep-crimson);
        }

        .info-message i {
            font-size: 16px;
        }

        /* Responsive */
        @media (max-width: 640px) {
            body {
                padding: 15px;
            }

            .card-header {
                padding: 20px;
            }

            .card-header h1 {
                font-size: 22px;
            }

            .card-body {
                padding: 20px;
            }

            .button-group {
                flex-direction: column;
            }

            .card-footer {
                flex-direction: column;
                text-align: center;
            }
        }
    </style>
</head>
<body>
<div class="container">
    <div class="card">
        <div class="card-header">
            <h1>Agregar Materia</h1>
        </div>

        <div class="card-body">
            <?php if($es_preceptor): ?>
                <div class="info-message">
                    Solo puedes agregar materias a los cursos que tienes asignados como Preceptor.
                </div>
            <?php endif; ?>

            <form action="guardar_materia.php" method="post">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <div class="form-group">
                    <label>
                        Nombre de la Materia
                    </label>
                    <input type="text" name="txt_nombre" id="txt_nombre" 
                           placeholder="Ej: Matemática, Lengua, Programación..." 
                           required maxlength="100">
                </div>

                <div class="form-group">
                    <label>
                        Curso
                    </label>
                    <select name="txt_curso" id="txt_curso" required>
                        <option value="-1">-- Seleccione un curso --</option>
                        <?php
                        if($es_preceptor){
                            $preceptor_dni = $_SESSION["dni"];
                            $query_cursos = "SELECT c.ID_curso, c.curso, c.division, c.turno
                                             FROM curso c
                                             INNER JOIN preceptorxcurso pxc ON c.ID_curso = pxc.id_curso
                                             WHERE pxc.id_preceptor = '$preceptor_dni'
                                             ORDER BY c.curso, c.division";
                        } else {
                            $query_cursos = "SELECT ID_curso, curso, division, turno FROM curso ORDER BY curso, division";
                        }
                        $res_cursos = mysqli_query($con, $query_cursos);
                        if(mysqli_num_rows($res_cursos) > 0){
                            while($fila_curso = mysqli_fetch_array($res_cursos)){
                                $turno_texto = $fila_curso['turno'] == 'M' ? 'Mañana' : 'Tarde';
                                $selected = ($preselect_curso == $fila_curso['ID_curso']) ? 'selected' : '';
                                echo '<option value="'.$fila_curso["ID_curso"].'" '.$selected.'>'
                                     .$fila_curso["curso"].'° "'.$fila_curso["division"].'" - '.$turno_texto.'</option>';
                            }
                        } else {
                            echo '<option value="-1" disabled>No hay cursos disponibles</option>';
                        }
                        ?>
                    </select>
                </div>

                <div class="button-group">
                    <button type="submit" class="btn-primary">
                        <i class="fas fa-save"></i> Guardar Materia
                    </button>
                    <button type="reset" class="btn-secondary">
                        <i class="fas fa-eraser"></i> Limpiar Campos
                    </button>
                </div>
            </form>
        </div>

        <div class="card-footer">
            <a href="listado_materia.php<?php echo $preselect_curso ? '?curso_id='.$preselect_curso : ''; ?>" class="btn-outline">
                <i class="fas fa-list"></i> Ver Listado de Materias
            </a>
            <a href="../../recursos/panel.php" class="btn-outline">
                <i class="fas fa-arrow-left"></i> Volver al Panel
            </a>
        </div>
    </div>
</div>
</body>
</html>
<?php mysqli_close($con); ?>