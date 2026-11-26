<title>GAFA - Movimientos de hacienda</title>
<?php
include('./Template/Cabecera.php');
include('./Modelo/Conexion.php');
include_once('./Modelo/Auth.php');
include_once('./Modelo/Hacienda.php');
include_once('./Modelo/Ventas.php');

if (!haciendaListas($conexion)) {
    echo '<center><h1>MOVIMIENTOS DE HACIENDA</h1><p class="sin-datos">Falta ejecutar <b>migracion_hacienda.sql</b> en la base de datos (ver el README).</p></center>';
    include('./Template/Pie.php');
    exit;
}
$escribe = puedeEscribir($conexion);
$id = (int)($_GET['no'] ?? 0);

// ---- Listado de proyectos de hacienda con stock ----
if ($id === 0 || !esProyectoHacienda($conexion, $id)) {
    $ps = proyectosConStock($conexion);
    $total = array_sum(array_column($ps, 'actual'));
    ?>
<center>
    <h1>HACIENDA: STOCK DE CABEZAS</h1>
    <div class="resumen-alquiler"><div class="tarjeta-resumen"><span class="num"><?php echo (int)$total; ?></span><span>Cabezas en total (todos los proyectos de hacienda)</span></div></div>
    <?php if (!$ps) { ?><p class="sin-datos">No hay proyectos de hacienda.</p><?php } else { ?>
    <table><thead><tr><th>Proyecto</th><th>Estado</th><th style="text-align:right;">Iniciales</th><th style="text-align:right;">Entradas</th><th style="text-align:right;">Salidas</th><th style="text-align:right;">Stock actual</th><th style="text-align:center;">Movimientos</th></tr></thead><tbody>
        <?php foreach ($ps as $p) { ?>
        <tr><td><?php echo e($p['nombreProyecto']); ?></td><td><?php echo e($p['estado']); ?></td>
            <td style="text-align:right;"><?php echo (int)$p['inicial']; ?></td><td style="text-align:right;">+<?php echo (int)$p['entradas']; ?></td><td style="text-align:right;">−<?php echo (int)$p['salidas']; ?></td>
            <td style="text-align:right;"><b><?php echo (int)$p['actual']; ?></b></td>
            <td class="text-center"><a class="btn btn-sm btn-outline-success" href="./movimientosHacienda.php?no=<?php echo (int)$p['id_Proyecto']; ?>">Ver</a></td></tr>
        <?php } ?></tbody></table>
    <?php } ?>
</center>
<?php
    $conexion->close();
    include('./Template/Pie.php');
    exit;
}

