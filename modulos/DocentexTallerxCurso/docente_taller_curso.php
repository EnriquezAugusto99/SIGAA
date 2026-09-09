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

// Verificar permisos (solo Admin y Preceptor)
if($_SESSION['rol'] != 'Admin' && $_SESSION['rol'] != 'Preceptor'){
    echo '<script>alert("No tiene permisos para gestionar asignaciones de talleres"); window.location="../../recursos/panel.php";</script>';
    exit();
}

include '../../recursos/conexion.php';

// Generar token CSRF si no existe
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Obtener listas para selects
$query_docentes = "SELECT DNI_U, Nombre, Apellido FROM usuario WHERE ID_rol = 2 AND ID_Estado = 1 ORDER BY Apellido, Nombre";
$res_docentes = mysqli_query($con, $query_docentes);

$query_talleres = "SELECT ID_taller, nombre, anio_taller FROM talleres WHERE activo = 1 ORDER BY anio_taller, nombre";
$res_talleres = mysqli_query($con, $query_talleres);

$query_cursos = "SELECT ID_curso, curso, division, turno FROM curso ORDER BY curso, division";
$res_cursos = mysqli_query($con, $query_cursos);

$anio_actual = date('Y');
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../../recursos/styles.css">
    <title>Asignar Docente a Taller</title>
    <style>
        .info {
            background: #e8f0fe;
            border-left: 4px solid #2196F3;
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            color: #1565C0;
            font-size: 13px;
        }
    </style>
</head>
<body>
    <div class="caja">
        <h1>Asignar Docente a Taller por Curso</h1>
        
        <div class="info">
            <strong>Información:</strong> Cada asignación vincula un docente con un taller específico 
            para un curso y turno determinados. Un mismo docente puede dar el mismo taller a diferentes cursos.
        </div>
        
        <form action="guardar_docente_taller_curso.php" method="post">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
            <input type="hidden" name="anio" value="<?php echo $anio_actual; ?>">
            
            <p>
                <label for="ID_docente">Docente</label>
                <select name="ID_docente" id="ID_docente" required>
                    <option value="">Seleccione un docente</option>
                    <?php while($docente = mysqli_fetch_array($res_docentes)): ?>
                        <option value="<?php echo $docente['DNI_U']; ?>">
                            <?php echo htmlspecialchars($docente['Apellido'] . ', ' . $docente['Nombre']); ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </p>

            <p>
                <label for="ID_taller">Taller</label>
                <select name="ID_taller" id="ID_taller" required>
                    <option value="">Seleccione un taller</option>
                    <?php while($taller = mysqli_fetch_array($res_talleres)): ?>
                        <option value="<?php echo $taller['ID_taller']; ?>">
                            <?php echo htmlspecialchars($taller['nombre'] . ' (' . $taller['anio_taller'] . ')'); ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </p>

            <p>
                <label for="ID_curso">Curso</label>
                <select name="ID_curso" id="ID_curso" required>
                    <option value="">Seleccione un curso</option>
                    <?php while($curso = mysqli_fetch_array($res_cursos)):
                        $turno_texto = $curso['turno'] == 'M' ? 'Mañana' : 'Tarde';
                        $curso_nombre = $curso['curso'] . '° "' . $curso['division'] . '" - ' . $turno_texto;
                    ?>
                        <option value="<?php echo $curso['ID_curso']; ?>">
                            <?php echo htmlspecialchars($curso_nombre); ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </p>

            <p>
                <label for="turno">Horario del Taller</label>
                <select name="turno" id="turno" required>
                    <option value="">Seleccione un horario</option>
                    <option value="M">Mañana (el taller se dicta por la mañana)</option>
                    <option value="T">Tarde (el taller se dicta por la tarde)</option>
                </select>
            </p>

            <p>
                <button type="submit">Guardar Asignación</button>
                <button type="reset">Limpiar</button>
            </p>
        </form>
        <p>
            <a href="listado_docente_taller_curso.php"><button>Ver Listado de Asignaciones</button></a>
            <a href="../../recursos/panel.php"><button>Volver al Panel</button></a>
        </p>
    </div>
</body>
</html>
<?php mysqli_close($con); ?>