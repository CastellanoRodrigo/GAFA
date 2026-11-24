<?php
include('./Modelo/Conexion.php');
include_once('./Modelo/Auth.php');
include_once('./Modelo/Alquileres.php');
requerirEscritura($conexion);

$idCobro = (int)($_POST['cobro'] ?? 0);
$accion = $_POST['accion'] ?? '';

$stmt = $conexion->prepare("SELECT id_Cobro, id_DetalleAlquiler FROM cobrosalquiler WHERE id_Cobro = ?");
$stmt->bind_param("i", $idCobro);
$stmt->execute();
$cobro = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$cobro || !in_array($accion, ['cobrar', 'deshacer'], true)) {
    mysqli_close($conexion);
    header("Location: ./alquileres.php?error");
    exit;
}

$contrato = (int)$cobro['id_DetalleAlquiler'];
if ($accion === 'cobrar') {
    $fecha = $_POST['fechaPago'] ?? '';
    if (!fechaValidaAlq($fecha)) {
        $fecha = date('Y-m-d');
    }
    $stmt = $conexion->prepare("UPDATE cobrosalquiler SET fechaPago = ? WHERE id_Cobro = ?");
    $stmt->bind_param("si", $fecha, $idCobro);
} else {
    $stmt = $conexion->prepare("UPDATE cobrosalquiler SET fechaPago = NULL WHERE id_Cobro = ?");
    $stmt->bind_param("i", $idCobro);
}
$stmt->execute();
$stmt->close();
mysqli_close($conexion);
header("Location: ./cobrosAlquiler.php?no=" . $contrato . "&ok");
exit;
