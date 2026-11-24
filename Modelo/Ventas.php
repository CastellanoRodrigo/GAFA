<?php
// Ventas (ingresos) y resultado por proyecto. Todo con consultas preparadas y totales separados por moneda.
require_once __DIR__ . '/Moneda.php';

function ventasListas($conexion): bool
{
    return tablaExiste($conexion, 'ventas') && tablaExiste($conexion, 'moneda');
}

function fechaValidaVentas($f): bool
{
    $d = DateTime::createFromFormat('Y-m-d', (string)$f);
    return $d && $d->format('Y-m-d') === $f;
}

function filtrosVentas(array $src): array
{
    $desde = $src['desde'] ?? '';
    $hasta = $src['hasta'] ?? '';
    return [
        'desde'    => fechaValidaVentas($desde) ? $desde : '',
        'hasta'    => fechaValidaVentas($hasta) ? $hasta : '',
        'proyecto' => (int)($src['proyecto'] ?? 0),
        'q'        => trim((string)($src['q'] ?? '')),
    ];
}

function hayFiltrosVentas(array $f): bool
{
    return $f['desde'] !== '' || $f['hasta'] !== '' || $f['proyecto'] > 0 || $f['q'] !== '';
}

function consultarVentas($conexion, array $f): array
{
    $sql = "SELECT v.id_Venta, v.id_Proyecto, v.fecha, v.concepto, v.cantidad, v.unidad, v.importe, v.comprador,
                   v.nroComprobante, v.observaciones, m.moneda, p.nombreProyecto
            FROM ventas v
            LEFT JOIN moneda m ON m.id_Moneda = v.id_Moneda
            LEFT JOIN proyectos p ON p.id_Proyecto = v.id_Proyecto
            WHERE 1 = 1";
    $tipos = '';
    $params = [];
    if ($f['desde'] !== '')  { $sql .= " AND v.fecha >= ?";      $tipos .= 's'; $params[] = $f['desde']; }
    if ($f['hasta'] !== '')  { $sql .= " AND v.fecha <= ?";      $tipos .= 's'; $params[] = $f['hasta']; }
    if ($f['proyecto'] > 0)  { $sql .= " AND v.id_Proyecto = ?"; $tipos .= 'i'; $params[] = $f['proyecto']; }
    if ($f['q'] !== '') {
        $sql .= " AND (v.concepto LIKE ? OR v.comprador LIKE ? OR v.nroComprobante LIKE ?)";
        $like = '%' . addcslashes($f['q'], '%_\\') . '%';
        $tipos .= 'sss'; $params[] = $like; $params[] = $like; $params[] = $like;
    }
    $sql .= " ORDER BY v.fecha DESC, v.id_Venta DESC";
    $stmt = $conexion->prepare($sql);
    if ($params) $stmt->bind_param($tipos, ...$params);
    $stmt->execute();
    $filas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $filas;
}

