<?php
session_start();

// Guardar filtros en sesión cuando se envía el formulario
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (isset($_GET['filtro_nombre']) || isset($_GET['filtro_apellido']) || isset($_GET['filtro_dni']) || 
        isset($_GET['filtro_rol']) || isset($_GET['filtro_estado']) || isset($_GET['filtro_curso']) ||
        isset($_GET['buscar_exacto']) || isset($_GET['aplicar_filtros'])) {
        
        $_SESSION['filtros_usuarios'] = [
            'buscar_exacto' => $_GET['buscar_exacto'] ?? '',
            'filtro_nombre' => $_GET['filtro_nombre'] ?? '',
            'filtro_apellido' => $_GET['filtro_apellido'] ?? '',
            'filtro_dni' => $_GET['filtro_dni'] ?? '',
            'filtro_rol' => $_GET['filtro_rol'] ?? '',
            'filtro_estado' => $_GET['filtro_estado'] ?? '',
            'filtro_curso' => $_GET['filtro_curso'] ?? ''
        ];
    }
    
    if (isset($_GET['limpiar']) && $_GET['limpiar'] == '1') {
        unset($_SESSION['filtros_usuarios']);
    }
}

if(isset($_SESSION["dni"]) && (time() - $_SESSION["ultima_actividad"] > 18000)){
    echo '<script>alert("Se venció el tiempo de la sesion"); window.location="../../index.php";</script>';
    exit();
}

if(!isset($_SESSION["dni"])){
    echo '<script>alert("Se venció el tiempo de la sesion"); window.location="../../index.php";</script>';
    exit();
}

include '../../recursos/conexion.php';

// Cargar filtros desde sesión si no vienen por GET
if (empty($_GET) && isset($_SESSION['filtros_usuarios'])) {
    $_GET = $_SESSION['filtros_usuarios'];
}

// Obtener filtros
$buscar_exacto = $_GET['buscar_exacto'] ?? '';
$filtro_nombre = $_GET['filtro_nombre'] ?? '';
$filtro_apellido = $_GET['filtro_apellido'] ?? '';
$filtro_dni = $_GET['filtro_dni'] ?? '';
$filtro_rol = $_GET['filtro_rol'] ?? '';
$filtro_estado = $_GET['filtro_estado'] ?? '';
$filtro_curso = $_GET['filtro_curso'] ?? '';

$mostrar_listado = false;
if(isset($_GET['aplicar_filtros']) || (isset($_SESSION['filtros_usuarios']) && !isset($_GET['limpiar']))) {
    $mostrar_listado = true;
}

// ============================================
// CONSTRUIR CONSULTA PRINCIPAL
// ============================================
$where = "WHERE 1=1";

if(!empty($buscar_exacto)){
    if(is_numeric($buscar_exacto)){
        $where = "WHERE u.DNI_U = '$buscar_exacto'";
    } else {
        $buscar_esc = mysqli_real_escape_string($con, $buscar_exacto);
        $where = "WHERE (LOWER(CONCAT(u.Nombre, ' ', u.Apellido)) LIKE LOWER('%$buscar_esc%') 
                   OR LOWER(u.email) LIKE LOWER('%$buscar_esc%')
                   OR u.DNI_U LIKE '%$buscar_esc%')";
    }
} else {
    if(!empty($filtro_nombre)){
        $filtro_nombre_esc = mysqli_real_escape_string($con, $filtro_nombre);
        $where .= " AND LOWER(u.Nombre) LIKE LOWER('%$filtro_nombre_esc%')";
    }
    
    if(!empty($filtro_apellido)){
        $filtro_apellido_esc = mysqli_real_escape_string($con, $filtro_apellido);
        $where .= " AND LOWER(u.Apellido) LIKE LOWER('%$filtro_apellido_esc%')";
    }
    
    if(!empty($filtro_dni)){
        $where .= " AND u.DNI_U = '$filtro_dni'";
    }
    
    if(!empty($filtro_rol)){
        $where .= " AND u.ID_rol = '$filtro_rol'";
    }
    
    if(!empty($filtro_estado)){
        $where .= " AND u.ID_Estado = '$filtro_estado'";
    }
    
    if(!empty($filtro_curso)){
        $where .= " AND u.id_curso = '$filtro_curso'";
    }
}

// ============================================
// CONSULTA PRINCIPAL CON JOIN A ROL, ESTADO Y CURSO
// ============================================
$query = "SELECT u.DNI_U, u.Nombre, u.Apellido, u.email, u.clave, u.ID_Estado, u.ID_rol, u.id_curso,
                 r.nom_rol as Rol,
                 e.nom_estado as Estado,
                 c.curso as curso_numero,
                 c.division as curso_division,
                 c.turno as curso_turno
          FROM usuario u
          LEFT JOIN rol r ON u.ID_rol = r.ID_rol
          LEFT JOIN estado e ON u.ID_Estado = e.ID_estado
          LEFT JOIN curso c ON u.id_curso = c.ID_curso
          $where
          ORDER BY u.Apellido, u.Nombre";

