<?php
session_start();
header('Content-Type: application/json');

if(!isset($_SESSION['dni'])){
    echo json_encode(['success' => false, 'message' => 'No hay sesión activa']);
    exit();
}

$rol = $_POST['rol'] ?? '';

if(!isset($_SESSION['rol_principal']) || !isset($_SESSION['roles_adicionales'])){
    echo json_encode(['success' => false, 'message' => 'No tienes roles múltiples configurados']);
    exit();
}

$es_valido = ($rol == $_SESSION['rol_principal']) || 
             (isset($_SESSION['roles_adicionales']) && in_array($rol, $_SESSION['roles_adicionales']));

if($es_valido){
    $_SESSION['rol_activo'] = $rol;
    // Actualizar también $_SESSION['rol'] para compatibilidad con código existente
    $_SESSION['rol'] = $rol;
    echo json_encode(['success' => true, 'rol' => $rol]);
} else {
    echo json_encode(['success' => false, 'message' => 'Rol no válido']);
}
?>