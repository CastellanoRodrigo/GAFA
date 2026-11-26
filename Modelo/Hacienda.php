<?php
// Movimientos de hacienda y stock de cabezas por proyecto.
require_once __DIR__ . '/Moneda.php';

const MOVIMIENTOS_HACIENDA = ['Compra' => 1, 'Nacimiento' => 1, 'Venta' => -1, 'Muerte' => -1];

function haciendaListas($conexion): bool
{
    return tablaExiste($conexion, 'movimientoshacienda');
}

function esProyectoHacienda($conexion, int $id): bool
{
    $s = $conexion->prepare("SELECT 1 FROM proyectos WHERE id_Proyecto = ? AND id_TipoProyecto = 1");
    $s->bind_param('i', $id);
    $s->execute();
    $ok = (bool)$s->get_result()->fetch_row();
    $s->close();
    return $ok;
}

// ['inicial'=>, 'entradas'=>, 'salidas'=>, 'actual'=>]
function stockHacienda($conexion, int $id): array
{
    $s = $conexion->prepare("SELECT COALESCE(SUM(cantidadCabezas), 0) FROM detallehacienda WHERE id_Proyecto = ?");
    $s->bind_param('i', $id);
    $s->execute();
    $ini = (int)$s->get_result()->fetch_row()[0];
    $s->close();
    $ent = $sal = 0;
    if (haciendaListas($conexion)) {
        $s = $conexion->prepare("SELECT tipo, SUM(cantidad) FROM movimientoshacienda WHERE id_Proyecto = ? GROUP BY tipo");
        $s->bind_param('i', $id);
        $s->execute();
        foreach ($s->get_result()->fetch_all() as [$t, $c]) {
            if ((MOVIMIENTOS_HACIENDA[$t] ?? 0) > 0) $ent += (int)$c; else $sal += (int)$c;
        }
        $s->close();
    }
    return ['inicial' => $ini, 'entradas' => $ent, 'salidas' => $sal, 'actual' => $ini + $ent - $sal];
}

function movimientosHacienda($conexion, int $id): array
{
    $s = $conexion->prepare("SELECT m.*, v.importe, mo.moneda FROM movimientoshacienda m
        LEFT JOIN ventas v ON v.id_Venta = m.id_Venta LEFT JOIN moneda mo ON mo.id_Moneda = v.id_Moneda
        WHERE m.id_Proyecto = ? ORDER BY m.fecha DESC, m.id_Movimiento DESC");
    $s->bind_param('i', $id);
    $s->execute();
    $f = $s->get_result()->fetch_all(MYSQLI_ASSOC);
    $s->close();
    return $f;
}

// Proyectos de hacienda con su stock, para el listado general.
function proyectosConStock($conexion): array
{
    $ps = mysqli_fetch_all(mysqli_query($conexion, "SELECT p.id_Proyecto, p.nombreProyecto, e.estado FROM proyectos p
        LEFT JOIN estadoproyecto e ON e.id_EstadoProyecto = p.id_EstadoProyecto WHERE p.id_TipoProyecto = 1 ORDER BY p.nombreProyecto"), MYSQLI_ASSOC);
    foreach ($ps as &$p) $p += stockHacienda($conexion, (int)$p['id_Proyecto']);
    return $ps;
}
