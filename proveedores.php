<title>GAFA - Proveedores</title>
<?php
include('./Template/Cabecera.php');
include('./Modelo/Conexion.php');
include_once('./Modelo/Auth.php');
?>
<center>
    <h1>PROVEEDORES</h1>
    <a href="./altaProveedor.php"><button class="btn btn-success">Agregar Proveedor</button></a>
    <br/><br/>
    <table style="border:none; width:1200px;box-shadow: 5px 5px 5px 5px rgb(148, 148, 148);border-radius: 5px;background-color: whitesmoke;">
        <thead>
        <tr style="color:green; background-color:white;font-weight:bold;border-bottom: 2px solid green;">
            <th style="padding:5px;">Proveedor</th>
            <th style="padding:5px;text-align:center;">Modificar</th>
        </tr>
        </thead>
        <?php
        $consulta = mysqli_query($conexion, "SELECT id_Proveedor, proveedor FROM proveedores ORDER BY proveedor ASC");
        while ($p = mysqli_fetch_assoc($consulta)) { ?>
        <tr>
            <td style="padding:5px;"><?php echo e($p['proveedor']); ?></td>
            <td class="text-center text-nowrap"><a class="btn btn-sm btn-outline-success" href="./detalleProveedor.php?no=<?php echo (int)$p['id_Proveedor']; ?>">Modificar</a></td>
        </tr>
        <?php } ?>
    </table>
</center>
<?php
$conexion->close();
include('./Template/Pie.php');
?>
