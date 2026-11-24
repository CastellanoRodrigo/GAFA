<?php
include('./Modelo/Conexion.php');
include_once('./Modelo/Auth.php');
requerirEscritura($conexion);

$proyecto = (int)($_POST['cmbNombre'] ?? 0);
$txtFechaInicio = $_POST['txtFechaInicio'] ?? '';
$txtCabezas = (int)($_POST['txtCabezas'] ?? 0);
$cmbMoneda = $_POST['cmbMoneda'] ?? '';
$tipoCambio = $_POST['tipoCambio'] ?? '';
$txtInversion = $_POST['txtInversion'] ?? '';
$cmbCategoria = (int)($_POST['cmbCategoria'] ?? 0);
$txtFechaCierre = $_POST['txtFechaCierre'] ?? '';

$stmt = $conexion->prepare("INSERT INTO detallehacienda VALUES (DEFAULT, ?, ?, ?, ?, ?, ?, ?, ?)");
$stmt->bind_param("issiisss", $proyecto, $txtFechaInicio, $txtFechaCierre, $txtCabezas, $cmbCategoria, $txtInversion, $cmbMoneda, $tipoCambio);
$stmt->execute();
$stmt->close();
mysqli_close($conexion);
header("Location: ./detalleHacienda.php?ok");
exit;
