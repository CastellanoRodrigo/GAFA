<?php
session_start();
include('./Modelo/Conexion.php');
include_once('./Modelo/Empresa.php');
include_once('./Modelo/Recuperacion.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    solicitarRecuperacion($conexion, (string)($_POST['identificador'] ?? ''));
    header("Location: ./recuperar.php?enviado");
    exit;
}
$listo = recuperacionLista($conexion);
$emp = '';
try { $emp = empresa($conexion)['nombre']; } catch (Throwable $e) {}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="shortcut icon" type="image/png" href="imgs/GAFA_icono_oscuro.png">
    <link rel="stylesheet" href="Css/estiloLogin.css">
    <title>GAFA - Recuperar contraseña</title>
</head>
<body>
    <form action="./recuperar.php" method="POST" class="form-login">
        <img class="logo" src="imgs/GAFA_LOGO_lema_claro.png" alt="GAFA">
        <?php if (isset($_GET['enviado'])) { ?>
            <p style="color:#fff;text-align:center;">Si el usuario existe y tiene un mail cargado, te enviamos un enlace para elegir una contraseña nueva. Revisá tu correo (y la carpeta de spam). El enlace vence en <?php echo RECUP_VALIDEZ_MIN; ?> minutos.</p>
            <a class="btn" style="text-align:center;text-decoration:none;display:block;" href="./login.php">Volver a ingresar</a>
        <?php } elseif (!$listo) { ?>
            <p style="color:#fff;text-align:center;">La recuperación por mail todavía no está activada. Pedile a un administrador que cambie tu contraseña.</p>
            <a class="btn" style="text-align:center;text-decoration:none;display:block;" href="./login.php">Volver</a>
        <?php } else { ?>
            <p style="color:#fff;text-align:center;">Escribí tu usuario o el mail asociado a tu cuenta.</p>
            <input class="cajas" type="text" name="identificador" placeholder="Usuario o mail" maxlength="120" required autofocus>
            <input class="btn" type="submit" value="Enviarme el enlace">
            <p style="text-align:center;"><a style="color:#fff;" href="./login.php">Volver</a></p>
        <?php } ?>
    </form>
    <p class="pie-login">Sistema GAFA</p>
</body>
</html>
