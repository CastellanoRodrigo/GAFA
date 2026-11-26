<?php
include('./Modelo/Conexion.php');
include_once('./Modelo/Auth.php');
include_once('./Modelo/Ventas.php');
include_once('./Modelo/Auditoria.php');
requerirEscritura($conexion);

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !ventasListas($conexion)) {
    header("Location: ./ventas.php?error=datos");
    exit;
}
$accion = $_POST['accion'] ?? 'crear';

if ($accion === 'eliminar') {
    $id = (int)($_POST['venta'] ?? 0);
    $stmt = $conexion->prepare("DELETE FROM ventas WHERE id_Venta = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $borrada = $stmt->affected_rows > 0;
    $stmt->close();
    if ($borrada) auditar($conexion, 'venta_quitada', 'Venta N° ' . $id);
    header("Location: ./ventas.php?" . ($borrada ? 'borrada' : 'error=datos'));
    exit;
}

$proyecto = (int)($_POST['proyecto'] ?? 0);
$fecha = $_POST['fecha'] ?? '';
$concepto = trim((string)($_POST['concepto'] ?? ''));
$importe = round((float)str_replace(',', '.', (string)($_POST['importe'] ?? '0')), 2);
$moneda = (int)($_POST['moneda'] ?? 0);
$cantTxt = trim((string)($_POST['cantidad'] ?? ''));
$cantidad = $cantTxt === '' ? null : round((float)str_replace(',', '.', $cantTxt), 2);
$unidad = mb_substr(trim((string)($_POST['unidad'] ?? '')), 0, 20);
$comprador = mb_substr(nombrePropio($_POST['comprador'] ?? ''), 0, 100);
$comprobante = mb_substr(trim((string)($_POST['comprobante'] ?? '')), 0, 40);
$obs = mb_substr(trim((string)($_POST['observaciones'] ?? '')), 0, 255);

$existe = function (string $sql, int $id) use ($conexion): bool {
    $s = $conexion->prepare($sql);
    $s->bind_param("i", $id);
    $s->execute();
    $ok = (bool)$s->get_result()->fetch_row();
    $s->close();
    return $ok;
};
if (!$existe("SELECT 1 FROM proyectos WHERE id_Proyecto = ?", $proyecto)) { header("Location: ./ventas.php?error=proyecto"); exit; }
if (!$existe("SELECT 1 FROM moneda WHERE id_Moneda = ?", $moneda)) { header("Location: ./ventas.php?error=moneda"); exit; }
if (!fechaValidaVentas($fecha) || $concepto === '' || mb_strlen($concepto) > 150) { header("Location: ./ventas.php?error=datos"); exit; }
if ($importe <= 0 || $importe > 999999999999) { header("Location: ./ventas.php?error=importe"); exit; }
if ($cantidad !== null && ($cantidad < 0 || $cantidad > 999999999999)) { header("Location: ./ventas.php?error=datos"); exit; }

$comp = $comprador !== '' ? $comprador : null;
$nro = $comprobante !== '' ? $comprobante : null;
$un = $unidad !== '' ? $unidad : null;
$ob = $obs !== '' ? $obs : null;
$stmt = $conexion->prepare("INSERT INTO ventas (id_Proyecto, fecha, concepto, cantidad, unidad, importe, id_Moneda, comprador, nroComprobante, observaciones)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
$stmt->bind_param("issdsdisss", $proyecto, $fecha, $concepto, $cantidad, $un, $importe, $moneda, $comp, $nro, $ob);
$stmt->execute();
$stmt->close();
auditar($conexion, 'venta_registrada', $concepto . ' - ' . $importe . ' (moneda ' . $moneda . ') - proyecto ' . $proyecto);
header("Location: ./ventas.php?ok");
exit;
