<?php
// Exporta el resultado por proyecto (ingresos - gastos) a Excel, una fila por proyecto y moneda.
session_start();
if (empty($_SESSION['usuario'])) {
	http_response_code(403);
	exit('Acceso denegado');
}
include('../Modelo/Conexion.php');
include_once('../Modelo/Ventas.php');

$filtros = filtrosResultados($_POST + $_GET);
$filas = filasResultados($conexion, $filtros);
$h = function ($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); };
$columnas = ['Proyecto', 'Tipo', 'Estado', 'Hectáreas', 'Moneda', 'Ventas', 'Alquiler cobrado', 'Ingresos', 'Gastos', 'Resultado'];

$html = '<meta charset="utf-8"><table border="1" cellpadding="4">';
$html .= '<tr><td colspan="' . count($columnas) . '" style="font-size:16px;font-weight:bold;">Resultados por proyecto - generado el ' . date('d-m-Y H:i') . '</td></tr>';
$html .= '<tr style="background-color:#2f5d34;color:#ffffff;font-weight:bold;">';
foreach ($columnas as $c) $html .= '<td>' . $h($c) . '</td>';
$html .= '</tr>';
$tot = [];
foreach ($filas as $r) {
	$m = $r['moneda'];
	$html .= '<tr><td>' . $h($r['nombreProyecto']) . '</td><td>' . $h($r['tipoProyecto']) . '</td><td>' . $h($r['estado']) . '</td><td align="right">' . (int)$r['cantidadHas'] . '</td><td>' . $h($m) . '</td>'
		. '<td align="right">' . $h(montoMoneda($r['ventas'], $m)) . '</td><td align="right">' . $h(montoMoneda($r['alquiler'], $m)) . '</td>'
		. '<td align="right">' . $h(montoMoneda($r['ingresos'], $m)) . '</td><td align="right">' . $h(montoMoneda($r['gastos'], $m)) . '</td>'
		. '<td align="right"><b>' . $h(montoMoneda($r['resultado'], $m)) . '</b></td></tr>';
	foreach (['ventas', 'alquiler', 'ingresos', 'gastos', 'resultado'] as $k) $tot[$m][$k] = ($tot[$m][$k] ?? 0) + $r[$k];
}
uksort($tot, function ($a, $b) { $o = ['ARS' => 0, 'USD' => 1]; return ($o[$a] ?? 2) <=> ($o[$b] ?? 2); });
foreach ($tot as $m => $t) {
	$html .= '<tr style="font-weight:bold;background-color:#e8f0e3;"><td colspan="4">Totales en ' . $h(strtolower(nombreMoneda($m))) . ' (' . $h($m) . ')</td><td>' . $h($m) . '</td>'
		. '<td align="right">' . $h(montoMoneda($t['ventas'], $m)) . '</td><td align="right">' . $h(montoMoneda($t['alquiler'], $m)) . '</td>'
		. '<td align="right">' . $h(montoMoneda($t['ingresos'], $m)) . '</td><td align="right">' . $h(montoMoneda($t['gastos'], $m)) . '</td>'
		. '<td align="right">' . $h(montoMoneda($t['resultado'], $m)) . '</td></tr>';
}
$html .= '</table>';

header("Cache-Control: no-cache, must-revalidate");
header("Pragma: no-cache");
header("Content-type: application/x-msexcel; charset=utf-8");
header('Content-Disposition: attachment; filename="ResultadosPorProyecto.xls"');
echo $html;
exit;
