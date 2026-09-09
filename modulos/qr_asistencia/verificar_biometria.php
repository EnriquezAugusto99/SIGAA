<?php
session_start();

if(isset($_SESSION["dni"]) && (time() - $_SESSION["ultima_actividad"] > 18000)){
    echo '<script>alert("Se venció el tiempo de la sesion"); window.location="../../index.php";</script>';
    exit();
}

if(!isset($_SESSION["dni"])){
    echo '<script>alert("Se venció el tiempo de la sesion"); window.location="../../index.php";</script>';
    exit();
}

// Solo Admin, Preceptor o Equipo de Orientacion pueden escanear
$es_admin = ($_SESSION['rol'] == 'Admin');
$es_preceptor = ($_SESSION['rol'] == 'Preceptor');
$es_equipo = ($_SESSION['rol'] == 'Equipo de Orientacion');

if((!$_SESSION['rol'] == 'Admin' || !$_SESSION['rol'] == 'Preceptor' || !$_SESSION['rol'] == 'Equipo de Orientacion') || (!$_SESSION['dni'] == 28084714 || !$_SESSION['dni'] == 28065416 || !$_SESSION['dni'] == 29801405)){
    echo '<script>alert("No tienes permiso para acceder a esta seccion"); window.location="../../index.php";</script>';
    exit();
}

include '../../recursos/conexion.php';

$mensaje = '';
$alumno_info = null;
$error = '';

// ============================================
// CONFIGURACIÓN DE HORARIOS (desde archivo JSON)
// ============================================
$config_file = __DIR__ . '/config_horarios.json';
$config_horarios = [];

if(file_exists($config_file)){
    $config_json = file_get_contents($config_file);
    $config_horarios = json_decode($config_json, true);
}

if(empty($config_horarios)){
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

// Función para convertir hora HH:MM a minutos
function horaAMinutos($hora) {
    $partes = explode(':', $hora);
    return intval($partes[0]) * 60 + intval($partes[1]);
}

// Función para convertir minutos a formato HH:MM
function minutosAHora($minutos) {
    $h = floor($minutos / 60);
    $m = $minutos % 60;
    return str_pad($h, 2, '0', STR_PAD_LEFT) . ':' . str_pad($m, 2, '0', STR_PAD_LEFT);
}

// ============================================
// GUARDAR CONFIGURACIÓN (POST)
// ============================================
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['guardar_config'])){
    function validarHora($hora) {
        return preg_match('/^([0-1][0-9]|2[0-3]):[0-5][0-9]$/', $hora);
    }
    
    $nueva_config = [
        'mañana' => [
            'normal_inicio' => trim($_POST['manana_normal_ini']),
            'normal_fin' => trim($_POST['manana_normal_fin']),
            'tardanza_inicio' => trim($_POST['manana_tardanza_ini']),
            'tardanza_fin' => trim($_POST['manana_tardanza_fin']),
            'falta_inicio' => trim($_POST['manana_falta_ini'])
        ],
        'tarde' => [
            'normal_inicio' => trim($_POST['tarde_normal_ini']),
            'normal_fin' => trim($_POST['tarde_normal_fin']),
            'tardanza_inicio' => trim($_POST['tarde_tardanza_ini']),
            'tardanza_fin' => trim($_POST['tarde_tardanza_fin']),
            'falta_inicio' => trim($_POST['tarde_falta_ini'])
        ]
    ];
    
    $errores = [];
    foreach($nueva_config as $turno => $valores){
        foreach($valores as $key => $valor){
            if(!validarHora($valor)){
                $errores[] = "El valor para $turno - $key ('$valor') no es una hora válida (formato HH:MM)";
            }
        }
    }
    
    if(empty($errores)){
        if(file_put_contents($config_file, json_encode($nueva_config, JSON_PRETTY_PRINT))){
            $config_horarios = $nueva_config;
            $mensaje = "Configuración guardada correctamente";
        } else {
            $error = "Error al guardar la configuración";
        }
    } else {
        $error = implode("<br>", $errores);
    }
}

