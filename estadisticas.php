<title>GAFA - Estadísticas</title>
<?php
include('./Template/Cabecera.php');
include('./Modelo/Conexion.php');
include_once('./Modelo/Auth.php');
include_once('./Modelo/Ventas.php');
include_once('./Modelo/Hacienda.php');

// Resumen economico de todos los proyectos, por moneda (nunca se mezclan pesos y dolares)
$res = resultadosPorProyecto($conexion);
$tot = [];
foreach ($res as $monedas) {
    foreach ($monedas as $m => $v) {
        foreach (['ventas', 'alquiler', 'gastos', 'ingresos', 'resultado'] as $k) $tot[$m][$k] = ($tot[$m][$k] ?? 0) + $v[$k];
    }
}
uksort($tot, function ($a, $b) { $o = ['ARS' => 0, 'USD' => 1]; return ($o[$a] ?? 2) <=> ($o[$b] ?? 2); });
$stockTotal = haciendaListas($conexion) ? array_sum(array_column(proyectosConStock($conexion), 'actual')) : null;
$nombres = array_column(mysqli_fetch_all(mysqli_query($conexion, "SELECT id_Proyecto, nombreProyecto FROM proyectos"), MYSQLI_ASSOC), 'nombreProyecto', 'id_Proyecto');
?>
<center>
    <h1>ESTADÍSTICAS</h1>

    <?php if ($tot) { ?>
    <div class="resumen-alquiler">
        <?php foreach ($tot as $mon => $t) { ?>
        <div class="tarjeta-resumen <?php echo $t['resultado'] < 0 ? 'mal' : ($mon === 'USD' ? 'usd' : 'ars'); ?>">
            <span class="num"><?php echo e(montoMoneda($t['resultado'], $mon)); ?></span>
            <span>Resultado total en <?php echo e(strtolower(nombreMoneda($mon))); ?> <?php echo chipMoneda($mon); ?><br>
                Ventas <?php echo e(montoMoneda($t['ventas'], $mon)); ?> · Alquiler <?php echo e(montoMoneda($t['alquiler'], $mon)); ?> · Gastos <?php echo e(montoMoneda($t['gastos'], $mon)); ?></span>
        </div>
        <?php } ?>
        <?php if ($stockTotal !== null) { ?>
        <div class="tarjeta-resumen"><span class="num"><?php echo (int)$stockTotal; ?></span><span>Cabezas de hacienda en stock</span></div>
        <?php } ?>
    </div>

    <?php foreach ($tot as $mon => $t) {
        $filas = [];
        foreach ($res as $idp => $monedas) if (isset($monedas[$mon])) $filas[] = ['id' => $idp, 'nombre' => $nombres[$idp] ?? ('Proyecto ' . $idp)] + $monedas[$mon];
        usort($filas, function ($a, $b) { return $b['resultado'] <=> $a['resultado']; });
        $max = max(1, max(array_map(function ($f) { return abs($f['resultado']); }, $filas)));
    ?>
    <h3>Resultado por proyecto en <?php echo e(strtolower(nombreMoneda($mon))); ?> <?php echo chipMoneda($mon); ?></h3>
    <table class="tabla-barras"><tbody>
        <?php foreach ($filas as $f) { $pct = round(abs($f['resultado']) / $max * 100); ?>
        <tr>
            <td><a href="./resultados.php?no=<?php echo (int)$f['id']; ?>"><?php echo e($f['nombre']); ?></a></td>
            <td style="width:45%;"><div style="height:14px;border-radius:7px;width:<?php echo (int)$pct; ?>%;min-width:3px;background:<?php echo $f['resultado'] < 0 ? '#b4432e' : ($mon === 'USD' ? '#2a6bb5' : '#2f5d34'); ?>;"></div></td>
            <td class="nowrap" style="text-align:right;"><b class="<?php echo $f['resultado'] < 0 ? 'texto-mal' : ''; ?>"><?php echo e(montoMoneda($f['resultado'], $mon)); ?></b></td>
        </tr>
        <?php } ?>
    </tbody></table>
    <?php } ?>
    <?php } else { ?>
    <p class="sin-datos">Todavía no hay ventas, cobros ni compras asignadas para calcular resultados.</p>
    <?php } ?>

    <br>
    <a href="./resultados.php" class="btn btn-success">Resultados por proyecto</a>
    <a href="./estadisticasGenerales.php" class="btn btn-success">Generales</a>
    <a href="./estadisticasSiembra.php" class="btn btn-success">Siembra</a>
    <a href="./estadisticasHacienda.php" class="btn btn-success">Hacienda</a>
</center>
<?php
$conexion->close();
include('./Template/Pie.php');
?>
