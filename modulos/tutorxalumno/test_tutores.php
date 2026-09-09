<?php
session_start();
include '../../recursos/conexion.php';

$query = "SELECT u.DNI_U, u.Nombre, u.Apellido 
          FROM usuario u
          INNER JOIN usuario_rol ur ON u.DNI_U = ur.dni_usuario
          WHERE ur.id_rol = 8 AND ur.activo = 1 AND u.ID_Estado = 1
          ORDER BY u.Apellido, u.Nombre";
$result = mysqli_query($con, $query);

echo "<h2>TEST - Tutores con rol adicional (id_rol=8)</h2>";
echo "<select>";
echo '<option value="-1">-- Seleccione un tutor --</option>';
while($row = mysqli_fetch_assoc($result)){
    echo '<option value="' . $row['DNI_U'] . '">' . $row['Apellido'] . ', ' . $row['Nombre'] . ' (DNI: ' . $row['DNI_U'] . ')</option>';
}
echo "</select>";
?>