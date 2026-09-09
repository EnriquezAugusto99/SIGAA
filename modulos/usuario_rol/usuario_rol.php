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
    echo '<script>alert("No tiene permisos para gestionar roles adicionales"); window.location="../../recursos/panel.php";</script>';
    exit();
}

include '../../recursos/conexion.php';

// Generar token CSRF
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Obtener roles disponibles (excluyendo Estudiante por ahora, se maneja aparte)
$query_roles = "SELECT ID_rol, nom_rol FROM rol ORDER BY nom_rol";
$res_roles = mysqli_query($con, $query_roles);

// Obtener cursos (para el filtro cuando se selecciona Estudiante)
$query_cursos = "SELECT ID_curso, curso, division, turno FROM curso ORDER BY curso, division";
$res_cursos = mysqli_query($con, $query_cursos);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../../recursos/styles.css">
    <title>Asignar Rol Adicional a Usuario</title>
    <style>
        .contenedor-form {
            max-width: 700px;
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
        .info-box.warning {
            background: rgba(255,152,0,0.1);
            border-left-color: #ff9800;
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
        .campo select, .campo input {
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
        .campo select:focus, .campo input:focus {
            outline: none;
            border-color: var(--deep-crimson);
        }
        .campo select:disabled, .campo input:disabled {
            background: var(--light-bg);
            cursor: not-allowed;
            opacity: 0.7;
        }
        .filtros-extra {
            background: #f5f5f5;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: none;
        }
        .filtros-extra.visible {
            display: block;
        }
        .filtros-extra h4 {
            margin: 0 0 10px 0;
            color: #3F070B;
            font-size: 14px;
        }
        .loading {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 2px solid #f3f3f3;
            border-top: 2px solid #710A14;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin-left: 10px;
            vertical-align: middle;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        .badge-rol-principal {
            display: inline-block;
            background: #2e7d32;
            color: white;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 11px;
            margin-left: 8px;
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
        }
        .btn-guardar:hover {
            background: #1b5e20;
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
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s ease;
        }
.btn-cargar {
    background: #2196F3;
    color: white;
    border: none;
    padding: 8px 16px;
    border-radius: 6px;
    cursor: pointer;
    font-size: 13px;
    font-weight: 600;
    transition: all 0.3s ease;
    margin-top: 5px;
}
.btn-cargar:hover {
    background: #1976D2;
    transform: translateY(-1px);
}

        .btn-volver:hover {
            background: var(--dark-red);
            transform: translateY(-2px);
        }
        @media (max-width: 768px) {
            .body-form {
                padding: 20px;
            }
            .acciones-form {
                flex-direction: column;
            }
            .btn-guardar, .btn-volver {
                width: 100%;
                text-align: center;
            }
        }
    </style>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body>
    <div class="caja" style="max-width: 750px; margin: 0 auto; padding: 20px;">
        <div class="contenedor-form">
            <div class="header-form">
                <h1>
                    <i>👥</i>
                    Asignar Rol Adicional a Usuario
                </h1>
            </div>
            
            <div class="body-form">
                <div class="info-box">
                    <strong>ℹ️ Instrucciones:</strong><br>
                    1. Seleccione el rol adicional que desea asignar<br>
                    2. Filtre los usuarios (por rol y curso si es estudiante)<br>
                    3. Seleccione el usuario<br>
                    4. Guarde la asignación
                </div>
                
                <div class="info-box warning">
                    <strong>⚠️ Importante:</strong><br>
                    - Un usuario puede tener múltiples roles adicionales<br>
                    - El rol principal se mantiene en la tabla "usuario"<br>
                    - Al asignar un rol adicional, el campo "tiene_multiples_roles" se activa automáticamente
                </div>
                
                <form action="guardar_usuario_rol.php" method="post" id="formAsignacion">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    
                    <div class="campo">
                        <label for="txt_rol">🎭 1. Seleccione el Rol Adicional:</label>
                        <select name="txt_rol" id="txt_rol" required>
                            <option value="">-- Seleccione un rol --</option>
                            <?php while($r = mysqli_fetch_assoc($res_roles)): ?>
                                <option value="<?php echo $r['ID_rol']; ?>">
                                    <?php echo $r['nom_rol']; ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <!-- Filtros adicionales (se muestran según el rol seleccionado) -->
                    <div id="filtrosAdicionales" class="filtros-extra">
                        <h4>🔍 Filtros para cargar usuarios</h4>
                        <div id="contenidoFiltros"></div>
                    </div>
                    
                    <div class="campo">
                        <label for="txt_usuario">👤 2. Seleccione el Usuario:</label>
                        <select name="txt_usuario" id="txt_usuario" required disabled>
                            <option value="">-- Primero seleccione un rol y aplique filtros --</option>
                        </select>
                        <div id="loadingUsuarios" style="display: none; margin-top: 5px; color: #710A14; font-size: 12px;">
                            Cargando usuarios <span class="loading"></span>
                        </div>
                    </div>

                    <div class="acciones-form">
                        <button type="submit" class="btn-guardar">💾 Guardar Asignación</button>
                        <a href="listado_usuario_rol.php" class="btn-volver">📋 Ver Listado</a>
                        <a href="../../recursos/panel.php" class="btn-volver">⬅️ Volver al Panel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script>
        $(document).ready(function() {
            let rolSeleccionado = '';
            
            // Cuando cambia el rol seleccionado
            $('#txt_rol').change(function() {
                rolSeleccionado = $(this).val();
                const rolNombre = $(this).find('option:selected').text();
                
                if(!rolSeleccionado){
                    $('#filtrosAdicionales').removeClass('visible');
                    $('#txt_usuario').prop('disabled', true).html('<option value="">-- Primero seleccione un rol --</option>');
                    return;
                }
                
                // Limpiar select de usuarios
                $('#txt_usuario').prop('disabled', true).html('<option value="">Cargando opciones...</option>');
                
                // Mostrar filtros según el rol
                let htmlFiltros = '';
                
                if(rolNombre === 'Estudiante'){
                    // Filtro por curso
                    htmlFiltros = `
                        <div class="campo" style="margin-bottom: 10px;">
                            <label for="filtro_curso">📚 Filtrar por Curso:</label>
                            <select id="filtro_curso">
                                <option value="">-- Todos los cursos --</option>
                                <?php while($c = mysqli_fetch_assoc($res_cursos)): 
                                    $turno_texto = ($c['turno'] == 'M') ? 'Mañana' : 'Tarde';
                                ?>
                                    <option value="<?php echo $c['ID_curso']; ?>">
                                        <?php echo $c['curso'] . '° "' . $c['division'] . '" - ' . $turno_texto; ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <button type="button" id="btnCargarUsuarios" class="btn-cargar">🔍 Cargar Usuarios</button>
                    `;
                } else {
                    // Para otros roles (Admin, Profesor, Preceptor, etc.) no hay filtro adicional
                    htmlFiltros = `
                        <button type="button" id="btnCargarUsuarios" class="btn-cargar">🔍 Cargar Usuarios</button>
                    `;
                }
                
                $('#contenidoFiltros').html(htmlFiltros);
                $('#filtrosAdicionales').addClass('visible');
                
                // Evento del botón de carga
                $('#btnCargarUsuarios').off('click').on('click', function() {
                    cargarUsuarios();
                });
            });
            
            function cargarUsuarios() {
                const rolId = $('#txt_rol').val();
                const rolNombre = $('#txt_rol').find('option:selected').text();
                const cursoId = $('#filtro_curso').val();
                const $selectUsuarios = $('#txt_usuario');
                const $loading = $('#loadingUsuarios');
                
                if(!rolId){
                    alert('Primero seleccione un rol');
                    return;
                }
                
                $loading.show();
                $selectUsuarios.prop('disabled', true).html('<option value="">Cargando usuarios...</option>');
                
                let data = {
                    rol_id: rolId,
                    rol_nombre: rolNombre
                };
                
                if(rolNombre === 'Estudiante' && cursoId){
                    data.curso_id = cursoId;
                }
                
                $.ajax({
                    url: 'ajax_get_usuarios_por_rol.php',
                    type: 'POST',
                    data: data,
                    dataType: 'json',
                    success: function(response) {
                        $loading.hide();
                        
                        if(response.success){
                            $selectUsuarios.html('<option value="">-- Seleccione un usuario --</option>');
                            
                            if(response.usuarios.length > 0){
                                for(var i = 0; i < response.usuarios.length; i++){
                                    var u = response.usuarios[i];
                                    var extra = u.rol_principal ? ' <span class="badge-rol-principal">' + u.rol_principal + '</span>' : '';
                                    $selectUsuarios.append('<option value="' + u.dni + '">' + 
                                        u.apellido + ', ' + u.nombre + ' (DNI: ' + u.dni + ')' + extra + 
                                        '</option>');
                                }
                                $selectUsuarios.prop('disabled', false);
                            } else {
                                $selectUsuarios.html('<option value="">-- No se encontraron usuarios --</option>');
                                $selectUsuarios.prop('disabled', true);
                            }
                        } else {
                            $selectUsuarios.html('<option value="">-- Error al cargar usuarios --</option>');
                            $selectUsuarios.prop('disabled', true);
                            alert('Error: ' + response.error);
                        }
                    },
                    error: function(xhr, status, error) {
                        $loading.hide();
                        $selectUsuarios.html('<option value="">-- Error al cargar usuarios --</option>');
                        $selectUsuarios.prop('disabled', true);
                        console.error('Error AJAX:', error);
                        alert('Error al cargar usuarios. Por favor, recargue la página.');
                    }
                });
            }
            
            // Validar antes de enviar
            $('#formAsignacion').on('submit', function(e) {
                const usuario = $('#txt_usuario').val();
                const rol = $('#txt_rol').val();
                
                if(!rol){
                    e.preventDefault();
                    alert('⚠️ Debe seleccionar un rol.');
                    return false;
                }
                if(!usuario){
                    e.preventDefault();
                    alert('⚠️ Debe seleccionar un usuario.');
                    return false;
                }
                return true;
            });
        });
    </script>
</body>
</html>
<?php mysqli_close($con); ?>