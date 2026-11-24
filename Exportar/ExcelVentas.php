<?php
// Exporta las ventas a Excel. Filtros (desde, hasta, proyecto, q) por GET o POST; totales por moneda.
session_start();
if (empty($_SESSION['usuario'])) {
	http_response_code(403);
	exit('Acceso denegado');
}
include('../Modelo/Conexion.php');
include_once('../Modelo/Ventas.php');

if (!ventasListas($conexion)) {
	http_response_code(409);
	exit('Falta ejecutar migracion_ventas.sql');
}
$filtros = filtrosVentas($_POST + $_GET);
$ventas = consultarVentas($conexion, $filtros);
$conFiltro = hayFiltrosVentas($filtros);
$h = function ($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); };
$columnas = ['Fecha', 'Proyecto', 'Concepto', 'Cantidad', 'Unidad', 'Comprador', 'Comprobante', 'Moneda', 'Importe'];

$html = '<meta charset="utf-8"><table border="1" cellpadding="4">';
$html .= '<tr><td colspan="' . count($columnas) . '" style="font-size:16px;font-weight:bold;">' . ($conFiltro ? 'Ventas filtradas' : 'Ventas')
	. ' - ' . count($ventas) . ' venta' . (count($ventas) === 1 ? '' : 's') . ' - generado el ' . date('d-m-Y H:i') . '</td></tr>';
$html .= '<tr style="background-color:#2f5d34;color:#ffffff;font-weight:bold;">';
foreach ($columnas as $c) $html .= '<td>' . $h($c) . '</td>';
$html .= '</tr>';
foreach ($ventas as $v) {
	$html .= '<tr><td>' . $h(date('d/m/Y', strtotime($v['fecha']))) . '</td><td>' . $h($v['nombreProyecto']) . '</td><td>' . $h($v['concepto']) . '</td>'
		. '<td align="right">' . ($v['cantidad'] !== null ? $h(number_format((float)$v['cantidad'], 2, ',', '.')) : '') . '</td><td>' . $h($v['unidad']) . '</td>'
		. '<td>' . $h($v['comprador']) . '</td><td>' . $h($v['nroComprobante']) . '</td><td>' . $h($v['moneda']) . '</td>'
		. '<td align="right">' . $h(montoMoneda($v['importe'], $v['moneda'])) . '</td></tr>';
}
foreach (totalesPorMoneda($ventas, 'importe') as $mon => $t) {
	$html .= '<tr style="font-weight:bold;background-color:#e8f0e3;"><td colspan="7">Total en ' . $h(strtolower(nombreMoneda($mon))) . ' (' . $h($mon) . ') - ' . (int)$t['cantidad'] . ' venta' . ($t['cantidad'] === 1 ? '' : 's') . '</td>'
		. '<td>' . $h($mon) . '</td><td align="right">' . $h(montoMoneda($t['total'], $mon)) . '</td></tr>';
}
$html .= '</table>';

header("Cache-Control: no-cache, must-revalidate");
header("Pragma: no-cache");
header("Content-type: application/x-msexcel; charset=utf-8");
header('Content-Disposition: attachment; filename="' . ($conFiltro ? 'VentasFiltradas.xls' : 'Ventas.xls') . '"');
echo $html;
exit;
