<?php
include('./Template/Cabecera.php');
?>
<section class="portada">
    <div class="portada-contenido">
        <img class="portada-logo" src="imgs/GAFA_LOGO_claro.png" alt="GAFA">
        <h1>Bienvenido/a, <?php echo htmlspecialchars($nombre . ' ' . $apellido, ENT_QUOTES, 'UTF-8'); ?></h1>
        <p>Ganadería y Agricultura de Fácil Administración. Todo el campo de Don Juan, ordenado en un solo lugar.</p>
    </div>
</section>

<section class="accesos">
    <a class="acceso" href="./todosLosProyectos.php">
        <span class="icono">🌾</span>
        <h3>Proyectos</h3>
        <p>Siembra y hacienda por parcela</p>
    </a>
    <a class="acceso" href="./verCompras.php">
        <span class="icono">🧾</span>
        <h3>Compras y gastos</h3>
        <p>Facturas y proveedores</p>
    </a>
    <a class="acceso" href="./estadisticas.php">
        <span class="icono">📊</span>
        <h3>Estadísticas</h3>
        <p>Inversión y resultados</p>
    </a>
    <a class="acceso" href="./proveedores.php">
        <span class="icono">🤝</span>
        <h3>Gestión</h3>
        <p>Usuarios, proveedores y categorías</p>
    </a>
</section>
<?php include('./Template/Pie.php'); ?>
