<?php
include('./Modelo/Conexion.php');
include_once('./Modelo/Auth.php');
include_once('./Modelo/Recuperacion.php');
requerirAdmin($conexion);

$id = (int)($_POST['id'] ?? 0);
$usuario = trim($_POST['usuario'] ?? '');
$nombre = nombrePropio($_POST['nombre'] ?? '');
$apellido = nombrePropio($_POST['apellido'] ?? '');
$pass = $_POST['pass'] ?? '';
$tipo = (int)($_POST['tipo'] ?? 0);
$estado = (int)($_POST['estado'] ?? 0);
$conMail = columnaExiste($conexion, 'usuarios', 'email');
$email = mb_strtolower(trim((string)($_POST['email'] ?? '')));

if ($id <= 0 || $usuario === '') {
    header("Location: ./usuarios.php?error");
    exit;
}
$stmt = $conexion->prepare("SELECT id_usuario FROM usuarios WHERE usuario = ? AND id_usuario <> ?");
$stmt->bind_param("si", $usuario, $id);
$stmt->execute();
$dup = $stmt->get_result()->fetch_assoc();
$stmt->close();
if ($dup) {
    header("Location: ./usuarios.php?error");
    exit;
}

if ($conMail && $email !== '') {
    $stmt = $conexion->prepare("SELECT id_usuario FROM usuarios WHERE email = ? AND id_usuario <> ?");
    $stmt->bind_param("si", $email, $id);
    $stmt->execute();
    $dupMail = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($dupMail || !correoValido($email)) {
        header("Location: ./usuarios.php?error=email");
        exit;
    }
}

if ($pass !== '') {
    $hash = password_hash($pass, PASSWORD_DEFAULT);
    $stmt = $conexion->prepare("UPDATE usuarios SET usuario=?, password=?, nombre=?, apellido=?, id_tipoUsuario=?, id_EstadoUsuario=? WHERE id_usuario=?");
    $stmt->bind_param("ssssiii", $usuario, $hash, $nombre, $apellido, $tipo, $estado, $id);
} else {
    // Sin contraseña nueva: se conserva la actual
    $stmt = $conexion->prepare("UPDATE usuarios SET usuario=?, nombre=?, apellido=?, id_tipoUsuario=?, id_EstadoUsuario=? WHERE id_usuario=?");
    $stmt->bind_param("sssiii", $usuario, $nombre, $apellido, $tipo, $estado, $id);
}
$stmt->execute();
$stmt->close();
if ($conMail) {
    $mailBd = $email !== '' ? $email : null;
    $stmt = $conexion->prepare("UPDATE usuarios SET email = ? WHERE id_usuario = ?");
    $stmt->bind_param("si", $mailBd, $id);
    $stmt->execute();
    $stmt->close();
}
mysqli_close($conexion);
header("Location: ./usuarios.php?ok");
exit;
