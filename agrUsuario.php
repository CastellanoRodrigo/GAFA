<?php
include('./Modelo/Conexion.php');
include_once('./Modelo/Auth.php');
requerirAdmin($conexion);

$usuario = trim($_POST['usuario'] ?? '');
$nombre = nombrePropio($_POST['nombre'] ?? '');
$apellido = nombrePropio($_POST['apellido'] ?? '');
$pass = $_POST['pass'] ?? '';
$tipo = (int)($_POST['tipo'] ?? 0);
$estado = (int)($_POST['estado'] ?? 0);

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

if ($existe) {
    mysqli_close($conexion);
    header("Location: ./usuarios.php?error");
    exit;
}

$hash = password_hash($pass, PASSWORD_DEFAULT);
$stmt = $conexion->prepare("INSERT INTO usuarios VALUES (DEFAULT, ?, ?, ?, ?, ?, ?)");
$stmt->bind_param("ssssii", $usuario, $hash, $nombre, $apellido, $tipo, $estado);
$stmt->execute();
$stmt->close();
mysqli_close($conexion);
header("Location: ./usuarios.php?ok");
exit;
