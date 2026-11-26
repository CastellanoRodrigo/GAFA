<?php
session_start();
header('Referrer-Policy: no-referrer');
header('Cache-Control: no-store');
include('./Modelo/Conexion.php');
include_once('./Modelo/Empresa.php');
include_once('./Modelo/Recuperacion.php');

$token = (string)($_POST['t'] ?? $_GET['t'] ?? '');
$pedido = buscarRecuperacion($conexion, $token);
$error = '';

if ($pedido && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $p1 = (string)($_POST['pass'] ?? '');
    $p2 = (string)($_POST['pass2'] ?? '');
    if (mb_strlen($p1) < CLAVE_MINIMA) {
        $error = 'La contraseña debe tener al menos ' . CLAVE_MINIMA . ' caracteres.';
    } elseif ($p1 !== $p2) {
        $error = 'Las dos contraseñas no coinciden.';
    } else {
        $hash = password_hash($p1, PASSWORD_DEFAULT);
        $uid = (int)$pedido['id_usuario'];
        $s = $conexion->prepare("UPDATE usuarios SET password = ? WHERE id_usuario = ?");
        $s->bind_param('si', $hash, $uid);
        $s->execute();
        $s->close();
        // El enlace (y cualquier otro pendiente) deja de servir; se levanta el bloqueo por intentos
        $s = $conexion->prepare("UPDATE recuperacion SET usado = 1 WHERE id_usuario = ?");
        $s->bind_param('i', $uid);
        $s->execute();
        $s->close();
        limpiarIntentos($conexion, $pedido['usuario']);
        auditar($conexion, 'clave_restablecida', 'Contraseña cambiada con el enlace de recuperación', $pedido['usuario']);
        header("Location: ./login.php?clave");
        exit;
    }
}
$emp = '';
try { $emp = empresa($conexion)['nombre']; } catch (Throwable $e) {}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="referrer" content="no-referrer">
    <link rel="shortcut icon" type="image/png" href="imgs/GAFA_icono_oscuro.png">
    <link rel="stylesheet" href="Css/estiloLogin.css">
    <title>GAFA - Nueva contraseña</title>
</head>
<body>
    <form action="./restablecer.php" method="POST" class="form-login">
        <img class="logo" src="imgs/GAFA_LOGO_lema_claro.png" alt="GAFA">
        <?php if (!$pedido) { ?>
            <p style="color:#fff;text-align:center;">Este enlace no es válido: ya se usó o venció. Pedí uno nuevo.</p>
            <a class="btn" style="text-align:center;text-decoration:none;display:block;" href="./recuperar.php">Pedir un enlace nuevo</a>
        <?php } else { ?>
            <p style="color:#fff;text-align:center;">Elegí una contraseña nueva para <b><?php echo htmlspecialchars($pedido['usuario'], ENT_QUOTES, 'UTF-8'); ?></b> (mínimo <?php echo CLAVE_MINIMA; ?> caracteres).</p>
            <?php if ($error !== '') { ?><p style="color:#ffd7d0;text-align:center;"><b><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></b></p><?php } ?>
            <input type="hidden" name="t" value="<?php echo htmlspecialchars($token, ENT_QUOTES, 'UTF-8'); ?>">
            <input class="cajas" type="password" name="pass" placeholder="Contraseña nueva" minlength="<?php echo CLAVE_MINIMA; ?>" autocomplete="new-password" required autofocus>
            <input class="cajas" type="password" name="pass2" placeholder="Repetir contraseña" minlength="<?php echo CLAVE_MINIMA; ?>" autocomplete="new-password" required>
            <input class="btn" type="submit" value="Guardar contraseña">
        <?php } ?>
    </form>
    <?php if ($emp !== '') { ?><p class="pie-login">Sistema GAFA · <?php echo htmlspecialchars($emp, ENT_QUOTES, 'UTF-8'); ?></p><?php } ?>
</body>
</html>
