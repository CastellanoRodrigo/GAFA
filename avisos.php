<?php
include('./Modelo/Conexion.php');
include_once('./Modelo/Auth.php');
include_once('./Modelo/Notificaciones.php');
requerirLogin();
$cfg = configNotificaciones();
$listas = notificacionesListas($conexion);
$avisos = $listas ? construirAvisos($conexion, $cfg) : [];

$grupos = ['mal' => [], 'aviso' => []];
foreach ($avisos as $a) {
    $grupos[$a['gravedad']][] = $a;
}
$driverMail = $cfg['email']['driver'];
$driverWa = $cfg['whatsapp']['driver'];
$etiquetaMail = ['archivo' => 'Modo prueba (no envía, guarda el mensaje)', 'smtp' => 'SMTP (' . $cfg['email']['host'] . ')', 'mail' => 'Función mail() del servidor'][$driverMail] ?? $driverMail;
$etiquetaWa = ['enlace' => 'Enlace de WhatsApp (un clic para enviar)', 'cloud_api' => 'WhatsApp Business API (automático)'][$driverWa] ?? $driverWa;

function fechaAviso($conexion, array $a, string $canal): string
{
    $f = ultimoAviso($conexion, $a['tipo'], $a['ref'], $canal);
    return $f ? date('d/m/Y', strtotime($f)) : '';
}
?>
<title>Don Juan S.R.L - Avisos</title>
<?php include('./Template/Cabecera.php'); ?>
<center>
    <h1>AVISOS DE VENCIMIENTOS Y PAGOS</h1>

    <?php if (!$listas) { ?>
    <div class="centrado--form" style="margin:0 auto;text-align:left;">
        <p><b>Falta preparar la base de datos.</b> Ejecutá una vez <code>migracion_alquiler.sql</code> y luego
        <code>migracion_notificaciones.sql</code> sobre la base <code>donjuanbd</code>, y recargá esta página.</p>
    </div>
    <?php } else { ?>

    <div class="resumen-alquiler">
        <div class="tarjeta-resumen mal"><span class="num"><?php echo count($grupos['mal']); ?></span><span>Cuotas vencidas sin cobrar</span></div>
        <div class="tarjeta-resumen aviso"><span class="num"><?php echo count($grupos['aviso']); ?></span><span>Próximos vencimientos (cuotas y contratos)</span></div>
        <div class="tarjeta-resumen"><span class="num" style="font-size:1rem;">Mail: <?php echo e($etiquetaMail); ?></span><span style="font-size:1rem;">WhatsApp: <?php echo e($etiquetaWa); ?></span></div>
    </div>

    <div class="barra-listado">
        <span class="barra-acciones">
            <form method="POST" action="./enviarAviso.php" style="display:inline;" onsubmit="return confirm('¿Enviar por mail todos los avisos pendientes?');">
                <input type="hidden" name="accion" value="todos">
                <button class="btn btn-success" type="submit">Enviar por mail todos los pendientes</button>
            </form>
            <form method="POST" action="./enviarAviso.php" style="display:inline;">
                <input type="hidden" name="accion" value="resumen">
                <button class="btn btn-outline-success" type="submit">Enviarme el resumen</button>
            </form>
        </span>
    </div>

    <?php if (!$avisos) { ?>
    <p class="sin-datos">No hay vencimientos ni pagos pendientes de aviso. ¡Todo al día!</p>
    <?php } else { ?>
    <table>
        <thead>
        <tr>
            <th>Aviso</th>
            <th>Detalle</th>
            <th>Vence</th>
            <th>Contacto</th>
            <th style="text-align:center;">Enviar</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach (array_merge($grupos['mal'], $grupos['aviso']) as $a) {
            $tel = normalizarTelefono($a['telefono'], $cfg['whatsapp']['codigo_pais']);
            $mailOk = emailValido($a['email']);
            $waOk = telefonoValido($tel);
            $ultMail = fechaAviso($conexion, $a, 'email');
            $ultWa = fechaAviso($conexion, $a, 'whatsapp'); ?>
        <tr>
            <td><span class="chip-estado <?php echo $a['gravedad'] === 'mal' ? 'est-mal' : 'est-aviso'; ?>"><?php echo e($a['titulo']); ?></span></td>
            <td><?php echo e($a['detalle']); ?></td>
            <td class="nowrap"><?php echo e(fechaCorta($a['vencimiento'])); ?></td>
            <td><small><?php echo $mailOk ? e($a['email']) : '<span class="texto-mal">Sin mail</span>'; ?><br>
                <?php echo $waOk ? e('+' . $tel) : '<span class="texto-mal">Sin teléfono</span>'; ?></small></td>
            <td class="text-center text-nowrap">
                <form method="POST" action="./enviarAviso.php" style="display:inline;">
                    <input type="hidden" name="tipo" value="<?php echo e($a['tipo']); ?>">
                    <input type="hidden" name="ref" value="<?php echo (int)$a['ref']; ?>">
                    <input type="hidden" name="accion" value="uno">
                    <button class="btn btn-sm btn-outline-success" name="canal" value="email" type="submit" <?php echo $mailOk ? '' : 'disabled'; ?>
                        title="<?php echo $ultMail ? 'Último aviso: ' . e($ultMail) : 'Enviar por mail'; ?>">✉ Mail<?php echo $ultMail ? ' ✓' : ''; ?></button>
                    <button class="btn btn-sm btn-success" name="canal" value="whatsapp" type="submit" <?php echo $waOk ? '' : 'disabled'; ?>
                        title="<?php echo $ultWa ? 'Último aviso: ' . e($ultWa) : 'Enviar por WhatsApp'; ?>">WhatsApp<?php echo $ultWa ? ' ✓' : ''; ?></button>
                </form>
                <a class="btn btn-sm btn-outline-success" href="./cobrosAlquiler.php?no=<?php echo (int)$a['contrato']; ?>">Contrato</a>
            </td>
        </tr>
        <?php } ?>
        </tbody>
    </table>
    <p class="ayuda-form" style="margin:14px 0;">✓ = ya se avisó antes por ese canal. No se repite el mismo aviso antes de <?php echo (int)$cfg['reenviar_cada_dias']; ?> días.
        Cargá el mail y el teléfono del inquilino en la pantalla del contrato.</p>
    <?php } ?>

    <?php } ?>
</center>
<script>
    const p = new URLSearchParams(window.location.search);
    if (p.get('ok')) {
        Swal.fire({icon: 'success', title: p.get('ok'), timer: 2600, showConfirmButton: false});
    } else if (p.get('error')) {
        Swal.fire({icon: 'error', title: 'No se pudo enviar', text: p.get('error')});
    }
</script>
<?php
$conexion->close();
include('./Template/Pie.php');
?>
