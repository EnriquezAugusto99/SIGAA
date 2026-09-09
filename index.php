<?php
session_start();

// Generar token CSRF si no existe
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>EPET N°34 | Inicio de Sesión</title>
    
    <!-- Fuentes -->
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    
    <style>
        /* ===== RESET Y VARIABLES ===== */
        :root {
            --dark-red: #3F070B;
            --deep-crimson: #710A14;
            --dark-burgundy: #180605;
            --dusty-rose: #818582;
            --rustic-red: #8F3C45;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Montserrat', sans-serif;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            position: relative;
            overflow: hidden;
        }

        /* ===== SLIDER DE FONDO ===== */
        .hero-slider {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 0;
        }

        .slide {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-size: cover;
            background-position: center;
            opacity: 0;
            transition: opacity 1.2s ease-in-out;
        }

        .slide.active {
            opacity: 1;
        }

        .slide::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.6);
        }

        /* ===== CONTENEDOR DEL FORMULARIO ===== */
        .login-container {
            position: relative;
            z-index: 10;
            width: 100%;
            max-width: 450px;
            margin: 20px;
            animation: fadeInUp 0.8s ease-out;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .login-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 40px 35px;
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.3);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .logo {
            text-align: center;
            margin-bottom: 25px;
        }

        .logo-img {
            width: 100px;
            height: auto;
            filter: drop-shadow(0 4px 8px rgba(0, 0, 0, 0.2));
        }

        .login-card h1 {
            text-align: center;
            color: var(--dark-red);
            font-size: 1.8rem;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .login-card .subtitle {
            text-align: center;
            color: var(--dusty-rose);
            font-size: 0.85rem;
            margin-bottom: 30px;
            font-weight: 500;
        }

        /* Formulario */
        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            font-weight: 600;
            color: var(--dark-burgundy);
            margin-bottom: 8px;
            font-size: 14px;
        }

        .form-group label i {
            margin-right: 8px;
            color: var(--deep-crimson);
        }

        .form-group input {
            width: 100%;
            padding: 14px 16px;
            border: 2px solid #e0e0e0;
            border-radius: 12px;
            font-size: 15px;
            font-family: 'Montserrat', sans-serif;
            transition: all 0.3s ease;
            background: white;
            color: #333;
        }

        .form-group input:focus {
            outline: none;
            border-color: var(--deep-crimson);
            box-shadow: 0 0 0 3px rgba(113, 10, 20, 0.1);
        }

        .form-group input::placeholder {
            color: #aaa;
        }

        /* Botones */
        .button-group {
            display: flex;
            gap: 15px;
            margin-top: 25px;
            flex-wrap: wrap;
        }

        .btn-primary {
            background: var(--deep-crimson);
            color: white;
            border: none;
            padding: 14px 20px;
            border-radius: 12px;
            font-weight: 700;
            font-size: 15px;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            flex: 1;
        }

        .btn-primary:hover {
            background: var(--dark-red);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(63, 7, 11, 0.3);
        }

        .btn-secondary {
            background: var(--dusty-rose);
            color: white;
            border: none;
            padding: 14px 20px;
            border-radius: 12px;
            font-weight: 700;
            font-size: 15px;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            flex: 1;
        }

        .btn-secondary:hover {
            background: var(--deep-crimson);
            transform: translateY(-2px);
        }

        .btn-outline {
            background: transparent;
            color: var(--deep-crimson);
            border: 2px solid var(--deep-crimson);
            padding: 12px 20px;
            border-radius: 12px;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            text-decoration: none;
            width: 100%;
            margin-top: 15px;
        }

        .btn-outline:hover {
            background: var(--deep-crimson);
            color: white;
            transform: translateY(-2px);
        }

        /* Footer */
        .login-footer {
            margin-top: 25px;
            text-align: center;
            font-size: 12px;
            color: var(--dusty-rose);
        }

        /* Responsive */
        @media (max-width: 550px) {
            .login-card {
                padding: 30px 25px;
            }
            
            .login-card h1 {
                font-size: 1.5rem;
            }
            
            .button-group {
                flex-direction: column;
            }
            
            .logo-img {
                width: 75px;
            }
        }
    </style>
</head>
<body>
    <!-- Slider de fondo -->
    <div class="hero-slider" id="heroSlider">
        <div class="slide active" style="background-image: url('resources/escuela1.jpg');"></div>
        <div class="slide" style="background-image: url('resources/taller.jpg');"></div>
        <div class="slide" style="background-image: url('resources/alumnos.jpg');"></div>
    </div>

    <!-- Formulario de inicio de sesión -->
    <div class="login-container">
        <div class="login-card">
            <div class="logo">
                <img src="resources/logoepet.png" alt="EPET N°34" class="logo-img">
            </div>
            <h1>Bienvenido</h1>
            <div class="subtitle">Sistema de Gestión Académica</div>
            
            <form action="recursos/validar_login.php" method="post">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                
                <div class="form-group">
                    <label for="txt_dni">
                        <i class="fas fa-id-card"></i> DNI
                    </label>
                    <input type="number" name="txt_dni" id="txt_dni" 
                           placeholder="Ingrese su DNI" min="11000000" required>
                </div>

                <div class="form-group">
                    <label for="txt_contraseña">
                        <i class="fas fa-lock"></i> Contraseña
                    </label>
                    <input type="password" name="txt_contraseña" id="txt_contraseña" 
                           placeholder="Ingrese su contraseña" required>
                </div>

                <div class="button-group">
                    <button type="submit" class="btn-primary">
                        <i class="fas fa-sign-in-alt"></i> Iniciar Sesión
                    </button>
                    <button type="reset" class="btn-secondary">
                        <i class="fas fa-eraser"></i> Limpiar
                    </button>
                </div>
            </form>

<div style="text-align: center; margin-top: 15px;">
    <a href="olvide_contraseña.php" style="color: #7a0000; font-size: 13px;">¿Olvidaste tu contraseña?</a>
</div>
            
            <a href="../../index.php" class="btn-outline">
                <i class="fas fa-arrow-left"></i> Volver al Inicio
            </a>
        </div>
    </div>

    <script>
        // ===== SLIDER AUTOMÁTICO PARA EL FONDO =====
        const slides = document.querySelectorAll(".slide");
        let currentSlide = 0;

        function nextSlide() {
            if (slides.length > 0) {
                slides[currentSlide].classList.remove("active");
                currentSlide = (currentSlide + 1) % slides.length;
                slides[currentSlide].classList.add("active");
            }
        }

        if (slides.length > 0) {
            setInterval(nextSlide, 5000);
        }
    </script>
</body>
</html>