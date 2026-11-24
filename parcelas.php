<?php
include('./Modelo/Conexion.php');
include_once('./Modelo/Auth.php');
requerirLogin();
$escribe = puedeEscribir($conexion);
// Hectareas ocupadas = proyectos iniciados (estado 2) en cada parcela
$parcelas = mysqli_fetch_all(mysqli_query($conexion,
    "SELECT pa.id_Parcela, pa.dimension,
            COALESCE((SELECT SUM(p.cantidadHas) FROM proyectos p WHERE p.id_Parcela = pa.id_Parcela AND p.id_EstadoProyecto = 2), 0) AS ocupadas,
            (SELECT COUNT(*) FROM proyectos p WHERE p.id_Parcela = pa.id_Parcela) AS proyectos
     FROM parcela pa ORDER BY pa.id_Parcela"), MYSQLI_ASSOC);
$totalHas = array_sum(array_column($parcelas, 'dimension'));
$totalOcup = array_sum(array_column($parcelas, 'ocupadas'));
?>
<title>GAFA - Parcelas</title>
<?php include('./Template/Cabecera.php'); ?>
<center>
    <h1>PARCELAS DEL CAMPO</h1>
    <div class="resumen-alquiler">
        <div class="tarjeta-resumen"><span class="num"><?php echo count($parcelas); ?></span><span>Parcelas</span></div>
        <div class="tarjeta-resumen"><span class="num"><?php echo (int)$totalHas; ?> has</span><span>Superficie total</span></div>
        <div class="tarjeta-resumen aviso"><span class="num"><?php echo (int)$totalOcup; ?> has</span><span>En uso por proyectos iniciados</span></div>
    </div>

    <?php if ($escribe) { ?>
    <form method="POST" action="./agrParcela.php" class="filtros-listado">
        <div><label for="dimension">Nueva parcela: hectáreas</label>
            <input type="number" name="dimension" id="dimension" min="1" max="100000" required></div>
        <div class="filtros-botones"><button type="submit" class="btn btn-success">Agregar parcela</button></div>
    </form>
    <?php } ?>

    <?php if (!$parcelas) { ?>
    <p class="sin-datos">Todavía no hay parcelas. Agregá la primera para poder crear proyectos.</p>
    <?php } else { ?>
    <table>
        <thead><tr><th>Parcela</th><th style="text-align:right;">Hectáreas</th><th style="text-align:right;">En uso</th><th style="text-align:right;">Libres</th><th style="text-align:center;">Proyectos</th><?php echo $escribe ? '<th style="text-align:center;">Modificar</th>' : ''; ?></tr></thead>
        <tbody>
        <?php foreach ($parcelas as $p) { $libres = (int)$p['dimension'] - (int)$p['ocupadas']; ?>
        <tr>
            <td><b>N° <?php echo (int)$p['id_Parcela']; ?></b></td>
            <td style="text-align:right;"><?php echo (int)$p['dimension']; ?></td>
            <td style="text-align:right;"><?php echo (int)$p['ocupadas']; ?></td>
            <td style="text-align:right;"><b class="<?php echo $libres < 0 ? 'texto-mal' : ''; ?>"><?php echo $libres; ?></b></td>
            <td class="text-center"><a href="./todosLosProyectos.php?parcela=<?php echo (int)$p['id_Parcela']; ?>"><?php echo (int)$p['proyectos']; ?></a></td>
            <?php if ($escribe) { ?>
            <td class="text-center">
                <form method="POST" action="./modParcela.php" style="display:inline-flex;gap:6px;align-items:center;">
                    <input type="hidden" name="id" value="<?php echo (int)$p['id_Parcela']; ?>">
                    <input type="number" name="dimension" min="1" max="100000" value="<?php echo (int)$p['dimension']; ?>" style="width:110px !important;" required>
                    <button type="submit" class="btn btn-sm btn-outline-success">Guardar</button>
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
    if (q.get('ok') !== null) Swal.fire({icon: 'success', title: 'Guardado', timer: 1500, showConfirmButton: false});
    else if (q.get('error') !== null) Swal.fire({icon: 'error', title: 'No se guardó', text: 'Ingresá una superficie entre 1 y 100000 hectáreas.'});
</script>
<?php include('./Template/Pie.php'); ?>
