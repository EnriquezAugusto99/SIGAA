<?php
session_start();

if(isset($_SESSION["dni"]) && (time() - $_SESSION["ultima_actividad"] > 1800)){
    echo '<script>alert("Se venció el tiempo de la sesion"); window.location="../../recursos/index.php";</script>';
    exit();
}

if(!isset($_SESSION["dni"])){
    echo '<script>alert("Se venció el tiempo de la sesion"); window.location="../../recursos/index.php";</script>';
    exit();
}

// Verificar permisos
if($_SESSION['rol'] != 'Admin' && $_SESSION['rol'] != 'Preceptor' && $_SESSION['rol'] != 'Secretario' && $_SESSION['rol'] != 'Profesor'){
    echo '<script>alert("No tiene permisos para ver calificaciones"); window.location="../../recursos/panel.php";</script>';
    exit();
}

include '../../recursos/conexion.php';

// Obtener filtros
$buscar_exacto = $_GET['buscar_exacto'] ?? '';
$filtro_alumno = $_GET['filtro_alumno'] ?? '';
$filtro_materia = $_GET['filtro_materia'] ?? '';
$filtro_curso = $_GET['filtro_curso'] ?? '';
$filtro_docente = $_GET['filtro_docente'] ?? '';
$filtro_trimestre = $_GET['filtro_trimestre'] ?? '';

// Construir consulta con filtros
$where = "WHERE 1=1";

if(!empty($buscar_exacto)){
    if(is_numeric($buscar_exacto)){
        $where = "WHERE c.id_calificacion = '$buscar_exacto' OR a.DNI_U = '$buscar_exacto' OR d.DNI_U = '$buscar_exacto'";
    } else {
        $where = "WHERE LOWER(CONCAT(a.Apellido, ' ', a.Nombre, ' ', m.Nom_materia, ' ', d.Apellido, ' ', d.Nombre)) LIKE LOWER('%" . mysqli_real_escape_string($con, $buscar_exacto) . "%')";
    }
} else {
    if(!empty($filtro_alumno)){
        $where .= " AND LOWER(CONCAT(a.Apellido, ' ', a.Nombre)) LIKE LOWER('%" . mysqli_real_escape_string($con, $filtro_alumno) . "%')";
    }
    
    if(!empty($filtro_materia)){
        $where .= " AND m.ID_materia = '$filtro_materia'";
    }
    
    if(!empty($filtro_curso)){
        $where .= " AND a.id_curso = '$filtro_curso'";
    }
    
    if(!empty($filtro_docente)){
        $where .= " AND d.DNI_U = '$filtro_docente'";
    }
    
    if(!empty($filtro_trimestre)){
        $where .= " AND c.trimestre = '$filtro_trimestre'";
    }
}

// Consulta principal
$query = "SELECT c.id_calificacion, a.DNI_U as dni_alumno, a.Nombre as nombre_alumno, a.Apellido as apellido_alumno, m.ID_materia as id_materia, m.Nom_materia as nombre_materia, d.DNI_U as dni_docente, d.Nombre as nombre_docente, d.Apellido as apellido_docente, c.trimestre, c.nota, c.tipo_evaluacion, c.fecha, cur.ID_curso as id_curso, cur.curso as curso_numero, cur.division as curso_division, cur.turno as curso_turno, t.trimestre as nombre_trimestre FROM calificaciones c INNER JOIN usuario a ON c.id_alumno = a.DNI_U INNER JOIN materia m ON c.id_materia = m.ID_materia INNER JOIN usuario d ON c.id_docente = d.DNI_U INNER JOIN curso cur ON a.id_curso = cur.ID_curso INNER JOIN trimestres t ON c.trimestre = t.id_trimestre $where ORDER BY c.fecha DESC, a.Apellido, a.Nombre";

$res = mysqli_query($con, $query);

// Obtener total de calificaciones
$query_total = "SELECT COUNT(*) as total FROM calificaciones";
$res_total = mysqli_query($con, $query_total);
$total_calificaciones = mysqli_fetch_array($res_total)['total'];

// Obtener lista de materias para filtro
$query_materias = "SELECT ID_materia, Nom_materia FROM materia ORDER BY Nom_materia";
$res_materias = mysqli_query($con, $query_materias);

