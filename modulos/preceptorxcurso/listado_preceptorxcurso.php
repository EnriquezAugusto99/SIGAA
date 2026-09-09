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
if($_SESSION['rol'] != 'Admin' && $_SESSION['rol'] != 'Secretario' && $_SESSION['rol'] != 'Preceptor'){
    echo '<script>alert("No tiene permisos para ver asignaciones preceptor-curso"); window.location="../../recursos/panel.php";</script>';
    exit();
}

include '../../recursos/conexion.php';

// Obtener filtros
$buscar_exacto = $_GET['buscar_exacto'] ?? '';
$filtro_preceptor = $_GET['filtro_preceptor'] ?? '';
$filtro_curso = $_GET['filtro_curso'] ?? '';
$filtro_id = $_GET['filtro_id'] ?? '';
$mostrar_filtros = isset($_GET['mostrar_filtros']) ? $_GET['mostrar_filtros'] : '0';

// Construir consulta WHERE
$where = "WHERE 1=1";

if(!empty($buscar_exacto)){
    if(is_numeric($buscar_exacto)){
        $where = "WHERE pc.id_preceptorxcurso = '$buscar_exacto' OR p.DNI_U = '$buscar_exacto' OR c.ID_curso = '$buscar_exacto'";
    } else {
        $buscar = mysqli_real_escape_string($con, $buscar_exacto);
        $where = "WHERE (p.Apellido LIKE '%$buscar%' OR p.Nombre LIKE '%$buscar%' OR CONCAT(c.curso, c.division) LIKE '%$buscar%')";
    }
} else {
    if(!empty($filtro_preceptor)){
        $preceptor = mysqli_real_escape_string($con, $filtro_preceptor);
        $where .= " AND (p.Apellido LIKE '%$preceptor%' OR p.Nombre LIKE '%$preceptor%')";
    }
    if(!empty($filtro_curso)){
        $curso = mysqli_real_escape_string($con, $filtro_curso);
        $where .= " AND CONCAT(c.curso, c.division) LIKE '%$curso%'";
    }
    if(!empty($filtro_id)){
        $where .= " AND pc.id_preceptorxcurso = '$filtro_id'";
    }
}

// Consulta principal
$query = "SELECT 
    pc.id_preceptorxcurso,
    p.DNI_U as dni_preceptor,
    p.Nombre as nombre_preceptor,
    p.Apellido as apellido_preceptor,
    c.ID_curso as id_curso,
    c.curso as curso_numero,
    c.division as curso_division,
    c.turno as curso_turno
    FROM preceptorxcurso pc
    INNER JOIN usuario p ON pc.id_preceptor = p.DNI_U AND p.ID_rol = 1
    INNER JOIN curso c ON pc.id_curso = c.ID_curso
    $where 
    ORDER BY c.curso, c.division, p.Apellido";

$res = mysqli_query($con, $query);

