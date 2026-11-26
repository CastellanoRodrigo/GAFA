<?php
session_start();
include("../Modelo/Conexion.php");
include_once("../Modelo/Auditoria.php");

if (!isset($_POST['btningresar'])) {
    header("location: ../login.php");
    exit;
}

$usuario = trim($_POST['usuario'] ?? '');
$password = $_POST['password'] ?? '';

if (loginBloqueado($conexion, $usuario)) {
    auditar($conexion, 'login_bloqueado', 'Demasiados intentos fallidos', $usuario);
    mysqli_close($conexion);
    header("location: ../login.php?bloq");
    exit;
}

$stmt = $conexion->prepare("SELECT id_usuario, password, id_EstadoUsuario FROM usuarios WHERE usuario = ?");
$stmt->bind_param("s", $usuario);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Usuario inexistente o contraseña incorrecta: mismo mensaje
$stored = $row['password'] ?? '';
$esHash = $row && strlen($stored) >= 60 && password_get_info($stored)['algo'] !== null;
$valida = false;
if ($row) {
    // Compatibilidad: contraseñas antiguas en texto plano (se migran al primer login exitoso)
    $valida = $esHash ? password_verify($password, $stored) : hash_equals($stored, $password);
}

if (!$valida) {
    registrarIntentoFallido($conexion, $usuario);
    auditar($conexion, 'login_fallido', 'Clave o usuario incorrectos', $usuario);
    mysqli_close($conexion);
    header("location: ../login.php?err");
    exit;
}

if ((int)$row['id_EstadoUsuario'] !== 1) {
    mysqli_close($conexion);
    header("location: ../login.php?ini");
    exit;
}

// Migrar a hash si aún estaba en texto plano (requiere columna password VARCHAR(255), ver migracion_password.sql)
if (!$esHash) {
    $nuevo = password_hash($password, PASSWORD_DEFAULT);
    $up = $conexion->prepare("UPDATE usuarios SET password = ? WHERE id_usuario = ?");
    $up->bind_param("si", $nuevo, $row['id_usuario']);
    $up->execute();
    $up->close();
}

session_regenerate_id(true);
$_SESSION['usuario'] = $usuario;
limpiarIntentos($conexion, $usuario);
auditar($conexion, 'login', 'Ingreso al sistema', $usuario);
mysqli_close($conexion);
header("location: ../login.php?ok");
exit;
