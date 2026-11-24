<?php
session_start();
// Login correcto: ir al inicio. Cualquier otra visita (incluido "Salir") cierra la sesión.
if (isset($_GET['ok']) && !empty($_SESSION['usuario'])) {
    header("Location: ./inicio.php");
    exit;
}
$_SESSION = [];
session_destroy();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="shortcut icon" href="imgs/GAFA.png">
    <link rel="stylesheet" href="Css/estiloLogin.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <title>GAFA - Ingresar</title>
</head>
<body>
    <form action="Controlador/controlador_login.php" method="POST" class="form-login">
        <h5>Ingresar</h5>
        <input class="cajas" type="text" name="usuario" placeholder="Usuario" required autofocus>
        <input class="cajas" type="password" name="password" placeholder="Contraseña" required>
        <input class="btn" type="submit" name="btningresar" value="Ingresar">
    </form>
    <script>
        const q = window.location.search;
        if (q.includes('err')) {
            Swal.fire({icon: 'error', title: 'Usuario o contraseña incorrectos'});
        } else if (q.includes('ini')) {
            Swal.fire({icon: 'warning', title: 'Usuario inactivo', text: 'Contacte al administrador.'});
        }
    </script>
</body>
</html>
