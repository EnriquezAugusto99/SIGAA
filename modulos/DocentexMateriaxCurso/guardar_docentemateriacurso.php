<?php
// ============================================
// DEBUGGING EXTREMO - Mostrar todos los errores
// ============================================
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

// Crear archivo de log
$log_file = dirname(__FILE__) . '/debug_guardar.log';
file_put_contents($log_file, "=== " . date('Y-m-d H:i:s') . " ===\n", FILE_APPEND);

function debug_log($msg) {
    global $log_file;
    file_put_contents($log_file, $msg . "\n", FILE_APPEND);
}

debug_log("Paso 1: Iniciando script");

session_start();
debug_log("Paso 2: Session iniciada");

// Verificar sesión
if(isset($_SESSION["dni"]) && (time() - $_SESSION["ultima_actividad"] > 1800)){
    debug_log("Paso 3a: Sesión expirada");
    echo '<script>alert("Se venció el tiempo de la sesion"); window.location="../../index.php";</script>';
    exit();
}

if(!isset($_SESSION["dni"])){
    debug_log("Paso 3b: No hay sesión");
    echo '<script>alert("Se venció el tiempo de la sesion"); window.location="../../index.php";</script>';
    exit();
}

debug_log("Paso 3: Sesión válida. DNI: " . $_SESSION['dni']);

// Verificar permisos
if($_SESSION['rol'] != 'Admin' && $_SESSION['rol'] != 'Preceptor' && $_SESSION['rol'] != 'Secretario'){
    debug_log("Paso 3c: Permiso denegado. Rol: " . $_SESSION['rol']);
    echo '<script>alert("No tiene permisos para gestionar asignaciones docente-materia-curso"); window.location="../../recursos/panel.php";</script>';
    exit();
}

debug_log("Paso 4: Permisos OK");

// Verificar CSRF token (sin destruir sesión)
debug_log("Paso 5: Verificando CSRF");
debug_log("POST csrf_token: " . (isset($_POST['csrf_token']) ? $_POST['csrf_token'] : 'NO EXISTE'));
debug_log("SESSION csrf_token: " . (isset($_SESSION['csrf_token']) ? $_SESSION['csrf_token'] : 'NO EXISTE'));

$token_valido = false;
if(isset($_POST['csrf_token']) && isset($_SESSION['csrf_token'])) {
    if($_POST['csrf_token'] === $_SESSION['csrf_token']) {
        $token_valido = true;
        debug_log("Paso 5a: Token válido");
    } else {
        debug_log("Paso 5b: Token NO coincide");
    }
} else {
    debug_log("Paso 5c: Token no presente en POST o SESSION");
}

if(!$token_valido) {
    // NO destruir la sesión, solo mostrar error
    debug_log("Paso 5d: Token inválido - redirigiendo");
    echo '<script>alert("Error de seguridad: Token inválido. Por favor, recargue la página."); window.location.href="docentemateriacurso.php";</script>';
    exit();
}

debug_log("Paso 6: Obteniendo datos POST");
$docente = isset($_POST['txt_docente']) ? $_POST['txt_docente'] : '';
$materia = isset($_POST['txt_materia']) ? $_POST['txt_materia'] : '';
$curso = isset($_POST['txt_curso']) ? $_POST['txt_curso'] : '';

debug_log("Docente: $docente, Materia: $materia, Curso: $curso");

// Validaciones
if($docente == -1 || $materia == -1 || $curso == -1){
    debug_log("Paso 7: Valores -1");
    echo '<script>alert("ERROR: Debe seleccionar docente, materia y curso"); history.go(-1);</script>';
    exit();
}

if(!is_numeric($docente) || !is_numeric($materia) || !is_numeric($curso)){
    debug_log("Paso 8: No son numéricos");
    echo '<script>alert("ERROR: Los datos enviados no son válidos"); history.go(-1);</script>';
    exit();
}

debug_log("Paso 9: Incluyendo conexión");
include '../../recursos/conexion.php';

if(!$con) {
    debug_log("Paso 9a: Error de conexión");
    echo '<script>alert("Error de conexión a la base de datos"); history.go(-1);</script>';
    exit();
}

debug_log("Paso 10: Conexión OK");

// Resto del código...
$query_verificar_docente = "SELECT DNI_U, Nombre, Apellido FROM usuario WHERE DNI_U = '$docente' AND ID_rol = 2 AND ID_Estado = 1";
debug_log("Query docente: $query_verificar_docente");
$res_verificar_docente = mysqli_query($con, $query_verificar_docente);

