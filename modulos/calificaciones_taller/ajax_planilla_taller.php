<?php
session_start();

if(isset($_SESSION["dni"]) && (time() - $_SESSION["ultima_actividad"] > 18000)){
    echo '<div class="mensaje-error">Sesión expirada</div>';
    exit();
}

if(!isset($_SESSION["dni"])){
    echo '<div class="mensaje-error">No autorizado</div>';
    exit();
}

include '../../recursos/conexion.php';

$taller_id = $_POST['taller_id'];
$rotacion_id = $_POST['rotacion_id'];
$curso_id = $_POST['curso_id'];
$grupo_id = $_POST['grupo_id'];
$anio_actual = date('Y');

$es_admin = ($_SESSION['rol'] == 'Admin');
$docente_dni = $_SESSION["dni"];

// Verificar permisos
if(!$es_admin){
    $query_permiso = "SELECT ID_dtc FROM docente_taller_curso 
                      WHERE ID_docente = '$docente_dni' 
                      AND ID_taller = '$taller_id' 
                      AND anio = '$anio_actual'";
    $res_permiso = mysqli_query($con, $query_permiso);
    if(mysqli_num_rows($res_permiso) == 0){
        echo '<div class="mensaje-error">No tiene permisos para este taller</div>';
        exit();
    }
}

