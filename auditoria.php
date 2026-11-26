<?php
include('./Modelo/Conexion.php');
include_once('./Modelo/Auth.php');
include_once('./Modelo/Auditoria.php');
requerirAdmin($conexion);
$listo = seguridadLista($conexion);
$u = trim((string)($_GET['usuario'] ?? ''));
$filas = [];
if ($listo) {
    if ($u !== '') {
        $s = $conexion->prepare("SELECT fecha, usuario, accion, detalle, ip FROM auditoria WHERE usuario = ? ORDER BY id_Auditoria DESC LIMIT 300");
        $s->bind_param('s', $u);
        $s->execute();
        $filas = $s->get_result()->fetch_all(MYSQLI_ASSOC);
        $s->close();
    } else {
        $filas = mysqli_fetch_all(mysqli_query($conexion, "SELECT fecha, usuario, accion, detalle, ip FROM auditoria ORDER BY id_Auditoria DESC LIMIT 300"), MYSQLI_ASSOC);
    }
}
?>
<title>GAFA - Registro de actividad</title>
<?php include('./Template/Cabecera.php'); ?>
<center>
    <h1>REGISTRO DE ACTIVIDAD</h1>
    <?php if (!$listo) { ?>
    <p class="sin-datos">Falta ejecutar <b>migracion_seguridad.sql</b> en la base de datos (ver el README).</p>
    <?php } else { ?>
    <form method="GET" action="./auditoria.php" class="filtros-listado">
        <div><label for="usuario">Usuario</label><input type="text" name="usuario" id="usuario" value="<?php echo e($u); ?>" placeholder="Todos"></div>
        <div class="filtros-botones"><button type="submit" class="btn btn-success">Filtrar</button> <a href="./auditoria.php" class="btn btn-outline-success">Limpiar</a></div>
    </form>
    <p><small>Últimas 300 acciones: ingresos, intentos fallidos, ventas, movimientos de hacienda, cobros, usuarios y cambios de empresa.</small></p>
    <?php if (!$filas) { ?><p class="sin-datos">Sin actividad registrada.</p><?php } else { ?>
    <table><thead><tr><th>Fecha</th><th>Usuario</th><th>Acción</th><th>Detalle</th><th>IP</th></tr></thead><tbody>
        <?php foreach ($filas as $f) { ?>
        <tr><td class="nowrap"><?php echo e(date('d/m/Y H:i', strtotime($f['fecha']))); ?></td><td><?php echo e($f['usuario']); ?></td><td><?php echo e($f['accion']); ?></td><td><?php echo e($f['detalle']); ?></td><td><?php echo e($f['ip']); ?></td></tr>
        <?php } ?></tbody></table>
    <?php } } ?>
</center>
<?php include('./Template/Pie.php'); ?>
