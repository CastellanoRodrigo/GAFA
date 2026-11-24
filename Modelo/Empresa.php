<?php
// Datos de la empresa (marca blanca): nombre, logo, color, contacto. Se guardan en la tabla `configuracion`.
require_once __DIR__ . '/Moneda.php';

const EMPRESA_COLOR_BASE = '#2f5d34';
const GAFA_VERSION = '1.0.0';

function empresaPorDefecto(): array
{
    return [
        'nombre' => 'Don Juan S.R.L',
        'lema' => 'Ganadería y Agricultura de Fácil Administración',
        'cuit' => '', 'email' => '', 'telefono' => '', 'direccion' => '',
        'color' => EMPRESA_COLOR_BASE,
        'logo' => '',
    ];
}

function configuracionLista($conexion): bool
{
    return tablaExiste($conexion, 'configuracion');
}

function empresa($conexion = null, bool $recargar = false): array
{
    static $cache = null;
    if ($recargar) $cache = null;
    if ($cache !== null) return $cache;
    $datos = empresaPorDefecto();
    $conexion = $conexion ?? ($GLOBALS['conexion'] ?? null);
    if ($conexion && configuracionLista($conexion)) {
        $res = $conexion->query("SELECT clave, valor FROM configuracion");
        foreach ($res->fetch_all(MYSQLI_ASSOC) as $f) {
            if (array_key_exists($f['clave'], $datos) && trim((string)$f['valor']) !== '') {
                $datos[$f['clave']] = $f['valor'];
            }
        }
    }
    $cache = $datos;
    return $cache;
}

function guardarConfiguracion($conexion, string $clave, string $valor): void
{
    $stmt = $conexion->prepare("INSERT INTO configuracion (clave, valor) VALUES (?, ?) ON DUPLICATE KEY UPDATE valor = VALUES(valor)");
    $stmt->bind_param("ss", $clave, $valor);
    $stmt->execute();
    $stmt->close();
}

function colorValido(string $c): bool
{
    return (bool)preg_match('/^#[0-9a-fA-F]{6}$/', $c);
}

// Oscurece un color hexadecimal (factor < 1) para derivar el tono de los titulos y la barra de menu
function colorOscuro(string $hex, float $factor = 0.66): string
{
    $hex = ltrim($hex, '#');
    $canales = [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
    return sprintf('#%02x%02x%02x', (int)($canales[0] * $factor), (int)($canales[1] * $factor), (int)($canales[2] * $factor));
}

// Ruta web del logo propio de la empresa, si cargo uno y el archivo existe
function logoEmpresa(array $emp): string
{
    if ($emp['logo'] === '' || !preg_match('~^uploads/logo\.(png|jpg|webp)$~', $emp['logo'])) return '';
    return is_file(__DIR__ . '/../' . $emp['logo']) ? $emp['logo'] : '';
}
