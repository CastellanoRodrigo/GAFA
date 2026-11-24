<?php
include('./Modelo/Conexion.php');
include_once('./Modelo/Auth.php');
include_once('./Modelo/Notificaciones.php');
requerirEscritura($conexion);

$id = (int)($_POST['contrato'] ?? 0);
$email = trim($_POST['email'] ?? '');
$telefono = trim($_POST['telefono'] ?? '');

$volver = function (string $q) use ($conexion, $id) {
    mysqli_close($conexion);
    header("Location: ./cobrosAlquiler.php?no=" . $id . $q);
    exit;
};

if (!notificacionesListas($conexion)) {
    $volver('&errorContacto=tablas');
}
if ($email !== '' && !emailValido($email)) {
    $volver('&errorContacto=email');
}
if ($telefono !== '' && !telefonoValido(normalizarTelefono($telefono))) {
    $volver('&errorContacto=telefono');
}

$emailDb = $email === '' ? null : $email;
$telDb = $telefono === '' ? null : $telefono;
$stmt = $conexion->prepare("UPDATE detallealquiler SET email = ?, telefono = ? WHERE id_DetalleAlquiler = ?");
$stmt->bind_param("ssi", $emailDb, $telDb, $id);
$stmt->execute();
$stmt->close();
$volver('&ok');
