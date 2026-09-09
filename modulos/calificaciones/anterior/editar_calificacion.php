<?php
session_start();

if(isset($_SESSION["dni"]) && (time() - $_SESSION["ultima_actividad"] > 1800)){
    echo '<script>alert("Se venció el tiempo de la sesion"); window.location="../../recursos/index.php";</script>';
    exit();
}

if(!isset($_SESSION["dni"])){
    echo '<script>alert("Se venció el tiempo de la sesion"); window.location="../../recursos/index.php";</script>';
    exit();
}

// Verificar permisos
if($_SESSION['rol'] != 'Admin' && $_SESSION['rol'] != 'Preceptor' && $_SESSION['rol'] != 'Secretario' && $_SESSION['rol'] != 'Profesor'){
    echo '<script>alert("No tiene permisos para editar calificaciones"); window.location="../../recursos/panel.php";</script>';
    exit();
}

include '../../recursos/conexion.php';

$id = $_GET['id'];

// Obtener la calificación actual
$query_calificacion = "SELECT calificaciones.id_calificacion, calificaciones.id_alumno, calificaciones.id_materia, calificaciones.id_docente, calificaciones.trimestre, calificaciones.nota, calificaciones.tipo_evaluacion, calificaciones.fecha, usuario.Nombre as nombre_alumno, usuario.Apellido as apellido_alumno, usuario.id_curso as id_curso_alumno, materia.Nom_materia as nombre_materia FROM calificaciones INNER JOIN usuario ON calificaciones.id_alumno = usuario.DNI_U INNER JOIN materia ON calificaciones.id_materia = materia.ID_materia WHERE calificaciones.id_calificacion = '$id'";
$res_calificacion = mysqli_query($con, $query_calificacion);

if(mysqli_num_rows($res_calificacion) == 0){
    echo '<script>alert("La calificación no existe"); window.location="listado_calificacion.php";</script>';
    exit();
}

$fila = mysqli_fetch_array($res_calificacion);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../../recursos/styles.css">
    <title>Editar Calificación</title>
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
        
        input[type="number"] {
            width: 100px;
        }
        
        input[type="date"] {
            width: 200px;
        }
    </style>
