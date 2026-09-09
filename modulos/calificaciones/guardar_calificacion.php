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

    // Verificar permisos
    if($_SESSION['rol'] != 'Admin' && $_SESSION['rol'] != 'Preceptor' && $_SESSION['rol'] != 'Secretario' && $_SESSION['rol'] != 'Profesor'){
        echo '<script>alert("No tiene permisos para gestionar calificaciones"); window.location="../../recursos/panel.php";</script>';
        exit();
    }

    // Verificar CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $_SESSION = array();
        session_destroy();
        header("Location: ../../index.php");
        exit();
    }

    $alumno = $_POST['txt_alumno'];
    $materia = $_POST['txt_materia'];
    $docente = $_POST['txt_docente'];
    $curso = $_POST['txt_curso'];
    $nota = $_POST['txt_nota'];
    $tipo_evaluacion = $_POST['txt_tipo_evaluacion'];
    $fecha = $_POST['txt_fecha'];

    // 1. Validar que se enviaron todos los valores
    if(empty($alumno) || empty($materia) || empty($docente) || empty($curso) || empty($nota) || empty($tipo_evaluacion) || empty($fecha)){
        echo '<script>alert("ERROR: Todos los campos son requeridos"); history.go(-1);</script>';
        exit();
    }

    // 2. Validar que sean números los campos correspondientes
    if(!is_numeric($alumno) || !is_numeric($materia) || !is_numeric($docente) || !is_numeric($curso)){
        echo '<script>alert("ERROR: Los datos enviados no son válidos"); history.go(-1);</script>';
        exit();
    }

    // 3. Validar rango de nota
    $nota_num = floatval($nota);
    if($nota_num < 1 || $nota_num > 10){
        echo '<script>alert("ERROR: La nota debe estar entre 1 y 10"); history.go(-1);</script>';
        exit();
    }

    // 4. Validar formato de fecha
    if(!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)){
        echo '<script>alert("ERROR: Formato de fecha inválido"); history.go(-1);</script>';
        exit();
    }

    include '../../recursos/conexion.php';

    // 5. Validar que el alumno exista y esté en el curso seleccionado
    $query_verificar_alumno = "SELECT DNI_U, Nombre, Apellido FROM usuario WHERE DNI_U = '$alumno' AND id_curso = '$curso' AND ID_rol = 3 AND ID_Estado = 1";
    $res_verificar_alumno = mysqli_query($con, $query_verificar_alumno);
    if(mysqli_num_rows($res_verificar_alumno) == 0){
        echo '<script>alert("ERROR: El alumno seleccionado no existe, no está activo o no pertenece a este curso"); history.go(-1);</script>';
        exit();
    }
    $fila_alumno = mysqli_fetch_array($res_verificar_alumno);
    $nombre_alumno = $fila_alumno['Apellido'] . ', ' . $fila_alumno['Nombre'];

    // 6. Validar que la materia exista y pertenezca al curso
    $query_verificar_materia = "SELECT ID_materia, Nom_materia, id_curso FROM materia WHERE ID_materia = '$materia' AND id_curso = '$curso'";
    $res_verificar_materia = mysqli_query($con, $query_verificar_materia);
    if(mysqli_num_rows($res_verificar_materia) == 0){
        echo '<script>alert("ERROR: La materia seleccionada no existe o no pertenece a este curso"); history.go(-1);</script>';
        exit();
    }
    $fila_materia = mysqli_fetch_array($res_verificar_materia);
    $nombre_materia = $fila_materia['Nom_materia'];

    // 7. Validar que el docente exista, sea docente y esté asignado a esa materia en ese curso
    $query_verificar_docente = "SELECT usuario.DNI_U, usuario.Nombre, usuario.Apellido FROM usuario INNER JOIN docentemateriacurso ON usuario.DNI_U = docentemateriacurso.id_docente WHERE usuario.DNI_U = '$docente' AND usuario.ID_rol = 2 AND usuario.ID_Estado = 1 AND docentemateriacurso.id_materia = '$materia' AND docentemateriacurso.id_curso = '$curso'";
    $res_verificar_docente = mysqli_query($con, $query_verificar_docente);
    if(mysqli_num_rows($res_verificar_docente) == 0){
        echo '<script>alert("ERROR: El docente seleccionado no existe, no está activo o no está asignado a esta materia en este curso"); history.go(-1);</script>';
        exit();
    }
    $fila_docente = mysqli_fetch_array($res_verificar_docente);
    $nombre_docente = $fila_docente['Apellido'] . ', ' . $fila_docente['Nombre'];

    // 8. Validar que el curso exista
    $query_verificar_curso = "SELECT ID_curso, curso, division, turno FROM curso WHERE ID_curso = '$curso'";
    $res_verificar_curso = mysqli_query($con, $query_verificar_curso);
    if(mysqli_num_rows($res_verificar_curso) == 0){
        echo '<script>alert("ERROR: El curso seleccionado no existe"); history.go(-1);</script>';
        exit();
    }
    $fila_curso = mysqli_fetch_array($res_verificar_curso);
    $turno_texto = $fila_curso['turno'] == 'M' ? 'Mañana' : 'Tarde';
    $nombre_curso = $fila_curso['curso'] . '° "' . $fila_curso['division'] . '" - ' . $turno_texto;

    // 9. Validar que la fecha corresponda a una instancia (trimestre o recuperatorio)
    $query_validar_fecha = "SELECT id_trimestre, trimestre FROM trimestres WHERE '$fecha' BETWEEN fecha_inicio AND fecha_fin LIMIT 1";
    $res_validar_fecha = mysqli_query($con, $query_validar_fecha);
    if(mysqli_num_rows($res_validar_fecha) == 0){
        echo '<script>alert("ERROR: La fecha seleccionada no corresponde a ninguna instancia (trimestre o recuperatorio)"); history.go(-1);</script>';
        exit();
    }
    $fila_fecha = mysqli_fetch_array($res_validar_fecha);
    $id_trimestre = $fila_fecha['id_trimestre'];
    $nombre_trimestre = $fila_fecha['trimestre'];

    // 10. Insertar la calificación
    $query = "INSERT INTO calificaciones (id_alumno, id_materia, id_docente, trimestre, nota, tipo_evaluacion, fecha) VALUES ('$alumno', '$materia', '$docente', '$id_trimestre', '$nota', '" . mysqli_real_escape_string($con, $tipo_evaluacion) . "', '$fecha')";
    $res = mysqli_query($con, $query);

    if($res) {
        echo '<script>alert("Calificación guardada exitosamente"); window.location="listado_calificacion.php";</script>';
        exit();
    } else {
        echo '<script>alert("ERROR: No se pudo guardar la calificación"); history.go(-1);</script>';
    }

    mysqli_close($con);
?>