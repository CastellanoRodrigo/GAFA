<?php
// Avisos de vencimientos y pagos por mail y WhatsApp.
require_once __DIR__ . '/Moneda.php';
require_once __DIR__ . '/Alquileres.php';
require_once __DIR__ . '/Empresa.php';

function configNotificaciones(): array
{
    $defecto = require __DIR__ . '/../config/notificaciones.ejemplo.php';
    $archivo = __DIR__ . '/../config/notificaciones.php';
    $real = is_file($archivo) ? (require $archivo) : [];
    // Mezcla por secciones: lo que no este en la config real toma el valor del ejemplo
    $cfg = array_merge($defecto, $real);
    foreach (['email', 'whatsapp'] as $sec) {
        $cfg[$sec] = array_merge($defecto[$sec], $real[$sec] ?? []);
    }
    // Sin nombre fijado en el archivo de configuracion, se usa el de la empresa cargada en el sistema
    if (trim((string)($real['empresa'] ?? '')) === '') {
        $cfg['empresa'] = empresa()['nombre'];
        if (trim((string)($real['email']['nombre_remitente'] ?? '')) === '') $cfg['email']['nombre_remitente'] = $cfg['empresa'];
    }
    return $cfg;
}

function notificacionesListas($conexion): bool
{
    return tablasAlquilerListas($conexion)
        && tablaExiste($conexion, 'notificaciones')
        && columnaExiste($conexion, 'detallealquiler', 'email')
        && columnaExiste($conexion, 'detallealquiler', 'telefono');
}

// ---------- Avisos pendientes ----------