</head>
<body>
    <div class="caja">
        <h1>Editar Calificación</h1>
        <form action="guardar_editarCalificacion.php" method="post" id="formCalificacion">
            <input type="hidden" name="txt_id" value="<?php echo $fila['id_calificacion']; ?>">
            <input type="hidden" name="txt_alumno_original" value="<?php echo $fila['id_alumno']; ?>">
            <input type="hidden" name="txt_materia_original" value="<?php echo $fila['id_materia']; ?>">
            <input type="hidden" name="txt_docente_original" value="<?php echo $fila['id_docente']; ?>">
            <input type="hidden" name="txt_curso_original" value="<?php echo $fila['id_curso_alumno']; ?>">
            
            <!-- Paso 1: Seleccionar Curso -->
            <p>
                <label for="txt_curso">Seleccione el curso</label>
                <select name="txt_curso" id="txt_curso" required onchange="cargarDatosCurso()">
                    <option value="-1">Seleccione un curso</option>
                    <?php
                    $query_curso = "SELECT ID_curso, curso, division, turno FROM curso ORDER BY curso, division";
                    $res_curso = mysqli_query($con, $query_curso);
                    if(mysqli_num_rows($res_curso) > 0){
                        while($fila_curso = mysqli_fetch_array($res_curso)){
                            $turno_texto = $fila_curso['turno'] == 'M' ? 'Mañana' : 'Tarde';
                            $selected = ($fila_curso['ID_curso'] == $fila['id_curso_alumno']) ? 'selected' : '';
                            echo '<option value="'.$fila_curso["ID_curso"].'" '.$selected.'>'.$fila_curso["curso"].'° "'.$fila_curso["division"].'" - '.$turno_texto.'</option>';
                        }
                    }
                    ?>
                </select> 
            </p>
            
            <!-- Paso 2: Seleccionar Materia (se carga dinámicamente) -->
            <p>
                <label for="txt_materia">Seleccione la materia</label>
                <select name="txt_materia" id="txt_materia" required onchange="cargarDocentes()" disabled>
                    <option value="-1">Primero seleccione un curso</option>
                </select>
                <div id="loading-materias" class="loading" style="display: none;">Cargando materias...</div>
            </p>
            
            <!-- Paso 3: Seleccionar Alumno (se carga dinámicamente) -->
            <p>
                <label for="txt_alumno">Seleccione el alumno</label>
                <select name="txt_alumno" id="txt_alumno" required disabled>
                    <option value="-1">Primero seleccione un curso</option>
                </select>
                <div id="loading-alumnos" class="loading" style="display: none;">Cargando alumnos...</div>
            </p>
            
            <!-- Paso 4: Seleccionar Docente (se carga dinámicamente) -->
            <p>
                <label for="txt_docente">Seleccione el docente</label>
                <select name="txt_docente" id="txt_docente" required disabled>
                    <option value="-1">Primero seleccione materia y curso</option>
                </select>
                <div id="loading-docentes" class="loading" style="display: none;">Cargando docentes...</div>
            </p>

            <!-- Datos de la calificación -->
            <p>
                <label for="txt_nota">Nota</label>
                <input type="number" step="0.01" min="1" max="10" name="txt_nota" id="txt_nota" placeholder="Ej: 9.38" value="<?php echo $fila['nota']; ?>" required>
            </p>

            <p>
                <label for="txt_tipo_evaluacion">Tipo de evaluación</label>
                <input type="text" name="txt_tipo_evaluacion" id="txt_tipo_evaluacion" placeholder="Ej: Parcial, Trabajo Práctico, etc." value="<?php echo htmlspecialchars($fila['tipo_evaluacion']); ?>" required>
            </p>

            <p>
                <label for="txt_fecha">Fecha de la calificación</label>
                <input type="date" name="txt_fecha" id="txt_fecha" value="<?php echo $fila['fecha']; ?>" required onchange="validarFecha()">
                <div id="info-trimestre" class="loading" style="display: none;"></div>
            </p>

            <p>
                <button type="submit" id="btn-submit">Guardar Cambios</button>
                <button type="button" onclick="resetForm()">Restablecer</button>
                <a href="listado_calificacion.php"><button type="button">Cancelar</button></a>
            </p>
        </form>
    </div>
    
    <script>
        // Cargar datos iniciales
        window.onload = function() {
            const cursoId = <?php echo $fila['id_curso_alumno']; ?>;
            if(cursoId) {
                cargarMateriasInicial();
                cargarAlumnosInicial();
            }
        };
        
        function cargarMateriasInicial() {
            const cursoSelect = document.getElementById('txt_curso');
            const materiaSelect = document.getElementById('txt_materia');
            const cursoId = cursoSelect.value;
            const materiaOriginal = <?php echo $fila['id_materia']; ?>;
            
            if(cursoId == -1) {
                materiaSelect.innerHTML = '<option value="-1">Primero seleccione un curso</option>';
                materiaSelect.disabled = true;
                return;
            }
            
            materiaSelect.disabled = true;
            materiaSelect.innerHTML = '<option value="-1">Cargando materias...</option>';
            
            const xhr = new XMLHttpRequest();
            xhr.open('GET', 'obtener_materias_curso_calif.php?curso_id=' + cursoId, true);
            
            xhr.onload = function() {
                if(xhr.status === 200) {
                    const materias = JSON.parse(xhr.responseText);
                    
                    if(materias.length > 0) {
                        materiaSelect.innerHTML = '<option value="-1">Seleccione una materia</option>';
                        materias.forEach(function(materia) {
                            const option = document.createElement('option');
                            option.value = materia.ID_materia;
                            option.textContent = materia.Nom_materia;
                            if(materia.ID_materia == materiaOriginal) {
                                option.selected = true;
                            }
                            materiaSelect.appendChild(option);
                        });
                        materiaSelect.disabled = false;
                        
                        // Cargar docentes para la materia seleccionada
                        if(materiaOriginal != -1) {
                            setTimeout(function() {
                                cargarDocentesInicial();
                            }, 100);
                        }
                    } else {
                        materiaSelect.innerHTML = '<option value="-1">No hay materias asignadas a este curso</option>';
                        materiaSelect.disabled = true;
                    }
                } else {
                    materiaSelect.innerHTML = '<option value="-1">Error al cargar materias</option>';
                    materiaSelect.disabled = true;
                }
            };
            
            xhr.send();
        }
        
        function cargarAlumnosInicial() {
            const cursoSelect = document.getElementById('txt_curso');
            const alumnoSelect = document.getElementById('txt_alumno');
            const cursoId = cursoSelect.value;
            const alumnoOriginal = <?php echo $fila['id_alumno']; ?>;
            
            if(cursoId == -1) {
                alumnoSelect.innerHTML = '<option value="-1">Primero seleccione un curso</option>';
                alumnoSelect.disabled = true;
                return;
            }
            
            alumnoSelect.disabled = true;
            alumnoSelect.innerHTML = '<option value="-1">Cargando alumnos...</option>';
            
            const xhr = new XMLHttpRequest();
            xhr.open('GET', 'obtener_alumnos_curso.php?curso_id=' + cursoId, true);
            
            xhr.onload = function() {
                if(xhr.status === 200) {
                    const alumnos = JSON.parse(xhr.responseText);
                    
                    if(alumnos.length > 0) {
                        alumnoSelect.innerHTML = '<option value="-1">Seleccione un alumno</option>';
                        alumnos.forEach(function(alumno) {
                            const option = document.createElement('option');
                            option.value = alumno.DNI_U;
                            option.textContent = alumno.Apellido + ', ' + alumno.Nombre + ' (DNI: ' + alumno.DNI_U + ')';
                            if(alumno.DNI_U == alumnoOriginal) {
                                option.selected = true;
                            }
                            alumnoSelect.appendChild(option);
                        });
                        alumnoSelect.disabled = false;
                    } else {
                        alumnoSelect.innerHTML = '<option value="-1">No hay alumnos en este curso</option>';
                        alumnoSelect.disabled = true;
                    }
                } else {
                    alumnoSelect.innerHTML = '<option value="-1">Error al cargar alumnos</option>';
                    alumnoSelect.disabled = true;
                }
            };
            
            xhr.send();
        }
        
        function cargarDocentesInicial() {
            const materiaSelect = document.getElementById('txt_materia');
            const cursoSelect = document.getElementById('txt_curso');
            const docenteSelect = document.getElementById('txt_docente');
            const materiaId = materiaSelect.value;
            const cursoId = cursoSelect.value;
            const docenteOriginal = <?php echo $fila['id_docente']; ?>;
            
            if(materiaId == -1 || cursoId == -1) {
                docenteSelect.innerHTML = '<option value="-1">Primero seleccione materia y curso</option>';
                docenteSelect.disabled = true;
                return;
            }
            
            docenteSelect.disabled = true;
            docenteSelect.innerHTML = '<option value="-1">Cargando docentes...</option>';
            
            const xhr = new XMLHttpRequest();
            xhr.open('GET', 'obtener_docentes_materia_curso.php?materia_id=' + materiaId + '&curso_id=' + cursoId, true);
            
            xhr.onload = function() {
                if(xhr.status === 200) {
                    const docentes = JSON.parse(xhr.responseText);
                    
                    if(docentes.length > 0) {
                        docenteSelect.innerHTML = '<option value="-1">Seleccione un docente</option>';
                        docentes.forEach(function(docente) {
                            const option = document.createElement('option');
                            option.value = docente.DNI_U;
                            option.textContent = docente.Apellido + ', ' + docente.Nombre + ' (DNI: ' + docente.DNI_U + ')';
                            if(docente.DNI_U == docenteOriginal) {
                                option.selected = true;
                            }
                            docenteSelect.appendChild(option);
                        });
                        docenteSelect.disabled = false;
                    } else {
                        docenteSelect.innerHTML = '<option value="-1">No hay docentes asignados a esta materia en este curso</option>';
                        docenteSelect.disabled = true;
                    }
                } else {
                    docenteSelect.innerHTML = '<option value="-1">Error al cargar docentes</option>';
                    docenteSelect.disabled = true;
                }
            };
            
            xhr.send();
        }
        
        function cargarDatosCurso() {
            const cursoSelect = document.getElementById('txt_curso');
            const cursoId = cursoSelect.value;
            
            if(cursoId == -1) {
                resetForm();
                return;
            }
            
            // Cargar materias y alumnos en paralelo
            cargarMateriasInicial();
            cargarAlumnosInicial();
        }
        
        function cargarMaterias() {
            const cursoSelect = document.getElementById('txt_curso');
            const materiaSelect = document.getElementById('txt_materia');
            const loadingDiv = document.getElementById('loading-materias');
            const cursoId = cursoSelect.value;
            
            if(cursoId == -1) {
                materiaSelect.innerHTML = '<option value="-1">Primero seleccione un curso</option>';
                materiaSelect.disabled = true;
                return;
            }
            
            // Mostrar loading
            materiaSelect.disabled = true;
            loadingDiv.style.display = 'block';
            materiaSelect.innerHTML = '<option value="-1">Cargando materias...</option>';
            
            // Hacer petición AJAX para obtener las materias del curso
            const xhr = new XMLHttpRequest();
            xhr.open('GET', 'obtener_materias_curso_calif.php?curso_id=' + cursoId, true);
            
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
                    } else {
                        materiaSelect.innerHTML = '<option value="-1">No hay materias asignadas a este curso</option>';
                        materiaSelect.disabled = true;
                    }
                } else {
                    materiaSelect.innerHTML = '<option value="-1">Error al cargar materias</option>';
                    materiaSelect.disabled = true;
                }
                
                loadingDiv.style.display = 'none';
            };
            
            xhr.onerror = function() {
                materiaSelect.innerHTML = '<option value="-1">Error de conexión</option>';
                materiaSelect.disabled = true;
                loadingDiv.style.display = 'none';
            };
            
            xhr.send();
        }
        
        function cargarAlumnos() {
            const cursoSelect = document.getElementById('txt_curso');
            const alumnoSelect = document.getElementById('txt_alumno');
            const loadingDiv = document.getElementById('loading-alumnos');
            const cursoId = cursoSelect.value;
            
            if(cursoId == -1) {
                alumnoSelect.innerHTML = '<option value="-1">Primero seleccione un curso</option>';
                alumnoSelect.disabled = true;
                return;
            }
            
            // Mostrar loading
            alumnoSelect.disabled = true;
            loadingDiv.style.display = 'block';
            alumnoSelect.innerHTML = '<option value="-1">Cargando alumnos...</option>';
            
            // Hacer petición AJAX para obtener los alumnos del curso
            const xhr = new XMLHttpRequest();
            xhr.open('GET', 'obtener_alumnos_curso.php?curso_id=' + cursoId, true);
            
            xhr.onload = function() {
                if(xhr.status === 200) {
                    const alumnos = JSON.parse(xhr.responseText);
                    
                    if(alumnos.length > 0) {
                        alumnoSelect.innerHTML = '<option value="-1">Seleccione un alumno</option>';
                        alumnos.forEach(function(alumno) {
                            const option = document.createElement('option');
                            option.value = alumno.DNI_U;
                            option.textContent = alumno.Apellido + ', ' + alumno.Nombre + ' (DNI: ' + alumno.DNI_U + ')';
                            alumnoSelect.appendChild(option);
                        });
                        alumnoSelect.disabled = false;
                    } else {
                        alumnoSelect.innerHTML = '<option value="-1">No hay alumnos en este curso</option>';
                        alumnoSelect.disabled = true;
                    }
                } else {
                    alumnoSelect.innerHTML = '<option value="-1">Error al cargar alumnos</option>';
                    alumnoSelect.disabled = true;
                }
                
                loadingDiv.style.display = 'none';
            };
            
            xhr.onerror = function() {
                alumnoSelect.innerHTML = '<option value="-1">Error de conexión</option>';
                alumnoSelect.disabled = true;
                loadingDiv.style.display = 'none';
            };
            
            xhr.send();
        }
        
        function cargarDocentes() {
            const materiaSelect = document.getElementById('txt_materia');
            const cursoSelect = document.getElementById('txt_curso');
            const docenteSelect = document.getElementById('txt_docente');
            const loadingDiv = document.getElementById('loading-docentes');
            
            const materiaId = materiaSelect.value;
            const cursoId = cursoSelect.value;
            
            if(materiaId == -1 || cursoId == -1) {
                docenteSelect.innerHTML = '<option value="-1">Primero seleccione materia y curso</option>';
                docenteSelect.disabled = true;
                return;
            }
            
            // Mostrar loading
            docenteSelect.disabled = true;
            loadingDiv.style.display = 'block';
            docenteSelect.innerHTML = '<option value="-1">Cargando docentes...</option>';
            
            // Hacer petición AJAX para obtener los docentes de la materia en ese curso
            const xhr = new XMLHttpRequest();
            xhr.open('GET', 'obtener_docentes_materia_curso.php?materia_id=' + materiaId + '&curso_id=' + cursoId, true);
            
            xhr.onload = function() {
                if(xhr.status === 200) {
                    const docentes = JSON.parse(xhr.responseText);
                    
                    if(docentes.length > 0) {
                        docenteSelect.innerHTML = '<option value="-1">Seleccione un docente</option>';
                        docentes.forEach(function(docente) {
                            const option = document.createElement('option');
                            option.value = docente.DNI_U;
                            option.textContent = docente.Apellido + ', ' + docente.Nombre + ' (DNI: ' + docente.DNI_U + ')';
                            docenteSelect.appendChild(option);
                        });
                        docenteSelect.disabled = false;
                    } else {
                        docenteSelect.innerHTML = '<option value="-1">No hay docentes asignados a esta materia en este curso</option>';
                        docenteSelect.disabled = true;
                    }
                } else {
                    docenteSelect.innerHTML = '<option value="-1">Error al cargar docentes</option>';
                    docenteSelect.disabled = true;
                }
                
                loadingDiv.style.display = 'none';
            };
            
            xhr.onerror = function() {
                docenteSelect.innerHTML = '<option value="-1">Error de conexión</option>';
                docenteSelect.disabled = true;
                loadingDiv.style.display = 'none';
            };
            
            xhr.send();
        }
        
        function validarFecha() {
            const fechaInput = document.getElementById('txt_fecha');
            const infoDiv = document.getElementById('info-trimestre');
            const fecha = fechaInput.value;
            
            if(!fecha) {
                infoDiv.style.display = 'none';
                return;
            }
            
            // Hacer petición AJAX para validar la fecha
            const xhr = new XMLHttpRequest();
            xhr.open('GET', 'validar_fecha_trimestre.php?fecha=' + fecha, true);
            
            xhr.onload = function() {
                if(xhr.status === 200) {
                    const resultado = JSON.parse(xhr.responseText);
                    
                    if(resultado.valido) {
                        infoDiv.textContent = 'Instancia: ' + resultado.trimestre;
                        infoDiv.style.color = '#4CAF50';
                        infoDiv.style.display = 'block';
                    } else {
                        infoDiv.textContent = 'ERROR: No hay ninguna instancia en esta fecha';
                        infoDiv.style.color = '#F44336';
                        infoDiv.style.display = 'block';
                        document.getElementById('btn-submit').disabled = true;
                    }
                }
            };
            
            xhr.send();
        }
        
        function resetForm() {
            const cursoSelect = document.getElementById('txt_curso');
            cursoSelect.value = <?php echo $fila['id_curso_alumno']; ?>;
            cargarMateriasInicial();
            cargarAlumnosInicial();
            
            document.getElementById('txt_nota').value = '<?php echo $fila['nota']; ?>';
            document.getElementById('txt_tipo_evaluacion').value = '<?php echo htmlspecialchars($fila['tipo_evaluacion']); ?>';
            document.getElementById('txt_fecha').value = '<?php echo $fila['fecha']; ?>';
            document.getElementById('info-trimestre').style.display = 'none';
            document.getElementById('btn-submit').disabled = false;
        }
        
        document.getElementById('formCalificacion').addEventListener('submit', function(e) {
            const nota = document.getElementById('txt_nota').value;
            const tipoEval = document.getElementById('txt_tipo_evaluacion').value;
            const fecha = document.getElementById('txt_fecha').value;
            
            if(!nota || !tipoEval || !fecha){
                e.preventDefault();
                alert('Debe completar todos los campos del formulario.');
                return false;
            }
            
            // Validar rango de nota
            const notaNum = parseFloat(nota);
            if(notaNum < 1 || notaNum > 10){
                e.preventDefault();
                alert('La nota debe estar entre 1 y 10.');
                return false;
            }
        });
    </script>
</body>
</html>
<?php
mysqli_close($con);
?>