<?php
include("./Modelo/Conexion.php");
include('./Modelo/Auth.php');
requerirLogin();

function insertarProyecto($conexion, int $parcela, string $nombre, int $tipo, int $cantidad, int $estado): void
{
    $stmt = $conexion->prepare("INSERT INTO proyectos VALUES (DEFAULT, ?, ?, ?, ?, ?)");
    $stmt->bind_param("isiii", $parcela, $nombre, $tipo, $cantidad, $estado);
    $stmt->execute();
    $stmt->close();
}

if (isset($_POST['btn1'])) {
    insertarProyecto($conexion, (int)($_POST['cmbparcela1'] ?? 0), trim($_POST['nombre1'] ?? ''),
        (int)($_POST['cmbTipoProyecto1'] ?? 0), (int)($_POST['cantHas1'] ?? 0), (int)($_POST['cmbEstado1'] ?? 0));
}

if (isset($_POST['btn2'])) {
    $estado2 = 3; /* Proyecto finalizado */
    insertarProyecto($conexion, (int)($_POST['cmbparcela2'] ?? 0), trim($_POST['nombre2'] ?? ''),
        (int)($_POST['cmbTipoProyecto2'] ?? 0), (int)($_POST['cantHas2'] ?? 0), $estado2);
}

mysqli_close($conexion);
header("Location: ./altaProyectos.php?ok");
exit;
