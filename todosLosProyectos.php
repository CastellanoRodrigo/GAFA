<title>Don Juan S.R.L - Proyectos</title>
<?php
include('./Template/Cabecera.php');
include('./Modelo/Conexion.php');
include('./Modelo/Auth.php');
include('./Modelo/Proyectos.php');

$f = filtrosProyectos($_GET);
$proyectos = consultarProyectos($conexion, $f);
$tiposProy = mysqli_fetch_all(mysqli_query($conexion, "SELECT id_tipoProyecto, tipoProyecto FROM tipoproyecto ORDER BY tipoProyecto"), MYSQLI_ASSOC);
$estadosProy = mysqli_fetch_all(mysqli_query($conexion, "SELECT id_EstadoProyecto, estado FROM estadoproyecto ORDER BY id_EstadoProyecto"), MYSQLI_ASSOC);
$parcelas = mysqli_fetch_all(mysqli_query($conexion, "SELECT id_Parcela FROM parcela ORDER BY id_Parcela"), MYSQLI_ASSOC);

$totalHas = array_sum(array_map(function ($p) { return (int)$p['cantidadHas']; }, $proyectos));
$qs = http_build_query(array_filter($f, function ($v) { return $v !== '' && $v !== 0; }));
?>
<center>
    <h1>LISTADO DE PROYECTOS</h1>

    <form method="GET" action="./todosLosProyectos.php" class="filtros-listado">
        <div>
            <label for="tipo">Tipo</label>
            <select name="tipo" id="tipo">
                <option value="0">Todos</option>
                <?php foreach ($tiposProy as $t) { ?>
                <option value="<?php echo (int)$t['id_tipoProyecto']; ?>" <?php echo $f['tipo'] === (int)$t['id_tipoProyecto'] ? 'selected' : ''; ?>><?php echo e($t['tipoProyecto']); ?></option>
                <?php } ?>
            </select>
        </div>
        <div>
            <label for="estado">Estado</label>
            <select name="estado" id="estado">
                <option value="0">Todos</option>
                <?php foreach ($estadosProy as $s) { ?>
                <option value="<?php echo (int)$s['id_EstadoProyecto']; ?>" <?php echo $f['estado'] === (int)$s['id_EstadoProyecto'] ? 'selected' : ''; ?>><?php echo e($s['estado']); ?></option>
                <?php } ?>
            </select>
        </div>
        <div>
            <label for="parcela">Parcela</label>
            <select name="parcela" id="parcela">
                <option value="0">Todas</option>
                <?php foreach ($parcelas as $pa) { ?>
                <option value="<?php echo (int)$pa['id_Parcela']; ?>" <?php echo $f['parcela'] === (int)$pa['id_Parcela'] ? 'selected' : ''; ?>>N° <?php echo (int)$pa['id_Parcela']; ?></option>
                <?php } ?>
            </select>
        </div>
        <div>
            <label for="q">Nombre del proyecto</label>
            <input type="text" name="q" id="q" class="campo-ancho" value="<?php echo e($f['q']); ?>" placeholder="Buscar...">
        </div>
        <div class="filtros-botones">
            <button type="submit" class="btn btn-success">Filtrar</button>
            <a href="./todosLosProyectos.php" class="btn btn-outline-success">Limpiar</a>
        </div>
    </form>

    <div class="barra-listado">
        <span><b><?php echo count($proyectos); ?></b> proyecto<?php echo count($proyectos) === 1 ? '' : 's'; ?></span>
        <span class="chip-total"><b><?php echo $totalHas; ?></b> has</span>
        <span class="barra-acciones">
            <a href="./altaProyectos.php" class="btn btn-success">Agregar proyecto</a>
            <a href="./Exportar/ExcelProyectos.php<?php echo $qs ? '?' . e($qs) : ''; ?>" class="btn btn-outline-success">Exportar a Excel</a>
        </span>
    </div>

    <?php if (!$proyectos) { ?>
    <p class="sin-datos">No hay proyectos para mostrar<?php echo hayFiltrosProyectos($f) ? ' con esos filtros' : ''; ?>.</p>
    <?php } else { ?>
    <table>
        <thead>
        <tr>
            <th>Proyecto</th>
            <th>Parcela</th>
            <th>Tipo</th>
            <th style="text-align:right;">Has</th>
            <th>Estado</th>
            <th style="text-align:center;">Detalle</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($proyectos as $p) { ?>
        <tr>
            <td><?php echo e($p['nombreProyecto']); ?></td>
            <td>N° <?php echo (int)$p['id_Parcela']; ?></td>
            <td><?php echo e($p['tipoProyecto']); ?></td>
            <td style="text-align:right;"><?php echo (int)$p['cantidadHas']; ?></td>
            <td><?php echo e($p['estado']); ?></td>
            <td class="text-center text-nowrap"><a class="btn btn-sm btn-outline-success" href="./detalleProyecto.php?no=<?php echo (int)$p['id_Proyecto']; ?>">Ver / modificar</a></td>
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