// Devuelve los avisos que corresponde hacer hoy, con el mensaje ya redactado para el inquilino.
function construirAvisos($conexion, array $cfg): array
{
    $avisos = [];
    $hoy = new DateTime(date('Y-m-d'));
    $diasCuota = max(0, (int)$cfg['dias_cuota_proxima']);
    $diasContrato = max(0, (int)$cfg['dias_contrato_por_vencer']);
    $empresa = $cfg['empresa'];

    // Cuotas de alquiler vencidas o por vencer
    $stmt = $conexion->prepare("SELECT c.id_Cobro, c.fechaVencimiento, c.monto, da.id_DetalleAlquiler, da.inquilino, da.email, da.telefono,
                                       m.moneda, p.nombreProyecto, p.id_Parcela
        FROM cobrosalquiler c
        JOIN detallealquiler da ON da.id_DetalleAlquiler = c.id_DetalleAlquiler
        JOIN proyectos p ON p.id_Proyecto = da.id_Proyecto
        LEFT JOIN moneda m ON m.id_Moneda = da.id_Moneda
        WHERE c.fechaPago IS NULL AND c.fechaVencimiento <= DATE_ADD(CURDATE(), INTERVAL ? DAY)
        ORDER BY c.fechaVencimiento ASC, c.id_Cobro ASC");
    $stmt->bind_param("i", $diasCuota);
    $stmt->execute();
    $vencidas = [];   // por contrato: un solo aviso con todas las cuotas atrasadas
    foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $c) {
        $venc = new DateTime($c['fechaVencimiento']);
        $dif = (int)$hoy->diff($venc)->format('%r%a');   // negativo = ya vencida
        $lugar = $c['nombreProyecto'] . ' (parcela N° ' . (int)$c['id_Parcela'] . ')';
        if ($dif < 0) {
            $k = (int)$c['id_DetalleAlquiler'];
            if (!isset($vencidas[$k])) {
                $vencidas[$k] = ['c' => $c, 'lugar' => $lugar, 'cuotas' => 0, 'total' => 0.0, 'masAntigua' => $c['fechaVencimiento'], 'dias' => abs($dif)];
            }
            $vencidas[$k]['cuotas']++;
            $vencidas[$k]['total'] += (float)$c['monto'];
            continue;
        }
        $monto = montoMoneda($c['monto'], $c['moneda']);
        $cuando = $dif === 0 ? 'vence hoy' : ($dif === 1 ? 'vence mañana' : 'vence el ' . fechaCorta($c['fechaVencimiento']));
        $avisos[] = [
            'tipo' => 'cuota_proxima', 'ref' => (int)$c['id_Cobro'], 'contrato' => (int)$c['id_DetalleAlquiler'],
            'gravedad' => 'aviso', 'titulo' => $dif === 0 ? 'Cuota vence hoy' : "Cuota vence en $dif día" . ($dif === 1 ? '' : 's'),
            'detalle' => $lugar . ' · ' . $c['inquilino'] . ' · ' . $monto,
            'vencimiento' => $c['fechaVencimiento'], 'inquilino' => $c['inquilino'],
            'email' => $c['email'], 'telefono' => $c['telefono'],
            'asunto' => 'Recordatorio de cuota de alquiler - ' . $empresa,
            'mensaje' => "Hola {$c['inquilino']}, le recordamos que la cuota de alquiler de $lugar por $monto $cuando. Muchas gracias. $empresa",
        ];
    }
    $stmt->close();
    foreach ($vencidas as $k => $v) {
        $c = $v['c'];
        $totalTxt = montoMoneda($v['total'], $c['moneda']);
        $n = $v['cuotas'];
        $avisos[] = [
            'tipo' => 'cuotas_vencidas', 'ref' => $k, 'contrato' => $k,
            'gravedad' => 'mal',
            'titulo' => $n . ' cuota' . ($n === 1 ? '' : 's') . ' vencida' . ($n === 1 ? '' : 's') . ' (la más antigua hace ' . $v['dias'] . ' día' . ($v['dias'] === 1 ? '' : 's') . ')',
            'detalle' => $v['lugar'] . ' · ' . $c['inquilino'] . ' · ' . $totalTxt,
            'vencimiento' => $v['masAntigua'], 'inquilino' => $c['inquilino'],
            'email' => $c['email'], 'telefono' => $c['telefono'],
            'asunto' => ($n === 1 ? 'Cuota de alquiler vencida' : 'Cuotas de alquiler vencidas') . ' - ' . $empresa,
            'mensaje' => "Hola {$c['inquilino']}, registramos $n cuota" . ($n === 1 ? '' : 's') . " de alquiler de {$v['lugar']} vencida" . ($n === 1 ? '' : 's')
                . " sin cobrar, por un total de $totalTxt (la más antigua venció el " . fechaCorta($v['masAntigua']) . "). "
                . "Si ya las abonó, por favor ignore este mensaje. Muchas gracias. $empresa",
        ];
    }
    // Vencidas primero, mas antiguas arriba
    usort($avisos, function ($a, $b) {
        return [$a['gravedad'] === 'mal' ? 0 : 1, $a['vencimiento']] <=> [$b['gravedad'] === 'mal' ? 0 : 1, $b['vencimiento']];
    });
    // Contratos que terminan pronto
    $stmt = $conexion->prepare("SELECT da.id_DetalleAlquiler, da.inquilino, da.email, da.telefono, da.fechaFin, p.nombreProyecto, p.id_Parcela
        FROM detallealquiler da JOIN proyectos p ON p.id_Proyecto = da.id_Proyecto
        WHERE da.fechaFin >= CURDATE() AND da.fechaFin <= DATE_ADD(CURDATE(), INTERVAL ? DAY)
        ORDER BY da.fechaFin ASC");
    $stmt->bind_param("i", $diasContrato);
    $stmt->execute();
    foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $k) {
        $dif = (int)$hoy->diff(new DateTime($k['fechaFin']))->format('%r%a');
        $lugar = $k['nombreProyecto'] . ' (parcela N° ' . (int)$k['id_Parcela'] . ')';
        $avisos[] = [
            'tipo' => 'contrato_por_vencer', 'ref' => (int)$k['id_DetalleAlquiler'], 'contrato' => (int)$k['id_DetalleAlquiler'],
            'gravedad' => 'aviso', 'titulo' => "Contrato termina en $dif día" . ($dif === 1 ? '' : 's'),
            'detalle' => $lugar . ' · ' . $k['inquilino'], 'vencimiento' => $k['fechaFin'], 'inquilino' => $k['inquilino'],
            'email' => $k['email'], 'telefono' => $k['telefono'],
            'asunto' => 'Vencimiento de contrato de alquiler - ' . $empresa,
            'mensaje' => "Hola {$k['inquilino']}, le informamos que el contrato de alquiler de $lugar vence el " . fechaCorta($k['fechaFin'])
                . ". Quedamos a disposición para coordinar la renovación. $empresa",
        ];
    }
    $stmt->close();
    usort($avisos, function ($a, $b) {
        return [$a['gravedad'] === 'mal' ? 0 : 1, $a['vencimiento']] <=> [$b['gravedad'] === 'mal' ? 0 : 1, $b['vencimiento']];
    });
    return $avisos;
}

