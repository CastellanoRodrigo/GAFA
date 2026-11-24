<?php
include('./Modelo/Conexion.php');
include_once('./Modelo/Auth.php');
include_once('./Modelo/Respaldo.php');
requerirAdmin($conexion);
$guardados = listarRespaldos();
?>
<title>GAFA - Copias de seguridad</title>
<?php include('./Template/Cabecera.php'); ?>
<center>
    <h1>COPIAS DE SEGURIDAD</h1>
    <div class="resumen-alquiler">
        <div class="tarjeta-resumen"><span class="num"><?php echo count($guardados); ?></span><span>Copias guardadas en el servidor</span></div>
        <div class="tarjeta-resumen aviso"><span class="num" style="font-size:1rem;">Descargá una copia seguido y guardala fuera de esta computadora.</span><span>Si se rompe el disco, sin copia se pierde todo.</span></div>
    </div>
    <div class="barra-listado">
        <span class="barra-acciones">
            <form method="POST" action="./descargarRespaldo.php" style="display:inline;">
                <input type="hidden" name="accion" value="descargar">
                <button class="btn btn-success" type="submit">Descargar copia ahora</button>
            </form>
            <form method="POST" action="./descargarRespaldo.php" style="display:inline;">
                <input type="hidden" name="accion" value="guardar">
                <button class="btn btn-outline-success" type="submit">Guardar copia en el servidor</button>
            </form>
        </span>
    </div>
    <?php if (!$guardados) { ?>
    <p class="sin-datos">Todavía no hay copias guardadas en el servidor.</p>
    <?php } else { ?>
    <table>
        <thead><tr><th>Archivo</th><th>Fecha</th><th style="text-align:right;">Tamaño</th><th style="text-align:center;">Descargar</th></tr></thead>
        <tbody>
        <?php foreach ($guardados as $g) { ?>
        <tr>
            <td><?php echo e($g['archivo']); ?></td>
            <td class="nowrap"><?php echo e(date('d/m/Y H:i', $g['fecha'])); ?></td>
            <td class="nowrap" style="text-align:right;"><?php echo e(number_format($g['tamano'] / 1024, 1, ',', '.')); ?> KB</td>
            <td class="text-center">
                <form method="POST" action="./descargarRespaldo.php" style="display:inline;">
                    <input type="hidden" name="accion" value="bajar">
                    <input type="hidden" name="archivo" value="<?php echo e($g['archivo']); ?>">
                    <button class="btn btn-sm btn-outline-success" type="submit">Descargar</button>
                </form>
            </td>
        </tr>
        <?php } ?>
        </tbody>
    </table>
    <?php } ?>
    <p class="ayuda-form" style="margin:16px auto;max-width:760px;">
        <b>Copia automática:</b> programá <code>php cron/respaldo.php</code> una vez por día (Programador de tareas de Windows); se conservan las últimas 14.<br>
        <b>Para restaurar:</b> importá el archivo .sql en MariaDB/MySQL (phpMyAdmin → Importar) sobre la base de datos del sistema.
    </p>
</center>
<script>
    const q = new URLSearchParams(window.location.search);
    if (q.get('ok') !== null) Swal.fire({icon: 'success', title: 'Copia guardada en el servidor', timer: 1800, showConfirmButton: false});
    else if (q.get('error')) Swal.fire({icon: 'error', title: 'No se pudo completar', text: q.get('error')});
</script>
<?php include('./Template/Pie.php'); ?>
