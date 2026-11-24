<?php
include('./Modelo/Conexion.php');
include_once('./Modelo/Auth.php');
requerirEscritura($conexion);

$id = (int)($_POST['id'] ?? 0);
$estado = (int)($_POST['estado'] ?? 0);
$fechaH = $_POST['fechaCierreH'] ?? '';
$fechaS = $_POST['fechaCierreS'] ?? '';

function fechaValida($f): bool
{
    $d = DateTime::createFromFormat('Y-m-d', $f);
    return $d && $d->format('Y-m-d') === $f;
}

if ($id <= 0 || $estado <= 0) {
    header("Location: ./todosLosProyectos.php?error");
    exit;
}

$stmt = $conexion->prepare("UPDATE proyectos SET id_EstadoProyecto = ? WHERE id_Proyecto = ?");
$stmt->bind_param("ii", $estado, $id);
$stmt->execute();
$stmt->close();

if (fechaValida($fechaH)) {
    $stmt = $conexion->prepare("UPDATE detallehacienda SET fechaCierre = ? WHERE id_Proyecto = ?");
    $stmt->bind_param("si", $fechaH, $id);
    $stmt->execute();
    $stmt->close();
}
if (fechaValida($fechaS)) {
    $stmt = $conexion->prepare("UPDATE detallesiembra SET fechaCierre = ? WHERE id_Proyecto = ?");
    $stmt->bind_param("si", $fechaS, $id);
    $stmt->execute();
    $stmt->close();
}
mysqli_close($conexion);
header("Location: ./detalleProyecto.php?no=" . $id . "&ok");
exit;
