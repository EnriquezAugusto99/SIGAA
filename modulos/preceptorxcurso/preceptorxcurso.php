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

// Verificar permisos
if($_SESSION['rol'] != 'Admin' && $_SESSION['rol'] != 'Secretario'){
    echo '<script>alert("No tiene permisos para gestionar asignaciones preceptor-curso"); window.location="../../recursos/panel.php";</script>';
    exit();
}

include '../../recursos/conexion.php';

// Generar token CSRF
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Obtener listas de preceptores y cursos
$query_preceptores = "SELECT u.DNI_U, u.Nombre, u.Apellido 
                      FROM usuario u
                      WHERE u.ID_rol = 1 AND u.ID_Estado = 1 
                      ORDER BY u.Apellido, u.Nombre";
$res_preceptores = mysqli_query($con, $query_preceptores);

$query_cursos = "SELECT c.ID_curso, c.curso, c.division, c.turno 
                 FROM curso c 
                 ORDER BY c.curso, c.division";
$res_cursos = mysqli_query($con, $query_cursos);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../../recursos/styles.css">
    <title>Asignar Preceptor a Curso</title>
    <style>
        .contenedor-form {
            max-width: 650px;
            margin: 0 auto;
            background: var(--card-bg);
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        .header-form {
            background: var(--dark-red);
            padding: 20px 25px;
        }
        .header-form h1 {
            color: white;
            font-size: 22px;
            font-weight: 600;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .body-form {
            padding: 30px;
        }
        .info-box {
            background: rgba(113,10,20,0.08);
            border-left: 4px solid var(--deep-crimson);
            padding: 12px 16px;
            margin-bottom: 25px;
            border-radius: 8px;
            font-size: 13px;
            color: var(--text-dark);
        }
        .info-box strong {
            color: var(--deep-crimson);
        }
        .campo {
            margin-bottom: 20px;
        }
        .campo label {
            display: block;
            font-weight: 600;
            color: var(--dark-burgundy);
            margin-bottom: 8px;
            font-size: 14px;
        }
        .campo select {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid var(--border-color);
            border-radius: 10px;
            font-size: 14px;
            font-family: 'Montserrat', sans-serif;
            background: var(--card-bg);
            color: var(--text-dark);
            transition: all 0.3s ease;
        }
        .campo select:focus {
            outline: none;
            border-color: var(--deep-crimson);
        }
        .acciones-form {
            display: flex;
            gap: 15px;
            margin-top: 25px;
            flex-wrap: wrap;
        }
        .btn-guardar {
            background: var(--success);
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .btn-guardar:hover {
            background: #1b5e20;
            transform: translateY(-2px);
        }
        .btn-volver {
            background: var(--deep-crimson);
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s ease;
        }
        .btn-volver:hover {
            background: var(--dark-red);
            transform: translateY(-2px);
        }
        @media (max-width: 768px) {
            .body-form {
                padding: 20px;
            }
            .acciones-form {
                flex-direction: column;
            }
            .btn-guardar, .btn-volver {
                width: 100%;
                text-align: center;
            }
        }
    </style>
</head>
<body>
    <div class="caja" style="max-width: 700px; margin: 0 auto; padding: 20px;">
        <div class="contenedor-form">
            <div class="header-form">
                <h1>
                    <i>👨‍🏫</i>
                    Asignar Preceptor a Curso
                </h1>
            </div>
            
            <div class="body-form">
                <div class="info-box">
                    <strong>ℹ️ Instrucciones:</strong><br>
                    1. Seleccione el preceptor<br>
                    2. Seleccione el curso<br>
                    3. Guarde la asignación
                </div>
                
                <form action="guardar_preceptorxcurso.php" method="post" id="formAsignacion">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    
                    <div class="campo">
                        <label for="txt_preceptor">👨‍🏫 1. Seleccione el Preceptor:</label>
                        <select name="txt_preceptor" id="txt_preceptor" required>
                            <option value="">-- Seleccione un preceptor --</option>
                            <?php while($p = mysqli_fetch_assoc($res_preceptores)): ?>
                                <option value="<?php echo $p['DNI_U']; ?>">
                                    <?php echo htmlspecialchars($p['Apellido'] . ', ' . $p['Nombre'] . ' (DNI: ' . $p['DNI_U'] . ')'); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div class="campo">
                        <label for="txt_curso">📚 2. Seleccione el Curso:</label>
                        <select name="txt_curso" id="txt_curso" required>
                            <option value="">-- Seleccione un curso --</option>
                            <?php while($c = mysqli_fetch_assoc($res_cursos)): 
                                $turno_texto = ($c['turno'] == 'M') ? 'Mañana' : 'Tarde';
                            ?>
                                <option value="<?php echo $c['ID_curso']; ?>">
                                    <?php echo $c['curso'] . '° "' . $c['division'] . '" - ' . $turno_texto; ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div class="acciones-form">
                        <button type="submit" class="btn-guardar">💾 Guardar Asignación</button>
                        <a href="listado_preceptorxcurso.php" class="btn-volver">📋 Ver Listado</a>
                        <a href="../../recursos/panel.php" class="btn-volver">⬅️ Volver al Panel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script>
        // Validar antes de enviar
        document.getElementById('formAsignacion')?.addEventListener('submit', function(e) {
            const preceptor = document.getElementById('txt_preceptor').value;
            const curso = document.getElementById('txt_curso').value;
            
            if(!preceptor){
                e.preventDefault();
                alert('⚠️ Debe seleccionar un preceptor.');
                return false;
            }
            if(!curso){
                e.preventDefault();
                alert('⚠️ Debe seleccionar un curso.');
                return false;
            }
            return true;
        });
    </script>
</body>
</html>
<?php mysqli_close($con); ?>