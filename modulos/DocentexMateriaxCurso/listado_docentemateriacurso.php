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
    echo '<script>alert("No tiene permisos para ver asignaciones docente-materia-curso"); window.location="../../recursos/panel.php";</script>';
    exit();
}

include '../../recursos/conexion.php';

// Obtener filtros
$buscar_exacto = $_GET['buscar_exacto'] ?? '';
$filtro_docente = $_GET['filtro_docente'] ?? '';
$filtro_materia = $_GET['filtro_materia'] ?? '';
$filtro_curso = $_GET['filtro_curso'] ?? '';
$filtro_id = $_GET['filtro_id'] ?? '';

// Construir consulta con filtros
$where = "WHERE 1=1";

if(!empty($buscar_exacto)){
    if(is_numeric($buscar_exacto)){
        $where = "WHERE dmc.id_dmc = '$buscar_exacto' OR d.DNI_U = '$buscar_exacto' OR m.ID_materia = '$buscar_exacto' OR c.ID_curso = '$buscar_exacto'";
    } else {
        $where = "WHERE LOWER(CONCAT(d.Apellido, ' ', d.Nombre, ' ', m.Nom_materia, ' ', c.curso, '° \"', c.division, '\"')) LIKE LOWER('%" . mysqli_real_escape_string($con, $buscar_exacto) . "%')";
    }
} else {
    if(!empty($filtro_docente)){
        $where .= " AND LOWER(CONCAT(d.Apellido, ' ', d.Nombre)) LIKE LOWER('%" . mysqli_real_escape_string($con, $filtro_docente) . "%')";
    }
    
    if(!empty($filtro_materia)){
        $where .= " AND LOWER(m.Nom_materia) LIKE LOWER('%" . mysqli_real_escape_string($con, $filtro_materia) . "%')";
    }
    
    if(!empty($filtro_curso)){
        $where .= " AND c.ID_curso = '$filtro_curso'";
    }
    
    if(!empty($filtro_id)){
        $where .= " AND dmc.id_dmc = '$filtro_id'";
    }
}

$query = "SELECT dmc.id_dmc, d.DNI_U as dni_docente, d.Nombre as nombre_docente, d.Apellido as apellido_docente, m.ID_materia as id_materia, m.Nom_materia as nombre_materia, c.ID_curso as id_curso, c.curso as curso_numero, c.division as curso_division, c.turno as curso_turno FROM docentemateriacurso dmc INNER JOIN usuario d ON dmc.id_docente = d.DNI_U AND d.ID_rol = 2 INNER JOIN materia m ON dmc.id_materia = m.ID_materia INNER JOIN curso c ON dmc.id_curso = c.ID_curso $where ORDER BY d.Apellido, d.Nombre, m.Nom_materia, c.curso, c.division";
$res = mysqli_query($con, $query);

// Obtener total de asignaciones (EN 1 LÍNEA)
$query_total = "SELECT COUNT(*) as total FROM docentemateriacurso";
$res_total = mysqli_query($con, $query_total);
$total_asignaciones = mysqli_fetch_array($res_total)['total'];


?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, minimum-scale=1.0">
    <link rel="stylesheet" href="../../recursos/styles.css">
    <title>Listado de Docente x Materia x Curso</title>
    <style>
/* ============================================ */
/* ESTILOS RESPONSIVE PARA MÓVILES */
/* ============================================ */

/* ============================================ */
/* ESTILOS RESPONSIVE - SOLO SCROLL HORIZONTAL */
/* ============================================ */

/* Contenedor con scroll horizontal */
.tabla-container {
    width: 100%;
    overflow-x: auto;
    overflow-y: visible;
    -webkit-overflow-scrolling: touch;
    margin-bottom: 20px;
}

/* La tabla mantiene su formato original */
.tabla {
    width: 100%;
    min-width: 800px; /* Ancho mínimo para que las columnas no se compriman */
    border-collapse: collapse;
}

/* Ajustes para tablets y celulares */
@media (max-width: 992px) {
    .caja {
        padding: 15px;
        margin: 10px;
    }
    
    h1 {
        font-size: 1.4rem;
        text-align: center;
    }
    
    .filtro-rapido form {
        display: flex;
        flex-direction: column;
        gap: 8px;
    }
    
    .filtro-rapido input,
    .filtro-rapido button,
    .filtro-rapido a button {
        width: 100%;
    }
    
    .filtros form {
        display: flex;
        flex-direction: column;
    }
    
    .filtros input,
    .filtros select {
        width: 100%;
        margin: 5px 0;
    }
    
    .filtros button {
        margin: 5px 0;
    }
    
    p {
        display: flex;
        flex-direction: column;
        gap: 8px;
    }
    
    p button,
    p a button {
        width: 100%;
    }
}

