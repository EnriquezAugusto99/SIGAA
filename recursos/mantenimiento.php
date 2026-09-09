<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mantenimiento - EPET N°34</title>
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
            --success: #2e7d32;
            --warning: #ed6c02;
            --error: #d32f2f;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Montserrat', 'Segoe UI', Roboto, sans-serif;
            background: var(--light-bg);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }

        .container {
            max-width: 500px;
            width: 100%;
            margin: 0 auto;
        }

        .card {
            background: var(--card-bg);
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            overflow: hidden;
            text-align: center;
        }

        .card-header {
            background: var(--dark-red);
            padding: 25px 20px;
        }

        .card-header h1 {
            color: white;
            font-size: 24px;
            font-weight: 600;
            margin: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .card-header h1 i {
            font-size: 28px;
        }

        .card-body {
            padding: 40px 30px;
        }

        .icono {
            font-size: 64px;
            margin-bottom: 20px;
        }

        .mensaje {
            font-size: 18px;
            color: var(--text-dark);
            margin-bottom: 15px;
            line-height: 1.5;
        }

        .horario {
            font-size: 28px;
            font-weight: 700;
            color: var(--deep-crimson);
            margin: 15px 0;
        }

        .subtitulo {
            font-size: 14px;
            color: var(--text-muted);
            margin-bottom: 30px;
        }

        .btn-volver {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: var(--deep-crimson);
            color: white;
            text-decoration: none;
            padding: 12px 28px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 14px;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
        }

        .btn-volver:hover {
            background: var(--dark-red);
            transform: translateY(-2px);
        }

        .footer {
            background: var(--light-bg);
            padding: 15px 20px;
            border-top: 1px solid var(--border-color);
            font-size: 12px;
            color: var(--text-muted);
        }

        @media (max-width: 480px) {
            .card-body {
                padding: 30px 20px;
            }
            .mensaje {
                font-size: 16px;
            }
            .horario {
                font-size: 24px;
            }
            .card-header h1 {
                font-size: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="card-header">
                <h1>
                    <span></span>
                    Sistema Escolar
                </h1>
            </div>
            <div class="card-body">
                <div class="icono"></div>
                <div class="mensaje">
                    <strong>Modulo Inhabilitado</strong>
                </div>
                <div class="subtitulo">
                    Modulo inhabilitado por mejoras en el sistema.
                </div>
                <a href="javascript:history.back()" class="btn-volver">
                    ← Volver al módulo anterior
                </a>
            </div>
            <div class="footer">
                EPET N°34 - Sistema de Gestión Académica
            </div>
        </div>
    </div>
</body>
</html>