// ============================================
// FUNCIÓN: Determinar tipo de asistencia según hora (CRITERIO UNIVERSAL)
// ============================================
function determinarTipoAsistencia($hora) {
    global $config_horarios;
    
    $partes = explode(':', $hora);
    $minutos_actual = intval($partes[0]) * 60 + intval($partes[1]);
    
    $limite_mediodia = 12 * 60; // 720 minutos
    
    if($minutos_actual < $limite_mediodia) {
        // TURNO MAÑANA
        $conf = $config_horarios['mañana'];
        $normal_ini = horaAMinutos($conf['normal_inicio']);
        $normal_fin = horaAMinutos($conf['normal_fin']);
        $tardanza_ini = horaAMinutos($conf['tardanza_inicio']);
        $tardanza_fin = horaAMinutos($conf['tardanza_fin']);
        $falta_ini = horaAMinutos($conf['falta_inicio']);
        
        if($minutos_actual >= $normal_ini && $minutos_actual < $normal_fin) {
            return ['tipo' => 'presente', 'turno' => 'M', 'mensaje' => 'Asistencia normal (mañana)'];
        } elseif($minutos_actual >= $tardanza_ini && $minutos_actual < $tardanza_fin) {
            return ['tipo' => 'tardanza', 'turno' => 'M', 'mensaje' => 'Tardanza (mañana)'];
        } elseif($minutos_actual >= $falta_ini) {
            return ['tipo' => 'falta', 'turno' => 'M', 'mensaje' => 'Falta (mañana)'];
        } else {
            return ['tipo' => 'temprano', 'turno' => 'M', 'mensaje' => 'Demasiado temprano (mañana)'];
        }
    } else {
        // TURNO TARDE
        $conf = $config_horarios['tarde'];
        $normal_ini = horaAMinutos($conf['normal_inicio']);
        $normal_fin = horaAMinutos($conf['normal_fin']);
        $tardanza_ini = horaAMinutos($conf['tardanza_inicio']);
        $tardanza_fin = horaAMinutos($conf['tardanza_fin']);
        $falta_ini = horaAMinutos($conf['falta_inicio']);
        
        if($minutos_actual >= $normal_ini && $minutos_actual < $normal_fin) {
            return ['tipo' => 'presente', 'turno' => 'T', 'mensaje' => 'Asistencia normal (tarde)'];
        } elseif($minutos_actual >= $tardanza_ini && $minutos_actual < $tardanza_fin) {
            return ['tipo' => 'tardanza', 'turno' => 'T', 'mensaje' => 'Tardanza (tarde)'];
        } elseif($minutos_actual >= $falta_ini) {
            return ['tipo' => 'falta', 'turno' => 'T', 'mensaje' => 'Falta (tarde)'];
        } else {
            return ['tipo' => 'temprano', 'turno' => 'T', 'mensaje' => 'Demasiado temprano (tarde)'];
        }
    }
}

// ============================================
// PROCESAR ESCANEO MANUAL
// ============================================
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['dni_alumno']) && !isset($_POST['guardar_config'])){
    $dni = trim($_POST['dni_alumno']);
    
    if(empty($dni)){
        $error = "Ingrese un DNI válido";
    } else {
        $query_alumno = "SELECT u.Nombre, u.Apellido, u.id_curso, c.curso, c.division, c.turno
                         FROM usuario u
                         INNER JOIN curso c ON u.id_curso = c.ID_curso
                         WHERE u.DNI_U = '$dni' AND u.ID_rol = 3 AND u.ID_Estado = 1";
        $res_alumno = mysqli_query($con, $query_alumno);
        
        if(mysqli_num_rows($res_alumno) > 0){
            $alumno_info = mysqli_fetch_assoc($res_alumno);
            
            $hora_actual = date('H:i:s');
            $fecha_actual = date('Y-m-d');
            $trimestre_actual = determinarTrimestre();
            
            // EVALUAR CON CRITERIO UNIVERSAL (por hora, no por turno del alumno)
            $asistencia = determinarTipoAsistencia($hora_actual);
            $turno_detectado = $asistencia['turno']; // 'M' o 'T'
            
            // CASO 1: Asistencia normal (a tiempo)
            if($asistencia['tipo'] == 'presente') {
                $mensaje = "Asistencia normal registrada para " . $alumno_info['Apellido'] . ', ' . $alumno_info['Nombre'] . ' a las ' . $hora_actual;
                $mensaje .= "<br><small>" . $asistencia['mensaje'] . "</small>";
            } 
            // CASO 2: Tardanza
            elseif($asistencia['tipo'] == 'tardanza') {
                // Verificar si ya registró en este turno (mañana o tarde)
                $query_check = "SELECT id_inasistencia 
                                FROM inasistencias 
                                WHERE id_alumno = '$dni' 
                                AND fecha = '$fecha_actual' 
                                AND trimestre = '$trimestre_actual'
                                AND turno_detectado = '$turno_detectado'";
                $res_check = mysqli_query($con, $query_check);
                
                if(mysqli_num_rows($res_check) == 0){
                    $query_insert = "INSERT INTO inasistencias (id_alumno, fecha, hora, justificada, tipo_falta, trimestre, id_curso, turno_detectado) 
                                     VALUES ('$dni', '$fecha_actual', '$hora_actual', 'no', 'tardanza', '$trimestre_actual', '{$alumno_info['id_curso']}', '$turno_detectado')";
                    
                    if(mysqli_query($con, $query_insert)){
                        $mensaje = "Tardanza registrada para " . $alumno_info['Apellido'] . ', ' . $alumno_info['Nombre'] . ' a las ' . $hora_actual;
                        $mensaje .= "<br><small>" . $asistencia['mensaje'] . "</small>";
                    } else {
                        $error = "Error al registrar: " . mysqli_error($con);
                    }
                } else {
                    $turno_texto = $turno_detectado == 'M' ? 'mañana' : 'tarde';
                    $mensaje = "Este alumno ya registró tardanza en el turno " . $turno_texto . " hoy";
                }
            } 
            // CASO 3: Falta
            elseif($asistencia['tipo'] == 'falta') {
                // Verificar si ya registró en este turno (mañana o tarde)
                $query_check = "SELECT id_inasistencia 
                                FROM inasistencias 
                                WHERE id_alumno = '$dni' 
                                AND fecha = '$fecha_actual' 
                                AND trimestre = '$trimestre_actual'
                                AND turno_detectado = '$turno_detectado'";
                $res_check = mysqli_query($con, $query_check);
                
                if(mysqli_num_rows($res_check) == 0){
                    $query_insert = "INSERT INTO inasistencias (id_alumno, fecha, hora, justificada, tipo_falta, trimestre, id_curso, turno_detectado) 
                                     VALUES ('$dni', '$fecha_actual', '$hora_actual', 'no', 'falta', '$trimestre_actual', '{$alumno_info['id_curso']}', '$turno_detectado')";
                    
                    if(mysqli_query($con, $query_insert)){
                        $mensaje = "Falta registrada para " . $alumno_info['Apellido'] . ', ' . $alumno_info['Nombre'] . ' a las ' . $hora_actual;
                        $mensaje .= "<br><small>" . $asistencia['mensaje'] . "</small>";
                    } else {
                        $error = "Error al registrar: " . mysqli_error($con);
                    }
                } else {
                    $turno_texto = $turno_detectado == 'M' ? 'mañana' : 'tarde';
                    $mensaje = "Este alumno ya registró inasistencia en el turno " . $turno_texto . " hoy";
                }
            } 
            // CASO 4: Temprano
            else {
                $mensaje = "Todavía no es hora de entrada. Llegaste muy temprano (" . $hora_actual . ")";
            }
            
        } else {
            $error = "Alumno no encontrado o inactivo";
        }
    }
}

