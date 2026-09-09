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
if($_SESSION['rol'] != 'Admin' && $_SESSION['rol'] != 'Preceptor'){
    echo '<script>alert("No tiene permisos para ver talleres"); window.location="../../recursos/panel.php";</script>';
    exit();
}

include '../../recursos/conexion.php';

// Obtener filtros
$buscar_exacto = $_GET['buscar_exacto'] ?? '';
$filtro_nombre = $_GET['filtro_nombre'] ?? '';
$filtro_id = $_GET['filtro_id'] ?? '';
$filtro_anio = $_GET['filtro_anio'] ?? '';
$filtro_estado = $_GET['filtro_estado'] ?? '';

// Construir consulta con filtros
$where = "WHERE 1=1";

if(!empty($buscar_exacto)){
    if(is_numeric($buscar_exacto)){
        $where = "WHERE t.ID_taller = '$buscar_exacto'";
    } else {
        $where = "WHERE LOWER(t.nombre) LIKE LOWER('%" . mysqli_real_escape_string($con, $buscar_exacto) . "%') OR LOWER(t.descripcion) LIKE LOWER('%" . mysqli_real_escape_string($con, $buscar_exacto) . "%')";
    }
} else {
    if(!empty($filtro_nombre)){
        $where .= " AND LOWER(t.nombre) LIKE LOWER('%" . mysqli_real_escape_string($con, $filtro_nombre) . "%')";
    }
    
    if(!empty($filtro_id)){
        $where .= " AND t.ID_taller = '$filtro_id'";
    }
    
    if(!empty($filtro_anio)){
        $where .= " AND t.anio_taller = '$filtro_anio'";
    }
    
    if($filtro_estado !== ''){
        $where .= " AND t.activo = '$filtro_estado'";
    }
}

// Consulta principal con estadísticas de uso
$query = "SELECT 
    t.ID_taller, 
    t.nombre, 
    t.anio_taller, 
    t.descripcion, 
    t.activo,
    (SELECT COUNT(DISTINCT dtc.ID_docente) FROM docente_taller_curso dtc WHERE dtc.ID_taller = t.ID_taller) as total_docentes,
    (SELECT COUNT(DISTINCT dtc.ID_curso) FROM docente_taller_curso dtc WHERE dtc.ID_taller = t.ID_taller) as total_cursos
    FROM talleres t 
    $where 
    ORDER BY t.anio_taller, t.nombre";

$res = mysqli_query($con, $query);

