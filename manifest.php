<?php
// Manifiesto de la app instalable: toma el nombre y el color de la empresa configurada.
include('./Modelo/Conexion.php');
include_once('./Modelo/Empresa.php');
$nombre = 'GAFA';
$color = '#0a6111';
try {
    $emp = empresa($conexion);
    if (!empty($emp['nombre'])) $nombre = (string)$emp['nombre'];
    if (!empty($emp['color']) && preg_match('/^#[0-9a-fA-F]{6}$/', $emp['color'])) $color = $emp['color'];
} catch (Throwable $e) {}
header('Content-Type: application/manifest+json; charset=utf-8');
header('Cache-Control: no-cache');
echo json_encode([
    'name' => 'GAFA - ' . $nombre,
    'short_name' => 'GAFA',
    'description' => 'Gestion agropecuaria: compras, ventas, hacienda y alquileres.',
    'lang' => 'es-AR',
    'start_url' => './inicio.php',
    'scope' => './',
    'display' => 'standalone',
    'orientation' => 'portrait-primary',
    'background_color' => '#f7f2e2',
    'theme_color' => $color,
    'icons' => [
        ['src' => 'pwa/icon-192.png', 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
        ['src' => 'pwa/icon-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'],
        ['src' => 'pwa/icon-maskable-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
    ],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