// ============================================
// FUNCIÓN: Determinar trimestre actual
// ============================================
function determinarTrimestre() {
    $mes = date('n');
    if($mes >= 3 && $mes <= 6) return 1;
    if($mes >= 7 && $mes <= 9) return 2;
    if($mes >= 10 && $mes <= 12) return 3;
    return 1;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Escáner QR - Asistencia</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Montserrat', sans-serif; background: #f5f5f5; padding: 20px; }
        .container { max-width: 600px; margin: 0 auto; }
        .card { background: white; border-radius: 16px; padding: 30px; box-shadow: 0 4px 20px rgba(0,0,0,0.1); }
        .header { background: linear-gradient(135deg, #3F070B 0%, #710A14 100%); border-radius: 12px; padding: 20px; margin-bottom: 25px; color: white; }
        .header h1 { font-size: 22px; }
        .header p { font-size: 13px; opacity: 0.8; }
        
        .form-group { margin-bottom: 20px; position: relative; }
        .form-group label { font-weight: 600; display: block; margin-bottom: 8px; color: #333; }
        .form-group input { width: 100%; padding: 12px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 16px; }
        .form-group input:focus { outline: none; border-color: #710A14; }
        
        .btn-primary { background: #710A14; color: white; border: none; padding: 12px 24px; border-radius: 8px; font-weight: 600; cursor: pointer; width: 100%; font-size: 16px; transition: all 0.3s ease; }
        .btn-primary:hover { background: #3F070B; }
        .btn-primary:disabled { background: #999; cursor: not-allowed; }
        
        .btn-scanner {
            background: #2196F3;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            width: 100%;
            font-size: 16px;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        .btn-scanner:hover { background: #1976D2; }
        .btn-scanner:disabled { background: #999; cursor: not-allowed; }
        .btn-scanner.activo { background: #f44336; }
        .btn-scanner.activo:hover { background: #d32f2f; }
        
        .mensaje { padding: 15px; border-radius: 8px; margin: 20px 0; }
        .mensaje.exito { background: #d4edda; color: #155724; border-left: 4px solid #28a745; }
        .mensaje.error { background: #f8d7da; color: #721c24; border-left: 4px solid #dc3545; }
        .mensaje.warning { background: #fff3cd; color: #856404; border-left: 4px solid #ffc107; }
        .mensaje.info { background: #e8f0fe; color: #3F070B; border-left: 4px solid #710A14; }
        
        .info-alumno { background: #f0f0f0; padding: 15px; border-radius: 10px; margin: 15px 0; }
        .info-alumno strong { color: #710A14; }
        
        .btn-volver { display: inline-block; margin-top: 20px; padding: 10px 24px; background: #666; color: white; text-decoration: none; border-radius: 8px; font-weight: 600; }
        .btn-volver:hover { background: #555; }
        
        .video-container {
            position: relative;
            width: 100%;
            max-width: 400px;
            margin: 0 auto 20px;
            border-radius: 12px;
            overflow: hidden;
            background: #000;
            display: none;
        }
        .video-container video { width: 100%; height: auto; display: block; }
        .video-container .scanner-overlay {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 200px;
            height: 200px;
            border: 3px solid rgba(255,255,255,0.8);
            border-radius: 12px;
            box-shadow: 0 0 0 4000px rgba(0,0,0,0.3);
            pointer-events: none;
        }
        .video-container .scanner-line {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 180px;
            height: 2px;
            background: #4CAF50;
            animation: scan 2s ease-in-out infinite;
            pointer-events: none;
        }
        @keyframes scan {
            0% { transform: translate(-50%, -80px); }
            50% { transform: translate(-50%, 80px); }
            100% { transform: translate(-50%, -80px); }
        }
        
        .info-horario {
            background: #fff8e1;
            padding: 15px;
            border-radius: 8px;
            margin: 15px 0;
            border-left: 4px solid #ffc107;
        }
        .info-horario table { width: 100%; font-size: 13px; }
        .info-horario th, .info-horario td { padding: 4px 8px; text-align: center; }
        .info-horario .normal { color: #2e7d32; font-weight: bold; }
        .info-horario .tardanza { color: #ff9800; font-weight: bold; }
        .info-horario .falta { color: #d32f2f; font-weight: bold; }
        
        .badge-escaneo {
            display: inline-block;
            padding: 2px 10px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
        }
        .badge-presente { background: #d4edda; color: #155724; }
        .badge-tardanza { background: #fff3cd; color: #856404; }
        .badge-falta { background: #f8d7da; color: #721c24; }
        
        .suggestions-box {
            position: absolute;
            background: white;
            border: 1px solid #ddd;
            border-radius: 8px;
            max-height: 250px;
            overflow-y: auto;
            width: 100%;
            z-index: 1000;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            display: none;
        }
        .suggestions-box .suggestion-item {
            padding: 10px 15px;
            cursor: pointer;
            border-bottom: 1px solid #f0f0f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: background 0.15s;
        }
        .suggestions-box .suggestion-item:hover { background: #f5f0f0; }
        .suggestions-box .suggestion-item .sug-dni { font-weight: bold; color: #710A14; font-size: 14px; }
        .suggestions-box .suggestion-item .sug-nombre { font-size: 13px; color: #333; }
        .suggestions-box .suggestion-item .sug-curso { font-size: 11px; color: #666; }
        .suggestions-box .suggestion-item .sug-info { display: flex; flex-direction: column; align-items: flex-start; flex: 1; margin-left: 10px; }
        
        .btn-export {
            background: #4CAF50;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            font-size: 14px;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            flex: 1;
            justify-content: center;
        }
        .btn-export:hover { background: #45a049; transform: translateY(-2px); }
        .btn-config {
            background: #FF9800;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            font-size: 14px;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            flex: 1;
            justify-content: center;
        }
        .btn-config:hover { background: #F57C00; transform: translateY(-2px); }
        
        .acciones-rapidas {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            justify-content: center;
            margin: 15px 0;
        }
        
        .modal-config {
            display: none;
            position: fixed;
            z-index: 2000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            justify-content: center;
            align-items: center;
        }
        .modal-config-content {
            background: white;
            border-radius: 16px;
            padding: 30px;
            max-width: 550px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
            animation: modalFadeIn 0.3s;
        }
        @keyframes modalFadeIn {
            from { opacity: 0; transform: translateY(-30px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .modal-config-content h2 { color: #710A14; margin-bottom: 20px; text-align: center; }
        .config-row {
            display: flex;
            gap: 8px;
            margin-bottom: 8px;
            align-items: center;
            flex-wrap: wrap;
        }
        .config-row label { font-weight: 600; font-size: 13px; min-width: 90px; color: #333; }
        .config-row input[type="time"] {
            padding: 6px 8px;
            border: 1px solid #ddd;
            border-radius: 6px;
            width: 100px;
            font-size: 14px;
            text-align: center;
        }
        .config-row .hora-label { font-size: 12px; color: #666; }
        .config-section { border-top: 1px solid #eee; padding-top: 15px; margin-top: 15px; }
        .config-section h3 { color: #710A14; font-size: 16px; margin-bottom: 10px; }
        
        .position-relative { position: relative; }
        
        @media (max-width: 480px) {
            .card { padding: 20px; }
            .header h1 { font-size: 18px; }
            .video-container .scanner-overlay { width: 150px; height: 150px; }
            .video-container .scanner-line { width: 130px; }
            .modal-config-content { padding: 20px; }
            .config-row input[type="time"] { width: 80px; font-size: 12px; }
        }
    </style>
</head>
<body>
<div class="container">
    <div class="card">
        <div class="header">
            <h1>Escáner QR - Asistencia</h1>
            <p>Escanea el QR del alumno para registrar su asistencia</p>
        </div>

        <!-- Acciones rápidas -->
        <div class="acciones-rapidas">
            <a href="exportar_asistencias.php" class="btn-export">
                Exportar Excel Hoy
            </a>
            <button class="btn-config" onclick="abrirConfiguracion()">
                Configurar Horarios
            </button>
        </div>

        <!-- Información de horarios -->
        <div class="info-horario" id="infoHorarios">
            <strong>Horarios de Asistencia</strong>
            <div style="background: #e8f0fe; padding: 4px 8px; border-radius: 4px; margin: 5px 0; font-size: 11px; text-align: center;">
                Configura los horarios desde el botón de arriba
            </div>
            <table>
                <tr>
                    <th>Turno</th>
                    <th>Normal</th>
                    <th>Tardanza</th>
                    <th>Falta</th>
                </tr>
                <tr>
                    <td><strong>Mañana</strong></td>
                    <td class="normal"><?= $config_horarios['mañana']['normal_inicio'] ?> - <?= $config_horarios['mañana']['normal_fin'] ?></td>
                    <td class="tardanza"><?= $config_horarios['mañana']['tardanza_inicio'] ?> - <?= $config_horarios['mañana']['tardanza_fin'] ?></td>
                    <td class="falta"><?= $config_horarios['mañana']['falta_inicio'] ?>+</td>
                </tr>
                <tr>
                    <td><strong>Tarde</strong></td>
                    <td class="normal"><?= $config_horarios['tarde']['normal_inicio'] ?> - <?= $config_horarios['tarde']['normal_fin'] ?></td>
                    <td class="tardanza"><?= $config_horarios['tarde']['tardanza_inicio'] ?> - <?= $config_horarios['tarde']['tardanza_fin'] ?></td>
                    <td class="falta"><?= $config_horarios['tarde']['falta_inicio'] ?>+</td>
                </tr>
            </table>
            <small style="display: block; margin-top: 8px; color: #666;">
                La hora se toma del servidor
            </small>
        </div>

        <?php if($error): ?>
            <div class="mensaje error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if($mensaje): ?>
            <div class="mensaje <?= strpos($mensaje, 'Asistencia normal') !== false ? 'exito' : (strpos($mensaje, 'Tardanza') !== false ? 'warning' : (strpos($mensaje, 'Falta') !== false ? 'error' : 'info')) ?>">
                <?= $mensaje ?>
            </div>
        <?php endif; ?>

        <?php if($alumno_info): ?>
            <div class="info-alumno">
                <strong><?= htmlspecialchars($alumno_info['Apellido'] . ', ' . $alumno_info['Nombre']) ?></strong><br>
                Curso: <?= $alumno_info['curso'] . '° "' . $alumno_info['division'] . '"' ?> | 
                Turno: <?= $alumno_info['turno'] == 'M' ? 'Mañana' : 'Tarde' ?>
                <?php 
                $hora_actual = date('H:i:s');
                $asistencia = determinarTipoAsistencia($hora_actual);
                $badge_class = '';
                $badge_text = '';
                if($asistencia['tipo'] == 'presente') { $badge_class = 'badge-presente'; $badge_text = 'A tiempo'; }
                elseif($asistencia['tipo'] == 'tardanza') { $badge_class = 'badge-tardanza'; $badge_text = 'Tardanza'; }
                elseif($asistencia['tipo'] == 'falta') { $badge_class = 'badge-falta'; $badge_text = 'Falta'; }
                if($badge_class): ?>
                    <span class="badge-escaneo <?= $badge_class ?>" style="margin-top: 5px; display: inline-block;">
                        <?= $badge_text ?> - <?= $hora_actual ?>
                    </span>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <!-- Botón para abrir cámara y escanear QR -->
        <button id="btnScanner" class="btn-scanner" onclick="iniciarScanner()">
            Abrir Cámara para Escanear QR
        </button>

        <!-- Contenedor del video -->
        <div id="videoContainer" class="video-container">
            <video id="video" playsinline></video>
            <div class="scanner-overlay"></div>
            <div class="scanner-line"></div>
        </div>

        <div style="text-align: center; margin: 15px 0; display: none;" id="scanStatus">
            Escaneando QR...
        </div>

        <hr style="margin: 25px 0;">

        <h3 style="text-align: center; color: #666; font-size: 14px; margin-bottom: 15px;">
            O ingresa el DNI manualmente
        </h3>

        <form method="POST" action="" id="formManual">
            <div class="form-group position-relative">
                <label>DNI del Alumno</label>
                <input type="text" name="dni_alumno" id="dniInput" placeholder="Ingrese DNI del alumno" autocomplete="off" required>
                <div id="suggestionsBox" class="suggestions-box"></div>
            </div>
            <button type="submit" class="btn-primary">
                Registrar Asistencia
            </button>
        </form>

        <a href="../../recursos/panel.php" class="btn-volver">Volver al Panel</a>
    </div>
</div>

<!-- Modal de Configuración -->
<div id="modalConfig" class="modal-config">
    <div class="modal-config-content">
        <h2>Configurar Horarios</h2>
        <p style="text-align: center; color: #666; font-size: 13px; margin-bottom: 15px;">
            Ingresa las horas en formato <strong>HH:MM</strong> (ej: 07:30, 13:15)
        </p>
        
        <form method="POST" action="" id="formConfig">
            <input type="hidden" name="guardar_config" value="1">
            
            <div class="config-section">
                <h3>Turno Mañana</h3>
                <div class="config-row">
                    <label>Normal:</label>
                    <input type="time" name="manana_normal_ini" value="<?= $config_horarios['mañana']['normal_inicio'] ?>" step="60">
                    <span class="hora-label">a</span>
                    <input type="time" name="manana_normal_fin" value="<?= $config_horarios['mañana']['normal_fin'] ?>" step="60">
                </div>
                <div class="config-row">
                    <label>Tardanza:</label>
                    <input type="time" name="manana_tardanza_ini" value="<?= $config_horarios['mañana']['tardanza_inicio'] ?>" step="60">
                    <span class="hora-label">a</span>
                    <input type="time" name="manana_tardanza_fin" value="<?= $config_horarios['mañana']['tardanza_fin'] ?>" step="60">
                </div>
                <div class="config-row">
                    <label>Falta desde:</label>
                    <input type="time" name="manana_falta_ini" value="<?= $config_horarios['mañana']['falta_inicio'] ?>" step="60">
                </div>
            </div>
            
            <div class="config-section">
                <h3>Turno Tarde</h3>
                <div class="config-row">
                    <label>Normal:</label>
                    <input type="time" name="tarde_normal_ini" value="<?= $config_horarios['tarde']['normal_inicio'] ?>" step="60">
                    <span class="hora-label">a</span>
                    <input type="time" name="tarde_normal_fin" value="<?= $config_horarios['tarde']['normal_fin'] ?>" step="60">
                </div>
                <div class="config-row">
                    <label>Tardanza:</label>
                    <input type="time" name="tarde_tardanza_ini" value="<?= $config_horarios['tarde']['tardanza_inicio'] ?>" step="60">
                    <span class="hora-label">a</span>
                    <input type="time" name="tarde_tardanza_fin" value="<?= $config_horarios['tarde']['tardanza_fin'] ?>" step="60">
                </div>
                <div class="config-row">
                    <label>Falta desde:</label>
                    <input type="time" name="tarde_falta_ini" value="<?= $config_horarios['tarde']['falta_inicio'] ?>" step="60">
                </div>
            </div>
            
            <!-- Vista previa de horarios -->
            <div class="config-section" style="background: #f9f9f9; padding: 12px; border-radius: 8px;">
                <h4 style="font-size: 13px; color: #333;">Vista previa:</h4>
                <table style="width: 100%; font-size: 13px; margin-top: 5px;">
                    <tr>
                        <th>Turno</th>
                        <th>Normal</th>
                        <th>Tardanza</th>
                        <th>Falta</th>
                    </tr>
                    <tr>
                        <td><strong>Mañana</strong></td>
                        <td class="normal" id="vista_manana_normal"><?= $config_horarios['mañana']['normal_inicio'] ?> - <?= $config_horarios['mañana']['normal_fin'] ?></td>
                        <td class="tardanza" id="vista_manana_tardanza"><?= $config_horarios['mañana']['tardanza_inicio'] ?> - <?= $config_horarios['mañana']['tardanza_fin'] ?></td>
                        <td class="falta" id="vista_manana_falta"><?= $config_horarios['mañana']['falta_inicio'] ?>+</td>
                    </tr>
                    <tr>
                        <td><strong>Tarde</strong></td>
                        <td class="normal" id="vista_tarde_normal"><?= $config_horarios['tarde']['normal_inicio'] ?> - <?= $config_horarios['tarde']['normal_fin'] ?></td>
                        <td class="tardanza" id="vista_tarde_tardanza"><?= $config_horarios['tarde']['tardanza_inicio'] ?> - <?= $config_horarios['tarde']['tardanza_fin'] ?></td>
                        <td class="falta" id="vista_tarde_falta"><?= $config_horarios['tarde']['falta_inicio'] ?>+</td>
                    </tr>
                </table>
            </div>
            
            <div style="display: flex; gap: 10px; margin-top: 20px; flex-wrap: wrap;">
                <button type="submit" class="btn-primary" style="flex: 1;">
                    Guardar Configuración
                </button>
                <button type="button" class="btn-scanner" onclick="cerrarConfiguracion()" style="flex: 1; background: #666;">
                    Cerrar
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Librerías -->
<script src="https://unpkg.com/@zxing/library@0.18.6/umd/index.js"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<script>
// ============================================
// INICIALIZAR MODALES Y FUNCIONES
// ============================================
function abrirConfiguracion() {
    document.getElementById('modalConfig').style.display = 'flex';
}

function cerrarConfiguracion() {
    document.getElementById('modalConfig').style.display = 'none';
}

document.getElementById('modalConfig').addEventListener('click', function(e) {
    if (e.target === this) {
        cerrarConfiguracion();
    }
});

// ============================================
// VISTA PREVIA DE HORARIOS EN TIEMPO REAL
// ============================================
document.addEventListener('DOMContentLoaded', function() {
    const formConfig = document.getElementById('formConfig');
    if (!formConfig) return;
    
    const campos = formConfig.querySelectorAll('input[type="time"]');
    campos.forEach(campo => {
        campo.addEventListener('change', function() {
            actualizarVistaPrevia();
        });
        campo.addEventListener('input', function() {
            actualizarVistaPrevia();
        });
    });
    
    function actualizarVistaPrevia() {
        const mn_ini = document.querySelector('input[name="manana_normal_ini"]')?.value || '--:--';
        const mn_fin = document.querySelector('input[name="manana_normal_fin"]')?.value || '--:--';
        const mt_ini = document.querySelector('input[name="manana_tardanza_ini"]')?.value || '--:--';
        const mt_fin = document.querySelector('input[name="manana_tardanza_fin"]')?.value || '--:--';
        const mf_ini = document.querySelector('input[name="manana_falta_ini"]')?.value || '--:--';
        
        document.getElementById('vista_manana_normal').textContent = mn_ini + ' - ' + mn_fin;
        document.getElementById('vista_manana_tardanza').textContent = mt_ini + ' - ' + mt_fin;
        document.getElementById('vista_manana_falta').textContent = mf_ini + '+';
        
        const tn_ini = document.querySelector('input[name="tarde_normal_ini"]')?.value || '--:--';
        const tn_fin = document.querySelector('input[name="tarde_normal_fin"]')?.value || '--:--';
        const tt_ini = document.querySelector('input[name="tarde_tardanza_ini"]')?.value || '--:--';
        const tt_fin = document.querySelector('input[name="tarde_tardanza_fin"]')?.value || '--:--';
        const tf_ini = document.querySelector('input[name="tarde_falta_ini"]')?.value || '--:--';
        
        document.getElementById('vista_tarde_normal').textContent = tn_ini + ' - ' + tn_fin;
        document.getElementById('vista_tarde_tardanza').textContent = tt_ini + ' - ' + tt_fin;
        document.getElementById('vista_tarde_falta').textContent = tf_ini + '+';
    }
});

// ============================================
// AUTOCOMPLETADO DE DNI CON AJAX
// ============================================
let timeoutBusqueda = null;
const dniInput = document.getElementById('dniInput');
const suggestionsBox = document.getElementById('suggestionsBox');

dniInput.addEventListener('input', function() {
    const termino = this.value.trim();
    
    if (termino.length === 0) {
        suggestionsBox.style.display = 'none';
        return;
    }
    
    clearTimeout(timeoutBusqueda);
    timeoutBusqueda = setTimeout(function() {
        $.ajax({
            url: 'ajax_buscar_alumnos.php',
            type: 'POST',
            data: { termino: termino },
            dataType: 'json',
            success: function(response) {
                if (response.success && response.alumnos.length > 0) {
                    let html = '';
                    const alumnos = response.alumnos.slice(0, 7);
                    alumnos.forEach(function(alumno) {
                        html += `
                            <div class="suggestion-item" onclick="seleccionarAlumno('${alumno.DNI_U}')">
                                <span class="sug-dni">${alumno.DNI_U}</span>
                                <div class="sug-info">
                                    <span class="sug-nombre">${alumno.Apellido}, ${alumno.Nombre}</span>
                                    <span class="sug-curso">${alumno.curso}° "${alumno.division}" - ${alumno.turno == 'M' ? 'Mañana' : 'Tarde'}</span>
                                </div>
                            </div>
                        `;
                    });
                    suggestionsBox.innerHTML = html;
                    suggestionsBox.style.display = 'block';
                } else {
                    suggestionsBox.style.display = 'none';
                }
            },
            error: function() {
                suggestionsBox.style.display = 'none';
            }
        });
    }, 300);
});

function seleccionarAlumno(dni) {
    dniInput.value = dni;
    suggestionsBox.style.display = 'none';
}

document.addEventListener('click', function(e) {
    if (!e.target.closest('.position-relative')) {
        suggestionsBox.style.display = 'none';
    }
});

// ============================================
// ESCÁNER QR CON ZXing
// ============================================
let codeReader = null;
let videoElement = null;
let btnScanner = null;
let videoContainer = null;
let scanActive = false;
let selectedDeviceId = null;

async function iniciarScanner() {
    btnScanner = document.getElementById('btnScanner');
    videoContainer = document.getElementById('videoContainer');
    videoElement = document.getElementById('video');
    const scanStatus = document.getElementById('scanStatus');

    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
        alert('Tu navegador no soporta acceso a la cámara. Usa Chrome, Edge o Safari actualizado.');
        return;
    }

    if (scanActive) {
        detenerScanner();
        return;
    }

    try {
        btnScanner.disabled = true;
        btnScanner.innerHTML = 'Iniciando cámara...';
        btnScanner.classList.remove('activo');

        if (!codeReader) {
            codeReader = new ZXing.BrowserQRCodeReader();
        }

        const videoInputDevices = await codeReader.listVideoInputDevices();
        
        if (videoInputDevices.length === 0) {
            alert('No se encontraron cámaras disponibles.');
            btnScanner.disabled = false;
            btnScanner.innerHTML = 'Abrir Cámara para Escanear QR';
            return;
        }

        selectedDeviceId = videoInputDevices[0].deviceId;
        for (let device of videoInputDevices) {
            if (device.label && device.label.toLowerCase().includes('back')) {
                selectedDeviceId = device.deviceId;
                break;
            }
        }

        videoContainer.style.display = 'block';
        scanStatus.style.display = 'block';
        scanStatus.innerHTML = 'Iniciando cámara...';

        await codeReader.decodeFromVideoDevice(selectedDeviceId, videoElement, (result, error) => {
            if (result) {
                const qrData = result.getText();
                console.log('QR detectado:', qrData);
                procesarQR(qrData);
                detenerScanner();
            }
            if (error && !(error instanceof ZXing.NotFoundException)) {
                console.warn('Error de escaneo:', error);
            }
        });

        scanActive = true;
        btnScanner.innerHTML = 'Detener Cámara';
        btnScanner.classList.add('activo');
        btnScanner.disabled = false;
        scanStatus.innerHTML = 'Escaneando...';

    } catch (error) {
        console.error('Error al iniciar cámara:', error);
        alert('Error al iniciar la cámara: ' + error.message);
        btnScanner.disabled = false;
        btnScanner.innerHTML = 'Abrir Cámara para Escanear QR';
        btnScanner.classList.remove('activo');
        videoContainer.style.display = 'none';
        scanStatus.style.display = 'none';
        scanActive = false;
    }
}

function detenerScanner() {
    if (codeReader) {
        try {
            codeReader.reset();
        } catch(e) {}
    }
    
    if (videoElement && videoElement.srcObject) {
        const tracks = videoElement.srcObject.getTracks();
        tracks.forEach(track => track.stop());
        videoElement.srcObject = null;
    }

    scanActive = false;
    videoContainer.style.display = 'none';
    document.getElementById('scanStatus').style.display = 'none';

    if (btnScanner) {
        btnScanner.innerHTML = 'Abrir Cámara para Escanear QR';
        btnScanner.classList.remove('activo');
        btnScanner.disabled = false;
    }
}

function procesarQR(qrData) {
    try {
        let datos = null;
        try {
            datos = JSON.parse(decodeURIComponent(qrData));
        } catch(e) {
            const dniMatch = qrData.match(/\d{7,10}/);
            if (dniMatch) {
                datos = { dni: parseInt(dniMatch[0]) };
            } else {
                alert('QR inválido: No se pudo leer el DNI del alumno.');
                return;
            }
        }

        if (!datos || !datos.dni) {
            alert('QR inválido: No contiene DNI del alumno.');
            return;
        }

        const dni = datos.dni;
        console.log('DNI extraído:', dni);

        if (confirm('Registrar asistencia para el alumno con DNI ' + dni + '?')) {
            registrarAsistencia(dni);
        }

    } catch (error) {
        console.error('Error procesando QR:', error);
        alert('Error al procesar el QR: ' + error.message);
    }
}

function registrarAsistencia(dni) {
    const formData = new FormData();
    formData.append('dni_alumno', dni);
    formData.append('ajax', '1');

    const scanStatus = document.getElementById('scanStatus');
    scanStatus.innerHTML = 'Registrando asistencia...';
    scanStatus.style.display = 'block';

    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(response => response.text())
    .then(html => {
        const tempDiv = document.createElement('div');
        tempDiv.innerHTML = html;
        const mensajeDiv = tempDiv.querySelector('.mensaje');
        
        if (mensajeDiv) {
            alert(mensajeDiv.textContent.trim());
        } else {
            alert('Asistencia registrada correctamente.');
        }
        
        location.reload();
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error al registrar la asistencia: ' + error.message);
        scanStatus.style.display = 'none';
    });
}

// ============================================
// INICIALIZACIÓN
// ============================================
document.addEventListener('DOMContentLoaded', function() {
    const dniInput = document.getElementById('dniInput');
    if (dniInput) {
        dniInput.addEventListener('input', function() {
            this.value = this.value.replace(/\D/g, '');
        });
    }

    window.addEventListener('beforeunload', function() {
        if (scanActive) {
            detenerScanner();
        }
    });
});
</script>
</body>
</html>
<?php mysqli_close($con); ?>