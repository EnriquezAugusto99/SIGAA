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

// Verificar permisos - Solo Admin e Invitado
$es_admin = ($_SESSION['rol'] == 'Admin');
$es_invitado = ($_SESSION['rol'] == 'Invitado');

if(!$es_admin && !$es_invitado){
    echo '<script>alert("No tiene permisos para acceder a esta sección"); window.location="../../recursos/panel.php";</script>';
    exit();
}

include '../../recursos/conexion.php';

$error = '';
$success = '';

// ============================================
// PROCESAMIENTO DEL FORMULARIO UNIFICADO
// ============================================
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['registrar_tutor'])){
    $dni = trim($_POST['dni']);
    $nombre = trim($_POST['nombre']);
    $apellido = trim($_POST['apellido']);
    $email = trim($_POST['email']);
    $alumnos = $_POST['alumnos'] ?? [];
    
    $errores = [];
    
    // Validaciones básicas
    if(empty($dni) || !is_numeric($dni) || strlen($dni) < 7){
        $errores[] = "El DNI es obligatorio y debe tener al menos 7 dígitos.";
    }
    if(empty($nombre)) $errores[] = "El nombre es obligatorio.";
    if(empty($apellido)) $errores[] = "El apellido es obligatorio.";
    if(!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)){
        $errores[] = "El formato del email no es válido.";
    }
    
    // Limpiar alumnos vacíos
    $alumnos = array_filter($alumnos, function($val) {
        return !empty($val);
    });
    
    if(empty($alumnos)){
        $errores[] = "Debe asignar al menos un alumno al tutor.";
    }
    
    // Verificar si el DNI del tutor ya existe
    if(!empty($dni)){
        $query_check = "SELECT DNI_U FROM usuario WHERE DNI_U = '$dni'";
        $res_check = mysqli_query($con, $query_check);
        if(mysqli_num_rows($res_check) > 0){
            $errores[] = "El DNI $dni ya está registrado en el sistema.";
        }
    }
    
    if(empty($errores)){
        // Iniciar transacción para asegurar que se guarde todo o nada
        mysqli_begin_transaction($con);
        
        try {
            // 1. Insertar el Tutor en la tabla usuario
            $id_estado = 1; // Activo
            $id_rol = 8;    // Rol Tutor
            $clave = $dni;  // Clave por defecto el DNI
            $id_curso = 0;  // Sin curso
            $tiene_multiples = 0;
            
            $query_insert_tutor = "INSERT INTO usuario (DNI_U, Nombre, Apellido, ID_Estado, email, ID_rol, clave, id_curso, tiene_multiples_roles) 
                                   VALUES ('$dni', '$nombre', '$apellido', '$id_estado', '$email', '$id_rol', '$clave', '$id_curso', '$tiene_multiples')";
            
            if(!mysqli_query($con, $query_insert_tutor)){
                throw new Exception("Error al registrar el tutor: " . mysqli_error($con));
            }
            
            // 2. Insertar las relaciones Alumno-Tutor
            $asignados = 0;
            foreach($alumnos as $alumno_dni){
                // Verificar que el alumno existe y es estudiante
                $query_check_alumno = "SELECT DNI_U FROM usuario WHERE DNI_U = '$alumno_dni' AND ID_rol = 3 AND ID_Estado = 1";
                $res_check_alumno = mysqli_query($con, $query_check_alumno);
                
                if(mysqli_num_rows($res_check_alumno) > 0){
                    $query_insert_rel = "INSERT INTO alumnoxtutor (id_alumno, id_tutor) VALUES ('$alumno_dni', '$dni')";
                    if(mysqli_query($con, $query_insert_rel)){
                        $asignados++;
                    }
                }
            }
            
            if($asignados > 0){
                mysqli_commit($con); // Confirmar cambios
                $success = "Tutor registrado exitosamente con $asignados alumno(s) asignado(s).";
                // Limpiar POST para que no se autocompleten los campos
                $_POST = array(); 
            } else {
                throw new Exception("No se pudo validar ningún alumno para asignar.");
            }
            
        } catch (Exception $e) {
            mysqli_rollback($con); // Revertir cambios si algo falló
            $error = $e->getMessage();
        }
    } else {
        $error = implode("<br>", $errores);
    }
}

