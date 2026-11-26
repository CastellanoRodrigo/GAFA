<?php
// Bloqueo de intentos de ingreso y registro de actividad. Si la migracion no se ejecuto, todo funciona como antes (no-op).
require_once __DIR__ . '/Moneda.php';

const LOGIN_MAX_USUARIO = 5;     // fallos por usuario en la ventana
const LOGIN_MAX_IP = 20;         // fallos por IP en la ventana
const LOGIN_VENTANA_MIN = 15;

function seguridadLista($conexion): bool
{
    return tablaExiste($conexion, 'intentoslogin') && tablaExiste($conexion, 'auditoria');
}

function ipCliente(): string
{
    return mb_substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
}

function loginBloqueado($conexion, string $usuario): bool
{
    if (!seguridadLista($conexion)) return false;
    $u = mb_substr($usuario, 0, 60);
    $ip = ipCliente();
    $min = LOGIN_VENTANA_MIN;
    $s = $conexion->prepare("SELECT
        (SELECT COUNT(*) FROM intentoslogin WHERE usuario = ? AND fecha > NOW() - INTERVAL $min MINUTE),
        (SELECT COUNT(*) FROM intentoslogin WHERE ip = ? AND fecha > NOW() - INTERVAL $min MINUTE)");
    $s->bind_param('ss', $u, $ip);
    $s->execute();
    [$porUsuario, $porIp] = $s->get_result()->fetch_row();
    $s->close();
    return (int)$porUsuario >= LOGIN_MAX_USUARIO || (int)$porIp >= LOGIN_MAX_IP;
}

function registrarIntentoFallido($conexion, string $usuario): void
{
    if (!seguridadLista($conexion)) return;
    $u = mb_substr($usuario, 0, 60);
    $ip = ipCliente();
    $s = $conexion->prepare("INSERT INTO intentoslogin (usuario, ip) VALUES (?, ?)");
    $s->bind_param('ss', $u, $ip);
    $s->execute();
    $s->close();
}

function limpiarIntentos($conexion, string $usuario): void
{
    if (!seguridadLista($conexion)) return;
    $u = mb_substr($usuario, 0, 60);
    $s = $conexion->prepare("DELETE FROM intentoslogin WHERE usuario = ? OR fecha < NOW() - INTERVAL 1 DAY");
    $s->bind_param('s', $u);
    $s->execute();
    $s->close();
}

// Registra una accion del usuario en sesion (o del indicado).
function auditar($conexion, string $accion, string $detalle = '', ?string $usuario = null): void
{
    if (!seguridadLista($conexion)) return;
    $usuario = $usuario ?? ($_SESSION['usuario'] ?? null);
    $u = $usuario !== null ? mb_substr($usuario, 0, 60) : null;
    $a = mb_substr($accion, 0, 40);
    $d = mb_substr($detalle, 0, 255);
    $ip = ipCliente();
    $s = $conexion->prepare("INSERT INTO auditoria (usuario, accion, detalle, ip) VALUES (?, ?, ?, ?)");
    $s->bind_param('ssss', $u, $a, $d, $ip);
    $s->execute();
    $s->close();
}
