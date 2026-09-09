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
    echo '<script>alert("No tiene permisos para ver aulas"); window.location="../../recursos/panel.php";</script>';
    exit();
}

include '../../recursos/conexion.php';

// Obtener filtros
$buscar_exacto = $_GET['buscar_exacto'] ?? '';
$filtro_nombre = $_GET['filtro_nombre'] ?? '';
$filtro_id = $_GET['filtro_id'] ?? '';

// Construir consulta con filtros
$where = "WHERE 1=1";

if(!empty($buscar_exacto)){
    if(is_numeric($buscar_exacto)){
        $where = "WHERE aulas.id_aula = '$buscar_exacto'";
    } else {
        $where = "WHERE LOWER(aulas.aula) LIKE LOWER('%" . mysqli_real_escape_string($con, $buscar_exacto) . "%') OR LOWER(aulas.descripcion) LIKE LOWER('%" . mysqli_real_escape_string($con, $buscar_exacto) . "%')";
    }
} else {
    if(!empty($filtro_nombre)){
        $where .= " AND LOWER(aulas.aula) LIKE LOWER('%" . mysqli_real_escape_string($con, $filtro_nombre) . "%')";
    }
    
    if(!empty($filtro_id)){
        $where .= " AND aulas.id_aula = '$filtro_id'";
    }
}

// Consulta principal con estadísticas de uso
$query = "SELECT 
    aulas.id_aula, 
    aulas.aula, 
    aulas.descripcion,
    (SELECT COUNT(*) FROM horarios WHERE horarios.id_aula = aulas.id_aula) as total_horarios
    FROM aulas 
    $where 
    ORDER BY aulas.aula";

$res = mysqli_query($con, $query);

// Obtener total de aulas
$query_total = "SELECT COUNT(*) as total FROM aulas";
$res_total = mysqli_query($con, $query_total);
$total_aulas = mysqli_fetch_array($res_total)['total'];

