<title>GAFA - Cobros de alquiler</title>
<?php
include('./Template/Cabecera.php');
include('./Modelo/Conexion.php');
include_once('./Modelo/Auth.php');
include_once('./Modelo/Alquileres.php');
include_once('./Modelo/Moneda.php');

$id = (int)($_GET['no'] ?? 0);
$contrato = null;
if (tablasAlquilerListas($conexion)) {
    $stmt = $conexion->prepare("SELECT da.*, p.nombreProyecto, p.id_Parcela, p.cantidadHas, m.moneda
        FROM detallealquiler da JOIN proyectos p ON p.id_Proyecto = da.id_Proyecto
        LEFT JOIN moneda m ON m.id_Moneda = da.id_Moneda WHERE da.id_DetalleAlquiler = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $contrato = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}
if (!$contrato) {
    echo '<center><h1>CONTRATO NO ENCONTRADO</h1><p><a class="btn btn-success" href="./alquileres.php">Volver a alquileres</a></p></center>';
    include('./Template/Pie.php');
    exit;
}

$stmt = $conexion->prepare("SELECT id_Cobro, fechaVencimiento, monto, fechaPago FROM cobrosalquiler WHERE id_DetalleAlquiler = ? ORDER BY fechaVencimiento");
$stmt->bind_param("i", $id);
$stmt->execute();
$cuotas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$hoy = date('Y-m-d');
$cobrado = $pendiente = $vencido = 0;
foreach ($cuotas as &$q) {
    if ($q['fechaPago']) { $q['estado'] = 'Cobrado'; $cobrado += (float)$q['monto']; }
    elseif ($q['fechaVencimiento'] < $hoy) { $q['estado'] = 'Vencida'; $vencido += (float)$q['monto']; $pendiente += (float)$q['monto']; }
    else { $q['estado'] = 'Pendiente'; $pendiente += (float)$q['monto']; }
}
unset($q);
$estado = estadoContrato($contrato['fechaInicio'], $contrato['fechaFin']);
$cod = $contrato['moneda'];
?>
<center>
    <h1>COBROS DEL ALQUILER</h1>

    <div class="ficha-contrato">
        <div>
            <h3><?php echo e($contrato['nombreProyecto']); ?></h3>
            <p>Parcela N° <?php echo (int)$contrato['id_Parcela']; ?> · <?php echo (int)$contrato['cantidadHas']; ?> has</p>
            <p><b>Inquilino:</b> <?php echo e($contrato['inquilino']); ?><?php echo $contrato['contacto'] ? ' · ' . e($contrato['contacto']) : ''; ?></p>
        </div>
        <div>
            <p><b>Contrato:</b> <?php echo e(fechaCorta($contrato['fechaInicio'])); ?> al <?php echo e(fechaCorta($contrato['fechaFin'])); ?>
                <span class="chip-estado <?php echo claseEstado($estado); ?>"><?php echo e($estado); ?></span></p>
            <p><b>Canon:</b> <?php echo e(montoMoneda($contrato['canon'], $cod)); ?> <?php echo chipMoneda($cod); ?> (<?php echo e($contrato['periodicidad']); ?>)</p>
            <?php if ($contrato['observaciones']) { ?><p><b>Obs.:</b> <?php echo e($contrato['observaciones']); ?></p><?php } ?>
        </div>
    </div>

    <?php include_once('./Modelo/Notificaciones.php');
    if (notificacionesListas($conexion)) { ?>
    <form method="POST" action="./guardarContacto.php" class="filtros-listado" style="margin-bottom:16px;">
        <input type="hidden" name="contrato" value="<?php echo (int)$contrato['id_DetalleAlquiler']; ?>">
        <div>
            <label for="email">Mail para avisos</label>
            <input type="email" name="email" id="email" class="campo-ancho" maxlength="120" value="<?php echo e($contrato['email'] ?? ''); ?>" placeholder="nombre@correo.com">
        </div>
        <div>
            <label for="telefono">WhatsApp para avisos</label>
            <input type="tel" name="telefono" id="telefono" maxlength="30" value="<?php echo e($contrato['telefono'] ?? ''); ?>" placeholder="3492 15 123456">
        </div>
        <div class="filtros-botones"><button type="submit" class="btn btn-success">Guardar contacto</button></div>
    </form>
    <?php } ?>

    <div class="resumen-alquiler">
        <div class="tarjeta-resumen"><span class="num"><?php echo e(montoMoneda($cobrado, $cod)); ?></span><span>Cobrado en <?php echo e(strtolower(nombreMoneda($cod))); ?> <?php echo chipMoneda($cod); ?></span></div>
        <div class="tarjeta-resumen aviso"><span class="num"><?php echo e(montoMoneda($pendiente, $cod)); ?></span><span>Pendiente de cobro</span></div>
        <div class="tarjeta-resumen mal"><span class="num"><?php echo e(montoMoneda($vencido, $cod)); ?></span><span>Vencido</span></div>
    </div>

    <table>
        <thead>
        <tr>
            <th>Vencimiento</th>
            <th style="text-align:right;">Monto</th>
            <th>Estado</th>
            <th>Cobrado el</th>
            <th style="text-align:center;">Acción</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($cuotas as $q) { ?>
        <tr>
            <td class="nowrap"><?php echo e(fechaCorta($q['fechaVencimiento'])); ?></td>
            <td class="nowrap" style="text-align:right;"><?php echo e(montoMoneda($q['monto'], $cod)); ?></td>
            <td><span class="chip-estado <?php echo claseEstado($q['estado']); ?>"><?php echo e($q['estado']); ?></span></td>
            <td class="nowrap"><?php echo $q['fechaPago'] ? e(fechaCorta($q['fechaPago'])) : '-'; ?></td>
            <td class="text-center">
                <form method="POST" action="./registrarCobro.php" style="display:inline-flex; gap:6px; align-items:center;">
                    <input type="hidden" name="cobro" value="<?php echo (int)$q['id_Cobro']; ?>">
                    <?php if ($q['fechaPago']) { ?>
                    <input type="hidden" name="accion" value="deshacer">
                    <button type="submit" class="btn btn-sm btn-outline-success">Deshacer cobro</button>
                    <?php } else { ?>
                    <input type="hidden" name="accion" value="cobrar">
                    <input type="date" name="fechaPago" value="<?php echo e($hoy); ?>" style="width:150px !important;">
                    <button type="submit" class="btn btn-sm btn-success">Registrar cobro</button>
                    <?php } ?>
                </form>
            </td>
        </tr>
        <?php } ?>
        </tbody>
    </table>
    <div style="margin:18px 0;"><a href="./alquileres.php" class="btn btn-outline-success">Volver a alquileres</a></div>
</center>
<script>
    const pq = new URLSearchParams(window.location.search);
    if (pq.get('errorContacto')) {
        const m = {email: 'El mail no es válido.', telefono: 'El teléfono no es válido (incluí el código de área).', tablas: 'Falta ejecutar migracion_notificaciones.sql.'};
        Swal.fire({icon: 'error', title: 'No se guardó el contacto', text: m[pq.get('errorContacto')] || ''});
    } else if (window.location.search.includes('ok')) {
        Swal.fire({icon: 'success', title: 'Guardado', timer: 1600, showConfirmButton: false});
    }
</script>
<?php
$conexion->close();
include('./Template/Pie.php');
?>
