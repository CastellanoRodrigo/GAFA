<?php
include('./Modelo/Conexion.php');
include_once('./Modelo/Auth.php');
requerirAdmin($conexion);

$id = (int)($_POST['id'] ?? 0);
$prov = trim($_POST['prov'] ?? '');
if ($id <= 0 || $prov === '') {
    header("Location: ./proveedores.php?error");
    exit;
}
// Nombre duplicado en otro proveedor
$stmt = $conexion->prepare("SELECT id_Proveedor FROM proveedores WHERE proveedor = ? AND id_Proveedor <> ?");
$stmt->bind_param("si", $prov, $id);
$stmt->execute();
$dup = $stmt->get_result()->fetch_assoc();
$stmt->close();
if ($dup) {
    header("Location: ./proveedores.php?error");
    exit;
}
$stmt = $conexion->prepare("UPDATE proveedores SET proveedor = ? WHERE id_Proveedor = ?");
$stmt->bind_param("si", $prov, $id);
$stmt->execute();
$stmt->close();
mysqli_close($conexion);
header("Location: ./proveedores.php?ok");
exit;
