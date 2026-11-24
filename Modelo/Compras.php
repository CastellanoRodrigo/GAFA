<?php
// Consulta compartida del listado de compras y de sus exportaciones a Excel.
// Los filtros llegan como valores simples y se aplican con parametros: nunca se recibe SQL del cliente.

function fechaValidaCompras($f): bool
{
    $d = DateTime::createFromFormat('Y-m-d', (string)$f);
    return $d && $d->format('Y-m-d') === $f;
}

function filtrosCompras(array $src): array
{
    $desde = $src['desde'] ?? '';
    $hasta = $src['hasta'] ?? '';
    return [
        'desde'     => fechaValidaCompras($desde) ? $desde : '',
        'hasta'     => fechaValidaCompras($hasta) ? $hasta : '',
        'proveedor' => (int)($src['proveedor'] ?? 0),
        'q'         => trim((string)($src['q'] ?? '')),
    ];
}

function hayFiltrosCompras(array $f): bool
{
    return $f['desde'] !== '' || $f['hasta'] !== '' || $f['proveedor'] > 0 || $f['q'] !== '';
}

function consultarCompras($conexion, array $f): array
{
    $sql = "SELECT c.id_Compras, c.fecha, c.tipoFactura, c.puntoDeVenta, c.nroFactura, c.detalle,
                   p.proveedor, m.moneda, c.importeNeto, c.IVA, c.importeTotal, f.formaPago,
                   cp.id_TipoCompra, pr.nombreProyecto
            FROM compras c
            LEFT JOIN proveedores p ON p.id_Proveedor = c.id_Proveedor
            LEFT JOIN moneda m ON m.id_Moneda = c.id_Moneda
            LEFT JOIN formapago f ON f.id_Forma = c.id_Forma
            LEFT JOIN compraproyecto cp ON cp.id_Compras = c.id_Compras
            LEFT JOIN proyectos pr ON pr.id_Proyecto = cp.id_Proyecto AND cp.id_TipoCompra = 2
            WHERE 1 = 1";
    $tipos = '';
    $params = [];
    if ($f['desde'] !== '') { $sql .= " AND c.fecha >= ?"; $tipos .= 's'; $params[] = $f['desde']; }
    if ($f['hasta'] !== '') { $sql .= " AND c.fecha <= ?"; $tipos .= 's'; $params[] = $f['hasta']; }
    if ($f['proveedor'] > 0) { $sql .= " AND c.id_Proveedor = ?"; $tipos .= 'i'; $params[] = $f['proveedor']; }
    if ($f['q'] !== '') {
        $sql .= " AND (CAST(c.nroFactura AS CHAR) LIKE ? OR c.detalle LIKE ?)";
        $like = '%' . addcslashes($f['q'], '%_\\') . '%';
        $tipos .= 'ss'; $params[] = $like; $params[] = $like;
    }
    $sql .= " ORDER BY c.fecha DESC, c.id_Compras DESC";

    $stmt = $conexion->prepare($sql);
    if ($params) {
        $stmt->bind_param($tipos, ...$params);
    }
    $stmt->execute();
    $filas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $filas;
}
