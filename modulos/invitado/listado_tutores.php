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

// Verificar permisos - Admin o Invitado
$es_admin = ($_SESSION['rol'] == 'Admin');
$es_invitado = ($_SESSION['rol'] == 'Invitado');

if(!$es_admin && !$es_invitado){
    echo '<script>alert("No tiene permisos para acceder a esta sección"); window.location="../../recursos/panel.php";</script>';
    exit();
}

include '../../recursos/conexion.php';

// Cargar todos los tutores inicialmente
$query = "SELECT u.DNI_U, u.Nombre, u.Apellido, u.email,
          (SELECT COUNT(*) FROM alumnoxtutor WHERE id_tutor = u.DNI_U) as total_hijos
          FROM usuario u
          WHERE u.ID_rol = 8
          ORDER BY u.Apellido, u.Nombre";
$res = mysqli_query($con, $query);
$tutores = [];
while($row = mysqli_fetch_assoc($res)){
    $tutores[] = $row;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <title>Listado de Tutores</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Montserrat', sans-serif; background: #f5f5f5; padding: 20px; }
        .container { max-width: 1200px; margin: 0 auto; }
        .header { background: linear-gradient(135deg, #3F070B 0%, #710A14 100%); border-radius: 12px; padding: 25px 30px; margin-bottom: 30px; color: white; }
        .header h1 { font-size: 24px; margin-bottom: 8px; display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }
        .header p { opacity: 0.9; font-size: 14px; }
        .card { background: white; border-radius: 12px; padding: 25px; margin-bottom: 30px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
        .card h2 { font-size: 18px; color: #3F070B; margin-bottom: 20px; border-left: 4px solid #710A14; padding-left: 15px; }
        .filtro-rapido { display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 20px; }
        .filtro-rapido input { 
            flex: 1; 
            min-width: 200px; 
            padding: 12px; 
            border: 2px solid #e0e0e0; 
            border-radius: 8px; 
            font-size: 14px; 
            font-family: 'Montserrat', sans-serif;
            transition: all 0.3s ease;
        }
        .filtro-rapido input:focus { 
            outline: none; 
            border-color: #710A14; 
            box-shadow: 0 0 0 3px rgba(113,10,20,0.1);
        }
        .filtro-rapido input::placeholder {
            color: #aaa;
        }
        .btn-primary { background: #710A14; color: white; border: none; padding: 12px 24px; border-radius: 8px; font-weight: 600; cursor: pointer; transition: all 0.3s ease; }
        .btn-primary:hover { background: #3F070B; transform: translateY(-2px); }
        .btn-secondary { background: #666; color: white; border: none; padding: 12px 24px; border-radius: 8px; font-weight: 600; cursor: pointer; transition: all 0.3s ease; display: inline-flex; align-items: center; gap: 8px; text-decoration: none; }
        .btn-secondary:hover { background: #555; transform: translateY(-2px); }
        .btn-group { display: flex; gap: 15px; margin-top: 20px; flex-wrap: wrap; }
        .tabla-container { overflow-x: auto; }
        .tabla { width: 100%; border-collapse: collapse; font-size: 13px; }
        .tabla th, .tabla td { border: 1px solid #ddd; padding: 12px; text-align: left; }
        .tabla th { background: #710A14; color: white; }
        .tabla tr:hover td { background: #fdf5f5; }
        .badge-hijos { background: #ff9800; color: white; padding: 2px 10px; border-radius: 20px; font-size: 12px; }
        .badge-ver { background: #2196F3; color: white; padding: 6px 12px; border-radius: 6px; text-decoration: none; font-size: 12px; }
        .badge-ver:hover { background: #1976D2; }
        .sin-datos { text-align: center; padding: 40px; color: #999; }
        .loading-row { text-align: center; padding: 20px; color: #999; }
        .loading-row .spinner { display: inline-block; width: 24px; height: 24px; border: 3px solid #f3f3f3; border-top: 3px solid #710A14; border-radius: 50%; animation: spin 1s linear infinite; margin-right: 10px; vertical-align: middle; }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
        .resultados-info { font-size: 13px; color: #666; margin-bottom: 15px; }
        @media (max-width: 768px) {
            .filtro-rapido { flex-direction: column; }
            .filtro-rapido input { width: 100%; }
            .tabla { font-size: 11px; }
            .tabla th, .tabla td { padding: 8px 6px; }
            .btn-group { flex-direction: column; }
            .btn-group .btn-primary, .btn-group .btn-secondary { width: 100%; justify-content: center; }
        }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1>
            <i class="fas fa-users"></i> Listado de Tutores
            <?php if($es_admin): ?>
                <span style="background: #ff9800; color: #1a2a3a; padding: 4px 12px; border-radius: 20px; font-size: 12px;">Administrador</span>
            <?php else: ?>
                <span style="background: #ff9800; color: #1a2a3a; padding: 4px 12px; border-radius: 20px; font-size: 12px;">Mesa de Entrada</span>
            <?php endif; ?>
        </h1>
    </div>

    <div class="card">
        <div class="filtro-rapido">
            <input type="text" id="buscarInput" placeholder="Buscar tutor por DNI, nombre o apellido..." autofocus>
            <a href="listado_tutores.php" class="btn-secondary"><i class="fas fa-times"></i> Limpiar</a>
        </div>
        
        <div class="tabla-container">
            <table class="tabla" id="tablaTutores">
                <thead>
                    <tr>
                        <th>DNI</th>
                        <th>Apellido y Nombre</th>
                        <th>Email</th>
                        <th>Hijos Asignados</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody id="tablaBody">
                    <?php foreach($tutores as $row): ?>
                        <tr data-dni="<?= $row['DNI_U'] ?>" data-nombre="<?= strtolower($row['Nombre'] . ' ' . $row['Apellido']) ?>" data-apellido="<?= strtolower($row['Apellido']) ?>">
                            <td><?= $row['DNI_U'] ?></td>
                            <td><strong><?= htmlspecialchars($row['Apellido'] . ', ' . $row['Nombre']) ?></strong></td>
                            <td><?= htmlspecialchars($row['email'] ?: 'No registrado') ?></td>
                            <td><span class="badge-hijos"><?= $row['total_hijos'] ?> hijo(s)</span></td>
                            <td>
                                <a href="ver_tutor.php?id=<?= $row['DNI_U'] ?>" class="badge-ver">
                                    <i class="fas fa-eye"></i> Ver
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <div class="btn-group">
            <a href="registrar_tutor.php" class="btn-primary">
                <i class="fas fa-plus"></i> Registrar Nuevo Tutor
            </a>
            <a href="../../recursos/panel.php" class="btn-secondary">
                <i class="fas fa-arrow-left"></i> Volver al Panel
            </a>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const inputBuscar = document.getElementById('buscarInput');
    const tablaBody = document.getElementById('tablaBody');
    const resultadosInfo = document.getElementById('resultados-info');
    const filas = tablaBody.querySelectorAll('tr');
    let timeout = null;

    function filtrarTutores() {
        const termino = inputBuscar.value.trim().toLowerCase();
        let encontrados = 0;
        
        // Si el término está vacío, mostrar todas las filas
        if (termino === '') {
            filas.forEach(fila => {
                fila.style.display = '';
                encontrados++;
            });
            resultadosInfo.textContent = `Mostrando ${encontrados} tutor(es)`;
            return;
        }
        
        // Filtrar
        filas.forEach(fila => {
            const dni = fila.getAttribute('data-dni') || '';
            const nombre = fila.getAttribute('data-nombre') || '';
            const apellido = fila.getAttribute('data-apellido') || '';
            
            // Buscar en DNI, nombre completo o apellido
            const textoCompleto = dni + ' ' + nombre + ' ' + apellido;
            
            if (textoCompleto.toLowerCase().includes(termino)) {
                fila.style.display = '';
                encontrados++;
            } else {
                fila.style.display = 'none';
            }
        });
        
        // Actualizar contador
        if (encontrados === 0) {
            resultadosInfo.innerHTML = '⚠️ No se encontraron tutores que coincidan con "<strong>' + inputBuscar.value + '</strong>"';
            // Mostrar mensaje en la tabla
            const filaVacia = tablaBody.querySelector('.sin-resultados');
            if (!filaVacia) {
                const tr = document.createElement('tr');
                tr.className = 'sin-resultados';
                tr.innerHTML = `<td colspan="5" class="sin-datos">
                                    <i class="fas fa-search" style="font-size: 36px; margin-bottom: 10px; display: block;"></i>
                                    No hay tutores que coincidan con "<strong>${inputBuscar.value}</strong>"
                                </td>`;
                tablaBody.appendChild(tr);
            } else {
                filaVacia.innerHTML = `<td colspan="5" class="sin-datos">
                                            <i class="fas fa-search" style="font-size: 36px; margin-bottom: 10px; display: block;"></i>
                                            No hay tutores que coincidan con "<strong>${inputBuscar.value}</strong>"
                                        </td>`;
            }
        } else {
            // Eliminar mensaje de "sin resultados"
            const filaVacia = tablaBody.querySelector('.sin-resultados');
            if (filaVacia) {
                filaVacia.remove();
            }
            resultadosInfo.innerHTML = `Mostrando <strong>${encontrados}</strong> tutor(es) que coinciden con "<strong>${inputBuscar.value}</strong>"`;
        }
    }

    // Evento input con debounce (espera 300ms después de dejar de escribir)
    inputBuscar.addEventListener('input', function() {
        clearTimeout(timeout);
        timeout = setTimeout(filtrarTutores, 300);
    });

    // Ejecutar al cargar para mostrar el contador inicial
    setTimeout(filtrarTutores, 100);
});
</script>
</body>
</html>
<?php mysqli_close($con); ?>