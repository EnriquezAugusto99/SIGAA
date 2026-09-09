<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

if(!isset($_SESSION['dni'])){
    header("Location: ../../index.php");
    exit();
}

include '../../recursos/conexion.php';

// ============================================
// FUNCIÓN: Convertir hora HH:MM a minutos
// ============================================
function horaAMinutos($hora) {
    $partes = explode(':', $hora);
    return intval($partes[0]) * 60 + intval($partes[1]);
}

// ============================================
// FUNCIÓN: Determinar si una hora es Mañana o Tarde
// ============================================
function getTurnoPorHora($hora) {
    $partes = explode(':', $hora);
    $minutos = intval($partes[0]) * 60 + intval($partes[1]);
    $limite = 12 * 60; // 12:00 hrs en minutos
    return ($minutos < $limite) ? 'M' : 'T';
}

// ============================================
// CARGAR CONFIGURACIÓN DE HORARIOS
// ============================================
$config_file = __DIR__ . '/config_horarios.json';
$config_horarios = [];

if(file_exists($config_file)){
    $config_json = file_get_contents($config_file);
    $config_horarios = json_decode($config_json, true);
}

if(empty($config_horarios) || !is_array($config_horarios)){
    $config_horarios = [
        'mañana' => [
            'normal_inicio' => '06:45',
            'normal_fin' => '07:00',
            'tardanza_inicio' => '07:00',
            'tardanza_fin' => '07:40',
            'falta_inicio' => '07:46'
        ],
        'tarde' => [
            'normal_inicio' => '13:45',
            'normal_fin' => '14:00',
            'tardanza_inicio' => '14:00',
            'tardanza_fin' => '14:40',
            'falta_inicio' => '14:46'
        ]
    ];
}

// ============================================
// FUNCIÓN: Determinar tipo de falta
// ============================================
function determinarTipoFalta($hora, $config_horarios) {
    if(empty($hora)) return 'Sin hora';
    
    $partes = explode(':', $hora);
    if(count($partes) < 2) return 'Hora inválida';
    
    $minutos_actual = intval($partes[0]) * 60 + intval($partes[1]);
    $limite_mediodia = 12 * 60;
    
    if($minutos_actual < $limite_mediodia) {
        $conf = $config_horarios['mañana'];
        $normal_ini = horaAMinutos($conf['normal_inicio']);
        $normal_fin = horaAMinutos($conf['normal_fin']);
        $tardanza_ini = horaAMinutos($conf['tardanza_inicio']);
        $tardanza_fin = horaAMinutos($conf['tardanza_fin']);
        $falta_ini = horaAMinutos($conf['falta_inicio']);
        
        if($minutos_actual >= $normal_ini && $minutos_actual < $normal_fin) {
            return 'Asistencia';
        } elseif($minutos_actual >= $tardanza_ini && $minutos_actual < $tardanza_fin) {
            return 'Tardanza';
        } elseif($minutos_actual >= $falta_ini) {
            return 'Falta';
        } else {
            return 'Fuera de horario';
        }
    } else {
        $conf = $config_horarios['tarde'];
        $normal_ini = horaAMinutos($conf['normal_inicio']);
        $normal_fin = horaAMinutos($conf['normal_fin']);
        $tardanza_ini = horaAMinutos($conf['tardanza_inicio']);
        $tardanza_fin = horaAMinutos($conf['tardanza_fin']);
        $falta_ini = horaAMinutos($conf['falta_inicio']);
        
        if($minutos_actual >= $normal_ini && $minutos_actual < $normal_fin) {
            return 'Asistencia';
        } elseif($minutos_actual >= $tardanza_ini && $minutos_actual < $tardanza_fin) {
            return 'Tardanza';
        } elseif($minutos_actual >= $falta_ini) {
            return 'Falta';
        } else {
            return 'Fuera de horario';
        }
    }
}

// ============================================
// OBTENER FECHA Y TURNO
// ============================================
$fecha = isset($_GET['fecha']) ? $_GET['fecha'] : date('Y-m-d');
$turno = isset($_GET['turno']) ? $_GET['turno'] : '';

