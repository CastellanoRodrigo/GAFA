<?php
// Pruebas de humo sin dependencias. Uso (con el servidor y la BD de prueba levantados):
//   php -S localhost:8000 -t .        (desde la raiz del proyecto)
//   php tests/run.php http://localhost:8000
// Usuarios del dump: admin/123 (activo, administrador), nuevoa/nuevoa (inactivo).
$base = rtrim($argv[1] ?? 'http://localhost:8000', '/');
$raiz = dirname(__DIR__);
$fallos = 0;

function peticion(string $url, ?array $post = null, string $cookie = '', array $files = []): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_FOLLOWLOCATION => false]);
    if ($cookie) curl_setopt($ch, CURLOPT_COOKIE, $cookie);
    if ($post !== null) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $files ? $post + $files : http_build_query($post));
    }
    $r = curl_exec($ch);
    $hs = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $h = substr($r, 0, $hs);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    preg_match('/Location:\s*(\S+)/i', $h, $loc);
    preg_match('/Set-Cookie:\s*(PHPSESSID=[^;]+)/i', $h, $ck);
    return ['code' => $code, 'loc' => $loc[1] ?? '', 'cookie' => $ck[1] ?? '', 'body' => substr($r, $hs)];
}

function prueba(string $nombre, bool $ok): void
{
    global $fallos;
    echo ($ok ? "[OK]    " : "[FALLO] ") . $nombre . "\n";
    if (!$ok) $fallos++;
}

function login(string $base, string $u, string $p): array
{
    return peticion("$base/Controlador/controlador_login.php", ['usuario' => $u, 'password' => $p, 'btningresar' => '1']);
}

// 1. Sintaxis de todos los .php
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($raiz, FilesystemIterator::SKIP_DOTS));
foreach ($it as $f) {
    $f = (string)$f;
    if (substr($f, -4) === '.php' && strpos($f, DIRECTORY_SEPARATOR . 'tests' . DIRECTORY_SEPARATOR) === false
        && strpos($f, DIRECTORY_SEPARATOR . 'entorno' . DIRECTORY_SEPARATOR) === false) {
        $out = [];
        exec('"' . PHP_BINARY . '" -l ' . escapeshellarg($f) . ' 2>&1', $out, $rc);
        prueba('sintaxis ' . substr($f, strlen($raiz) + 1), $rc === 0);
    }
}

// 2. Login
$r = login($base, 'admin', '123');
prueba('login valido redirige a ?ok', strpos($r['loc'], 'login.php?ok') !== false);
$cookieAdmin = $r['cookie'];
prueba('login valido crea sesion', $cookieAdmin !== '');
prueba('clave incorrecta -> ?err', strpos(login($base, 'admin', 'xxx')['loc'], '?err') !== false);
prueba('usuario inexistente -> ?err', strpos(login($base, 'noexiste', 'x')['loc'], '?err') !== false);
prueba("inyeccion SQL ' OR '1'='1 rechazada", strpos(login($base, "' OR '1'='1", "' OR '1'='1")['loc'], '?err') !== false);
prueba('usuario inactivo -> ?ini', strpos(login($base, 'nuevoa', 'nuevoa')['loc'], '?ini') !== false);
prueba('login sigue valido tras migrar a hash', strpos(login($base, 'admin', '123')['loc'], '?ok') !== false);

// 3. Sin sesion
foreach (['inicio.php', 'usuarios.php', 'proveedores.php', 'todosLosProyectos.php'] as $p) {
    prueba("$p sin sesion redirige a login", strpos(peticion("$base/$p")['loc'], 'login.php') !== false);
}
foreach (['Exportar/ExcelCompras.php', 'Exportar/ExcelProyectos.php'] as $p) {
    prueba("$p sin sesion -> 403", peticion("$base/$p", ['sql' => 'SELECT 1'])['code'] === 403);
}
$nuevo = ['usuario' => 'hack', 'pass' => 'x', 'nombre' => 'a', 'apellido' => 'b', 'tipo' => 1, 'estado' => 1];
prueba('agrUsuario sin sesion redirige a login', strpos(peticion("$base/agrUsuario.php", $nuevo)['loc'], 'login.php') !== false);

