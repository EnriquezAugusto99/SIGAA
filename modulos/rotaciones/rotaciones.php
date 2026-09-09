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
    echo '<script>alert("No tiene permisos para gestionar rotaciones"); window.location="../../recursos/panel.php";</script>';
    exit();
}

include '../../recursos/conexion.php';

$anio_actual = date('Y');
$filtro_anio = $_GET['filtro_anio'] ?? $anio_actual;

$query = "SELECT * FROM rotaciones WHERE anio = '$filtro_anio' ORDER BY numero_rotacion";
$res = mysqli_query($con, $query);

$query_anios = "SELECT DISTINCT anio FROM rotaciones ORDER BY anio DESC";
$res_anios = mysqli_query($con, $query_anios);

// Generar token CSRF
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
    <title>Gestión de Rotaciones</title>
    <style>
        .form-nueva-rotacion {
            background: #f5f5f5;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 30px;
            border: 1px solid #e0e0e0;
        }
        .form-nueva-rotacion h3 {
            margin-top: 0;
            margin-bottom: 15px;
            color: #333;
        }
        .grid-2 {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 15px;
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            font-weight: 600;
            margin-bottom: 5px;
            font-size: 13px;
            color: #555;
        }
        .form-group input {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
        }
        .btn-pequeno {
            padding: 8px 16px;
            font-size: 12px;
        }
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
        <h1>Gestión de Rotaciones</h1>
        
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
                <a href="rotaciones.php"><button type="button">Limpiar</button></a>
            </form>
        </div>
        
        <!-- Formulario para nueva rotación -->
        <div class="form-nueva-rotacion">
            <h3>➕ Agregar Nueva Rotación para <?php echo $filtro_anio; ?></h3>
            <form action="guardar_rotacion.php" method="post">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <input type="hidden" name="anio" value="<?php echo $filtro_anio; ?>">
                
                <div class="grid-2">
                    <div class="form-group">
                        <label>Número de Rotación</label>
                        <input type="number" name="numero_rotacion" min="1" max="6" required placeholder="Ej: 1">
                    </div>
                    <div class="form-group">
                        <label>Nombre</label>
                        <input type="text" name="nombre" required placeholder="Ej: 1° (PRIMERA)">
                    </div>
                    <div class="form-group">
                        <label>Fecha Inicio</label>
                        <input type="date" name="fecha_inicio" required>
                    </div>
                    <div class="form-group">
                        <label>Fecha Fin</label>
                        <input type="date" name="fecha_fin" required>
                    </div>
                    <div class="form-group">
                        <label>Duración (semanas)</label>
                        <input type="number" name="duracion_semanas" min="1" max="12" placeholder="Opcional">
                    </div>
                    <div class="form-group">
                        <label>Estado</label>
                        <select name="activo">
                            <option value="1">Activo</option>
                            <option value="0">Inactivo</option>
                        </select>
                    </div>
                </div>
                <button type="submit">Guardar Rotación</button>
                <button type="reset">Limpiar</button>
            </form>
        </div>
        
        <!-- Listado de rotaciones -->
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
                    <th class="no-exportar">Editar</th>
                    <th class="no-exportar">Eliminar</th>
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
                        echo '<td class="no-exportar"><a href="editar_rotacion.php?id=' . $row['ID_rotacion'] . '"><button class="btn-pequeno">Editar</button></a></td>';
                        
                        // Solo permitir eliminar si no tiene referencias
                        $tiene_referencias = false; // TODO: verificar si tiene planillas o calificaciones
                        if(!$tiene_referencias){
                            echo '<td class="no-exportar"><a href="borrar_rotacion.php?id=' . $row['ID_rotacion'] . '" onclick="return confirm(\'¿Eliminar esta rotación?\')"><button class="btn-pequeno">Eliminar</button></a></td>';
                        } else {
                            echo '<td class="no-exportar"><button class="btn-pequeno" disabled>Tiene referencias</button></td>';
                        }
                        echo '</tr>';
                    }
                } else {
                    echo '<tr><td colspan="8">No hay rotaciones configuradas para el año ' . $filtro_anio . '.</td></tr>';
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