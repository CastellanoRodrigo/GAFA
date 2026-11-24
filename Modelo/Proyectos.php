<?php
// Consulta compartida del listado de proyectos y de sus exportaciones a Excel.
// Los filtros llegan como valores simples y se aplican con parametros: nunca se recibe SQL del cliente.

function filtrosProyectos(array $src): array
{
    return [
        'tipo'    => (int)($src['tipo'] ?? 0),
        'estado'  => (int)($src['estado'] ?? 0),
        'parcela' => (int)($src['parcela'] ?? 0),
        'q'       => trim((string)($src['q'] ?? '')),
    ];
}

function hayFiltrosProyectos(array $f): bool
{
    return $f['tipo'] > 0 || $f['estado'] > 0 || $f['parcela'] > 0 || $f['q'] !== '';
}

function consultarProyectos($conexion, array $f): array
{
    $sql = "SELECT p.id_Proyecto, p.nombreProyecto, p.id_Parcela, t.tipoProyecto, p.cantidadHas, e.estado,
                   COALESCE(dh.fechaInicio, ds.fechaInicio) AS fechaInicio,
                   COALESCE(dh.fechaCierre, ds.fechaCierre) AS fechaCierre,
                   dh.cantidadCabezas, c.nombreCategoria, cu.nombreCultivo,
                   COALESCE(dh.inversion, ds.inversion) AS inversion
            FROM proyectos p
            LEFT JOIN estadoproyecto e ON e.id_EstadoProyecto = p.id_EstadoProyecto
            LEFT JOIN tipoproyecto t ON t.id_tipoProyecto = p.id_TipoProyecto
            LEFT JOIN detallehacienda dh ON dh.id_Proyecto = p.id_Proyecto
            LEFT JOIN categoria c ON c.id_Categoria = dh.id_Categoria
            LEFT JOIN detallesiembra ds ON ds.id_Proyecto = p.id_Proyecto
            LEFT JOIN cultivos cu ON cu.id_Cultivo = ds.id_Cultivo
            WHERE 1 = 1";
    $tipos = '';
    $params = [];
    if ($f['tipo'] > 0)    { $sql .= " AND p.id_TipoProyecto = ?";   $tipos .= 'i'; $params[] = $f['tipo']; }
    if ($f['estado'] > 0)  { $sql .= " AND p.id_EstadoProyecto = ?"; $tipos .= 'i'; $params[] = $f['estado']; }
    if ($f['parcela'] > 0) { $sql .= " AND p.id_Parcela = ?";        $tipos .= 'i'; $params[] = $f['parcela']; }
    if ($f['q'] !== '') {
        $sql .= " AND p.nombreProyecto LIKE ?";
        $tipos .= 's';
        $params[] = '%' . addcslashes($f['q'], '%_\\') . '%';
    }
    $sql .= " ORDER BY p.nombreProyecto, p.id_Proyecto";

    $stmt = $conexion->prepare($sql);
    if ($params) {
        $stmt->bind_param($tipos, ...$params);
    }
    $stmt->execute();
    $filas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $filas;
}
