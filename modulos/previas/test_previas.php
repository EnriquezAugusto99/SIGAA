<?php
session_start();
include '../../recursos/conexion.php';

echo "<h1>TEST DE PREVIAS</h1>";

// Test 1: Verificar que la tabla previas existe y tiene la columna ID_taller
echo "<h2>Test 1: Estructura de tabla previas</h2>";
$query = "DESCRIBE previas";
$res = mysqli_query($con, $query);
if($res){
    echo "<table border='1'>";
    echo "<tr><th>Campo</th><th>Tipo</th><th>Null</th><th>Key</th></tr>";
    while($row = mysqli_fetch_array($res)){
        echo "<tr>";
        echo "<td>" . $row['Field'] . "</td>";
        echo "<td>" . $row['Type'] . "</td>";
        echo "<td>" . $row['Null'] . "</td>";
        echo "<td>" . $row['Key'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p style='color:red'>Error: " . mysqli_error($con) . "</p>";
}

// Test 2: Verificar conexión
echo "<h2>Test 2: Conexión a BD</h2>";
if($con){
    echo "<p style='color:green'>Conexión exitosa</p>";
} else {
    echo "<p style='color:red'>Error de conexión</p>";
}

// Test 3: Verificar materias de 1° año división A
echo "<h2>Test 3: Materias de 1° año (División A)</h2>";
$query = "SELECT DISTINCT m.ID_materia, m.Nom_materia
          FROM materia m
          INNER JOIN curso c ON m.id_curso = c.ID_curso
          WHERE c.curso = 1 AND c.division = 'A'
          AND m.Nom_materia NOT LIKE '%Taller%'
          LIMIT 10";
$res = mysqli_query($con, $query);
if($res){
    while($row = mysqli_fetch_array($res)){
        echo "<p>ID: " . $row['ID_materia'] . " - " . $row['Nom_materia'] . "</p>";
    }
} else {
    echo "<p style='color:red'>Error: " . mysqli_error($con) . "</p>";
}

// Test 4: Verificar talleres de 1° año
echo "<h2>Test 4: Talleres de 1° año</h2>";
$query = "SELECT ID_taller, nombre FROM talleres WHERE anio_taller = 'I' AND activo = 1";
$res = mysqli_query($con, $query);
if($res){
    while($row = mysqli_fetch_array($res)){
        echo "<p>ID: " . $row['ID_taller'] . " - " . $row['nombre'] . "</p>";
    }
} else {
    echo "<p style='color:red'>Error: " . mysqli_error($con) . "</p>";
}
?>