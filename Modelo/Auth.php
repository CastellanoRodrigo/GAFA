<?php
// Helpers de sesión y salida segura. Incluir al inicio de scripts de procesamiento.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

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

// Solo usuarios de tipo Administrador (id_tipoUsuario = 1)
function requerirAdmin($conexion): void
{
    requerirLogin();
    $stmt = $conexion->prepare("SELECT id_tipoUsuario FROM usuarios WHERE usuario = ?");
    $stmt->bind_param("s", $_SESSION['usuario']);
    $stmt->execute();
    $fila = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$fila || (int)$fila['id_tipoUsuario'] !== 1) {
        http_response_code(403);
        exit('Acceso denegado');
    }
}
