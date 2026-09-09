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
    echo '<script>alert("No tiene permisos para ver asignaciones"); window.location="../../recursos/panel.php";</script>';
    exit();
}

include '../../recursos/conexion.php';
mysqli_set_charset($con, "utf8");

$anio_actual = date('Y');
$filtro_anio = isset($_GET['filtro_anio']) ? $_GET['filtro_anio'] : $anio_actual;
$filtro_taller = isset($_GET['filtro_taller']) ? $_GET['filtro_taller'] : '';
$filtro_curso = isset($_GET['filtro_curso']) ? $_GET['filtro_curso'] : '';

$where = "WHERE dtc.anio = '" . mysqli_real_escape_string($con, $filtro_anio) . "'";

if(!empty($filtro_taller)){
    $where .= " AND dtc.ID_taller = '" . mysqli_real_escape_string($con, $filtro_taller) . "'";
}
if(!empty($filtro_curso)){
    $where .= " AND dtc.ID_curso = '" . mysqli_real_escape_string($con, $filtro_curso) . "'";
}

$query = "SELECT 
            dtc.ID_dtc,
            dtc.turno,
            dtc.anio,
            u.DNI_U as docente_dni,
            u.Nombre as docente_nombre,
            u.Apellido as docente_apellido,
            t.ID_taller,
            t.nombre as taller_nombre,
            t.anio_taller,
            c.ID_curso,
            c.curso,
            c.division,
            c.turno as curso_turno
          FROM docente_taller_curso dtc
          LEFT JOIN usuario u ON dtc.ID_docente = u.DNI_U
          LEFT JOIN talleres t ON dtc.ID_taller = t.ID_taller
          LEFT JOIN curso c ON dtc.ID_curso = c.ID_curso
          $where
          ORDER BY t.nombre, c.curso, c.division";

$res = mysqli_query($con, $query);

if(!$res){
    die("Error en consulta: " . mysqli_error($con));
}

$query_talleres = "SELECT ID_taller, nombre, anio_taller FROM talleres WHERE activo = 1 ORDER BY anio_taller, nombre";
$res_talleres = mysqli_query($con, $query_talleres);

$query_cursos = "SELECT ID_curso, curso, division, turno FROM curso ORDER BY curso, division";
$res_cursos = mysqli_query($con, $query_cursos);