// Total de asignaciones
$query_total = "SELECT COUNT(*) as total FROM preceptorxcurso";
$res_total = mysqli_query($con, $query_total);
$total_asignaciones = mysqli_fetch_assoc($res_total)['total'];
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, minimum-scale=1.0">
    <link rel="stylesheet" href="../../recursos/styles.css">
    <title>Listado de Asignaciones Preceptor x Curso</title>
    <style>
        .filtros-avanzados {
            display: <?php echo ($mostrar_filtros == '1') ? 'block' : 'none'; ?>;
        }
        .btn-filtros {
            background: #710A14;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 5px;
            cursor: pointer;
            margin-bottom: 10px;
        }
        .btn-filtros:hover {
            background: #3F070B;
        }
        .btn-borrar {
            background: #818582;
            color: white;
            padding: 5px 10px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        .btn-borrar:hover {
            background: #710A14;
        }
    </style>
</head>
<body>
    <div class="caja">
        <h1>Listado de Asignaciones Preceptor x Curso</h1>
        
        <!-- Búsqueda rápida -->
        <div class="filtro-rapido">
            <form method="GET" action="">
                <label for="buscar_exacto"><strong>Búsqueda rápida:</strong></label>
                <input type="text" name="buscar_exacto" id="buscar_exacto" placeholder="ID, preceptor, curso..." value="<?php echo htmlspecialchars($buscar_exacto); ?>">
                <button type="submit">Buscar</button>
                <?php if(!empty($buscar_exacto)): ?>
                    <a href="listado_preceptorxcurso.php"><button type="button">Limpiar búsqueda</button></a>
                <?php endif; ?>
                <button type="button" class="btn-filtros" onclick="toggleFiltros()">🔽 Filtros Avanzados</button>
            </form>
        </div>
        
        <!-- Filtros avanzados -->
        <div class="filtros-avanzados" id="filtrosAvanzados">
            <div class="filtros">
                <form method="GET" action="">
                    <h3>Filtros Avanzados</h3>
                    
                    <div>
                        <label>Preceptor:</label>
                        <input type="text" name="filtro_preceptor" placeholder="Apellido o nombre" value="<?php echo htmlspecialchars($filtro_preceptor); ?>">
                        
                        <label>Curso:</label>
                        <input type="text" name="filtro_curso" placeholder="Ej: 1A, 2B, 3C..." value="<?php echo htmlspecialchars($filtro_curso); ?>">
                        
                        <label>ID Asignación:</label>
                        <input type="number" name="filtro_id" placeholder="ID" min="1" value="<?php echo htmlspecialchars($filtro_id); ?>">
                    </div>
                    
                    <input type="hidden" name="mostrar_filtros" value="1">
                    
                    <div style="margin-top: 10px;">
                        <button type="submit">Aplicar Filtros</button>
                        <a href="listado_preceptorxcurso.php"><button type="button">Limpiar Filtros</button></a>
                    </div>
                </form>
            </div>
        </div>
        
        <div class="tabla-container">
            <table id="listado" class="tabla">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Preceptor</th>
                        <th>DNI Preceptor</th>
                        <th>Curso</th>
                        <th>Turno</th>
                        <th class="no-exportar">Eliminar</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    if($res && mysqli_num_rows($res) > 0){
                        while($fila = mysqli_fetch_assoc($res)){
                            $turno_texto = ($fila['curso_turno'] == 'M') ? 'Mañana' : 'Tarde';
                            $curso_completo = $fila['curso_numero'] . '° "' . $fila['curso_division'] . '"';
                            
                            echo '<tr>';
                            echo '<td>' . $fila['id_preceptorxcurso'] . '</td>';
                            echo '<td><strong>' . htmlspecialchars($fila['apellido_preceptor'] . ', ' . $fila['nombre_preceptor']) . '</strong></td>';
                            echo '<td>' . $fila['dni_preceptor'] . '</td>';
                            echo '<td>' . $curso_completo . '</td>';
                            echo '<td>' . $turno_texto . '</td>';
                            echo '<td class="no-exportar">';
                            if($_SESSION['rol'] == 'Admin' || $_SESSION['rol'] == 'Secretario'){
                                echo '<a href="borrar_preceptorxcurso.php?id=' . $fila['id_preceptorxcurso'] . '" onclick="return confirm(\'¿Está seguro que desea eliminar esta asignación?\\n\\nPreceptor: ' . htmlspecialchars($fila['apellido_preceptor'] . ', ' . $fila['nombre_preceptor']) . '\\nCurso: ' . $curso_completo . '\')"><button class="btn-borrar">Eliminar</button></a>';
                            } else {
                                echo '<button class="btn-borrar" disabled>Eliminar</button>';
                            }
                            echo '</td>';
                            echo '</tr>';
                        }
                    } else {
                        echo '<tr><td colspan="6" style="text-align: center;">No se encontraron asignaciones con los filtros aplicados.</td></tr>';
                    }
                    ?>
                </tbody>
            </table>
        </div>
        
        <p>
            <?php if($_SESSION['rol'] == 'Admin' || $_SESSION['rol'] == 'Secretario'): ?>
                <a href="preceptorxcurso.php"><button>Agregar Nueva Asignación</button></a>
            <?php endif; ?>
            <a href="../../recursos/panel.php"><button>Volver al Panel</button></a>
        </p>
    </div>
    
    <script>
        function toggleFiltros() {
            var filtros = document.getElementById('filtrosAvanzados');
            if(filtros.style.display === 'none' || filtros.style.display === '') {
                filtros.style.display = 'block';
            } else {
                filtros.style.display = 'none';
            }
        }
    </script>
</body>
</html>
<?php mysqli_close($con); ?>