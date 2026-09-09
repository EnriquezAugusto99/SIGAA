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

    // Verificar permisos (solo Admin, Preceptor y Secretario)
    if($_SESSION['rol'] != 'Admin' && $_SESSION['rol'] != 'Preceptor' && $_SESSION['rol'] != 'Secretario'){
        echo '<script>alert("No tiene permisos para gestionar asignaciones docente-materia-curso"); window.location="../../recursos/panel.php";</script>';
        exit();
    }

    include '../../recursos/conexion.php';

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
    <title>ABM Docente x Materia x Curso</title>
    <style>
        select {
            width: 100%;
            max-width: 500px;
            padding: 8px;
            border-radius: 4px;
            border: 1px solid #40E0D0;
            background: #2D5A8C;
            color: #E0F7FA;
        }
        
        .loading {
            color: #40E0D0;
            font-style: italic;
        }
    </style>
</head>
<body>
    <div class="caja">
        <h1>Modulo Docente x Materia x Curso</h1>
        
        <form action="guardar_docentemateriacurso.php" method="post" id="formAsignacion">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
            <p>
                <label for="txt_docente">Seleccione el docente</label>
                <select name="txt_docente" id="txt_docente" required>
                    <option value="-1">Seleccione un docente</option>
                    <?php
                        $query_docente = "SELECT DNI_U, Nombre, Apellido FROM usuario WHERE ID_rol = 2 AND ID_Estado = 1 ORDER BY Apellido, Nombre";
                        $res_docente = mysqli_query($con, $query_docente);
                        if(mysqli_num_rows($res_docente) > 0){
                            while($fila_docente = mysqli_fetch_array($res_docente)){
                                echo '<option value="'.$fila_docente["DNI_U"].'">'.$fila_docente["Apellido"].', '.$fila_docente["Nombre"].' (DNI: '.$fila_docente["DNI_U"].')</option>';
                            }
                        }
                    ?>
                </select> 
            </p>

            <p>
                <label for="txt_curso">Seleccione el curso</label>
                <select name="txt_curso" id="txt_curso" required onchange="cargarMaterias()">
                    <option value="-1">Seleccione un curso</option>
                    <?php
                        $query_curso = "SELECT ID_curso, curso, division, turno FROM curso ORDER BY curso, division, turno";
                        $res_curso = mysqli_query($con, $query_curso);
                        if(mysqli_num_rows($res_curso) > 0){
                            while($fila_curso = mysqli_fetch_array($res_curso)){
                                $turno_texto = $fila_curso['turno'] == 'M' ? 'Mañana' : 'Tarde';
                                echo '<option value="'.$fila_curso["ID_curso"].'">'.$fila_curso["curso"].'° "'.$fila_curso["division"].'" - '.$turno_texto.'</option>';
                            }
                        }
                    ?>
                </select> 
            </p>

            <p>
                <label for="txt_materia">Seleccione la materia</label>
                <select name="txt_materia" id="txt_materia" required disabled>
                    <option value="-1">Primero seleccione un curso</option>
                </select> 
                <div id="loading-materias" class="loading" style="display: none;">Cargando materias...</div>
            </p>

            <p>
                <button type="submit" id="btn-submit" disabled>Guardar</button>
                <button type="reset" onclick="resetForm()">Limpiar los campos</button>
            </p>
        </form>
        <p>
            <a href="listado_docentemateriacurso.php"><button>Ver Listado de Relaciones</button></a>
            <a href="../../recursos/panel.php"><button>Volver</button></a>
        </p>
    </div>
    
    <script>
        function cargarMaterias() {
            const cursoSelect = document.getElementById('txt_curso');
            const materiaSelect = document.getElementById('txt_materia');
            const loadingDiv = document.getElementById('loading-materias');
            const submitBtn = document.getElementById('btn-submit');
            
            const cursoId = cursoSelect.value;
            
            if(cursoId == -1) {
                materiaSelect.innerHTML = '<option value="-1">Primero seleccione un curso</option>';
                materiaSelect.disabled = true;
                submitBtn.disabled = true;
                return;
            }
            
            // Mostrar loading
            materiaSelect.disabled = true;
            loadingDiv.style.display = 'block';
            materiaSelect.innerHTML = '<option value="-1">Cargando materias...</option>';
            
            // Hacer petición AJAX para obtener las materias del curso
            const xhr = new XMLHttpRequest();
            xhr.open('GET', 'obtener_materias_curso.php?curso_id=' + cursoId, true);
            
            xhr.onload = function() {
                if(xhr.status === 200) {
                    const materias = JSON.parse(xhr.responseText);
                    
                    if(materias.length > 0) {
                        materiaSelect.innerHTML = '<option value="-1">Seleccione una materia</option>';
                        materias.forEach(function(materia) {
                            const option = document.createElement('option');
                            option.value = materia.ID_materia;
                            option.textContent = materia.Nom_materia;
                            materiaSelect.appendChild(option);
                        });
                        materiaSelect.disabled = false;
                        submitBtn.disabled = false;
                    } else {
                        materiaSelect.innerHTML = '<option value="-1">No hay materias asignadas a este curso</option>';
                        materiaSelect.disabled = true;
                        submitBtn.disabled = true;
                    }
                } else {
                    materiaSelect.innerHTML = '<option value="-1">Error al cargar materias</option>';
                    materiaSelect.disabled = true;
                    submitBtn.disabled = true;
                }
                
                loadingDiv.style.display = 'none';
            };
            
            xhr.onerror = function() {
                materiaSelect.innerHTML = '<option value="-1">Error de conexión</option>';
                materiaSelect.disabled = true;
                submitBtn.disabled = true;
                loadingDiv.style.display = 'none';
            };
            
            xhr.send();
        }
        
        function resetForm() {
            document.getElementById('txt_materia').innerHTML = '<option value="-1">Primero seleccione un curso</option>';
            document.getElementById('txt_materia').disabled = true;
            document.getElementById('btn-submit').disabled = true;
            document.getElementById('loading-materias').style.display = 'none';
        }
        
        document.getElementById('formAsignacion').addEventListener('submit', function(e) {
            const docente = document.getElementById('txt_docente').value;
            const materia = document.getElementById('txt_materia').value;
            const curso = document.getElementById('txt_curso').value;
            
            if(docente == -1 || materia == -1 || curso == -1){
                e.preventDefault();
                alert('Debe seleccionar docente, curso y materia.');
                return false;
            }
        });
    </script>
</body>
</html>
<?php
mysqli_close($con);
?>