$query_total = "SELECT COUNT(*) as total FROM docente_taller_curso WHERE anio = '" . mysqli_real_escape_string($con, $filtro_anio) . "'";
$res_total = mysqli_query($con, $query_total);
$total_asignaciones = mysqli_fetch_array($res_total)['total'];

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../../recursos/styles.css">
    <title>Listado de Asignaciones Docente - Taller - Curso</title>
    <style>
        .resumen { background: #e8f0fe; padding: 10px 15px; border-radius: 8px; margin-bottom: 20px; }
        .filtros { background: #f5f5f5; padding: 15px; border-radius: 8px; margin-bottom: 20px; }
        .btn-eliminar { background: #dc3545; color: white; border: none; padding: 5px 12px; border-radius: 4px; cursor: pointer; }
        .btn-eliminar:hover { background: #c82333; }
        .highlight { background-color: #fff3cd !important; }
        .caja { max-width: 1200px; margin: 0 auto; background: white; border-radius: 10px; padding: 20px; box-shadow: 0 4px 10px rgba(0,0,0,0.1); }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th, td { border: 1px solid #ddd; padding: 10px; text-align: left; }
        th { background: #7a0000; color: white; }
        button { padding: 8px 15px; background: #7a0000; color: white; border: none; border-radius: 4px; cursor: pointer; }
        button:hover { background: #8b0000; }
/* Contenedor con scroll horizontal */
.tabla-container {
    width: 100%;
    overflow-x: auto;
    overflow-y: visible;
    -webkit-overflow-scrolling: touch;
    margin-bottom: 20px;
}

/* Ajustes responsive */
@media (max-width: 992px) {
    .caja { padding: 15px; margin: 10px; }
    h1 { font-size: 1.4rem; text-align: center; }
    .filtro-rapido form { display: flex; flex-direction: column; gap: 8px; }
    .filtro-rapido input, .filtro-rapido button, .filtro-rapido a button { width: 100%; }
    .filtros form { display: flex; flex-direction: column; }
    .filtros input, .filtros select, .filtros button { width: 100%; margin: 5px 0; }
    p { display: flex; flex-direction: column; gap: 8px; }
    p button, p a button { width: 100%; }
}

@media (max-width: 768px) {
    h1 { font-size: 1.2rem; }
    .resumen { font-size: 0.8rem; text-align: center; }
    .tabla th, .tabla td { padding: 8px 6px; font-size: 0.75rem; }
}

@media (max-width: 480px) {
    h1 { font-size: 1rem; }
    .tabla th, .tabla td { padding: 6px 4px; font-size: 0.7rem; }
}
    </style>
</head>
<body>
    <div class="caja">
        <h1>Asignaciones Docente - Taller - Curso</h1>
        
        <div class="resumen">
            <strong>Año lectivo: <?php echo htmlspecialchars($filtro_anio); ?></strong> | 
            <strong>Total de asignaciones: <?php echo $total_asignaciones; ?></strong>
        </div>
        
        <div class="filtros">
            <form method="GET" action="">
                <div style="display: flex; flex-wrap: wrap; gap: 15px; align-items: flex-end;">
                    <div>
                        <label><strong>Año:</strong></label><br>
                        <select name="filtro_anio">
                            <option value="2025" <?php echo $filtro_anio == '2025' ? 'selected' : ''; ?>>2025</option>
                            <option value="2026" <?php echo $filtro_anio == '2026' ? 'selected' : ''; ?>>2026</option>
                            <option value="2027" <?php echo $filtro_anio == '2027' ? 'selected' : ''; ?>>2027</option>
                        </select>
                    </div>
                    <div>
                        <label><strong>Taller:</strong></label><br>
                        <select name="filtro_taller">
                            <option value="">Todos</option>
                            <?php 
                            // Resetear el puntero del resultado de talleres
                            if(mysqli_num_rows($res_talleres) > 0){
                                mysqli_data_seek($res_talleres, 0);
                                while($taller = mysqli_fetch_array($res_talleres)): 
                                    $selected = ($filtro_taller == $taller['ID_taller']) ? 'selected' : '';
                            ?>
                                <option value="<?php echo $taller['ID_taller']; ?>" <?php echo $selected; ?>>
                                    <?php echo htmlspecialchars($taller['nombre'] . ' (' . $taller['anio_taller'] . ')'); ?>
                                </option>
                            <?php 
                                endwhile; 
                            }
                            ?>
                        </select>
                    </div>
                    <div>
                        <label><strong>Curso:</strong></label><br>
                        <select name="filtro_curso">
                            <option value="">Todos</option>
                            <?php 
                            // Resetear el puntero del resultado de cursos
                            if(mysqli_num_rows($res_cursos) > 0){
                                mysqli_data_seek($res_cursos, 0);
                                while($curso = mysqli_fetch_array($res_cursos)):
                                    $turno_texto = $curso['turno'] == 'M' ? 'Mañana' : 'Tarde';
                                    $curso_nombre = $curso['curso'] . '° "' . $curso['division'] . '" - ' . $turno_texto;
                                    $selected = ($filtro_curso == $curso['ID_curso']) ? 'selected' : '';
                            ?>
                                <option value="<?php echo $curso['ID_curso']; ?>" <?php echo $selected; ?>>
                                    <?php echo htmlspecialchars($curso_nombre); ?>
                                </option>
                            <?php 
                                endwhile;
                            }
                            ?>
                        </select>
                    </div>
                    <div>
                        <button type="submit">Filtrar</button>
                        <a href="listado_docente_taller_curso.php"><button type="button">Limpiar</button></a>
                    </div>
                </div>
            </form>
        </div>
        
        <form id="formEliminar" method="POST" action="borrar_docente_taller_curso.php" style="display: none;">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
            <input type="hidden" name="id_dtc" id="id_dtc_eliminar">
        </form>
        
<div class="tabla-container">
        <table class="tabla">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Docente</th>
                    <th>Taller</th>
                    <th>Curso</th>
                    <th>Horario Taller</th>
                    <th>Turno Escuela</th>
                    <th>Año</th>
                    <th class="no-exportar">Eliminar</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $contador = 0;
                if(mysqli_num_rows($res) > 0){
                    while($fila = mysqli_fetch_array($res)){
                        $contador++;
                        $turno_taller = $fila['turno'] == 'M' ? 'Mañana' : 'Tarde';
                        $turno_escuela = $fila['curso_turno'] == 'M' ? 'Mañana' : 'Tarde';
                        
                        // Mostrar el nombre del docente o "SIN ASIGNAR"
                        $docente_nombre = 'SIN ASIGNAR';
                        if(!empty($fila['docente_apellido'])){
                            $docente_nombre = $fila['docente_apellido'] . ', ' . $fila['docente_nombre'];
                        } elseif(!empty($fila['docente_dni'])){
                            $docente_nombre = 'DNI: ' . $fila['docente_dni'] . ' (no encontrado)';
                        }
                        
                        // Mostrar el nombre completo del taller
                        $taller_completo = $fila['taller_nombre'] . ' (' . $fila['anio_taller'] . ')';
                        
                        echo '<tr>';
                        echo '<td>' . htmlspecialchars($fila['ID_dtc']) . '</td>';
                        echo '<td><strong>' . htmlspecialchars($docente_nombre) . '</strong></td>';
                        echo '<td>' . htmlspecialchars($taller_completo) . '</td>';
                        echo '<td>' . htmlspecialchars($fila['curso']) . '° "' . htmlspecialchars($fila['division']) . '"' . '</td>';
                        echo '<td>' . htmlspecialchars($turno_taller) . '</td>';
                        echo '<td>' . htmlspecialchars($turno_escuela) . '</td>';
                        echo '<td>' . htmlspecialchars($fila['anio']) . '</td>';
                        echo '<td class="no-exportar">
                                <button type="button" class="btn-eliminar" onclick="confirmarEliminar(' . $fila['ID_dtc'] . ')">Eliminar</button>
                              </td>';
                        echo '</tr>';
                    }
                } else {
                    echo '<tr><td colspan="8">No hay asignaciones para los filtros seleccionados.</td></tr>';
                }
                ?>
            </tbody>
        </table>
</div>
        
        <p style="margin-top: 20px;">
            <a href="docente_taller_curso.php"><button type="button">Agregar Nueva Asignación</button></a>
            <a href="../../recursos/panel.php"><button type="button">Volver al Panel</button></a>
        </p>
    </div>
    
    <script>
        function confirmarEliminar(id) {
            if(confirm('¿Está seguro que desea eliminar esta asignación?')) {
                document.getElementById('id_dtc_eliminar').value = id;
                document.getElementById('formEliminar').submit();
            }
        }
    </script>
</body>
</html>
<?php mysqli_close($con); ?>