// Obtener total de talleres
$query_total = "SELECT COUNT(*) as total FROM talleres";
$res_total = mysqli_query($con, $query_total);
$total_talleres = mysqli_fetch_array($res_total)['total'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, minimum-scale=1.0">
    <link rel="stylesheet" href="../../recursos/styles.css">
    <title>Listado de Talleres</title>
    <style>
        .activo { color: #4CAF50; font-weight: bold; }
        .inactivo { color: #F44336; font-weight: bold; }
        .badge-primero { background: #2196F3; color: white; padding: 2px 8px; border-radius: 12px; font-size: 11px; }
        .badge-segundo { background: #FF9800; color: white; padding: 2px 8px; border-radius: 12px; font-size: 11px; }
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
        <h1>Listado de Talleres</h1>
        
        <!-- Resumen -->
        <div class="resumen">
            <strong>Total de talleres en el sistema: <?php echo $total_talleres; ?></strong>
        </div>
        
        <!-- Búsqueda rápida -->
        <div class="filtro-rapido">
            <form method="GET" action="">
                <label for="buscar_exacto"><strong>Búsqueda rápida:</strong></label>
                <input type="text" name="buscar_exacto" id="buscar_exacto" placeholder="ID, nombre o descripción" value="<?php echo htmlspecialchars($buscar_exacto); ?>">
                <button type="submit">Buscar</button>
                <?php if(!empty($buscar_exacto)): ?>
                    <a href="listado_talleres.php"><button type="button">Limpiar búsqueda</button></a>
                <?php endif; ?>
            </form>
        </div>
        
        <!-- Filtros avanzados -->
        <div class="filtros">
            <form method="GET" action="">
                <h3>Filtros Avanzados</h3>
                
                <div>
                    <label>Nombre (contiene):</label>
                    <input type="text" name="filtro_nombre" placeholder="Ej: Electricidad" value="<?php echo htmlspecialchars($filtro_nombre); ?>">
                    
                    <label>ID Taller:</label>
                    <input type="number" name="filtro_id" placeholder="Ej: 1" min="1" value="<?php echo htmlspecialchars($filtro_id); ?>">
                </div>
                
                <div style="margin-top: 10px;">
                    <label>Año:</label>
                    <select name="filtro_anio">
                        <option value="">Todos</option>
                        <option value="I" <?php echo $filtro_anio == 'I' ? 'selected' : ''; ?>>I (Primer año)</option>
                        <option value="II" <?php echo $filtro_anio == 'II' ? 'selected' : ''; ?>>II (Segundo año)</option>
                    </select>
                    
                    <label>Estado:</label>
                    <select name="filtro_estado">
                        <option value="">Todos</option>
                        <option value="1" <?php echo $filtro_estado === '1' ? 'selected' : ''; ?>>Activo</option>
                        <option value="0" <?php echo $filtro_estado === '0' ? 'selected' : ''; ?>>Inactivo</option>
                    </select>
                </div>
                
                <div style="margin-top: 10px;">
                    <button type="submit">Aplicar Filtros</button>
                    <a href="listado_talleres.php"><button type="button">Limpiar Filtros</button></a>
                </div>
            </form>
        </div>
        
<div class="tabla-container">
        <table class="tabla" id="listado">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nombre del Taller</th>
                    <th>Año</th>
                    <th>Descripción</th>
                    <th>Docentes</th>
                    <th>Cursos</th>
                    <th>Estado</th>
                    <th class="no-exportar">Editar</th>
                    <th class="no-exportar">Eliminar</th>
                </tr>
            </thead>
            <tbody>
                <?php
                if($res && mysqli_num_rows($res) > 0){
                    $contador = 0;
                    while($fila = mysqli_fetch_array($res)){
                        $contador++;
                        $anio_clase = $fila['anio_taller'] == 'I' ? 'badge-primero' : 'badge-segundo';
                        $estado_texto = $fila['activo'] == 1 ? 'Activo' : 'Inactivo';
                        $estado_clase = $fila['activo'] == 1 ? 'activo' : 'inactivo';
                        $tiene_referencias = ($fila['total_docentes'] > 0 || $fila['total_cursos'] > 0);
                        
                        echo '<tr>';
                        echo '<td>' . $fila['ID_taller'] . '</td>';
                        echo '<td><strong>' . htmlspecialchars($fila['nombre']) . '</strong></td>';
                        echo '<td><span class="' . $anio_clase . '">' . $fila['anio_taller'] . '</span></td>';
                        echo '<td>' . (!empty($fila['descripcion']) ? htmlspecialchars($fila['descripcion']) : '<span style="color: #888; font-style: italic;">Sin descripción</span>') . '</td>';
                        echo '<td>' . $fila['total_docentes'] . '</td>';
                        echo '<td>' . $fila['total_cursos'] . '</td>';
                        echo '<td class="' . $estado_clase . '">' . $estado_texto . '</td>';
                        
                        // Botón Editar
                        echo '<td class="no-exportar">';
                        echo '<a href="editar_taller.php?id=' . $fila['ID_taller'] . '"><button>Editar</button></a>';
                        echo '</td>';
                        
                        // Botón Eliminar (solo si no tiene referencias)
                        echo '<td class="no-exportar">';
                        if(!$tiene_referencias){
                            echo '<a href="borrar_taller.php?id=' . $fila['ID_taller'] . '" onclick="return confirm(\'¿Está seguro que desea eliminar este taller?\')"><button>Eliminar</button></a>';
                        } else {
                            echo '<button disabled title="No se puede eliminar porque tiene docentes o cursos asignados">Eliminar</button>';
                        }
                        echo '</td>';
                        echo '</tr>';
                    }
                } else {
                    echo '<tr><td colspan="9">No se encontraron talleres con los filtros aplicados.</td></tr>';
                }
                ?>
            </tbody>
        </table>
</div>
        
        <p>
            <a href="talleres.php"><button>Agregar Nuevo Taller</button></a>
            <a href="../../recursos/panel.php"><button>Volver al Panel</button></a>
            <button onclick="generarPDF()">Exportar a PDF</button>
            <button onclick="exportarExcel()">Exportar a Excel</button>
        </p>
    </div>
    
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.28/jspdf.plugin.autotable.min.js"></script>
    <script src="https://unpkg.com/xlsx/dist/xlsx.full.min.js"></script>
    
    <script>
        function generarPDF() {
            const { jsPDF } = window.jspdf;
            const doc = new jsPDF('landscape');
            
            doc.setFontSize(16);
            doc.setFont(undefined, 'bold');
            doc.text('Listado de Talleres - EPET 34', 14, 15);
            
            doc.setFontSize(10);
            doc.setFont(undefined, 'normal');
            doc.text('Generado: ' + new Date().toLocaleString(), 14, 22);
            
            const table = document.getElementById('listado');
            const rows = table.querySelectorAll('tr');
            const data = [];
            
            rows.forEach(row => {
                const rowData = [];
                row.querySelectorAll('th:not(.no-exportar), td:not(.no-exportar)').forEach(cell => {
                    rowData.push(cell.innerText);
                });
                if(rowData.length > 0) data.push(rowData);
            });
            
            doc.autoTable({
                startY: 30,
                head: [data[0]],
                body: data.slice(1),
                theme: 'grid',
                styles: { fontSize: 8, cellPadding: 4 },
                headStyles: { fillColor: [63, 7, 11] }
            });
            
            doc.save('listado_talleres.pdf');
        }
        
        function exportarExcel() {
            const tablaOriginal = document.getElementById('listado');
            const tablaClon = tablaOriginal.cloneNode(true);
            
            const filas = tablaClon.querySelectorAll('tr');
            filas.forEach(fila => {
                const celdas = fila.querySelectorAll('th, td');
                for(let i = celdas.length - 1; i >= 0; i--){
                    if(celdas[i].classList.contains('no-exportar')){
                        celdas[i].remove();
                    }
                }
            });
            
            const wb = XLSX.utils.book_new();
            const ws = XLSX.utils.table_to_sheet(tablaClon);
            XLSX.utils.book_append_sheet(wb, ws, "Talleres");
            XLSX.writeFile(wb, 'listado_talleres.xlsx');
        }
    </script>
</body>
</html>
<?php
mysqli_close($con);
?>