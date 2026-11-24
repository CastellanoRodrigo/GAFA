<?php
include('./Modelo/Conexion.php');
include_once('./Modelo/Auth.php');
include_once('./Modelo/Empresa.php');
requerirAdmin($conexion);
$listo = configuracionLista($conexion);
$emp = empresa($conexion, true);
$logo = logoEmpresa($emp);
?>
<title>GAFA - Datos de la empresa</title>
<?php include('./Template/Cabecera.php'); ?>
<div class="centrado">
    <div class="centrado--titulo"><h1>DATOS DE LA EMPRESA</h1></div>
    <?php if (!$listo) { ?>
    <div class="centrado--form">
        <p><b>Falta preparar la base de datos.</b> Ejecutá una vez <code>migracion_roles_empresa.sql</code> y recargá esta página.</p>
    </div>
    <?php } else { ?>
    <div class="centrado--form">
        <form action="./guardarEmpresa.php" method="POST" enctype="multipart/form-data" class="centrado--form--form">
            <div class="labelInput"><label for="nombre">Nombre de la empresa:</label>
                <input type="text" name="nombre" id="nombre" maxlength="100" value="<?php echo e($emp['nombre']); ?>" required></div>
            <div class="labelInput"><label for="lema">Lema (opcional):</label>
                <input type="text" name="lema" id="lema" maxlength="120" value="<?php echo e($emp['lema']); ?>"></div>
            <div class="labelInput"><label for="cuit">CUIT:</label>
                <input type="text" name="cuit" id="cuit" maxlength="20" value="<?php echo e($emp['cuit']); ?>"></div>
            <div class="labelInput"><label for="email">Mail de contacto:</label>
                <input type="email" name="email" id="email" maxlength="120" value="<?php echo e($emp['email']); ?>"></div>
            <div class="labelInput"><label for="telefono">Teléfono:</label>
                <input type="text" name="telefono" id="telefono" maxlength="40" value="<?php echo e($emp['telefono']); ?>"></div>
            <div class="labelInput"><label for="direccion">Dirección:</label>
                <input type="text" name="direccion" id="direccion" maxlength="150" value="<?php echo e($emp['direccion']); ?>"></div>
            <div class="labelInput"><label for="color">Color principal:</label>
                <input type="color" name="color" id="color" value="<?php echo e($emp['color']); ?>" style="width:90px;height:40px;padding:2px;"></div>
            <div class="labelInput"><label for="logo">Logo de la empresa (PNG, JPG o WEBP, hasta 1 MB):</label>
                <input type="file" name="logo" id="logo" accept="image/png,image/jpeg,image/webp"></div>
            <?php if ($logo) { ?>
            <div class="labelInput"><label>Logo actual:</label>
                <span><img src="<?php echo e($logo); ?>?v=<?php echo filemtime(__DIR__ . '/' . $logo); ?>" alt="Logo" style="max-height:60px;">
                <label style="font-weight:400;margin-left:12px;"><input type="checkbox" name="quitar_logo" value="1"> Quitar</label></span></div>
            <?php } ?>
            <p class="ayuda-form">El nombre y el logo aparecen en la cabecera, los mails de aviso y las exportaciones. El logo de GAFA se mantiene como marca del sistema.</p>
            <div class="labelInputbtn" style="gap:8px;">
                <a href="./inicio.php" class="btn btn-outline-success">Cancelar</a>
                <input type="submit" value="Guardar" class="btn btn-success">
            </div>
        </form>
    </div>
    <?php } ?>
</div>
<script>
    const q = new URLSearchParams(window.location.search);
    const msg = {nombre: 'El nombre de la empresa es obligatorio.', color: 'El color no es válido.', logo: 'El logo debe ser una imagen PNG, JPG o WEBP de hasta 1 MB.',
                 email: 'El mail de contacto no es válido.', tablas: 'Falta ejecutar migracion_roles_empresa.sql.', carpeta: 'No se pudo guardar el logo en el servidor.'};
    if (q.get('ok') !== null) Swal.fire({icon: 'success', title: 'Datos guardados', timer: 1800, showConfirmButton: false});
    else if (q.get('error')) Swal.fire({icon: 'error', title: 'No se guardó', text: msg[q.get('error')] || 'Revisá los datos.'});
</script>
<?php include('./Template/Pie.php'); ?>
