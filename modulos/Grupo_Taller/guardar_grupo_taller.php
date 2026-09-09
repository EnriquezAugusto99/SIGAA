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

if($_SESSION['rol'] != 'Admin' && $_SESSION['rol'] != 'Preceptor'){
    echo '<script>alert("No tiene permisos para gestionar grupos"); window.location="../../recursos/panel.php";</script>';
    exit();
}

if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    $_SESSION = array();
    session_destroy();
    header("Location: ../../index.php");
    exit();
}

$ID_curso = isset($_POST['ID_curso']) ? (int)$_POST['ID_curso'] : 0;
$anio = isset($_POST['anio']) ? (int)$_POST['anio'] : date('Y');
$grupos = isset($_POST['grupo']) ? $_POST['grupo'] : [];

if($ID_curso == 0){
    echo '<script>alert("ERROR: Curso no válido"); window.location="grupos_taller.php";</script>';
    exit();
}

include '../../recursos/conexion.php';

// Verificar que el curso existe
$check_curso = "SELECT ID_curso FROM curso WHERE ID_curso = '$ID_curso'";
$res_curso = mysqli_query($con, $check_curso);
if(mysqli_num_rows($res_curso) == 0){
    echo '<script>alert("ERROR: Curso no válido"); window.location="grupos_taller.php";</script>';
    exit();
}

$insertados = 0;
$actualizados = 0;

foreach($grupos as $id_alumno => $numero_grupo){
    $id_alumno = (int)$id_alumno;
    $numero_grupo = (int)$numero_grupo;
    
    if($numero_grupo <= 0 || $numero_grupo > 12){
        // Eliminar asignación existente
        $query_delete = "DELETE FROM alumno_grupo_taller WHERE ID_alumno = '$id_alumno' AND ID_curso = '$ID_curso' AND anio = '$anio'";
        mysqli_query($con, $query_delete);
        continue;
    }
    
    // USAR REPLACE EN LUGAR DE INSERT (evita duplicados)
    $query = "REPLACE INTO alumno_grupo_taller (ID_alumno, ID_curso, numero_grupo, anio) 
              VALUES ('$id_alumno', '$ID_curso', '$numero_grupo', '$anio')";
    
    if(mysqli_query($con, $query)){
        $insertados++;
    } else {
        // Si REPLACE no funciona, usar INSERT ON DUPLICATE KEY UPDATE
        $query2 = "INSERT INTO alumno_grupo_taller (ID_alumno, ID_curso, numero_grupo, anio) 
                   VALUES ('$id_alumno', '$ID_curso', '$numero_grupo', '$anio')
                   ON DUPLICATE KEY UPDATE numero_grupo = '$numero_grupo'";
        if(mysqli_query($con, $query2)){
            $insertados++;
        } else {
            echo "<!-- Error: " . mysqli_error($con) . " -->";
        }
    }
}

$_SESSION['mensaje_grupos'] = "✅ Grupos guardados: $insertados registros procesados.";
header("Location: grupos_taller.php?curso_id=$ID_curso");
exit();

mysqli_close($con);
?>