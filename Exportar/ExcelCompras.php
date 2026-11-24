<?php
// Exporta compras a Excel. Acepta filtros opcionales (desde, hasta, proveedor, q) por GET o POST.
// Ya no recibe SQL del cliente.
session_start();
if (empty($_SESSION['usuario'])) {
	http_response_code(403);
	exit('Acceso denegado');
}
include('../Modelo/Conexion.php');
include_once('../Modelo/Compras.php');
include_once('../Modelo/Moneda.php');

$filtros = filtrosCompras($_POST + $_GET);
$compras = consultarCompras($conexion, $filtros);
$conPrefiltro = hayFiltrosCompras($filtros);

$h = function ($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); };

$arquivo = $conPrefiltro ? 'comprasFiltradas.xls' : 'compras.xls';

$html = '<meta charset="utf-8">';
$html .= '<table border="1">';
$html .= '<tr><td colspan="8">' . ($conPrefiltro ? 'Lista de compras filtradas' : 'Lista de compras') . '</td></tr>';
$html .= '<tr>';
foreach (['Fecha', 'NroFactura', 'Proveedor', 'Moneda', 'ImporteNeto', 'IVA', 'Total', 'FormaPago'] as $col) {
	$html .= '<td><b>' . $col . '</b></td>';
}
$html .= '</tr>';

foreach ($compras as $c) {
	$html .= '<tr>';
	$html .= '<td>' . $h(date('d-m-Y', strtotime($c['fecha']))) . '</td>';
	$html .= '<td>' . $h($c['nroFactura']) . '</td>';
	$html .= '<td>' . $h($c['proveedor']) . '</td>';
	$html .= '<td>' . $h($c['moneda']) . '</td>';
	$html .= '<td align="right">' . $h(montoMoneda($c['importeNeto'], $c['moneda'])) . '</td>';
	$html .= '<td>' . $h($c['IVA']) . '%</td>';
	$html .= '<td align="right">' . $h(montoMoneda($c['importeTotal'], $c['moneda'])) . '</td>';
	$html .= '<td>' . $h($c['formaPago']) . '</td>';
	$html .= '</tr>';
}
// Totales discriminados por moneda
$netos = totalesPorMoneda($compras, 'importeNeto');
foreach (totalesPorMoneda($compras, 'importeTotal') as $mon => $t) {
	$html .= '<tr style="font-weight:bold;background-color:#e8f0e3;"><td colspan="3">Total en ' . $h(strtolower(nombreMoneda($mon))) . ' (' . $h($mon) . ') - ' . (int)$t['cantidad'] . ' compra' . ($t['cantidad'] === 1 ? '' : 's') . '</td>'
		. '<td>' . $h($mon) . '</td><td align="right">' . $h(montoMoneda($netos[$mon]['total'], $mon)) . '</td><td></td>'
		. '<td align="right">' . $h(montoMoneda($t['total'], $mon)) . '</td><td></td></tr>';
}
$html .= '</table>';

header("Expires: Mon, 26 Jul 2227 05:00:00 GMT");
header("Last-Modified: " . gmdate("D,d M YH:i:s") . " GMT");
header("Cache-Control: no-cache, must-revalidate");
header("Pragma: no-cache");
header("Content-type: application/x-msexcel; charset=utf-8");
header("Content-Disposition: attachment; filename=\"{$arquivo}\"");
header("Content-Description: PHP Generado Data");
echo $html;
exit;
