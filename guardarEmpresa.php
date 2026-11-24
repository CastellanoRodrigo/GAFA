<?php
include('./Modelo/Conexion.php');
include_once('./Modelo/Auth.php');
include_once('./Modelo/Empresa.php');
requerirAdmin($conexion);

function volverEmpresa(string $q): void
{
    header("Location: ./empresa.php?" . $q);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') volverEmpresa('error=nombre');
if (!configuracionLista($conexion)) volverEmpresa('error=tablas');

$nombre = trim($_POST['nombre'] ?? '');
$color = trim($_POST['color'] ?? EMPRESA_COLOR_BASE);
$email = trim($_POST['email'] ?? '');
if ($nombre === '' || mb_strlen($nombre) > 100) volverEmpresa('error=nombre');
if (!colorValido($color)) volverEmpresa('error=color');
if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) volverEmpresa('error=email');

// Logo: imagen real, formato permitido, hasta 1 MB. Se guarda con nombre fijo (nunca el que manda el usuario).
$logoNuevo = null;
if (isset($_FILES['logo']) && $_FILES['logo']['error'] !== UPLOAD_ERR_NO_FILE) {
    $f = $_FILES['logo'];
    if ($f['error'] !== UPLOAD_ERR_OK || $f['size'] > 1048576) volverEmpresa('error=logo');
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
    $ext = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp'][$mime] ?? null;
    if ($ext === null || @getimagesize($f['tmp_name']) === false) volverEmpresa('error=logo');
    $dir = __DIR__ . '/uploads';
    if (!is_dir($dir) && !@mkdir($dir, 0775, true)) volverEmpresa('error=carpeta');
    foreach (glob($dir . '/logo.*') ?: [] as $viejo) @unlink($viejo);
    if (!move_uploaded_file($f['tmp_name'], $dir . '/logo.' . $ext)) volverEmpresa('error=carpeta');
    $logoNuevo = 'uploads/logo.' . $ext;
}
if (!empty($_POST['quitar_logo']) && $logoNuevo === null) {
    foreach (glob(__DIR__ . '/uploads/logo.*') ?: [] as $viejo) @unlink($viejo);
    $logoNuevo = '';
}

guardarConfiguracion($conexion, 'nombre', $nombre);
guardarConfiguracion($conexion, 'color', $color);
foreach (['lema', 'cuit', 'email', 'telefono', 'direccion'] as $campo) {
    guardarConfiguracion($conexion, $campo, mb_substr(trim($_POST[$campo] ?? ''), 0, 150));
}
if ($logoNuevo !== null) guardarConfiguracion($conexion, 'logo', $logoNuevo);

volverEmpresa('ok');
