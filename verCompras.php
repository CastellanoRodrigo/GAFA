<title>GAFA - Compras</title>
<?php
include('./Template/Cabecera.php');
include('./Modelo/Conexion.php');
include_once('./Modelo/Auth.php');
include_once('./Modelo/Compras.php');
include_once('./Modelo/Moneda.php');

$f = filtrosCompras($_GET);
$compras = consultarCompras($conexion, $f);
$proveedores = mysqli_query($conexion, "SELECT id_Proveedor, proveedor FROM proveedores ORDER BY proveedor");

// Totales discriminados por moneda (nunca se suman pesos con dolares)
$totales = totalesPorMoneda($compras, 'importeTotal');
$netos = totalesPorMoneda($compras, 'importeNeto');
$qs = http_build_query(array_filter($f, function ($v) { return $v !== '' && $v !== 0; }));
?>
<center>
    <h1>COMPRAS CARGADAS</h1>

    <form method="GET" action="./verCompras.php" class="filtros-listado">
        <div>
            <label for="desde">Desde</label>
            <input type="date" name="desde" id="desde" value="<?php echo e($f['desde']); ?>">
        </div>
        <div>
            <label for="hasta">Hasta</label>
            <input type="date" name="hasta" id="hasta" value="<?php echo e($f['hasta']); ?>">
        </div>
        <div>
            <label for="proveedor">Proveedor</label>
            <select name="proveedor" id="proveedor">
                <option value="0">Todos</option>
                <?php while ($p = mysqli_fetch_assoc($proveedores)) { ?>
                <option value="<?php echo (int)$p['id_Proveedor']; ?>" <?php echo $f['proveedor'] === (int)$p['id_Proveedor'] ? 'selected' : ''; ?>><?php echo e($p['proveedor']); ?></option>
                <?php } ?>
            </select>
        </div>
        <div>
            <label for="q">Factura o detalle</label>
            <input type="text" name="q" id="q" class="campo-ancho" value="<?php echo e($f['q']); ?>" placeholder="Buscar...">
        </div>
        <div class="filtros-botones">
            <button type="submit" class="btn btn-success">Filtrar</button>
            <a href="./verCompras.php" class="btn btn-outline-success">Limpiar</a>
        </div>
    </form>

    <div class="barra-listado">
        <span><b><?php echo count($compras); ?></b> compra<?php echo count($compras) === 1 ? '' : 's'; ?></span>
        <span class="barra-acciones">
            <a href="./altaCompras.php" class="btn btn-success">Cargar compra</a>
            <a href="./asignarCompra.php" class="btn btn-outline-success">Asignar compras</a>
            <a href="./Exportar/ExcelCompras.php<?php echo $qs ? '?' . e($qs) : ''; ?>" class="btn btn-outline-success">Exportar a Excel</a>
        </span>
    </div>

    <?php if ($compras) { ?>
    <div class="resumen-alquiler">
        <?php foreach ($totales as $mon => $t) { ?>
        <div class="tarjeta-resumen <?php echo $mon === 'USD' ? 'usd' : 'ars'; ?>">
            <span class="num"><?php echo e(montoMoneda($t['total'], $mon)); ?></span>
            <span>Total en <?php echo e(strtolower(nombreMoneda($mon))); ?> (<?php echo chipMoneda($mon); ?>) · <?php echo (int)$t['cantidad']; ?> compra<?php echo $t['cantidad'] === 1 ? '' : 's'; ?> · neto <?php echo e(montoMoneda($netos[$mon]['total'], $mon)); ?></span>
        </div>
        <?php } ?>
    </div>
    <?php } ?>

    <?php if (!$compras) { ?>
    <p class="sin-datos">No hay compras para mostrar<?php echo hayFiltrosCompras($f) ? ' con esos filtros' : ''; ?>.</p>
    <?php } else { ?>
    <table>
        <thead>
        <tr>
            <th>Fecha</th>
            <th class="col-factura">Factura</th>
            <th>Proveedor</th>
            <th>Destino</th>
            <th>Moneda</th>
            <th style="text-align:right;">Neto</th>
            <th style="text-align:right;">Total</th>
            <th>Pago</th>
            <th style="text-align:center;">Detalle</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($compras as $c) { ?>
        <tr>
            <td class="nowrap"><?php echo e(date('d/m/Y', strtotime($c['fecha']))); ?></td>
            <td class="col-factura"><?php echo e($c['tipoFactura']); ?> <?php echo e(str_pad((string)$c['puntoDeVenta'], 4, '0', STR_PAD_LEFT)); ?>-<?php echo e(str_pad((string)$c['nroFactura'], 8, '0', STR_PAD_LEFT)); ?></td>
            <td><?php echo e($c['proveedor']); ?></td>
            <td><?php echo (int)$c['id_TipoCompra'] === 2 && $c['nombreProyecto'] ? e($c['nombreProyecto']) : '<span class="chip-general">General</span>'; ?></td>
            <td><?php echo chipMoneda($c['moneda']); ?></td>
            <td class="nowrap" style="text-align:right;"><?php echo e(montoMoneda($c['importeNeto'], $c['moneda'])); ?></td>
            <td class="nowrap" style="text-align:right;"><b><?php echo e(montoMoneda($c['importeTotal'], $c['moneda'])); ?></b></td>
            <td><?php echo e($c['formaPago']); ?></td>
            <td class="text-center text-nowrap"><a class="btn btn-sm btn-outline-success" href="./detalleCompra.php?no=<?php echo (int)$c['id_Compras']; ?>">Ver</a></td>
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
