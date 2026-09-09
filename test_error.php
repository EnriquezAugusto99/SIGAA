<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Paso 1: Script iniciado<br>";

echo "Paso 2: Incluyendo conexion.php...<br>";
include 'conexion.php';
echo "Paso 3: Conexión incluida correctamente<br>";

echo "Paso 4: Verificando PHPMailer...<br>";
if(file_exists('recursos/vendor/src/PHPMailer.php')){
    echo "Paso 5: PHPMailer encontrado<br>";
    require 'recursos/vendor/src/PHPMailer.php';
    echo "Paso 6: PHPMailer cargado<br>";
} else {
    echo "ERROR: PHPMailer NO encontrado en 'recursos/vendor/src/PHPMailer.php'<br>";
}

echo "Paso 7: Fin del test<br>";
?>