// Obtener lista de cursos para filtro
$query_cursos = "SELECT ID_curso, curso, division, turno FROM curso ORDER BY curso, division";
$res_cursos = mysqli_query($con, $query_cursos);

// Obtener lista de docentes para filtro
$query_docentes = "SELECT DNI_U, Nombre, Apellido FROM usuario WHERE ID_rol = 2 AND ID_Estado = 1 ORDER BY Apellido, Nombre";
$res_docentes = mysqli_query($con, $query_docentes);

// Obtener lista de trimestres para filtro
$query_trimestres = "SELECT id_trimestre, trimestre FROM trimestres ORDER BY fecha_inicio";
$res_trimestres = mysqli_query($con, $query_trimestres);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, minimum-scale=1.0">
    <link rel="stylesheet" href="../../recursos/styles.css">
    <title>Listado de Calificaciones</title>
    <style>
        /* Filtros */
        .filtros {
            background: #1A3A5F;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 15px;
            border: 1px solid rgba(64, 224, 208, 0.1);
        }

        .filtros h3 {
            color: #E0F7FA;
            margin-top: 0;
            margin-bottom: 10px;
            border-bottom: 1px solid #40E0D0;
            padding-bottom: 5px;
        }

        .filtros label {
            color: #E0F7FA;
            font-weight: bold;
            margin-right: 5px;
            display: inline-block;
            min-width: 100px;
        }

        .filtros input, .filtros select {
            background: #2D5A8C;
            border: 1px solid #40E0D0;
            border-radius: 4px;
            color: #E0F7FA;
            padding: 6px 10px;
            margin: 3px;
            width: 200px;
        }

        .filtro-rapido {
            background: #1A3A5F;
            padding: 10px;
            border-radius: 6px;
            margin-bottom: 10px;
            border: 1px solid rgba(64, 224, 208, 0.1);
        }

        .filtro-rapido label {
            color: #E0F7FA;
            font-weight: bold;
            margin-right: 10px;
        }

        .filtro-rapido input {
            background: #2D5A8C;
            border: 1px solid #40E0D0;
            border-radius: 4px;
            color: #E0F7FA;
            padding: 6px 10px;
            margin-right: 10px;
            width: 250px;
        }

        .resumen {
            background: #2D5A8C;
            padding: 8px 15px;
            border-radius: 6px;
            margin-bottom: 10px;
            border: 1px solid rgba(64, 224, 208, 0.2);
        }

        .resumen strong {
            color: #E0F7FA;
        }

        .estadisticas {
            background: rgba(64, 224, 208, 0.1);
            padding: 10px 15px;
            border-radius: 6px;
            margin-bottom: 15px;
            border-left: 4px solid #40E0D0;
        }

        /* Tabla */
        .tabla {
            background: #2D5A8C;
            border-collapse: collapse;
            border-radius: 6px;
            overflow: hidden;
            width: 100%;
            margin-top: 15px;
        }

        .tabla th {
            background: #3A6BA9;
            color: #E0F7FA;
            font-weight: bold;
            padding: 10px;
            border-bottom: 2px solid #40E0D0;
            text-align: left;
        }

        .tabla td {
            color: #E0F7FA;
            padding: 8px;
            border-bottom: 1px solid rgba(64, 224, 208, 0.1);
        }

        .tabla tr:hover {
            background: rgba(64, 224, 208, 0.05);
        }

        .no-exportar {
            background: rgba(255, 107, 139, 0.05);
        }

        .nota-aprobada {
            color: #4CAF50;
            font-weight: bold;
        }
        
        .nota-desaprobada {
            color: #F44336;
            font-weight: bold;
        }
        
        .nota-regular {
            color: #FF9800;
            font-weight: bold;
        }

        .caja {
            width: 100%;
            max-width: 98%;
            overflow-x: auto;
        }
        
        body {
            overflow-x: auto;
            min-height: 100vh;
            background: #0C1A2A;
            font-family: Verdana, Geneva, Tahoma, sans-serif;
            color: #E0F7FA;
            padding: 10px;
        }
        
        /* Anchuras específicas para columnas */
        .tabla th:nth-child(1), .tabla td:nth-child(1) { width: 80px; } /* ID */
        .tabla th:nth-child(2), .tabla td:nth-child(2) { width: 200px; } /* Alumno */
        .tabla th:nth-child(3), .tabla td:nth-child(3) { width: 150px; } /* Materia */
        .tabla th:nth-child(4), .tabla td:nth-child(4) { width: 120px; } /* Curso */
        .tabla th:nth-child(5), .tabla td:nth-child(5) { width: 180px; } /* Docente */
        .tabla th:nth-child(6), .tabla td:nth-child(6) { width: 100px; } /* Nota */
        .tabla th:nth-child(7), .tabla td:nth-child(7) { width: 120px; } /* Tipo Evaluación */
        .tabla th:nth-child(8), .tabla td:nth-child(8) { width: 100px; } /* Trimestre */
        .tabla th:nth-child(9), .tabla td:nth-child(9) { width: 100px; } /* Fecha */
        .tabla th:nth-child(10), .tabla td:nth-child(10) { width: 80px; } /* Editar */
        .tabla th:nth-child(11), .tabla td:nth-child(11) { width: 80px; } /* Eliminar */
    </style>
