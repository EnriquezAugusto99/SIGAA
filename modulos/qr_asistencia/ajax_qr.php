<?php
session_start();
header('Content-Type: application/json');

if(!isset($_SESSION['dni'])){
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit();
}

if($_SESSION['rol'] != 'Estudiante'){
    echo json_encode(['success' => false, 'message' => 'No tiene permisos']);
    exit();
}

include '../../recursos/conexion.php';

$action = isset($_POST['action']) ? $_POST['action'] : '';

// ============================================
// VERIFICAR QR (cuando es escaneado por el preceptor)
// ============================================
if($action == 'verificar_qr'){
    $datos_qr = isset($_POST['datos_qr']) ? json_decode(urldecode($_POST['datos_qr']), true) : null;
    
    if(!$datos_qr){
        echo json_encode(['success' => false, 'message' => 'QR inválido']);
        exit();
    }
    
    $dni = $datos_qr['dni'] ?? 0;
    $fecha = $datos_qr['fecha'] ?? '';
    $hora = $datos_qr['hora'] ?? '';
    $timestamp = $datos_qr['timestamp'] ?? 0;
    
    // Verificar que el QR no sea muy viejo (máx 5 minutos de diferencia)
    $diferencia = time() - ($timestamp / 1000);
    if($diferencia > 300){
        echo json_encode(['success' => false, 'message' => 'QR expirado']);
        exit();
    }
    
    // Verificar que el alumno existe
    $query_alumno = "SELECT u.Nombre, u.Apellido, u.id_curso, c.curso, c.division
                     FROM usuario u
                     INNER JOIN curso c ON u.id_curso = c.ID_curso
                     WHERE u.DNI_U = '$dni' AND u.ID_rol = 3 AND u.ID_Estado = 1";
    $res_alumno = mysqli_query($con, $query_alumno);
    
    if(mysqli_num_rows($res_alumno) == 0){
        echo json_encode(['success' => false, 'message' => 'Alumno no encontrado']);
        exit();
    }
    
    $alumno = mysqli_fetch_assoc($res_alumno);
    
    // Registrar la asistencia (tardanza)
    $hora_actual = date('H:i:s');
    $fecha_actual = date('Y-m-d');
    
    // Verificar si ya registró tardanza hoy
    $query_check = "SELECT id_inasistencia FROM inasistencias 
                    WHERE id_alumno = '$dni' AND fecha = '$fecha_actual' AND trimestre = 1";
    $res_check = mysqli_query($con, $query_check);
    
    if(mysqli_num_rows($res_check) > 0){
        echo json_encode(['success' => false, 'message' => 'Ya registró tardanza hoy']);
        exit();
    }
    
    // Registrar tardanza (como inasistencia injustificada con tipo_falta = 'media')
    $query_insert = "INSERT INTO inasistencias (id_alumno, fecha, justificada, tipo_falta, trimestre, id_curso) 
                     VALUES ('$dni', '$fecha_actual', 0, 'media', 1, '{$alumno['id_curso']}')";
    
    if(mysqli_query($con, $query_insert)){
        echo json_encode([
            'success' => true, 
            'message' => 'Asistencia registrada correctamente',
            'alumno' => $alumno['Apellido'] . ', ' . $alumno['Nombre'],
            'curso' => $alumno['curso'] . '° "' . $alumno['division'] . '"',
            'hora' => $hora_actual
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error al registrar: ' . mysqli_error($con)]);
    }
    exit();
}

echo json_encode(['success' => false, 'message' => 'Acción no válida']);
?>