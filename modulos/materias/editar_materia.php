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

$id = $_GET['id'];

$query_verificar = "SELECT materia.ID_materia, materia.Nom_materia, materia.id_curso, curso.curso, curso.division, curso.turno FROM materia LEFT JOIN curso ON materia.id_curso = curso.ID_curso WHERE materia.ID_materia = '$id'";
$res_verificar = mysqli_query($con, $query_verificar);

if(mysqli_num_rows($res_verificar) == 0){
    echo '<script>alert("La materia no existe"); window.location="listado_materia.php";</script>';
    exit();
}

$fila = mysqli_fetch_array($res_verificar);

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
    <title>Editar Materia</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&display=swap" rel="stylesheet">
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
            background: var(--light-bg);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }

        .container {
            max-width: 550px;
            width: 100%;
            margin: 0 auto;
        }

        .card {
            background: var(--card-bg);
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            overflow: hidden;
        }

        .card-header {
            background: var(--dark-red);
            padding: 22px 25px;
            text-align: center;
        }

        .card-header h1 {
            color: white;
            font-size: 24px;
            font-weight: 600;
            margin: 0;
        }

        .card-body {
            padding: 30px;
        }

        .form-group {
            margin-bottom: 22px;
        }

        .form-group label {
            display: block;
            font-weight: 600;
            color: var(--dark-burgundy);
            margin-bottom: 8px;
            font-size: 14px;
        }

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid var(--border-color);
            border-radius: 10px;
            font-size: 14px;
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

        .button-group {
            display: flex;
            gap: 15px;
            margin-top: 25px;
            flex-wrap: wrap;
        }

        .btn-primary {
            background: var(--deep-crimson);
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-family: 'Montserrat', sans-serif;
            flex: 1;
        }

        .btn-primary:hover {
            background: var(--dark-red);
            transform: translateY(-2px);
        }

        .btn-secondary {
            background: var(--dusty-rose);
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-family: 'Montserrat', sans-serif;
            text-decoration: none;
            display: inline-block;
            text-align: center;
            flex: 1;
        }

        .btn-secondary:hover {
            background: var(--deep-crimson);
            transform: translateY(-2px);
        }

        .card-footer {
            background: var(--light-bg);
            padding: 18px 30px;
            border-top: 1px solid var(--border-color);
            text-align: center;
        }

        .btn-link {
            background: transparent;
            color: var(--deep-crimson);
            border: none;
            padding: 8px 20px;
            font-weight: 600;
            font-size: 13px;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
            font-family: 'Montserrat', sans-serif;
        }

        .btn-link:hover {
            color: var(--dark-red);
            text-decoration: underline;
        }

        @media (max-width: 550px) {
            body {
                padding: 15px;
            }

            .card-header {
                padding: 18px 20px;
            }

            .card-header h1 {
                font-size: 20px;
            }

            .card-body {
                padding: 20px;
            }

            .button-group {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
<div class="container">
    <div class="card">
        <div class="card-header">
            <h1>Editar Materia</h1>
        </div>

        <div class="card-body">
            <form action="guardar_editarMateria.php" method="post">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <input type="hidden" name="txt_id" value="<?php echo $fila['ID_materia']; ?>">
                
                <div class="form-group">
                    <label for="txt_nombre">Nombre</label>
                    <input type="text" name="txt_nombre" id="txt_nombre" 
                           value="<?php echo htmlspecialchars($fila['Nom_materia']); ?>" 
                           required maxlength="100">
                </div>

                <div class="form-group">
                    <label for="txt_curso">Curso</label>
                    <select name="txt_curso" id="txt_curso" required>
                        <option value="-1">Seleccione un curso</option>
                        <?php
                        $query_cursos = "SELECT ID_curso, curso, division, turno FROM curso ORDER BY curso, division";
                        $res_cursos = mysqli_query($con, $query_cursos);
                        while($fila_curso = mysqli_fetch_array($res_cursos)){
                            $turno_texto = $fila_curso['turno'] == 'M' ? 'Mañana' : 'Tarde';
                            $curso_completo = $fila_curso['curso'] . '° "' . $fila_curso['division'] . '" - ' . $turno_texto;
                            $selected = ($fila_curso['ID_curso'] == $fila['id_curso']) ? 'selected' : '';
                            echo '<option value="' . $fila_curso['ID_curso'] . '" ' . $selected . '>' . $curso_completo . '</option>';
                        }
                        ?>
                    </select>
                </div>

                <div class="button-group">
                    <button type="submit" class="btn-primary">Guardar Cambios</button>
                    <a href="listado_materia.php" class="btn-secondary">Cancelar</a>
                </div>
            </form>
        </div>

        <div class="card-footer">
            <a href="listado_materia.php" class="btn-link">Volver al listado</a>
        </div>
    </div>
</div>
</body>
</html>
<?php
mysqli_close($con);
?>