$res = mysqli_query($con, $query);

// Verificar si la consulta falló
if(!$res){
    die("Error en la consulta: " . mysqli_error($con));
}

// Obtener lista de roles para filtro
$query_roles = "SELECT ID_rol, nom_rol FROM rol ORDER BY nom_rol";
$res_roles = mysqli_query($con, $query_roles);

// Obtener lista de estados para filtro
$query_estados = "SELECT ID_estado, nom_estado FROM estado ORDER BY nom_estado";
$res_estados = mysqli_query($con, $query_estados);

// Obtener lista de cursos para filtro
$query_cursos = "SELECT ID_curso, curso, division, turno FROM curso ORDER BY curso, division";
$res_cursos = mysqli_query($con, $query_cursos);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, minimum-scale=1.0">
    <link rel="stylesheet" href="../../recursos/styles.css">
    <title>Listado de Usuarios</title>
    <style>
        .tabla-container {
            width: 100%;
            overflow-x: auto;
            overflow-y: visible;
            -webkit-overflow-scrolling: touch;
            margin-bottom: 20px;
        }
        .tabla {
            width: 100%;
            min-width: 1000px;
            border-collapse: collapse;
            font-size: 13px;
        }
        .tabla th, .tabla td {
            border: 1px solid #ddd;
            padding: 10px 8px;
            text-align: left;
            vertical-align: middle;
        }
        .tabla th {
            background: #710A14;
            color: white;
            font-weight: 600;
        }
        .tabla tr:hover td {
            background: #fdf5f5;
        }
        .btn-editar {
            background: #2196F3;
            color: white;
            border: none;
            padding: 5px 10px;
            border-radius: 4px;
            cursor: pointer;
        }
        .btn-editar:hover {
            background: #1976D2;
        }
        .estado-Activo { color: #2e7d32; font-weight: bold; }
        .estado-Inactivo { color: #d32f2f; font-weight: bold; }
        
        @media (max-width: 768px) {
            .tabla th, .tabla td {
                padding: 6px 4px;
                font-size: 11px;
            }
        }
    </style>
</head>
<body>
    <div class="caja caja-listado">
        <h1>Listado de Usuarios</h1>
        
        <br>
        
        <center>
            <p>
                <a href="../../recursos/panel.php"><button>ir al Panel</button></a>
            </p>
        </center>
        
        <br>
        
        <!-- Filtros avanzados -->
        <div class="filtros">
            <form method="GET" action="">
                <h3>Filtros</h3>
                
                <div style="display: flex; flex-wrap: wrap; gap: 15px;">
                    <div>
                        <label>DNI:</label>
                        <input type="text" name="filtro_dni" placeholder="DNI" value="<?php echo htmlspecialchars($filtro_dni); ?>">
                    </div>
                    
                    <div>
                        <label>Nombre:</label>
                        <input type="text" name="filtro_nombre" placeholder="Nombre" value="<?php echo htmlspecialchars($filtro_nombre); ?>">
                    </div>
                    
                    <div>
                        <label>Apellido:</label>
                        <input type="text" name="filtro_apellido" placeholder="Apellido" value="<?php echo htmlspecialchars($filtro_apellido); ?>">
                    </div>
                </div>
                
                <div style="margin-top: 10px; display: flex; flex-wrap: wrap; gap: 15px;">
                    <div>
                        <label>Rol:</label>
                        <select name="filtro_rol">
                            <option value="">Todos los roles</option>
                            <?php
                            mysqli_data_seek($res_roles, 0);
                            while($fila_rol = mysqli_fetch_array($res_roles)){
                                $selected = ($filtro_rol == $fila_rol['ID_rol']) ? 'selected' : '';
                                echo '<option value="' . $fila_rol['ID_rol'] . '" ' . $selected . '>' . htmlspecialchars($fila_rol['nom_rol']) . '</option>';
                            }
                            ?>
                        </select>
                    </div>
                    
                    <div>
                        <label>Estado:</label>
                        <select name="filtro_estado">
                            <option value="">Todos los estados</option>
                            <?php
                            mysqli_data_seek($res_estados, 0);
                            while($fila_estado = mysqli_fetch_array($res_estados)){
                                $selected = ($filtro_estado == $fila_estado['ID_estado']) ? 'selected' : '';
                                echo '<option value="' . $fila_estado['ID_estado'] . '" ' . $selected . '>' . htmlspecialchars($fila_estado['nom_estado']) . '</option>';
                            }
                            ?>
                        </select>
                    </div>
                    
                    <div>
                        <label>Curso:</label>
                        <select name="filtro_curso">
                            <option value="">Todos los cursos</option>
                            <?php
                            mysqli_data_seek($res_cursos, 0);
                            while($fila_curso = mysqli_fetch_array($res_cursos)){
                                $turno_texto = $fila_curso['turno'] == 'M' ? 'Mañana' : 'Tarde';
                                $curso_completo = $fila_curso['curso'] . '° "' . $fila_curso['division'] . '" - ' . $turno_texto;
                                $selected = ($filtro_curso == $fila_curso['ID_curso']) ? 'selected' : '';
                                echo '<option value="' . $fila_curso['ID_curso'] . '" ' . $selected . '>' . htmlspecialchars($curso_completo) . '</option>';
                            }
                            ?>
                        </select>
                    </div>
                </div>
                
                <div style="margin-top: 15px; display: flex; gap: 10px; flex-wrap: wrap;">
                    <button type="submit" name="aplicar_filtros" value="1">Aplicar Filtros</button>
                    <a href="?limpiar=1">
                        <button type="button">Limpiar Filtros</button>
                    </a>
                </div>
            </form>
        </div>
        
        <?php if($mostrar_listado): ?>
            <div class="tabla-container">
                <table id="listado" class="tabla">
                    <thead>
                        <tr>
                            <th>DNI</th>
                            <th>Nombre</th>
                            <th>Apellido</th>
                            <th>Email</th>
                            <th>Rol</th>
                            <th>Estado</th>
                            <th>Curso</th>
                            <th class="no-exportar">Editar</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        if($res && mysqli_num_rows($res) > 0){
                            while($fila = mysqli_fetch_array($res)){
                                $claseEstado = 'estado-' . str_replace(' ', '-', $fila['Estado']);
                                
                                // Determinar texto del curso
                                $curso_texto = "";
                                if($fila['id_curso'] == 0 || empty($fila['id_curso'])){
                                    $curso_texto = 'Sin curso';
                                } elseif(!empty($fila['curso_numero'])){
                                    $turno_texto = $fila['curso_turno'] == 'M' ? 'Mañana' : 'Tarde';
                                    $curso_texto = $fila['curso_numero'] . '° "' . $fila['curso_division'] . '" - ' . $turno_texto;
                                } else {
                                    $curso_texto = 'Curso no encontrado';
                                }
                                
                                echo '<tr>';
                                echo '<td>' . $fila['DNI_U'] . '</td>';
                                echo '<td>' . htmlspecialchars($fila['Nombre']) . '</td>';
                                echo '<td>' . htmlspecialchars($fila['Apellido']) . '</td>';
                                echo '<td>' . htmlspecialchars($fila['email']) . '</td>';
                                echo '<td>' . htmlspecialchars($fila['Rol']) . '</td>';
                                echo '<td class="' . $claseEstado . '">' . htmlspecialchars($fila['Estado']) . '</td>';
                                echo '<td>' . htmlspecialchars($curso_texto) . '</td>';
                                echo '<td class="no-exportar">
                                    <a href="editar_usuario.php?id=' . $fila['DNI_U'] . '">
                                        <button class="btn-editar">Editar</button>
                                    </a>
                                </td>';
                                echo '</tr>';
                            }
                        } else {
                            echo '<tr><td colspan="8" style="text-align: center;">No se encontraron usuarios con los filtros aplicados.</td></tr>';
                        }
                        ?>
                    </tbody>
                </table>
            </div>
            
            <p>
                <?php if($_SESSION['rol'] == 'Admin'){ ?>
                    <a href="usuario.php"><button>Volver</button></a>
                <?php } ?>
                <a href="../../recursos/panel.php"><button>ir al Panel</button></a>
                <button onclick="generarPDF()">Exportar a PDF</button>
            </p>
        <?php else: ?>
            <div style="text-align: center; padding: 40px; background: #f5f5f5; border-radius: 5px; margin-top: 20px;">
                <p>Complete los filtros y presione <strong>"Aplicar Filtros"</strong> para ver el listado de usuarios.</p>
            </div>
            
            <p>
                <?php if($_SESSION['rol'] == 'Admin'){ ?>   
                    <a href="usuario.php"><button>Volver</button></a>
                <?php } ?>
                <a href="../../recursos/panel.php"><button>ir al Panel</button></a>
            </p>
        <?php endif; ?>
    </div>
    
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.28/jspdf.plugin.autotable.min.js"></script>
    
    <script>
        function generarPDF() {
            const { jsPDF } = window.jspdf;
            const doc = new jsPDF('l');
            
            const table = document.getElementById('listado');
            const rows = table.querySelectorAll('tr');
            const data = [];
            
            rows.forEach(row => {
                const rowData = [];
                row.querySelectorAll('th:not(.no-exportar), td:not(.no-exportar)').forEach(cell => {
                    rowData.push(cell.innerText);
                });
                data.push(rowData);
            });
            
            doc.autoTable({
                head: [data[0]],
                body: data.slice(1), 
                styles: {
                    textColor: [0, 0, 0], 
                    fillColor: [255, 255, 255] 
                },
                headStyles: {
                    fillColor: [200, 200, 200] 
                }
            });
            
            doc.save('listado_usuarios.pdf');
        }
    </script>
</body>
</html>
<?php
mysqli_close($con);
?>