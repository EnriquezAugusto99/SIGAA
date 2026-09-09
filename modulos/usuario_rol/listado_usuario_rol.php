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
if($_SESSION['rol'] != 'Admin'){
    echo '<script>alert("No tiene permisos para ver roles adicionales"); window.location="../../recursos/panel.php";</script>';
    exit();
}

include '../../recursos/conexion.php';

// Obtener filtros
$buscar_exacto = $_GET['buscar_exacto'] ?? '';
$filtro_usuario = $_GET['filtro_usuario'] ?? '';
$filtro_rol = $_GET['filtro_rol'] ?? '';
$filtro_id = $_GET['filtro_id'] ?? '';
$mostrar_filtros = isset($_GET['mostrar_filtros']) ? $_GET['mostrar_filtros'] : '0';

// Construir consulta WHERE
$where = "WHERE 1=1";

if(!empty($buscar_exacto)){
    if(is_numeric($buscar_exacto)){
        $where = "WHERE ur.id_usuario_rol = '$buscar_exacto' OR u.DNI_U = '$buscar_exacto'";
    } else {
        $buscar = mysqli_real_escape_string($con, $buscar_exacto);
        $where = "WHERE (u.Apellido LIKE '%$buscar%' OR u.Nombre LIKE '%$buscar%' OR r.nom_rol LIKE '%$buscar%')";
    }
} else {
    if(!empty($filtro_usuario)){
        $usuario = mysqli_real_escape_string($con, $filtro_usuario);
        $where .= " AND (u.Apellido LIKE '%$usuario%' OR u.Nombre LIKE '%$usuario%')";
    }
    if(!empty($filtro_rol)){
        $rol = mysqli_real_escape_string($con, $filtro_rol);
        $where .= " AND r.nom_rol LIKE '%$rol%'";
    }
    if(!empty($filtro_id)){
        $where .= " AND ur.id_usuario_rol = '$filtro_id'";
    }
}

// Consulta principal
$query = "SELECT 
    ur.id_usuario_rol,
    ur.dni_usuario,
    ur.id_rol,
    ur.activo,
    ur.fecha_asignacion,
    u.Nombre as user_nombre,
    u.Apellido as user_apellido,
    u.ID_rol as rol_principal_id,
    rp.nom_rol as rol_principal_nombre,
    r.nom_rol as rol_adicional_nombre
    FROM usuario_rol ur
    INNER JOIN usuario u ON ur.dni_usuario = u.DNI_U
    INNER JOIN rol r ON ur.id_rol = r.ID_rol
    INNER JOIN rol rp ON u.ID_rol = rp.ID_rol
    $where 
    ORDER BY u.Apellido, u.Nombre, r.nom_rol";

$res = mysqli_query($con, $query);

// Total de asignaciones
$query_total = "SELECT COUNT(*) as total FROM usuario_rol";
$res_total = mysqli_query($con, $query_total);
$total_asignaciones = mysqli_fetch_assoc($res_total)['total'];
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, minimum-scale=1.0">
    <link rel="stylesheet" href="../../recursos/styles.css">
    <title>Listado de Roles Adicionales</title>
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
        .badge-principal {
            background: #2e7d32;
            color: white;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 11px;
        }
        .badge-adicional {
            background: #ff9800;
            color: #1a2a3a;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 11px;
        }
        .badge-activo {
            background: #2e7d32;
            color: white;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 11px;
        }
        .badge-inactivo {
            background: #d32f2f;
            color: white;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 11px;
        }
    </style>
</head>
<body>
    <div class="caja">
        <h1>Listado de Roles Adicionales de Usuarios</h1>
        
        <!-- Búsqueda rápida -->
        <div class="filtro-rapido">
            <form method="GET" action="">
                <label for="buscar_exacto"><strong>Búsqueda rápida:</strong></label>
                <input type="text" name="buscar_exacto" id="buscar_exacto" placeholder="ID, usuario, rol..." value="<?php echo htmlspecialchars($buscar_exacto); ?>">
                <button type="submit">Buscar</button>
                <?php if(!empty($buscar_exacto)): ?>
                    <a href="listado_usuario_rol.php"><button type="button">Limpiar búsqueda</button></a>
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
                        <label>Usuario:</label>
                        <input type="text" name="filtro_usuario" placeholder="Apellido o nombre" value="<?php echo htmlspecialchars($filtro_usuario); ?>">
                        
                        <label>Rol Adicional:</label>
                        <input type="text" name="filtro_rol" placeholder="Nombre del rol" value="<?php echo htmlspecialchars($filtro_rol); ?>">
                        
                        <label>ID Asignación:</label>
                        <input type="number" name="filtro_id" placeholder="ID" min="1" value="<?php echo htmlspecialchars($filtro_id); ?>">
                    </div>
                    
                    <input type="hidden" name="mostrar_filtros" value="1">
                    
                    <div style="margin-top: 10px;">
                        <button type="submit">Aplicar Filtros</button>
                        <a href="listado_usuario_rol.php"><button type="button">Limpiar Filtros</button></a>
                    </div>
                </form>
            </div>
        </div>
        
        <div class="tabla-container">
            <table id="listado" class="tabla">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Usuario</th>
                        <th>DNI</th>
                        <th>Rol Principal</th>
                        <th>Rol Adicional</th>
                        <th>Estado</th>
                        <th>Fecha Asignación</th>
                        <th class="no-exportar">Eliminar</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    if($res && mysqli_num_rows($res) > 0){
                        while($fila = mysqli_fetch_assoc($res)){
                            $estado_class = ($fila['activo'] == 1) ? 'badge-activo' : 'badge-inactivo';
                            $estado_texto = ($fila['activo'] == 1) ? 'Activo' : 'Inactivo';
                            
                            echo '<tr>';
                            echo '<td>' . $fila['id_usuario_rol'] . '</td>';
                            echo '<td><strong>' . htmlspecialchars($fila['user_apellido'] . ', ' . $fila['user_nombre']) . '</strong></td>';
                            echo '<td>' . $fila['dni_usuario'] . '</td>';
                            echo '<td><span class="badge-principal">' . $fila['rol_principal_nombre'] . '</span></td>';
                            echo '<td><span class="badge-adicional">' . $fila['rol_adicional_nombre'] . '</span></td>';
                            echo '<td><span class="' . $estado_class . '">' . $estado_texto . '</span></td>';
                            echo '<td>' . date('d/m/Y H:i', strtotime($fila['fecha_asignacion'])) . '</td>';
                            echo '<td class="no-exportar">';
                            echo '<a href="borrar_usuario_rol.php?id=' . $fila['id_usuario_rol'] . '&dni=' . $fila['dni_usuario'] . '" onclick="return confirm(\'¿Está seguro que desea eliminar este rol adicional?\\n\\nUsuario: ' . htmlspecialchars($fila['user_apellido'] . ', ' . $fila['user_nombre']) . '\\nRol adicional: ' . $fila['rol_adicional_nombre'] . '\')"><button class="btn-borrar">Eliminar</button></a>';
                            echo '</td>';
                            echo '</tr>';
                        }
                    } else {
                        echo '<tr><td colspan="8" style="text-align: center;">No se encontraron asignaciones de roles adicionales.</td></tr>';
                    }
                    ?>
                </tbody>
            </table>
        </div>
        
        <p>
            <a href="usuario_rol.php"><button>Agregar Nueva Asignación</button></a>
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