// Obtener cursos para enviarlos al JavaScript y armar los selects dinámicos
$query_cursos = "SELECT ID_curso, curso, division, turno FROM curso ORDER BY curso, division";
$res_cursos = mysqli_query($con, $query_cursos);
$cursos = [];
while($row = mysqli_fetch_assoc($res_cursos)){
    $cursos[] = $row;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <title>Registro de Tutores</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Montserrat', sans-serif; background: #f5f5f5; padding: 20px; }
        .container { max-width: 800px; margin: 0 auto; }
        .header { background: linear-gradient(135deg, #3F070B 0%, #710A14 100%); border-radius: 12px; padding: 25px 30px; margin-bottom: 30px; color: white; }
        .header h1 { font-size: 24px; margin-bottom: 8px; display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }
        .header p { opacity: 0.9; font-size: 14px; }
        .card { background: white; border-radius: 12px; padding: 25px; margin-bottom: 30px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
        .card h2 { font-size: 18px; color: #3F070B; margin-bottom: 20px; border-left: 4px solid #710A14; padding-left: 15px; }
        .form-group { margin-bottom: 18px; }
        .form-group label { font-weight: 600; color: #333; font-size: 14px; display: block; margin-bottom: 6px; }
        .form-group label i { color: #710A14; margin-right: 6px; }
        .form-group input, .form-group select { width: 100%; padding: 12px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 14px; font-family: 'Montserrat', sans-serif; transition: all 0.3s ease; }
        .form-group input:focus, .form-group select:focus { outline: none; border-color: #710A14; box-shadow: 0 0 0 3px rgba(113,10,20,0.1); }
        .form-row { display: flex; gap: 20px; flex-wrap: wrap; }
        .form-row .form-group { flex: 1; min-width: 200px; }
        .btn-primary { background: #710A14; color: white; border: none; padding: 12px 24px; border-radius: 8px; font-weight: 600; cursor: pointer; transition: all 0.3s ease; display: inline-flex; align-items: center; gap: 8px; }
        .btn-primary:hover { background: #3F070B; transform: translateY(-2px); }
        .btn-secondary { background: #666; color: white; border: none; padding: 12px 24px; border-radius: 8px; font-weight: 600; cursor: pointer; transition: all 0.3s ease; display: inline-flex; align-items: center; gap: 8px; text-decoration: none; }
        .btn-secondary:hover { background: #555; transform: translateY(-2px); }
        .btn-group { display: flex; gap: 15px; margin-top: 20px; flex-wrap: wrap; }
        .mensaje-exito, .mensaje-error { padding: 12px 15px; border-radius: 8px; margin-bottom: 20px; }
        .mensaje-exito { background: #d4edda; color: #155724; border-left: 4px solid #28a745; }
        .mensaje-error { background: #f8d7da; color: #721c24; border-left: 4px solid #dc3545; }
        .hijos-container { margin-top: 20px; padding: 20px; background: #f8f9fa; border-radius: 8px; border: 2px dashed #ddd; display: none; }
        .hijo-item { background: white; padding: 15px; border-radius: 8px; margin-bottom: 15px; border: 1px solid #e0e0e0; }
        .hijo-item .form-row { gap: 10px; }
        .hijo-item .form-group { flex: 1; min-width: 150px; }
        .hijo-item .form-group select { padding: 10px; font-size: 13px; }
        .badge-role { background: #ff9800; color: white; padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: bold; margin-left: 10px; }
        .info-text { background: #e8f0fe; padding: 12px 15px; border-radius: 8px; font-size: 13px; color: #3F070B; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; }
        .info-text i { font-size: 18px; }
        
        @media (max-width: 768px) {
            .container { padding: 0; }
            .form-row { flex-direction: column; gap: 0; }
            .form-row .form-group { min-width: 100%; }
            .btn-group { flex-direction: column; }
            .btn-group .btn-primary, .btn-group .btn-secondary { width: 100%; justify-content: center; }
            .hijo-item .form-row { flex-direction: column; gap: 0; }
            .hijo-item .form-group { min-width: 100%; }
        }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1>
            <i></i> Registro de Tutores
            <?php if($es_admin): ?>
                <span class="badge-role">Administrador</span>
            <?php else: ?>
                <span class="badge-role">Mesa de Entrada</span>
            <?php endif; ?>
        </h1>
    </div>

    <?php if($error): ?>
        <div class="mensaje-error"><?= $error ?></div>
    <?php endif; ?>
    <?php if($success): ?>
        <div class="mensaje-exito"><?= $success ?></div>
    <?php endif; ?>

    <div class="card">

        <form method="POST" action="" id="formTutorUnificado">
            <input type="hidden" name="registrar_tutor" value="1">
            
            <h2><i></i> Datos Personales del Tutor</h2>
            
            <div class="form-row">
                <div class="form-group">
                    <label><i class="fas fa-id-card"></i> DNI *</label>
                    <input type="number" name="dni" id="dni" required placeholder="DNI del tutor" min="1000000" max="99999999" value="<?= isset($_POST['dni']) ? htmlspecialchars($_POST['dni']) : '' ?>">
                </div>
                <div class="form-group">
                    <label><i class="fas fa-user"></i> Nombre *</label>
                    <input type="text" name="nombre" id="nombre" required placeholder="Nombre del tutor" value="<?= isset($_POST['nombre']) ? htmlspecialchars($_POST['nombre']) : '' ?>">
                </div>
                <div class="form-group">
                    <label><i class="fas fa-user"></i> Apellido *</label>
                    <input type="text" name="apellido" id="apellido" required placeholder="Apellido del tutor" value="<?= isset($_POST['apellido']) ? htmlspecialchars($_POST['apellido']) : '' ?>">
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label><i class="fas fa-envelope"></i> Email</label>
                    <input type="email" name="email" id="email" placeholder="email@ejemplo.com" value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>">
                </div>
                <div class="form-group">
                    <label><i class="fas fa-child"></i> Cantidad de hijos *</label>
                    <select name="cantidad_hijos" id="cantidad_hijos" required>
                        <option value="0">-- Seleccione --</option>
                        <?php for($i = 1; $i <= 10; $i++): ?>
                            <option value="<?= $i ?>" <?= (isset($_POST['cantidad_hijos']) && $_POST['cantidad_hijos'] == $i) ? 'selected' : '' ?>><?= $i ?> hijo(s)</option>
                        <?php endfor; ?>
                    </select>
                </div>
            </div>

            <div id="hijos-container" class="hijos-container">
                </div>
            
            <div class="btn-group" style="margin-top: 30px;">
                <button type="submit" class="btn-primary" id="btnRegistrar">
                    <i class="fas fa-save"></i> Registrar Tutor y Asignar
                </button>
                <a href="listado_tutores.php" class="btn-secondary">
                    <i class="fas fa-list"></i> Ver Listado
                </a>
                <a href="../../recursos/panel.php" class="btn-secondary">
                    <i class="fas fa-arrow-left"></i> Volver al Panel
                </a>
            </div>
        </form>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
// Pasar los cursos desde PHP a JavaScript
const cursosDisponibles = <?= json_encode($cursos) ?>;

$(document).ready(function() {
    
    // ============================================
    // GENERAR CAMPOS DE HIJOS DINÁMICAMENTE
    // ============================================
    function generarCamposHijos(cantidad) {
        const contenedor = $('#hijos-container');
        contenedor.empty(); 
        
        if (cantidad > 0) {
            contenedor.show();
            
            // Construir options de cursos
            let cursoOptions = '<option value="">-- Seleccione curso --</option>';
            cursosDisponibles.forEach(function(c) {
                let turnoStr = (c.turno === 'M') ? 'Mañana' : 'Tarde';
                cursoOptions += `<option value="${c.ID_curso}">${c.curso}° "${c.division}" - ${turnoStr}</option>`;
            });

            // Generar los bloques de acuerdo a la cantidad
            for (let i = 1; i <= cantidad; i++) {
                let htmlBlock = `
                <div class="hijo-item">
                    <h4 style="margin-bottom: 10px; color: #710A14;">Asignación Hijo ${i}</h4>
                    <div class="form-row">
                        <div class="form-group">
                            <label><i class="fas fa-school"></i> Curso</label>
                            <select class="select-curso" data-index="${i}" required>
                                ${cursoOptions}
                            </select>
                        </div>
                        <div class="form-group">
                            <label><i class="fas fa-user-graduate"></i> Alumno</label>
                            <select name="alumnos[]" class="select-alumno" data-index="${i}" required disabled>
                                <option value="">-- Primero seleccione curso --</option>
                            </select>
                        </div>
                    </div>
                </div>`;
                
                contenedor.append(htmlBlock);
            }
        } else {
            contenedor.hide();
        }
    }

    // Disparar al cambiar el selector
    $('#cantidad_hijos').change(function() {
        generarCamposHijos(parseInt($(this).val()));
    });

    // Disparar al cargar la página (por si recargó por un error y ya había hijos seleccionados)
    if($('#cantidad_hijos').val() > 0) {
        generarCamposHijos(parseInt($('#cantidad_hijos').val()));
    }

    // ============================================
    // CARGAR ALUMNOS POR CURSO (AJAX CON DELEGACIÓN)
    // ============================================
    $(document).on('change', '.select-curso', function() {
        var cursoId = $(this).val();
        var index = $(this).data('index');
        var $alumnoSelect = $('.select-alumno[data-index="' + index + '"]');
        
        if(cursoId) {
            $alumnoSelect.prop('disabled', true).html('<option value="">Cargando alumnos...</option>');
            
            $.ajax({
                url: 'ajax_invitado.php',
                type: 'POST',
                data: { action: 'get_alumnos_curso', curso_id: cursoId },
                dataType: 'json',
                success: function(response) {
                    if(response.success) {
                        $alumnoSelect.html('<option value="">-- Seleccione un alumno --</option>');
                        $.each(response.alumnos, function(i, alumno) {
                            $alumnoSelect.append('<option value="' + alumno.DNI_U + '">' + alumno.Apellido + ', ' + alumno.Nombre + '</option>');
                        });
                        $alumnoSelect.prop('disabled', false);
                    } else {
                        $alumnoSelect.html('<option value="">' + response.message + '</option>');
                        $alumnoSelect.prop('disabled', true);
                    }
                },
                error: function() {
                    $alumnoSelect.html('<option value="">Error al cargar alumnos</option>');
                    $alumnoSelect.prop('disabled', true);
                }
            });
        } else {
            $alumnoSelect.prop('disabled', true).html('<option value="">-- Primero seleccione curso --</option>');
        }
    });
    
    // ============================================
    // VALIDAR FORMULARIO ANTES DE ENVIAR
    // ============================================
    $('#formTutorUnificado').submit(function(e) {
        var cantidad = $('#cantidad_hijos').val();
        if(cantidad == "0") {
            alert('Por favor, indique la cantidad de hijos.');
            e.preventDefault();
            return false;
        }

        // Bloquear el botón para evitar doble submit
        $('#btnRegistrar').prop('disabled', true).html('<i class="fas fa-spinner fa-pulse"></i> Registrando...');
        return true;
    });
});
</script>
</body>
</html>
<?php mysqli_close($con); ?>