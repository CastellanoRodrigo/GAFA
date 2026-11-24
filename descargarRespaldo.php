<?php
include('./Modelo/Conexion.php');
include_once('./Modelo/Auth.php');
include_once('./Modelo/Respaldo.php');
requerirAdmin($conexion);   // la copia contiene todos los datos de la empresa: solo administradores

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ./respaldo.php");
    exit;
}
$accion = $_POST['accion'] ?? '';

function enviarArchivo(string $nombre, string $contenido): void
{
    header('Content-Type: application/sql; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $nombre . '"');
    header('Content-Length: ' . strlen($contenido));
    header('Cache-Control: no-store');
    echo $contenido;
    exit;
}

if ($accion === 'descargar') {
    enviarArchivo('gafa-' . date('Ymd-His') . '.sql', volcarSql($conexion));
}
if ($accion === 'guardar') {
    try {
        guardarRespaldo($conexion);
        header("Location: ./respaldo.php?ok");
    } catch (Throwable $e) {
        header("Location: ./respaldo.php?error=" . rawurlencode($e->getMessage()));
    }
    exit;
}
if ($accion === 'bajar') {
    $n = (string)($_POST['archivo'] ?? '');
    $ruta = carpetaRespaldos() . '/' . $n;
    if (nombreRespaldoValido($n) && is_file($ruta)) {
        enviarArchivo($n, (string)file_get_contents($ruta));
    }
}
header("Location: ./respaldo.php?error=" . rawurlencode('Archivo no encontrado'));
exit;
