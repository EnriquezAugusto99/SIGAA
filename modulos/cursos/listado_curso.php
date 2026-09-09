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

if($_SESSION['rol'] != 'Admin' && $_SESSION['rol'] != 'Preceptor' && $_SESSION['rol'] != 'Profesor'){
    echo '<script>alert("No tiene permisos para ver cursos"); window.location="../../recursos/panel.php";</script>';
    exit();
}

include '../../recursos/conexion.php';

$es_preceptor = ($_SESSION['rol'] == 'Preceptor');

if($es_preceptor){
    $preceptor_dni = $_SESSION["dni"];
    
    $mensaje = '';
    if(isset($_GET['tomar']) && is_numeric($_GET['tomar'])){
        $nuevo_curso = $_GET['tomar'];
        $q_check = "SELECT 1 FROM preceptorxcurso WHERE id_preceptor='$preceptor_dni' AND id_curso='$nuevo_curso'";
        if(mysqli_num_rows(mysqli_query($con, $q_check)) > 0){
            $_SESSION['curso_activo_preceptor'] = $nuevo_curso;
            $mensaje = "✅ Has cambiado al curso seleccionado. Ahora controlas ese curso.";
        } else {
            $mensaje = "❌ Curso no autorizado.";
        }
    }
    
    $query = "SELECT 
                curso.ID_curso, curso.curso, curso.division, curso.turno,
                (SELECT COUNT(*) FROM usuario WHERE usuario.id_curso = curso.ID_curso AND usuario.ID_rol = 3) as total_estudiantes,
                (SELECT COUNT(*) FROM horarios WHERE horarios.id_curso = curso.ID_curso) as total_horarios,
                (SELECT COUNT(*) FROM docentemateriacurso WHERE docentemateriacurso.id_curso = curso.ID_curso) as total_docentes,
                (SELECT COUNT(*) FROM inasistencias WHERE inasistencias.id_curso = curso.ID_curso) as total_inasistencias
              FROM curso
              INNER JOIN preceptorxcurso pxc ON curso.ID_curso = pxc.id_curso
              WHERE pxc.id_preceptor = '$preceptor_dni'
              ORDER BY curso.curso, curso.division";
    $res = mysqli_query($con, $query);
    $total_cursos = mysqli_num_rows($res);
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <link rel="stylesheet" href="../../recursos/styles.css">
        <title>Mis Cursos - Preceptor</title>
        <style>

/* Contenedor con scroll horizontal */
.tabla-container {
    width: 100%;
    overflow-x: auto;
    overflow-y: visible;
    -webkit-overflow-scrolling: touch;
    margin-bottom: 20px;
}

/* Ajustes para tablets y celulares */
@media (max-width: 992px) {
    .caja {
        padding: 15px;
        margin: 10px;
    }
    
    h1 {
        font-size: 1.4rem;
        text-align: center;
    }
    
    .filtro-rapido form {
        display: flex;
        flex-direction: column;
        gap: 8px;
    }
    
    .filtro-rapido input,
    .filtro-rapido button,
    .filtro-rapido a button {
        width: 100%;
    }
    
    .filtros form {
        display: flex;
        flex-direction: column;
    }
    
    .filtros select,
    .filtros button {
        width: 100%;
        margin: 5px 0;
    }
    
    .card-footer {
        display: flex;
        flex-direction: column;
        gap: 8px;
        margin-top: 15px;
    }
    
    .card-footer button,
    .card-footer a button {
        width: 100%;
    }
}

@media (max-width: 768px) {
    h1 {
        font-size: 1.2rem;
    }
    
    .resumen {
        font-size: 0.8rem;
        text-align: center;
    }
    
    .tabla th,
    .tabla td {
        padding: 8px 6px;
        font-size: 0.7rem;
    }
}

@media (max-width: 480px) {
    h1 {
        font-size: 1rem;
    }
    
    .tabla th,
    .tabla td {
        padding: 6px 4px;
        font-size: 0.65rem;
    }
}
        </style>
    </head>
    <body>
    <div class="caja">
        <h1>Mis Cursos</h1>
        <?php if($mensaje): ?>
            <div class="mensaje-flotante" id="mensajeControl"><?= htmlspecialchars($mensaje) ?></div>
            <script>setTimeout(function(){ var msg = document.getElementById('mensajeControl'); if(msg) msg.style.display = 'none'; }, 3000);</script>
        <?php endif; ?>
        <div class="resumen"><strong>Total de cursos a su cargo: <?= $total_cursos ?></strong></div>

<div class="tabla-container">
        <table class="tabla" id="listado">
            <thead><tr><th>ID</th><th>Curso</th><th>Estudiantes</th><th>Horarios</th><th>Docentes</th><th>Inasistencias</th><th>Acción</th></tr></thead>
            <tbody>
                <?php if($res && mysqli_num_rows($res) > 0):
                    $curso_activo = $_SESSION['curso_activo_preceptor'] ?? null;
                    while($fila = mysqli_fetch_array($res)):
                        $turno_texto = $fila['turno'] == 'M' ? 'Mañana' : 'Tarde';
                        $curso_completo = $fila['curso'] . '° "' . $fila['division'] . '" - ' . $turno_texto;
                        $tiene_ref = ($fila['total_estudiantes'] > 0 || $fila['total_horarios'] > 0 || $fila['total_docentes'] > 0 || $fila['total_inasistencias'] > 0);
                        $es_activo = ($curso_activo == $fila['ID_curso']);
                        $clase_fila = $es_activo ? 'curso-activo' : '';
                ?>
                    <tr class="<?= $clase_fila ?>">
                        <td><?= $fila['ID_curso'] ?></td>
                        <td><strong><?= htmlspecialchars($curso_completo) ?></strong> <?= $es_activo ? '<span style="color:#FFD700;">(ACTIVO)</span>' : '' ?></td>
                        <td><?= $fila['total_estudiantes'] ?></td>
                        <td><?= $fila['total_horarios'] ?></td>
                        <td><?= $fila['total_docentes'] ?></td>
                        <td><?= $fila['total_inasistencias'] ?></td>
                        <td><?php if(!$es_activo): ?><a href="?tomar=<?= $fila['ID_curso'] ?>" class="btn-control">📌 Tomar control</a><?php else: ?><button class="btn-control" disabled style="opacity:0.6;">✔️ Control activo</button><?php endif; ?></td>
                    </tr>
                <?php endwhile; else: ?>
                    <tr><td colspan="9">No tiene cursos asignados.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
</div>
        <p>
            <?php if($_SESSION['rol'] == 'Admin'){ ?>   
            <a href="cursos.php"><button>Agregar Nuevo Curso</button></a>
            <?php } ?>
            <a href="../../recursos/panel.php"><button>Volver al Panel</button></a>
        </p>
    </div>
    </body>
    </html>
    <?php
    mysqli_close($con);
    exit();
}


