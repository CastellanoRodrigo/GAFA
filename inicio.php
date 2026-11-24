<title>Don Juan S.R.L</title>
<?php
include('./Template/Cabecera.php');
?>
<center>
    <h1>Bienvenido/a, <?php echo htmlspecialchars($nombre . ' ' . $apellido, ENT_QUOTES, 'UTF-8'); ?></h1>
    <p>GAFA - Ganadería y Agricultura de Fácil Administración</p>
    <br>
    <div>
        <a href="./todosLosProyectos.php" class="btn btn-success">Proyectos</a>
        <a href="./verCompras.php" class="btn btn-success">Compras</a>
        <a href="./estadisticas.php" class="btn btn-success">Estadísticas</a>
    </div>
</center>
<?php include('./Template/Pie.php'); ?>
