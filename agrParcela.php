<?php
include('./Modelo/Conexion.php');
include_once('./Modelo/Auth.php');
requerirEscritura($conexion);

$dim = (int)($_POST['dimension'] ?? 0);
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || $dim < 1 || $dim > 100000) {
    header("Location: ./parcelas.php?error");
    exit;
}
$stmt = $conexion->prepare("INSERT INTO parcela (dimension) VALUES (?)");
$stmt->bind_param("i", $dim);
$stmt->execute();
$stmt->close();
header("Location: ./parcelas.php?ok");
exit;