if(!$res_verificar_docente) {
    debug_log("Error en query docente: " . mysqli_error($con));
    echo '<script>alert("Error en la consulta de docente"); history.go(-1);</script>';
    exit();
}

if(mysqli_num_rows($res_verificar_docente) == 0){
    debug_log("Paso 11: Docente no encontrado o no activo");
    echo '<script>alert("ERROR: El docente seleccionado no existe o no está activo"); history.go(-1);</script>';
    exit();
}
$fila_docente = mysqli_fetch_array($res_verificar_docente);
$nombre_docente = $fila_docente['Apellido'] . ', ' . $fila_docente['Nombre'];
debug_log("Docente encontrado: $nombre_docente");

// Verificar materia
$query_verificar_materia = "SELECT ID_materia, Nom_materia, id_curso FROM materia WHERE ID_materia = '$materia'";
debug_log("Query materia: $query_verificar_materia");
$res_verificar_materia = mysqli_query($con, $query_verificar_materia);

if(!$res_verificar_materia) {
    debug_log("Error en query materia: " . mysqli_error($con));
    echo '<script>alert("Error en la consulta de materia"); history.go(-1);</script>';
    exit();
}

if(mysqli_num_rows($res_verificar_materia) == 0){
    debug_log("Paso 12: Materia no encontrada");
    echo '<script>alert("ERROR: La materia seleccionada no existe"); history.go(-1);</script>';
    exit();
}
$fila_materia = mysqli_fetch_array($res_verificar_materia);
$nombre_materia = $fila_materia['Nom_materia'];
debug_log("Materia encontrada: $nombre_materia");

// Verificar que materia pertenezca al curso
if($fila_materia['id_curso'] != $curso){
    debug_log("Paso 13: Materia no pertenece al curso. id_curso_materia: " . $fila_materia['id_curso'] . ", curso seleccionado: $curso");
    echo '<script>alert("ERROR: La materia seleccionada no pertenece al curso seleccionado"); history.go(-1);</script>';
    exit();
}

// Verificar curso
$query_verificar_curso = "SELECT ID_curso, curso, division, turno FROM curso WHERE ID_curso = '$curso'";
debug_log("Query curso: $query_verificar_curso");
$res_verificar_curso = mysqli_query($con, $query_verificar_curso);

if(!$res_verificar_curso) {
    debug_log("Error en query curso: " . mysqli_error($con));
    echo '<script>alert("Error en la consulta de curso"); history.go(-1);</script>';
    exit();
}

if(mysqli_num_rows($res_verificar_curso) == 0){
    debug_log("Paso 14: Curso no encontrado");
    echo '<script>alert("ERROR: El curso seleccionado no existe"); history.go(-1);</script>';
    exit();
}

$fila_curso = mysqli_fetch_array($res_verificar_curso);
$turno_texto = $fila_curso['turno'] == 'M' ? 'Mañana' : 'Tarde';
$nombre_curso = $fila_curso['curso'] . '° "' . $fila_curso['division'] . '" - ' . $turno_texto;
debug_log("Curso encontrado: $nombre_curso");

// Verificar si ya existe
$query_verificar = "SELECT id_dmc FROM docentemateriacurso WHERE id_docente = '$docente' AND id_materia = '$materia' AND id_curso = '$curso'";
debug_log("Query verificar existencia: $query_verificar");
$res_verificar = mysqli_query($con, $query_verificar);

if(mysqli_num_rows($res_verificar) > 0) {
    debug_log("Paso 15: Asignación ya existe");
    echo '<script>alert("ADVERTENCIA: Esta asignación ya existe"); history.go(-1);</script>';
    exit();
}

// Insertar
$query = "INSERT INTO docentemateriacurso (id_docente, id_materia, id_curso) VALUES ('$docente', '$materia', '$curso')";
debug_log("Query insert: $query");
$res = mysqli_query($con, $query);

if($res) {
    debug_log("Paso 16: Inserción exitosa");
    echo '<script>window.location="listado_docentemateriacurso.php";</script>';
    exit();
} else {
    debug_log("Paso 16a: Error en inserción: " . mysqli_error($con));
    echo '<script>alert("ERROR: No se pudo guardar la asignación. Error: ' . mysqli_error($con) . '"); history.go(-1);</script>';
}

mysqli_close($con);
debug_log("Paso 17: Fin del script");
?>