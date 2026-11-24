<?php
include('./Modelo/Conexion.php');
include_once('./Modelo/Auth.php');
requerirLogin();

$proyecto = (int)($_POST['cmbNombre'] ?? 0);
$txtFechaInicio = $_POST['txtFechaInicio'] ?? '';
$cmbCultivo = (int)($_POST['cmbCultivo'] ?? 0);
$cmbMoneda = $_POST['cmbMoneda'] ?? '';
$tipoCambio = $_POST['tipoCambio'] ?? '';
$txtInversion = $_POST['txtInversion'] ?? '';
$txtFechaCierre = $_POST['txtFechaCierre'] ?? '';

$stmt = $conexion->prepare("INSERT INTO detallesiembra VALUES (DEFAULT, ?, ?, ?, ?, ?, ?, ?)");
$stmt->bind_param("ississs", $proyecto, $txtFechaInicio, $txtFechaCierre, $cmbCultivo, $txtInversion, $cmbMoneda, $tipoCambio);
$stmt->execute();
$stmt->close();
mysqli_close($conexion);
header("Location: ./detalleSiembra.php?ok");
exit;
