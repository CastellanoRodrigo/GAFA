<?php
include('./Modelo/Conexion.php'); include_once('./Modelo/Auth.php'); requerirEscritura($conexion);
?>
<title>GAFA - Detalle de alquiler</title>
<?php
include('./Modelo/Conexion.php');
include_once('./Modelo/Auth.php');
include_once('./Modelo/Alquileres.php');
include('./Template/Cabecera.php');

$listas = tablasAlquilerListas($conexion);
$proyectos = $monedas = [];
if ($listas) {
    $proyectos = mysqli_fetch_all(mysqli_query($conexion,
        "SELECT p.id_Proyecto, p.nombreProyecto, p.id_Parcela, p.cantidadHas FROM proyectos p
         WHERE p.id_TipoProyecto = 3 AND p.id_Proyecto NOT IN (SELECT id_Proyecto FROM detallealquiler)
         ORDER BY p.nombreProyecto"), MYSQLI_ASSOC);
    $monedas = mysqli_fetch_all(mysqli_query($conexion, "SELECT id_Moneda, moneda FROM moneda ORDER BY moneda"), MYSQLI_ASSOC);
}
?>
<div class="centrado">
    <div class="centrado--titulo">
        <h1>ASIGNAR CONTRATO DE ALQUILER</h1>
    </div>

    <?php if (!$listas) { ?>
    <div class="centrado--form">
        <p><b>Falta preparar la base de datos.</b> Ejecutá una vez el archivo <code>migracion_alquiler.sql</code> sobre la base
        <code>donjuanbd</code> y volvé a abrir esta página.</p>
    </div>
    <?php } elseif (!$proyectos) { ?>
    <div class="centrado--form">
        <p>No hay proyectos de tipo <b>Alquiler</b> sin contrato. Primero creá uno desde
        <a href="./altaProyectos.php">Alta proyecto</a>, eligiendo el tipo "Alquiler".</p>
        <p style="margin-top:12px;"><a class="btn btn-outline-success" href="./alquileres.php">Ver alquileres</a></p>
    </div>
    <?php } else { ?>
    <div class="centrado--form">
        <form action="./agrDetalleAlquiler.php" method="POST" class="centrado--form--form">
            <div class="labelInput">
                <label for="cmbNombre">Proyecto de alquiler:</label>
                <select name="cmbNombre" id="cmbNombre" required>
                    <option value="" selected disabled>-SELECCIONE UNO-</option>
                    <?php foreach ($proyectos as $p) { ?>
                    <option value="<?php echo (int)$p['id_Proyecto']; ?>"><?php echo e($p['nombreProyecto']); ?> (parcela N° <?php echo (int)$p['id_Parcela']; ?>, <?php echo (int)$p['cantidadHas']; ?> has)</option>
                    <?php } ?>
                </select>
            </div>
            <div class="labelInput">
                <label for="inquilino">Inquilino:</label>
                <input type="text" name="inquilino" id="inquilino" maxlength="100" required>
            </div>
            <div class="labelInput">
                <label for="contacto">Contacto (opcional):</label>
                <input type="text" name="contacto" id="contacto" maxlength="100" placeholder="Teléfono o correo">
            </div>
            <div class="labelInput">
                <label for="email">Mail del inquilino (para avisos):</label>
                <input type="email" name="email" id="email" maxlength="120" placeholder="nombre@correo.com">
            </div>
            <div class="labelInput">
                <label for="telefono">WhatsApp del inquilino:</label>
                <input type="tel" name="telefono" id="telefono" maxlength="30" placeholder="Ej: 3492 15 123456">
            </div>
            <div class="labelInput">
                <label for="fechaInicio">Inicio del contrato:</label>
                <input type="date" name="fechaInicio" id="fechaInicio" required>
            </div>
            <div class="labelInput">
                <label for="fechaFin">Fin del contrato:</label>
                <input type="date" name="fechaFin" id="fechaFin" required onchange="validarFechasAlquiler()">
            </div>
            <div class="labelInput">
                <label for="periodicidad">Cobro:</label>
                <select name="periodicidad" id="periodicidad" required>
                    <?php foreach (array_keys(PERIODICIDADES) as $per) { ?>
                    <option value="<?php echo e($per); ?>" <?php echo $per === 'Anual' ? 'selected' : ''; ?>><?php echo e($per); ?></option>
                    <?php } ?>
                </select>
            </div>
            <div class="labelInput">
                <label for="canon">Canon por período:</label>
                <input type="number" name="canon" id="canon" min="0.01" step="0.01" required>
            </div>
            <div class="labelInput">
                <label for="cmbMoneda">Moneda:</label>
                <select name="cmbMoneda" id="cmbMoneda" required>
                    <option value="" selected disabled>-SELECCIONE UNA-</option>
                    <?php foreach ($monedas as $m) { ?>
                    <option value="<?php echo (int)$m['id_Moneda']; ?>"><?php echo e($m['moneda']); ?></option>
                    <?php } ?>
                </select>
            </div>
            <div class="labelInput">
                <label for="tipoCambio">Tipo de cambio (opcional):</label>
                <input type="number" name="tipoCambio" id="tipoCambio" min="0" step="0.01">
            </div>
            <div class="labelInput">
                <label for="observaciones">Observaciones:</label>
                <textarea name="observaciones" id="observaciones" rows="2" maxlength="255"></textarea>
            </div>
            <p class="ayuda-form">Al guardar se genera el cronograma de cobros: una cuota al inicio de cada período, dentro de las fechas del contrato.</p>
            <div class="labelInputbtn" style="gap:8px;">
                <a href="./alquileres.php" class="btn btn-outline-success">Cancelar</a>
                <input type="submit" value="Guardar contrato" class="btn btn-success">
            </div>
        </form>
    </div>
    <?php } ?>
</div>
<script>
    function validarFechasAlquiler() {
        const ini = document.getElementById('fechaInicio').value;
        const fin = document.getElementById('fechaFin').value;
        if (ini && fin && Date.parse(fin) < Date.parse(ini)) {
            Swal.fire({icon: 'warning', title: 'La fecha de fin debe ser posterior a la de inicio'});
            document.getElementById('fechaFin').value = '';
        }
    }
    const mensajes = {
        tablas: 'Falta ejecutar migracion_alquiler.sql en la base de datos.',
        datos: 'Revisá los datos: inquilino, cobro y moneda son obligatorios.',
        fechas: 'Las fechas no son válidas o el fin es anterior al inicio.',
        canon: 'El canon debe ser mayor a cero.',
        proyecto: 'El proyecto elegido no existe o no es de tipo Alquiler.',
        duplicado: 'Ese proyecto ya tiene un contrato cargado.'
    };
    const err = new URLSearchParams(window.location.search).get('error');
    if (err) {
        Swal.fire({icon: 'error', title: 'No se guardó el contrato', text: mensajes[err] || 'Intentá nuevamente.'});
    }
</script>
<?php
$conexion->close();
include('./Template/Pie.php');
?>
