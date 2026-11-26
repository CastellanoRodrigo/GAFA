<?php
include('./Modelo/Conexion.php');
include_once('./Modelo/Auth.php');
include_once('./Modelo/Hacienda.php');
include_once('./Modelo/Ventas.php');
include_once('./Modelo/Auditoria.php');
requerirEscritura($conexion);

$volver = function (int $p, string $q) { header("Location: ./movimientosHacienda.php?no=$p&$q"); exit; };
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !haciendaListas($conexion)) { header("Location: ./movimientosHacienda.php?error=datos"); exit; }
$accion = $_POST['accion'] ?? 'crear';

if ($accion === 'eliminar') {
    $id = (int)($_POST['movimiento'] ?? 0);
    $s = $conexion->prepare("SELECT id_Proyecto, tipo, cantidad, id_Venta FROM movimientoshacienda WHERE id_Movimiento = ?");
    $s->bind_param('i', $id);
    $s->execute();
    $m = $s->get_result()->fetch_assoc();
    $s->close();
    if (!$m) { header("Location: ./movimientosHacienda.php?error=datos"); exit; }
    $p = (int)$m['id_Proyecto'];
    // Quitar una entrada no puede dejar el stock en negativo
    if ((MOVIMIENTOS_HACIENDA[$m['tipo']] ?? 0) > 0 && stockHacienda($conexion, $p)['actual'] - (int)$m['cantidad'] < 0) $volver($p, 'error=stock');
    $s = $conexion->prepare("DELETE FROM movimientoshacienda WHERE id_Movimiento = ?");
    $s->bind_param('i', $id);
    $s->execute();
    $s->close();
    if ($m['id_Venta'] && ventasListas($conexion)) {
        $s = $conexion->prepare("DELETE FROM ventas WHERE id_Venta = ?");
        $v = (int)$m['id_Venta'];
        $s->bind_param('i', $v);
        $s->execute();
        $s->close();
    }
    auditar($conexion, 'hacienda_quitado', $m['tipo'] . ' ' . $m['cantidad'] . ' cabezas - proyecto ' . $p);
    $volver($p, 'borrado');
}

$p = (int)($_POST['proyecto'] ?? 0);
$fecha = $_POST['fecha'] ?? '';
$tipo = $_POST['tipo'] ?? '';
$cant = (int)($_POST['cantidad'] ?? 0);
$obs = mb_substr(trim((string)($_POST['observaciones'] ?? '')), 0, 255);
if (!esProyectoHacienda($conexion, $p)) { header("Location: ./movimientosHacienda.php?error=proyecto"); exit; }
if (!isset(MOVIMIENTOS_HACIENDA[$tipo]) || !fechaValidaVentas($fecha)) $volver($p, 'error=datos');
if ($cant < 1 || $cant > 1000000) $volver($p, 'error=cantidad');
if (MOVIMIENTOS_HACIENDA[$tipo] < 0 && $cant > stockHacienda($conexion, $p)['actual']) $volver($p, 'error=stock');

// Venta con importe: tambien se registra como ingreso en Ventas
$idVenta = null;
$importe = round((float)str_replace(',', '.', (string)($_POST['importe'] ?? '0')), 2);
if ($tipo === 'Venta' && $importe > 0 && ventasListas($conexion)) {
    $mon = (int)($_POST['moneda'] ?? 0);
    $s = $conexion->prepare("SELECT 1 FROM moneda WHERE id_Moneda = ?");
    $s->bind_param('i', $mon);
    $s->execute();
    $okMon = (bool)$s->get_result()->fetch_row();
    $s->close();
    if (!$okMon) $volver($p, 'error=datos');
    $concepto = 'Venta de ' . $cant . ' cabeza' . ($cant === 1 ? '' : 's') . ' de hacienda';
    $un = 'cabezas';
    $comp = mb_substr(nombrePropio($_POST['comprador'] ?? ''), 0, 100) ?: null;
    $s = $conexion->prepare("INSERT INTO ventas (id_Proyecto, fecha, concepto, cantidad, unidad, importe, id_Moneda, comprador) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $c = (float)$cant;
    $s->bind_param('issdsdis', $p, $fecha, $concepto, $c, $un, $importe, $mon, $comp);
    $s->execute();
    $idVenta = $conexion->insert_id;
    $s->close();
}
$ob = $obs !== '' ? $obs : null;
$s = $conexion->prepare("INSERT INTO movimientoshacienda (id_Proyecto, fecha, tipo, cantidad, observaciones, id_Venta) VALUES (?, ?, ?, ?, ?, ?)");
$s->bind_param('issisi', $p, $fecha, $tipo, $cant, $ob, $idVenta);
$s->execute();
$s->close();
auditar($conexion, 'hacienda_' . strtolower($tipo), $cant . ' cabezas - proyecto ' . $p);
$volver($p, 'ok');
