<?php
include('./Modelo/Conexion.php');
include('./Modelo/Auth.php');
requerirAdmin($conexion);

$id = (int)($_POST['id'] ?? 0);
$cat = trim($_POST['cat'] ?? '');
if ($id <= 0 || $cat === '') {
    header("Location: ./categorias.php?error");
    exit;
}
$stmt = $conexion->prepare("SELECT id_Categoria FROM categoria WHERE nombreCategoria = ? AND id_Categoria <> ?");
$stmt->bind_param("si", $cat, $id);
$stmt->execute();
$dup = $stmt->get_result()->fetch_assoc();
$stmt->close();
if ($dup) {
    header("Location: ./categorias.php?error");
    exit;
}
$stmt = $conexion->prepare("UPDATE categoria SET nombreCategoria = ? WHERE id_Categoria = ?");
$stmt->bind_param("si", $cat, $id);
$stmt->execute();
$stmt->close();
mysqli_close($conexion);
header("Location: ./categorias.php?ok");
exit;