// Obtener total de aulas en uso
$query_en_uso = "SELECT COUNT(DISTINCT aulas.id_aula) as total FROM aulas INNER JOIN horarios ON horarios.id_aula = aulas.id_aula";
$res_en_uso = mysqli_query($con, $query_en_uso);
$total_en_uso = mysqli_fetch_array($res_en_uso)['total'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, minimum-scale=1.0">
    <link rel="stylesheet" href="../../recursos/styles.css">
    <title>Listado de Aulas</title>
    <style>
/* Contenedor con scroll horizontal */
.tabla-container {
    width: 100%;
    overflow-x: auto;
    overflow-y: visible;
    -webkit-overflow-scrolling: touch;
    margin-bottom: 20px;
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
        width: 100%;
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
    
    .resumen {
        font-size: 0.8rem;
        text-align: center;
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
    </style
</head>
<body>
    <div class="caja">
        <h1> Listado de Aulas</h1>
        
        <!-- Resumen -->
        <div class="resumen">
            <strong>Total de aulas en el sistema: <?php echo $total_aulas; ?></strong> | 
            <strong>Aulas en uso: <?php echo $total_en_uso; ?></strong> | 
            <strong>Aulas disponibles: <?php echo $total_aulas - $total_en_uso; ?></strong>
        </div>
        
        <!-- Búsqueda rápida -->
        <div class="filtro-rapido">
            <form method="GET" action="">
                <label for="buscar_exacto"><strong> Búsqueda rápida:</strong></label>
                <input type="text" name="buscar_exacto" id="buscar_exacto" placeholder="ID, nombre o descripción del aula" value="<?php echo htmlspecialchars($buscar_exacto); ?>">
                <button type="submit">Buscar</button>
                <?php if(!empty($buscar_exacto)): ?>
                    <a href="listado_aulas.php"><button type="button">Limpiar búsqueda</button></a>
                <?php endif; ?>
            </form>
        </div>
        
        <!-- Filtros avanzados -->
        <div class="filtros">
            <form method="GET" action="">
                <h3> Filtros Avanzados</h3>
                
                <div>
                    <label>Nombre (contiene):</label>
                    <input type="text" name="filtro_nombre" placeholder="Ej: Laboratorio" value="<?php echo htmlspecialchars($filtro_nombre); ?>">
                    
                    <label>ID Aula:</label>
                    <input type="number" name="filtro_id" placeholder="Ej: 1" min="1" value="<?php echo htmlspecialchars($filtro_id); ?>">
                </div>
                
                <div style="margin-top: 10px;">
                    <button type="submit">Aplicar Filtros</button>
                    <a href="listado_aulas.php"><button type="button">Limpiar Filtros</button></a>
                </div>
            </form>
        </div>
        
<div class="tabla-container">
        <table border="1" id="listado" class="tabla">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nombre del Aula</th>
                    <th>Descripción</th>
                    <th>Horarios Asignados</th>
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
                        $clase_fila = ($fila['total_horarios'] > 0) ? 'con-horarios' : 'sin-horarios';
                        
                        echo '<tr class="' . $clase_fila . '">';
                        echo '<td>' . $fila['id_aula'] . '</td>';
                        echo '<td><strong>' . htmlspecialchars($fila['aula']) . '</strong></td>';
                        echo '<td>' . (!empty($fila['descripcion']) ? htmlspecialchars($fila['descripcion']) : '<span style="color: #888; font-style: italic;">Sin descripción</span>') . '</td>';
                        echo '<td>' . $fila['total_horarios'] . '</td>';
                        
                        // Botón Editar (solo Admin, Preceptor y Secretario)
                        echo '<td class="no-exportar">';
                        if($_SESSION['rol'] == 'Admin' || $_SESSION['rol'] == 'Preceptor' || $_SESSION['rol'] == 'Secretario'){
                            echo '<a href="editar_aula.php?id=' . $fila['id_aula'] . '"><button> Editar</button></a>';
                        } else {
                            echo '<button disabled>Editar</button>';
                        }
                        echo '</td>';
                        
                        // Botón Eliminar (solo Admin, Preceptor y Secretario, y solo si no tiene horarios)
                        echo '<td class="no-exportar">';
                        if(($_SESSION['rol'] == 'Admin' || $_SESSION['rol'] == 'Preceptor' || $_SESSION['rol'] == 'Secretario') && 
                           $fila['total_horarios'] == 0){
                            echo '<a href="borrar_aula.php?id=' . $fila['id_aula'] . '" onclick="return confirm(\'¿Está seguro que desea eliminar esta aula?\')"><button> Eliminar</button></a>';
                        } else {
                            echo '<button disabled title="' . ($fila['total_horarios'] > 0 ? 'No se puede eliminar porque tiene horarios asignados' : 'No tiene permisos para eliminar') . '">Eliminar</button>';
                        }
                        echo '</td>';
                        echo '</tr>';
                    }
                } else {
                    echo '<tr><td colspan="6">No se encontraron aulas con los filtros aplicados.</td></tr>';
                }
                ?>
            </tbody>
        </table>
</div>
        
        <?php if(isset($contador) && $contador > 0): ?>
        <div class="resumen" style="margin-top: 10px;">
            <strong>Aulas encontradas: <?php echo $contador; ?></strong> | 
            <strong>Con horarios asignados: <?php 
                $query_con_horarios = "SELECT COUNT(DISTINCT a.id_aula) as total FROM aulas a WHERE EXISTS (SELECT 1 FROM horarios h WHERE h.id_aula = a.id_aula)";
                $res_con_horarios = mysqli_query($con, $query_con_horarios);
                $total_con_horarios = mysqli_fetch_array($res_con_horarios)['total'];
                echo $total_con_horarios;
            ?></strong>
        </div>
        <?php endif; ?>
        
        <p>
            <?php if($_SESSION['rol'] == 'Admin' || $_SESSION['rol'] == 'Preceptor' || $_SESSION['rol'] == 'Secretario'): ?>
                <a href="aulas.php"><button> Agregar Nueva Aula</button></a>
            <?php endif; ?>
            <a href="../../recursos/panel.php"><button> Volver al Panel</button></a>
            <button onclick="generarPDF()"> Exportar a PDF</button>
            <button onclick="exportarExcel()"> Exportar a Excel</button>
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
            doc.text('Listado de Aulas - EPET 34', 14, 15);
            
            doc.setFontSize(10);
            doc.setFont(undefined, 'normal');
            const fecha = new Date().toLocaleDateString();
            const hora = new Date().toLocaleTimeString();
            doc.text('Generado el: ' + fecha + ' ' + hora, 14, 22);
            
            // Información de filtros aplicados
            let filtrosAplicados = [];
            <?php if(!empty($buscar_exacto)): ?>
                filtrosAplicados.push('Búsqueda: <?php echo $buscar_exacto; ?>');
            <?php endif; ?>
            <?php if(!empty($filtro_nombre)): ?>
                filtrosAplicados.push('Nombre contiene: <?php echo $filtro_nombre; ?>');
            <?php endif; ?>
            <?php if(!empty($filtro_id)): ?>
                filtrosAplicados.push('ID: <?php echo $filtro_id; ?>');
            <?php endif; ?>
            
            if(filtrosAplicados.length > 0){
                doc.text('Filtros aplicados: ' + filtrosAplicados.join(', '), 14, 29);
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
                startY: 35,
                head: [data[0]],
                body: data.slice(1),
                theme: 'grid',
                styles: {
                    fontSize: 9,
                    cellPadding: 4,
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
                    0: { halign: 'center', cellWidth: 20 }, // ID
                    1: { halign: 'left', cellWidth: 60 },   // Nombre
                    2: { halign: 'left', cellWidth: 100 },  // Descripción
                    3: { halign: 'center', cellWidth: 30 }  // Horarios
                },
                margin: { left: 14, right: 14 },
                didDrawPage: function(data) {
                    doc.setFontSize(8);
                    doc.text('Página ' + doc.internal.getNumberOfPages(), doc.internal.pageSize.width - 20, doc.internal.pageSize.height - 10);
                }
            });
            
            // Estadísticas
            const finalY = doc.lastAutoTable.finalY + 10;
            doc.setFontSize(10);
            doc.text('Total de aulas encontradas: <?php echo isset($contador) ? $contador : 0; ?>', 14, finalY);
            doc.text('Aulas en uso: <?php echo $total_con_horarios ?? 0; ?>', 14, finalY + 7);
            
            // Guardar PDF
            doc.save('listado_aulas_<?php echo date("Y-m-d_H-i"); ?>.pdf');
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
                {wch: 30},  // Nombre
                {wch: 50},  // Descripción
                {wch: 15}   // Horarios
            ];
            ws['!cols'] = colWidths;
            
            XLSX.utils.book_append_sheet(wb, ws, "Aulas");
            
            // Hoja de resumen
            const resumenData = [
                ["RESUMEN DE AULAS"],
                ["Fecha de exportación", new Date().toLocaleString()],
                ["Total de aulas encontradas", <?php echo isset($contador) ? $contador : 0; ?>],
                ["Aulas en uso", <?php echo $total_con_horarios ?? 0; ?>],
                ["Aulas disponibles", <?php echo $total_aulas - $total_en_uso; ?>],
                ["Total aulas en sistema", <?php echo $total_aulas; ?>],
                [" "],
                ["FILTROS APLICADOS"],
                <?php 
                $filtros_excel = [];
                if(!empty($buscar_exacto)) $filtros_excel[] = '["Búsqueda", "' . $buscar_exacto . '"]';
                if(!empty($filtro_nombre)) $filtros_excel[] = '["Nombre contiene", "' . $filtro_nombre . '"]';
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
            
            XLSX.writeFile(wb, 'listado_aulas_<?php echo date("Y-m-d_H-i"); ?>.xlsx');
        }
    </script>
</body>
</html>
<?php
mysqli_close($con);
?>