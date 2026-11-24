<?php
// Exporta los contratos de alquiler a Excel. Filtros opcionales (estado, q) por GET o POST.
session_start();
if (empty($_SESSION['usuario'])) {
	http_response_code(403);
	exit('Acceso denegado');
}
include('../Modelo/Conexion.php');
include_once('../Modelo/Alquileres.php');
include_once('../Modelo/Moneda.php');

if (!tablasAlquilerListas($conexion)) {
	http_response_code(409);
	exit('Falta ejecutar migracion_alquiler.sql');
}

$filtros = filtrosAlquileres($_POST + $_GET);
$contratos = consultarAlquileres($conexion, $filtros);
$conFiltro = hayFiltrosAlquileres($filtros);
$h = function ($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); };

$arquivo = $conFiltro ? 'AlquileresFiltrados.xls' : 'Alquileres.xls';
$columnas = ['Proyecto', 'Parcela', 'Hectáreas', 'Inquilino', 'Contacto', 'Inicio', 'Fin', 'Estado', 'Moneda', 'Canon', 'Cobro', 'Cuotas pendientes', 'Vencido', 'Cobrado'];

$html = '<meta charset="utf-8"><table border="1" cellpadding="4">';
$html .= '<tr><td colspan="' . count($columnas) . '" style="font-size:16px;font-weight:bold;">'
	. ($conFiltro ? 'Alquileres filtrados' : 'Alquileres') . ' - ' . count($contratos) . ' contrato' . (count($contratos) === 1 ? '' : 's')
	. ' - generado el ' . date('d-m-Y H:i') . '</td></tr>';
$html .= '<tr style="background-color:#2f5d34;color:#ffffff;font-weight:bold;">';
foreach ($columnas as $col) {
	$html .= '<td>' . $h($col) . '</td>';
}
$html .= '</tr>';
foreach ($contratos as $c) {
	$html .= '<tr>';
	$html .= '<td>' . $h($c['nombreProyecto']) . '</td>';
	$html .= '<td>N° ' . (int)$c['id_Parcela'] . '</td>';
	$html .= '<td align="right">' . (int)$c['cantidadHas'] . '</td>';
	$html .= '<td>' . $h($c['inquilino']) . '</td>';
	$html .= '<td>' . $h($c['contacto']) . '</td>';
	$html .= '<td>' . $h(fechaCorta($c['fechaInicio'])) . '</td>';
	$html .= '<td>' . $h(fechaCorta($c['fechaFin'])) . '</td>';
	$html .= '<td>' . $h($c['estado']) . '</td>';
	$html .= '<td>' . $h($c['moneda']) . '</td>';
	$html .= '<td align="right">' . $h(montoMoneda($c['canon'], $c['moneda'])) . '</td>';
	$html .= '<td>' . $h($c['periodicidad']) . '</td>';
	$html .= '<td align="right">' . (int)$c['pendientes'] . '</td>';
	$html .= '<td align="right">' . $h(montoMoneda($c['vencido'], $c['moneda'])) . '</td>';
	$html .= '<td align="right">' . $h(montoMoneda($c['cobrado'], $c['moneda'])) . '</td>';
	$html .= '</tr>';
}
// Totales discriminados por moneda
$cobMon = totalesPorMoneda($contratos, 'cobrado');
$venMon = totalesPorMoneda($contratos, 'vencido');
foreach (totalesPorMoneda($contratos, 'porCobrar') as $mon => $t) {
	$html .= '<tr style="font-weight:bold;background-color:#e8f0e3;"><td colspan="8">Totales en ' . $h(strtolower(nombreMoneda($mon))) . ' (' . $h($mon) . ') - ' . (int)$t['cantidad'] . ' contrato' . ($t['cantidad'] === 1 ? '' : 's') . '</td>'
		. '<td>' . $h($mon) . '</td><td></td><td></td>'
		. '<td align="right">Por cobrar: ' . $h(montoMoneda($t['total'], $mon)) . '</td>'
		. '<td align="right">' . $h(montoMoneda($venMon[$mon]['total'], $mon)) . '</td>'
		. '<td align="right">' . $h(montoMoneda($cobMon[$mon]['total'], $mon)) . '</td></tr>';
}
$html .= '</table>';

header("Cache-Control: no-cache, must-revalidate");
header("Pragma: no-cache");
header("Content-type: application/x-msexcel; charset=utf-8");
header("Content-Disposition: attachment; filename=\"{$arquivo}\"");
echo $html;
exit;
