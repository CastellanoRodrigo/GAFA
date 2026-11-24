<?php
include('./Modelo/Conexion.php');
include_once('./Modelo/Auth.php');
requerirEscritura($conexion);

$id = (int)($_POST['id'] ?? 0);
$dim = (int)($_POST['dimension'] ?? 0);
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || $id < 1 || $dim < 1 || $dim > 100000) {
    header("Location: ./parcelas.php?error");
    exit;
}
$stmt = $conexion->prepare("UPDATE parcela SET dimension = ? WHERE id_Parcela = ?");
$stmt->bind_param("ii", $dim, $id);
$stmt->execute();
$stmt->close();
header("Location: ./parcelas.php?ok");
exit;
