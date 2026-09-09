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

// Solo estudiantes (rol 3)
if($_SESSION['rol'] != 'Estudiante'){
    echo '<script>alert("No tiene permisos para acceder a esta sección"); window.location="../../recursos/panel.php";</script>';
    exit();
}

include '../../recursos/conexion.php';

$dni_alumno = $_SESSION["dni"];

// Obtener datos del alumno
$query_alumno = "SELECT u.Nombre, u.Apellido, u.id_curso, c.curso, c.division 
                 FROM usuario u
                 INNER JOIN curso c ON u.id_curso = c.ID_curso
                 WHERE u.DNI_U = '$dni_alumno'";
$res_alumno = mysqli_query($con, $query_alumno);
$alumno = mysqli_fetch_assoc($res_alumno);

$nombre_completo = $alumno['Apellido'] . ', ' . $alumno['Nombre'];
$curso_nombre = $alumno['curso'] . '° "' . $alumno['division'] . '"';

// Verificar si el usuario ya tiene credenciales WebAuthn registradas
$query_cred = "SELECT COUNT(*) as total FROM webauthn_credentials WHERE dni_usuario = '$dni_alumno'";
$res_cred = mysqli_query($con, $query_cred);
$tiene_credencial = mysqli_fetch_assoc($res_cred)['total'] > 0;
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>QR de Asistencia - EPET N°34</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: 'Montserrat', sans-serif; 
            background: #f5f5f5; 
            padding: 20px; 
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
        }
        .container { 
            max-width: 500px; 
            width: 100%;
            margin: 0 auto; 
        }
        .card { 
            background: white; 
            border-radius: 16px; 
            padding: 30px; 
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            text-align: center;
            user-select: none;
            -webkit-user-select: none;
            -moz-user-select: none;
            -ms-user-select: none;
        }
        .header { 
            background: linear-gradient(135deg, #3F070B 0%, #710A14 100%); 
            border-radius: 12px; 
            padding: 20px; 
            margin-bottom: 25px; 
            color: white; 
        }
        .header h1 { font-size: 22px; }
        .header p { font-size: 12px; opacity: 0.8; margin-top: 5px; }
        
        .info-alumno {
            background: #f0f0f0;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        .info-alumno strong { color: #710A14; }

        .qr-container {
            background: white;
            padding: 20px;
            border-radius: 12px;
            border: 2px dashed #ddd;
            margin: 20px 0;
            min-height: 250px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }
        .qr-container img { 
            max-width: 250px; 
            width: 100%;
            height: auto;
            pointer-events: none;
            -webkit-touch-callout: none;
            -webkit-user-select: none;
            transition: opacity 0.3s;
        }

        .qr-timer {
            font-size: 12px;
            color: #666;
            margin-top: 10px;
        }

        .btn-biometrico {
            background: #710A14;
            color: white;
            border: none;
            padding: 16px 24px;
            border-radius: 12px;
            font-size: 18px;
            font-weight: 600;
            cursor: pointer;
            width: 100%;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
        }
        .btn-biometrico:hover {
            background: #3F070B;
            transform: translateY(-2px);
        }
        .btn-biometrico:disabled {
            background: #999;
            cursor: not-allowed;
            transform: none;
        }

        .btn-volver {
            display: inline-block;
            margin-top: 20px;
            padding: 10px 24px;
            background: #666;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
        }
        .btn-volver:hover { background: #555; }

        .estado-auth {
            padding: 12px;
            border-radius: 8px;
            margin: 15px 0;
            font-weight: 500;
        }
        .estado-auth.exito {
            background: #d4edda;
            color: #155724;
            border-left: 4px solid #28a745;
        }
        .estado-auth.error {
            background: #f8d7da;
            color: #721c24;
            border-left: 4px solid #dc3545;
        }
        .estado-auth.info {
            background: #e8f0fe;
            color: #3F070B;
            border-left: 4px solid #710A14;
        }

        .badge-dispositivo {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        .badge-movil { background: #d4edda; color: #155724; }
        .badge-pc { background: #fff3cd; color: #856404; }

        .footer-info {
            font-size: 11px;
            color: #999;
            margin-top: 20px;
        }

        .loading-spinner {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid #f3f3f3;
            border-top: 3px solid #710A14;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin-right: 10px;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        @media (max-width: 480px) {
            .card { padding: 20px; }
            .header h1 { font-size: 18px; }
            .qr-container img { max-width: 180px; }
        }
    </style>
</head>
<body>

<div class="container">
    <div class="card" id="mainCard">
        <div class="header">
            <h1>QR de Asistencia</h1>
        </div>

        <div class="info-alumno">
            <strong><?= htmlspecialchars($nombre_completo) ?></strong> | 
            DNI: <?= $dni_alumno ?> | 
            Curso: <?= htmlspecialchars($curso_nombre) ?>
            <span id="badgeDispositivo"></span>
        </div>

        <div id="contenedor-estado"></div>

        <?php if(!$tiene_credencial): ?>
        <div id="seccion-registro">
            <p style="color: #666; font-size: 14px; margin-bottom: 15px;">
                Primero debes registrar tu huella o rostro en tu dispositivo
            </p>
            <button id="btnRegistrar" class="btn-biometrico" onclick="registrarBiometria()">
                Registrar Huella / Rostro
            </button>
            <p style="font-size: 11px; color: #999; margin-top: 8px;">
                Usa el sensor de huella o cámara frontal de tu dispositivo
            </p>
        </div>
        <?php else: ?>
        <div id="seccion-autenticacion">
            <p style="color: #666; font-size: 14px; margin-bottom: 15px;">
                Autenticate con tu huella o reconocimiento facial
            </p>
            <button id="btnAutenticar" class="btn-biometrico" onclick="iniciarAutenticacion()">
                Autenticar con Huella / Rostro
            </button>
        </div>
        <?php endif; ?>

        <div id="seccion-qr" style="display: none;">
            <div class="qr-container" id="qrContainer">
                <img id="qrImagen" src="" alt="QR de Asistencia">
                <div class="qr-timer" id="qrTimer">Actualizando en <span id="contadorSegundos">60</span>s</div>
            </div>
        </div>

        <a href="../../recursos/panel.php" class="btn-volver">Volver al Panel</a>
    </div>
</div>

<script>
// ============================================
// BLOQUEAR CAPTURA DE PANTALLA
// ============================================
document.addEventListener('keydown', function(e) {
    if (e.key === 'PrintScreen' || 
        (e.ctrlKey && e.shiftKey && (e.key === 's' || e.key === 'S')) ||
        (e.ctrlKey && e.key === 'p')) {
        e.preventDefault();
        return false;
    }
});

document.addEventListener('contextmenu', function(e) {
    e.preventDefault();
    return false;
});

if (document.addEventListener) {
    document.addEventListener('visibilitychange', function() {
        if (document.hidden) {
            const qrImg = document.getElementById('qrImagen');
            if (qrImg) {
                qrImg.style.opacity = '0.2';
                setTimeout(function() {
                    qrImg.style.opacity = '1';
                }, 1000);
            }
        }
    });
}

// ============================================
// DETECCIÓN DE DISPOSITIVO
// ============================================
function esDispositivoMovil() {
    const userAgent = navigator.userAgent || navigator.vendor || window.opera;
    const esMovil = /android|webos|iphone|ipad|ipod|blackberry|windows phone/i.test(userAgent);
    const esPantallaPequeña = window.innerWidth <= 1024;
    return esMovil && esPantallaPequeña;
}

// ============================================
// VERIFICAR SOPORTE WEBAUTHN
// ============================================
function soportaWebAuthn() {
    return window.PublicKeyCredential !== undefined;
}

// ============================================
// REGISTRAR BIOMETRÍA (WebAuthn)
// ============================================
async function registrarBiometria() {
    const btn = document.getElementById('btnRegistrar');
    const estadoDiv = document.getElementById('contenedor-estado');
    const dni = <?= json_encode($dni_alumno) ?>;
    const nombre = <?= json_encode($nombre_completo) ?>;
    
    if (!esDispositivoMovil()) {
        estadoDiv.innerHTML = `
            <div class="estado-auth error">
                <strong>Dispositivo no compatible</strong><br>
                Para registrar tu huella o rostro debes usar un dispositivo móvil con sensor biométrico.
            </div>
        `;
        return;
    }
    
    if (!soportaWebAuthn()) {
        estadoDiv.innerHTML = `
            <div class="estado-auth error">
                <strong>Navegador no compatible</strong><br>
                Usa Safari en iPhone, Chrome o Edge actualizado.
            </div>
        `;
        return;
    }
    
    btn.disabled = true;
    btn.innerHTML = 'Preparando registro...';
    
    estadoDiv.innerHTML = `
        <div class="estado-auth info">
            <strong>Solicitando registro biométrico...</strong><br>
            Acepta la solicitud en tu dispositivo.
        </div>
    `;
    
    try {
        const response = await fetch('ajax_webauthn.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'action=register_start&dni=' + dni + '&nombre=' + encodeURIComponent(nombre)
        });
        
        const data = await response.json();
        
        if (!data.success) {
            throw new Error(data.message || 'Error al iniciar registro');
        }
        
        const publicKey = {
            challenge: Uint8Array.from(atob(data.options.challenge), function(c) { return c.charCodeAt(0); }),
            rp: data.options.rp,
            user: {
                id: Uint8Array.from(atob(data.options.user.id), function(c) { return c.charCodeAt(0); }),
                name: data.options.user.name,
                displayName: data.options.user.displayName
            },
            pubKeyCredParams: data.options.pubKeyCredParams.map(function(param) {
                return {
                    type: param.type,
                    alg: parseInt(param.alg)
                };
            }),
            authenticatorSelection: data.options.authenticatorSelection || {
                authenticatorAttachment: 'platform',
                residentKey: 'preferred',
                userVerification: 'required'
            },
            timeout: 60000,
            attestation: 'none',
            excludeCredentials: data.options.excludeCredentials || []
        };
        
        const credential = await navigator.credentials.create({ publicKey: publicKey });
        
        if (!credential) {
            throw new Error('No se pudo crear la credencial');
        }
        
        const credentialData = {
            id: credential.id,
            rawId: btoa(String.fromCharCode.apply(null, new Uint8Array(credential.rawId))),
            type: credential.type,
            response: {
                clientDataJSON: btoa(String.fromCharCode.apply(null, new Uint8Array(credential.response.clientDataJSON))),
                attestationObject: btoa(String.fromCharCode.apply(null, new Uint8Array(credential.response.attestationObject)))
            }
        };
        
        estadoDiv.innerHTML = `
            <div class="estado-auth info">
                <strong>Guardando credencial...</strong>
            </div>
        `;
        
        const saveResponse = await fetch('ajax_webauthn.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'action=register_complete&dni=' + dni + '&credential=' + encodeURIComponent(JSON.stringify(credentialData))
        });
        
        const saveData = await saveResponse.json();
        
        if (saveData.success) {
            estadoDiv.innerHTML = `
                <div class="estado-auth exito">
                    <strong>Registro biométrico exitoso</strong><br>
                    Tu huella/rostro ha sido registrado correctamente.
                </div>
            `;
            btn.innerHTML = 'Registrado';
            
            setTimeout(function() {
                location.reload();
            }, 2000);
            
        } else {
            throw new Error(saveData.message || 'Error al guardar credencial');
        }
        
    } catch (error) {
        console.error('Error:', error);
        
        var mensajeError = 'No se pudo completar el registro.';
        var mensajeAyuda = 'Intenta nuevamente o contacta al administrador.';
        
        var msg = (error.message || '').toLowerCase();
        
        if (msg.indexOf('cancel') !== -1 || msg.indexOf('user cancelled') !== -1) {
            mensajeError = 'Operación cancelada por el usuario.';
            mensajeAyuda = 'Puedes intentarlo nuevamente cuando quieras.';
        } else if (msg.indexOf('not allowed') !== -1 || msg.indexOf('permission') !== -1) {
            mensajeError = 'No se permitió el acceso al sensor biométrico.';
            mensajeAyuda = 'Verifica los permisos de tu navegador.';
        } else if (msg.indexOf('not supported') !== -1 || msg.indexOf('unsupported') !== -1) {
            mensajeError = 'Tu dispositivo no soporta autenticación biométrica.';
            mensajeAyuda = 'Asegúrate de tener Face ID o Touch ID configurado.';
        } else if (msg.indexOf('timeout') !== -1 || msg.indexOf('timed out') !== -1) {
            mensajeError = 'Tiempo de espera agotado.';
            mensajeAyuda = 'Intenta nuevamente y asegúrate de usar el sensor.';
        } else if (msg.indexOf('already') !== -1 || msg.indexOf('exist') !== -1) {
            mensajeError = 'Ya tienes una huella registrada.';
            mensajeAyuda = 'Si quieres cambiarla, contacta al administrador.';
        } else if (msg.indexOf('network') !== -1 || msg.indexOf('connection') !== -1) {
            mensajeError = 'Error de conexión.';
            mensajeAyuda = 'Verifica tu conexión a internet.';
        }
        
        estadoDiv.innerHTML = `
            <div class="estado-auth error">
                <strong>${mensajeError}</strong><br>
                <span style="font-size: 13px;">${mensajeAyuda}</span>
            </div>
        `;
        btn.disabled = false;
        btn.innerHTML = 'Registrar Huella / Rostro';
    }
}

// ============================================
// INICIAR AUTENTICACIÓN BIOMÉTRICA
// ============================================
async function iniciarAutenticacion() {
    const btn = document.getElementById('btnAutenticar');
    const estadoDiv = document.getElementById('contenedor-estado');
    const dni = <?= json_encode($dni_alumno) ?>;
    
    if (!esDispositivoMovil()) {
        estadoDiv.innerHTML = `
            <div class="estado-auth error">
                <strong>Dispositivo no compatible</strong><br>
                Para autenticarte debes usar un dispositivo móvil con sensor biométrico.
            </div>
        `;
        return;
    }
    
    if (!soportaWebAuthn()) {
        estadoDiv.innerHTML = `
            <div class="estado-auth error">
                <strong>Navegador no compatible</strong><br>
                Usa Safari en iPhone, Chrome o Edge actualizado.
            </div>
        `;
        return;
    }
    
    btn.disabled = true;
    btn.innerHTML = 'Autenticando...';
    
    estadoDiv.innerHTML = `
        <div class="estado-auth info">
            <strong>Esperando autenticación biométrica...</strong><br>
            Usa tu huella o reconocimiento facial.
        </div>
    `;
    
    try {
        const response = await fetch('ajax_webauthn.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'action=login_start&dni=' + dni
        });
        
        const data = await response.json();
        
        if (!data.success) {
            throw new Error(data.message || 'Error al iniciar autenticación');
        }
        
        if (!data.options || !data.options.allowCredentials || data.options.allowCredentials.length === 0) {
            throw new Error('No tienes una huella/rostro registrado.');
        }
        
        const publicKey = {
            challenge: Uint8Array.from(atob(data.options.challenge), function(c) { return c.charCodeAt(0); }),
            allowCredentials: data.options.allowCredentials.map(function(cred) {
                return {
                    id: Uint8Array.from(atob(cred.id), function(c) { return c.charCodeAt(0); }),
                    type: cred.type,
                    transports: cred.transports || []
                };
            }),
            userVerification: 'required',
            timeout: 60000,
            rpId: data.options.rpId || window.location.hostname
        };
        
        const credential = await navigator.credentials.get({ publicKey: publicKey, mediation: 'required' });
        
        if (!credential) {
            throw new Error('Autenticación cancelada o fallida');
        }
        
        var authData = {
            id: credential.id,
            rawId: btoa(String.fromCharCode.apply(null, new Uint8Array(credential.rawId))),
            type: credential.type,
            response: {
                clientDataJSON: btoa(String.fromCharCode.apply(null, new Uint8Array(credential.response.clientDataJSON))),
                authenticatorData: btoa(String.fromCharCode.apply(null, new Uint8Array(credential.response.authenticatorData))),
                signature: btoa(String.fromCharCode.apply(null, new Uint8Array(credential.response.signature))),
                userHandle: credential.response.userHandle ? btoa(String.fromCharCode.apply(null, new Uint8Array(credential.response.userHandle))) : null
            }
        };
        
        const verifyResponse = await fetch('ajax_webauthn.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'action=login_complete&dni=' + dni + '&auth_data=' + encodeURIComponent(JSON.stringify(authData))
        });
        
        const verifyData = await verifyResponse.json();
        
        if (verifyData.success) {
            estadoDiv.innerHTML = `
                <div class="estado-auth exito">
                    <strong>Autenticación exitosa</strong>
                </div>
            `;
            
            document.getElementById('seccion-autenticacion').style.display = 'none';
            document.getElementById('seccion-qr').style.display = 'block';
            
            generarQR();
            iniciarActualizacionQR();
            
        } else {
            throw new Error(verifyData.message || 'Error al verificar autenticación');
        }
        
    } catch (error) {
        console.error('Error:', error);
        
        var mensajeError = 'No se pudo autenticar.';
        var mensajeAyuda = 'Intenta nuevamente.';
        
        var msg = (error.message || '').toLowerCase();
        
        if (msg.indexOf('cancel') !== -1 || msg.indexOf('user cancelled') !== -1) {
            mensajeError = 'Autenticación cancelada por el usuario.';
            mensajeAyuda = 'Puedes intentarlo nuevamente cuando quieras.';
        } else if (msg.indexOf('not allowed') !== -1 || msg.indexOf('permission') !== -1) {
            mensajeError = 'No se permitió el acceso al sensor biométrico.';
            mensajeAyuda = 'Verifica los permisos de tu navegador.';
        } else if (msg.indexOf('not supported') !== -1 || msg.indexOf('unsupported') !== -1) {
            mensajeError = 'Tu dispositivo no soporta autenticación biométrica.';
            mensajeAyuda = 'Asegúrate de tener Face ID o Touch ID configurado.';
        } else if (msg.indexOf('timeout') !== -1 || msg.indexOf('timed out') !== -1) {
            mensajeError = 'Tiempo de espera agotado.';
            mensajeAyuda = 'Intenta nuevamente y asegúrate de usar el sensor.';
        } else if (msg.indexOf('not found') !== -1 || msg.indexOf('no credentials') !== -1) {
            mensajeError = 'No se encontró tu huella registrada.';
            mensajeAyuda = 'Registra tu huella primero.';
        } else if (msg.indexOf('network') !== -1 || msg.indexOf('connection') !== -1) {
            mensajeError = 'Error de conexión.';
            mensajeAyuda = 'Verifica tu conexión a internet.';
        }
        
        estadoDiv.innerHTML = `
            <div class="estado-auth error">
                <strong>${mensajeError}</strong><br>
                <span style="font-size: 13px;">${mensajeAyuda}</span>
            </div>
        `;
        btn.disabled = false;
        btn.innerHTML = 'Autenticar con Huella / Rostro';
    }
}

// ============================================
// GENERAR QR
// ============================================
function generarQR() {
    const dni = <?= json_encode($dni_alumno) ?>;
    const fecha = new Date();
    const fechaStr = fecha.toISOString().split('T')[0];
    const horaStr = fecha.toTimeString().split(' ')[0];
    
    const datosQR = {
        dni: dni,
        fecha: fechaStr,
        hora: horaStr,
        timestamp: fecha.getTime()
    };
    
    const datosString = encodeURIComponent(JSON.stringify(datosQR));
    const urlQR = 'https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=' + datosString + '&bgcolor=FFFFFF&color=3F070B&margin=10';
    
    const img = document.getElementById('qrImagen');
    img.src = urlQR;
    img.alt = 'QR de Asistencia';
    img.style.display = 'block';
    
    document.getElementById('contadorSegundos').textContent = '60';
}

// ============================================
// ACTUALIZACIÓN AUTOMÁTICA DEL QR
// ============================================
var intervaloQR = null;
var segundosRestantes = 60;

function iniciarActualizacionQR() {
    segundosRestantes = 60;
    actualizarContador();
    
    if (intervaloQR) {
        clearInterval(intervaloQR);
    }
    
    intervaloQR = setInterval(function() {
        segundosRestantes--;
        
        if (segundosRestantes <= 0) {
            generarQR();
            segundosRestantes = 60;
        }
        
        actualizarContador();
    }, 1000);
}

function actualizarContador() {
    const contador = document.getElementById('contadorSegundos');
    if (contador) {
        contador.textContent = segundosRestantes;
        if (segundosRestantes <= 10) {
            contador.style.color = '#d32f2f';
            contador.style.fontWeight = 'bold';
        } else {
            contador.style.color = '';
            contador.style.fontWeight = '';
        }
    }
}

// ============================================
// INICIALIZACIÓN
// ============================================
document.addEventListener('DOMContentLoaded', function() {
    const esMovil = esDispositivoMovil();
    const badge = document.getElementById('badgeDispositivo');
    
    if (badge) {
        badge.innerHTML = '<span class="badge-dispositivo ' + (esMovil ? 'badge-movil' : 'badge-pc') + '">' + (esMovil ? 'Movil' : 'PC') + '</span>';
    }
    
    if (!esMovil) {
        const estadoDiv = document.getElementById('contenedor-estado');
        estadoDiv.innerHTML = `
            <div class="estado-auth error">
                <strong>Acceso desde PC</strong><br>
                Para generar el QR de asistencia debes usar un dispositivo móvil con sensor biométrico.
            </div>
        `;
        var btnRegistrar = document.getElementById('btnRegistrar');
        var btnAutenticar = document.getElementById('btnAutenticar');
        if (btnRegistrar) btnRegistrar.disabled = true;
        if (btnAutenticar) btnAutenticar.disabled = true;
    }
});
</script>
</body>
</html>
<?php mysqli_close($con); ?>