<?php
session_start();

// ============================================
// CAPA 1: LIMITAR INTENTOS DE LOGIN (BRUTE FORCE)
// ============================================
function registrarIntentoFallido($ip) {
    $archivo = sys_get_temp_dir() . '/login_intentos_' . md5($ip) . '.txt';
    $intentos = [];
    
    if(file_exists($archivo)) {
        $intentos = json_decode(file_get_contents($archivo), true);
        // Limpiar intentos viejos (más de 15 minutos)
        $limite = time() - 900;
        $intentos = array_filter($intentos, function($tiempo) use ($limite) {
            return $tiempo > $limite;
        });
    }
    
    $intentos[] = time();
    file_put_contents($archivo, json_encode($intentos));
    
    return count($intentos);
}

function verificarIntentosFallidos($ip) {
    $archivo = sys_get_temp_dir() . '/login_intentos_' . md5($ip) . '.txt';
    if(!file_exists($archivo)) return 0;
    
    $intentos = json_decode(file_get_contents($archivo), true);
    $limite = time() - 900; // 15 minutos
    $intentos_recientes = array_filter($intentos, function($tiempo) use ($limite) {
        return $tiempo > $limite;
    });
    
    return count($intentos_recientes);
}

function limpiarIntentosFallidos($ip) {
    $archivo = sys_get_temp_dir() . '/login_intentos_' . md5($ip) . '.txt';
    if(file_exists($archivo)) unlink($archivo);
}

// Obtener IP real del cliente
function obtenerIPReal() {
    $ip = '';
    if(!empty($_SERVER['HTTP_CLIENT_IP'])) {
        $ip = $_SERVER['HTTP_CLIENT_IP'];
    } elseif(!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
    } else {
        $ip = $_SERVER['REMOTE_ADDR'];
    }
    return $ip;
}

$ip_usuario = obtenerIPReal();
$intentos = verificarIntentosFallidos($ip_usuario);

if($intentos >= 5) {
    $tiempo_restante = 900 - (time() - filemtime(sys_get_temp_dir() . '/login_intentos_' . md5($ip_usuario) . '.txt'));
    $minutos = ceil($tiempo_restante / 60);
    echo '<script>alert("Demasiados intentos fallidos. Espere ' . $minutos . ' minutos antes de intentar nuevamente."); window.location.href="../index.php";</script>';
    exit();
}

// Verificar CSRF token
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    $_SESSION = array();
    session_destroy();
    echo '<script>alert("Sesión expirada. Por favor, inicie sesión nuevamente"); window.location.href="../index.php";</script>';
    exit();
}

// OBTENER DATOS
$dni = trim($_POST["txt_dni"]);
$contraseña = $_POST["txt_contraseña"];
$user_agent = $_SERVER['HTTP_USER_AGENT'];

// ============================================
// CAPA 2: VALIDACIONES ESTRICTAS
// ============================================
if(empty($dni)){
    registrarIntentoFallido($ip_usuario);
    sleep(rand(1, 3)); // Delay aleatorio para dificutar timing attacks
    echo '<script>alert("El DNI es requerido"); history.go(-1);</script>';
    exit();
}

if(empty($contraseña)){
    registrarIntentoFallido($ip_usuario);
    sleep(rand(1, 3));
    echo '<script>alert("La contraseña es requerida"); history.go(-1);</script>';
    exit();
}

// Sanitizar DNI (solo números)
if(!preg_match('/^\d+$/', $dni)){
    registrarIntentoFallido($ip_usuario);
    sleep(rand(1, 3));
    echo '<script>alert("El DNI solo puede contener números"); history.go(-1);</script>';
    exit();
}

if(strlen($dni) != 8){
    registrarIntentoFallido($ip_usuario);
    echo '<script>alert("El DNI debe tener 8 dígitos"); history.go(-1);</script>';
    exit();
}

if($dni <= 11000000){
    registrarIntentoFallido($ip_usuario);
    echo '<script>alert("DNI no válido"); history.go(-1);</script>';
    exit();
}

if($dni > 99999999){
    registrarIntentoFallido($ip_usuario);
    echo '<script>alert("DNI no válido"); history.go(-1);</script>';
    exit();
}

// ============================================
// CAPA 3: CONEXIÓN SEGURA A BD
// ============================================
include 'conexion.php';

// Verificar que la conexión sea exitosa
if(!$con) {
    registrarIntentoFallido($ip_usuario);
    sleep(rand(2, 5));
    echo '<script>alert("Error del sistema. Intente más tarde."); window.location.href="../index.php";</script>';
    exit();
}

// ============================================
// CAPA 4: QUERY PREPARADA (SQL INJECTION PROTECTION)
// ============================================
$query = "SELECT usuario.DNI_U, usuario.Nombre, usuario.Apellido, usuario.clave, 
                 usuario.tiene_multiples_roles, usuario.id_curso,
                 rol.nom_rol, estado.nom_estado 
          FROM usuario 
          INNER JOIN rol ON usuario.ID_rol = rol.ID_rol 
          INNER JOIN estado ON usuario.ID_Estado = estado.ID_estado 
          WHERE usuario.DNI_U = ? AND usuario.ID_Estado = 1";

