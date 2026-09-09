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
if($_SESSION['rol'] != 'Admin' && $_SESSION['rol'] != 'Preceptor' && $_SESSION['rol'] != 'Secretario' && $_SESSION['rol'] != 'Profesor'){
    echo '<script>alert("No tiene permisos para ver relaciones tutor-alumno"); window.location="../../recursos/panel.php";</script>';
    exit();
}

include '../../recursos/conexion.php';

// Obtener filtros
$buscar_exacto = $_GET['buscar_exacto'] ?? '';
$filtro_alumno = $_GET['filtro_alumno'] ?? '';
$filtro_tutor = $_GET['filtro_tutor'] ?? '';
$filtro_id = $_GET['filtro_id'] ?? '';
$mostrar_filtros = isset($_GET['mostrar_filtros']) ? $_GET['mostrar_filtros'] : '0';

// Construir consulta WHERE
$where = "WHERE 1=1";

if(!empty($buscar_exacto)){
    if(is_numeric($buscar_exacto)){
        $where = "WHERE ax.id_AlumnoxTutor = '$buscar_exacto' OR a.DNI_U = '$buscar_exacto' OR t.DNI_U = '$buscar_exacto'";
    } else {
        $buscar = mysqli_real_escape_string($con, $buscar_exacto);
        $where = "WHERE (a.Apellido LIKE '%$buscar%' OR a.Nombre LIKE '%$buscar%' OR t.Apellido LIKE '%$buscar%' OR t.Nombre LIKE '%$buscar%')";
    }
} else {
    if(!empty($filtro_alumno)){
        $alumno = mysqli_real_escape_string($con, $filtro_alumno);
        $where .= " AND (a.Apellido LIKE '%$alumno%' OR a.Nombre LIKE '%$alumno%')";
    }
    if(!empty($filtro_tutor)){
        $tutor = mysqli_real_escape_string($con, $filtro_tutor);
        $where .= " AND (t.Apellido LIKE '%$tutor%' OR t.Nombre LIKE '%$tutor%')";
    }
    if(!empty($filtro_id)){
        $where .= " AND ax.id_AlumnoxTutor = '$filtro_id'";
    }
}

// Consulta principal
$query = "SELECT 
    ax.id_AlumnoxTutor,
    a.DNI_U as dni_alumno,
    a.Nombre as nombre_alumno,
    a.Apellido as apellido_alumno,
    a.id_curso,
    t.DNI_U as dni_tutor,
    t.Nombre as nombre_tutor,
    t.Apellido as apellido_tutor,
    c.curso as curso_numero,
    c.division as curso_division,
    c.turno as curso_turno
    FROM alumnoxtutor ax
    INNER JOIN usuario a ON ax.id_alumno = a.DNI_U AND a.ID_rol = 3
    INNER JOIN usuario t ON ax.id_tutor = t.DNI_U 
    LEFT JOIN curso c ON a.id_curso = c.ID_curso
    $where 
    ORDER BY a.Apellido, a.Nombre, t.Apellido, t.Nombre";

$res = mysqli_query($con, $query);

// Contar total de relaciones para el contador
$query_total = "SELECT COUNT(*) as total FROM alumnoxtutor";
$res_total = mysqli_query($con, $query_total);
$total_relaciones = mysqli_fetch_assoc($res_total)['total'];
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, minimum-scale=1.0">
    <link rel="stylesheet" href="../../recursos/styles.css">
    <title>Listado de Relaciones Tutor x Alumno</title>
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
        .badge-tutor-extra {
            background: #ff9800;
            color: #1a2a3a;
            padding: 2px 6px;
            border-radius: 12px;
            font-size: 10px;
            margin-left: 5px;
            font-weight: normal;
        }
    </style>