// VISTA PARA ADMIN Y PROFESOR
$filtro_curso = $_GET['filtro_curso'] ?? '';
$filtro_division = $_GET['filtro_division'] ?? '';
$filtro_turno = $_GET['filtro_turno'] ?? '';
$buscar_exacto = $_GET['buscar_exacto'] ?? '';

$where = "WHERE 1=1";
if(!empty($buscar_exacto)){
    if(is_numeric($buscar_exacto)){
        $where = "WHERE curso.ID_curso = '$buscar_exacto'";
    } else {
        $where = "WHERE CONCAT(curso.curso, '° \"', curso.division, '\" - ', CASE curso.turno WHEN 'M' THEN 'Mañana' ELSE 'Tarde' END) LIKE '%" . mysqli_real_escape_string($con, $buscar_exacto) . "%'";
    }
} else {
    if(!empty($filtro_curso)) $where .= " AND curso.curso = '$filtro_curso'";
    if(!empty($filtro_division)) $where .= " AND curso.division = '$filtro_division'";
    if(!empty($filtro_turno)) $where .= " AND curso.turno = '$filtro_turno'";
}

$query = "SELECT 
            curso.ID_curso, curso.curso, curso.division, curso.turno,
            (SELECT COUNT(*) FROM usuario WHERE usuario.id_curso = curso.ID_curso AND usuario.ID_rol = 3) as total_estudiantes,
            (SELECT COUNT(*) FROM horarios WHERE horarios.id_curso = curso.ID_curso) as total_horarios,
            (SELECT COUNT(*) FROM docentemateriacurso WHERE docentemateriacurso.id_curso = curso.ID_curso) as total_docentes,
            (SELECT COUNT(*) FROM inasistencias WHERE inasistencias.id_curso = curso.ID_curso) as total_inasistencias
          FROM curso $where ORDER BY curso.curso, curso.division";