@media (max-width: 768px) {
    h1 {
        font-size: 1.2rem;
    }
    
    .filtro-rapido label,
    .filtros h3 {
        font-size: 0.9rem;
    }
    
    .tabla th,
    .tabla td {
        padding: 8px 6px;
        font-size: 0.75rem;
    }
}

@media (max-width: 480px) {
    h1 {
        font-size: 1rem;
    }
    
    .tabla th,
    .tabla td {
        padding: 6px 4px;
        font-size: 0.7rem;
    }
}
    </style>
</head>
<body>
    <div class="caja">
        <h1>Listado de Asignaciones Docente x Materia x Curso</h1>
        
        
        <!-- Búsqueda rápida -->
        <div class="filtro-rapido">
            <form method="GET" action="">
                <label for="buscar_exacto"><strong>Búsqueda rápida:</strong></label>
                <input type="text" name="buscar_exacto" id="buscar_exacto" placeholder="ID, nombre, materia o curso" value="<?php echo htmlspecialchars($buscar_exacto); ?>">
                <button type="submit">Buscar</button>
                <?php if(!empty($buscar_exacto)): ?>
                    <a href="listado_docentemateriacurso.php"><button type="button">Limpiar búsqueda</button></a>
                <?php endif; ?>
            </form>
        </div>
        
        <!-- Filtros avanzados -->
        <div class="filtros">
            <form method="GET" action="">
                <h3>Filtros Avanzados</h3>
                
                <div>
                    <label>Nombre Docente:</label>
                    <input type="text" name="filtro_docente" placeholder="Apellido o nombre" value="<?php echo htmlspecialchars($filtro_docente); ?>">
                    
                    <label>Nombre Materia:</label>
                    <input type="text" name="filtro_materia" placeholder="Nombre materia" value="<?php echo htmlspecialchars($filtro_materia); ?>">
                </div>
                
                <div style="margin-top: 10px;">
                    <label>Curso:</label>
                    <select name="filtro_curso">
                        <option value="">Todos los cursos</option>
                        <?php
                        $query_cursos_filtro = "SELECT ID_curso, curso, division, turno FROM curso ORDER BY curso, division";
                        $res_cursos_filtro = mysqli_query($con, $query_cursos_filtro);
                        while($fila_curso_filtro = mysqli_fetch_array($res_cursos_filtro)){
                            $selected = ($filtro_curso == $fila_curso_filtro['ID_curso']) ? 'selected' : '';
                            $turno_filtro = $fila_curso_filtro['turno'] == 'M' ? 'Mañana' : 'Tarde';
                            echo '<option value="' . $fila_curso_filtro['ID_curso'] . '" ' . $selected . '>' . $fila_curso_filtro['curso'] . '° "' . $fila_curso_filtro['division'] . '" - ' . $turno_filtro . '</option>';
                        }
                        ?>
                    </select>
                    
                    <label>ID Asignación:</label>
                    <input type="number" name="filtro_id" placeholder="ID de asignación" min="1" value="<?php echo htmlspecialchars($filtro_id); ?>">
                </div>
                
                <div style="margin-top: 10px;">
                    <button type="submit">Aplicar Filtros</button>
                    <a href="listado_docentemateriacurso.php"><button type="button">Limpiar Filtros</button></a>
                </div>
            </form>
        </div>
        
<div class="tabla-container">
        <table border="1" id="listado" class="tabla">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Docente</th>
                    <th>DNI Docente</th>
                    <th>Materia</th>
                    <th>Curso</th>
                    <th class="no-exportar">Eliminar</th>
                </tr>
            </thead>
            <tbody>
                <?php
                if($res && mysqli_num_rows($res) > 0){
                    $contador = 0;
                    while($fila = mysqli_fetch_array($res)){
                        $contador++;
                        $turno_texto = $fila['curso_turno'] == 'M' ? 'Mañana' : 'Tarde';
                        $curso_completo = $fila['curso_numero'] . '° "' . $fila['curso_division'] . '" - ' . $turno_texto;
                        
                        echo '<tr>';
                        echo '<td>' . $fila['id_dmc'] . '</td>';
                        echo '<td><strong>' . htmlspecialchars($fila['apellido_docente'] . ', ' . $fila['nombre_docente']) . '</strong></td>';
                        echo '<td>' . $fila['dni_docente'] . '</td>';
                        echo '<td><strong>' . htmlspecialchars($fila['nombre_materia']) . '</strong></td>';
                        echo '<td>' . $curso_completo . '</td>';
                        
                        // Botón Eliminar (solo Admin, Preceptor y Secretario)
                        echo '<td class="no-exportar">';
                        if($_SESSION['rol'] == 'Admin' || $_SESSION['rol'] == 'Preceptor' || $_SESSION['rol'] == 'Secretario'){
                            echo '<a href="borrar_docentemateriacurso.php?id=' . $fila['id_dmc'] . '" onclick="return confirm(\'¿Está seguro que desea eliminar esta asignación?\\n\\nDocente: ' . htmlspecialchars($fila['apellido_docente'] . ', ' . $fila['nombre_docente']) . '\\nMateria: ' . htmlspecialchars($fila['nombre_materia']) . '\\nCurso: ' . $curso_completo . '\')"><button>🗑️ Eliminar</button></a>';
                        } else {
                            echo '<button disabled title="No tiene permisos para eliminar">Eliminar</button>';
                        }
                        echo '</td>';
                        echo '</tr>';
                    }
                } else {
                    echo '<tr><td colspan="6">No se encontraron asignaciones con los filtros aplicados.</td></tr>';
                }
                ?>
            </tbody>
        </table>
