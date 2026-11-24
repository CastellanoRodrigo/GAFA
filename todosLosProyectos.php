<title>Don Juan S.R.L - Proyectos</title>
<?php
include('./Template/Cabecera.php');
include('./Modelo/Conexion.php');
include('./Modelo/Auth.php');
?>
<center>
    <h1>LISTADO DE PROYECTOS</h1>
    <a href="./altaProyectos.php"><button class="btn btn-success">Agregar Proyecto</button></a>
    <br/><br/>
    <table style="border:none; width:1200px;box-shadow: 5px 5px 5px 5px rgb(148, 148, 148);border-radius: 5px;background-color: whitesmoke;">
        <thead>
        <tr style="color:green; background-color:white;font-weight:bold;border-bottom: 2px solid green;">
            <th style="padding:5px;">Proyecto</th>
            <th style="padding:5px;">Parcela</th>
            <th style="padding:5px;">Tipo</th>
            <th style="padding:5px;">Has</th>
            <th style="padding:5px;">Estado</th>
            <th style="padding:5px;text-align:center;">Detalle</th>
        </tr>
        </thead>
        <?php
        $consulta = mysqli_query($conexion, "SELECT p.id_Proyecto, p.nombreProyecto, p.id_Parcela, t.tipoProyecto, p.cantidadHas, e.estado
            FROM proyectos p
            LEFT JOIN tipoproyecto t ON t.id_tipoProyecto = p.id_TipoProyecto
            LEFT JOIN estadoproyecto e ON e.id_EstadoProyecto = p.id_EstadoProyecto
            ORDER BY p.nombreProyecto");
        while ($p = mysqli_fetch_assoc($consulta)) { ?>
        <tr>
            <td style="padding:5px;"><?php echo e($p['nombreProyecto']); ?></td>
            <td style="padding:5px;"><?php echo (int)$p['id_Parcela']; ?></td>
            <td style="padding:5px;"><?php echo e($p['tipoProyecto']); ?></td>
            <td style="padding:5px;"><?php echo (int)$p['cantidadHas']; ?></td>
            <td style="padding:5px;"><?php echo e($p['estado']); ?></td>
            <td class="text-center text-nowrap"><a class="btn btn-sm btn-outline-success" href="./detalleProyecto.php?no=<?php echo (int)$p['id_Proyecto']; ?>">Ver / modificar</a></td>
        </tr>
        <?php } ?>
    </table>
</center>
<?php
$conexion->close();
include('./Template/Pie.php');
?>
