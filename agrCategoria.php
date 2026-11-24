<?php
include('./Modelo/Conexion.php');
include('./Modelo/Auth.php');
requerirAdmin($conexion);

$nombreFinal = trim($_POST['cat'] ?? '');
if ($nombreFinal === '') {
    header("Location: ./categorias.php?error");
    exit;
}

/* SI EL NOMBRE ESTA REPETIDO */
$stmt = $conexion->prepare("SELECT id_Categoria FROM categoria WHERE nombreCategoria = ?");
$stmt->bind_param("s", $nombreFinal);
$stmt->execute();
$existe = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($existe) {
    mysqli_close($conexion);
    header("Location: ./categorias.php?error");
    exit;
}
$stmt = $conexion->prepare("INSERT INTO categoria VALUES (DEFAULT, ?)");
$stmt->bind_param("s", $nombreFinal);
$stmt->execute();
$stmt->close();
mysqli_close($conexion);
header("Location: ./categorias.php?ok");
exit;