if(empty($turno)){
    // Mostrar selector
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Exportar Tardanzas</title>
        <style>
            body { font-family: Arial; background: #f5f5f5; display: flex; justify-content: center; align-items: center; min-height: 100vh; }
            .container { background: white; padding: 40px; border-radius: 16px; max-width: 500px; width: 100%; box-shadow: 0 4px 20px rgba(0,0,0,0.1); }
            h1 { color: #3F070B; text-align: center; }
            .fecha { background: #f0f0f0; padding: 12px; border-radius: 8px; text-align: center; margin: 20px 0; }
            .btn { display: block; padding: 15px; background: #f8f9fa; border: 2px solid #e9ecef; border-radius: 10px; text-align: center; text-decoration: none; color: #333; margin-bottom: 10px; font-weight: 600; }
            .btn:hover { border-color: #710A14; background: #f5f0f0; }
            .btn .cant { background: #710A14; color: white; padding: 2px 12px; border-radius: 20px; font-size: 14px; }
            .volver { display: block; text-align: center; padding: 12px; background: #666; color: white; text-decoration: none; border-radius: 8px; margin-top: 15px; }
            .sin-datos { text-align: center; color: #999; padding: 20px; }
            .separador { border: none; border-top: 2px solid #e0e0e0; margin: 20px 0; }
        </style>
    </head>
    <body>
    <div class="container">
        <h1>Exportar Tardanzas</h1>
        <div class="fecha">Fecha: <?= date('d/m/Y', strtotime($fecha)) ?></div>
        
        <?php
        // Consulta para obtener todas las inasistencias del día con su hora
        $query_all = "SELECT i.id_alumno, i.fecha, i.hora, i.turno_detectado
                      FROM inasistencias i 
                      WHERE DATE(i.fecha) = '$fecha'";
        $res_all = mysqli_query($con, $query_all);
        
        $count_manana = 0;
        $count_tarde = 0;
        $sin_hora = 0;
        
        while($row = mysqli_fetch_assoc($res_all)){
            if(empty($row['hora'])){
                $sin_hora++;
            } else {
                $turno_hora = getTurnoPorHora($row['hora']);
                if($turno_hora == 'M'){
                    $count_manana++;
                } else {
                    $count_tarde++;
                }
            }
        }
        
        $total_registros = $count_manana + $count_tarde + $sin_hora;
        ?>
        
        <?php if($count_manana > 0): ?>
            <a href="?fecha=<?= $fecha ?>&turno=M" class="btn">
                Turno Mañana
                <span class="cant"><?= $count_manana ?></span>
            </a>
        <?php endif; ?>
        
        <?php if($count_tarde > 0): ?>
            <a href="?fecha=<?= $fecha ?>&turno=T" class="btn" style="border-color:#1976D2; background:#e3f2fd;">
                Turno Tarde
                <span class="cant"><?= $count_tarde ?></span>
            </a>
        <?php endif; ?>
        
        <?php if($sin_hora > 0): ?>
            <a href="?fecha=<?= $fecha ?>&turno=SIN" class="btn" style="border-color:#ff9800; background:#fff3e0;">
                Sin hora registrada 
                <span class="cant"><?= $sin_hora ?></span>
            </a>
        <?php endif; ?>
        
        <?php if($total_registros > 1 && $count_manana > 0 && $count_tarde > 0): ?>
            <hr class="separador">
            <a href="?fecha=<?= $fecha ?>&turno=TODOS" class="btn" style="border-color:#710A14;background:#f5f0f0;">
                Todos los turnos 
                <span class="cant"><?= $total_registros ?></span>
            </a>
        <?php endif; ?>
        
        <?php if($total_registros == 0): ?>
            <div class="sin-datos">No hay registros para esta fecha</div>
        <?php endif; ?>
        
        <a href="verificar_biometria.php" class="volver">Volver</a>
    </div>
    </body>
    </html>
    <?php
    exit();
}

// ============================================
// OBTENER DATOS SEGUN TURNO
// ============================================
$where_hora = "";
$where_turno_detectado = "";

if($turno == 'M'){
    $where_hora = " AND TIME(i.hora) < '12:00:00'";
} elseif($turno == 'T'){
    $where_hora = " AND TIME(i.hora) >= '12:00:00'";
} elseif($turno == 'SIN'){
    $where_hora = " AND i.hora IS NULL";
}
// Si es 'TODOS' no filtramos por turno

$query = "SELECT i.id_alumno, u.Apellido, u.Nombre, c.curso, c.division, c.turno as turno_curso,
                 i.fecha, i.hora, i.justificada, i.turno_detectado, i.tipo_falta
          FROM inasistencias i
          INNER JOIN usuario u ON i.id_alumno = u.DNI_U
          INNER JOIN curso c ON i.id_curso = c.ID_curso
          WHERE DATE(i.fecha) = '$fecha'
          $where_hora
          ORDER BY i.hora, u.Apellido, u.Nombre";

$res = mysqli_query($con, $query);

$datos = [];
$total_faltas = 0;
$total_tardanzas = 0;
$total_asistencias = 0;
$total_justificadas = 0;
$total_injustificadas = 0;
$turnos_presentes = [];

while($row = mysqli_fetch_assoc($res)){
    // Determinar el turno real para cada registro
    if(empty($row['hora'])){
        $turno_real = 'SIN';
        $tipo = 'Sin hora';
    } else {
        $turno_real = getTurnoPorHora($row['hora']);
        // Usar el tipo_falta de la base de datos si existe
        if($row['tipo_falta'] == 'tardanza'){
            $tipo = 'Tardanza';
            $total_tardanzas++;
        } elseif($row['tipo_falta'] == 'falta'){
            $tipo = 'Falta';
            $total_faltas++;
        } else {
            $tipo = determinarTipoFalta($row['hora'], $config_horarios);
            if($tipo == 'Tardanza') $total_tardanzas++;
            elseif($tipo == 'Falta') $total_faltas++;
            elseif($tipo == 'Asistencia') $total_asistencias++;
        }
    }
    
    if($row['justificada'] == 1) $total_justificadas++;
    else $total_injustificadas++;
    
    $turno_nombre = $turno_real == 'M' ? 'Mañana' : ($turno_real == 'T' ? 'Tarde' : 'Sin hora');
    if(!in_array($turno_nombre, $turnos_presentes)){
        $turnos_presentes[] = $turno_nombre;
    }
    
    $datos[] = [
        'dni' => $row['id_alumno'],
        'apellido' => $row['Apellido'],
        'nombre' => $row['Nombre'],
        'curso' => $row['curso'] . '° "' . $row['division'] . '"',
        'fecha_hora' => date('d/m/Y', strtotime($row['fecha'])) . ' ' . substr($row['hora'], 0, 5),
        'turno' => $turno_real,
        'tipo' => $tipo,
        'justificacion' => $row['justificada'] ? 'Justificada' : 'Injustificada'
    ];
}

if(empty($datos)){
    echo '<h2>No hay registros</h2>';
    echo '<a href="exportar_asistencias.php">Volver</a>';
    exit();
}

// ============================================
// DETERMINAR EL TÍTULO DEL TURNO
// ============================================
if($turno == 'M'){
    $turno_label = 'Mañana (antes de 12:00)';
} elseif($turno == 'T'){
    $turno_label = 'Tarde (después de 12:00)';
} elseif($turno == 'SIN'){
    $turno_label = 'Sin hora registrada';
} else {
    $turno_label = 'Todos los turnos';
}

// ============================================
// GENERAR XLSX
// ============================================
if(!class_exists('ZipArchive')){
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="inasistencias-' . $fecha . '.csv"');
    echo "\xEF\xBB\xBF";
    $output = fopen('php://output', 'w');
    fputcsv($output, ['DNI', 'Apellido', 'Nombre', 'Curso', 'Fecha y Hora', 'Turno', 'Tipo', 'Justificacion'], ';');
    foreach($datos as $row){
        fputcsv($output, [
            $row['dni'], $row['apellido'], $row['nombre'],
            $row['curso'], $row['fecha_hora'], 
            $row['turno'] == 'M' ? 'Mañana' : ($row['turno'] == 'T' ? 'Tarde' : 'Sin hora'),
            $row['tipo'], $row['justificacion']
        ], ';');
    }
    fclose($output);
    exit();
}

$nombre_archivo = 'inasistencias-' . $fecha . '.xlsx';

$sheet_xml = '<?xml version="1.0" encoding="UTF-8"?>
<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
    <sheetData>
        <row r="1"><c r="A1" s="1" t="inlineStr"><is><t>INFORME DE INASISTENCIAS</t></is></c></row>
        <row r="2"><c r="A2" t="inlineStr"><is><t>Fecha: ' . date('d/m/Y', strtotime($fecha)) . ' - Turno: ' . $turno_label . '</t></is></c></row>
        <row r="3"/>
        <row r="4" ht="20">
            <c r="A4" s="1" t="inlineStr"><is><t>DNI</t></is></c>
            <c r="B4" s="1" t="inlineStr"><is><t>Apellido</t></is></c>
            <c r="C4" s="1" t="inlineStr"><is><t>Nombre</t></is></c>
            <c r="D4" s="1" t="inlineStr"><is><t>Curso</t></is></c>
            <c r="E4" s="1" t="inlineStr"><is><t>Fecha y Hora</t></is></c>
            <c r="F4" s="1" t="inlineStr"><is><t>Turno</t></is></c>
            <c r="G4" s="1" t="inlineStr"><is><t>Tipo</t></is></c>
            <c r="H4" s="1" t="inlineStr"><is><t>Justificacion</t></is></c>
        </row>';

$fila = 5;
foreach($datos as $row){
    $turno_texto = $row['turno'] == 'M' ? 'Mañana' : ($row['turno'] == 'T' ? 'Tarde' : 'Sin hora');
    $sheet_xml .= '
        <row r="' . $fila . '">
            <c r="A' . $fila . '" t="n"><v>' . $row['dni'] . '</v></c>
            <c r="B' . $fila . '" t="inlineStr"><is><t>' . htmlspecialchars($row['apellido']) . '</t></is></c>
            <c r="C' . $fila . '" t="inlineStr"><is><t>' . htmlspecialchars($row['nombre']) . '</t></is></c>
            <c r="D' . $fila . '" t="inlineStr"><is><t>' . htmlspecialchars($row['curso']) . '</t></is></c>
            <c r="E' . $fila . '" t="inlineStr"><is><t>' . $row['fecha_hora'] . '</t></is></c>
            <c r="F' . $fila . '" t="inlineStr"><is><t>' . $turno_texto . '</t></is></c>
            <c r="G' . $fila . '" t="inlineStr"><is><t>' . $row['tipo'] . '</t></is></c>
            <c r="H' . $fila . '" t="inlineStr"><is><t>' . $row['justificacion'] . '</t></is></c>
        </row>';
    $fila++;
}

$sheet_xml .= '
        <row r="' . ($fila + 1) . '"><c r="A' . ($fila + 1) . '" t="inlineStr"><is><t>RESUMEN</t></is></c></row>
        <row r="' . ($fila + 2) . '">
            <c r="A' . ($fila + 2) . '" t="inlineStr"><is><t>Total: ' . count($datos) . '</t></is></c>
            <c r="B' . ($fila + 2) . '" t="inlineStr"><is><t>Faltas: ' . $total_faltas . '</t></is></c>
            <c r="C' . ($fila + 2) . '" t="inlineStr"><is><t>Tardanzas: ' . $total_tardanzas . '</t></is></c>
            <c r="D' . ($fila + 2) . '" t="inlineStr"><is><t>Asistencias: ' . $total_asistencias . '</t></is></c>
            <c r="E' . ($fila + 2) . '" t="inlineStr"><is><t>Justificadas: ' . $total_justificadas . '</t></is></c>
            <c r="F' . ($fila + 2) . '" t="inlineStr"><is><t>Injustificadas: ' . $total_injustificadas . '</t></is></c>
        </row>
    </sheetData>
</worksheet>';

$zip = new ZipArchive();
$temp = tempnam(sys_get_temp_dir(), 'xlsx_');

if($zip->open($temp, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true){
    die('Error al crear el archivo ZIP');
}

$zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/></Types>');
$zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
$zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Inasistencias" sheetId="1" r:id="rId1"/></sheets></workbook>');
$zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/></Relationships>');
$zip->addFromString('xl/styles.xml', '<?xml version="1.0" encoding="UTF-8"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><fonts count="2"><font><name val="Arial"/><sz val="10"/></font><font><name val="Arial"/><b/><sz val="11"/><color rgb="FFFFFFFF"/></font></fonts><fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FF7A0000"/></patternFill></fill></fills><cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs><cellXfs count="2"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/><xf numFmtId="0" fontId="1" fillId="1" borderId="0" applyFont="1" applyFill="1"/></cellXfs></styleSheet>');
$zip->addFromString('xl/worksheets/sheet1.xml', $sheet_xml);

$zip->close();

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $nombre_archivo . '"');
header('Cache-Control: max-age=0');
header('Content-Length: ' . filesize($temp));

readfile($temp);
unlink($temp);
exit();
?>