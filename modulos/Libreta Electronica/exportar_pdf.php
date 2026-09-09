<?php
// 1. Inclusión de la conexión a la base de datos
// Ajustá esta ruta según la ubicación real de tu archivo de conexión
include_once "../../conexion.php"; 

// 2. Captura y validación del ID del alumno por URL
$alumno_id = isset($_GET['alumno_id']) ? intval($_GET['alumno_id']) : 0;

if ($alumno_id === 0) {
    die("<h3 style='text-align:center; color:red; font-family:Arial;'>Error: ID de alumno no válido.</h3>");
}

// 3. Consulta para obtener los datos personales del alumno
// Asumimos que los datos están en la tabla 'usuarios' según tu estructura
$sql_alumno = "SELECT nombre, apellido, curso FROM usuarios WHERE id = $alumno_id LIMIT 1";
$resultado_alumno = mysqli_query($conexion, $sql_alumno);

if (!$resultado_alumno || mysqli_num_rows($resultado_alumno) == 0) {
    die("<h3 style='text-align:center; color:red; font-family:Arial;'>Error: Alumno no encontrado en el sistema.</h3>");
}

$datos_alumno = mysqli_fetch_assoc($resultado_alumno);
$nombre_completo = $datos_alumno['nombre'] . " " . $datos_alumno['apellido'];
$curso = $datos_alumno['curso'];

// 4. Consulta para traer todas las calificaciones del alumno
// Trae las materias y las notas de los tres trimestres
$sql_notas = "SELECT materia, trimestre1, trimestre2, trimestre3, promedio FROM calificaciones WHERE alumno_id = $alumno_id";
$resultado_notas = mysqli_query($conexion, $sql_notas);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Libreta_<?php echo $alumno_id; ?></title>
    <style>
        /* --- ESTILOS PARA VISTA EN PANTALLA --- */
        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            margin: 30px;
            background-color: #f8f9fa;
            color: #333;
        }
        .contenedor-libreta {
            max-width: 850px;
            margin: 0 auto;
            background: #ffffff;
            padding: 40px;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
        }
        .header {
            text-align: center;
            border-bottom: 3px double #1a365d;
            padding-bottom: 15px;
            margin-bottom: 25px;
        }
        .header h2 {
            margin: 0 0 5px 0;
            color: #1a365d;
            font-size: 24px;
        }
        .header h3 {
            margin: 0;
            color: #4a5568;
            font-size: 18px;
            font-weight: normal;
        }
        .datos-alumno {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-bottom: 30px;
            background-color: #f7fafc;
            padding: 15px;
            border-radius: 6px;
            border-left: 4px solid #1a365d;
        }
        .datos-alumno p {
            margin: 0;
            font-size: 15px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        table, th, td {
            border: 1px solid #cbd5e0;
        }
        th, td {
            padding: 12px;
            text-align: left;
            font-size: 14px;
        }
        th {
            background-color: #2d3748;
            color: white;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 12px;
            letter-spacing: 0.5px;
        }
        tr:nth-child(even) {
            background-color: #f8fafc;
        }
        .txt-centro {
            text-align: center;
        }
        .promedio-destacado {
            font-weight: bold;
            color: #1a365d;
        }
        .btn-descargar {
            display: block;
            width: fit-content;
            margin: 0 auto 20px auto;
            padding: 12px 25px;
            background-color: #2b6cb0;
            color: white;
            text-decoration: none;
            border: none;
            border-radius: 6px;
            font-size: 15px;
            font-weight: bold;
            cursor: pointer;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            transition: background 0.2s ease;
        }
        .btn-descargar:hover {
            background-color: #2c5282;
        }

        /* --- CONTROL DE IMPRESIÓN Y EXPORTACIÓN A PDF --- */
        @media print {
            body {
                background-color: #fff;
                margin: 0;
                color: #000;
            }
            .contenedor-libreta {
                border: none;
                box-shadow: none;
                padding: 0;
                width: 100%;
            }
            .btn-descargar {
                display: none !important; /* Desaparece el botón en el PDF */
            }
            th {
                background-color: #eeeeee !important; /* Asegura legibilidad en blanco y negro si imprimen */
                color: #000 !important;
            }
            * {
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
        }
    </style>
</head>
<body>

    <button class="btn-descargar" id="btnPdf">Descargar Libreta (PDF)</button>

    <div class="contenedor-libreta">
        <div class="header">
            <h2>EPET N° 34</h2>
            <h3>Libreta Digital de Calificaciones</h3>
        </div>

        <div class="datos-alumno">
            <p><strong>Alumno:</strong> <?php echo htmlspecialchars($nombre_completo); ?></p>
            <p><strong>Curso:</strong> <?php echo htmlspecialchars($curso); ?></p>
            <p><strong>ID Sistema:</strong> <?php echo $alumno_id; ?></p>
            <p><strong>Fecha de Emisión:</strong> <?php echo date("d/m/Y"); ?></p>
        </div>

        <table>
            <thead>
                <tr>
                    <th>Módulo / Materia</th>
                    <th class="txt-centro">1° Trim</th>
                    <th class="txt-centro">2° Trim</th>
                    <th class="txt-centro">3° Trim</th>
                    <th class="txt-centro">Promedio Final</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                if (mysqli_num_rows($resultado_notas) > 0) {
                    // El bucle while recorre dinámicamente las materias cargadas para este ID
                    while ($nota = mysqli_fetch_assoc($resultado_notas)) { 
                ?>
                        <tr>
                            <td><?php echo htmlspecialchars($nota['materia']); ?></td>
                            <td class="txt-centro"><?php echo !empty($nota['trimestre1']) ? $nota['trimestre1'] : '--'; ?></td>
                            <td class="txt-centro"><?php echo !empty($nota['trimestre2']) ? $nota['trimestre2'] : '--'; ?></td>
                            <td class="txt-centro"><?php echo !empty($nota['trimestre3']) ? $nota['trimestre3'] : '--'; ?></td>
                            <td class="txt-centro promedio-destacado"><?php echo !empty($nota['promedio']) ? $nota['promedio'] : '--'; ?></td>
                        </tr>
                <?php 
                    } 
                } else { 
                ?>
                    <tr>
                        <td colspan="5" style="text-align: center; color: #718096;">No hay calificaciones registradas para este alumno todavía.</td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
    </div>

    <script>
        document.getElementById('btnPdf').addEventListener('click', function() {
            // Cambiamos temporalmente el título de la pestaña para que el navegador use este nombre al guardar el archivo
            const tituloOriginal = document.title;
            document.title = "Libreta_<?php echo $alumno_id; ?>_<?php echo str_replace(' ', '_', $nombre_completo); ?>";

            // Ejecuta el motor de PDF/Impresión nativo
            window.print();

            // Restablece el título original de la pestaña
            document.title = tituloOriginal;
        });
    </script>

</body>
</html>