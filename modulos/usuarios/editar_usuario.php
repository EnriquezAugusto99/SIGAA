<?php
session_start();

if(isset($_SESSION["dni"]) && (time() - $_SESSION["ultima_actividad"] > 18000)){
    echo '<script>alert("Se venció el tiempo de la sesion"); window.location="../../index.php";</script>';
    exit();
}

if(!isset($_SESSION["dni"])){
    echo '<script>alert("Se venció el tiempo de la sesion"); window.location="../../index.php";</script>';
    exit();
}

include '../../recursos/conexion.php';

$id = $_GET['id'];
$query = "SELECT usuario.DNI_U, usuario.Nombre, usuario.Apellido, usuario.clave, usuario.email, usuario.id_curso, usuario.ID_rol, usuario.ID_Estado FROM usuario WHERE usuario.DNI_U = '$id'";
$res = mysqli_query($con, $query);
$fila = mysqli_fetch_array($res);

// Generar token CSRF si no existe
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../../recursos/styles.css">
    <title>Editar Usuario</title>
    <script>
        function actualizarEstados() {
            var rolSelect = document.getElementById('txt_rol');
            var estadoSelect = document.getElementById('txt_estado');
            var rolSeleccionado = rolSelect.value;
            
            // Definir estados por rol para epet34_final
            var estadosPorRol = {
                '3': ['1', '2', '5', '6'], // Estudiante: Activo, Inactivo, Egresado, De baja
                '2': ['1', '2', '3', '4'], // Profesor: Activo, Inactivo, Licencia, Jubilado
                '1': ['1', '2', '3', '4'], // Preceptor: Activo, Inactivo, Licencia, Jubilado
                '6': ['1'], // Admin: solo Activo
                '8': ['1'], // Tutor: solo Activo
                '7': ['1'], // Invitado: solo Activo
                '5': ['1']  // Secretario: solo Activo
            };
            
            var estadosDisponibles = estadosPorRol[rolSeleccionado] || ['1'];
            var estadoActual = '<?php echo $fila["ID_Estado"]; ?>';
            
            var nombresEstados = {
                '1': 'Activo',
                '2': 'Inactivo',
                '3': 'Licencia',
                '4': 'Jubilado',
                '5': 'Egresado',
                '6': 'De baja'
            };
            
            // Limpiar y actualizar opciones
            estadoSelect.innerHTML = '';
            
            estadosDisponibles.forEach(function(idEstado) {
                var option = document.createElement('option');
                option.value = idEstado;
                option.textContent = nombresEstados[idEstado];
                if (idEstado == estadoActual) {
                    option.selected = true;
                }
                estadoSelect.appendChild(option);
            });
            
            // Si el estado actual no está disponible, seleccionar el primero
            if (!estadosDisponibles.includes(estadoActual)) {
                estadoSelect.selectedIndex = 0;
            }
            
            // Mostrar/ocultar campo de curso
            mostrarOcultarCurso(rolSeleccionado);
        }
        
        function mostrarOcultarCurso(rolId) {
            var campoCurso = document.getElementById('campo_curso');
            var selectCurso = document.getElementById('txt_curso');
            
            if(rolId == '3') {
                campoCurso.style.display = 'block';
                selectCurso.required = true;
                
                // Si no hay curso seleccionado, poner valor por defecto
                if(selectCurso.value == "" || selectCurso.value == "0") {
                    selectCurso.value = "<?php echo $fila['id_curso'] ?? '0'; ?>";
                }
            } else {
                campoCurso.style.display = 'none';
                selectCurso.required = false;
                selectCurso.value = "0";
            }
        }
        
        document.addEventListener('DOMContentLoaded', function() {
            actualizarEstados();
        });
    </script>
</head>
<body>
    <div class="caja">
        <h1>Editar Usuario</h1>
        <form action="guardar_editarUsuario.php" method="post">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
            <input type="hidden" name="txt_id" value="<?php echo $fila['DNI_U']; ?>">
            
            <p>
                <label for="txt_dni">DNI:</label>
                <input type="number" name="txt_dni" id="txt_dni" value="<?php echo $fila['DNI_U']; ?>" readonly>
            </p>

            <p>
                <label for="txt_nombre">Nombre:</label>
                <input type="text" name="txt_nombre" id="txt_nombre" value="<?php echo htmlspecialchars($fila['Nombre']); ?>" required>
            </p>

            <p>
                <label for="txt_apellido">Apellido:</label>
                <input type="text" name="txt_apellido" id="txt_apellido" value="<?php echo htmlspecialchars($fila['Apellido']); ?>" required>
            </p>

            <p>
                <label for="txt_clave">Clave:</label>
                <input type="text" name="txt_clave" id="txt_clave" value="<?php echo htmlspecialchars($fila['clave']); ?>" required>
            </p>

            <p>
                <label for="txt_email">Email:</label>
                <input type="email" name="txt_email" id="txt_email" value="<?php echo htmlspecialchars($fila['email'] ?? ''); ?>">
            </p>

            <p id="campo_curso" style="display: none;">
                <label for="txt_curso">Curso *</label>
                <select name="txt_curso" id="txt_curso">
                    <option value="0">Seleccione un curso</option>
                    <?php
                        $query_cursos="SELECT * FROM curso ORDER BY curso, division";
                        $res_cursos=mysqli_query($con,$query_cursos);
                        if(mysqli_num_rows($res_cursos) > 0){
                            while($fila_curso = mysqli_fetch_array($res_cursos)){
                                $selected = ($fila_curso["ID_curso"] == $fila['id_curso']) ? 'selected' : '';
                                echo '<option value="'.$fila_curso["ID_curso"].'" '.$selected.'>'.$fila_curso["curso"].'° "'.$fila_curso["division"].'" - '.$fila_curso["turno"].'</option>';
                            }
                        }
                    ?>
                </select> 
            </p>

            <p>
                <label for="txt_rol">Rol:</label>
                <select name="txt_rol" id="txt_rol" onchange="actualizarEstados()" required>
                    <?php
                        $query_roles="SELECT * FROM rol ORDER BY nom_rol";
                        $res_roles=mysqli_query($con,$query_roles);
                        if(mysqli_num_rows($res_roles) > 0){
                            while($fila_rol = mysqli_fetch_array($res_roles)){
                                $selected = ($fila_rol["ID_rol"] == $fila['ID_rol']) ? 'selected' : '';
                                echo '<option value="'.$fila_rol["ID_rol"].'" '.$selected.'>'.$fila_rol["nom_rol"].'</option>';
                            }
                        }
                    ?>
                </select> 
            </p>

            <p>
                <label for="txt_estado">Estado:</label>
                <select name="txt_estado" id="txt_estado" required>
                    <!-- Opciones se cargan dinámicamente -->
                </select> 
            </p>

            <p>
                <button type="submit">Guardar Cambios</button>
                <a href="listado_usuario.php"><button type="button">Cancelar</button></a>
            </p>
        </form>
    </div>
</body>
</html>
<?php
mysqli_close($con);
?>