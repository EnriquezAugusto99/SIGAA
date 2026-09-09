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

// Verificar permisos
if($_SESSION['rol'] != 'Admin' && $_SESSION['rol'] != 'Preceptor' && $_SESSION['rol'] != 'Profesor'){
    echo '<script>alert("No tiene permisos para ver materias"); window.location="../../recursos/panel.php";</script>';
    exit();
}

include '../../recursos/conexion.php';

$es_preceptor = ($_SESSION['rol'] == 'Preceptor');

//PARA PRECEPTOR 
if($es_preceptor){
    $preceptor_dni = $_SESSION["dni"];
    
    // Obtener cursos del preceptor
    $cursos_preceptor = [];
    $q_cursos = "SELECT c.ID_curso, c.curso, c.division, c.turno
                 FROM curso c 
                 INNER JOIN preceptorxcurso pxc ON c.ID_curso = pxc.id_curso
                 WHERE pxc.id_preceptor = '$preceptor_dni'
                 ORDER BY c.curso, c.division";
    $res_cp = mysqli_query($con, $q_cursos);
    while($f = mysqli_fetch_array($res_cp)){
        $cursos_preceptor[] = $f;
    }
    
    $curso_seleccionado = $_GET['curso_id'] ?? '';
    $buscar_rapido = $_GET['buscar_rapido'] ?? '';
    
    // Validar que el curso pertenezca al preceptor
    $curso_valido = false;
    $curso_info_sel = null;
    foreach($cursos_preceptor as $cp){
        if($cp['ID_curso'] == $curso_seleccionado){
            $curso_valido = true;
            $curso_info_sel = $cp;
            break;
        }
    }
    
    // Obtener materias del curso elegido (si es válido)
    $materias_curso = [];
    if($curso_valido){
        $where_mat = "WHERE m.id_curso = '$curso_seleccionado'";
        if(!empty($buscar_rapido)){
            $buscar_rapido_esc = mysqli_real_escape_string($con, $buscar_rapido);
            $where_mat .= " AND LOWER(m.Nom_materia) LIKE LOWER('%$buscar_rapido_esc%')";
        }
        $q_mat = "SELECT m.*, 
                 (SELECT COUNT(*) FROM docentemateriacurso WHERE id_materia=m.ID_materia AND id_curso='$curso_seleccionado') as total_docentes,
                 (SELECT COUNT(*) FROM calificaciones WHERE id_materia=m.ID_materia) as total_calificaciones
          FROM materia m
          $where_mat AND m.Nom_materia NOT LIKE '%Taller%'
          ORDER BY m.Nom_materia";
        $res_mat = mysqli_query($con, $q_mat);
        while($f = mysqli_fetch_array($res_mat)){
            $materias_curso[] = $f;
        }
    }
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Materias - Preceptor</title>
        <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&display=swap" rel="stylesheet">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
        <style>
            :root {
                --dark-red: #3F070B;
                --deep-crimson: #710A14;
                --dark-burgundy: #180605;
                --dusty-rose: #818582;
                --rustic-red: #8F3C45;
                --light-bg: #f5f5f5;
                --card-bg: #ffffff;
                --border-color: #e0e0e0;
                --text-dark: #2c2c2c;
                --text-muted: #666666;
            }

            * {
                margin: 0;
                padding: 0;
                box-sizing: border-box;
            }

            body {
                font-family: 'Montserrat', sans-serif;
                background: var(--light-bg);
                min-height: 100vh;
                padding: 30px 20px;
            }

            .container {
                max-width: 1400px;
                margin: 0 auto;
            }

            /* Header */
            .header {
                background: var(--card-bg);
                border-radius: 12px;
                padding: 25px 30px;
                margin-bottom: 30px;
                box-shadow: 0 4px 15px rgba(0,0,0,0.1);
                border-left: 5px solid var(--dark-red);
            }

            .header h1 {
                font-size: 28px;
                color: var(--dark-burgundy);
                margin-bottom: 8px;
                display: flex;
                align-items: center;
                gap: 12px;
            }

            .header h1 i {
                color: var(--deep-crimson);
                font-size: 32px;
            }

            .header p {
                color: var(--text-muted);
                font-size: 14px;
            }

            /* Sección de cursos */
            .cursos-section {
                background: var(--card-bg);
                border-radius: 12px;
                padding: 25px;
                margin-bottom: 30px;
                box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            }

            .cursos-section h2 {
                font-size: 18px;
                color: var(--dark-burgundy);
                margin-bottom: 20px;
                display: flex;
                align-items: center;
                gap: 10px;
            }

            .cursos-section h2 i {
                color: var(--deep-crimson);
            }

            .cursos-grid {
                display: flex;
                flex-wrap: wrap;
                gap: 15px;
            }

            .curso-card {
                background: var(--light-bg);
                border: 2px solid var(--border-color);
                border-radius: 12px;
                padding: 12px 20px;
                text-decoration: none;
                color: var(--text-dark);
                transition: all 0.3s ease;
                display: flex;
                align-items: center;
                gap: 10px;
                font-weight: 600;
                font-size: 14px;
            }

            .curso-card i {
                font-size: 18px;
                color: var(--deep-crimson);
            }

            .curso-card:hover {
                background: var(--deep-crimson);
                border-color: var(--deep-crimson);
                color: white;
                transform: translateY(-3px);
                box-shadow: 0 6px 15px rgba(113,10,20,0.3);
            }

            .curso-card:hover i {
                color: white;
            }

            .curso-card.active {
                background: var(--dark-red);
                border-color: var(--dark-red);
                color: white;
            }

            .curso-card.active i {
                color: white;
            }

            /* Filtro */
            .filtro-section {
                background: var(--card-bg);
                border-radius: 12px;
                padding: 20px 25px;
                margin-bottom: 30px;
                box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            }

            .filtro-form {
                display: flex;
                flex-wrap: wrap;
                align-items: center;
                gap: 15px;
            }

            .filtro-form label {
                font-weight: 600;
                color: var(--dark-burgundy);
                font-size: 14px;
            }

            .filtro-form input {
                flex: 1;
                min-width: 250px;
                padding: 12px 16px;
                border: 2px solid var(--border-color);
                border-radius: 8px;
                font-size: 14px;
                transition: all 0.3s ease;
                font-family: 'Montserrat', sans-serif;
            }

            .filtro-form input:focus {
                outline: none;
                border-color: var(--deep-crimson);
                box-shadow: 0 0 0 3px rgba(113,10,20,0.1);
            }

            .btn-primary {
                background: var(--deep-crimson);
                color: white;
                border: none;
                padding: 12px 24px;
                border-radius: 8px;
                font-weight: 600;
                cursor: pointer;
                transition: all 0.3s ease;
                font-family: 'Montserrat', sans-serif;
                display: inline-flex;
                align-items: center;
                gap: 8px;
            }

            .btn-primary:hover {
                background: var(--dark-red);
                transform: translateY(-2px);
            }

            .btn-secondary {
                background: var(--dusty-rose);
                color: white;
                border: none;
                padding: 12px 24px;
                border-radius: 8px;
                font-weight: 600;
                cursor: pointer;
                transition: all 0.3s ease;
                font-family: 'Montserrat', sans-serif;
                display: inline-flex;
                align-items: center;
                gap: 8px;
            }

            .btn-secondary:hover {
                background: var(--deep-crimson);
            }

            /* Tabla */
            .table-section {
                background: var(--card-bg);
                border-radius: 12px;
                overflow: hidden;
                box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            }

            .table-header {
                padding: 20px 25px;
                background: var(--light-bg);
                border-bottom: 1px solid var(--border-color);
                display: flex;
                justify-content: space-between;
                align-items: center;
                flex-wrap: wrap;
                gap: 15px;
            }

            .table-header h3 {
                font-size: 18px;
                color: var(--dark-burgundy);
                display: flex;
                align-items: center;
                gap: 10px;
            }

            .badge {
                background: var(--deep-crimson);
                color: white;
                padding: 5px 12px;
                border-radius: 20px;
                font-size: 12px;
                font-weight: 600;
            }

            table {
                width: 100%;
                border-collapse: collapse;
            }

            th {
                text-align: left;
                padding: 16px 20px;
                background: var(--dark-red);
                color: white;
                font-weight: 600;
                font-size: 14px;
            }

            td {
                padding: 16px 20px;
                border-bottom: 1px solid var(--border-color);
                color: var(--text-dark);
                font-size: 14px;
            }

            tr:hover td {
                background: rgba(129,133,130,0.05);
            }

            .btn-edit {
                background: var(--rustic-red);
                color: white;
                border: none;
                padding: 8px 16px;
                border-radius: 6px;
                font-size: 13px;
                font-weight: 600;
                cursor: pointer;
                transition: all 0.3s ease;
                text-decoration: none;
                display: inline-flex;
                align-items: center;
                gap: 6px;
                margin-right: 8px;
            }

            .btn-edit:hover {
                background: var(--deep-crimson);
                transform: translateY(-2px);
            }
            
            .btn-profesores {
                background: var(--deep-crimson);
                color: white;
                border: none;
                padding: 8px 16px;
                border-radius: 6px;
                font-size: 13px;
                font-weight: 600;
                cursor: pointer;
                transition: all 0.3s ease;
                display: inline-flex;
                align-items: center;
                gap: 6px;
            }
            
            .btn-profesores:hover {
                background: var(--dark-red);
                transform: translateY(-2px);
            }

            .btn-delete {
                background: var(--dusty-rose);
                color: white;
                border: none;
                padding: 8px 16px;
                border-radius: 6px;
                font-size: 13px;
                font-weight: 600;
                cursor: pointer;
                transition: all 0.3s ease;
                display: inline-flex;
                align-items: center;
                gap: 6px;
            }

            .btn-delete:hover:not(:disabled) {
                background: var(--deep-crimson);
                transform: translateY(-2px);
            }

            .btn-delete:disabled {
                background: #ccc;
                cursor: not-allowed;
                opacity: 0.6;
            }

            .btn-add {
                background: var(--deep-crimson);
                color: white;
                border: none;
                padding: 12px 24px;
                border-radius: 8px;
                font-weight: 600;
                cursor: pointer;
                transition: all 0.3s ease;
                text-decoration: none;
                display: inline-flex;
                align-items: center;
                gap: 8px;
            }

            .btn-add:hover {
                background: var(--dark-red);
                transform: translateY(-2px);
            }

            .btn-back {
                background: var(--dusty-rose);
                color: white;
                border: none;
                padding: 12px 24px;
                border-radius: 8px;
                font-weight: 600;
                cursor: pointer;
                transition: all 0.3s ease;
                text-decoration: none;
                display: inline-flex;
                align-items: center;
                gap: 8px;
            }

            .btn-back:hover {
                background: var(--deep-crimson);
                transform: translateY(-2px);
            }

            .empty-state {
                text-align: center;
                padding: 60px 20px;
                color: var(--dusty-rose);
            }

            .empty-state i {
                font-size: 48px;
                margin-bottom: 15px;
                color: var(--deep-crimson);
            }

            .actions-bar {
                padding: 20px 25px;
                background: var(--light-bg);
                border-top: 1px solid var(--border-color);
                display: flex;
                gap: 15px;
                flex-wrap: wrap;
            }

            .mensaje-info, .mensaje-error {
                padding: 15px 20px;
                border-radius: 12px;
                margin-bottom: 20px;
                display: flex;
                align-items: center;
                gap: 12px;
            }

            .mensaje-info {
                background: #e8f0fe;
                color: var(--dark-red);
                border-left: 4px solid var(--deep-crimson);
            }

            .mensaje-error {
                background: #fde8e8;
                color: var(--dark-red);
                border-left: 4px solid var(--dark-red);
            }

            .docente-badge {
                background: #f0e6e6;
                color: var(--deep-crimson);
                padding: 4px 10px;
                border-radius: 20px;
                font-size: 12px;
                font-weight: 600;
                cursor: pointer;
                transition: all 0.3s ease;
                display: inline-block;
            }
            
            .docente-badge:hover {
                background: var(--deep-crimson);
                color: white;
            }

            /* Modal Styles */
            .modal {
                display: none;
                position: fixed;
                z-index: 1000;
                left: 0;
                top: 0;
                width: 100%;
                height: 100%;
                background-color: rgba(0,0,0,0.5);
                animation: fadeIn 0.3s ease;
            }
            
            @keyframes fadeIn {
                from { opacity: 0; }
                to { opacity: 1; }
            }
            
            .modal-content {
                background-color: var(--card-bg);
                margin: 50px auto;
                width: 90%;
                max-width: 800px;
                border-radius: 16px;
                box-shadow: 0 20px 60px rgba(0,0,0,0.3);
                animation: slideDown 0.3s ease;
            }
            
            @keyframes slideDown {
                from {
                    transform: translateY(-50px);
                    opacity: 0;
                }
                to {
                    transform: translateY(0);
                    opacity: 1;
                }
            }
            
            .modal-header {
                padding: 20px 25px;
                background: var(--dark-red);
                color: white;
                border-radius: 16px 16px 0 0;
                display: flex;
                justify-content: space-between;
                align-items: center;
            }
            
            .modal-header h3 {
                font-size: 20px;
                display: flex;
                align-items: center;
                gap: 10px;
            }
            
            .close-modal {
                background: none;
                border: none;
                color: white;
                font-size: 28px;
                cursor: pointer;
                transition: transform 0.3s ease;
            }
            
            .close-modal:hover {
                transform: scale(1.1);
            }
            
            .modal-body {
                padding: 25px;
                max-height: 500px;
                overflow-y: auto;
            }
            
            .profesores-lista {
                margin-bottom: 30px;
            }
            
            .profesores-lista h4 {
                color: var(--dark-burgundy);
                margin-bottom: 15px;
                font-size: 16px;
                border-bottom: 2px solid var(--border-color);
                padding-bottom: 8px;
            }
            
            .profesor-item {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 12px 15px;
                background: var(--light-bg);
                border-radius: 8px;
                margin-bottom: 8px;
            }
            
            .profesor-info {
                display: flex;
                align-items: center;
                gap: 12px;
            }
            
            .profesor-info i {
                color: var(--deep-crimson);
                font-size: 18px;
            }
            
            .btn-remove {
                background: var(--dusty-rose);
                color: white;
                border: none;
                padding: 6px 12px;
                border-radius: 6px;
                cursor: pointer;
                font-size: 12px;
                transition: all 0.3s ease;
            }
            
            .btn-remove:hover {
                background: var(--deep-crimson);
            }
            
            .add-profesor {
                margin-top: 20px;
                padding-top: 20px;
                border-top: 2px solid var(--border-color);
            }
            
            .add-profesor h4 {
                color: var(--dark-burgundy);
                margin-bottom: 15px;
                font-size: 16px;
            }
            
            .select-profesor {
                display: flex;
                gap: 10px;
                flex-wrap: wrap;
            }
            
            .select-profesor select {
                flex: 1;
                padding: 12px;
                border: 2px solid var(--border-color);
                border-radius: 8px;
                font-family: 'Montserrat', sans-serif;
                font-size: 14px;
            }
            
            .select-profesor select:focus {
                outline: none;
                border-color: var(--deep-crimson);
            }
            
            .loading {
                text-align: center;
                padding: 40px;
                color: var(--dusty-rose);
            }
            
            .loading i {
                font-size: 40px;
                margin-bottom: 10px;
            }
            
            .toast {
                position: fixed;
                bottom: 30px;
                right: 30px;
                background: var(--deep-crimson);
                color: white;
                padding: 12px 20px;
                border-radius: 8px;
                display: none;
                align-items: center;
                gap: 10px;
                z-index: 1001;
                animation: slideIn 0.3s ease;
            }
            
            @keyframes slideIn {
                from {
                    transform: translateX(100%);
                    opacity: 0;
                }
                to {
                    transform: translateX(0);
                    opacity: 1;
                }
            }
            
            .toast-success {
                background: #28a745;
            }
            
            .toast-error {
                background: var(--deep-crimson);
            }

            @media (max-width: 768px) {
                body {
                    padding: 15px;
                }

                th, td {
                    padding: 12px;
                }

                .filtro-form {
                    flex-direction: column;
                    align-items: stretch;
                }

                .filtro-form input {
                    width: 100%;
                }

                .curso-card {
                    padding: 8px 15px;
                    font-size: 12px;
                }
                
                .modal-content {
                    width: 95%;
                    margin: 20px auto;
                }
            }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h1>
                    <i class="fas fa-chalkboard-teacher"></i>
                    Gestión de Materias
                </h1>
                <p><i class="fas fa-graduation-cap"></i> Visualiza y gestiona las materias de tus cursos asignados</p>
            </div>
            
            <?php if(empty($cursos_preceptor)): ?>
                <div class="mensaje-error">
                    <i class="fas fa-exclamation-triangle"></i>
                    No tiene cursos asignados. Contacte al administrador.
                </div>
            <?php else: ?>
                <div class="cursos-section">
                    <h2>
                        <i class="fas fa-school"></i>
                        Mis Cursos
                    </h2>
                    <div class="cursos-grid">
                        <?php foreach($cursos_preceptor as $curso):
                            $turno_texto = ($curso['turno'] == 'M') ? 'Mañana' : 'Tarde';
                            $curso_nombre = $curso['curso'] . '° "' . $curso['division'] . '" - ' . $turno_texto;
                            $active = ($curso_seleccionado == $curso['ID_curso']) ? 'active' : '';
                        ?>
                            <a href="listado_materia.php?curso_id=<?= $curso['ID_curso'] ?>" class="curso-card <?= $active ?>">
                                <i class="fas fa-book-open"></i>
                                <span><?= htmlspecialchars($curso_nombre) ?></span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <?php if($curso_valido && $curso_info_sel): ?>
                    <div class="filtro-section">
                        <form method="GET" action="" class="filtro-form">
                            <input type="hidden" name="curso_id" value="<?= $curso_seleccionado ?>">
                            <i class="fas fa-search" style="color: var(--dusty-rose);"></i>
                            <input type="text" name="buscar_rapido" placeholder="Buscar materia por nombre..." value="<?= htmlspecialchars($buscar_rapido) ?>">
                            <button type="submit" class="btn-primary">
                                <i class="fas fa-search"></i> Buscar
                            </button>
                            <?php if(!empty($buscar_rapido)): ?>
                                <a href="listado_materia.php?curso_id=<?= $curso_seleccionado ?>" class="btn-secondary">
                                    <i class="fas fa-times"></i> Limpiar
                                </a>
                            <?php endif; ?>
                        </form>
                    </div>
                    
                    <div class="table-section">
                        <div class="table-header">
                            <h3>
                                <i class="fas fa-list"></i>
                                Materias del Curso
                                <span class="badge"><?= count($materias_curso) ?> materias</span>
                            </h3>
                        </div>
                        
                        <div style="overflow-x: auto;">
                            <table id="tabla-materias">
                                <thead>
                                    <tr>
                                        <th><i class="fas fa-hashtag"></i> ID</th>
                                        <th><i class="fas fa-book"></i> Nombre</th>
                                        <th><i class="fas fa-chalkboard-user"></i> Docentes</th>
                                        <th><i class="fas fa-cogs"></i> Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if(count($materias_curso) > 0):
                                        foreach($materias_curso as $mat): ?>
                                        <tr>
                                            <td><?= $mat['ID_materia'] ?></td>
                                            <td><strong><?= htmlspecialchars($mat['Nom_materia']) ?></strong></td>
                                            <td>
                                                <span class="docente-badge" onclick="abrirModalProfesores(<?= $mat['ID_materia'] ?>, <?= $curso_seleccionado ?>, '<?= htmlspecialchars($mat['Nom_materia']) ?>')">
                                                    <i class="fas fa-users"></i> <?= $mat['total_docentes'] ?> docente(s)
                                                </span>
                                             </div>
                                            </td>
                                            <td>
                                                <button onclick="abrirModalProfesores(<?= $mat['ID_materia'] ?>, <?= $curso_seleccionado ?>, '<?= htmlspecialchars($mat['Nom_materia']) ?>')" class="btn-profesores">
                                                    <i class="fas fa-chalkboard-user"></i> Gestionar Profesores
                                                </button>
                                            </div>
                                            </td>
                                        </tr>
                                        <?php endforeach;
                                    else: ?>
                                        <tr>
                                            <td colspan="4" class="empty-state">
                                                <i class="fas fa-inbox"></i>
                                                <p>No hay materias para este curso</p>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <div class="actions-bar">
                            <a href="../../recursos/panel.php" class="btn-back">
                                <i class="fas fa-arrow-left"></i> Volver al Panel
                            </a>
                        </div>
                    </div>
                <?php elseif($curso_seleccionado && !$curso_valido): ?>
                    <div class="mensaje-error">
                        <i class="fas fa-shield-alt"></i>
                        Curso no válido o no autorizado.
                    </div>
                <?php elseif(!$curso_seleccionado && !empty($cursos_preceptor)): ?>
                    <div class="mensaje-info">
                        <i class="fas fa-info-circle"></i>
                        Seleccione un curso de la lista superior para ver sus materias.
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        
        <!-- Modal para gestionar profesores -->
        <div id="modalProfesores" class="modal">
            <div class="modal-content">
                <div class="modal-header">
                    <h3>
                        <i class="fas fa-chalkboard-user"></i>
                        Gestionar Profesores
                    </h3>
                    <button class="close-modal" onclick="cerrarModal()">&times;</button>
                </div>
                <div class="modal-body" id="modalBody">
                    <div class="loading">
                        <i class="fas fa-spinner fa-pulse"></i>
                        <p>Cargando información...</p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Toast de notificaciones -->
        <div id="toast" class="toast">
            <i class="fas fa-check-circle"></i>
            <span id="toastMessage"></span>
        </div>
        
        <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
        <script>
        let materiaActual = null;
        let cursoActual = null;
        
        function mostrarToast(mensaje, tipo = 'success') {
            const toast = document.getElementById('toast');
            const toastMessage = document.getElementById('toastMessage');
            toastMessage.textContent = mensaje;
            toast.className = `toast toast-${tipo}`;
            toast.style.display = 'flex';
            setTimeout(() => {
                toast.style.display = 'none';
            }, 3000);
        }
        
        function abrirModalProfesores(idMateria, idCurso, nombreMateria) {
            materiaActual = idMateria;
            cursoActual = idCurso;
            const modal = document.getElementById('modalProfesores');
            const modalBody = document.getElementById('modalBody');
            
            modal.style.display = 'block';
            modalBody.innerHTML = '<div class="loading"><i class="fas fa-spinner fa-pulse"></i><p>Cargando profesores...</p></div>';
            
            $.ajax({
                url: 'gestionar_profesores_materia.php',
                type: 'POST',
                data: {
                    action: 'obtener_profesores',
                    id_materia: idMateria,
                    id_curso: idCurso
                },
                dataType: 'json',
                success: function(response) {
                    if(response.success) {
                        let turnoTexto = response.info_materia.turno === 'M' ? 'Mañana' : 'Tarde';
                        let cursoTexto = response.info_materia.curso + '° "' + response.info_materia.division + '" - ' + turnoTexto;
                        
                        let html = `
                            <div style="margin-bottom: 20px; padding: 10px; background: var(--light-bg); border-radius: 8px;">
                                <p><strong>Materia:</strong> ${escapeHtml(response.info_materia.Nom_materia)}</p>
                                <p><strong>Curso:</strong> ${escapeHtml(cursoTexto)}</p>
                            </div>
                            <div class="profesores-lista">
                                <h4><i class="fas fa-user-check"></i> Profesores Asignados (${response.profesores_actuales.length})</h4>
                                <div id="lista-profesores-actuales">
                        `;
                        
                        if(response.profesores_actuales.length > 0) {
                            response.profesores_actuales.forEach(prof => {
                                html += `
                                    <div class="profesor-item" id="prof-item-${prof.id_dmc}">
                                        <div class="profesor-info">
                                            <i class="fas fa-chalkboard-user"></i>
                                            <span><strong>${escapeHtml(prof.Apellido)}</strong>, ${escapeHtml(prof.Nombre)} (DNI: ${prof.DNI_U})</span>
                                        </div>
                                        <button class="btn-remove" onclick="quitarProfesor(${prof.id_dmc}, ${prof.DNI_U})">
                                            <i class="fas fa-trash-alt"></i> Quitar
                                        </button>
                                    </div>
                                `;
                            });
                        } else {
                            html += '<p style="color: var(--dusty-rose); text-align: center; padding: 20px;"><i class="fas fa-info-circle"></i> No hay profesores asignados a esta materia.</p>';
                        }
                        
                        html += `
                                </div>
                            </div>
                            <div class="add-profesor">
                                <h4><i class="fas fa-user-plus"></i> Agregar Profesor</h4>
                                <div class="select-profesor">
                                    <select id="select-profesor">
                                        <option value="">Seleccionar profesor...</option>
                        `;
                        
                        response.profesores_disponibles.forEach(prof => {
                            let yaAsignado = response.profesores_actuales.some(p => p.DNI_U == prof.DNI_U);
                            if(!yaAsignado) {
                                html += `<option value="${prof.DNI_U}">${escapeHtml(prof.Apellido)}, ${escapeHtml(prof.Nombre)} (DNI: ${prof.DNI_U})</option>`;
                            }
                        });
                        
                        html += `
                                    </select>
                                    <button class="btn-primary" onclick="agregarProfesor()">
                                        <i class="fas fa-plus"></i> Agregar
                                    </button>
                                </div>
                            </div>
                        `;
                        
                        modalBody.innerHTML = html;
                    } else {
                        modalBody.innerHTML = '<div class="mensaje-error"><i class="fas fa-exclamation-triangle"></i> Error al cargar los profesores</div>';
                    }
                },
                error: function() {
                    modalBody.innerHTML = '<div class="mensaje-error"><i class="fas fa-exclamation-triangle"></i> Error de conexión</div>';
                }
            });
        }
        
        function cerrarModal() {
            document.getElementById('modalProfesores').style.display = 'none';
            materiaActual = null;
            cursoActual = null;
        }
        
        function quitarProfesor(idDMC, dniDocente) {
            if(confirm('¿Estás seguro de que deseas quitar este profesor de la materia?')) {
                $.ajax({
                    url: 'gestionar_profesores_materia.php',
                    type: 'POST',
                    data: {
                        action: 'quitar_profesor',
                        id_dmc: idDMC
                    },
                    dataType: 'json',
                    success: function(response) {
                        if(response.success) {
                            mostrarToast(response.message, 'success');
                            // Recargar el modal
                            abrirModalProfesores(materiaActual, cursoActual, '');
                            // Actualizar el contador en la tabla
                            actualizarContadorDocentes(materiaActual);
                        } else {
                            mostrarToast(response.message, 'error');
                        }
                    },
                    error: function() {
                        mostrarToast('Error al quitar el profesor', 'error');
                    }
                });
            }
        }
        
        function agregarProfesor() {
            const select = document.getElementById('select-profesor');
            const idDocente = select.value;
            
            if(!idDocente) {
                mostrarToast('Seleccione un profesor primero', 'error');
                return;
            }
            
            $.ajax({
                url: 'gestionar_profesores_materia.php',
                type: 'POST',
                data: {
                    action: 'agregar_profesor',
                    id_materia: materiaActual,
                    id_curso: cursoActual,
                    id_docente: idDocente
                },
                dataType: 'json',
                success: function(response) {
                    if(response.success) {
                        mostrarToast(response.message, 'success');
                        // Recargar el modal
                        abrirModalProfesores(materiaActual, cursoActual, '');
                        // Actualizar el contador en la tabla
                        actualizarContadorDocentes(materiaActual);
                    } else {
                        mostrarToast(response.message, 'error');
                    }
                },
                error: function() {
                    mostrarToast('Error al agregar el profesor', 'error');
                }
            });
        }
        
        function actualizarContadorDocentes(idMateria) {
            $.ajax({
                url: 'gestionar_profesores_materia.php',
                type: 'POST',
                data: {
                    action: 'obtener_profesores',
                    id_materia: idMateria,
                    id_curso: cursoActual
                },
                dataType: 'json',
                success: function(response) {
                    if(response.success) {
                        const badge = document.querySelector(`.docente-badge[onclick*="${idMateria}"]`);
                        if(badge) {
                            badge.innerHTML = `<i class="fas fa-users"></i> ${response.profesores_actuales.length} docente(s)`;
                        }
                        const btnProfesores = document.querySelector(`.btn-profesores[onclick*="${idMateria}"]`);
                        if(btnProfesores && btnProfesores.parentElement.previousElementSibling) {
                            const badgeCell = btnProfesores.parentElement.previousElementSibling;
                            badgeCell.innerHTML = `<span class="docente-badge" onclick="abrirModalProfesores(${idMateria}, ${cursoActual}, '')">
                                <i class="fas fa-users"></i> ${response.profesores_actuales.length} docente(s)
                            </span>`;
                        }
                    }
                }
            });
        }
        
        function escapeHtml(text) {
            if(!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        // Cerrar modal al hacer clic fuera
        window.onclick = function(event) {
            const modal = document.getElementById('modalProfesores');
            if (event.target == modal) {
                cerrarModal();
            }
        }
        </script>
    </body>
    </html>
    <?php
    mysqli_close($con);
    exit();
}


// LÓGICA ORIGINAL PARA ADMIN Y PROFESOR (con paleta de colores respetada)
$filtro_nombre = $_GET['filtro_nombre'] ?? '';
$filtro_id = $_GET['filtro_id'] ?? '';
$filtro_curso = $_GET['filtro_curso'] ?? '';
$buscar_exacto = $_GET['buscar_exacto'] ?? '';

$where = "WHERE 1=1";
if(!empty($buscar_exacto)){
    if(is_numeric($buscar_exacto)){
        $where = "WHERE materia.ID_materia = '$buscar_exacto'";
    } else {
        $where = "WHERE LOWER(materia.Nom_materia) LIKE LOWER('%" . mysqli_real_escape_string($con, $buscar_exacto) . "%') OR LOWER(curso.curso) LIKE LOWER('%" . mysqli_real_escape_string($con, $buscar_exacto) . "%')";
    }
} else {
    if(!empty($filtro_nombre)) $where .= " AND LOWER(materia.Nom_materia) LIKE LOWER('%" . mysqli_real_escape_string($con, $filtro_nombre) . "%')";
    if(!empty($filtro_id)) $where .= " AND materia.ID_materia = '$filtro_id'";
    if(!empty($filtro_curso)) $where .= " AND materia.id_curso = '$filtro_curso'";
}

$query = "SELECT materia.ID_materia, materia.Nom_materia, materia.id_curso, curso.curso as curso_numero, curso.division as curso_division, curso.turno as curso_turno, (SELECT COUNT(*) FROM calificaciones WHERE calificaciones.id_materia = materia.ID_materia) as total_calificaciones, (SELECT COUNT(*) FROM docentemateriacurso WHERE docentemateriacurso.id_materia = materia.ID_materia) as total_docentes FROM materia LEFT JOIN curso ON materia.id_curso = curso.ID_curso $where AND materia.Nom_materia NOT LIKE '%Taller%' ORDER BY materia.Nom_materia";
$res = mysqli_query($con, $query);

$query_total = "SELECT COUNT(*) as total FROM materia";
$res_total = mysqli_query($con, $query_total);
$total_materias = mysqli_fetch_array($res_total)['total'];

$query_cursos = "SELECT ID_curso, curso, division, turno FROM curso ORDER BY curso, division";
$res_cursos = mysqli_query($con, $query_cursos);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, minimum-scale=1.0">
    <title>Listado de Materias - Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --dark-red: #3F070B;
            --deep-crimson: #710A14;
            --dark-burgundy: #180605;
            --dusty-rose: #818582;
            --rustic-red: #8F3C45;
            --light-bg: #f5f5f5;
            --card-bg: #ffffff;
            --border-color: #e0e0e0;
            --text-dark: #2c2c2c;
            --text-muted: #666666;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Montserrat', sans-serif;
            background: var(--light-bg);
            min-height: 100vh;
            padding: 30px 20px;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
        }

        /* Header */
        .header {
            background: var(--card-bg);
            border-radius: 12px;
            padding: 25px 30px;
            margin-bottom: 30px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            border-left: 5px solid var(--dark-red);
        }

        .header h1 {
            font-size: 28px;
            color: var(--dark-burgundy);
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .header h1 i {
            color: var(--deep-crimson);
            font-size: 32px;
        }

        .header p {
            color: var(--text-muted);
            font-size: 14px;
        }

        /* Stats Card */
        .stats-card {
            background: linear-gradient(135deg, var(--deep-crimson) 0%, var(--dark-red) 100%);
            border-radius: 12px;
            padding: 20px 25px;
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
            color: white;
        }

        .stats-card h3 {
            font-size: 32px;
            font-weight: 700;
        }

        .stats-card p {
            opacity: 0.9;
            font-size: 14px;
        }

        .stats-card i {
            font-size: 48px;
            opacity: 0.8;
        }

        /* Filtros */
        .filtros-card {
            background: var(--card-bg);
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }

        .filtros-card h3 {
            font-size: 18px;
            color: var(--dark-burgundy);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .filtros-card h3 i {
            color: var(--deep-crimson);
        }

        .busqueda-rapida {
            background: var(--light-bg);
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 15px;
        }

        .busqueda-rapida label {
            font-weight: 600;
            color: var(--dark-burgundy);
        }

        .busqueda-rapida input {
            flex: 1;
            min-width: 250px;
            padding: 12px;
            border: 2px solid var(--border-color);
            border-radius: 8px;
            font-size: 14px;
            font-family: 'Montserrat', sans-serif;
        }

        .busqueda-rapida input:focus {
            outline: none;
            border-color: var(--deep-crimson);
        }

        .filtros-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }

        .filtro-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .filtro-group label {
            font-weight: 600;
            color: var(--dark-burgundy);
            font-size: 13px;
        }

        .filtro-group input, .filtro-group select {
            padding: 12px;
            border: 2px solid var(--border-color);
            border-radius: 8px;
            font-size: 14px;
            font-family: 'Montserrat', sans-serif;
            background: white;
        }

        .filtro-group input:focus, .filtro-group select:focus {
            outline: none;
            border-color: var(--deep-crimson);
        }

        .btn-primary {
            background: var(--deep-crimson);
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-family: 'Montserrat', sans-serif;
        }

        .btn-primary:hover {
            background: var(--dark-red);
            transform: translateY(-2px);
        }

        .btn-secondary {
            background: var(--dusty-rose);
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-family: 'Montserrat', sans-serif;
        }

        .btn-secondary:hover {
            background: var(--deep-crimson);
        }

        /* Tabla */
        .table-container {
            background: var(--card-bg);
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            text-align: left;
            padding: 16px 20px;
            background: var(--dark-red);
            color: white;
            font-weight: 600;
            font-size: 14px;
        }

        td {
            padding: 16px 20px;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-dark);
            font-size: 14px;
        }

        tr:hover td {
            background: rgba(129,133,130,0.05);
        }

        .btn-edit {
            background: var(--rustic-red);
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            margin-right: 8px;
        }

        .btn-edit:hover {
            background: var(--deep-crimson);
        }

        .btn-delete {
            background: var(--dusty-rose);
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .btn-delete:hover:not(:disabled) {
            background: var(--deep-crimson);
        }

        .btn-delete:disabled {
            background: #ccc;
            cursor: not-allowed;
            opacity: 0.6;
        }

        .badge-curso {
            background: #f0e6e6;
            color: var(--deep-crimson);
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            display: inline-block;
        }

        .docente-count {
            background: var(--deep-crimson);
            color: white;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .actions-footer {
            padding: 20px 25px;
            background: var(--light-bg);
            border-top: 1px solid var(--border-color);
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }

        @media (max-width: 768px) {
            body {
                padding: 15px;
            }

            th, td {
                padding: 12px;
                font-size: 12px;
            }

            .filtros-grid {
                grid-template-columns: 1fr;
            }

            .busqueda-rapida {
                flex-direction: column;
                align-items: stretch;
            }
        }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1>
            <i class="fas fa-database"></i>
            Listado de Materias
        </h1>
        <p><i class="fas fa-chart-line"></i> Gestión completa de todas las materias del sistema</p>
    </div>
    
    <div class="stats-card">
        <div>
            <h3><?php echo $total_materias; ?></h3>
            <p>Total de materias registradas</p>
        </div>
        <i class="fas fa-book"></i>
    </div>
    
    <div class="filtros-card">
        <h3>
            <i class="fas fa-filter"></i>
            Filtros de Búsqueda
        </h3>
        
        <div class="busqueda-rapida">
            <form method="GET" action="" style="flex: 1; display: flex; gap: 10px; flex-wrap: wrap;">
                <i class="fas fa-search" style="align-self: center; color: var(--dusty-rose);"></i>
                <input type="text" name="buscar_exacto" placeholder="Buscar por ID, nombre o curso..." value="<?php echo htmlspecialchars($buscar_exacto); ?>" style="flex: 1;">
                <button type="submit" class="btn-primary">
                    <i class="fas fa-search"></i> Buscar
                </button>
                <?php if(!empty($buscar_exacto)): ?>
                    <a href="listado_materia.php" class="btn-secondary">
                        <i class="fas fa-times"></i> Limpiar
                    </a>
                <?php endif; ?>
            </form>
        </div>
        
        <?php if(empty($buscar_exacto)): ?>
        <form method="GET" action="">
            <div class="filtros-grid">
                <div class="filtro-group">
                    <label><i class="fas fa-tag"></i> Nombre</label>
                    <input type="text" name="filtro_nombre" placeholder="Nombre de materia..." value="<?php echo htmlspecialchars($filtro_nombre); ?>">
                </div>
                <div class="filtro-group">
                    <label><i class="fas fa-hashtag"></i> ID Materia</label>
                    <input type="number" name="filtro_id" placeholder="ID exacto..." value="<?php echo htmlspecialchars($filtro_id); ?>">
                </div>
                <div class="filtro-group">
                    <label><i class="fas fa-graduation-cap"></i> Curso</label>
                    <select name="filtro_curso">
                        <option value="">Todos los cursos</option>
                        <?php
                        mysqli_data_seek($res_cursos, 0);
                        while($fila_curso = mysqli_fetch_array($res_cursos)){
                            $turno_texto = $fila_curso['turno'] == 'M' ? 'Mañana' : 'Tarde';
                            $curso_completo = $fila_curso['curso'] . '° "' . $fila_curso['division'] . '" - ' . $turno_texto;
                            $selected = ($filtro_curso == $fila_curso['ID_curso']) ? 'selected' : '';
                            echo '<option value="' . $fila_curso['ID_curso'] . '" ' . $selected . '>' . $curso_completo . '</option>';
                        }
                        ?>
                    </select>
                </div>
            </div>
            <div style="display: flex; gap: 10px;">
                <button type="submit" class="btn-primary">
                    <i class="fas fa-search"></i> Aplicar Filtros
                </button>
                <a href="listado_materia.php" class="btn-secondary">
                    <i class="fas fa-eraser"></i> Limpiar Filtros
                </a>
            </div>
        </form>
        <?php endif; ?>
    </div>
    
    <div class="table-container">
        <div style="overflow-x: auto;">
            <table id="listado">
                <thead>
                    <tr>
                        <th><i class="fas fa-hashtag"></i> ID</th>
                        <th><i class="fas fa-book"></i> Nombre</th>
                        <th><i class="fas fa-graduation-cap"></i> Curso</th>
                        <th><i class="fas fa-chalkboard-user"></i> Docentes</th>
                        <th><i class="fas fa-cogs"></i> Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    if($res && mysqli_num_rows($res) > 0){
                        while($fila = mysqli_fetch_array($res)){
                            $turno_texto = $fila['curso_turno'] == 'M' ? 'Mañana' : 'Tarde';
                            $curso_texto = $fila['id_curso'] ? $fila['curso_numero'] . '° "' . $fila['curso_division'] . '" - ' . $turno_texto : '<i>Sin curso</i>';
                            $disabled = ($fila['total_calificaciones'] > 0 || $fila['total_docentes'] > 0);
                    ?>
                        <tr>
                            <td><?= $fila['ID_materia'] ?></td>
                            <td><strong><?= htmlspecialchars($fila['Nom_materia']) ?></strong></td>
                            <td><span class="badge-curso"><?= $curso_texto ?></span></td>
                            <td>
                                <?php if($fila['total_docentes'] > 0): ?>
                                    <span class="docente-count">
                                        <i class="fas fa-user-check"></i> <?= $fila['total_docentes'] ?>
                                    </span>
                                <?php else: ?>
                                    <span style="color: var(--dusty-rose);">
                                        <i class="fas fa-user-slash"></i> 0
                                    </span>
                                <?php endif; ?>
                             </div>
                            </td>
                            <td>
                                <a href="editar_materia.php?id=<?= $fila['ID_materia'] ?>" class="btn-edit">
                                    <i class="fas fa-edit"></i> Editar
                                </a>
                                <?php if(!$disabled): ?>
                                    <a href="borrar_materia.php?id=<?= $fila['ID_materia'] ?>" class="btn-delete" onclick="return confirm('¿Eliminar esta materia?')">
                                        <i class="fas fa-trash-alt"></i> Eliminar
                                    </a>
                                <?php else: ?>
                                    <button class="btn-delete" disabled title="No se puede eliminar porque tiene calificaciones o docentes asociados">
                                        <i class="fas fa-trash-alt"></i> Eliminar
                                    </button>
                                <?php endif; ?>
                             </div>
                            </td>
                        </tr>
                    <?php
                        }
                    } else {
                        echo '<tr><td colspan="5" style="text-align: center; padding: 60px;"><i class="fas fa-inbox" style="font-size: 48px; margin-bottom: 15px; display: block; color: var(--deep-crimson);"></i>No hay materias registradas</td></tr>';
                    }
                    ?>
                </tbody>
            </table>
        </div>
        
        <div class="actions-footer">
            <a href="materia.php" class="btn-primary">
                <i class="fas fa-plus"></i> Agregar Nueva Materia
            </a>
            <a href="../../recursos/panel.php" class="btn-secondary">
                <i class="fas fa-arrow-left"></i> Volver al Panel
            </a>
            <button onclick="generarPDF()" class="btn-secondary">
                <i class="fas fa-file-pdf"></i> Exportar PDF
            </button>
        </div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.28/jspdf.plugin.autotable.min.js"></script>
<script>
function generarPDF() {
    const { jsPDF } = window.jspdf;
    const doc = new jsPDF('landscape');
    doc.setFontSize(18);
    doc.text('Listado de Materias - EPET Nº 34', 14, 15);
    doc.setFontSize(10);
    doc.text('Fecha: ' + new Date().toLocaleDateString(), 14, 25);
    
    const table = document.getElementById('listado');
    const rows = table.querySelectorAll('tr');
    const data = [];
    rows.forEach(row => {
        const rowData = [];
        row.querySelectorAll('th, td').forEach(cell => {
            let text = cell.innerText;
            if(text.includes('Editar') || text.includes('Eliminar')) {
                text = '';
            }
            if(text) rowData.push(text);
        });
        if(rowData.length > 0) data.push(rowData);
    });
    
    doc.autoTable({ 
        head: [data[0]], 
        body: data.slice(1), 
        startY: 35,
        theme: 'grid',
        styles: { fontSize: 8, cellPadding: 3 },
        headStyles: { fillColor: [63, 7, 11] }
    });
    doc.save('materias.pdf');
}
</script>
</body>
</html>
<?php
mysqli_close($con);
?>