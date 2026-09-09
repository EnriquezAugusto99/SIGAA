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

// Solo Admin puede configurar
if($_SESSION['rol'] != 'Admin'){
    echo '<script>alert("No tiene permisos para acceder a esta seccion"); window.location="../../recursos/panel.php";</script>';
    exit();
}

include '../../recursos/conexion.php';

$mensaje = '';
$error = '';

// ============================================
// PROCESAR FORMULARIO
// ============================================
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['guardar_config'])){
    $trimestre1 = $_POST['trimestre1_apertura'] ?? '';
    $trimestre2 = $_POST['trimestre2_apertura'] ?? '';
    $trimestre3 = $_POST['trimestre3_apertura'] ?? '';
    
    $configs = [
        1 => $trimestre1,
        2 => $trimestre2,
        3 => $trimestre3
    ];
    
    $errores = false;
    foreach($configs as $trim => $fecha){
        if(empty($fecha)){
            $error = "La fecha de apertura para el trimestre $trim es obligatoria";
            $errores = true;
            break;
        }
    }
    
    if(!$errores){
        foreach($configs as $trim => $fecha){
            $query = "INSERT INTO configuracion_libreta (trimestre, fecha_apertura, activo) 
                      VALUES ($trim, '$fecha', 1)
                      ON DUPLICATE KEY UPDATE 
                      fecha_apertura = '$fecha',
                      activo = 1";
            
            if(!mysqli_query($con, $query)){
                $error = "Error al guardar configuración: " . mysqli_error($con);
                break;
            }
        }
        
        if(empty($error)){
            $mensaje = "Configuración guardada correctamente";
        }
    }
}

// ============================================
// OBTENER CONFIGURACIÓN ACTUAL
// ============================================
$configuraciones = [];
for($i = 1; $i <= 3; $i++){
    $query = "SELECT * FROM configuracion_libreta WHERE trimestre = $i";
    $res = mysqli_query($con, $query);
    if(mysqli_num_rows($res) > 0){
        $configuraciones[$i] = mysqli_fetch_assoc($res);
    } else {
        $configuraciones[$i] = [
            'trimestre' => $i,
            'fecha_apertura' => '',
            'activo' => 0
        ];
    }
}

