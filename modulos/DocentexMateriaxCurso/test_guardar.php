<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Paso 1: Script iniciado<br>";

session_start();
echo "Paso 2: Session iniciada<br>";

echo "Paso 3: Verificando POST<br>";
var_dump($_POST);
echo "<br>";

if(isset($_POST['txt_docente'])) {
    echo "Paso 4: txt_docente existe: " . $_POST['txt_docente'] . "<br>";
} else {
    echo "Paso 4: txt_docente NO existe<br>";
}

echo "Paso 5: Verificando CSRF token<br>";
if(isset($_POST['csrf_token'])) {
    echo "csrf_token enviado: " . $_POST['csrf_token'] . "<br>";
} else {
    echo "csrf_token NO enviado<br>";
}

if(isset($_SESSION['csrf_token'])) {
    echo "csrf_token en sesión: " . $_SESSION['csrf_token'] . "<br>";
} else {
    echo "csrf_token en sesión NO existe<br>";
}

echo "Paso 6: Fin del test<br>";
?>