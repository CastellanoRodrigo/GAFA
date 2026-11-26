<?php
include('./Modelo/Conexion.php');
include_once('./Modelo/Auth.php');
include_once('./Modelo/Auditoria.php');
include_once('./Modelo/Recuperacion.php');
requerirAdmin($conexion);

$usuario = trim($_POST['usuario'] ?? '');
$nombre = nombrePropio($_POST['nombre'] ?? '');
$apellido = nombrePropio($_POST['apellido'] ?? '');
$pass = $_POST['pass'] ?? '';
$tipo = (int)($_POST['tipo'] ?? 0);
$estado = (int)($_POST['estado'] ?? 0);
$conMail = columnaExiste($conexion, 'usuarios', 'email');
$email = mb_strtolower(trim((string)($_POST['email'] ?? '')));
if ($conMail && !correoValido($email)) {
    header("Location: ./usuarios.php?error=email");
    exit;
}

if ($usuario === '' || $pass === '') {
    header("Location: ./usuarios.php?error");
    exit;
}

/* SI EL USUARIO ESTA REPETIDO */
$stmt = $conexion->prepare("SELECT id_usuario FROM usuarios WHERE usuario = ?");
$stmt->bind_param("s", $usuario);
$stmt->execute();
$existe = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($conMail && !$existe) {
    $stmt = $conexion->prepare("SELECT id_usuario FROM usuarios WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $existe = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}
if ($existe) {
    mysqli_close($conexion);
    header("Location: ./usuarios.php?error");
    exit;
}

$hash = password_hash($pass, PASSWORD_DEFAULT);
if ($conMail) {
    $stmt = $conexion->prepare("INSERT INTO usuarios (usuario, password, nombre, apellido, id_tipoUsuario, id_EstadoUsuario, email) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssiis", $usuario, $hash, $nombre, $apellido, $tipo, $estado, $email);
} else {
    $stmt = $conexion->prepare("INSERT INTO usuarios (usuario, password, nombre, apellido, id_tipoUsuario, id_EstadoUsuario) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssii", $usuario, $hash, $nombre, $apellido, $tipo, $estado);
}
$stmt->execute();
$stmt->close();
auditar($conexion, 'usuario_creado', $usuario . ' (rol ' . $tipo . ')');
mysqli_close($conexion);
header("Location: ./usuarios.php?ok");
exit;