$res = mysqli_query($con, $query);
$total_cursos = mysqli_num_rows($res);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../../recursos/styles.css">
    <title>Listado de Cursos</title>
 <style>

/* Contenedor con scroll horizontal */
.tabla-container {
    width: 100%;
    overflow-x: auto;
    overflow-y: visible;
    -webkit-overflow-scrolling: touch;
    margin-bottom: 20px;
}

/* Ajustes para tablets y celulares */
@media (max-width: 992px) {
    .caja {
        padding: 15px;
        margin: 10px;
    }
    
    h1 {
        font-size: 1.4rem;
        text-align: center;
    }
    
    .filtro-rapido form {
        display: flex;
        flex-direction: column;
        gap: 8px;
    }
    
    .filtro-rapido input,
    .filtro-rapido button,
    .filtro-rapido a button {
        width: 100%;
    }
    
    .filtros form {
        display: flex;
        flex-direction: column;
    }
    
    .filtros select,
    .filtros button {
        width: 100%;
        margin: 5px 0;
    }
    
    .card-footer {
        display: flex;
        flex-direction: column;
        gap: 8px;
        margin-top: 15px;
    }
    
    .card-footer button,
    .card-footer a button {
        width: 100%;
    }
}

@media (max-width: 768px) {
    h1 {
        font-size: 1.2rem;
    }
    
    .resumen {
        font-size: 0.8rem;
        text-align: center;
    }
    
    .tabla th,
    .tabla td {
        padding: 8px 6px;
        font-size: 0.7rem;
    }
}

@media (max-width: 480px) {
    h1 {
        font-size: 1rem;
    }
    
    .tabla th,
    .tabla td {
        padding: 6px 4px;
        font-size: 0.65rem;
    }
}
        </style>
</head>
<body>
<div class="caja">
    <h1>Listado de Cursos</h1>
    <div class="resumen"><strong>Total de cursos: <?= $total_cursos ?></strong></div>
    <div class="filtro-rapido">
        <form method="GET">
            <label><strong>Búsqueda rápida:</strong></label>
            <input type="text" name="buscar_exacto" value="<?= htmlspecialchars($buscar_exacto) ?>" placeholder="ID o descripción">
            <button type="submit">Buscar</button>
            <?php if(!empty($buscar_exacto)): ?><a href="listado_curso.php"><button type="button">Limpiar</button></a><?php endif; ?>
        </form>
    </div>
    <div class="filtros">
        <form method="GET">
            <h3>Filtros Avanzados</h3>
            <label>Año:</label>
            <select name="filtro_curso">
                <option value="">Todos</option>
                <?php for($i=1;$i<=6;$i++) echo '<option value="'.$i.'" '.($filtro_curso==$i?'selected':'').'>'.$i.'°</option>'; ?>
            </select>
            <label>División:</label>
            <select name="filtro_division">
                <option value="">Todas</option>
                <?php foreach(['A','B','C','D','E','F'] as $d) echo '<option value="'.$d.'" '.($filtro_division==$d?'selected':'').'>'.$d.'</option>'; ?>
            </select>
            <label>Turno:</label>
            <select name="filtro_turno">
                <option value="">Todos</option>
                <option value="M" <?= ($filtro_turno=='M'?'selected':'') ?>>Mañana</option>
                <option value="T" <?= ($filtro_turno=='T'?'selected':'') ?>>Tarde</option>
            </select>
            <button type="submit">Aplicar</button>
            <a href="listado_curso.php"><button type="button">Limpiar</button></a>
        </form>
    </div>
