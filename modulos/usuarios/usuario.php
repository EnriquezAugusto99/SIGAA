<?php
session_start();

if(isset($_SESSION["dni"]) && (time() - $_SESSION["ultima_actividad"] > 1800)){
    echo '<script>alert("Se venció el tiempo de la sesion"); window.location="../../index.php";</script>';
    exit();
}

if(!isset($_SESSION["dni"])){
    echo '<script>alert("Se venció el tiempo de la sesion"); window.location="../../index.php";</script>';
    exit();
}

// Verificar permisos (solo Admin)
if($_SESSION['rol'] != 'Admin'){
    echo '<script>alert("No tiene permisos para gestionar usuarios"); window.location="../../recursos/panel.php";</script>';
    exit();
}

// Generar token CSRF si no existe
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Incluir conexión UNA SOLA VEZ
include '../../recursos/conexion.php';

// Obtener roles
$query_rol = "SELECT * FROM rol ORDER BY nom_rol";
$res_rol = mysqli_query($con, $query_rol);

// Obtener cursos (solo una vez)
$query_curso = "SELECT * FROM curso ORDER BY curso, division";
$res_curso = mysqli_query($con, $query_curso);

// Guardar cursos en un array para usar después
$cursos = [];
while($fila_curso = mysqli_fetch_array($res_curso)){
    $cursos[] = $fila_curso;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../../recursos/styles.css">
    <title>ABM Usuario</title>
    <style>
        .h1{
            color: white;
        }
    </style>
    <script>
        function actualizarEstados() {
            var rolSelect = document.getElementById('txt_rol');
            var estadoSelect = document.getElementById('txt_estado');
            var rolSeleccionado = rolSelect.value;
            
            // Limpiar opciones actuales
            estadoSelect.innerHTML = '<option value="-1">Seleccione un estado</option>';
            
            if (rolSeleccionado == -1) return;
            
            // Definir estados por rol
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
            
            // Obtener nombres de estados
            var nombresEstados = {
                '1': 'Activo',
                '2': 'Inactivo',
                '3': 'Licencia',
                '4': 'Jubilado',
                '5': 'Egresado',
                '6': 'De baja'
            };
            
            // Agregar opciones disponibles
            estadosDisponibles.forEach(function(idEstado) {
                var option = document.createElement('option');
                option.value = idEstado;
                option.textContent = nombresEstados[idEstado];
                estadoSelect.appendChild(option);
            });
            
            // Mostrar/ocultar campo de curso si es Estudiante (rol 3)
            mostrarOcultarCurso(rolSeleccionado);
        }
        
        function mostrarOcultarCurso(rolId) {
            var campoCurso = document.getElementById('campo_curso');
            var selectCurso = document.getElementById('txt_curso');
            
            if(rolId == '3') { // ID del rol Estudiante
                campoCurso.style.display = 'block';
                selectCurso.required = true;
            } else {
                campoCurso.style.display = 'none';
                selectCurso.required = false;
                selectCurso.value = "0";
            }
        }
        
        // Ejecutar cuando la página cargue
        document.addEventListener('DOMContentLoaded', function() {
            actualizarEstados();
        });
    </script>
</head>
<body>
    <div class="caja">
        <h1>Modulo Usuario</h1>
        <form action="guardar_usuario.php" method="post">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
            <p>
                <label for="txt_dni">DNI</label>
                <input type="number" name="txt_dni" id="txt_dni" placeholder="Ingrese el DNI del usuario" min="10000000" max="99999999" required>
            </p>

            <p>
                <label for="txt_nombre">Nombre</label>
                <input type="text" name="txt_nombre" id="txt_nombre" placeholder="Ingrese el nombre del usuario" required>
            </p>

            <p>
                <label for="txt_apellido">Apellido</label>
                <input type="text" name="txt_apellido" id="txt_apellido" placeholder="Ingrese el apellido del usuario" required>
            </p>

            <p>
                <label for="txt_clave">Clave</label>
                <input type="text" name="txt_clave" id="txt_clave" placeholder="Ingrese la clave del usuario" required>
            </p>

            <p>
                <label for="txt_email">Email</label>
                <input type="email" name="txt_email" id="txt_email" placeholder="Ingrese el email del usuario">
            </p>

            <p>
                <label for="txt_rol">Seleccione el rol del usuario</label>
                <select name="txt_rol" id="txt_rol" onchange="actualizarEstados()" required>
                    <option value="-1">Seleccione un rol</option>
                    <?php while($fila_rol = mysqli_fetch_array($res_rol)): ?>
                        <option value="<?php echo $fila_rol['ID_rol']; ?>"><?php echo $fila_rol['nom_rol']; ?></option>
                    <?php endwhile; ?>
                </select> 
            </p>

            <p id="campo_curso" style="display: none;">
                <label for="txt_curso">Curso</label>
                <select name="txt_curso" id="txt_curso">
                    <option value="0">Seleccione un curso</option>
                    <?php foreach($cursos as $fila_curso): ?>
                        <option value="<?php echo $fila_curso['ID_curso']; ?>">
                            <?php echo $fila_curso['curso'] . '° "' . $fila_curso['division'] . '" - ' . ($fila_curso['turno'] == 'M' ? 'Mañana' : 'Tarde'); ?>
                        </option>
                    <?php endforeach; ?>
                </select> 
            </p>

            <p>
                <label for="txt_estado">Seleccione el estado del usuario</label>
                <select name="txt_estado" id="txt_estado" required>
                    <option value="-1">Seleccione un estado</option>
                </select> 
            </p>

            <p>
                <button type="submit">Guardar</button>
                <button type="reset">Limpiar los campos</button>
            </p>
        </form>
        <p>
            <a href="listado_usuario.php"><button>Ver Listado de Usuarios</button></a>
            <a href="../../recursos/panel.php"><button>Volver</button></a>
        </p>
    </div>
</body>
</html>
<?php mysqli_close($con); ?>