function buscarAviso($conexion, array $cfg, string $tipo, int $ref): ?array
{
    foreach (construirAvisos($conexion, $cfg) as $a) {
        if ($a['tipo'] === $tipo && $a['ref'] === $ref) return $a;
    }
    return null;
}

// ---------- Registro y control de repeticiones ----------

function registrarNotificacion($conexion, array $aviso, string $canal, string $destino, string $estado, ?string $error = null): void
{
    $stmt = $conexion->prepare("INSERT INTO notificaciones (tipo, id_Referencia, canal, destinatario, asunto, mensaje, estado, detalleError)
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $tipo = $aviso['tipo']; $ref = $aviso['ref']; $asunto = $aviso['asunto']; $msg = $aviso['mensaje'];
    $error = $error !== null ? mb_substr($error, 0, 250) : null;
    $stmt->bind_param("sissssss", $tipo, $ref, $canal, $destino, $asunto, $msg, $estado, $error);
    $stmt->execute();
    $stmt->close();
}

// Ultima fecha en que se aviso por ese canal (solo envios que no fallaron)
function ultimoAviso($conexion, string $tipo, int $ref, string $canal): ?string
{
    $stmt = $conexion->prepare("SELECT MAX(fecha) FROM notificaciones WHERE tipo = ? AND id_Referencia = ? AND canal = ? AND estado <> 'error'");
    $stmt->bind_param("sis", $tipo, $ref, $canal);
    $stmt->execute();
    $f = $stmt->get_result()->fetch_row()[0] ?? null;
    $stmt->close();
    return $f;
}

function avisadoRecientemente($conexion, array $cfg, string $tipo, int $ref, string $canal): bool
{
    $ultimo = ultimoAviso($conexion, $tipo, $ref, $canal);
    if (!$ultimo) return false;
    return strtotime($ultimo) > strtotime('-' . max(1, (int)$cfg['reenviar_cada_dias']) . ' days');
}

// ---------- Telefonos y WhatsApp ----------

// Deja solo digitos con codigo de pais, listo para wa.me. Argentina: 54 9 + area + numero (sin el 0 ni el 15).
// Ej: "3492 15 123456" -> 5493492123456 ; "011 15 5555-1234" -> 5491155551234 ; "+54 9 3492 123456" -> 5493492123456
function normalizarTelefono(?string $tel, string $pais = '54'): string
{
    $n = preg_replace('/\D+/', '', (string)$tel);
    if ($n === '') return '';
    if (strpos($n, '00') === 0) $n = substr($n, 2);
    elseif (strpos($n, '0') === 0) $n = substr($n, 1);
    if ($pais !== '54') {
        return strpos($n, $pais) === 0 ? $n : $pais . $n;
    }
    if (strpos($n, '54') === 0 && strlen($n) > 10) $n = substr($n, 2);
    if (strpos($n, '9') === 0 && strlen($n) === 11) $n = substr($n, 1);
    // Area (2 a 4 digitos) + "15" + abonado = 12 digitos: se quita el 15
    if (strlen($n) === 12) {
        foreach ([2, 3, 4] as $area) {
            if (substr($n, $area, 2) === '15') { $n = substr($n, 0, $area) . substr($n, $area + 2); break; }
        }
    }
    return '549' . $n;
}

function telefonoValido(string $normalizado): bool
{
    return strlen($normalizado) >= 11 && strlen($normalizado) <= 15;
}

function enlaceWhatsApp(string $telefonoNormalizado, string $mensaje): string
{
    return 'https://wa.me/' . $telefonoNormalizado . '?text=' . rawurlencode($mensaje);
}

function enviarWhatsAppApi(array $cfg, string $telefonoNormalizado, string $mensaje): array
{
    $w = $cfg['whatsapp'];
    if ($w['token'] === '' || $w['phone_number_id'] === '') return [false, 'Faltan token o phone_number_id en config/notificaciones.php'];
    $ch = curl_init('https://graph.facebook.com/v19.0/' . rawurlencode($w['phone_number_id']) . '/messages');
    curl_setopt_array($ch, [
        CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 20,
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $w['token'], 'Content-Type: application/json'],
        CURLOPT_POSTFIELDS => json_encode(['messaging_product' => 'whatsapp', 'to' => $telefonoNormalizado, 'type' => 'text', 'text' => ['body' => $mensaje]]),
    ]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    if ($resp === false) return [false, 'Error de conexión: ' . $err];
    return $code >= 200 && $code < 300 ? [true, null] : [false, 'WhatsApp respondió ' . $code . ': ' . substr((string)$resp, 0, 180)];
}

// ---------- Mail ----------

function emailValido(?string $e): bool
{
    return (bool)filter_var((string)$e, FILTER_VALIDATE_EMAIL);
}

function armarMensajeMime(array $cfg, string $para, string $asunto, string $texto): string
{
    $e = $cfg['email'];
    $de = $e['remitente'];
    $nombre = '=?UTF-8?B?' . base64_encode($e['nombre_remitente']) . '?=';
    $cab = [
        'Date: ' . date('r'),
        "From: $nombre <$de>",
        "To: $para",
        'Subject: =?UTF-8?B?' . base64_encode($asunto) . '?=',
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
        'Content-Transfer-Encoding: base64',
        'Message-ID: <' . bin2hex(random_bytes(8)) . '@gafa.local>',
    ];
    return implode("\r\n", $cab) . "\r\n\r\n" . chunk_split(base64_encode($texto), 76, "\r\n");
}

function enviarEmail(array $cfg, string $para, string $asunto, string $texto): array
{
    if (!emailValido($para)) return [false, 'Dirección de correo inválida'];
    $e = $cfg['email'];
    $mime = armarMensajeMime($cfg, $para, $asunto, $texto);

    if ($e['driver'] === 'archivo') {
        $dir = $e['carpeta_archivo'];
        if (!is_dir($dir) && !@mkdir($dir, 0775, true)) return [false, 'No se pudo crear la carpeta ' . $dir];
        $ruta = rtrim($dir, '/\\') . DIRECTORY_SEPARATOR . date('Ymd-His') . '-' . bin2hex(random_bytes(3)) . '.eml';
        return @file_put_contents($ruta, $mime) !== false ? [true, null] : [false, 'No se pudo escribir ' . $ruta];
    }
    if ($e['driver'] === 'mail') {
        $partes = explode("\r\n\r\n", $mime, 2);
        $ok = @mail($para, '=?UTF-8?B?' . base64_encode($asunto) . '?=', $partes[1], preg_replace('/^(To|Subject): .*\r\n/m', '', $partes[0]));
        return $ok ? [true, null] : [false, 'mail() de PHP no pudo enviar (servidor sin configurar)'];
    }
    if ($e['driver'] === 'smtp') {
        return enviarSmtp($e, $para, $mime);
    }
    return [false, 'Driver de mail desconocido: ' . $e['driver']];
}

function enviarSmtp(array $e, string $para, string $mime): array
{
    $esSsl = $e['seguridad'] === 'ssl';
    $fp = @stream_socket_client(($esSsl ? 'ssl://' : 'tcp://') . $e['host'] . ':' . (int)$e['puerto'], $errno, $errstr, 15);
    if (!$fp) return [false, "No se pudo conectar a {$e['host']}: $errstr"];
    stream_set_timeout($fp, 15);
    $leer = function () use ($fp) {
        $r = '';
        while (($l = fgets($fp, 515)) !== false) { $r .= $l; if (isset($l[3]) && $l[3] === ' ') break; }
        return $r;
    };
    $cmd = function (string $c, array $esperados) use ($fp, $leer) {
        fwrite($fp, $c . "\r\n");
        $r = $leer();
        return in_array(substr($r, 0, 3), $esperados, true) ? [true, $r] : [false, trim($r)];
    };
    try {
        $leer();
        [$ok, $r] = $cmd('EHLO gafa.local', ['250']); if (!$ok) return [false, "EHLO: $r"];
        if ($e['seguridad'] === 'tls') {
            [$ok, $r] = $cmd('STARTTLS', ['220']); if (!$ok) return [false, "STARTTLS: $r"];
            if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) return [false, 'No se pudo establecer la conexión segura'];
            [$ok, $r] = $cmd('EHLO gafa.local', ['250']); if (!$ok) return [false, "EHLO: $r"];
        }
        if ($e['usuario'] !== '') {
            [$ok, $r] = $cmd('AUTH LOGIN', ['334']); if (!$ok) return [false, "AUTH: $r"];
            [$ok, $r] = $cmd(base64_encode($e['usuario']), ['334']); if (!$ok) return [false, "Usuario SMTP: $r"];
            [$ok, $r] = $cmd(base64_encode($e['clave']), ['235']); if (!$ok) return [false, 'Usuario o clave SMTP incorrectos'];
        }
        [$ok, $r] = $cmd('MAIL FROM:<' . $e['remitente'] . '>', ['250']); if (!$ok) return [false, "MAIL FROM: $r"];
        [$ok, $r] = $cmd('RCPT TO:<' . $para . '>', ['250', '251']); if (!$ok) return [false, "RCPT TO: $r"];
        [$ok, $r] = $cmd('DATA', ['354']); if (!$ok) return [false, "DATA: $r"];
        fwrite($fp, preg_replace('/^\./m', '..', $mime) . "\r\n.\r\n");
        $r = $leer();
        if (substr($r, 0, 3) !== '250') return [false, 'El servidor rechazó el mensaje: ' . trim($r)];
        $cmd('QUIT', ['221']);
        return [true, null];
    } finally {
        fclose($fp);
    }
}

