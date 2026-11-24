<?php
// Copia de seguridad diaria. Ejecutar desde la linea de comandos:
//   php cron/respaldo.php            (conserva las ultimas 14 copias)
//   php cron/respaldo.php --conservar=30
// Programarlo una vez por dia en el Programador de tareas de Windows (o con cron en Linux).
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Solo se puede ejecutar por linea de comandos');
}
chdir(dirname(__DIR__));
include './Modelo/Conexion.php';
include_once './Modelo/Respaldo.php';

$conservar = 14;
foreach (array_slice($argv, 1) as $a) {
    if (preg_match('/^--conservar=(\d+)$/', $a, $m)) $conservar = max(1, (int)$m[1]);
}
try {
    $ruta = guardarRespaldo($conexion, $conservar);
    echo date('Y-m-d H:i') . ' - copia guardada: ' . basename($ruta) . ' (' . round(filesize($ruta) / 1024, 1) . " KB), se conservan $conservar\n";
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, 'ERROR: ' . $e->getMessage() . "\n");
    exit(1);
}
