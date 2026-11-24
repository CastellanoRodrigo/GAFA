<?php
include('./Modelo/Conexion.php');
include_once('./Modelo/Auth.php');
requerirAdmin($conexion);

$nombreFinal = trim($_POST['prov'] ?? '');
if ($nombreFinal === '') {
    header("Location: ./proveedores.php?error");
    exit;
}

/* SI EL NOMBRE ESTA REPETIDO */
$stmt = $conexion->prepare("SELECT id_Proveedor FROM proveedores WHERE proveedor = ?");
$stmt->bind_param("s", $nombreFinal);
$stmt->execute();
$existe = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($existe) {
    mysqli_close($conexion);
    header("Location: ./proveedores.php?error");
    exit;
}
$stmt = $conexion->prepare("INSERT INTO proveedores VALUES (DEFAULT, ?)");
$stmt->bind_param("s", $nombreFinal);
$stmt->execute();
$stmt->close();
mysqli_close($conexion);
header("Location: ./proveedores.php?ok");
exit;
