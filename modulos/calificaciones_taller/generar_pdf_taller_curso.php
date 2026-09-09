<?php
session_start();

if(!isset($_SESSION['pdf_taller_curso_data'])){
    header('Location: exportar_planilla_taller_curso_pdf.php');
    exit();
}

$data = $_SESSION['pdf_taller_curso_data'];
$curso = $data['curso'];
$grupos = $data['grupos'];
$ordenTalleresPorGrupo = $data['orden_talleres_por_grupo'];
$alumnosPorGrupo = $data['alumnos_por_grupo'];
$calificaciones = $data['calificaciones'];

unset($_SESSION['pdf_taller_curso_data']);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Generando PDF - Planilla de Talleres</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.8.2/jspdf.plugin.autotable.min.js"></script>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; display: flex; justify-content: center; align-items: center; min-height: 100vh; background: #f0f0f0; margin: 0; }
        .loading-container { text-align: center; background: white; padding: 40px; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.1); min-width: 350px; }
        .spinner { width: 50px; height: 50px; border: 5px solid #f3f3f3; border-top: 5px solid #7a0000; border-radius: 50%; animation: spin 1s linear infinite; margin: 0 auto 20px; }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
        .progress-bar { width: 300px; height: 8px; background: #ddd; border-radius: 4px; margin: 20px auto; overflow: hidden; }
        .progress-fill { width: 0%; height: 100%; background: #7a0000; transition: width 0.3s; }
        button { background: #7a0000; color: white; border: none; padding: 10px 20px; border-radius: 6px; cursor: pointer; margin-top: 20px; }
        button:hover { background: #8b0000; }
        .error { color: #d32f2f; margin-top: 15px; }
    </style>
</head>
<body>
<div class="loading-container">
    <div class="spinner"></div>
    <div class="progress-bar"><div class="progress-fill" id="progressFill"></div></div>
    <p id="statusText">Generando planilla de talleres...</p>
    <p id="detailText" style="font-size: 12px; color: #666;"></p>
    <button id="downloadBtn" style="display: none;">⬇️ Descargar PDF</button>
    <div id="errorMsg" class="error" style="display: none;"></div>
</div>

<script>
    const { jsPDF } = window.jspdf;
    
    const curso = <?= json_encode($curso) ?>;
    const grupos = <?= json_encode($grupos) ?>;
    const ordenTalleresPorGrupo = <?= json_encode($ordenTalleresPorGrupo) ?>;
    const alumnosPorGrupo = <?= json_encode($alumnosPorGrupo) ?>;
    const calificaciones = <?= json_encode($calificaciones) ?>;
    
    const progressFill = document.getElementById('progressFill');
    const statusText = document.getElementById('statusText');
    const detailText = document.getElementById('detailText');
    const downloadBtn = document.getElementById('downloadBtn');
    const errorMsg = document.getElementById('errorMsg');
    
    function updateProgress(percent, status, detail) {
        progressFill.style.width = percent + '%';
        if(status) statusText.innerHTML = status;
        if(detail) detailText.innerHTML = detail;
    }
    
    function showError(message) {
        errorMsg.style.display = 'block';
        errorMsg.innerHTML = '❌ ' + message;
        statusText.innerHTML = 'Error al generar el PDF';
        document.querySelector('.spinner').style.display = 'none';
    }
    
    function limpiarTexto(texto) {
        if (!texto) return '';
        return String(texto).replace(/\n/g, ' ').replace(/\s+/g, ' ').trim();
    }
    
    function formatearNota(nota) {
        if(nota === null || nota === undefined || nota === '') return '';
        let num = parseFloat(nota);
        if(isNaN(num)) return '';
        if(num === Math.floor(num)) return num.toString();
        let conDecimales = num.toFixed(2);
        if(conDecimales.endsWith('.00')) return Math.floor(num).toString();
        return conDecimales;
    }
    
    async function generarPDF() {
        try {
            updateProgress(5, 'Inicializando PDF...', '');
            
            const doc = new jsPDF('l', 'mm', 'a4');
            const anchoHoja = doc.internal.pageSize.getWidth();
            const altoHoja = doc.internal.pageSize.getHeight();
            
            const gruposOrdenados = [...grupos].sort((a,b) => a - b);
            
            // Determinar cuántas rotaciones tiene el primer grupo (todas tienen 6)
            const primerGrupo = gruposOrdenados[0];
            const talleresDelGrupo = ordenTalleresPorGrupo[primerGrupo] || [];
            const cantidadRotaciones = talleresDelGrupo.length; // Debería ser 6
            
            // Recolectar alumnos por grupo (con DISTINCT ya aplicado en SQL)
            const alumnosPorGrupoOrdenado = {};
            let totalAlumnos = 0;
            
            for(const grupo of gruposOrdenados) {
                const alumnosGrupo = alumnosPorGrupo[grupo] || [];
                alumnosPorGrupoOrdenado[grupo] = alumnosGrupo;
                totalAlumnos += alumnosGrupo.length;
            }
            
            if(totalAlumnos === 0) {
                showError('No hay alumnos en este curso');
                return;
            }
            
            updateProgress(10, 'Procesando alumnos...', `Total: ${totalAlumnos}`);
            
            // ============================================
            // CABECERAS - Formato EXACTO del Excel
            // Columnas: N° | Apellido y Nombres | 
            // Por cada rotación: [Taller] + 5 notas + P + R + PF + CD
            // ============================================
            
            // Para la cabecera, usamos el orden del primer grupo (todos los grupos tienen el mismo orden de rotaciones)
            const superHeaders = [
                { content: 'N°', rowSpan: 2, styles: { halign: 'center', valign: 'middle', fontSize: 7 } },
                { content: 'APELLIDO Y NOMBRES', rowSpan: 2, styles: { halign: 'left', valign: 'middle', fontSize: 7 } }
            ];
            
            const subHeaders = ['N°', 'Alumno'];
            
            // Por cada rotación (1 a 6)
            for(let idx = 0; idx < cantidadRotaciones; idx++) {
                const tallerInfo = talleresDelGrupo[idx];
                if(!tallerInfo) continue;
                
                // Nombre del taller para el superheader
                const nombreTaller = `${tallerInfo.taller_nombre} (${tallerInfo.anio_taller})`;
                const rotacionNombre = tallerInfo.rotacion_nombre;
                
                // Superheader: rotación + taller
                superHeaders.push({
                    content: `${rotacionNombre}\n${nombreTaller}`,
                    colSpan: 9,  // 5 notas + P + R + PF + CD = 9 columnas
                    styles: { halign: 'center', fontSize: 5.5, valign: 'middle' }
                });
                
                // Subheaders: 1, 2, 3, 4, 5, P, R, PF, CD
                subHeaders.push('1', '2', '3', '4', '5', 'P', 'R', 'PF', 'CD');
            }
            
            updateProgress(30, 'Construyendo tabla...', `${cantidadRotaciones} rotaciones, 9 columnas cada una`);
            
            // ============================================
            // CONSTRUCCIÓN DEL CUERPO
            // ============================================
            const tableBody = [];
            
            for(const grupo of gruposOrdenados) {
                const alumnosGrupo = alumnosPorGrupoOrdenado[grupo] || [];
                if(alumnosGrupo.length === 0) continue;
                
                // Obtener el orden de talleres para este grupo específico
                const ordenTalleres = ordenTalleresPorGrupo[grupo] || [];
                
                // Fila de título del grupo (como en el Excel)
                const groupRow = [];
                groupRow.push('');
                groupRow.push(`>>> GRUPO ${grupo} <<<`);
                for(let i = 0; i < cantidadRotaciones * 9; i++) groupRow.push('');
                tableBody.push(groupRow);
                
                let numAlumno = 1;
                for(const alumno of alumnosGrupo) {
                    const row = [numAlumno.toString(), limpiarTexto(`${alumno.Apellido}, ${alumno.Nombre}`)];
                    
                    // Para cada rotación (en el orden correcto para este grupo)
                    for(const tallerInfo of ordenTalleres) {
                        const rotacionId = tallerInfo.numero_rotacion;
                        const tallerId = tallerInfo.ID_taller;
                        
                        // Buscar calificaciones del alumno para esta rotación y taller
                        const calif = calificaciones[alumno.DNI_U]?.[rotacionId]?.[tallerId] || {};
                        
                        // 5 notas
                        row.push(formatearNota(calif.nota1));
                        row.push(formatearNota(calif.nota2));
                        row.push(formatearNota(calif.nota3));
                        row.push(formatearNota(calif.nota4));
                        row.push(formatearNota(calif.nota5));
                        
                        // P (Promedio)
                        row.push(formatearNota(calif.promedio));
                        
                        // R (Recuperatorio)
                        row.push(formatearNota(calif.recuperatorio));
                        
                        // PF (Promedio Final)
                        row.push(formatearNota(calif.promedio_final));
                        
                        // CD (Calificación Definitiva)
                        row.push(formatearNota(calif.calificacion_definitiva));
                    }
                    
                    tableBody.push(row);
                    numAlumno++;
                }
            }
            
            updateProgress(60, 'Calculando anchos...', `${tableBody.length} filas`);
            
            // ============================================
            // ANCHOS DE COLUMNAS
            // ============================================
            const anchoDisponible = anchoHoja - 20;
            const anchoNumero = 6;
            const anchoNombre = Math.min(50, anchoDisponible * 0.12);
            const anchoColumna = Math.min(7, (anchoDisponible - anchoNumero - anchoNombre) / (cantidadRotaciones * 9));
            
            const columnStyles = {
                0: { cellWidth: anchoNumero, halign: 'center' },
                1: { cellWidth: anchoNombre, halign: 'left', overflow: 'ellipsize', cellPadding: 0.8 }
            };
            
            let colIndex = 2;
            for(let i = 0; i < cantidadRotaciones; i++) {
                for(let j = 0; j < 9; j++) {
                    columnStyles[colIndex] = { cellWidth: anchoColumna, halign: 'center' };
                    colIndex++;
                }
            }
            
            updateProgress(70, 'Generando PDF...', '');
            
            // ============================================
            // LOGO
            // ============================================
            const logoUrl = '../../recursos/logo_epet.jpg';
            const cargarLogo = () => {
                return new Promise((resolve) => {
                    const img = new Image();
                    img.crossOrigin = 'Anonymous';
                    img.onload = function() {
                        const logoWidth = 16;
                        const logoHeight = (img.height * logoWidth) / img.width;
                        doc.addImage(img, 'PNG', anchoHoja - logoWidth - 10, 5, logoWidth, logoHeight);
                        resolve();
                    };
                    img.onerror = function() { resolve(); };
                    img.src = logoUrl;
                });
            };
            
            await cargarLogo();
            
            // ============================================
            // ENCABEZADO
            // ============================================
            doc.setFont("Helvetica", "bold");
            doc.setFontSize(12);
            doc.setTextColor(122, 0, 0);
            doc.text('Planilla de Talleres del Ciclo Básico', anchoHoja / 2, 8, { align: 'center' });
            doc.setDrawColor(122, 0, 0);
            doc.setLineWidth(0.3);
            doc.line(anchoHoja / 2 - 40, 10, anchoHoja / 2 + 40, 10);
            
            doc.setFont("Helvetica", "bold");
            doc.setFontSize(9);
            doc.setTextColor(0, 0, 0);
            const turnoTexto = curso.turno === 'T' ? 'Tarde' : 'Mañana';
            doc.text(`Curso: ${curso.curso}° "${curso.division}" - Turno: ${turnoTexto} - Año: ${new Date().getFullYear()}`, 10, 17);
            doc.setFont("Helvetica", "normal");
            doc.setFontSize(7);
            doc.text('Establecimiento: E.P.E.T N° 34', 10, 22);
            
            updateProgress(85, 'Renderizando tabla...', '');
            
            // ============================================
            // TABLA PRINCIPAL
            // ============================================
            doc.autoTable({
                head: [superHeaders, subHeaders],
                body: tableBody,
                startY: 26,
                theme: 'grid',
                styles: {
                    fontSize: 5.5,
                    cellPadding: 0.8,
                    halign: 'center',
                    valign: 'middle',
                    lineWidth: 0.1
                },
                headStyles: {
                    fillColor: [240, 240, 240],
                    textColor: [0, 0, 0],
                    fontStyle: 'bold',
                    fontSize: 5.5,
                    cellPadding: 1
                },
                columnStyles: columnStyles,
                margin: { left: 10, right: 10 },
                didDrawPage: function(data) {
                    const pageCount = doc.internal.getNumberOfPages();
                    doc.setFontSize(6);
                    doc.setTextColor(100, 100, 100);
                    doc.text(`Página ${data.pageNumber} de ${pageCount}`, anchoHoja - 15, altoHoja - 5, { align: 'right' });
                    
                    if(data.pageNumber === pageCount) {
                        doc.setFontSize(6);
                        doc.setTextColor(0, 0, 0);
                        doc.text('P: Promedio / R: Recuperatorio / PF: Promedio Final / CD: Calificación Definitiva', 10, altoHoja - 8);
                        doc.text('Firma:', 10, altoHoja - 3);
                        doc.text('Firma:', anchoHoja - 30, altoHoja - 3);
                    }
                }
            });
            
            updateProgress(95, 'Finalizando...', '');
            
            const nombreArchivo = `Planilla_Talleres_${curso.curso}°_${curso.division}_${new Date().getFullYear()}.pdf`;
            doc.save(nombreArchivo);
            
            updateProgress(100, '¡PDF generado con éxito!', '');
            downloadBtn.style.display = 'inline-block';
            document.querySelector('.spinner').style.display = 'none';
            
        } catch(error) {
            console.error('Error:', error);
            showError(error.message);
        }
    }
    
    downloadBtn.addEventListener('click', () => {
        window.location.href = 'exportar_planilla_taller_curso_pdf.php';
    });
    
    generarPDF();
</script>
</body>
</html>