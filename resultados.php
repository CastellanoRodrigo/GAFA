<title>GAFA - Resultados por proyecto</title>
<?php
include('./Template/Cabecera.php');
include('./Modelo/Conexion.php');
include_once('./Modelo/Auth.php');
include_once('./Modelo/Ventas.php');

$sinVentas = !ventasListas($conexion);
$idDet = (int)($_GET['no'] ?? 0);

// ---- Detalle de un proyecto ----
if ($idDet > 0) {
    $stmt = $conexion->prepare("SELECT p.id_Proyecto, p.nombreProyecto, p.cantidadHas, p.id_Parcela, t.tipoProyecto, e.estado
        FROM proyectos p LEFT JOIN tipoproyecto t ON t.id_tipoProyecto = p.id_TipoProyecto
        LEFT JOIN estadoproyecto e ON e.id_EstadoProyecto = p.id_EstadoProyecto WHERE p.id_Proyecto = ?");
    $stmt->bind_param("i", $idDet);
    $stmt->execute();
    $proy = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$proy) {
        echo '<center><h1>PROYECTO NO ENCONTRADO</h1><p><a class="btn btn-success" href="./resultados.php">Volver a resultados</a></p></center>';
        include('./Template/Pie.php');
        exit;
    }
    $res = resultadosPorProyecto($conexion, $idDet)[$idDet] ?? [];
    $ventas = $sinVentas ? [] : consultarVentas($conexion, ['desde' => '', 'hasta' => '', 'proyecto' => $idDet, 'q' => '']);
    $gastos = [];
    if (tablaExiste($conexion, 'moneda') && columnaExiste($conexion, 'compras', 'id_Moneda')) {
        $stmt = $conexion->prepare("SELECT c.id_Compras, c.fecha, c.detalle, c.importeTotal, m.moneda, pv.proveedor
            FROM compraproyecto cp JOIN compras c ON c.id_Compras = cp.id_Compras
            LEFT JOIN moneda m ON m.id_Moneda = c.id_Moneda LEFT JOIN proveedores pv ON pv.id_Proveedor = c.id_Proveedor
            WHERE cp.id_TipoCompra = 2 AND cp.id_Proyecto = ? ORDER BY c.fecha DESC");
        $stmt->bind_param("i", $idDet);
        $stmt->execute();
        $gastos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }
    ?>
<center>
    <h1>RESULTADO: <?php echo e(mb_strtoupper($proy['nombreProyecto'])); ?></h1>
    <p><?php echo e($proy['tipoProyecto']); ?> · parcela N° <?php echo (int)$proy['id_Parcela']; ?> · <?php echo (int)$proy['cantidadHas']; ?> has · <?php echo e($proy['estado']); ?></p>

    <?php if (!$res) { ?>
    <p class="sin-datos">Este proyecto todavía no tiene ventas, cobros ni compras asignadas.</p>
    <?php } ?>
    <div class="resumen-alquiler">
        <?php foreach ($res as $mon => $v) { ?>
        <div class="tarjeta-resumen <?php echo $v['resultado'] < 0 ? 'mal' : ($mon === 'USD' ? 'usd' : 'ars'); ?>">
            <span class="num"><?php echo e(montoMoneda($v['resultado'], $mon)); ?></span>
            <span><?php echo $v['resultado'] < 0 ? 'Pérdida' : 'Ganancia'; ?> en <?php echo e(strtolower(nombreMoneda($mon))); ?> <?php echo chipMoneda($mon); ?><br>
                Ingresos <?php echo e(montoMoneda($v['ingresos'], $mon)); ?> − Gastos <?php echo e(montoMoneda($v['gastos'], $mon)); ?></span>
        </div>
        <?php } ?>
    </div>

    <h3>Ingresos por ventas</h3>
    <?php if (!$ventas) { ?><p class="sin-datos">Sin ventas registradas.</p><?php } else { ?>
    <table><thead><tr><th>Fecha</th><th>Concepto</th><th>Comprador</th><th>Moneda</th><th style="text-align:right;">Importe</th></tr></thead><tbody>
        <?php foreach ($ventas as $v) { ?>
        <tr><td class="nowrap"><?php echo e(date('d/m/Y', strtotime($v['fecha']))); ?></td><td><?php echo e($v['concepto']); ?></td><td><?php echo e($v['comprador']); ?></td>
            <td><?php echo chipMoneda($v['moneda']); ?></td><td class="nowrap" style="text-align:right;"><?php echo e(montoMoneda($v['importe'], $v['moneda'])); ?></td></tr>
        <?php } ?></tbody></table>
    <?php } ?>

    <h3>Gastos (compras asignadas)</h3>
    <?php if (!$gastos) { ?><p class="sin-datos">Sin compras asignadas.</p><?php } else { ?>
    <table><thead><tr><th>Fecha</th><th>Proveedor</th><th>Detalle</th><th>Moneda</th><th style="text-align:right;">Total</th></tr></thead><tbody>
        <?php foreach ($gastos as $g) { ?>
        <tr><td class="nowrap"><?php echo e(date('d/m/Y', strtotime($g['fecha']))); ?></td><td><?php echo e($g['proveedor']); ?></td>
            <td><a href="./detalleCompra.php?no=<?php echo (int)$g['id_Compras']; ?>"><?php echo e($g['detalle']); ?></a></td>
            <td><?php echo chipMoneda($g['moneda']); ?></td><td class="nowrap" style="text-align:right;"><?php echo e(montoMoneda($g['importeTotal'], $g['moneda'])); ?></td></tr>
        <?php } ?></tbody></table>
    <?php } ?>

    <?php foreach ($res as $mon => $v) { if ($v['alquiler'] > 0) { ?>
    <p>Cobros de alquiler incluidos en los ingresos (<?php echo e($mon); ?>): <b><?php echo e(montoMoneda($v['alquiler'], $mon)); ?></b></p>
    <?php } } ?>
    <p><small>El resultado es: ventas + cobros de alquiler − compras asignadas al proyecto, por moneda. No se convierten pesos a dólares.</small></p>
    <p><a class="btn btn-outline-success" href="./resultados.php">Todos los proyectos</a>
       <a class="btn btn-outline-success" href="./detalleProyecto.php?no=<?php echo (int)$idDet; ?>">Ver proyecto</a>
       <?php if (puedeEscribir($conexion) && !$sinVentas) { ?><a class="btn btn-success" href="./ventas.php?nueva=1">Registrar venta</a><?php } ?></p>
</center>
<?php
    $conexion->close();
    include('./Template/Pie.php');
    exit;
}

// ---- Listado de todos los proyectos ----
$f = filtrosResultados($_GET);
$filas = filasResultados($conexion, $f);
$tiposProy = mysqli_fetch_all(mysqli_query($conexion, "SELECT id_tipoProyecto, tipoProyecto FROM tipoproyecto ORDER BY tipoProyecto"), MYSQLI_ASSOC);
$estadosProy = mysqli_fetch_all(mysqli_query($conexion, "SELECT id_EstadoProyecto, estado FROM estadoproyecto ORDER BY id_EstadoProyecto"), MYSQLI_ASSOC);
$qs = http_build_query(array_filter($f, function ($v) { return $v !== '' && $v !== 0; }));
$tot = [];
foreach ($filas as $r) {
    $m = $r['moneda'];
    foreach (['ingresos', 'gastos', 'resultado'] as $k) $tot[$m][$k] = ($tot[$m][$k] ?? 0) + $r[$k];
}
uksort($tot, function ($a, $b) { $o = ['ARS' => 0, 'USD' => 1]; return ($o[$a] ?? 2) <=> ($o[$b] ?? 2); });
?>
<center>
    <h1>RESULTADOS POR PROYECTO</h1>
    <form method="GET" action="./resultados.php" class="filtros-listado">
        <div><label for="tipo">Tipo</label>
            <select name="tipo" id="tipo"><option value="0">Todos</option>
                <?php foreach ($tiposProy as $t) { ?><option value="<?php echo (int)$t['id_tipoProyecto']; ?>" <?php echo $f['tipo'] === (int)$t['id_tipoProyecto'] ? 'selected' : ''; ?>><?php echo e($t['tipoProyecto']); ?></option><?php } ?>
            </select></div>
        <div><label for="estado">Estado</label>
            <select name="estado" id="estado"><option value="0">Todos</option>
                <?php foreach ($estadosProy as $s) { ?><option value="<?php echo (int)$s['id_EstadoProyecto']; ?>" <?php echo $f['estado'] === (int)$s['id_EstadoProyecto'] ? 'selected' : ''; ?>><?php echo e($s['estado']); ?></option><?php } ?>
            </select></div>
        <div><label for="q">Nombre</label><input type="text" name="q" id="q" class="campo-ancho" value="<?php echo e($f['q']); ?>" placeholder="Buscar..."></div>
        <div class="filtros-botones"><button type="submit" class="btn btn-success">Filtrar</button> <a href="./resultados.php" class="btn btn-outline-success">Limpiar</a></div>
    </form>

    <div class="barra-listado">
        <span>Ingresos (ventas + cobros de alquiler) menos gastos (compras asignadas), en cada moneda</span>
        <span class="barra-acciones">
            <a href="./ventas.php" class="btn btn-outline-success">Ver ventas</a>
            <a href="./Exportar/ExcelResultados.php<?php echo $qs ? '?' . e($qs) : ''; ?>" class="btn btn-outline-success">Exportar a Excel</a>
        </span>
    </div>
    <?php if ($sinVentas) { ?><p class="sin-datos">Falta ejecutar <b>migracion_ventas.sql</b> para registrar ventas; por ahora solo se cuentan gastos y cobros de alquiler.</p><?php } ?>

    <?php if ($tot) { ?>
    <div class="resumen-alquiler">
        <?php foreach ($tot as $mon => $t) { ?>
        <div class="tarjeta-resumen <?php echo $t['resultado'] < 0 ? 'mal' : ($mon === 'USD' ? 'usd' : 'ars'); ?>">
            <span class="num"><?php echo e(montoMoneda($t['resultado'], $mon)); ?></span>
            <span>Resultado en <?php echo e(strtolower(nombreMoneda($mon))); ?> <?php echo chipMoneda($mon); ?><br>Ingresos <?php echo e(montoMoneda($t['ingresos'], $mon)); ?> − Gastos <?php echo e(montoMoneda($t['gastos'], $mon)); ?></span>
        </div>
        <?php } ?>
    </div>
    <?php } ?>

    <?php if (!$filas) { ?>
    <p class="sin-datos">Todavía no hay movimientos económicos para mostrar.</p>
    <?php } else { ?>
    <table>
        <thead><tr><th>Proyecto</th><th>Tipo</th><th>Moneda</th><th style="text-align:right;">Ventas</th><th style="text-align:right;">Alquiler cobrado</th><th style="text-align:right;">Gastos</th><th style="text-align:right;">Resultado</th><th style="text-align:center;">Detalle</th></tr></thead>
        <tbody>
        <?php foreach ($filas as $r) { $m = $r['moneda']; ?>
        <tr>
            <td><?php echo e($r['nombreProyecto']); ?></td>
            <td><?php echo e($r['tipoProyecto']); ?></td>
            <td><?php echo chipMoneda($m); ?></td>
            <td class="nowrap" style="text-align:right;"><?php echo e(montoMoneda($r['ventas'], $m)); ?></td>
            <td class="nowrap" style="text-align:right;"><?php echo e(montoMoneda($r['alquiler'], $m)); ?></td>
            <td class="nowrap" style="text-align:right;"><?php echo e(montoMoneda($r['gastos'], $m)); ?></td>
            <td class="nowrap" style="text-align:right;"><b class="<?php echo $r['resultado'] < 0 ? 'texto-mal' : ''; ?>"><?php echo e(montoMoneda($r['resultado'], $m)); ?></b></td>
            <td class="text-center"><a class="btn btn-sm btn-outline-success" href="./resultados.php?no=<?php echo (int)$r['id_Proyecto']; ?>">Ver</a></td>
        </tr>
        <?php } ?>
        </tbody>
    </table>
    <?php } ?>
</center>
<?php
$conexion->close();
include('./Template/Pie.php');
?>
