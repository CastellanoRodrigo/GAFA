<?php
// Copias de seguridad de la base de datos en formato SQL (se restauran importando el archivo).

function carpetaRespaldos(): string
{
    return __DIR__ . '/../storage/backups';
}

function tablasBase($conexion): array
{
    $res = $conexion->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'");
    return array_map(function ($f) { return $f[0]; }, $res->fetch_all(MYSQLI_NUM));
}

function valorSql($conexion, $v): string
{
    return $v === null ? 'NULL' : "'" . $conexion->real_escape_string((string)$v) . "'";
}

// Genera el SQL completo. $tablasConDatos = null -> todas las tablas con sus datos;
// una lista -> estructura de todas y datos solo de esas (se usa para el esquema de instalacion).
function volcarSql($conexion, ?array $tablasConDatos = null, string $titulo = 'Copia de seguridad GAFA'): string
{
    $sql = "-- $titulo\n-- Generada el " . date('Y-m-d H:i:s') . "\n"
        . "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\nSET SQL_MODE='NO_AUTO_VALUE_ON_ZERO';\n\n";
    foreach (tablasBase($conexion) as $tabla) {
        $crear = $conexion->query("SHOW CREATE TABLE `$tabla`")->fetch_row()[1];
        $sql .= "DROP TABLE IF EXISTS `$tabla`;\n$crear;\n\n";
        if ($tablasConDatos !== null && !in_array($tabla, $tablasConDatos, true)) continue;
        $res = $conexion->query("SELECT * FROM `$tabla`");
        $cols = array_map(function ($c) { return '`' . $c->name . '`'; }, $res->fetch_fields());
        $lote = [];
        while ($fila = $res->fetch_row()) {
            $lote[] = '(' . implode(', ', array_map(function ($v) use ($conexion) { return valorSql($conexion, $v); }, $fila)) . ')';
            if (count($lote) >= 100) {
                $sql .= "INSERT INTO `$tabla` (" . implode(', ', $cols) . ") VALUES\n" . implode(",\n", $lote) . ";\n";
                $lote = [];
            }
        }
        if ($lote) {
            $sql .= "INSERT INTO `$tabla` (" . implode(', ', $cols) . ") VALUES\n" . implode(",\n", $lote) . ";\n";
        }
        $sql .= "\n";
    }
    return $sql . "SET FOREIGN_KEY_CHECKS=1;\n";
}

function listarRespaldos(): array
{
    $dir = carpetaRespaldos();
    $r = [];
    foreach (glob($dir . '/gafa-*.sql') ?: [] as $f) {
        $r[] = ['archivo' => basename($f), 'tamano' => filesize($f), 'fecha' => filemtime($f)];
    }
    usort($r, function ($a, $b) { return $b['fecha'] <=> $a['fecha'] ?: strcmp($b['archivo'], $a['archivo']); });
    return $r;
}

// Guarda una copia en el servidor y conserva solo las ultimas $conservar
function guardarRespaldo($conexion, int $conservar = 14): string
{
    $dir = carpetaRespaldos();
    if (!is_dir($dir) && !@mkdir($dir, 0775, true)) {
        throw new RuntimeException('No se pudo crear la carpeta ' . $dir);
    }
    $ruta = $dir . '/gafa-' . date('Ymd-His') . '-' . bin2hex(random_bytes(2)) . '.sql';
    if (file_put_contents($ruta, volcarSql($conexion)) === false) {
        throw new RuntimeException('No se pudo escribir ' . $ruta);
    }
    foreach (array_slice(listarRespaldos(), max(1, $conservar)) as $viejo) {
        @unlink($dir . '/' . $viejo['archivo']);
    }
    return $ruta;
}

function nombreRespaldoValido(string $n): bool
{
    return (bool)preg_match('/^gafa-[0-9]{8}-[0-9]{6}(-[0-9a-f]{4})?\.sql$/', $n);
}