// ============================================
// FUNCIÓN: Verificar si un trimestre está habilitado
// ============================================
function trimestreHabilitado($trimestre) {
    global $con;
    $query = "SELECT fecha_apertura, activo 
              FROM configuracion_libreta 
              WHERE trimestre = $trimestre AND activo = 1";
    $res = mysqli_query($con, $query);
    
    if(mysqli_num_rows($res) == 0) return false;
    
    $config = mysqli_fetch_assoc($res);
    $fecha_actual = date('Y-m-d');
    
    return $fecha_actual >= $config['fecha_apertura'];
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configurar Libreta - EPET N°34</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Montserrat', sans-serif; background: #f5f5f5; padding: 20px; }
        .container { max-width: 800px; margin: 0 auto; }
        .header { background: linear-gradient(135deg, #3F070B 0%, #710A14 100%); border-radius: 12px; padding: 20px 30px; margin-bottom: 25px; color: white; }
        .header h1 { font-size: 24px; }
        .header p { opacity: 0.9; font-size: 13px; }
        
        .card { background: white; border-radius: 12px; padding: 25px; margin-bottom: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); }
        .card h2 { font-size: 16px; color: #3F070B; margin-bottom: 15px; border-left: 4px solid #710A14; padding-left: 12px; }
        
        .config-item { 
            background: #f8f9fa; 
            border-radius: 10px; 
            padding: 20px; 
            margin-bottom: 15px;
            border: 1px solid #e9ecef;
        }
        .config-item .titulo-trimestre {
            font-weight: 600;
            color: #710A14;
            font-size: 16px;
            margin-bottom: 12px;
        }
        .config-item .estado-actual {
            font-size: 13px;
            margin-bottom: 12px;
            padding: 8px 12px;
            border-radius: 6px;
        }
        .config-item .estado-activo { background: #d4edda; color: #155724; }
        .config-item .estado-inactivo { background: #f8d7da; color: #721c24; }
        .config-item .estado-pendiente { background: #fff3cd; color: #856404; }
        
        .form-row { display: flex; gap: 15px; flex-wrap: wrap; align-items: flex-end; }
        .form-group { flex: 1; min-width: 150px; }
        .form-group label { display: block; font-weight: 600; font-size: 13px; color: #333; margin-bottom: 4px; }
        .form-group input[type="date"] { 
            width: 100%; 
            padding: 8px 12px; 
            border: 1px solid #ddd; 
            border-radius: 6px; 
            font-size: 14px; 
            font-family: 'Montserrat', sans-serif;
        }
        .form-group input[type="date"]:focus { outline: none; border-color: #710A14; }
        
        .btn-primary { background: #710A14; color: white; border: none; padding: 10px 30px; border-radius: 6px; cursor: pointer; font-weight: 600; transition: all 0.3s ease; }
        .btn-primary:hover { background: #3F070B; }
        .btn-volver { display: inline-block; padding: 10px 24px; background: #666; color: white; text-decoration: none; border-radius: 8px; font-weight: 600; }
        .btn-volver:hover { background: #555; }
        
        .mensaje-exito { background: #d4edda; color: #155724; padding: 12px; border-radius: 6px; margin-bottom: 15px; border-left: 4px solid #28a745; }
        .mensaje-error { background: #f8d7da; color: #721c24; padding: 12px; border-radius: 6px; margin-bottom: 15px; border-left: 4px solid #dc3545; }
        
        .text-center { text-align: center; margin-top: 20px; }
        .badge-habilitado { background: #2e7d32; color: white; padding: 2px 10px; border-radius: 12px; font-size: 11px; }
        .badge-no-habilitado { background: #d32f2f; color: white; padding: 2px 10px; border-radius: 12px; font-size: 11px; }
        
        @media (max-width: 768px) {
            .form-row { flex-direction: column; }
            .form-group { min-width: 100%; }
        }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1>Configurar Apertura de Libretas</h1>
    </div>

    <?php if($mensaje): ?>
        <div class="mensaje-exito"><?= htmlspecialchars($mensaje) ?></div>
    <?php endif; ?>
    <?php if($error): ?>
        <div class="mensaje-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="card">
        <h2>Fechas de Apertura</h2>
        <p style="font-size: 13px; color: #666; margin-bottom: 20px;">
            Una vez que la fecha actual supere la fecha de apertura configurada, las notas de ese trimestre seran visibles para alumnos y tutores.
        </p>
        
        <form method="POST" action="">
            <!-- Trimestre 1 -->
            <div class="config-item">
                <div class="titulo-trimestre">1er Trimestre</div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Fecha de apertura</label>
                        <input type="date" name="trimestre1_apertura" value="<?= $configuraciones[1]['fecha_apertura'] ?? '' ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Estado actual</label>
                        <div style="padding: 8px 12px; border-radius: 6px; background: <?= trimestreHabilitado(1) ? '#d4edda' : '#fff3cd' ?>; color: <?= trimestreHabilitado(1) ? '#155724' : '#856404' ?>;">
                            <?= trimestreHabilitado(1) ? 'Habilitado' : 'Pendiente de apertura' ?>
                            <?php if(!trimestreHabilitado(1) && !empty($configuraciones[1]['fecha_apertura'])): ?>
                                (desde <?= date('d/m/Y', strtotime($configuraciones[1]['fecha_apertura'])) ?>)
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Trimestre 2 -->
            <div class="config-item">
                <div class="titulo-trimestre">2do Trimestre</div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Fecha de apertura</label>
                        <input type="date" name="trimestre2_apertura" value="<?= $configuraciones[2]['fecha_apertura'] ?? '' ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Estado actual</label>
                        <div style="padding: 8px 12px; border-radius: 6px; background: <?= trimestreHabilitado(2) ? '#d4edda' : '#fff3cd' ?>; color: <?= trimestreHabilitado(2) ? '#155724' : '#856404' ?>;">
                            <?= trimestreHabilitado(2) ? 'Habilitado' : 'Pendiente de apertura' ?>
                            <?php if(!trimestreHabilitado(2) && !empty($configuraciones[2]['fecha_apertura'])): ?>
                                (desde <?= date('d/m/Y', strtotime($configuraciones[2]['fecha_apertura'])) ?>)
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Trimestre 3 -->
            <div class="config-item">
                <div class="titulo-trimestre">3er Trimestre</div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Fecha de apertura</label>
                        <input type="date" name="trimestre3_apertura" value="<?= $configuraciones[3]['fecha_apertura'] ?? '' ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Estado actual</label>
                        <div style="padding: 8px 12px; border-radius: 6px; background: <?= trimestreHabilitado(3) ? '#d4edda' : '#fff3cd' ?>; color: <?= trimestreHabilitado(3) ? '#155724' : '#856404' ?>;">
                            <?= trimestreHabilitado(3) ? 'Habilitado' : 'Pendiente de apertura' ?>
                            <?php if(!trimestreHabilitado(3) && !empty($configuraciones[3]['fecha_apertura'])): ?>
                                (desde <?= date('d/m/Y', strtotime($configuraciones[3]['fecha_apertura'])) ?>)
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            <center>
               <button type="submit" name="guardar_config" class="btn-primary">Guardar Configuración</button>
            </center>
        </form>
    </div>

    <div class="text-center">
        <a href="../../recursos/panel.php" class="btn-volver">Volver al Panel</a>
    </div>
</div>
</body>
</html>
<?php mysqli_close($con); ?>