// ---- Detalle de un proyecto ----
$s = $conexion->prepare("SELECT nombreProyecto FROM proyectos WHERE id_Proyecto = ?");
$s->bind_param('i', $id);
$s->execute();
$nombre = $s->get_result()->fetch_row()[0];
$s->close();
$stock = stockHacienda($conexion, $id);
$movs = movimientosHacienda($conexion, $id);
$monedas = ventasListas($conexion) ? mysqli_fetch_all(mysqli_query($conexion, "SELECT id_Moneda, moneda FROM moneda ORDER BY id_Moneda"), MYSQLI_ASSOC) : [];
?>
<center>
    <h1>HACIENDA: <?php echo e(mb_strtoupper($nombre)); ?></h1>
    <div class="resumen-alquiler">
        <div class="tarjeta-resumen"><span class="num"><?php echo (int)$stock['actual']; ?></span><span>Cabezas actuales</span></div>
        <div class="tarjeta-resumen"><span class="num"><?php echo (int)$stock['inicial']; ?></span><span>Iniciales</span></div>
        <div class="tarjeta-resumen aviso"><span class="num">+<?php echo (int)$stock['entradas']; ?> / −<?php echo (int)$stock['salidas']; ?></span><span>Entradas / salidas</span></div>
    </div>

    <?php if ($escribe) { ?>
    <form method="POST" action="./registrarMovimiento.php" class="filtros-listado">
        <input type="hidden" name="accion" value="crear"><input type="hidden" name="proyecto" value="<?php echo (int)$id; ?>">
        <div><label for="tipo">Movimiento</label>
            <select name="tipo" id="tipo" required>
                <?php foreach (MOVIMIENTOS_HACIENDA as $t => $sg) { ?><option value="<?php echo e($t); ?>"><?php echo e($t); ?> (<?php echo $sg > 0 ? 'suma' : 'resta'; ?>)</option><?php } ?>
            </select></div>
        <div><label for="fecha">Fecha</label><input type="date" name="fecha" id="fecha" value="<?php echo date('Y-m-d'); ?>" required></div>
        <div><label for="cantidad">Cabezas</label><input type="number" name="cantidad" id="cantidad" min="1" max="1000000" required style="width:110px !important;"></div>
        <div><label for="observaciones">Observaciones</label><input type="text" name="observaciones" id="observaciones" class="campo-ancho" maxlength="255"></div>
        <?php if ($monedas) { ?>
        <div><label for="importe">Si es venta: importe total</label><input type="number" name="importe" id="importe" min="0" step="0.01" placeholder="opcional"></div>
        <div><label for="moneda">Moneda</label><select name="moneda" id="moneda"><?php foreach ($monedas as $m) { ?><option value="<?php echo (int)$m['id_Moneda']; ?>"><?php echo e($m['moneda']); ?></option><?php } ?></select></div>
        <div><label for="comprador">Comprador</label><input type="text" name="comprador" id="comprador" maxlength="100"></div>
        <?php } ?>
        <div class="filtros-botones"><button type="submit" class="btn btn-success">Registrar</button></div>
    </form>
    <p><small>Si cargás el importe de una venta, también se registra en Ventas y suma al resultado del proyecto.</small></p>
    <?php } ?>

    <?php if (!$movs) { ?><p class="sin-datos">Todavía no hay movimientos.</p><?php } else { ?>
    <table><thead><tr><th>Fecha</th><th>Movimiento</th><th style="text-align:right;">Cabezas</th><th>Ingreso</th><th>Observaciones</th><?php echo $escribe ? '<th style="text-align:center;">Quitar</th>' : ''; ?></tr></thead><tbody>
        <?php foreach ($movs as $m) { $suma = (MOVIMIENTOS_HACIENDA[$m['tipo']] ?? 0) > 0; ?>
        <tr><td class="nowrap"><?php echo e(date('d/m/Y', strtotime($m['fecha']))); ?></td><td><?php echo e($m['tipo']); ?></td>
            <td style="text-align:right;"><b><?php echo $suma ? '+' : '−'; ?><?php echo (int)$m['cantidad']; ?></b></td>
            <td class="nowrap"><?php echo $m['importe'] !== null ? e(montoMoneda($m['importe'], $m['moneda'])) . ' ' . chipMoneda($m['moneda']) : '-'; ?></td>
            <td><?php echo e($m['observaciones']); ?></td>
            <?php if ($escribe) { ?><td class="text-center"><form method="POST" action="./registrarMovimiento.php" onsubmit="return confirm('¿Quitar este movimiento?');" style="display:inline;">
                <input type="hidden" name="accion" value="eliminar"><input type="hidden" name="movimiento" value="<?php echo (int)$m['id_Movimiento']; ?>"><button type="submit" class="btn btn-sm btn-outline-danger">Quitar</button></form></td><?php } ?>
        </tr>
        <?php } ?></tbody></table>
    <?php } ?>
    <p><a class="btn btn-outline-success" href="./movimientosHacienda.php">Todos los proyectos de hacienda</a>
       <a class="btn btn-outline-success" href="./resultados.php?no=<?php echo (int)$id; ?>">Resultado del proyecto</a></p>
</center>
<script>
    const q = new URLSearchParams(window.location.search);
    if (q.get('ok') !== null) Swal.fire({icon: 'success', title: 'Movimiento registrado', timer: 1500, showConfirmButton: false});
    else if (q.get('borrado') !== null) Swal.fire({icon: 'success', title: 'Movimiento quitado', timer: 1500, showConfirmButton: false});
    else if (q.get('error') !== null) {
        const t = {stock: 'No hay suficientes cabezas: el stock no puede quedar en negativo.', cantidad: 'La cantidad debe ser un número entero mayor a cero.', datos: 'Revisá la fecha y los datos.'};
        Swal.fire({icon: 'error', title: 'No se guardó', text: t[q.get('error')] || t.datos});
    }
</script>
<?php
$conexion->close();
include('./Template/Pie.php');
?>
