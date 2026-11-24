<?php
// Helpers de sesión y salida segura. Incluir al inicio de scripts de procesamiento.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function e($valor): string
{
    return htmlspecialchars((string)$valor, ENT_QUOTES, 'UTF-8');
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
