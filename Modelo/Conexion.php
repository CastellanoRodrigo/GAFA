<?php
// Conexion a la base de datos. Lee config/base_datos.php (lo crea el instalador);
// si no existe usa los valores por defecto de XAMPP de este proyecto.
$__archivoBd = __DIR__ . '/../config/base_datos.php';
$__bd = is_file($__archivoBd) ? (require $__archivoBd)
    : ['host' => 'localhost', 'puerto' => 3307, 'usuario' => 'root', 'clave' => '', 'base' => 'donjuanbd'];
$conexion = mysqli_connect($__bd['host'], $__bd['usuario'], $__bd['clave'], $__bd['base'], (int)$__bd['puerto']);
unset($__archivoBd, $__bd);
?>
