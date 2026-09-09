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

$materia_id = $_GET['materia_id'] ?? '';
$curso_id = $_GET['curso_id'] ?? '';

// Validar que los IDs sean numéricos
if(!is_numeric($materia_id) || $materia_id <= 0 || !is_numeric($curso_id) || $curso_id <= 0){
    echo json_encode([]);
    exit();
}

// Obtener docentes asignados a esa materia en ese curso específico
$query = "SELECT usuario.DNI_U, usuario.Nombre, usuario.Apellido FROM docentemateriacurso INNER JOIN usuario ON docentemateriacurso.id_docente = usuario.DNI_U WHERE docentemateriacurso.id_materia = '$materia_id' AND docentemateriacurso.id_curso = '$curso_id' AND usuario.ID_Estado = 1 ORDER BY usuario.Apellido, usuario.Nombre";
$res = mysqli_query($con, $query);

$docentes = [];
if($res && mysqli_num_rows($res) > 0){
    while($fila = mysqli_fetch_array($res)){
        $docentes[] = [
            'DNI_U' => $fila['DNI_U'],
            'Nombre' => $fila['Nombre'],
            'Apellido' => $fila['Apellido']
        ];
    }
}

header('Content-Type: application/json');
echo json_encode($docentes);

mysqli_close($con);
?>