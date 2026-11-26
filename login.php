<?php
session_start();
// Login correcto: ir al inicio. Cualquier otra visita (incluido "Salir") cierra la sesión.
if (isset($_GET['ok']) && !empty($_SESSION['usuario'])) {
    header("Location: ./inicio.php");
    exit;
}
$_SESSION = [];
session_destroy();
$empLogin = '';
try {
    include('./Modelo/Conexion.php');
    include_once('./Modelo/Empresa.php');
    $empLogin = empresa($conexion)['nombre'];
} catch (Throwable $e) {
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="shortcut icon" type="image/png" href="imgs/GAFA_icono_oscuro.png">
    <link rel="stylesheet" href="Css/estiloLogin.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <title>GAFA - Ingresar</title>
</head>
<body>
    <form action="Controlador/controlador_login.php" method="POST" class="form-login">
        <img class="logo" src="imgs/GAFA_LOGO_lema_claro.png" alt="GAFA - Ganadería y Agricultura de Fácil Administración">
        <input class="cajas" type="text" name="usuario" placeholder="Usuario" required autofocus>
        <input class="cajas" type="password" name="password" placeholder="Contraseña" required>
        <input class="btn" type="submit" name="btningresar" value="Ingresar">
        <p style="text-align:center;margin:8px 0 0;"><a style="color:#fff;" href="./recuperar.php">¿Olvidaste tu contraseña?</a></p>
    </form>
    <p class="pie-login">Sistema GAFA</p>
    <script>
        const q = window.location.search;
        if (q.includes('err')) {
            Swal.fire({icon: 'error', title: 'Usuario o contraseña incorrectos'});
        } else if (q.includes('clave')) {
            Swal.fire({icon: 'success', title: 'Contraseña actualizada', text: 'Ya podés ingresar con la nueva contraseña.'});
        } else if (q.includes('bloq')) {
            Swal.fire({icon: 'warning', title: 'Demasiados intentos', text: 'Por seguridad, esperá 15 minutos o pedile a un administrador que revise tu usuario.'});
        } else if (q.includes('ini')) {
            Swal.fire({icon: 'warning', title: 'Usuario inactivo', text: 'Contacte al administrador.'});
        }
    </script>
</body>
</html>
