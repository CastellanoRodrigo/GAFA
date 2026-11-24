<?php
// Genera instalacion/esquema_limpio.sql a partir de la base actual: estructura de todas las tablas
// y datos solo de los catalogos (estados, tipos, monedas, formas de pago, categorias, cultivos, roles).
// Volver a ejecutarlo cada vez que cambie la estructura de la base:
//   php instalacion/generar_esquema.php
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Solo por linea de comandos'); }
chdir(dirname(__DIR__));
include './Modelo/Conexion.php';
include_once './Modelo/Respaldo.php';

$catalogos = ['categoria', 'cultivos', 'estadoproyecto', 'estadousuario', 'tipoproyecto', 'tipo_usuario', 'moneda', 'formapago'];
$faltan = array_diff($catalogos, tablasBase($conexion));
if ($faltan) { fwrite(STDERR, 'Faltan tablas en la base: ' . implode(', ', $faltan) . "\nEjecute antes todas las migraciones.\n"); exit(1); }
$sql = volcarSql($conexion, $catalogos, 'Esquema limpio de GAFA (sin datos de ejemplo)');
file_put_contents(__DIR__ . '/esquema_limpio.sql', $sql);
echo 'instalacion/esquema_limpio.sql generado (' . count(tablasBase($conexion)) . ' tablas, ' . round(strlen($sql) / 1024, 1) . " KB)\n";
