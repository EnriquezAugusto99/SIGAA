<?php
session_start();
unset($_SESSION['forzar_cambio']);
$_SESSION = array();
session_destroy();  // Destruye la sesión
header("Location: ../index.php");
exit();
?>