<div class="tabla-container">
    <table class="tabla" id="listado">
        <thead><tr><th>ID</th><th>Curso</th><th>Estudiantes</th><th>Horarios</th><th>Docentes</th><th>Inasistencias</th><th>Editar</th><th>Eliminar</th></tr></thead>
        <tbody>
            <?php if($res && mysqli_num_rows($res)>0):
                while($fila = mysqli_fetch_array($res)):
                    $turno_texto = $fila['turno']=='M'?'Mañana':'Tarde';
                    $curso_completo = $fila['curso'].'° "'.$fila['division'].'" - '.$turno_texto;
                    $tiene_ref = ($fila['total_estudiantes']>0||$fila['total_horarios']>0||$fila['total_docentes']>0||$fila['total_inasistencias']>0);
            ?>
                <tr>
                    <td><?= $fila['ID_curso'] ?></td>
                    <td><strong><?= htmlspecialchars($curso_completo) ?></strong></td>
                    <td><?= $fila['total_estudiantes'] ?></td>
                    <td><?= $fila['total_horarios'] ?></td>
                    <td><?= $fila['total_docentes'] ?></td>
                    <td><?= $fila['total_inasistencias'] ?></td>
                    <td><a href="editar_curso.php?id=<?= $fila['ID_curso'] ?>"><button>Editar</button></a></td>
                    <!-- Dentro del while de los cursos, reemplazar la celda de Eliminar -->
<td>
    <?php if(!$tiene_ref): ?>
        <form action="borrar_curso.php" method="post" style="display:inline;" onsubmit="return confirm('¿Está seguro que desea eliminar el curso <?= htmlspecialchars($curso_completo) ?>?\n\nEsta acción no se puede deshacer.');">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
            <input type="hidden" name="id" value="<?= $fila['ID_curso'] ?>">
            <button type="submit" style="background:#d32f2f; color:white; border:none; padding:5px 10px; border-radius:4px; cursor:pointer;">Eliminar</button>
        </form>
    <?php else: ?>
        <button disabled style="background:#ccc; border:none; padding:5px 10px; border-radius:4px;">Eliminar</button>
    <?php endif; ?>
</td>
                </tr>
            <?php endwhile; else: ?>
                <tr><td colspan="8">No hay cursos.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>
    <div class="card-footer">
    <?php if($_SESSION['rol'] == 'Admin'){ ?>   
    	<a href="cursos.php"><button>Agregar Nuevo Curso</button></a> 
    <?php } ?>
    <a href="../../recursos/panel.php" class="btn-secondary"><button>Volver al Panel</button></a>
    <button onclick="generarPDF()">Exportar PDF</button>
</div>
</div>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.28/jspdf.plugin.autotable.min.js"></script>
<script>
function generarPDF() {
    const { jsPDF } = window.jspdf;
    const doc = new jsPDF('l');
    const table = document.getElementById('listado');
    const rows = table.querySelectorAll('tr');
    const data = [];
    rows.forEach(row => {
        const rowData = [];
        row.querySelectorAll('th, td').forEach(cell => rowData.push(cell.innerText));
        data.push(rowData);
    });
    doc.autoTable({ head: [data[0]], body: data.slice(1), startY: 25 });
    doc.save('cursos.pdf');
}
</script>
</body>
</html>
<?php mysqli_close($con); ?>