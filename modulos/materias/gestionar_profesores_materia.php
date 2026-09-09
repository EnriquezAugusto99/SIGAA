<?php
session_start();

if(!isset($_SESSION["dni"]) || $_SESSION['rol'] != 'Preceptor'){
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit();
}

include '../../recursos/conexion.php';

$action = $_POST['action'] ?? $_GET['action'] ?? '';

if($action == 'obtener_profesores'){
    $id_materia = $_POST['id_materia'] ?? 0;
    $id_curso = $_POST['id_curso'] ?? 0;
    
    // Obtener profesores actuales
    $query_actuales = "SELECT u.DNI_U, u.Nombre, u.Apellido, dmc.id_dmc
                       FROM docentemateriacurso dmc
                       INNER JOIN usuario u ON dmc.id_docente = u.DNI_U
                       WHERE dmc.id_materia = '$id_materia' AND dmc.id_curso = '$id_curso'
                       AND u.ID_rol = 2";
    $res_actuales = mysqli_query($con, $query_actuales);
    $profesores_actuales = [];
    while($f = mysqli_fetch_array($res_actuales)){
        $profesores_actuales[] = $f;
    }
    
    // Obtener todos los profesores disponibles
    $query_disponibles = "SELECT DNI_U, Nombre, Apellido 
                          FROM usuario 
                          WHERE ID_rol = 2 AND ID_Estado = 1
                          ORDER BY Apellido, Nombre";
    $res_disponibles = mysqli_query($con, $query_disponibles);
    $profesores_disponibles = [];
    while($f = mysqli_fetch_array($res_disponibles)){
        $profesores_disponibles[] = $f;
    }
    
    // Obtener información de la materia y curso
    $query_info = "SELECT m.Nom_materia, c.curso, c.division, c.turno 
                   FROM materia m 
                   INNER JOIN curso c ON m.id_curso = c.ID_curso 
                   WHERE m.ID_materia = '$id_materia'";
    $res_info = mysqli_query($con, $query_info);
    $info = mysqli_fetch_array($res_info);
    
    echo json_encode([
        'success' => true,
        'profesores_actuales' => $profesores_actuales,
        'profesores_disponibles' => $profesores_disponibles,
        'info_materia' => $info
    ]);
    exit();
}
elseif($action == 'agregar_profesor'){
    $id_materia = $_POST['id_materia'] ?? 0;
    $id_curso = $_POST['id_curso'] ?? 0;
    $id_docente = $_POST['id_docente'] ?? 0;
    
    // Verificar si ya existe la relación
    $query_check = "SELECT id_dmc FROM docentemateriacurso 
                    WHERE id_materia = '$id_materia' 
                    AND id_curso = '$id_curso' 
                    AND id_docente = '$id_docente'";
    $res_check = mysqli_query($con, $query_check);
    
    if(mysqli_num_rows($res_check) > 0){
        echo json_encode(['success' => false, 'message' => 'El profesor ya está asignado a esta materia en este curso']);
        exit();
    }
    
    $query_insert = "INSERT INTO docentemateriacurso (id_docente, id_materia, id_curso) 
                     VALUES ('$id_docente', '$id_materia', '$id_curso')";
    
    if(mysqli_query($con, $query_insert)){
        echo json_encode(['success' => true, 'message' => 'Profesor agregado correctamente']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error al agregar el profesor: ' . mysqli_error($con)]);
    }
    exit();
}
elseif($action == 'quitar_profesor'){
    $id_dmc = $_POST['id_dmc'] ?? 0;
    
    $query_delete = "DELETE FROM docentemateriacurso WHERE id_dmc = '$id_dmc'";
    
    if(mysqli_query($con, $query_delete)){
        echo json_encode(['success' => true, 'message' => 'Profesor removido correctamente']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error al remover el profesor']);
    }
    exit();
}

echo json_encode(['success' => false, 'message' => 'Acción no válida']);
?>