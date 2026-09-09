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
    echo '<script>alert("No tienes permisos para modificar esta sección"); window.location="../../recursos/panel.php";</script>';
    exit();
}

include '../../recursos/conexion.php';

$fecha = $_GET['fecha'] ?? '';

// Validar formato de fecha
if(!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)){
    echo json_encode(['valido' => false, 'trimestre' => 'Fecha inválida']);
    exit();
}

// Buscar en qué instancia (trimestre o recuperatorio) cae esta fecha
$query = "SELECT id_trimestre, trimestre FROM trimestres WHERE '$fecha' BETWEEN fecha_inicio AND fecha_fin LIMIT 1";
$res = mysqli_query($con, $query);

if($res && mysqli_num_rows($res) > 0){
    $fila = mysqli_fetch_array($res);
    echo json_encode([
        'valido' => true,
        'trimestre' => $fila['trimestre'],
        'id_trimestre' => $fila['id_trimestre']
    ]);
} else {
    echo json_encode(['valido' => false, 'trimestre' => 'No hay instancia en esta fecha']);
}

mysqli_close($con);
?>