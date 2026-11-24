<?php
include('./Modelo/Conexion.php');
include_once('./Modelo/Auth.php');
include_once('./Modelo/Notificaciones.php');
requerirAdmin($conexion);   // enviar mensajes a terceros solo lo hace un administrador

$cfg = configNotificaciones();

function volverAvisos($conexion, string $clave, string $texto): void
{
    mysqli_close($conexion);
    header("Location: ./avisos.php?" . $clave . "=" . rawurlencode($texto));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !notificacionesListas($conexion)) {
    volverAvisos($conexion, 'error', 'Falta ejecutar migracion_notificaciones.sql en la base de datos.');
}

$accion = $_POST['accion'] ?? '';

if ($accion === 'uno') {
    $tipo = (string)($_POST['tipo'] ?? '');
    $ref = (int)($_POST['ref'] ?? 0);
    $canal = (string)($_POST['canal'] ?? '');
    // El mensaje se arma siempre en el servidor, nunca se acepta texto del navegador
    $aviso = buscarAviso($conexion, $cfg, $tipo, $ref);
    if (!$aviso) {
        volverAvisos($conexion, 'error', 'Ese aviso ya no está pendiente.');
    }
    $res = enviarAviso($conexion, $cfg, $aviso, $canal);
    if (!$res['ok']) {
        volverAvisos($conexion, 'error', (string)$res['error']);
    }
    if (isset($res['enlace'])) {          // WhatsApp por enlace: se abre la conversacion con el texto listo
        mysqli_close($conexion);
        header("Location: " . $res['enlace']);
        exit;
    }
    volverAvisos($conexion, 'ok', $res['estado'] === 'simulado'
        ? 'Mensaje guardado en modo prueba (no se envió). Configurá el envío real en config/notificaciones.php'
        : 'Aviso enviado');
}

if ($accion === 'todos') {
    $enviados = $omitidos = $fallidos = 0;
    foreach (construirAvisos($conexion, $cfg) as $a) {
        if (!emailValido($a['email']) || avisadoRecientemente($conexion, $cfg, $a['tipo'], $a['ref'], 'email')) {
            $omitidos++;
            continue;
        }
        $res = enviarAviso($conexion, $cfg, $a, 'email');
        $res['ok'] ? $enviados++ : $fallidos++;
    }
    volverAvisos($conexion, 'ok', "Mails: $enviados enviados, $omitidos omitidos (sin mail o ya avisados), $fallidos con error");
}

if ($accion === 'resumen') {
    $destino = $cfg['aviso_interno_email'];
    if (!emailValido($destino)) {
        volverAvisos($conexion, 'error', 'Configurá aviso_interno_email en config/notificaciones.php para recibir el resumen.');
    }
    [$asunto, $texto] = armarResumenInterno(construirAvisos($conexion, $cfg), $cfg);
    [$ok, $err] = enviarEmail($cfg, $destino, $asunto, $texto);
    volverAvisos($conexion, $ok ? 'ok' : 'error', $ok ? 'Resumen enviado a ' . $destino : (string)$err);
}

volverAvisos($conexion, 'error', 'Acción desconocida.');
