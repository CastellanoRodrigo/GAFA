<title>Don Juan S.R.L - Compras</title>
<?php
include('./Template/Cabecera.php');
include('./Modelo/Conexion.php');
include('./Modelo/Auth.php');
include('./Modelo/Compras.php');

$f = filtrosCompras($_GET);
$compras = consultarCompras($conexion, $f);
$proveedores = mysqli_query($conexion, "SELECT id_Proveedor, proveedor FROM proveedores ORDER BY proveedor");

// Totales por moneda (no se pueden sumar pesos con dolares)
$totales = [];
foreach ($compras as $c) {
    $mon = $c['moneda'] ?? '-';
    $totales[$mon] = ($totales[$mon] ?? 0) + (float)$c['importeTotal'];
}
$qs = http_build_query(array_filter($f, function ($v) { return $v !== '' && $v !== 0; }));
?>
<center>
    <h1>COMPRAS CARGADAS</h1>

    <form method="GET" action="./verCompras.php" class="filtros-compras">
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
            <input type="text" name="q" id="q" value="<?php echo e($f['q']); ?>" placeholder="Buscar...">
        </div>
        <div class="filtros-botones">
            <button type="submit" class="btn btn-success">Filtrar</button>
            <a href="./verCompras.php" class="btn btn-outline-success">Limpiar</a>
        </div>
    </form>

    <div class="barra-compras">
        <span><b><?php echo count($compras); ?></b> compra<?php echo count($compras) === 1 ? '' : 's'; ?></span>
        <?php foreach ($totales as $mon => $suma) { ?>
        <span class="chip-total"><?php echo e($mon); ?> <b>$<?php echo number_format($suma, 2, ',', '.'); ?></b></span>
        <?php } ?>
        <span class="barra-acciones">
            <a href="./altaCompras.php" class="btn btn-success">Cargar compra</a>
            <a href="./Exportar/ExcelCompras.php<?php echo $qs ? '?' . e($qs) : ''; ?>" class="btn btn-outline-success">Exportar a Excel</a>
        </span>
    </div>

    <?php if (!$compras) { ?>
    <p class="sin-datos">No hay compras para mostrar<?php echo hayFiltrosCompras($f) ? ' con esos filtros' : ''; ?>.</p>
    <?php } else { ?>
    <table>
        <thead>
        <tr>
            <th>Fecha</th>
            <th>Factura</th>
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
            <td><?php echo e(date('d/m/Y', strtotime($c['fecha']))); ?></td>
            <td><?php echo e($c['tipoFactura'] . ' ' . str_pad((string)$c['puntoDeVenta'], 4, '0', STR_PAD_LEFT) . '-' . $c['nroFactura']); ?></td>
            <td><?php echo e($c['proveedor']); ?></td>
            <td><?php echo (int)$c['id_TipoCompra'] === 2 && $c['nombreProyecto'] ? e($c['nombreProyecto']) : '<span class="chip-general">General</span>'; ?></td>
            <td><?php echo e($c['moneda']); ?></td>
            <td style="text-align:right;">$<?php echo number_format((float)$c['importeNeto'], 2, ',', '.'); ?></td>
            <td style="text-align:right;"><b>$<?php echo number_format((float)$c['importeTotal'], 2, ',', '.'); ?></b></td>
            <td><?php echo e($c['formaPago']); ?></td>
            <td class="text-center text-nowrap"><a class="btn btn-sm btn-outline-success" href="./detalleCompra.php?no=<?php echo (int)$c['id_Compras']; ?>">Ver</a></td>
        </tr>
        <?php } ?>
        </tbody>
    </table>
    <?php } ?>
</center>

<style>
    .filtros-compras {
        width: min(1200px, 94vw);
        display: flex; flex-wrap: wrap; gap: 14px; align-items: flex-end;
        background: var(--papel); border: 1px solid var(--borde); border-radius: var(--radio);
        box-shadow: var(--sombra); padding: 16px 20px; margin: 0 auto 16px; text-align: left;
    }
    .filtros-compras > div { display: flex; flex-direction: column; gap: 4px; }
    .filtros-compras label { font-weight: 700; color: #4a3517; font-size: .9rem; }
    .filtros-compras select, .filtros-compras input { width: 200px !important; }
    .filtros-botones { flex-direction: row !important; gap: 8px !important; }
    .barra-compras {
        width: min(1200px, 94vw); margin: 0 auto 14px;
        display: flex; flex-wrap: wrap; gap: 12px; align-items: center; text-align: left;
    }
    .barra-acciones { margin-left: auto; display: flex; gap: 8px; }
    .chip-total { background: var(--verde-claro); color: var(--verde-oscuro); border-radius: 999px; padding: 4px 14px; }
    .chip-general { background: #efe7d0; color: #4a3517; border-radius: 999px; padding: 2px 10px; font-size: .85rem; font-weight: 700; }
    .sin-datos { padding: 40px 0; color: var(--texto-suave); font-size: 1.1rem; }
</style>
<?php
$conexion->close();
include('./Template/Pie.php');
?>
