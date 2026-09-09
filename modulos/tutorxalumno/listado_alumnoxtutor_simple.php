<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

if(!isset($_SESSION["dni"])){
    echo "No hay sesion";
    exit();
}

include '../../recursos/conexion.php';

echo "<h1>Test - Listado de Relaciones</h1>";
echo "<p>Sesión activa: " . $_SESSION['dni'] . "</p>";

// Consulta simple
$query = "SELECT COUNT(*) as total FROM alumnoxtutor";
$res = mysqli_query($con, $query);
$total = mysqli_fetch_array($res)['total'];

echo "<p>Total relaciones: " . $total . "</p>";

// Consultar relaciones
$query2 = "SELECT 
    ax.id_AlumnoxTutor,
    a.Nombre as a_nombre, a.Apellido as a_apellido,
    t.Nombre as t_nombre, t.Apellido as t_apellido
    FROM alumnoxtutor ax
    INNER JOIN usuario a ON ax.id_alumno = a.DNI_U
    INNER JOIN usuario t ON ax.id_tutor = t.DNI_U
    LIMIT 10";

$res2 = mysqli_query($con, $query2);

echo "<table border='1'>";
echo "<tr><th>ID</th><th>Alumno</th><th>Tutor</th></tr>";
while($row = mysqli_fetch_array($res2)){
    echo "<tr>";
    echo "<td>" . $row['id_AlumnoxTutor'] . "</td>";
    echo "<td>" . $row['a_apellido'] . ", " . $row['a_nombre'] . "</td>";
    echo "<td>" . $row['t_apellido'] . ", " . $row['t_nombre'] . "</td>";
    echo "</tr>";
}
echo "</table>";

echo "<p><a href='tutorxalumno.php'>Volver</a></p>";
?>