</div>
        
        <?php if(isset($contador) && $contador > 0): ?>
        <div class="resumen" style="margin-top: 10px;">
            <strong>Asignaciones encontradas: <?php echo $contador; ?></strong>
        </div>
        <?php endif; ?>
        
        <p>
            <?php if($_SESSION['rol'] == 'Admin' || $_SESSION['rol'] == 'Preceptor' || $_SESSION['rol'] == 'Secretario'): ?>
                <a href="docentemateriacurso.php"><button>Agregar Nueva Asignación</button></a>
            <?php endif; ?>
            <a href="../../recursos/panel.php"><button>Volver al Panel</button></a>
            <button onclick="generarPDF()">Exportar a PDF</button>
            <button onclick="exportarExcel()">Exportar a Excel</button>
        </p>
    </div>
    
    <!-- Librerías para exportación -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.28/jspdf.plugin.autotable.min.js"></script>
    <script src="https://unpkg.com/xlsx/dist/xlsx.full.min.js"></script>
    
    <script>
        function generarPDF() {
            const { jsPDF } = window.jspdf;
            const doc = new jsPDF('landscape');
            
            // Título y fecha
            doc.setFontSize(16);
            doc.setFont(undefined, 'bold');
            doc.text('Listado de Asignaciones Docente x Materia x Curso - EPET 34', 14, 15);
            
            doc.setFontSize(10);
            doc.setFont(undefined, 'normal');
            const fecha = new Date().toLocaleDateString();
            const hora = new Date().toLocaleTimeString();
            doc.text('Generado el: ' + fecha + ' ' + hora, 14, 22);
            
            // Estadísticas
            doc.setFontSize(10);
            doc.text('Total asignaciones: <?php echo $total_asignaciones; ?>', 14, 29);
            doc.text('Docentes asignados: <?php echo $estadisticas['total_docentes_asignados']; ?> de <?php echo $total_docentes; ?>', 14, 36);
            doc.text('Materias asignadas: <?php echo $estadisticas['total_materias_asignadas']; ?> de <?php echo $total_materias; ?>', 14, 43);
            
            // Información de filtros aplicados
            let filtrosAplicados = [];
            <?php if(!empty($buscar_exacto)): ?>
                filtrosAplicados.push('Búsqueda: <?php echo $buscar_exacto; ?>');
            <?php endif; ?>
            <?php if(!empty($filtro_docente)): ?>
                filtrosAplicados.push('Docente: <?php echo $filtro_docente; ?>');
            <?php endif; ?>
            <?php if(!empty($filtro_materia)): ?>
                filtrosAplicados.push('Materia: <?php echo $filtro_materia; ?>');
            <?php endif; ?>
            <?php if(!empty($filtro_curso)): ?>
                filtrosAplicados.push('Curso ID: <?php echo $filtro_curso; ?>');
            <?php endif; ?>
            <?php if(!empty($filtro_id)): ?>
                filtrosAplicados.push('ID: <?php echo $filtro_id; ?>');
            <?php endif; ?>
            
            if(filtrosAplicados.length > 0){
                doc.text('Filtros aplicados: ' + filtrosAplicados.join(', '), 14, 50);
            }
            
            // Preparar datos de la tabla
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
            
            // Crear tabla en PDF
            doc.autoTable({
                startY: 60,
                head: [data[0]],
                body: data.slice(1),
                theme: 'grid',
                styles: {
                    fontSize: 8,
                    cellPadding: 3,
                    overflow: 'linebreak',
                    halign: 'center'
                },
                headStyles: {
                    fillColor: [41, 128, 185],
                    textColor: [255, 255, 255],
                    fontStyle: 'bold'
                },
                alternateRowStyles: {
                    fillColor: [245, 245, 245]
                },
                columnStyles: {
                    0: { halign: 'center', cellWidth: 15 }, // ID
                    1: { halign: 'left', cellWidth: 60 },   // Docente
                    2: { halign: 'center', cellWidth: 30 }, // DNI Docente
                    3: { halign: 'left', cellWidth: 80 },   // Materia
                    4: { halign: 'center', cellWidth: 40 }  // Curso
                },
                margin: { left: 14, right: 14 },
                didDrawPage: function(data) {
                    doc.setFontSize(8);
                    doc.text('Página ' + doc.internal.getNumberOfPages(), doc.internal.pageSize.width - 20, doc.internal.pageSize.height - 10);
                }
            });
            
            // Guardar PDF
            doc.save('listado_asignaciones_dmc_<?php echo date("Y-m-d_H-i"); ?>.pdf');
        }

        function exportarExcel() {
            const tablaOriginal = document.getElementById('listado');
            const tablaClon = tablaOriginal.cloneNode(true);
            
            // Eliminar columnas de acciones
            const filas = tablaClon.querySelectorAll('tr');
            filas.forEach(fila => {
                const celdas = fila.querySelectorAll('th, td');
                if (celdas.length >= 2) {
                    if(celdas[celdas.length - 1].classList.contains('no-exportar')){
                        celdas[celdas.length - 1].remove();
                    }
                }
            });
            
            const wb = XLSX.utils.book_new();
            const ws = XLSX.utils.table_to_sheet(tablaClon);
            
            const colWidths = [
                {wch: 8},   // ID
                {wch: 40},  // Docente
                {wch: 15},  // DNI Docente
                {wch: 50},  // Materia
                {wch: 30}   // Curso
            ];
            ws['!cols'] = colWidths;
            
            XLSX.utils.book_append_sheet(wb, ws, "Asignaciones");
            
            // Hoja de resumen
            const resumenData = [
                ["RESUMEN DE ASIGNACIONES DOCENTE x MATERIA x CURSO"],
                ["Fecha de exportación", new Date().toLocaleString()],
                ["Total de asignaciones encontradas", <?php echo isset($contador) ? $contador : 0; ?>],
                ["Total asignaciones en sistema", <?php echo $total_asignaciones; ?>],
                ["Docentes asignados", <?php echo $estadisticas['total_docentes_asignados']; ?>],
                ["Total docentes", <?php echo $total_docentes; ?>],
                ["Materias asignadas", <?php echo $estadisticas['total_materias_asignadas']; ?>],
                ["Total materias", <?php echo $total_materias; ?>],
                ["Cursos asignados", <?php echo $estadisticas['total_cursos_asignados']; ?>],
                ["Total cursos", <?php echo $total_cursos; ?>],
                [" "],
                ["FILTROS APLICADOS"],
                <?php 
                $filtros_excel = [];
                if(!empty($buscar_exacto)) $filtros_excel[] = '["Búsqueda", "' . $buscar_exacto . '"]';
                if(!empty($filtro_docente)) $filtros_excel[] = '["Docente", "' . $filtro_docente . '"]';
                if(!empty($filtro_materia)) $filtros_excel[] = '["Materia", "' . $filtro_materia . '"]';
                if(!empty($filtro_curso)) {
                    // Obtener nombre del curso para el filtro
                    $nombre_curso_filtro = "";
                    if(!empty($filtro_curso)){
                        $query_nombre_curso = "SELECT CONCAT(curso, '° \"', division, '\" - ', CASE turno WHEN 'M' THEN 'Mañana' ELSE 'Tarde' END) as nombre FROM curso WHERE ID_curso = '$filtro_curso'";
                        $res_nombre_curso = mysqli_query($con, $query_nombre_curso);
                        if(mysqli_num_rows($res_nombre_curso) > 0){
                            $fila_nombre = mysqli_fetch_array($res_nombre_curso);
                            $nombre_curso_filtro = $fila_nombre['nombre'];
                        }
                    }
                    $filtros_excel[] = '["Curso", "' . $nombre_curso_filtro . '"]';
                }
                if(!empty($filtro_id)) $filtros_excel[] = '["ID", "' . $filtro_id . '"]';
                
                if(empty($filtros_excel)){
                    echo '["Sin filtros aplicados", ""]';
                } else {
                    echo implode(",", $filtros_excel);
                }
                ?>
            ];
            
            const wsResumen = XLSX.utils.aoa_to_sheet(resumenData);
            XLSX.utils.book_append_sheet(wb, wsResumen, "Resumen");
            
            XLSX.writeFile(wb, 'listado_asignaciones_dmc_<?php echo date("Y-m-d_H-i"); ?>.xlsx');
        }
    </script>
</body>
</html>
<?php
mysqli_close($con);
?>