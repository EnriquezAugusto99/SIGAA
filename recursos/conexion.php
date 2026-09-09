<?php
$con=mysqli_connect("localhost","a0011360_e34","niBE96figo","a0011360_e34");
if(!$con){
    echo '<script>alert("No se puede conectar a la base de datos"); window.location="../index.php";</script>';
    exit();
}
mysqli_set_charset($con, "utf8");
?>