// ---------- Envio de un aviso ----------

// Envia un aviso por el canal indicado ('email' o 'whatsapp') y lo deja registrado.
// Para WhatsApp en modo "enlace" devuelve la URL a abrir (el envio lo confirma la persona en WhatsApp).
function enviarAviso($conexion, array $cfg, array $aviso, string $canal): array
{
    if ($canal === 'email') {
        if (!emailValido($aviso['email'])) return ['ok' => false, 'error' => 'El contrato no tiene un correo válido cargado.'];
        [$ok, $err] = enviarEmail($cfg, $aviso['email'], $aviso['asunto'], $aviso['mensaje']);
        $estado = $ok ? ($cfg['email']['driver'] === 'archivo' ? 'simulado' : 'enviado') : 'error';
        registrarNotificacion($conexion, $aviso, 'email', $aviso['email'], $estado, $err);
        return ['ok' => $ok, 'error' => $err, 'estado' => $estado];
    }
    if ($canal === 'whatsapp') {
        $tel = normalizarTelefono($aviso['telefono'], $cfg['whatsapp']['codigo_pais']);
        if (!telefonoValido($tel)) return ['ok' => false, 'error' => 'El contrato no tiene un teléfono válido cargado.'];
        if ($cfg['whatsapp']['driver'] === 'cloud_api') {
            [$ok, $err] = enviarWhatsAppApi($cfg, $tel, $aviso['mensaje']);
            registrarNotificacion($conexion, $aviso, 'whatsapp', $tel, $ok ? 'enviado' : 'error', $err);
            return ['ok' => $ok, 'error' => $err, 'estado' => $ok ? 'enviado' : 'error'];
        }
        registrarNotificacion($conexion, $aviso, 'whatsapp', $tel, 'manual');
        return ['ok' => true, 'error' => null, 'estado' => 'manual', 'enlace' => enlaceWhatsApp($tel, $aviso['mensaje'])];
    }
    return ['ok' => false, 'error' => 'Canal desconocido'];
}

// Resumen para el dueño: todo lo que vence o esta vencido
function armarResumenInterno(array $avisos, array $cfg): array
{
    $lineas = [];
    foreach ($avisos as $a) {
        $lineas[] = '- ' . $a['titulo'] . ': ' . $a['detalle'] . ' (' . fechaCorta($a['vencimiento']) . ')';
    }
    $n = count($avisos);
    $asunto = $cfg['empresa'] . ' - ' . $n . ' aviso' . ($n === 1 ? '' : 's') . ' de vencimientos y pagos';
    $texto = "Resumen de vencimientos y pagos al " . date('d/m/Y') . ":\n\n" . implode("\n", $lineas)
        . "\n\nPuede gestionarlos desde la sección Avisos del sistema GAFA.";
    return [$asunto, $texto];
}
