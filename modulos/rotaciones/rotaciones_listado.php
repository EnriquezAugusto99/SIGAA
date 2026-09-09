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

$es_admin = ($_SESSION['rol'] == 'Admin');
$es_profesor = ($_SESSION['rol'] == 'Profesor');

// Verificar permisos: Admin, Preceptor, o Profesor de taller
if(!$es_admin && $_SESSION['rol'] != 'Preceptor' && $_SESSION['rol'] != 'Profesor'){
    echo '<script>alert("No tiene permisos para ver rotaciones"); window.location="../../recursos/panel.php";</script>';
    exit();
}

include '../../recursos/conexion.php';

// Si es profesor, verificar que sea profesor de taller
if($es_profesor){
    $dni = $_SESSION['dni'];
    $query_verificar = "SELECT COUNT(*) as total FROM docente_taller_curso WHERE ID_docente = '$dni'";
    $res_verificar = mysqli_query($con, $query_verificar);
    $row_verificar = mysqli_fetch_assoc($res_verificar);
    if($row_verificar['total'] == 0){
        echo '<script>alert("No tiene permisos para ver rotaciones"); window.location="../../recursos/panel.php";</script>';
        exit();
    }
}

$anio_actual = date('Y');
$filtro_anio = $_GET['filtro_anio'] ?? $anio_actual;

$query = "SELECT * FROM rotaciones WHERE anio = '$filtro_anio' ORDER BY numero_rotacion";
$res = mysqli_query($con, $query);

$query_anios = "SELECT DISTINCT anio FROM rotaciones ORDER BY anio DESC";
$res_anios = mysqli_query($con, $query_anios);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../../recursos/styles.css">
    <title>Listado de Rotaciones</title>
    <style>
        .activo {
            color: green;
            font-weight: bold;
        }
        .inactivo {
            color: red;
            font-weight: bold;
        }
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
    <div class="caja" style="max-width: 1000px;">
        <h1>Listado de Rotaciones</h1>
        
        <!-- Filtro por año -->
        <div class="filtro-rapido">
            <form method="GET" action="">
                <label><strong>Año lectivo:</strong></label>
                <select name="filtro_anio" onchange="this.form.submit()">
                    <?php
                    $anio_inicio = 2025;
                    $anio_fin = date('Y') + 2;
                    for($a = $anio_fin; $a >= $anio_inicio; $a--){
                        $selected = ($filtro_anio == $a) ? 'selected' : '';
                        echo "<option value='$a' $selected>$a</option>";
                    }
                    ?>
                </select>
                <noscript><button type="submit">Filtrar</button></noscript>
                <a href="rotaciones_listado.php"><button type="button">Limpiar</button></a>
            </form>
        </div>
        
<div class="tabla-container">
        <table class="tabla">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Nombre</th>
                    <th>Fecha Inicio</th>
                    <th>Fecha Fin</th>
                    <th>Duración</th>
                    <th>Estado</th>
                </tr>
            </thead>
            <tbody>
                <?php
                if(mysqli_num_rows($res) > 0){
                    while($row = mysqli_fetch_array($res)){
                        $estado_texto = $row['activo'] == 1 ? 'Activo' : 'Inactivo';
                        $estado_clase = $row['activo'] == 1 ? 'activo' : 'inactivo';
                        $duracion = $row['duracion_semanas'] ? $row['duracion_semanas'] . ' semanas' : '-';
                        
                        echo '<tr>';
                        echo '<td>' . $row['numero_rotacion'] . '</td>';
                        echo '<td><strong>' . htmlspecialchars($row['nombre']) . '</strong></td>';
                        echo '<td>' . date('d/m/Y', strtotime($row['fecha_inicio'])) . '</td>';
                        echo '<td>' . date('d/m/Y', strtotime($row['fecha_fin'])) . '</td>';
                        echo '<td>' . $duracion . '</td>';
                        echo '<td class="' . $estado_clase . '">' . $estado_texto . '</td>';
                        echo '</tr>';
                    }
                } else {
                    echo '<tr><td colspan="6">No hay rotaciones configuradas para el año ' . $filtro_anio . '.</td></tr>';
                }
                ?>
            </tbody>
        </table>
</div>
        
        <p>
            <a href="../../recursos/panel.php"><button>Volver al Panel</button></a>
        </p>
    </div>
</body>
</html>
<?php mysqli_close($con); ?>