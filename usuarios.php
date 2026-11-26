<?php
include('./Modelo/Conexion.php'); include_once('./Modelo/Auth.php'); requerirAdmin($conexion);
?>
<title>GAFA - Usuarios</title>
<?php
include('./Template/Cabecera.php');
include('./Modelo/Conexion.php');
include_once('./Modelo/Auth.php');
include_once('./Modelo/Moneda.php');
$mailCol = columnaExiste($conexion, 'usuarios', 'email');
?>
<center>
    <h1>USUARIOS</h1>
    <a href="./altaUsuario.php"><button class="btn btn-success">Agregar Usuario</button></a>
    <br/><br/>
    <table style="border:none; width:1200px;box-shadow: 5px 5px 5px 5px rgb(148, 148, 148);border-radius: 5px;background-color: whitesmoke;">
        <thead>
        <tr style="color:green; background-color:white;font-weight:bold;border-bottom: 2px solid green;">
            <th style="padding:5px;">Usuario</th>
            <th style="padding:5px;">Nombre</th>
            <th style="padding:5px;">Apellido</th>
            <th style="padding:5px;">Mail</th>
            <th style="padding:5px;">Tipo</th>
            <th style="padding:5px;">Estado</th>
            <th style="padding:5px;text-align:center;">Modificar</th>
        </tr>
        </thead>
        <?php
        $consulta = mysqli_query($conexion, "SELECT u.id_usuario, u.usuario, u.nombre, u.apellido, " . ($mailCol ? "u.email, " : "") . "t.tipo, e.estadoUsuario
            FROM usuarios u
            LEFT JOIN tipo_usuario t ON t.id_tipoUsuario = u.id_tipoUsuario
            LEFT JOIN estadousuario e ON e.id_EstadoUsuario = u.id_EstadoUsuario
            ORDER BY u.usuario ASC");
        while ($u = mysqli_fetch_assoc($consulta)) { ?>
        <tr>
            <td style="padding:5px;"><?php echo e($u['usuario']); ?></td>
            <td style="padding:5px;"><?php echo e(nombrePropio($u['nombre'])); ?></td>
            <td style="padding:5px;"><?php echo e(nombrePropio($u['apellido'])); ?></td>
            <td style="padding:5px;"><?php echo $mailCol ? ($u['email'] ? e($u['email']) : '<span class="texto-mal">Sin mail</span>') : '-'; ?></td>
            <td style="padding:5px;"><?php echo e($u['tipo']); ?></td>
            <td style="padding:5px;"><?php echo e($u['estadoUsuario']); ?></td>
            <td class="text-center text-nowrap"><a class="btn btn-sm btn-outline-success" href="./detalleUsuario.php?no=<?php echo (int)$u['id_usuario']; ?>">Modificar</a></td>
        </tr>
        <?php } ?>
    </table>
</center>
<?php
$conexion->close();
include('./Template/Pie.php');
?>
