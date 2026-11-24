<?php
// Envio diario de avisos de vencimientos y pagos. Ejecutar desde la linea de comandos:
//   php cron/enviar_avisos.php
// Programarlo una vez por dia:
//   - Windows: Programador de tareas -> Accion: php.exe  Argumentos: D:\ruta\GAFA\cron\enviar_avisos.php
//   - Linux:   0 8 * * *  php /ruta/GAFA/cron/enviar_avisos.php
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Solo se puede ejecutar por linea de comandos');
}
chdir(dirname(__DIR__));
include './Modelo/Conexion.php';
include_once './Modelo/Notificaciones.php';

$cfg = configNotificaciones();
if (!notificacionesListas($conexion)) {
    fwrite(STDERR, "Falta ejecutar migracion_alquiler.sql y migracion_notificaciones.sql\n");
    exit(1);
}

$avisos = construirAvisos($conexion, $cfg);
$enviados = ['email' => 0, 'whatsapp' => 0]; $errores = 0; $omitidos = 0;

foreach ($avisos as $a) {
    // Mail al inquilino
    if (emailValido($a['email']) && !avisadoRecientemente($conexion, $cfg, $a['tipo'], $a['ref'], 'email')) {
        $r = enviarAviso($conexion, $cfg, $a, 'email');
        if ($r['ok']) { $enviados['email']++; } else { $errores++; fwrite(STDERR, "Mail {$a['tipo']}#{$a['ref']}: {$r['error']}\n"); }
    } else {
        $omitidos++;
    }
    // WhatsApp automatico solo si esta configurada la API (con "enlace" lo envia una persona desde la pantalla Avisos)
    if ($cfg['whatsapp']['driver'] === 'cloud_api' && telefonoValido(normalizarTelefono($a['telefono'], $cfg['whatsapp']['codigo_pais']))
        && !avisadoRecientemente($conexion, $cfg, $a['tipo'], $a['ref'], 'whatsapp')) {
        $r = enviarAviso($conexion, $cfg, $a, 'whatsapp');
        if ($r['ok']) { $enviados['whatsapp']++; } else { $errores++; fwrite(STDERR, "WhatsApp {$a['tipo']}#{$a['ref']}: {$r['error']}\n"); }
    }
}

// Resumen diario para el dueño
$resumen = 'sin destinatario configurado';
if ($avisos && emailValido($cfg['aviso_interno_email'])) {
    [$asunto, $texto] = armarResumenInterno($avisos, $cfg);
    [$ok, $err] = enviarEmail($cfg, $cfg['aviso_interno_email'], $asunto, $texto);
    $resumen = $ok ? 'enviado a ' . $cfg['aviso_interno_email'] : 'ERROR: ' . $err;
    if (!$ok) $errores++;
}

echo date('Y-m-d H:i') . " - avisos: " . count($avisos) . ", mails: {$enviados['email']}, whatsapp: {$enviados['whatsapp']}, omitidos: $omitidos, errores: $errores, resumen interno: $resumen\n";
exit($errores > 0 ? 2 : 0);
