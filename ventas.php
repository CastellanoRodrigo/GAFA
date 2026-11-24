<title>GAFA - Ventas</title>
<?php
include('./Template/Cabecera.php');
include('./Modelo/Conexion.php');
include_once('./Modelo/Auth.php');
include_once('./Modelo/Ventas.php');

if (!ventasListas($conexion)) {
    echo '<center><h1>VENTAS</h1><p class="sin-datos">Falta ejecutar <b>migracion_ventas.sql</b> en la base de datos (ver el README).</p></center>';
    include('./Template/Pie.php');
    exit;
}
$escribe = puedeEscribir($conexion);
$f = filtrosVentas($_GET);
$ventas = consultarVentas($conexion, $f);
$proyectos = mysqli_fetch_all(mysqli_query($conexion, "SELECT id_Proyecto, nombreProyecto FROM proyectos ORDER BY nombreProyecto"), MYSQLI_ASSOC);
$monedas = mysqli_fetch_all(mysqli_query($conexion, "SELECT id_Moneda, moneda FROM moneda ORDER BY id_Moneda"), MYSQLI_ASSOC);
$totales = totalesPorMoneda($ventas, 'importe');
$qs = http_build_query(array_filter($f, function ($v) { return $v !== '' && $v !== 0; }));
?>
<center>
    <h1>VENTAS E INGRESOS</h1>

    <?php if ($escribe) { ?>
    <details class="panel-alta" <?php echo isset($_GET['nueva']) ? 'open' : ''; ?>>
        <summary class="btn btn-success">Registrar una venta</summary>
        <form method="POST" action="./registrarVenta.php" class="filtros-listado" style="margin-top:12px;">
            <input type="hidden" name="accion" value="crear">
            <div><label for="v_proyecto">Proyecto</label>
                <select name="proyecto" id="v_proyecto" required>
                    <option value="" selected disabled>-Elegí uno-</option>
                    <?php foreach ($proyectos as $p) { ?><option value="<?php echo (int)$p['id_Proyecto']; ?>"><?php echo e($p['nombreProyecto']); ?></option><?php } ?>
                </select></div>
            <div><label for="v_fecha">Fecha</label><input type="date" name="fecha" id="v_fecha" value="<?php echo date('Y-m-d'); ?>" required></div>
            <div><label for="v_concepto">Concepto</label><input type="text" name="concepto" id="v_concepto" class="campo-ancho" maxlength="150" placeholder="Ej.: Venta de soja, Venta de terneros" required></div>
            <div><label for="v_cantidad">Cantidad</label><input type="number" name="cantidad" id="v_cantidad" min="0" step="0.01" style="width:110px !important;"></div>
            <div><label for="v_unidad">Unidad</label><input type="text" name="unidad" id="v_unidad" maxlength="20" placeholder="tn, cabezas, kg" style="width:120px !important;"></div>
            <div><label for="v_importe">Importe total</label><input type="number" name="importe" id="v_importe" min="0.01" step="0.01" required></div>
            <div><label for="v_moneda">Moneda</label>
                <select name="moneda" id="v_moneda" required>
                    <?php foreach ($monedas as $m) { ?><option value="<?php echo (int)$m['id_Moneda']; ?>"><?php echo e(nombreMoneda($m['moneda'])); ?> (<?php echo e($m['moneda']); ?>)</option><?php } ?>
                </select></div>
            <div><label for="v_comprador">Comprador</label><input type="text" name="comprador" id="v_comprador" maxlength="100"></div>
            <div><label for="v_comprobante">Comprobante</label><input type="text" name="comprobante" id="v_comprobante" maxlength="40"></div>
            <div><label for="v_obs">Observaciones</label><input type="text" name="observaciones" id="v_obs" class="campo-ancho" maxlength="255"></div>
            <div class="filtros-botones"><button type="submit" class="btn btn-success">Guardar venta</button></div>
        </form>
    </details>
    <?php } ?>

    <form method="GET" action="./ventas.php" class="filtros-listado">
        <div><label for="desde">Desde</label><input type="date" name="desde" id="desde" value="<?php echo e($f['desde']); ?>"></div>
        <div><label for="hasta">Hasta</label><input type="date" name="hasta" id="hasta" value="<?php echo e($f['hasta']); ?>"></div>
        <div><label for="proyecto">Proyecto</label>
            <select name="proyecto" id="proyecto">
                <option value="0">Todos</option>
                <?php foreach ($proyectos as $p) { ?>
                <option value="<?php echo (int)$p['id_Proyecto']; ?>" <?php echo $f['proyecto'] === (int)$p['id_Proyecto'] ? 'selected' : ''; ?>><?php echo e($p['nombreProyecto']); ?></option>
                <?php } ?>
            </select></div>
        <div><label for="q">Concepto, comprador o comprobante</label><input type="text" name="q" id="q" class="campo-ancho" value="<?php echo e($f['q']); ?>" placeholder="Buscar..."></div>
        <div class="filtros-botones">
            <button type="submit" class="btn btn-success">Filtrar</button>
            <a href="./ventas.php" class="btn btn-outline-success">Limpiar</a>
        </div>
    </form>

    <div class="barra-listado">
        <span><b><?php echo count($ventas); ?></b> venta<?php echo count($ventas) === 1 ? '' : 's'; ?></span>
        <span class="barra-acciones">
            <a href="./resultados.php" class="btn btn-outline-success">Resultados por proyecto</a>
            <a href="./Exportar/ExcelVentas.php<?php echo $qs ? '?' . e($qs) : ''; ?>" class="btn btn-outline-success">Exportar a Excel</a>
        </span>
    </div>

    <?php if ($ventas) { ?>
    <div class="resumen-alquiler">
        <?php foreach ($totales as $mon => $t) { ?>
        <div class="tarjeta-resumen <?php echo $mon === 'USD' ? 'usd' : 'ars'; ?>">
            <span class="num"><?php echo e(montoMoneda($t['total'], $mon)); ?></span>
            <span>Vendido en <?php echo e(strtolower(nombreMoneda($mon))); ?> (<?php echo chipMoneda($mon); ?>) · <?php echo (int)$t['cantidad']; ?> venta<?php echo $t['cantidad'] === 1 ? '' : 's'; ?></span>
        </div>
        <?php } ?>
    </div>
    <?php } ?>

    <?php if (!$ventas) { ?>
    <p class="sin-datos">No hay ventas para mostrar<?php echo hayFiltrosVentas($f) ? ' con esos filtros' : ''; ?>.</p>
    <?php } else { ?>
    <table>
        <thead><tr><th>Fecha</th><th>Proyecto</th><th>Concepto</th><th style="text-align:right;">Cantidad</th><th>Comprador</th><th>Moneda</th><th style="text-align:right;">Importe</th><?php echo $escribe ? '<th style="text-align:center;">Quitar</th>' : ''; ?></tr></thead>
        <tbody>
        <?php foreach ($ventas as $v) { ?>
        <tr>
            <td class="nowrap"><?php echo e(date('d/m/Y', strtotime($v['fecha']))); ?></td>
            <td><a href="./resultados.php?no=<?php echo (int)$v['id_Proyecto']; ?>"><?php echo e($v['nombreProyecto']); ?></a></td>
            <td><?php echo e($v['concepto']); ?><?php echo $v['nroComprobante'] ? '<br><small>Comp. ' . e($v['nroComprobante']) . '</small>' : ''; ?></td>
            <td class="nowrap" style="text-align:right;"><?php echo $v['cantidad'] !== null ? e(number_format((float)$v['cantidad'], 2, ',', '.') . ' ' . $v['unidad']) : '-'; ?></td>
            <td><?php echo e($v['comprador']); ?></td>
            <td><?php echo chipMoneda($v['moneda']); ?></td>
            <td class="nowrap" style="text-align:right;"><b><?php echo e(montoMoneda($v['importe'], $v['moneda'])); ?></b></td>
            <?php if ($escribe) { ?>
            <td class="text-center">
                <form method="POST" action="./registrarVenta.php" onsubmit="return confirm('¿Quitar esta venta?');" style="display:inline;">
                    <input type="hidden" name="accion" value="eliminar"><input type="hidden" name="venta" value="<?php echo (int)$v['id_Venta']; ?>">
                    <button type="submit" class="btn btn-sm btn-outline-danger">Quitar</button>
                </form>
            </td>
            <?php } ?>
        </tr>
        <?php } ?>
        </tbody>
    </table>
    <?php } ?>
</center>
<script>
    const q = new URLSearchParams(window.location.search);
    if (q.get('ok') !== null) Swal.fire({icon: 'success', title: 'Venta registrada', timer: 1500, showConfirmButton: false});
    else if (q.get('borrada') !== null) Swal.fire({icon: 'success', title: 'Venta quitada', timer: 1500, showConfirmButton: false});
    else if (q.get('error') !== null) {
        const t = {proyecto: 'El proyecto no existe.', moneda: 'Elegí una moneda válida.', importe: 'El importe debe ser mayor a cero.', datos: 'Revisá la fecha, el concepto y los datos.'};
        Swal.fire({icon: 'error', title: 'No se guardó', text: t[q.get('error')] || t.datos});
    }
</script>
<?php
$conexion->close();
include('./Template/Pie.php');
?>
