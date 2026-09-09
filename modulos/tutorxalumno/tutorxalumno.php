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
    echo '<script>alert("No tiene permisos para gestionar relaciones tutor-alumno"); window.location="../../recursos/panel.php";</script>';
    exit();
}

include '../../recursos/conexion.php';

// Generar token CSRF si no existe
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Obtener cursos para el filtro
$query_cursos = "SELECT ID_curso, curso, division, turno FROM curso ORDER BY curso, division";
$res_cursos = mysqli_query($con, $query_cursos);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../../recursos/styles.css">
    <title>ABM Relación Tutor x Alumno</title>
    <style>
        .contenedor-form {
            max-width: 650px;
            margin: 0 auto;
            background: var(--card-bg);
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .header-form {
            background: var(--dark-red);
            padding: 20px 25px;
        }
        
        .header-form h1 {
            color: white;
            font-size: 22px;
            font-weight: 600;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .header-form h1 i {
            font-size: 24px;
        }
        
        .body-form {
            padding: 30px;
        }
        
        .info-box {
            background: rgba(113,10,20,0.08);
            border-left: 4px solid var(--deep-crimson);
            padding: 12px 16px;
            margin-bottom: 25px;
            border-radius: 8px;
            font-size: 13px;
            color: var(--text-dark);
        }
        
        .info-box strong {
            color: var(--deep-crimson);
        }
        
        .campo {
            margin-bottom: 20px;
        }
        
        .campo label {
            display: block;
            font-weight: 600;
            color: var(--dark-burgundy);
            margin-bottom: 8px;
            font-size: 14px;
        }
        
        .campo select {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid var(--border-color);
            border-radius: 10px;
            font-size: 14px;
            font-family: 'Montserrat', sans-serif;
            background: var(--card-bg);
            color: var(--text-dark);
            transition: all 0.3s ease;
        }
        
        .campo select:focus {
            outline: none;
            border-color: var(--deep-crimson);
            box-shadow: 0 0 0 3px rgba(113,10,20,0.1);
        }
        
        .campo select:disabled {
            background: var(--light-bg);
            cursor: not-allowed;
            opacity: 0.7;
        }
        
        .loading-indicator {
            display: none;
            text-align: center;
            padding: 10px;
            color: var(--deep-crimson);
            font-size: 13px;
        }
        
        .loading-indicator::after {
            content: '...';
            animation: dots 1.5s steps(4, end) infinite;
        }
        
        @keyframes dots {
            0%, 20% { content: '.'; }
            40% { content: '..'; }
            60%, 100% { content: '...'; }
        }
        
        .acciones-form {
            display: flex;
            gap: 15px;
            margin-top: 25px;
            flex-wrap: wrap;
        }
        
        .btn-guardar {
            background: var(--success);
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-guardar:hover {
            background: #1b5e20;
            transform: translateY(-2px);
        }
        
        .btn-limpiar {
            background: var(--dusty-rose);
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-limpiar:hover {
            background: var(--deep-crimson);
            transform: translateY(-2px);
        }
        
        .btn-volver {
            background: var(--deep-crimson);
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-volver:hover {
            background: var(--dark-red);
            transform: translateY(-2px);
        }
        
        .badge-tutor {
            font-size: 11px;
            padding: 2px 6px;
            border-radius: 12px;
            margin-left: 5px;
        }
        
        .badge-principal {
            background: #2e7d32;
            color: white;
        }
        
        .badge-adicional {
            background: #ff9800;
            color: #1a2a3a;
        }
        
        @media (max-width: 768px) {
            .body-form {
                padding: 20px;
            }
            .acciones-form {
                flex-direction: column;
            }
            .btn-guardar, .btn-limpiar, .btn-volver {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body>
    <div class="caja" style="max-width: 700px; margin: 0 auto; padding: 20px;">
        <div class="contenedor-form">
            <div class="header-form">
                <h1>
                    <i></i>
                    Relación Tutor - Alumno
                </h1>
            </div>
            
            <div class="body-form">
                <div class="info-box">
                    <strong> Instrucciones:</strong><br>
                    1. Seleccione el curso del alumno<br>
                    2. Seleccione el alumno del curso elegido<br>
                    3. Seleccione el tutor (pueden ser tutores principales o con rol adicional)<br>
                    4. Guarde la relación
                </div>
                
                <form action="guardar_alumnoxtutor.php" method="post" id="formRelacion">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    
                    <div class="campo">
                        <label for="txt_curso"> 1. Seleccione el Curso:</label>
                        <select name="txt_curso" id="txt_curso" required>
                            <option value="">-- Seleccione un curso --</option>
                            <?php while($fila_curso = mysqli_fetch_array($res_cursos)): 
                                $turno_texto = ($fila_curso['turno'] == 'M') ? 'Mañana' : 'Tarde';
                            ?>
                                <option value="<?php echo $fila_curso['ID_curso']; ?>">
                                    <?php echo $fila_curso['curso'] . '° "' . $fila_curso['division'] . '" - ' . $turno_texto; ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div class="campo">
                        <label for="txt_alumno"> 2. Seleccione el Alumno:</label>
                        <select name="txt_alumno" id="txt_alumno" required disabled>
                            <option value="">-- Primero seleccione un curso --</option>
                        </select>
                        <div id="loading_alumnos" class="loading-indicator">Cargando alumnos</div>
                    </div>

                    <div class="campo">
                        <label for="txt_tutor"> 3. Seleccione el Tutor:</label>
                        <select name="txt_tutor" id="txt_tutor" required>
                            <option value="">-- Seleccione un tutor --</option>
                            <?php
                            $tutores_mostrados = [];
                            
                            // TUTORES CON ROL ADICIONAL (id_rol=8 en usuario_rol)
                            $tutores_adicionales = mysqli_query($con, "
                                SELECT u.DNI_U, u.Nombre, u.Apellido 
                                FROM usuario u
                                INNER JOIN usuario_rol ur ON u.DNI_U = ur.dni_usuario
                                WHERE ur.id_rol = 8 AND ur.activo = 1 AND u.ID_Estado = 1
                                ORDER BY u.Apellido
                            ");
                            while($t = mysqli_fetch_assoc($tutores_adicionales)){
                                echo '<option value="' . $t['DNI_U'] . '">' . 
                                     htmlspecialchars($t['Apellido'] . ', ' . $t['Nombre']) . 
                                     ' (DNI: ' . $t['DNI_U'] . ') <span class="badge-tutor badge-adicional">Adicional</span></option>';
                                $tutores_mostrados[] = $t['DNI_U'];
                            }
                            
                            // TUTORES CON ROL PRINCIPAL (ID_rol = 8)
                            $tutores_principales = mysqli_query($con, "
                                SELECT DNI_U, Nombre, Apellido FROM usuario 
                                WHERE ID_rol = 8 AND ID_Estado = 1 
                                ORDER BY Apellido
                            ");
                            while($t = mysqli_fetch_assoc($tutores_principales)){
                                if(!in_array($t['DNI_U'], $tutores_mostrados)){
                                    echo '<option value="' . $t['DNI_U'] . '">' . 
                                         htmlspecialchars($t['Apellido'] . ', ' . $t['Nombre']) . 
                                         ' (DNI: ' . $t['DNI_U'] . ') <span class="badge-tutor badge-principal">Principal</span></option>';
                                }
                            }
                            ?>
                        </select>
                    </div>

                    <div class="acciones-form">
                        <button type="submit" class="btn-guardar">
                             Guardar Relación
                        </button>
                        <button type="reset" class="btn-limpiar" id="btnLimpiar">
                             Limpiar Campos
                        </button>
                        <a href="listado_alumnoxtutor.php" class="btn-volver">
                             Ver Listado
                        </a>
                        <a href="../../recursos/panel.php" class="btn-volver">
                             Volver al Panel
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script>
        $(document).ready(function() {
            $('#txt_curso').change(function() {
                var cursoId = $(this).val();
                var $alumnoSelect = $('#txt_alumno');
                var $loading = $('#loading_alumnos');
                
                if(cursoId == '') {
                    $alumnoSelect.html('<option value="">-- Primero seleccione un curso --</option>');
                    $alumnoSelect.prop('disabled', true);
                    $loading.hide();
                    return;
                }
                
                $loading.show();
                $alumnoSelect.prop('disabled', true);
                $alumnoSelect.html('<option value="">Cargando alumnos...</option>');
                
                $.ajax({
                    url: 'ajax_get_alumnos.php',
                    type: 'POST',
                    data: { curso_id: cursoId },
                    dataType: 'json',
                    success: function(response) {
                        $loading.hide();
                        
                        if(response.success) {
                            $alumnoSelect.html('<option value="">-- Seleccione un alumno --</option>');
                            
                            if(response.alumnos.length > 0) {
                                for(var i = 0; i < response.alumnos.length; i++) {
                                    var alumno = response.alumnos[i];
                                    $alumnoSelect.append('<option value="' + alumno.dni + '">' + 
                                        alumno.apellido + ', ' + alumno.nombre + 
                                        ' (DNI: ' + alumno.dni + ')</option>');
                                }
                                $alumnoSelect.prop('disabled', false);
                            } else {
                                $alumnoSelect.html('<option value="">-- No hay alumnos en este curso --</option>');
                                $alumnoSelect.prop('disabled', true);
                            }
                        } else {
                            $alumnoSelect.html('<option value="">-- Error al cargar alumnos --</option>');
                            $alumnoSelect.prop('disabled', true);
                            alert('Error: ' + response.error);
                        }
                    },
                    error: function(xhr, status, error) {
                        $loading.hide();
                        $alumnoSelect.html('<option value="">-- Error al cargar alumnos --</option>');
                        $alumnoSelect.prop('disabled', true);
                        console.error('Error AJAX:', error);
                        alert('Error al cargar los alumnos. Por favor, recargue la página.');
                    }
                });
            });
            
            // Validar antes de enviar
            $('#formRelacion').on('submit', function(e) {
                var curso = $('#txt_curso').val();
                var alumno = $('#txt_alumno').val();
                var tutor = $('#txt_tutor').val();
                
                if(curso == '') {
                    e.preventDefault();
                    alert('⚠️ Debe seleccionar un curso.');
                    return false;
                }
                
                if(alumno == '') {
                    e.preventDefault();
                    alert('⚠️ Debe seleccionar un alumno.');
                    return false;
                }
                
                if(tutor == '') {
                    e.preventDefault();
                    alert('⚠️ Debe seleccionar un tutor.');
                    return false;
                }
                
                if(alumno == tutor) {
                    e.preventDefault();
                    alert('❌ Un alumno no puede ser su propio tutor.');
                    return false;
                }
                
                return true;
            });
            
            // Limpiar campos del formulario
            $('#btnLimpiar').on('click', function(e) {
                e.preventDefault();
                $('#txt_curso').val('').trigger('change');
                $('#txt_tutor').val('');
                $('select').css('border-color', '');
            });
        });
    </script>
</body>
</html>
<?php mysqli_close($con); ?>