</head>
<body>
    <div class="caja">
        <h1>📝 Listado de Calificaciones</h1>
        
        <!-- Estadísticas -->
        <div class="estadisticas">
            <strong>📊 Estadísticas:</strong><br>
            • Total de calificaciones: <?php echo $total_calificaciones; ?><br>
        </div>
        
        <!-- Búsqueda rápida -->
        <div class="filtro-rapido">
            <form method="GET" action="">
                <label for="buscar_exacto"><strong>🔍 Búsqueda rápida:</strong></label>
                <input type="text" name="buscar_exacto" id="buscar_exacto" placeholder="ID, nombre, apellido o materia" value="<?php echo htmlspecialchars($buscar_exacto); ?>">
                <button type="submit">Buscar</button>
                <?php if(!empty($buscar_exacto)): ?>
                    <a href="listado_calificacion.php"><button type="button">Limpiar búsqueda</button></a>
                <?php endif; ?>
            </form>
        </div>
        
        <!-- Filtros avanzados -->
        <div class="filtros">
            <form method="GET" action="">
                <h3>⚙️ Filtros Avanzados</h3>
                
                <div>
                    <label>Alumno:</label>
                    <input type="text" name="filtro_alumno" placeholder="Apellido o nombre" value="<?php echo htmlspecialchars($filtro_alumno); ?>">
                    
                    <label>Materia:</label>
                    <select name="filtro_materia">
                        <option value="">Todas las materias</option>
                        <?php
                        while($fila_materia = mysqli_fetch_array($res_materias)){
                            $selected = ($filtro_materia == $fila_materia['ID_materia']) ? 'selected' : '';
                            echo '<option value="' . $fila_materia['ID_materia'] . '" ' . $selected . '>' . $fila_materia['Nom_materia'] . '</option>';
                        }
                        ?>
                    </select>
                    
                    <label>Curso:</label>
                    <select name="filtro_curso">
                        <option value="">Todos los cursos</option>
                        <?php
                        while($fila_curso = mysqli_fetch_array($res_cursos)){
                            $turno_texto = $fila_curso['turno'] == 'M' ? 'Mañana' : 'Tarde';
                            $curso_completo = $fila_curso['curso'] . '° "' . $fila_curso['division'] . '" - ' . $turno_texto;
                            $selected = ($filtro_curso == $fila_curso['ID_curso']) ? 'selected' : '';
                            echo '<option value="' . $fila_curso['ID_curso'] . '" ' . $selected . '>' . $curso_completo . '</option>';
                        }
                        ?>
                    </select>
                </div>
                
                <div style="margin-top: 10px;">
                    <label>Docente:</label>
                    <select name="filtro_docente">
                        <option value="">Todos los docentes</option>
                        <?php
                        while($fila_docente = mysqli_fetch_array($res_docentes)){
                            $selected = ($filtro_docente == $fila_docente['DNI_U']) ? 'selected' : '';
                            echo '<option value="' . $fila_docente['DNI_U'] . '" ' . $selected . '>' . $fila_docente['Apellido'] . ', ' . $fila_docente['Nombre'] . '</option>';
                        }
                        ?>
                    </select>
                    
                    <label>Trimestre:</label>
                    <select name="filtro_trimestre">
                        <option value="">Todos los trimestres</option>
                        <?php
                        while($fila_trimestre = mysqli_fetch_array($res_trimestres)){
                            $selected = ($filtro_trimestre == $fila_trimestre['id_trimestre']) ? 'selected' : '';
                            echo '<option value="' . $fila_trimestre['id_trimestre'] . '" ' . $selected . '>' . $fila_trimestre['trimestre'] . '</option>';
                        }
                        ?>
                    </select>
                </div>
                
                <div style="margin-top: 10px;">
                    <button type="submit">Aplicar Filtros</button>
                    <a href="listado_calificacion.php"><button type="button">Limpiar Filtros</button></a>
                </div>
            </form>
        </div>
        
        <table border="1" id="listado" class="tabla">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Alumno</th>
                    <th>Materia</th>
                    <th>Curso</th>
                    <th>Docente</th>
                    <th>Nota</th>
                    <th>Tipo Evaluación</th>
                    <th>Trimestre</th>
                    <th>Fecha</th>
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
                        
                        // Determinar clase de la nota
                        $clase_nota = '';
                        if($fila['nota'] >= 7){
                            $clase_nota = 'nota-aprobada';
                        } elseif($fila['nota'] >= 4){
                            $clase_nota = 'nota-regular';
                        } else {
                            $clase_nota = 'nota-desaprobada';
                        }
                        
                        $turno_texto = $fila['curso_turno'] == 'M' ? 'Mañana' : 'Tarde';
                        $curso_completo = $fila['curso_numero'] . '° "' . $fila['curso_division'] . '" - ' . $turno_texto;
                        
                        echo '<tr>';
                        echo '<td>' . $fila['id_calificacion'] . '</td>';
                        echo '<td><strong>' . htmlspecialchars($fila['apellido_alumno'] . ', ' . $fila['nombre_alumno']) . '</strong><br><small>DNI: ' . $fila['dni_alumno'] . '</small></td>';
                        echo '<td>' . htmlspecialchars($fila['nombre_materia']) . '</td>';
                        echo '<td>' . $curso_completo . '</td>';
                        echo '<td>' . htmlspecialchars($fila['apellido_docente'] . ', ' . $fila['nombre_docente']) . '<br><small>DNI: ' . $fila['dni_docente'] . '</small></td>';
                        echo '<td class="' . $clase_nota . '">' . number_format($fila['nota'], 2) . '</td>';
                        echo '<td>' . htmlspecialchars($fila['tipo_evaluacion']) . '</td>';
                        echo '<td>' . $fila['nombre_trimestre'] . '</td>';
                        echo '<td>' . date('d/m/Y', strtotime($fila['fecha'])) . '</td>';
                        
                        // Botón Editar (solo Admin, Preceptor, Secretario y Profesor del curso)
                        echo '<td class="no-exportar">';
                        $puede_editar = false;
                        
                        // Admin, Preceptor y Secretario siempre pueden editar
                        if($_SESSION['rol'] == 'Admin' || $_SESSION['rol'] == 'Preceptor' || $_SESSION['rol'] == 'Secretario'){
                            $puede_editar = true;
                        } 
                        // Profesor solo puede editar sus propias calificaciones
                        elseif($_SESSION['rol'] == 'Profesor' && $_SESSION['dni'] == $fila['dni_docente']){
                            $puede_editar = true;
                        }
                        
                        if($puede_editar){
                            echo '<a href="editar_calificacion.php?id=' . $fila['id_calificacion'] . '"><button>Editar</button></a>';
                        } else {
                            echo '<button disabled title="No tiene permisos para editar">Editar</button>';
                        }
                        echo '</td>';
                        
                        // Botón Eliminar (solo Admin, Preceptor y Secretario)
                        echo '<td class="no-exportar">';
                        if($_SESSION['rol'] == 'Admin' || $_SESSION['rol'] == 'Preceptor' || $_SESSION['rol'] == 'Secretario'){
                            echo '<a href="borrar_calificacion.php?id=' . $fila['id_calificacion'] . '" onclick="return confirm(\'¿Está seguro que desea eliminar esta calificación?\\n\\nAlumno: ' . htmlspecialchars($fila['apellido_alumno'] . ', ' . $fila['nombre_alumno']) . '\\nMateria: ' . htmlspecialchars($fila['nombre_materia']) . '\\nNota: ' . $fila['nota'] . '\\nFecha: ' . date('d/m/Y', strtotime($fila['fecha'])) . '\')"><button>Eliminar</button></a>';
                        } else {
                            echo '<button disabled title="Solo Admin, Preceptor y Secretario pueden eliminar">Eliminar</button>';
                        }
                        echo '</td>';
                        echo '</tr>';
                    }
                } else {
                    echo '<tr><td colspan="11">No se encontraron calificaciones con los filtros aplicados.</td></tr>';
                }
                ?>
            </tbody>
        </table>
        
        <?php if(isset($contador) && $contador > 0): ?>
        <div class="resumen" style="margin-top: 10px;">
            <strong>Calificaciones encontradas: <?php echo $contador; ?></strong>
        </div>
        <?php endif; ?>
        
        <p>
            <?php if($_SESSION['rol'] == 'Admin' || $_SESSION['rol'] == 'Preceptor' || $_SESSION['rol'] == 'Secretario' || $_SESSION['rol'] == 'Profesor'): ?>
                <a href="calificaciones.php"><button>➕ Agregar Nueva Calificación</button></a>
            <?php endif; ?>
            <a href="../../recursos/panel.php"><button>🏠 Volver al Panel</button></a>
            <button onclick="generarPDF()">📄 Exportar a PDF</button>
            <button onclick="exportarExcel()">📊 Exportar a Excel</button>
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
            doc.text('Listado de Calificaciones - EPET 34', 14, 15);
            
            doc.setFontSize(10);
            doc.setFont(undefined, 'normal');
            const fecha = new Date().toLocaleDateString();
            const hora = new Date().toLocaleTimeString();
            doc.text('Generado el: ' + fecha + ' ' + hora, 14, 22);
            
            // Estadísticas
            doc.setFontSize(10);
            doc.text('Total de calificaciones: <?php echo $total_calificaciones; ?>', 14, 29);
            doc.text('Promedio general: <?php echo number_format($promedio, 2); ?>', 14, 36);
            
            // Información de filtros aplicados
            let filtrosAplicados = [];
            <?php if(!empty($buscar_exacto)): ?>
                filtrosAplicados.push('Búsqueda: <?php echo $buscar_exacto; ?>');
            <?php endif; ?>
            <?php if(!empty($filtro_alumno)): ?>
                filtrosAplicados.push('Alumno: <?php echo $filtro_alumno; ?>');
            <?php endif; ?>
            <?php if(!empty($filtro_materia)): ?>
                filtrosAplicados.push('Materia ID: <?php echo $filtro_materia; ?>');
            <?php endif; ?>
            <?php if(!empty($filtro_curso)): ?>
                filtrosAplicados.push('Curso ID: <?php echo $filtro_curso; ?>');
            <?php endif; ?>
            <?php if(!empty($filtro_docente)): ?>
                filtrosAplicados.push('Docente ID: <?php echo $filtro_docente; ?>');
            <?php endif; ?>
            <?php if(!empty($filtro_trimestre)): ?>
                filtrosAplicados.push('Trimestre ID: <?php echo $filtro_trimestre; ?>');
            <?php endif; ?>
            
            if(filtrosAplicados.length > 0){
                doc.text('Filtros aplicados: ' + filtrosAplicados.join(', '), 14, 43);
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
                startY: 50,
                head: [data[0]],
                body: data.slice(1),
                theme: 'grid',
                styles: {
                    fontSize: 7,
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
                    1: { halign: 'left', cellWidth: 60 },   // Alumno
                    2: { halign: 'left', cellWidth: 50 },   // Materia
                    3: { halign: 'center', cellWidth: 30 }, // Curso
                    4: { halign: 'left', cellWidth: 60 },   // Docente
                    5: { halign: 'center', cellWidth: 20 }, // Nota
                    6: { halign: 'center', cellWidth: 30 }, // Tipo Evaluación
                    7: { halign: 'center', cellWidth: 25 }, // Trimestre
                    8: { halign: 'center', cellWidth: 25 }  // Fecha
                },
                margin: { left: 14, right: 14 },
                didDrawPage: function(data) {
                    doc.setFontSize(8);
                    doc.text('Página ' + doc.internal.getNumberOfPages(), doc.internal.pageSize.width - 20, doc.internal.pageSize.height - 10);
                }
            });
            
            // Guardar PDF
            doc.save('listado_calificaciones_<?php echo date("Y-m-d_H-i"); ?>.pdf');
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
                    if(celdas[celdas.length - 1].classList.contains('no-exportar')){
                        celdas[celdas.length - 1].remove();
                    }
                }
            });
            
            const wb = XLSX.utils.book_new();
            const ws = XLSX.utils.table_to_sheet(tablaClon);
            
            const colWidths = [
                {wch: 8},   // ID
                {wch: 40},  // Alumno
                {wch: 30},  // Materia
                {wch: 20},  // Curso
                {wch: 40},  // Docente
                {wch: 10},  // Nota
                {wch: 25},  // Tipo Evaluación
                {wch: 20},  // Trimestre
                {wch: 15}   // Fecha
            ];
            ws['!cols'] = colWidths;
            
            XLSX.utils.book_append_sheet(wb, ws, "Calificaciones");
            
            // Hoja de resumen
            const resumenData = [
                ["RESUMEN DE CALIFICACIONES"],
                ["Fecha de exportación", new Date().toLocaleString()],
                ["Total de calificaciones encontradas", <?php echo isset($contador) ? $contador : 0; ?>],
                ["Total calificaciones en sistema", <?php echo $total_calificaciones; ?>],
                ["Promedio general", <?php echo number_format($promedio, 2); ?>],
                [" "],
                ["FILTROS APLICADOS"],
                <?php 
                $filtros_excel = [];
                if(!empty($buscar_exacto)) $filtros_excel[] = '["Búsqueda", "' . $buscar_exacto . '"]';
                if(!empty($filtro_alumno)) $filtros_excel[] = '["Alumno", "' . $filtro_alumno . '"]';
                if(!empty($filtro_materia)) {
                    $nombre_materia_filtro = "";
                    if(!empty($filtro_materia)){
                        $query_nombre_materia = "SELECT Nom_materia FROM materia WHERE ID_materia = '$filtro_materia'";
                        $res_nombre_materia = mysqli_query($con, $query_nombre_materia);
                        if(mysqli_num_rows($res_nombre_materia) > 0){
                            $fila_nombre = mysqli_fetch_array($res_nombre_materia);
                            $nombre_materia_filtro = $fila_nombre['Nom_materia'];
                        }
                    }
                    $filtros_excel[] = '["Materia", "' . $nombre_materia_filtro . '"]';
                }
                if(!empty($filtro_curso)) {
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
                if(!empty($filtro_docente)) {
                    $nombre_docente_filtro = "";
                    if(!empty($filtro_docente)){
                        $query_nombre_docente = "SELECT CONCAT(Apellido, ', ', Nombre) as nombre FROM usuario WHERE DNI_U = '$filtro_docente'";
                        $res_nombre_docente = mysqli_query($con, $query_nombre_docente);
                        if(mysqli_num_rows($res_nombre_docente) > 0){
                            $fila_nombre = mysqli_fetch_array($res_nombre_docente);
                            $nombre_docente_filtro = $fila_nombre['nombre'];
                        }
                    }
                    $filtros_excel[] = '["Docente", "' . $nombre_docente_filtro . '"]';
                }
                if(!empty($filtro_trimestre)) {
                    $nombre_trimestre_filtro = "";
                    if(!empty($filtro_trimestre)){
                        $query_nombre_trimestre = "SELECT trimestre FROM trimestres WHERE id_trimestre = '$filtro_trimestre'";
                        $res_nombre_trimestre = mysqli_query($con, $query_nombre_trimestre);
                        if(mysqli_num_rows($res_nombre_trimestre) > 0){
                            $fila_nombre = mysqli_fetch_array($res_nombre_trimestre);
                            $nombre_trimestre_filtro = $fila_nombre['trimestre'];
                        }
                    }
                    $filtros_excel[] = '["Trimestre", "' . $nombre_trimestre_filtro . '"]';
                }
                
                if(empty($filtros_excel)){
                    echo '["Sin filtros aplicados", ""]';
                } else {
                    echo implode(",", $filtros_excel);
                }
                ?>
            ];
            
            const wsResumen = XLSX.utils.aoa_to_sheet(resumenData);
            XLSX.utils.book_append_sheet(wb, wsResumen, "Resumen");
            
            XLSX.writeFile(wb, 'listado_calificaciones_<?php echo date("Y-m-d_H-i"); ?>.xlsx');
        }
    </script>
</body>
</html>
<?php
mysqli_close($con);
?>