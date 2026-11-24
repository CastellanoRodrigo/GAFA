<?php
// Sesion, permisos por rol y salida segura. Incluir con include_once.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

const ROL_ADMIN = 1;      // todo, incluida la gestion de usuarios, empresa y copias de seguridad
const ROL_LECTOR = 2;     // solo consulta
const ROL_OPERADOR = 3;   // carga y edita datos operativos (proyectos, compras, alquileres, cobros)

function e($valor): string
{
    return htmlspecialchars((string)$valor, ENT_QUOTES, 'UTF-8');
}

// Nombres propios con mayuscula inicial: "lucila dolce" -> "Lucila Dolce", "de la cruz" -> "De la Cruz"
function nombrePropio(?string $texto): string
{
    $texto = trim((string)preg_replace('/\s+/u', ' ', (string)$texto));
    if ($texto === '') return '';
    $conectores = ['de', 'del', 'la', 'las', 'los', 'y', 'e', 'da', 'di', 'do', 'van', 'von'];
    $palabras = explode(' ', mb_strtolower($texto, 'UTF-8'));
    foreach ($palabras as $i => $p) {
        if ($i > 0 && in_array($p, $conectores, true)) continue;
        $palabras[$i] = preg_replace_callback('/(^|[\'\-])(\p{L})/u', function ($m) {
            return $m[1] . mb_strtoupper($m[2], 'UTF-8');
        }, $p);
    }
    return implode(' ', $palabras);
}

function requerirLogin(): void
{
    if (empty($_SESSION['usuario'])) {
        header("Location: ./login.php");
        exit;
    }
}

// Rol del usuario con sesion iniciada (0 si no existe). Se consulta una vez por pedido.
function rolUsuario($conexion): int
{
    static $cache = [];
    $usuario = (string)($_SESSION['usuario'] ?? '');
    if ($usuario === '') return 0;
    if (!isset($cache[$usuario])) {
        $stmt = $conexion->prepare("SELECT id_tipoUsuario FROM usuarios WHERE usuario = ?");
        $stmt->bind_param("s", $usuario);
        $stmt->execute();
        $fila = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        $cache[$usuario] = $fila ? (int)$fila['id_tipoUsuario'] : 0;
    }
    return $cache[$usuario];
}

function nombreRol(int $rol): string
{
    return [ROL_ADMIN => 'Administrador', ROL_LECTOR => 'Lector', ROL_OPERADOR => 'Operador'][$rol] ?? 'Sin rol';
}

function esAdmin($conexion): bool
{
    return rolUsuario($conexion) === ROL_ADMIN;
}

function puedeEscribir($conexion): bool
{
    return in_array(rolUsuario($conexion), [ROL_ADMIN, ROL_OPERADOR], true);
}

function denegarAcceso(string $mensaje): void
{
    http_response_code(403);
    echo '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><title>Acceso denegado</title>'
        . '<style>body{font-family:Arial,sans-serif;background:#f7f2e6;color:#2b2a26;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0}'
        . '.c{background:#fffdf8;border:1px solid #e2dac6;border-top:5px solid #d4a84b;border-radius:14px;padding:32px 40px;max-width:460px;text-align:center;box-shadow:0 6px 20px rgba(60,50,20,.12)}'
        . 'a{display:inline-block;margin-top:14px;padding:9px 22px;background:#2f5d34;color:#fff;border-radius:999px;text-decoration:none;font-weight:700}</style></head>'
        . '<body><div class="c"><h2>Acceso denegado</h2><p>' . e($mensaje) . '</p><a href="./inicio.php">Volver al inicio</a></div></body></html>';
    exit;
}

// Acciones de administracion: usuarios, proveedores, categorias, empresa, copias, envio de avisos
function requerirAdmin($conexion): void
{
    requerirLogin();
    if (!esAdmin($conexion)) {
        denegarAcceso('Esta acción es solo para administradores.');
    }
}

// Acciones que cargan o modifican datos operativos: administrador u operador (el lector solo consulta)
function requerirEscritura($conexion): void
{
    requerirLogin();
    if (!puedeEscribir($conexion)) {
        denegarAcceso('Tu usuario es de solo lectura. Pedile a un administrador que te asigne el rol Operador.');
    }
}
