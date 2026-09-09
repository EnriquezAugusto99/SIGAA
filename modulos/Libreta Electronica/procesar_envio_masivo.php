<?php
session_start();
header('Content-Type: application/json');

// Verificar permisos - Admin o Preceptor
$es_admin = ($_SESSION['rol'] == 'Admin');
$es_preceptor = ($_SESSION['rol'] == 'Preceptor');

if(!isset($_SESSION['dni'])){
    echo json_encode(['success' => false, 'error' => 'No autorizado - Sesión no iniciada']);
    exit();
}

if(!$es_admin && !$es_preceptor){
    echo json_encode(['success' => false, 'error' => 'No autorizado - No tiene permisos']);
    exit();
}

include '../../recursos/conexion.php';

require __DIR__ . '/../../recursos/vendor/src/PHPMailer.php';
require __DIR__ . '/../../recursos/vendor/src/SMTP.php';
require __DIR__ . '/../../recursos/vendor/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$action = isset($_POST['action']) ? $_POST['action'] : '';

// ============================================
// ACCIÓN 1: Enviar email (PDF ya generado)
// ============================================
if($action == 'enviar_email' && isset($_POST['pdf_base64']) && isset($_POST['alumno_id']) && isset($_POST['tipo_envio_nombre'])){
    
    $pdf_base64 = $_POST['pdf_base64'];
    $alumno_id = $_POST['alumno_id'];
    $tipo_envio_nombre = $_POST['tipo_envio_nombre'];
    
    $pdf_content = base64_decode($pdf_base64);
    
    if(strlen($pdf_content) < 1000) {
        echo json_encode(['success' => false, 'error' => 'PDF inválido o muy pequeño (' . strlen($pdf_content) . ' bytes)']);
        exit();
    }
    
    // Obtener datos del alumno
    $query_alumno = "SELECT Nombre, Apellido FROM usuario WHERE DNI_U = '$alumno_id'";
    $res_alumno = mysqli_query($con, $query_alumno);
    $alumno = mysqli_fetch_assoc($res_alumno);
    $alumno_nombre = $alumno['Apellido'] . ', ' . $alumno['Nombre'];
    
    // CAMBIO: Ahora seleccionamos también el DNI_U del tutor
    $query_tutores = "SELECT u.DNI_U, u.Nombre, u.Apellido, u.email 
                      FROM alumnoxtutor at
                      INNER JOIN usuario u ON at.id_tutor = u.DNI_U
                      WHERE at.id_alumno = '$alumno_id' AND u.email IS NOT NULL AND u.email != ''";
    $res_tutores = mysqli_query($con, $query_tutores);
    
    $tutores = [];
    while($tutor = mysqli_fetch_assoc($res_tutores)){
        $tutores[] = $tutor;
    }
    
    if(empty($tutores)) {
        echo json_encode(['success' => false, 'error' => 'No hay tutores con email para este alumno']);
        exit();
    }
    
    $enviados = 0;
    $errores = [];
    
    foreach($tutores as $tutor){
        $id_tutor = $tutor['DNI_U'];
        $nombre_tutor = $tutor['Nombre'] . ' ' . $tutor['Apellido'];
        
        //$email_tutor = 'enriquezaugusto150@gmail.com'; 
        $email_tutor = $tutor['email'];
        
        // 1. GENERAR TOKEN ÚNICO Y REGISTRAR EN BDD
        $token = bin2hex(random_bytes(16));
        $tipo_envio_esc = mysqli_real_escape_string($con, $tipo_envio_nombre);
        
        $query_token = "INSERT INTO confirmacion_libreta (token, id_tutor, id_alumno, tipo_envio) 
                        VALUES ('$token', '$id_tutor', '$alumno_id', '$tipo_envio_esc')";
        mysqli_query($con, $query_token);
        
        //LINK DE RECEPCIÓN
        $link_confirmacion = "https://epet34.net.ar/calificaciones/modulos/Libreta%20Electronica/confirmar.php?token=" . $token;
        
        $mail = new PHPMailer(true);
        
        try {
            $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = 'sistema.calificacionesepet34@gmail.com';
            $mail->Password   = 'vpma sdyj taef gdao';
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = 587;
            $mail->CharSet    = 'UTF-8';
            
            $mail->SMTPOptions = [
                'ssl' => [ 'verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true ]
            ];
            
            $mail->setFrom('sistema.calificacionesepet34@gmail.com', 'Sistema Escolar - EPET N°34');
            $mail->addAddress($email_tutor, $nombre_tutor);
            $mail->addBCC('sistema.calificacionesepet34@gmail.com', 'Administración');
            
            $mail->addStringAttachment($pdf_content, 'Libreta_Calificaciones_' . $tipo_envio_nombre . '.pdf', 'base64', 'application/pdf');
            
            $mail->isHTML(true);
            $mail->Subject = '📓 Libreta de Calificaciones - ' . $tipo_envio_nombre . ' - ' . $alumno_nombre;
            
            $cuerpo = '
            <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; border: 1px solid #ddd; padding: 20px; border-radius: 8px;">
                <h2 style="color: #7a0000; text-align: center;">📓 Libreta de Calificaciones</h2>
                <p>Estimado/a <strong>' . htmlspecialchars($nombre_tutor) . '</strong>,</p>
                <p>Adjuntamos a este correo la <strong>libreta de calificaciones digital</strong> correspondiente al alumno/a <strong>' . htmlspecialchars($alumno_nombre) . '</strong> para el periodo: <strong>' . htmlspecialchars($tipo_envio_nombre) . '</strong>.</p>
                
                <div style="background-color: #f9f9f9; border-left: 4px solid #7a0000; padding: 15px; margin: 20px 0;">
                    <p style="margin: 0; font-size: 14px; color: #333;">
                        <strong>Importante:</strong> Para registrar la notificación oficial en el sistema de la institución, por favor haga clic en el siguiente botón:
                    </p>
                    <div style="text-align: center; margin-top: 15px;">
                        <a href="' . $link_confirmacion . '" style="background-color: #7a0000; color: #ffffff; padding: 12px 25px; text-decoration: none; font-weight: bold; border-radius: 5px; display: inline-block;">
                            ✔ Confirmar Recepción de Libreta
                        </a>
                    </div>
                </div>
                <hr style="border: 0; border-top: 1px solid #eee;">
                <p style="font-size: 11px; color: #777; text-align: center;">Este es un mensaje automático generado por el Sistema de Gestión de Calificaciones - EPET N°34.</p>
            </div>';
            
            $mail->Body = $cuerpo;
            $mail->AltBody = "Estimado/a $nombre_tutor,\n\nAdjuntamos la libreta de calificaciones de $alumno_nombre ($tipo_envio_nombre).\n\nPara confirmar la recepción oficial, ingrese al siguiente enlace:\n$link_confirmacion\n\nSistema Escolar - EPET N°34.";
            
            $mail->send();
            $enviados++;
            
        } catch(Exception $e) {
            $errores[] = "Tutor: {$tutor['Nombre']} (email: {$email_tutor}) - Error: " . $mail->ErrorInfo;
        }
    }
    
    echo json_encode([
        'success' => ($enviados > 0),
        'enviados' => $enviados,
        'fallidos' => count($errores),
        'errores' => $errores
    ]);
    exit();
}


// ============================================
// ACCIÓN 2: Obtener alumnos de un curso
// ============================================
if($action == 'obtener_alumnos_curso' && isset($_POST['curso_id'])){
    $curso_id = $_POST['curso_id'];
    $preceptor_dni = $_SESSION["dni"];
    
    // Si es preceptor, verificar que el curso le pertenezca
    if($es_preceptor){
        $query_check = "SELECT 1 FROM preceptorxcurso WHERE id_preceptor = '$preceptor_dni' AND id_curso = '$curso_id'";
        $res_check = mysqli_query($con, $query_check);
        if(mysqli_num_rows($res_check) == 0){
            echo json_encode(['success' => false, 'error' => 'No tiene permisos para este curso']);
            exit();
        }
    }
    
    $query_alumnos = "SELECT DNI_U as id, Nombre, Apellido 
                      FROM usuario 
                      WHERE id_curso = '$curso_id' 
                      AND ID_rol = 3 
                      AND ID_Estado = 1
                      ORDER BY Apellido, Nombre";
    $res_alumnos = mysqli_query($con, $query_alumnos);
    
    if(!$res_alumnos) {
        echo json_encode(['success' => false, 'error' => 'Error en consulta: ' . mysqli_error($con)]);
        exit();
    }
    
    $alumnos = [];
    while($alumno = mysqli_fetch_assoc($res_alumnos)){
        $alumnos[] = $alumno;
    }
    
    if(empty($alumnos)) {
        echo json_encode(['success' => false, 'error' => 'No se encontraron alumnos activos para este curso']);
        exit();
    }
    
    echo json_encode(['success' => true, 'alumnos' => $alumnos]);
    exit();
}

// ============================================
// ACCIÓN 3: Obtener todos los alumnos (solo Admin)
// ============================================
if($action == 'obtener_todos_alumnos'){
    // Solo Admin puede ver todos los alumnos
    if(!$es_admin){
        echo json_encode(['success' => false, 'error' => 'No autorizado']);
        exit();
    }
    
    $query_alumnos = "SELECT DNI_U as id, Nombre, Apellido, id_curso 
                      FROM usuario 
                      WHERE ID_rol = 3 
                      AND ID_Estado = 1
                      ORDER BY Apellido, Nombre";
    $res_alumnos = mysqli_query($con, $query_alumnos);
    
    $alumnos = [];
    while($alumno = mysqli_fetch_assoc($res_alumnos)){
        $alumnos[] = $alumno;
    }
    
    echo json_encode(['success' => true, 'alumnos' => $alumnos]);
    exit();
}

// ============================================
// ACCIÓN 4: Obtener cursos del preceptor
// ============================================
if($action == 'obtener_cursos_preceptor'){
    if(!$es_preceptor){
        echo json_encode(['success' => false, 'error' => 'No autorizado']);
        exit();
    }
    
    $preceptor_dni = $_SESSION["dni"];
    $query_cursos = "SELECT c.ID_curso, c.curso, c.division, c.turno
                     FROM curso c
                     INNER JOIN preceptorxcurso pxc ON c.ID_curso = pxc.id_curso
                     WHERE pxc.id_preceptor = '$preceptor_dni'
                     ORDER BY c.curso, c.division";
    $res_cursos = mysqli_query($con, $query_cursos);
    
    $cursos = [];
    while($curso = mysqli_fetch_assoc($res_cursos)){
        $cursos[] = $curso;
    }
    
    echo json_encode(['success' => true, 'cursos' => $cursos]);
    exit();
}

// ============================================
// ACCIÓN 5: Enviar email INDIVIDUAL (solo a un tutor específico)
// ============================================
if($action == 'enviar_email_individual' && isset($_POST['pdf_base64']) && isset($_POST['alumno_id']) && isset($_POST['tutor_dni']) && isset($_POST['tipo_envio_nombre'])){
    
    $pdf_base64 = $_POST['pdf_base64'];
    $alumno_id = $_POST['alumno_id'];
    $tutor_dni = $_POST['tutor_dni'];
    $tipo_envio_nombre = $_POST['tipo_envio_nombre'];
    
    $pdf_content = base64_decode($pdf_base64);
    
    if(strlen($pdf_content) < 1000) {
        echo json_encode(['success' => false, 'error' => 'PDF inválido o muy pequeño (' . strlen($pdf_content) . ' bytes)']);
        exit();
    }
    
    // Obtener datos del alumno
    $query_alumno = "SELECT Nombre, Apellido FROM usuario WHERE DNI_U = '$alumno_id'";
    $res_alumno = mysqli_query($con, $query_alumno);
    $alumno = mysqli_fetch_assoc($res_alumno);
    $alumno_nombre = $alumno['Apellido'] . ', ' . $alumno['Nombre'];
    
    // Obtener datos del tutor específico
    $query_tutor = "SELECT Nombre, Apellido, email FROM usuario WHERE DNI_U = '$tutor_dni'";
    $res_tutor = mysqli_query($con, $query_tutor);
    $tutor = mysqli_fetch_assoc($res_tutor);
    
    if(!$tutor || empty($tutor['email'])){
        echo json_encode(['success' => false, 'error' => 'El tutor no tiene email registrado']);
        exit();
    }
    
    $nombre_tutor = $tutor['Nombre'] . ' ' . $tutor['Apellido'];
    $email_tutor = $tutor['email'];
    
    // Generar token
    $token = bin2hex(random_bytes(16));
    $tipo_envio_esc = mysqli_real_escape_string($con, $tipo_envio_nombre);
    
    $query_token = "INSERT INTO confirmacion_libreta (token, id_tutor, id_alumno, tipo_envio) 
                    VALUES ('$token', '$tutor_dni', '$alumno_id', '$tipo_envio_esc')";
    mysqli_query($con, $query_token);
    
    $link_confirmacion = "https://epet34.net.ar/calificaciones/modulos/Libreta%20Electronica/confirmar.php?token=" . $token;
    
    $mail = new PHPMailer(true);
    
    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'sistema.calificacionesepet34@gmail.com';
        $mail->Password   = 'vpma sdyj taef gdao';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        $mail->CharSet    = 'UTF-8';
        
        $mail->SMTPOptions = [
            'ssl' => [ 'verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true ]
        ];
        
        $mail->setFrom('sistema.calificacionesepet34@gmail.com', 'Sistema Escolar - EPET N°34');
        $mail->addAddress($email_tutor, $nombre_tutor);
        $mail->addBCC('sistema.calificacionesepet34@gmail.com', 'Administración');
        
        $mail->addStringAttachment($pdf_content, 'Libreta_Calificaciones_' . $tipo_envio_nombre . '.pdf', 'base64', 'application/pdf');
        
        $mail->isHTML(true);
        $mail->Subject = '📓 Libreta de Calificaciones - ' . $tipo_envio_nombre . ' - ' . $alumno_nombre;
        
        $cuerpo = '
        <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; border: 1px solid #ddd; padding: 20px; border-radius: 8px;">
            <h2 style="color: #7a0000; text-align: center;">📓 Libreta de Calificaciones</h2>
            <p>Estimado/a <strong>' . htmlspecialchars($nombre_tutor) . '</strong>,</p>
            <p>Adjuntamos a este correo la <strong>libreta de calificaciones digital</strong> correspondiente al alumno/a <strong>' . htmlspecialchars($alumno_nombre) . '</strong> para el periodo: <strong>' . htmlspecialchars($tipo_envio_nombre) . '</strong>.</p>
            
            <div style="background-color: #f9f9f9; border-left: 4px solid #7a0000; padding: 15px; margin: 20px 0;">
                <p style="margin: 0; font-size: 14px; color: #333;">
                    <strong>Importante:</strong> Para registrar la notificación oficial en el sistema de la institución, por favor haga clic en el siguiente botón:
                </p>
                <div style="text-align: center; margin-top: 15px;">
                    <a href="' . $link_confirmacion . '" style="background-color: #7a0000; color: #ffffff; padding: 12px 25px; text-decoration: none; font-weight: bold; border-radius: 5px; display: inline-block;">
                        ✔ Confirmar Recepción de Libreta
                    </a>
                </div>
            </div>
            <hr style="border: 0; border-top: 1px solid #eee;">
            <p style="font-size: 11px; color: #777; text-align: center;">Este es un mensaje automático generado por el Sistema de Gestión de Calificaciones - EPET N°34.</p>
        </div>';
        
        $mail->Body = $cuerpo;
        $mail->AltBody = "Estimado/a $nombre_tutor,\n\nAdjuntamos la libreta de calificaciones de $alumno_nombre ($tipo_envio_nombre).\n\nPara confirmar la recepción oficial, ingrese al siguiente enlace:\n$link_confirmacion\n\nSistema Escolar - EPET N°34.";
        
        $mail->send();
        
        echo json_encode(['success' => true, 'message' => 'Email enviado correctamente']);
        
    } catch(Exception $e) {
        echo json_encode(['success' => false, 'error' => $mail->ErrorInfo]);
    }
    exit();
}

// Si ninguna acción coincide
echo json_encode(['success' => false, 'error' => 'Acción no válida: ' . $action]);
?>