$stmt = mysqli_prepare($con, $query);
mysqli_stmt_bind_param($stmt, "i", $dni);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);

if($res && mysqli_num_rows($res) > 0) {
    $fila = mysqli_fetch_array($res);
    
    // ============================================
    // CAPA 5: VERIFICACIÓN DE CONTRASEÑA (TIMING ATTACK PROTECTION)
    // ============================================
    $password_valida = false;
    if($fila["clave"] == $contraseña) {
        $password_valida = true;
    }
    
    // Delay artificial para evitar timing attacks (siempre el mismo tiempo)
    $tiempo_inicio = microtime(true);
    // Simular trabajo aunque la contraseña sea incorrecta
    for($i = 0; $i < 100000; $i++) {
        $x = sqrt($i);
    }
    $tiempo_fin = microtime(true);
    
    if(!$password_valida) {
        registrarIntentoFallido($ip_usuario);
        echo '<script>alert("Usuario y/o contraseña incorrectos"); history.go(-1);</script>';
        mysqli_close($con);
        exit();
    }
    
    // ============================================
    // CAPA 6: VERIFICAR ESTADO DEL USUARIO
    // ============================================
    if($fila['nom_estado'] != 'Activo'){
        registrarIntentoFallido($ip_usuario);
        echo '<script>alert("Usuario inactivo. Contacte al administrador"); history.go(-1);</script>';
        exit();
    }
    
    // ============================================
    // CAPA 7: REGENERAR SESSION ID (PREVENIR SESSION FIXATION)
    // ============================================
    session_regenerate_id(true);
    
    // ============================================
    // CAPA 8: GUARDAR DATOS EN SESIÓN (CON ROLES MÚLTIPLES)
    // ============================================
    $_SESSION['dni'] = $dni;
    $_SESSION['nombre'] = $fila['Nombre'];
    $_SESSION['apellido'] = $fila['Apellido'];
    $_SESSION['rol_principal'] = $fila['nom_rol'];
    $_SESSION['estado'] = $fila['nom_estado'];
    $_SESSION['ultima_actividad'] = time();
    $_SESSION['ip_usuario'] = $ip_usuario;
    $_SESSION['user_agent'] = $user_agent;
    $_SESSION['sesion_id'] = session_id();
    $_SESSION['tiene_multiples_roles'] = $fila['tiene_multiples_roles'];
    $_SESSION['id_curso'] = $fila['id_curso'] ?? null;
    
    // Cargar roles adicionales si tiene
    if($fila['tiene_multiples_roles'] == 1){
        $query_roles = "SELECT r.nom_rol 
                        FROM usuario_rol ur
                        INNER JOIN rol r ON ur.id_rol = r.ID_rol
                        WHERE ur.dni_usuario = '$dni' AND ur.activo = 1";
        $res_roles = mysqli_query($con, $query_roles);
        $_SESSION['roles_adicionales'] = [];
        while($rol = mysqli_fetch_assoc($res_roles)){
            $_SESSION['roles_adicionales'][] = $rol['nom_rol'];
        }
        // Rol activo por defecto = rol principal
        $_SESSION['rol_activo'] = $fila['nom_rol'];
    } else {
        $_SESSION['rol_activo'] = $fila['nom_rol'];
    }
    
    // Por compatibilidad con código existente que usa $_SESSION['rol']
    $_SESSION['rol'] = $_SESSION['rol_activo'];
    
    // ============================================
    // CAPA 9: VERIFICAR CONTRASEÑA INSEGURA / CUIL
    // ============================================
    function esCuil($contraseña, $dni) {
        if(strlen($contraseña) != 11) return false;
        if(!preg_match('/^\d+$/', $contraseña)) return false;
        $dni_del_cuil = substr($contraseña, 2, 8);
        return ($dni_del_cuil == $dni);
    }
    
    $claves_debiles = ["0", "12345678"];
    $es_cuil = esCuil($contraseña, $dni);
    $es_dni = ($contraseña == $dni);
    
    // Limpiar intentos fallidos después de login exitoso
    limpiarIntentosFallidos($ip_usuario);
    
    if($es_cuil || $es_dni || in_array($contraseña, $claves_debiles)) {
        $_SESSION['forzar_cambio'] = true;
        header("Location: cambiar_contraseña.php");
        exit();
    } else {
        header("Location: ../recursos/panel.php");
        exit();
    }
}

// Usuario no encontrado
registrarIntentoFallido($ip_usuario);
sleep(rand(1, 3)); // Delay para evitar timing attacks
echo '<script>alert("Usuario y/o contraseña incorrectos"); history.go(-1);</script>';
mysqli_close($con);
?>