// 4. Con sesion
foreach (['inicio.php', 'nosotros.php', 'usuarios.php', 'proveedores.php', 'categorias.php', 'todosLosProyectos.php', 'estadisticas.php'] as $p) {
    prueba("$p con sesion responde 200", peticion("$base/$p", null, $cookieAdmin)['code'] === 200);
}
prueba('exportacion rechaza SQL arbitrario', peticion("$base/Exportar/ExcelCompras.php", ['sql' => 'DROP TABLE usuarios'], $cookieAdmin)['code'] === 400);
prueba('exportacion rechaza consultas multiples', peticion("$base/Exportar/ExcelCompras.php", ['sql' => 'SELECT 1; DROP TABLE usuarios'], $cookieAdmin)['code'] === 400);
$dup = $nuevo; $dup['usuario'] = 'admin';
prueba('usuario duplicado -> error', strpos(peticion("$base/agrUsuario.php", $dup, $cookieAdmin)['loc'], '?error') !== false);
$body = peticion("$base/detalleUsuario.php?no=1", null, $cookieAdmin)['body'];
prueba('detalleUsuario no expone claves ni hashes', strpos($body, '$2y$') === false && strpos($body, 'value="123"') === false);

// 4b. Consultas preparadas en agr* y detalle*
foreach (['detalleCategoria.php?no=1', 'detalleProveedor.php?no=1', 'detalleProyecto.php?no=1'] as $p) {
    $r = peticion("$base/$p", null, $cookieAdmin);
    prueba("$p responde 200 sin errores PHP", $r['code'] === 200 && !preg_match('/Fatal error|Warning:|Notice:/', $r['body']));
}
$r = peticion("$base/detalleProveedor.php?no=" . urlencode("1' OR '1'='1"), null, $cookieAdmin);
prueba("detalleProveedor con ?no= inyectado no falla ni filtra", $r['code'] === 200 && !preg_match('/Fatal error|SQL syntax/', $r['body']));
$r = peticion("$base/agrCategoria.php", ['cat' => "Test'); DROP TABLE categoria;--"], $cookieAdmin);
prueba('agrCategoria guarda texto con comillas literalmente', strpos($r['loc'], '?ok') !== false);
prueba('categorias.php sigue funcionando tras el intento de inyeccion', peticion("$base/categorias.php", null, $cookieAdmin)['code'] === 200
    && strpos(peticion("$base/categorias.php", null, $cookieAdmin)['body'], 'Ternero') !== false);
prueba('agrCategoria duplicada -> error', strpos(peticion("$base/agrCategoria.php", ['cat' => 'Ternero'], $cookieAdmin)['loc'], '?error') !== false);
prueba('agrCategoria sin sesion no inserta', strpos(peticion("$base/agrCategoria.php", ['cat' => 'Hack']) ['loc'], 'login.php') !== false);
$r = peticion("$base/agrProyecto.php", ['btn1' => '1', 'cmbparcela1' => 1, 'nombre1' => "Proy'; DROP TABLE proyectos;--", 'cmbTipoProyecto1' => 2, 'cantHas1' => 5, 'cmbEstado1' => 1], $cookieAdmin);
prueba('agrProyecto guarda nombre con comillas', strpos($r['loc'], 'altaProyectos.php?ok') !== false);
prueba('datosCarga responde con parcela valida', strpos(peticion("$base/datosCarga.php", ['cmbparcela1' => '7'], $cookieAdmin)['body'], 'Hectareas') !== false);
prueba('datosCarga ignora parcela inyectada', strpos(peticion("$base/datosCarga.php", ['cmbparcela1' => "1 OR 1=1"], $cookieAdmin)['body'], 'Fatal error') === false);

// 5. Subida de PDF
$tmp = tempnam(sys_get_temp_dir(), 'pdf');
file_put_contents($tmp, "%PDF-1.4\n%%EOF");
$txt = tempnam(sys_get_temp_dir(), 'txt');
file_put_contents($txt, 'no soy pdf');
peticion("$base/cargaPdf.php", ['nroFactura' => 'testpdf1'], $cookieAdmin, ['fichero' => new CURLFile($tmp, 'application/pdf', 'f.pdf')]);
prueba('PDF valido se guarda', file_exists("$raiz/archivos/testpdf1.pdf"));
peticion("$base/cargaPdf.php", ['nroFactura' => 'testtxt1'], $cookieAdmin, ['fichero' => new CURLFile($txt, 'text/plain', 'f.pdf')]);
prueba('archivo no-PDF rechazado', !file_exists("$raiz/archivos/testtxt1.pdf"));
peticion("$base/cargaPdf.php", ['nroFactura' => '../../evil'], $cookieAdmin, ['fichero' => new CURLFile($tmp, 'application/pdf', 'f.pdf')]);
prueba('path traversal bloqueado', !file_exists(dirname($raiz) . '/evil.pdf'));
@unlink("$raiz/archivos/testpdf1.pdf");
@unlink("$raiz/archivos/evil.pdf");

echo "\n" . ($fallos === 0 ? "TODAS LAS PRUEBAS OK" : "$fallos PRUEBA(S) FALLARON") . "\n";
exit($fallos === 0 ? 0 : 1);