// PROCESAR GUARDADO SI SE ENVÍA EL FORMULARIO
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['guardar_calificaciones'])){
    $total_guardados = 0;
    
    foreach($_POST['notas'] as $alumno_id => $data){
        // Reemplazar comas por puntos y validar notas antes de usar floatval
        $nota1 = (!empty($data['nota1']) && $data['nota1'] !== '') ? round(floatval(str_replace(',', '.', $data['nota1'])), 2) : null;
        $nota2 = (!empty($data['nota2']) && $data['nota2'] !== '') ? round(floatval(str_replace(',', '.', $data['nota2'])), 2) : null;
        $nota3 = (!empty($data['nota3']) && $data['nota3'] !== '') ? round(floatval(str_replace(',', '.', $data['nota3'])), 2) : null;
        $nota4 = (!empty($data['nota4']) && $data['nota4'] !== '') ? round(floatval(str_replace(',', '.', $data['nota4'])), 2) : null;
        $nota5 = (!empty($data['nota5']) && $data['nota5'] !== '') ? round(floatval(str_replace(',', '.', $data['nota5'])), 2) : null;
        $recuperatorio = (!empty($data['recuperatorio']) && $data['recuperatorio'] !== '') ? round(floatval(str_replace(',', '.', $data['recuperatorio'])), 2) : null;
        $promedio = (!empty($data['promedio']) && $data['promedio'] != '-') ? round(floatval(str_replace(',', '.', $data['promedio'])), 2) : null;
        $promedio_final = (!empty($data['promedio_final']) && $data['promedio_final'] != '-') ? round(floatval(str_replace(',', '.', $data['promedio_final'])), 2) : null;
        $calificacion_definitiva = (!empty($data['calificacion_definitiva']) && $data['calificacion_definitiva'] != '-') ? round(floatval(str_replace(',', '.', $data['calificacion_definitiva'])), 2) : null;
        
        // Verificar si existe el registro
        $query_check = "SELECT ID_calif_taller FROM calificaciones_taller 
                        WHERE ID_alumno = '$alumno_id' 
                        AND ID_taller = '$taller_id' 
                        AND ID_rotacion = '$rotacion_id'";
        $res_check = mysqli_query($con, $query_check);
        
        if(mysqli_num_rows($res_check) > 0){
            $query = "UPDATE calificaciones_taller 
                      SET nota1 = " . ($nota1 !== null ? "'$nota1'" : "NULL") . ",
                          nota2 = " . ($nota2 !== null ? "'$nota2'" : "NULL") . ",
                          nota3 = " . ($nota3 !== null ? "'$nota3'" : "NULL") . ",
                          nota4 = " . ($nota4 !== null ? "'$nota4'" : "NULL") . ",
                          nota5 = " . ($nota5 !== null ? "'$nota5'" : "NULL") . ",
                          recuperatorio = " . ($recuperatorio !== null ? "'$recuperatorio'" : "NULL") . ",
                          promedio = " . ($promedio !== null ? "'$promedio'" : "NULL") . ",
                          promedio_final = " . ($promedio_final !== null ? "'$promedio_final'" : "NULL") . ",
                          calificacion_definitiva = " . ($calificacion_definitiva !== null ? "'$calificacion_definitiva'" : "NULL") . ",
                          fecha = CURDATE()
                      WHERE ID_alumno = '$alumno_id' 
                      AND ID_taller = '$taller_id' 
                      AND ID_rotacion = '$rotacion_id'";
        } else {
            $query = "INSERT INTO calificaciones_taller (ID_alumno, ID_taller, ID_rotacion, nota1, nota2, nota3, nota4, nota5, recuperatorio, promedio, promedio_final, calificacion_definitiva, fecha) 
                      VALUES ('$alumno_id', '$taller_id', '$rotacion_id', " . 
                      ($nota1 !== null ? "'$nota1'" : "NULL") . ", " .
                      ($nota2 !== null ? "'$nota2'" : "NULL") . ", " .
                      ($nota3 !== null ? "'$nota3'" : "NULL") . ", " .
                      ($nota4 !== null ? "'$nota4'" : "NULL") . ", " .
                      ($nota5 !== null ? "'$nota5'" : "NULL") . ", " .
                      ($recuperatorio !== null ? "'$recuperatorio'" : "NULL") . ", " .
                      ($promedio !== null ? "'$promedio'" : "NULL") . ", " .
                      ($promedio_final !== null ? "'$promedio_final'" : "NULL") . ", " .
                      ($calificacion_definitiva !== null ? "'$calificacion_definitiva'" : "NULL") . ", CURDATE())";
        }
        
        if(mysqli_query($con, $query)){
            $total_guardados++;
        } else {
            echo '<div class="mensaje-error">Error al guardar: ' . mysqli_error($con) . '</div>';
        }
    }
    
    $mensaje = '<div class="mensaje-success">✅ Calificaciones guardadas correctamente. (' . $total_guardados . ' alumnos)</div>';
}

// Obtener alumnos del grupo
$query_alumnos = "SELECT DISTINCT u.DNI_U, u.Nombre, u.Apellido
                  FROM usuario u
                  INNER JOIN alumno_grupo_taller agt ON u.DNI_U = agt.ID_alumno
                  WHERE agt.ID_curso = '$curso_id'
                  AND agt.numero_grupo = '$grupo_id'
                  AND agt.anio = '$anio_actual'
                  AND u.ID_rol = 3
                  AND u.ID_Estado = 1
                  ORDER BY u.Apellido, u.Nombre";
$res_alumnos = mysqli_query($con, $query_alumnos);
$alumnos = [];
while($row = mysqli_fetch_array($res_alumnos)){
    $alumnos[] = $row;
}

if(empty($alumnos)){
    echo '<div class="mensaje-error">No hay alumnos en este grupo</div>';
    exit();
}

// Obtener calificaciones existentes
$query_calif = "SELECT ID_alumno, nota1, nota2, nota3, nota4, nota5, recuperatorio, promedio, promedio_final, calificacion_definitiva 
                FROM calificaciones_taller 
                WHERE ID_taller = '$taller_id' 
                AND ID_rotacion = '$rotacion_id'";
$res_calif = mysqli_query($con, $query_calif);
$calificaciones = [];
while($row = mysqli_fetch_array($res_calif)){
    $calificaciones[$row['ID_alumno']] = [
        'nota1' => $row['nota1'],
        'nota2' => $row['nota2'],
        'nota3' => $row['nota3'],
        'nota4' => $row['nota4'],
        'nota5' => $row['nota5'],
        'recuperatorio' => $row['recuperatorio'],
        'promedio' => $row['promedio'],
        'promedio_final' => $row['promedio_final'],
        'calificacion_definitiva' => $row['calificacion_definitiva']
    ];
}

// Obtener información de la rotación
$q_rot = "SELECT nombre, fecha_inicio, fecha_fin FROM rotaciones WHERE ID_rotacion = '$rotacion_id'";
$r_rot = mysqli_query($con, $q_rot);
$rot_info = mysqli_fetch_array($r_rot);

// Obtener nombre del taller
$q_taller = "SELECT nombre FROM talleres WHERE ID_taller = '$taller_id'";
$r_taller = mysqli_query($con, $q_taller);
$taller_info = mysqli_fetch_array($r_taller);
?>

<style>
    .tabla-planilla {
        width: 100%;
        border-collapse: collapse;
        margin-top: 20px;
        font-size: 13px;
    }
    .tabla-planilla th, .tabla-planilla td {
        border: 1px solid #ddd;
        padding: 8px;
        text-align: center;
        vertical-align: middle;
    }
    .tabla-planilla th {
        background-color: #7a0000;
        color: white;
        font-weight: bold;
    }
    .tabla-planilla td.alumno-nombre {
        text-align: left;
        background-color: #f9f9f9;
    }
    .nota-input {
        width: 60px;
        padding: 6px;
        text-align: center;
        border: 1px solid #ccc;
        border-radius: 4px;
    }
    .nota-input:focus {
        outline: none;
        border-color: #7a0000;
    }
    .nota-input.invalid {
        border-color: #d32f2f;
        background-color: #ffebee;
    }
    .promedio-cell, .promedio-final-cell, .definitiva-cell {
        font-weight: bold;
        background-color: #f0f0f0;
    }
    .promedio-cell.aprobado, .promedio-final-cell.aprobado, .definitiva-cell.aprobado {
        color: #2e7d32;
    }
    .promedio-cell.desaprobado, .promedio-final-cell.desaprobado, .definitiva-cell.desaprobado {
        color: #d32f2f;
    }
    .info-rotacion {
        background: #e3f2fd;
        padding: 12px 15px;
        border-radius: 8px;
        margin-bottom: 20px;
        border-left: 4px solid #2196f3;
    }
    .mensaje-success {
        background: #d4edda;
        color: #155724;
        padding: 12px;
        border-radius: 6px;
        margin: 15px 0;
    }
    .btn-guardar {
        background: #4caf50;
        color: white;
        padding: 12px 24px;
        border: none;
        border-radius: 6px;
        cursor: pointer;
        font-weight: bold;
        margin-top: 20px;
    }
    .btn-guardar:hover {
        background: #45a049;
    }
    .numero-col {
        width: 40px;
        background-color: #f5f5f5;
        font-weight: bold;
    }
</style>

<div class="info-rotacion">
    <strong>📚 <?= htmlspecialchars($taller_info['nombre']) ?></strong> | 
    Rotación: <?= htmlspecialchars($rot_info['nombre']) ?> | 
    Fechas: <?= date('d/m/Y', strtotime($rot_info['fecha_inicio'])) ?> al <?= date('d/m/Y', strtotime($rot_info['fecha_fin'])) ?>
</div>

<?php if(isset($mensaje)) echo $mensaje; ?>

<form method="POST" action="" id="formPlanilla">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="guardar_calificaciones" value="1">
    <input type="hidden" name="taller_id" value="<?= $taller_id ?>">
    <input type="hidden" name="rotacion_id" value="<?= $rotacion_id ?>">
    
    <table class="tabla-planilla">
        <thead>
            <tr>
                <th rowspan="2">Nº</th>
                <th rowspan="2">Apellido y Nombre</th>
                <th colspan="5">Calificaciones</th>
                <th rowspan="2">Promedio (P)</th>
                <th rowspan="2">Recuperatorio (R)</th>
                <th rowspan="2">Promedio Final (PF)</th>
                <th rowspan="2">Calif. Definitiva (CD)</th>
            </tr>
            <tr>
                <th>Nota 1</th>
                <th>Nota 2</th>
                <th>Nota 3</th>
                <th>Nota 4</th>
                <th>Nota 5</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $contador = 1;
            foreach($alumnos as $alumno): 
                $notas = $calificaciones[$alumno['DNI_U']] ?? [
                    'nota1' => '', 'nota2' => '', 'nota3' => '', 'nota4' => '', 'nota5' => '',
                    'recuperatorio' => '', 'promedio' => '', 'promedio_final' => '', 'calificacion_definitiva' => ''
                ];
            ?>
                <tr data-alumno="<?= $alumno['DNI_U'] ?>">
                    <td class="numero-col"><?= $contador++ ?></td>
                    <td class="alumno-nombre"><?= htmlspecialchars($alumno['Apellido'] . ', ' . $alumno['Nombre']) ?></td>
                    <!-- Cambiados a type="text" para admitir comas de manera nativa -->
                    <td><input type="text" class="nota-input nota1" name="notas[<?= $alumno['DNI_U'] ?>][nota1]" value="<?= $notas['nota1'] ?>"></td>
                    <td><input type="text" class="nota-input nota2" name="notas[<?= $alumno['DNI_U'] ?>][nota2]" value="<?= $notas['nota2'] ?>"></td>
                    <td><input type="text" class="nota-input nota3" name="notas[<?= $alumno['DNI_U'] ?>][nota3]" value="<?= $notas['nota3'] ?>"></td>
                    <td><input type="text" class="nota-input nota4" name="notas[<?= $alumno['DNI_U'] ?>][nota4]" value="<?= $notas['nota4'] ?>"></td>
                    <td><input type="text" class="nota-input nota5" name="notas[<?= $alumno['DNI_U'] ?>][nota5]" value="<?= $notas['nota5'] ?>"></td>
                    <td class="promedio-cell" id="promedio_<?= $alumno['DNI_U'] ?>"><?= $notas['promedio'] ? number_format($notas['promedio'], 2) : '-' ?></td>
                    <td><input type="text" class="nota-input recuperatorio" name="notas[<?= $alumno['DNI_U'] ?>][recuperatorio]" value="<?= $notas['recuperatorio'] ?>"></td>
                    <td class="promedio-final-cell" id="pf_<?= $alumno['DNI_U'] ?>"><?= $notas['promedio_final'] ? number_format($notas['promedio_final'], 2) : '-' ?></td>
                    <td class="definitiva-cell" id="cd_<?= $alumno['DNI_U'] ?>"><?= $notas['calificacion_definitiva'] ? number_format($notas['calificacion_definitiva'], 2) : '-' ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    
    <button type="submit" class="btn-guardar">💾 Guardar Calificaciones</button>
</form>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
function validarNota(input, esBlur = false) {
    let valor = input.val().trim();
    if (valor === '') {
        input.removeClass('invalid');
        return true;
    }
    
    // Normalizar comas a puntos para la lógica de JavaScript
    let valorNormalizado = valor.replace(',', '.');
    let num = parseFloat(valorNormalizado);
    
    if (isNaN(num)) {
        if(esBlur) input.val('');
        input.removeClass('invalid');
        return false;
    }
    
    // Redondear temporalmente a 2 decimales para la comprobación
    num = Math.round(num * 100) / 100;
    
    if (num < 1 || num > 10) {
        input.addClass('invalid');
        if (esBlur) {
            num = num < 1 ? 1 : 10;
            input.val(num);
            input.removeClass('invalid');
        }
        return false;
    }
    
    input.removeClass('invalid');
    
    // Al salir de la celda (blur), normalizamos el diseño visual en la caja de texto
    if (esBlur) {
        if (num === Math.floor(num)) {
            input.val(num);
        } else {
            input.val(num.toFixed(2));
        }
    }
    
    return true;
}

function calcularPromedio(notas) {
    let notasValidas = notas.filter(n => n !== null && !isNaN(n) && n >= 1 && n <= 10);
    if(notasValidas.length >= 3) {
        let suma = notasValidas.reduce((a, b) => a + b, 0);
        let promedio = suma / notasValidas.length;
        return Math.floor(promedio * 100) / 100;
    }
    return null;
}

function recalcularFila(fila) {
    let alumnoId = fila.data('alumno');
    
    // Obtener las 5 notas convirtiendo dinámicamente comas a puntos
    let notas = [];
    for(let i = 1; i <= 5; i++) {
        let input = fila.find(`.nota${i}`);
        let valor = parseFloat(input.val().replace(',', '.'));
        notas.push(!isNaN(valor) && valor >= 1 && valor <= 10 ? valor : null);
    }
    
    // Calcular promedio
    let promedio = calcularPromedio(notas);
    let celdaPromedio = $(`#promedio_${alumnoId}`);
    let recuperatorioInput = fila.find('.recuperatorio');
    let recuperatorio = parseFloat(recuperatorioInput.val().replace(',', '.'));
    let celdaPF = $(`#pf_${alumnoId}`);
    let celdaCD = $(`#cd_${alumnoId}`);
    
    // Actualizar campo oculto del promedio
    if(promedio !== null) {
        celdaPromedio.text(promedio.toFixed(2));
        celdaPromedio.removeClass('aprobado desaprobado').addClass(promedio >= 6 ? 'aprobado' : 'desaprobado');
        fila.find(`input[name="notas[${alumnoId}][promedio]"]`).remove();
        fila.append(`<input type="hidden" name="notas[${alumnoId}][promedio]" value="${promedio}">`);
    } else {
        celdaPromedio.text('-');
        celdaPromedio.removeClass('aprobado desaprobado');
    }
    
    // Calcular Promedio Final y Calificación Definitiva
    let promedioFinal = null;
    
    if(promedio !== null) {
        if(promedio >= 6) {
            promedioFinal = promedio;
        } else if(!isNaN(recuperatorio) && recuperatorio >= 1 && recuperatorio <= 10) {
            if(recuperatorio >= 6) {
                promedioFinal = recuperatorio;
            } else {
                promedioFinal = Math.min(promedio, recuperatorio);
            }
        }
    }
    
    // Actualizar campos visuales y ocultos para persistencia POST
    if(promedioFinal !== null) {
        celdaPF.text(promedioFinal.toFixed(2));
        celdaPF.removeClass('aprobado desaprobado').addClass(promedioFinal >= 6 ? 'aprobado' : 'desaprobado');
        celdaCD.text(promedioFinal.toFixed(2));
        celdaCD.removeClass('aprobado desaprobado').addClass(promedioFinal >= 6 ? 'aprobado' : 'desaprobado');
        
        fila.find(`input[name="notas[${alumnoId}][promedio_final]"]`).remove();
        fila.find(`input[name="notas[${alumnoId}][calificacion_definitiva]"]`).remove();
        fila.append(`<input type="hidden" name="notas[${alumnoId}][promedio_final]" value="${promedioFinal}">`);
        fila.append(`<input type="hidden" name="notas[${alumnoId}][calificacion_definitiva]" value="${promedioFinal}">`);
    } else {
        celdaPF.text('-');
        celdaPF.removeClass('aprobado desaprobado');
        celdaCD.text('-');
        celdaCD.removeClass('aprobado desaprobado');
    }
}

$(document).ready(function() {
    $('.tabla-planilla tbody tr').each(function() {
        let fila = $(this);
        
        // Evento en tiempo de escritura (no fuerza formateo brusco de caracteres)
        fila.find('.nota1, .nota2, .nota3, .nota4, .nota5, .recuperatorio').on('input', function() {
            validarNota($(this), false);
            recalcularFila(fila);
        });
        
        // Evento al abandonar la celda (normaliza y limpia el formato visual)
        fila.find('.nota1, .nota2, .nota3, .nota4, .nota5, .recuperatorio').on('blur', function() {
            validarNota($(this), true);
            recalcularFila(fila);
        });
        
        // Evaluar datos iniciales provenientes de la base de datos
        fila.find('.nota1, .nota2, .nota3, .nota4, .nota5, .recuperatorio').each(function() {
            validarNota($(this), false);
        });
        
        recalcularFila(fila);
    });
});
</script>