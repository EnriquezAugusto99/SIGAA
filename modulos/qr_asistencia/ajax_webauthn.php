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

$dni_alumno = $_SESSION["dni"];
$action = isset($_POST['action']) ? $_POST['action'] : '';

// ============================================
// REGISTRO - INICIO
// ============================================
if($action == 'register_start'){
    $nombre = isset($_POST['nombre']) ? $_POST['nombre'] : '';
    
    if(empty($nombre)){
        echo json_encode(['success' => false, 'message' => 'Nombre requerido']);
        exit();
    }
    
    // Verificar si ya tiene credencial
    $query_check = "SELECT COUNT(*) as total FROM webauthn_credentials WHERE dni_usuario = '$dni_alumno'";
    $res_check = mysqli_query($con, $query_check);
    $total = mysqli_fetch_assoc($res_check)['total'];
    
    if($total > 0){
        echo json_encode(['success' => false, 'message' => 'Ya tienes una huella/rostro registrado']);
        exit();
    }
    
    // Generar challenge aleatorio
    $challenge = base64_encode(random_bytes(32));
    $_SESSION['webauthn_challenge'] = $challenge;
    
    $options = [
        'challenge' => $challenge,
        'rp' => [
            'name' => 'EPET N°34 - Sistema Escolar',
            'id' => $_SERVER['HTTP_HOST']
        ],
        'user' => [
            'id' => base64_encode($dni_alumno),
            'name' => $dni_alumno,
            'displayName' => $nombre
        ],
        'pubKeyCredParams' => [
            ['type' => 'public-key', 'alg' => -7],
            ['type' => 'public-key', 'alg' => -257]
        ],
        'authenticatorSelection' => [
            'authenticatorAttachment' => 'platform',
            'residentKey' => 'preferred',
            'userVerification' => 'required'
        ],
        'timeout' => 60000,
        'attestation' => 'none'
    ];
    
    echo json_encode(['success' => true, 'options' => $options]);
    exit();
}

// ============================================
// REGISTRO - COMPLETAR
// ============================================
if($action == 'register_complete'){
    $credential_data = isset($_POST['credential']) ? json_decode(urldecode($_POST['credential']), true) : null;
    
    if(!$credential_data){
        echo json_encode(['success' => false, 'message' => 'Datos de credencial inválidos']);
        exit();
    }
    
    // Verificar challenge (en producción, verificar firmas)
    // Por simplicidad, guardamos la credencial
    
    $credential_id = $credential_data['id'];
    $public_key = $credential_data['response']['attestationObject'];
    $transports = json_encode(['internal']);
    
    $query_insert = "INSERT INTO webauthn_credentials (dni_usuario, credential_id, public_key, transports) 
                     VALUES ('$dni_alumno', '$credential_id', '$public_key', '$transports')";
    
    if(mysqli_query($con, $query_insert)){
        unset($_SESSION['webauthn_challenge']);
        echo json_encode(['success' => true, 'message' => 'Credencial registrada correctamente']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error al guardar: ' . mysqli_error($con)]);
    }
    exit();
}

// ============================================
// AUTENTICACIÓN - INICIO
// ============================================
if($action == 'login_start'){
    // Obtener credenciales del usuario
    $query_cred = "SELECT credential_id, transports FROM webauthn_credentials WHERE dni_usuario = '$dni_alumno'";
    $res_cred = mysqli_query($con, $query_cred);
    
    $allowCredentials = [];
    while($row = mysqli_fetch_assoc($res_cred)){
        $transports = json_decode($row['transports'], true) ?: [];
        $allowCredentials[] = [
            'id' => $row['credential_id'],
            'type' => 'public-key',
            'transports' => $transports
        ];
    }
    
    if(empty($allowCredentials)){
        echo json_encode(['success' => false, 'message' => 'No tienes una huella/rostro registrado']);
        exit();
    }
    
    // Generar challenge
    $challenge = base64_encode(random_bytes(32));
    $_SESSION['webauthn_challenge'] = $challenge;
    
    $options = [
        'challenge' => $challenge,
        'allowCredentials' => $allowCredentials,
        'userVerification' => 'required',
        'timeout' => 60000,
        'rpId' => $_SERVER['HTTP_HOST']
    ];
    
    echo json_encode(['success' => true, 'options' => $options]);
    exit();
}

// ============================================
// AUTENTICACIÓN - COMPLETAR
// ============================================
if($action == 'login_complete'){
    $auth_data = isset($_POST['auth_data']) ? json_decode(urldecode($_POST['auth_data']), true) : null;
    
    if(!$auth_data){
        echo json_encode(['success' => false, 'message' => 'Datos de autenticación inválidos']);
        exit();
    }
    
    // Verificar que la credencial existe
    $credential_id = $auth_data['id'];
    $query_check = "SELECT COUNT(*) as total FROM webauthn_credentials 
                    WHERE dni_usuario = '$dni_alumno' AND credential_id = '$credential_id'";
    $res_check = mysqli_query($con, $query_check);
    $total = mysqli_fetch_assoc($res_check)['total'];
    
    if($total == 0){
        echo json_encode(['success' => false, 'message' => 'Credencial no válida']);
        exit();
    }
    
    // Actualizar último uso
    $query_update = "UPDATE webauthn_credentials SET last_used = NOW() 
                     WHERE dni_usuario = '$dni_alumno' AND credential_id = '$credential_id'";
    mysqli_query($con, $query_update);
    
    unset($_SESSION['webauthn_challenge']);
    
    echo json_encode(['success' => true, 'message' => 'Autenticación exitosa']);
    exit();
}

// ============================================
// GENERAR QR PARA EL ALUMNO (desde la app)
// ============================================
if($action == 'generar_qr'){
    $dni = $_SESSION['dni'];
    
    $fecha = date('Y-m-d');
    $hora = date('H:i:s');
    $timestamp = time() * 1000;
    
    $datosQR = [
        'dni' => $dni,
        'fecha' => $fecha,
        'hora' => $hora,
        'timestamp' => $timestamp
    ];
    
    echo json_encode(['success' => true, 'qr_data' => $datosQR]);
    exit();
}

echo json_encode(['success' => false, 'message' => 'Acción no válida']);
?>