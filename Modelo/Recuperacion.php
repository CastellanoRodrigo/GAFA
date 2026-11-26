<?php
// Recuperacion de contrasena por mail: enlace de un solo uso, con vencimiento, guardado solo como hash.
require_once __DIR__ . '/Moneda.php';
require_once __DIR__ . '/Auditoria.php';

const RECUP_VALIDEZ_MIN = 60;       // el enlace vence a la hora
const RECUP_MAX_POR_HORA = 3;       // pedidos por usuario por hora
const CLAVE_MINIMA = 8;

function recuperacionLista($conexion): bool
{
    return tablaExiste($conexion, 'recuperacion') && columnaExiste($conexion, 'usuarios', 'email');
}

function correoValido(?string $e): bool
{
    $e = (string)$e;
    return $e !== '' && mb_strlen($e) <= 120 && filter_var($e, FILTER_VALIDATE_EMAIL) !== false;
}

// Direccion publica del sistema. Se puede fijar con 'url_base' en config/notificaciones.php
// (recomendado en produccion: evita depender del encabezado Host del pedido).
function urlBaseSistema(array $cfg): string
{
    $fija = trim((string)($cfg['url_base'] ?? ''));
    if ($fija !== '') return rtrim($fija, '/');
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['SERVER_PORT'] ?? '') == 443;
    $host = preg_replace('/[^A-Za-z0-9.:-]/', '', (string)($_SERVER['HTTP_HOST'] ?? 'localhost'));
    $dir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
    return ($https ? 'https' : 'http') . '://' . $host . $dir;
}

// Siempre se responde igual (exista o no el usuario) para no revelar quien tiene cuenta.
function solicitarRecuperacion($conexion, string $identificador): void
{
    if (!recuperacionLista($conexion)) return;
    $ident = mb_substr(trim($identificador), 0, 120);
    if ($ident === '') return;

    $s = $conexion->prepare("SELECT id_usuario, usuario, nombre, email, id_EstadoUsuario FROM usuarios WHERE usuario = ? OR email = ? LIMIT 1");
    $s->bind_param('ss', $ident, $ident);
    $s->execute();
    $u = $s->get_result()->fetch_assoc();
    $s->close();
    if (!$u) { auditar($conexion, 'recuperacion_desconocida', 'Pedido para un usuario inexistente', mb_substr($ident, 0, 60)); return; }
    if ((int)$u['id_EstadoUsuario'] !== 1 || !correoValido($u['email'])) {
        auditar($conexion, 'recuperacion_rechazada', 'Usuario inactivo o sin mail', $u['usuario']);
        return;
    }
    $id = (int)$u['id_usuario'];
    $s = $conexion->prepare("SELECT COUNT(*) FROM recuperacion WHERE id_usuario = ? AND creado > NOW() - INTERVAL 1 HOUR");
    $s->bind_param('i', $id);
    $s->execute();
    $recientes = (int)$s->get_result()->fetch_row()[0];
    $s->close();
    if ($recientes >= RECUP_MAX_POR_HORA) { auditar($conexion, 'recuperacion_limitada', 'Demasiados pedidos', $u['usuario']); return; }

    // Un solo enlace vigente por usuario: los anteriores dejan de servir
    $s = $conexion->prepare("UPDATE recuperacion SET usado = 1 WHERE id_usuario = ? AND usado = 0");
    $s->bind_param('i', $id);
    $s->execute();
    $s->close();

    $token = bin2hex(random_bytes(32));
    $hash = hash('sha256', $token);
    $ip = ipCliente();
    $min = RECUP_VALIDEZ_MIN;
    $s = $conexion->prepare("INSERT INTO recuperacion (id_usuario, tokenHash, expira, ip) VALUES (?, ?, NOW() + INTERVAL $min MINUTE, ?)");
    $s->bind_param('iss', $id, $hash, $ip);
    $s->execute();
    $s->close();

    require_once __DIR__ . '/Empresa.php';
    require_once __DIR__ . '/Notificaciones.php';
    $cfg = configNotificaciones();
    $enlace = urlBaseSistema($cfg) . '/restablecer.php?t=' . $token;
    $nombre = nombrePropioSimple($u['nombre']);
    $texto = "Hola" . ($nombre !== '' ? " $nombre" : '') . ",\n\n"
        . "Recibimos un pedido para cambiar la contraseña de tu usuario \"{$u['usuario']}\" en el sistema de {$cfg['empresa']}.\n\n"
        . "Para elegir una contraseña nueva, abrí este enlace (sirve una sola vez y vence en $min minutos):\n$enlace\n\n"
        . "Si no fuiste vos, ignorá este mensaje: tu contraseña actual sigue funcionando.\n";
    [$ok, $err] = enviarEmail($cfg, $u['email'], 'Recuperar tu contraseña - ' . $cfg['empresa'], $texto);
    auditar($conexion, $ok ? 'recuperacion_enviada' : 'recuperacion_error', $ok ? 'Enlace enviado por mail' : mb_substr((string)$err, 0, 200), $u['usuario']);
}

function nombrePropioSimple(?string $n): string
{
    return function_exists('nombrePropio') ? nombrePropio($n) : trim((string)$n);
}

// Devuelve el pedido vigente (no usado, no vencido, usuario activo) o null.
function buscarRecuperacion($conexion, string $token): ?array
{
    if (!recuperacionLista($conexion) || !preg_match('/^[0-9a-f]{64}$/', $token)) return null;
    $hash = hash('sha256', $token);
    $s = $conexion->prepare("SELECT r.id_Recuperacion, r.id_usuario, u.usuario FROM recuperacion r
        JOIN usuarios u ON u.id_usuario = r.id_usuario
        WHERE r.tokenHash = ? AND r.usado = 0 AND r.expira > NOW() AND u.id_EstadoUsuario = 1");
    $s->bind_param('s', $hash);
    $s->execute();
    $r = $s->get_result()->fetch_assoc();
    $s->close();
    return $r ?: null;
}
