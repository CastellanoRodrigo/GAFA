<?php
include('./Modelo/Conexion.php');
include_once('./Modelo/Auth.php');
requerirLogin();

// Procesar la asignacion ANTES de imprimir nada (para poder redirigir)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requerirEscritura($conexion);
    $idCompra = (int)($_POST['compra'] ?? 0);
    $idProyecto = (int)($_POST['proyecto'] ?? 0);

    // La compra debe existir y seguir siendo General (tipo 1)
    $stmt = $conexion->prepare("SELECT c.id_Compras, cp.id_CompraProyecto, cp.id_TipoCompra
        FROM compras c LEFT JOIN compraproyecto cp ON cp.id_Compras = c.id_Compras
        WHERE c.id_Compras = ?");
    $stmt->bind_param("i", $idCompra);
    $stmt->execute();
    $compra = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    // El proyecto debe existir
    $stmt = $conexion->prepare("SELECT id_Proyecto FROM proyectos WHERE id_Proyecto = ?");
    $stmt->bind_param("i", $idProyecto);
    $stmt->execute();
    $proyecto = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $esGeneral = $compra && (int)($compra['id_TipoCompra'] ?? 1) !== 2;
    if (!$esGeneral || !$proyecto) {
        mysqli_close($conexion);
        header("Location: ./asignarCompra.php?error");
        exit;
    }

    if ($compra['id_CompraProyecto'] !== null) {
        $stmt = $conexion->prepare("UPDATE compraproyecto SET id_Proyecto = ?, id_TipoCompra = 2 WHERE id_Compras = ?");
        $stmt->bind_param("ii", $idProyecto, $idCompra);
    } else {
        $stmt = $conexion->prepare("INSERT INTO compraproyecto VALUES (DEFAULT, ?, ?, 2)");
        $stmt->bind_param("ii", $idCompra, $idProyecto);
    }
    $stmt->execute();
    $stmt->close();
    mysqli_close($conexion);
    header("Location: ./asignarCompra.php?ok");
    exit;
}

include_once('./Modelo/Compras.php');
include_once('./Modelo/Moneda.php');
$sinAsignar = array_values(array_filter(
    consultarCompras($conexion, filtrosCompras([])),
    function ($c) { return (int)$c['id_TipoCompra'] !== 2; }
));
$proyectos = mysqli_fetch_all(mysqli_query($conexion,
    "SELECT p.id_Proyecto, p.nombreProyecto, e.estado
     FROM proyectos p LEFT JOIN estadoproyecto e ON e.id_EstadoProyecto = p.id_EstadoProyecto
     ORDER BY p.nombreProyecto"), MYSQLI_ASSOC);
?>
<title>GAFA - Asignar compras</title>
<?php include('./Template/Cabecera.php'); ?>
<center>
    <h1>ASIGNAR COMPRAS A PROYECTOS</h1>
    <p class="sin-datos" style="padding:0 0 18px;">Compras generales todavía sin proyecto. Elegí el proyecto al que corresponde cada una.</p>

    <?php if (!$sinAsignar) { ?>
    <p class="sin-datos">No hay compras pendientes de asignar.</p>
    <?php } elseif (!$proyectos) { ?>
    <p class="sin-datos">Todavía no hay proyectos cargados. <a href="./altaProyectos.php">Crear un proyecto</a></p>
    <?php } else { ?>
    <table>
        <thead>
        <tr>
            <th>Fecha</th>
            <th class="col-factura">Factura</th>
            <th>Proveedor</th>
            <th style="text-align:right;">Total</th>
            <th>Asignar a proyecto</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($sinAsignar as $c) { ?>
        <tr>
            <td class="nowrap"><?php echo e(date('d/m/Y', strtotime($c['fecha']))); ?></td>
            <td class="col-factura"><?php echo e($c['tipoFactura']); ?> <?php echo e(str_pad((string)$c['puntoDeVenta'], 4, '0', STR_PAD_LEFT)); ?>-<?php echo e(str_pad((string)$c['nroFactura'], 8, '0', STR_PAD_LEFT)); ?></td>
            <td><?php echo e($c['proveedor']); ?></td>
            <td class="nowrap" style="text-align:right;"><b><?php echo e(montoMoneda($c['importeTotal'], $c['moneda'])); ?></b> <?php echo chipMoneda($c['moneda']); ?></td>
            <td>
                <form method="POST" action="./asignarCompra.php" style="display:flex; gap:8px; align-items:center;">
                    <input type="hidden" name="compra" value="<?php echo (int)$c['id_Compras']; ?>">
                    <select name="proyecto" class="sel-proyecto" required>
                        <option value="" selected disabled>-SELECCIONE UNO-</option>
                        <?php foreach ($proyectos as $p) { ?>
                        <option value="<?php echo (int)$p['id_Proyecto']; ?>"><?php echo e($p['nombreProyecto']); ?> (<?php echo e($p['estado']); ?>)</option>
                        <?php } ?>
                    </select>
                    <button type="submit" class="btn btn-success btn-sm">Asignar</button>
                </form>
            </td>
        </tr>
        <?php } ?>
        </tbody>
    </table>
    <?php } ?>
    <div style="margin:18px 0;"><a href="./verCompras.php" class="btn btn-outline-success">Volver a compras</a></div>
</center>
<script>
    const q = window.location.search;
    if (q.includes('ok')) {
        Swal.fire({icon: 'success', title: 'Compra asignada al proyecto'});
    } else if (q.includes('error')) {
        Swal.fire({icon: 'error', title: 'No se pudo asignar', text: 'La compra ya estaba asignada o el proyecto no existe.'});
    }
</script>
<?php
$conexion->close();
include('./Template/Pie.php');
?>