// Resultado por proyecto y moneda: ingresos (ventas + cobros de alquiler) menos gastos (compras asignadas).
// Devuelve [id_Proyecto => ['ARS' => ['ventas'=>, 'alquiler'=>, 'gastos'=>, 'ingresos'=>, 'resultado'=>], 'USD' => [...]]].
// $idProyecto > 0 limita a un proyecto. Nunca mezcla monedas.
function resultadosPorProyecto($conexion, int $idProyecto = 0): array
{
    $res = [];
    $sumar = function (array $filas, string $campo) use (&$res) {
        foreach ($filas as $r) {
            $p = (int)$r['id_Proyecto'];
            $m = strtoupper(trim((string)$r['moneda'])) ?: '-';
            if (!isset($res[$p][$m])) $res[$p][$m] = ['ventas' => 0.0, 'alquiler' => 0.0, 'gastos' => 0.0];
            $res[$p][$m][$campo] += (float)$r['total'];
        }
    };
    $consulta = function (string $sql, string $col) use ($conexion, $idProyecto) {
        $sql = str_replace('{F}', $idProyecto > 0 ? " AND $col = ?" : '', $sql);
        $stmt = $conexion->prepare($sql);
        if ($idProyecto > 0) $stmt->bind_param('i', $idProyecto);
        $stmt->execute();
        $f = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $f;
    };

    if (ventasListas($conexion)) {
        $sumar($consulta("SELECT v.id_Proyecto, m.moneda, SUM(v.importe) AS total FROM ventas v
            LEFT JOIN moneda m ON m.id_Moneda = v.id_Moneda WHERE 1 = 1 {F} GROUP BY v.id_Proyecto, m.moneda", 'v.id_Proyecto'), 'ventas');
    }
    if (tablaExiste($conexion, 'detallealquiler') && tablaExiste($conexion, 'cobrosalquiler') && tablaExiste($conexion, 'moneda')) {
        $sumar($consulta("SELECT da.id_Proyecto, m.moneda, SUM(c.monto) AS total FROM cobrosalquiler c
            JOIN detallealquiler da ON da.id_DetalleAlquiler = c.id_DetalleAlquiler
            LEFT JOIN moneda m ON m.id_Moneda = da.id_Moneda
            WHERE c.fechaPago IS NOT NULL {F} GROUP BY da.id_Proyecto, m.moneda", 'da.id_Proyecto'), 'alquiler');
    }
    if (tablaExiste($conexion, 'compraproyecto') && tablaExiste($conexion, 'moneda') && columnaExiste($conexion, 'compras', 'id_Moneda')) {
        $sumar($consulta("SELECT cp.id_Proyecto, m.moneda, SUM(c.importeTotal) AS total FROM compraproyecto cp
            JOIN compras c ON c.id_Compras = cp.id_Compras
            LEFT JOIN moneda m ON m.id_Moneda = c.id_Moneda
            WHERE cp.id_TipoCompra = 2 {F} GROUP BY cp.id_Proyecto, m.moneda", 'cp.id_Proyecto'), 'gastos');
    }
    foreach ($res as $p => $monedas) {
        foreach ($monedas as $m => $v) {
            $res[$p][$m]['ingresos'] = $v['ventas'] + $v['alquiler'];
            $res[$p][$m]['resultado'] = $res[$p][$m]['ingresos'] - $v['gastos'];
        }
        uksort($res[$p], function ($a, $b) {
            $o = ['ARS' => 0, 'USD' => 1];
            return ($o[$a] ?? 2) <=> ($o[$b] ?? 2);
        });
    }
    return $res;
}

function filtrosResultados(array $src): array
{
    return ['tipo' => (int)($src['tipo'] ?? 0), 'estado' => (int)($src['estado'] ?? 0), 'q' => trim((string)($src['q'] ?? ''))];
}

// Filas (proyecto x moneda) con los datos del proyecto, para listar y exportar.
function filasResultados($conexion, array $f): array
{
    $sql = "SELECT p.id_Proyecto, p.nombreProyecto, p.cantidadHas, t.tipoProyecto, e.estado
            FROM proyectos p
            LEFT JOIN tipoproyecto t ON t.id_tipoProyecto = p.id_TipoProyecto
            LEFT JOIN estadoproyecto e ON e.id_EstadoProyecto = p.id_EstadoProyecto
            WHERE 1 = 1";
    $tipos = ''; $params = [];
    if ($f['tipo'] > 0)   { $sql .= " AND p.id_TipoProyecto = ?";   $tipos .= 'i'; $params[] = $f['tipo']; }
    if ($f['estado'] > 0) { $sql .= " AND p.id_EstadoProyecto = ?"; $tipos .= 'i'; $params[] = $f['estado']; }
    if ($f['q'] !== '')   { $sql .= " AND p.nombreProyecto LIKE ?"; $tipos .= 's'; $params[] = '%' . addcslashes($f['q'], '%_\\') . '%'; }
    $sql .= " ORDER BY p.nombreProyecto";
    $stmt = $conexion->prepare($sql);
    if ($params) $stmt->bind_param($tipos, ...$params);
    $stmt->execute();
    $proyectos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $res = resultadosPorProyecto($conexion);
    $filas = [];
    foreach ($proyectos as $p) {
        foreach ($res[(int)$p['id_Proyecto']] ?? [] as $mon => $v) {
            $filas[] = $p + ['moneda' => $mon] + $v;
        }
    }
    return $filas;
}