</head>
<body>
    <div class="caja">
        <h1>Listado de Relaciones Tutor x Alumno</h1>
        
        <!-- Búsqueda rápida -->
        <div class="filtro-rapido">
            <form method="GET" action="">
                <label for="buscar_exacto"><strong>Búsqueda rápida:</strong></label>
                <input type="text" name="buscar_exacto" id="buscar_exacto" placeholder="ID, nombre, apellido o DNI" value="<?php echo htmlspecialchars($buscar_exacto); ?>">
                <button type="submit">Buscar</button>
                <?php if(!empty($buscar_exacto)): ?>
                    <a href="listado_alumnoxtutor.php"><button type="button">Limpiar búsqueda</button></a>
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
                        <label>Nombre Alumno:</label>
                        <input type="text" name="filtro_alumno" placeholder="Apellido o nombre" value="<?php echo htmlspecialchars($filtro_alumno); ?>">
                        
                        <label>Nombre Tutor:</label>
                        <input type="text" name="filtro_tutor" placeholder="Apellido o nombre" value="<?php echo htmlspecialchars($filtro_tutor); ?>">
                        
                        <label>ID Relación:</label>
                        <input type="number" name="filtro_id" placeholder="ID de relación" min="1" value="<?php echo htmlspecialchars($filtro_id); ?>">
                    </div>
                    
                    <input type="hidden" name="mostrar_filtros" value="1">
                    
                    <div style="margin-top: 10px;">
                        <button type="submit">Aplicar Filtros</button>
                        <a href="listado_alumnoxtutor.php"><button type="button">Limpiar Filtros</button></a>
                    </div>
                </form>
            </div>
        </div>
        
        <div class="tabla-container">
            <table id="listado" class="tabla">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Alumno</th>
                        <th>DNI</th>
                        <th>Curso</th>
                        <th>Tutor</th>
                        <th>DNI Tutor</th>
                        <th class="no-exportar">Eliminar</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    if($res && mysqli_num_rows($res) > 0){
                        while($fila = mysqli_fetch_assoc($res)){
                            $turno_texto = ($fila['curso_turno'] == 'M') ? 'Mañana' : 'Tarde';
                            $curso_completo = $fila['curso_numero'] ? $fila['curso_numero'] . '° "' . $fila['curso_division'] . '" - ' . $turno_texto : '<span style="color: #FF6B8B; font-style: italic;">Sin curso</span>';
                            
                            echo '<tr>';
                            echo '<td>' . $fila['id_AlumnoxTutor'] . '</td>';
                            echo '<td><strong>' . htmlspecialchars($fila['apellido_alumno'] . ', ' . $fila['nombre_alumno']) . '</strong></td>';
                            echo '<td>' . $fila['dni_alumno'] . '</td>';
                            echo '<td>' . $curso_completo . '</td>';
                            echo '<td><strong>' . htmlspecialchars($fila['apellido_tutor'] . ', ' . $fila['nombre_tutor']) . '</strong></td>';
                            echo '<td>' . $fila['dni_tutor'] . '</td>';
                            echo '<td class="no-exportar">';
                            if($_SESSION['rol'] == 'Admin' || $_SESSION['rol'] == 'Preceptor' || $_SESSION['rol'] == 'Secretario'){
                                echo '<a href="borrar_alumnoxtutor.php?id=' . $fila['id_AlumnoxTutor'] . '" onclick="return confirm(\'¿Está seguro que desea eliminar esta relación?\\n\\nAlumno: ' . htmlspecialchars($fila['apellido_alumno'] . ', ' . $fila['nombre_alumno']) . '\\nTutor: ' . htmlspecialchars($fila['apellido_tutor'] . ', ' . $fila['nombre_tutor']) . '\')"><button class="btn-borrar">Eliminar</button></a>';
                            } else {
                                echo '<button class="btn-borrar" disabled>Eliminar</button>';
                            }
                            echo '</td>';
                            echo '</tr>';
                        }
                    } else {
                        echo '<tr><td colspan="7" style="text-align: center;">No se encontraron relaciones con los filtros aplicados.✅  Se encontraron <?php echo mysqli_num_rows($res); ?> relaciones. <br> Total en sistema: <?php echo $total_relaciones; ?> </td></tr>';
                    }
                    ?>
                </tbody>
            </table>
        </div>
        
        <p>
            <?php if($_SESSION['rol'] == 'Admin' || $_SESSION['rol'] == 'Preceptor' || $_SESSION['rol'] == 'Secretario'): ?>
                <a href="tutorxalumno.php"><button>Agregar Nueva Relación</button></a>
            <?php endif; ?>
            <a href="../../recursos/panel.php"><button>Volver al Panel</button></a>
            <button onclick="generarPDF()">Exportar a PDF</button>
            <button onclick="exportarExcel()">Exportar a Excel</button>
        </p>
    </div>
    
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.28/jspdf.plugin.autotable.min.js"></script>
    <script src="https://unpkg.com/xlsx/dist/xlsx.full.min.js"></script>
    
    <script>
        function toggleFiltros() {
            var filtros = document.getElementById('filtrosAvanzados');
            if(filtros.style.display === 'none' || filtros.style.display === '') {
                filtros.style.display = 'block';
            } else {
                filtros.style.display = 'none';
            }
        }
        
        function generarPDF() {
            const { jsPDF } = window.jspdf;
            const doc = new jsPDF('landscape');
            
            doc.setFontSize(16);
            doc.setFont(undefined, 'bold');
            doc.text('Listado de Relaciones Tutor x Alumno - EPET 34', 14, 15);
            
            doc.setFontSize(10);
            doc.setFont(undefined, 'normal');
            const fecha = new Date().toLocaleDateString();
            const hora = new Date().toLocaleTimeString();
            doc.text('Generado el: ' + fecha + ' ' + hora, 14, 22);
            
            const table = document.getElementById('listado');
            const rows = table.querySelectorAll('tr');
            const data = [];
            
            rows.forEach(row => {
                const rowData = [];
                row.querySelectorAll('th:not(.no-exportar), td:not(.no-exportar)').forEach(cell => {
                    let text = cell.innerText.replace(/Sin curso/g, 'Sin curso');
                    text = text.replace(/\+Adicional/g, '').trim();
                    rowData.push(text);
                });
                if(rowData.length > 0) data.push(rowData);
            });
            
            if(data.length > 0){
                doc.autoTable({
                    startY: 35,
                    head: [data[0]],
                    body: data.slice(1),
                    theme: 'grid',
                    styles: { fontSize: 8, cellPadding: 3, halign: 'center' },
                    headStyles: { fillColor: [63, 7, 11], textColor: [255, 255, 255], fontStyle: 'bold' },
                    alternateRowStyles: { fillColor: [245, 245, 245] },
                    margin: { left: 14, right: 14 }
                });
            }
            
            doc.save('listado_relaciones_tutor_alumno.pdf');
        }
        
        function exportarExcel() {
            const tablaOriginal = document.getElementById('listado');
            const tablaClon = tablaOriginal.cloneNode(true);
            
            const filas = tablaClon.querySelectorAll('tr');
            filas.forEach(fila => {
                const celdas = fila.querySelectorAll('th, td');
                if(celdas.length >= 2 && celdas[celdas.length - 1].classList.contains('no-exportar')){
                    celdas[celdas.length - 1].remove();
                }
            });
            
            const wb = XLSX.utils.book_new();
            const ws = XLSX.utils.table_to_sheet(tablaClon);
            XLSX.utils.book_append_sheet(wb, ws, "Relaciones");
            XLSX.writeFile(wb, 'listado_relaciones_tutor_alumno.xlsx');
        }
    </script>
</body>
</html>
<?php mysqli_close($con); ?>