<title>GAFA - Alquileres</title>
<?php
include('./Template/Cabecera.php');
include('./Modelo/Conexion.php');
include_once('./Modelo/Auth.php');
include_once('./Modelo/Alquileres.php');
include_once('./Modelo/Moneda.php');

$listas = tablasAlquilerListas($conexion);
$f = filtrosAlquileres($_GET);
$contratos = $listas ? consultarAlquileres($conexion, $f) : [];

// Resumen sobre TODOS los contratos (sin filtrar), para que los avisos no dependan del filtro
$todos = $listas ? consultarAlquileres($conexion, ['estado' => '', 'q' => '']) : [];
$cuenta = array_fill_keys(ESTADOS_CONTRATO, 0);
foreach ($todos as $c) {
    $cuenta[$c['estado']]++;
}
// Cobros discriminados por moneda (sobre los contratos que se estan viendo)
$cobradoMon = totalesPorMoneda($contratos, 'cobrado');
$porCobrarMon = totalesPorMoneda($contratos, 'porCobrar');
$vencidoMon = totalesPorMoneda($contratos, 'vencido');
$qs = http_build_query(array_filter($f, function ($v) { return $v !== ''; }));
?>
<center>
    <h1>ALQUILER DE PARCELAS</h1>

    <?php if (!$listas) { ?>
    <div class="centrado--form" style="margin:0 auto;text-align:left;">
        <p><b>Falta preparar la base de datos.</b> Ejecutá una vez el archivo <code>migracion_alquiler.sql</code> sobre la base
        <code>donjuanbd</code> y recargá esta página.</p>
    </div>
    <?php } else { ?>

    <div class="resumen-alquiler">
        <div class="tarjeta-resumen"><span class="num"><?php echo $cuenta['Vigente']; ?></span><span>Vigentes</span></div>
        <div class="tarjeta-resumen aviso"><span class="num"><?php echo $cuenta['Por vencer']; ?></span><span>Por vencer (<?php echo DIAS_POR_VENCER; ?> días)</span></div>
        <div class="tarjeta-resumen mal"><span class="num"><?php echo $cuenta['Vencido']; ?></span><span>Vencidos</span></div>
    </div>
    <?php if ($contratos) { ?>
    <div class="resumen-alquiler">
        <?php foreach ($porCobrarMon as $mon => $t) { ?>
        <div class="tarjeta-resumen <?php echo $mon === 'USD' ? 'usd' : 'ars'; ?>">
            <span class="num"><?php echo e(montoMoneda($t['total'], $mon)); ?></span>
            <span>Por cobrar en <?php echo e(strtolower(nombreMoneda($mon))); ?> (<?php echo chipMoneda($mon); ?>)
                · cobrado <?php echo e(montoMoneda($cobradoMon[$mon]['total'], $mon)); ?>
                · vencido <b class="texto-mal"><?php echo e(montoMoneda($vencidoMon[$mon]['total'], $mon)); ?></b></span>
        </div>
        <?php } ?>
    </div>
    <?php } ?>

    <form method="GET" action="./alquileres.php" class="filtros-listado">
        <div>
            <label for="estado">Estado del contrato</label>
            <select name="estado" id="estado">
                <option value="">Todos</option>
                <?php foreach (ESTADOS_CONTRATO as $est) { ?>
                <option value="<?php echo e($est); ?>" <?php echo $f['estado'] === $est ? 'selected' : ''; ?>><?php echo e($est); ?></option>
                <?php } ?>
            </select>
        </div>
        <div>
            <label for="q">Inquilino o proyecto</label>
            <input type="text" name="q" id="q" class="campo-ancho" value="<?php echo e($f['q']); ?>" placeholder="Buscar...">
        </div>
        <div class="filtros-botones">
            <button type="submit" class="btn btn-success">Filtrar</button>
            <a href="./alquileres.php" class="btn btn-outline-success">Limpiar</a>
        </div>
    </form>

    <div class="barra-listado">
        <span><b><?php echo count($contratos); ?></b> contrato<?php echo count($contratos) === 1 ? '' : 's'; ?></span>
        <span class="barra-acciones">
            <a href="./altaProyectos.php" class="btn btn-outline-success">Nuevo proyecto de alquiler</a>
            <a href="./detalleAlquiler.php" class="btn btn-success">Cargar contrato</a>
            <a href="./Exportar/ExcelAlquileres.php<?php echo $qs ? '?' . e($qs) : ''; ?>" class="btn btn-outline-success">Exportar a Excel</a>
        </span>
    </div>

    <?php if (!$contratos) { ?>
    <p class="sin-datos">No hay contratos de alquiler<?php echo hayFiltrosAlquileres($f) ? ' con esos filtros' : ' cargados todavía'; ?>.</p>
    <?php } else { ?>
    <table>
        <thead>
        <tr>
            <th>Proyecto / parcela</th>
            <th>Inquilino</th>
            <th>Contrato</th>
            <th>Estado</th>
            <th style="text-align:right;">Canon</th>
            <th>Próximo cobro</th>
            <th style="text-align:right;">Vencido</th>
            <th style="text-align:center;">Cuotas</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($contratos as $c) { ?>
        <tr>
            <td><b><?php echo e($c['nombreProyecto']); ?></b><br><small>Parcela N° <?php echo (int)$c['id_Parcela']; ?> · <?php echo (int)$c['cantidadHas']; ?> has</small></td>
            <td><?php echo e($c['inquilino']); ?><?php echo $c['contacto'] ? '<br><small>' . e($c['contacto']) . '</small>' : ''; ?></td>
            <td class="nowrap"><?php echo e(fechaCorta($c['fechaInicio'])); ?> al <?php echo e(fechaCorta($c['fechaFin'])); ?></td>
            <td><span class="chip-estado <?php echo claseEstado($c['estado']); ?>"><?php echo e($c['estado']); ?></span></td>
            <td class="nowrap" style="text-align:right;"><?php echo e(montoMoneda($c['canon'], $c['moneda'])); ?> <?php echo chipMoneda($c['moneda']); ?><br><small><?php echo e($c['periodicidad']); ?></small></td>
            <td class="nowrap"><?php echo $c['proximo'] ? e(fechaCorta($c['proximo'])) : '<small>Sin pendientes</small>'; ?></td>
            <td class="nowrap" style="text-align:right;"><?php echo (float)$c['vencido'] > 0 ? '<b class="texto-mal">' . e(montoMoneda($c['vencido'], $c['moneda'])) . '</b>' : '-'; ?></td>
            <td class="text-center text-nowrap"><a class="btn btn-sm btn-outline-success" href="./cobrosAlquiler.php?no=<?php echo (int)$c['id_DetalleAlquiler']; ?>">Ver cobros (<?php echo (int)$c['pendientes']; ?>)</a></td>
        </tr>
        <?php } ?>
        </tbody>
    </table>
    <?php } ?>
    <?php } ?>
</center>
<?php
$conexion->close();
include('./Template/Pie.php');
?>
