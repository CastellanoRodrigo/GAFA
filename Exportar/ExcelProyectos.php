<?php
// Exporta proyectos a Excel. Acepta filtros opcionales (tipo, estado, parcela, q) por GET o POST.
// Ya no recibe SQL del cliente.
session_start();
if (empty($_SESSION['usuario'])) {
	http_response_code(403);
	exit('Acceso denegado');
}
include('../Modelo/Conexion.php');
include_once('../Modelo/Proyectos.php');
include_once('../Modelo/Moneda.php');

$filtros = filtrosProyectos($_POST + $_GET);
$proyectos = consultarProyectos($conexion, $filtros);
$conFiltro = hayFiltrosProyectos($filtros);

$h = function ($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); };
$fecha = function ($v) { return $v ? date('d-m-Y', strtotime($v)) : ''; };

$arquivo = $conFiltro ? 'ProyectosFiltrados.xls' : 'Proyectos.xls';
$columnas = ['Proyecto', 'Tipo', 'Parcela', 'Hectáreas', 'Estado', 'Fecha inicio', 'Fecha cierre', 'Cabezas', 'Categoría', 'Cultivo', 'Moneda', 'Inversión inicial', 'Inquilino', 'Canon de alquiler'];

$html = '<meta charset="utf-8">';
$html .= '<table border="1" cellpadding="4">';
$html .= '<tr><td colspan="' . count($columnas) . '" style="font-size:16px;font-weight:bold;">'
	. ($conFiltro ? 'Listado de proyectos filtrados' : 'Listado de proyectos')
	. ' - ' . count($proyectos) . ' registro' . (count($proyectos) === 1 ? '' : 's')
	. ' - generado el ' . date('d-m-Y H:i') . '</td></tr>';
$html .= '<tr style="background-color:#2f5d34;color:#ffffff;font-weight:bold;">';
foreach ($columnas as $col) {
	$html .= '<td>' . $h($col) . '</td>';
}
$html .= '</tr>';

$totalHas = 0;
foreach ($proyectos as $p) {
	$totalHas += (int)$p['cantidadHas'];
	$moneda = $p['monedaInversion'] ?: $p['monedaCanon'];
	$html .= '<tr>';
	$html .= '<td>' . $h($p['nombreProyecto']) . '</td>';
	$html .= '<td>' . $h($p['tipoProyecto']) . '</td>';
	$html .= '<td>N° ' . (int)$p['id_Parcela'] . '</td>';
	$html .= '<td align="right">' . (int)$p['cantidadHas'] . '</td>';
	$html .= '<td>' . $h($p['estado']) . '</td>';
	$html .= '<td>' . $h($fecha($p['fechaInicio'])) . '</td>';
	$html .= '<td>' . $h($fecha($p['fechaCierre'])) . '</td>';
	$html .= '<td align="right">' . ($p['cantidadCabezas'] !== null ? (int)$p['cantidadCabezas'] : '') . '</td>';
	$html .= '<td>' . $h($p['nombreCategoria']) . '</td>';
	$html .= '<td>' . $h($p['nombreCultivo']) . '</td>';
	$html .= '<td>' . $h($p['monedaInversion'] ?? '') . '</td>';
	$html .= '<td align="right">' . ($p['inversion'] !== null ? $h($p['monedaInversion'] ? montoMoneda($p['inversion'], $p['monedaInversion']) : number_format((float)$p['inversion'], 2, ',', '.')) : '') . '</td>';
	$html .= '<td>' . $h($p['inquilino'] ?? '') . '</td>';
	$html .= '<td align="right">' . ($p['canon'] !== null ? $h(montoMoneda($p['canon'], $p['monedaCanon']) . ' (' . $p['periodicidad'] . ')') : '') . '</td>';
	$html .= '</tr>';
}
$html .= '<tr style="font-weight:bold;background-color:#e8f0e3;"><td colspan="3">Total de hectáreas</td>'
	. '<td align="right">' . $totalHas . '</td><td colspan="' . (count($columnas) - 4) . '"></td></tr>';
// Inversion total discriminada por moneda (nunca se mezclan pesos con dolares)
$conInversion = array_filter($proyectos, function ($p) { return $p['inversion'] !== null; });
foreach (totalesPorMoneda($conInversion, 'inversion', 'monedaInversion') as $mon => $t) {
	$etiqueta = $mon === '-' ? 'moneda no registrada' : strtolower(nombreMoneda($mon)) . ' (' . $mon . ')';
	$html .= '<tr style="font-weight:bold;background-color:#e8f0e3;"><td colspan="10">Inversión total en ' . $h($etiqueta) . ' - ' . (int)$t['cantidad'] . ' proyecto' . ($t['cantidad'] === 1 ? '' : 's') . '</td>'
		. '<td>' . $h($mon === '-' ? '' : $mon) . '</td>'
		. '<td align="right">' . $h($mon === '-' ? number_format($t['total'], 2, ',', '.') : montoMoneda($t['total'], $mon)) . '</td><td colspan="2"></td></tr>';
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
