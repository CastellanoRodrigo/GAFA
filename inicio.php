<?php
include('./Template/Cabecera.php');
?>
<section class="portada">
    <div class="portada-contenido">
        <img class="portada-logo" src="imgs/GAFA_LOGO_claro.png" alt="GAFA">
        <h1>Bienvenido/a, <?php echo htmlspecialchars($nombre . ' ' . $apellido, ENT_QUOTES, 'UTF-8'); ?></h1>
        <p>Ganadería y Agricultura de Fácil Administración. Todo el campo de <?php echo e($emp['nombre']); ?>, ordenado en un solo lugar.</p>
    </div>
</section>

<section class="accesos">
    <a class="acceso" href="./todosLosProyectos.php">
        <span class="icono">🌾</span>
        <h3>Proyectos</h3>
        <p>Siembra, hacienda y alquiler por parcela</p>
    </a>
    <a class="acceso" href="./movimientosHacienda.php">
        <span class="icono">🐄</span>
        <h3>Hacienda</h3>
        <p>Stock de cabezas y movimientos</p>
    </a>
    <a class="acceso" href="./verCompras.php">
        <span class="icono">🧾</span>
        <h3>Compras y gastos</h3>
        <p>Facturas y proveedores</p>
    </a>
    <a class="acceso" href="./ventas.php">
        <span class="icono">💰</span>
        <h3>Ventas</h3>
        <p>Ingresos por proyecto</p>
    </a>
    <a class="acceso" href="./resultados.php">
        <span class="icono">📈</span>
        <h3>Resultados</h3>
        <p>Ganancia o pérdida por proyecto</p>
    </a>
    <a class="acceso" href="./avisos.php">
        <span class="icono">🔔</span>
        <h3>Avisos<?php if (!empty($cantAvisos)) { ?> <span class="badge-avisos"><?php echo (int)$cantAvisos; ?></span><?php } ?></h3>
        <p>Vencimientos y pagos por mail y WhatsApp</p>
    </a>
    <a class="acceso" href="./estadisticas.php">
        <span class="icono">📊</span>
        <h3>Estadísticas</h3>
        <p>Inversión, ventas y resultados</p>
    </a>
    <?php if ($esAdminUsr) { ?>
    <a class="acceso" href="./usuarios.php">
        <span class="icono">⚙️</span>
        <h3>Gestión</h3>
        <p>Usuarios, proveedores, empresa y copias</p>
    </a>
    <?php } else { ?>
    <a class="acceso" href="./parcelas.php">
        <span class="icono">🗺️</span>
        <h3>Parcelas</h3>
        <p>Hectáreas en uso y libres</p>
    </a>
    <?php } ?>
</section>
<?php include('./Template/Pie.php'); ?>
