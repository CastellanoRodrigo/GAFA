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
    return ['code' => $code, 'loc' => $loc[1] ?? '', 'cookie' => $ck[1] ?? '', 'body' => substr($r, $hs), 'headers' => $h];
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

$dup = $nuevo; $dup['usuario'] = 'admin';
prueba('usuario duplicado -> error', strpos(peticion("$base/agrUsuario.php", $dup, $cookieAdmin)['loc'], '?error') !== false);
$body = peticion("$base/detalleUsuario.php?no=1", null, $cookieAdmin)['body'];
prueba('detalleUsuario no expone claves ni hashes', strpos($body, '$2y$') === false && strpos($body, 'value="123"') === false);

// 4a. Compras (requiere tests/esquema_compras.sql aplicado a la base de prueba)
$r = peticion("$base/verCompras.php", null, $cookieAdmin);
prueba('verCompras responde 200 sin errores PHP', $r['code'] === 200 && !preg_match('/Fatal error|Warning:|Notice:/', $r['body']));
prueba('verCompras lista las compras cargadas', strpos($r['body'], 'Cereales') !== false && strpos($r['body'], 'Fedea') !== false);
$r = peticion("$base/verCompras.php?proveedor=1", null, $cookieAdmin);
// Se mira solo la tabla: el desplegable de proveedores siempre lista todos los nombres
$tabla = function (string $html): string { return preg_match('~<tbody>(.*?)</tbody>~s', $html, $m) ? $m[1] : ''; };
prueba('verCompras filtra por proveedor', substr_count($tabla($r['body']), '<tr>') === 2 && strpos($tabla($r['body']), 'Cereales') !== false && strpos($tabla($r['body']), 'Fedea') === false);
$r = peticion("$base/verCompras.php?desde=2023-06-01&hasta=2023-06-30", null, $cookieAdmin);
prueba('verCompras filtra por rango de fechas', strpos($r['body'], '04/06/2023') !== false && strpos($r['body'], '06/11/2022') === false);
$r = peticion("$base/verCompras.php?q=" . urlencode("' OR '1'='1"), null, $cookieAdmin);
prueba('verCompras con busqueda inyectada no falla ni lista todo', $r['code'] === 200 && !preg_match('/Fatal error|SQL syntax/', $r['body']) && substr_count($tabla($r['body']), '<tr>') === 0);
$r = peticion("$base/detalleCompra.php?no=1", null, $cookieAdmin);
prueba('detalleCompra responde sin errores PHP', $r['code'] === 200 && !preg_match('/Fatal error|Warning:|Notice:/', $r['body']));
$r = peticion("$base/Exportar/ExcelCompras.php", null, $cookieAdmin);
prueba('exportar compras genera Excel con datos', $r['code'] === 200 && strpos($r['body'], 'Cereales') !== false);
$r = peticion("$base/Exportar/ExcelCompras.php?proveedor=1", null, $cookieAdmin);
prueba('exportar compras respeta los filtros', strpos($r['body'], 'Cereales') !== false && strpos($r['body'], 'Fedea') === false);
$r = peticion("$base/Exportar/ExcelCompras.php", ['sql' => 'DROP TABLE usuarios'], $cookieAdmin);
prueba('exportacion ignora SQL enviado por el cliente', $r['code'] === 200 && strpos(peticion("$base/usuarios.php", null, $cookieAdmin)['body'], 'admin') !== false);
prueba('verCompras sin sesion redirige a login', strpos(peticion("$base/verCompras.php")['loc'], 'login.php') !== false);

// 4a-2. Asignar compras
$r = peticion("$base/asignarCompra.php", null, $cookieAdmin);
prueba('asignarCompra responde 200 sin errores PHP', $r['code'] === 200 && !preg_match('/Fatal error|Warning:|Notice:/', $r['body']));
prueba('asignarCompra lista la compra general pendiente', strpos($tabla($r['body']), '10.101.010,00') !== false);
prueba('asignarCompra sin sesion redirige a login', strpos(peticion("$base/asignarCompra.php", ['compra' => 2, 'proyecto' => 5])['loc'], 'login.php') !== false);
prueba('asignar a proyecto inexistente -> error', strpos(peticion("$base/asignarCompra.php", ['compra' => 2, 'proyecto' => 9999], $cookieAdmin)['loc'], '?error') !== false);
prueba('asignar compra inexistente -> error', strpos(peticion("$base/asignarCompra.php", ['compra' => 9999, 'proyecto' => 5], $cookieAdmin)['loc'], '?error') !== false);
prueba('asignar compra a proyecto -> ok', strpos(peticion("$base/asignarCompra.php", ['compra' => 2, 'proyecto' => 5], $cookieAdmin)['loc'], '?ok') !== false);
prueba('verCompras muestra el proyecto asignado', strpos($tabla(peticion("$base/verCompras.php", null, $cookieAdmin)['body']), 'Soja 2023') !== false);
prueba('la compra asignada sale de la lista de pendientes', strpos($tabla(peticion("$base/asignarCompra.php", null, $cookieAdmin)['body']), '10.101.010,00') === false);
prueba('no se puede reasignar una compra ya asignada', strpos(peticion("$base/asignarCompra.php", ['compra' => 2, 'proyecto' => 4], $cookieAdmin)['loc'], '?error') !== false);
prueba('asignar con valores inyectados no rompe', strpos(peticion("$base/asignarCompra.php", ['compra' => "3 OR 1=1", 'proyecto' => "5; DROP TABLE compras"], $cookieAdmin)['loc'], 'asignarCompra.php') !== false
    && strpos(peticion("$base/verCompras.php", null, $cookieAdmin)['body'], 'Fedea') !== false);

// 4a-3. Proyectos: listado con filtros y exportacion
$r = peticion("$base/todosLosProyectos.php", null, $cookieAdmin);
prueba('todosLosProyectos responde sin errores PHP', $r['code'] === 200 && !preg_match('/Fatal error|Warning:|Notice:/', $r['body']) && substr_count($tabla($r['body']), '<tr>') === 7);
$r = peticion("$base/todosLosProyectos.php?tipo=1", null, $cookieAdmin);
prueba('todosLosProyectos filtra por tipo', substr_count($tabla($r['body']), '<tr>') === 3 && strpos($tabla($r['body']), 'Soja 2023') === false);
$r = peticion("$base/todosLosProyectos.php?q=" . urlencode("' OR '1'='1"), null, $cookieAdmin);
prueba('todosLosProyectos con busqueda inyectada no lista todo', $r['code'] === 200 && substr_count($tabla($r['body']), '<tr>') === 0);
$r = peticion("$base/Exportar/ExcelProyectos.php", null, $cookieAdmin);
prueba('exportar proyectos genera Excel con datos y sin fechas 1970', $r['code'] === 200 && strpos($r['body'], 'Soja 2023') !== false && strpos($r['body'], '1970') === false);
$r = peticion("$base/Exportar/ExcelProyectos.php?tipo=1", null, $cookieAdmin);
prueba('exportar proyectos respeta los filtros', strpos($r['body'], 'Aberdinangus') !== false && strpos($r['body'], 'Soja 2023') === false);
$r = peticion("$base/Exportar/ExcelProyectosFiltrado.php", ['sql' => 'DROP TABLE usuarios', 'tipo' => 1], $cookieAdmin);
prueba('exportar proyectos filtrado ignora SQL del cliente', $r['code'] === 200 && strpos($r['body'], 'Aberdinangus') !== false
    && strpos(peticion("$base/usuarios.php", null, $cookieAdmin)['body'], 'admin') !== false);

// 4a-4. Moneda: cada operacion muestra ARS/USD y los totales no mezclan monedas
$r = peticion("$base/verCompras.php", null, $cookieAdmin);
prueba('verCompras marca cada compra con su moneda (ARS y USD)', strpos($tabla($r['body']), 'moneda-usd') !== false && strpos($tabla($r['body']), 'moneda-ars') !== false);
prueba('verCompras muestra importes con simbolo (US$ y $)', strpos($tabla($r['body']), 'US$ 1.785,00') !== false && strpos($tabla($r['body']), '$ 10.101.010,00') !== false);
prueba('verCompras: total en dolares separado (US$ 2.875,00)', strpos($r['body'], 'Total en dólares') !== false && strpos($r['body'], 'US$ 2.875,00') !== false);
prueba('verCompras: total en pesos separado ($ 29.691.049,00)', strpos($r['body'], 'Total en pesos') !== false && strpos($r['body'], '$ 29.691.049,00') !== false);
$r = peticion("$base/detalleCompra.php?no=1", null, $cookieAdmin);
prueba('detalleCompra muestra importe en dolares con su etiqueta', strpos($r['body'], 'US$ 1.785,00') !== false && strpos($r['body'], 'moneda-usd') !== false);
$r = peticion("$base/Exportar/ExcelCompras.php", null, $cookieAdmin);
prueba('Excel de compras trae totales por moneda', strpos($r['body'], 'Total en dólares (USD)') !== false && strpos($r['body'], 'US$ 2.875,00') !== false && strpos($r['body'], 'Total en pesos (ARS)') !== false);
$r = peticion("$base/todosLosProyectos.php", null, $cookieAdmin);
prueba('todosLosProyectos separa la inversion por moneda', strpos($r['body'], 'Inversión inicial en dólares') !== false && strpos($r['body'], 'Inversión inicial en pesos') !== false && strpos($tabla($r['body']), 'moneda-usd') !== false);
$r = peticion("$base/Exportar/ExcelProyectos.php", null, $cookieAdmin);
prueba('Excel de proyectos trae inversion total por moneda', strpos($r['body'], 'Inversión total en dólares (USD)') !== false && strpos($r['body'], 'Inversión total en pesos (ARS)') !== false);
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

// 4c. Alquiler de parcelas (requiere migracion_alquiler.sql aplicada a la base de prueba)
require_once $raiz . '/Modelo/Alquileres.php';
prueba('cuotas mensuales de un anio calendario = 12', count(generarVencimientos('2030-01-01', '2030-12-31', 1)) === 12);
prueba('cuotas trimestrales = 4', count(generarVencimientos('2030-01-01', '2030-12-31', 3)) === 4);
prueba('cuota anual de contrato de un anio = 1 (no hay cuota el ultimo dia)', count(generarVencimientos('2030-01-01', '2031-01-01', 12)) === 1);
prueba('pago unico = 1 cuota al inicio', generarVencimientos('2030-03-15', '2031-03-14', 0) === ['2030-03-15']);
prueba('31/01 + 1 mes = 28/02 sin desbordar', sumarMeses(new DateTime('2031-01-31'), 1)->format('Y-m-d') === '2031-02-28');
prueba('el dia 31 se conserva en los meses largos (cuota 3 de 31/01 mensual = 31/03)', generarVencimientos('2031-01-31', '2031-12-31', 1)[2] === '2031-03-31');
prueba('estado Vigente', estadoContrato('2030-01-01', '2030-12-31', '2030-03-01') === 'Vigente');
prueba('estado Por vencer (a 30 dias del fin)', estadoContrato('2030-01-01', '2030-12-31', '2030-12-01') === 'Por vencer');
prueba('estado Vencido', estadoContrato('2030-01-01', '2030-12-31', '2031-01-02') === 'Vencido');
prueba('estado Futuro', estadoContrato('2030-01-01', '2030-12-31', '2029-12-31') === 'Futuro');
prueba('montoMoneda distingue pesos y dolares', montoMoneda(1500, 'ARS') === '$ 1.500,00' && montoMoneda(1500, 'USD') === 'US$ 1.500,00');

$r = peticion("$base/alquileres.php", null, $cookieAdmin);
prueba('alquileres.php responde 200 sin errores PHP', $r['code'] === 200 && !preg_match('/Fatal error|Warning:|Notice:/', $r['body']));
prueba('alquileres sin sesion redirige a login', strpos(peticion("$base/alquileres.php")['loc'], 'login.php') !== false);
$r2 = peticion("$base/detalleAlquiler.php", null, $cookieAdmin);
prueba('detalleAlquiler responde 200 sin errores PHP', $r2['code'] === 200 && !preg_match('/Fatal error|Warning:|Notice:/', $r2['body']));

// Proyectos de alquiler (tipo 3, estado Iniciado)
$crearAlq = function (string $nombre) use ($base, $cookieAdmin): int {
    peticion("$base/agrProyecto.php", ['btn1' => '1', 'cmbparcela1' => 3, 'nombre1' => $nombre, 'cmbTipoProyecto1' => 3, 'cantHas1' => 20, 'cmbEstado1' => 2], $cookieAdmin);
    $r = peticion("$base/todosLosProyectos.php?q=" . urlencode($nombre), null, $cookieAdmin);
    return preg_match('~detalleProyecto\.php\?no=(\d+)~', $r['body'], $m) ? (int)$m[1] : 0;
};
$pVig = $crearAlq('Alq Vigente'); $pPor = $crearAlq('Alq PorVencer'); $pVen = $crearAlq('Alq Vencido'); $pDup = $crearAlq('Alq Extra');
prueba('se crearon 4 proyectos de alquiler', $pVig > 0 && $pPor > 0 && $pVen > 0 && $pDup > 0);
$r = peticion("$base/detalleAlquiler.php", null, $cookieAdmin);
prueba('detalleAlquiler lista solo proyectos de alquiler sin contrato', strpos($r['body'], 'Alq Vigente') !== false && strpos($r['body'], 'Soja 2023') === false);

$hoy = date('Y-m-d');
$contrato = function (int $proy, string $inq, string $ini, string $fin, string $per, $canon, int $mon) {
    return ['cmbNombre' => $proy, 'inquilino' => $inq, 'contacto' => '11-5555', 'fechaInicio' => $ini, 'fechaFin' => $fin,
            'periodicidad' => $per, 'canon' => $canon, 'cmbMoneda' => $mon, 'tipoCambio' => '', 'observaciones' => ''];
};
$post = function (array $d) use ($base, $cookieAdmin) { return peticion("$base/agrDetalleAlquiler.php", $d, $cookieAdmin); };
prueba('contrato con fin anterior al inicio -> error fechas', strpos($post($contrato($pDup, 'X', '2030-05-01', '2030-01-01', 'Anual', 100, 1))['loc'], 'error=fechas') !== false);
prueba('contrato con canon 0 -> error canon', strpos($post($contrato($pDup, 'X', '2030-01-01', '2030-12-31', 'Anual', 0, 1))['loc'], 'error=canon') !== false);
prueba('contrato sin inquilino -> error datos', strpos($post($contrato($pDup, '  ', '2030-01-01', '2030-12-31', 'Anual', 100, 1))['loc'], 'error=datos') !== false);
prueba('periodicidad inventada -> error datos', strpos($post($contrato($pDup, 'X', '2030-01-01', '2030-12-31', 'Cada tanto', 100, 1))['loc'], 'error=datos') !== false);
prueba('contrato sobre proyecto que no es de alquiler -> error proyecto', strpos($post($contrato(1, 'X', '2030-01-01', '2030-12-31', 'Anual', 100, 1))['loc'], 'error=proyecto') !== false);
prueba('agrDetalleAlquiler sin sesion redirige a login', strpos(peticion("$base/agrDetalleAlquiler.php", $contrato($pDup, 'X', '2030-01-01', '2030-12-31', 'Anual', 100, 1))['loc'], 'login.php') !== false);

$iniVig = date('Y-m-d', strtotime('first day of -2 months'));
$finVig = date('Y-m-d', strtotime('+10 months'));
$rVig = $post($contrato($pVig, "Pérez e Hijos S.A.", $iniVig, $finVig, 'Mensual', 1000, 1));
prueba('contrato vigente en pesos se guarda', preg_match('~cobrosAlquiler\.php\?no=(\d+)&ok~', $rVig['loc'], $mVig) === 1);
$idVig = (int)($mVig[1] ?? 0);
prueba('segundo contrato sobre el mismo proyecto -> error duplicado', strpos($post($contrato($pVig, 'Otro', '2030-01-01', '2030-12-31', 'Anual', 100, 1))['loc'], 'error=duplicado') !== false);
$rPor = $post($contrato($pPor, 'Campos del Sur', date('Y-m-d', strtotime('-11 months')), date('Y-m-d', strtotime('+30 days')), 'Semestral', 2500, 2));
prueba('contrato por vencer en dolares se guarda', preg_match('~cobrosAlquiler\.php\?no=(\d+)&ok~', $rPor['loc'], $mPor) === 1);
$rVen = $post($contrato($pVen, 'Viejo Inquilino', date('Y-m-d', strtotime('-14 months')), date('Y-m-d', strtotime('-2 months')), 'Mensual', 300, 2));
prueba('contrato vencido en dolares se guarda', preg_match('~cobrosAlquiler\.php\?no=(\d+)&ok~', $rVen['loc'], $mVen) === 1);
$idPor = (int)($mPor[1] ?? 0); $idVen = (int)($mVen[1] ?? 0);

$r = peticion("$base/cobrosAlquiler.php?no=$idVig", null, $cookieAdmin);
$esperadas = count(generarVencimientos($iniVig, $finVig, 1));
prueba("el cronograma mensual genera $esperadas cuotas", $r['code'] === 200 && substr_count($tabla($r['body']), '<tr>') === $esperadas);
prueba('cobros del contrato en pesos muestra $ y etiqueta ARS', strpos($tabla($r['body']), '$ 1.000,00') !== false && strpos($r['body'], 'moneda-ars') !== false && strpos($r['body'], 'US$') === false);
$r = peticion("$base/cobrosAlquiler.php?no=$idVen", null, $cookieAdmin);
prueba('cobros del contrato en dolares muestra US$ y etiqueta USD', strpos($tabla($r['body']), 'US$ 300,00') !== false && strpos($r['body'], 'moneda-usd') !== false);
prueba('las cuotas atrasadas figuran como Vencida', strpos($tabla($r['body']), 'Vencida') !== false);

$fila = function (string $html, string $nombre): string {
    foreach (explode('<tr>', $html) as $seg) if (strpos($seg, $nombre) !== false) return $seg;
    return '';
};
$r = peticion("$base/alquileres.php", null, $cookieAdmin);
prueba('alquileres marca el estado Vigente', strpos($fila($tabla($r['body']), 'Alq Vigente'), 'Vigente') !== false);
prueba('alquileres marca el estado Por vencer', strpos($fila($tabla($r['body']), 'Alq PorVencer'), 'Por vencer') !== false);
prueba('alquileres marca el estado Vencido con deuda en rojo', strpos($fila($tabla($r['body']), 'Alq Vencido'), 'texto-mal') !== false);
prueba('alquileres separa lo por cobrar en pesos y en dolares', strpos($r['body'], 'Por cobrar en pesos') !== false && strpos($r['body'], 'Por cobrar en dólares') !== false);
prueba('alquileres muestra la moneda de cada canon', strpos($fila($tabla($r['body']), 'Alq Vigente'), 'moneda-ars') !== false && strpos($fila($tabla($r['body']), 'Alq PorVencer'), 'moneda-usd') !== false);
$r = peticion("$base/alquileres.php?estado=Vencido", null, $cookieAdmin);
prueba('filtro por estado Vencido', strpos($tabla($r['body']), 'Alq Vencido') !== false && strpos($tabla($r['body']), 'Alq Vigente') === false);
$r = peticion("$base/alquileres.php?q=" . urlencode("' OR '1'='1"), null, $cookieAdmin);
prueba('alquileres con busqueda inyectada no lista nada', $r['code'] === 200 && substr_count($tabla($r['body']), '<tr>') === 0);

// Registrar y deshacer un cobro
$r = peticion("$base/cobrosAlquiler.php?no=$idVig", null, $cookieAdmin);
preg_match('~name="cobro" value="(\d+)"~', $r['body'], $mc);
$idCobro = (int)($mc[1] ?? 0);
prueba('registrarCobro sin sesion redirige a login', strpos(peticion("$base/registrarCobro.php", ['cobro' => $idCobro, 'accion' => 'cobrar'])['loc'], 'login.php') !== false);
prueba('registrarCobro con cobro inexistente -> error', strpos(peticion("$base/registrarCobro.php", ['cobro' => 999999, 'accion' => 'cobrar'], $cookieAdmin)['loc'], 'error') !== false);
$rc = peticion("$base/registrarCobro.php", ['cobro' => $idCobro, 'accion' => 'cobrar', 'fechaPago' => $hoy], $cookieAdmin);
prueba('registrar cobro redirige al contrato', strpos($rc['loc'], "cobrosAlquiler.php?no=$idVig") !== false);
$r = peticion("$base/cobrosAlquiler.php?no=$idVig", null, $cookieAdmin);
prueba('la cuota queda como Cobrada', substr_count($tabla($r['body']), 'Cobrado') === 1);
peticion("$base/registrarCobro.php", ['cobro' => $idCobro, 'accion' => 'deshacer'], $cookieAdmin);
$r = peticion("$base/cobrosAlquiler.php?no=$idVig", null, $cookieAdmin);
prueba('deshacer cobro deja la cuota sin cobrar', substr_count($tabla($r['body']), 'Cobrado') === 0);

// Integracion con proyectos y exportacion
$r = peticion("$base/todosLosProyectos.php?tipo=3", null, $cookieAdmin);
prueba('todosLosProyectos ofrece el enlace a cobros del contrato', strpos($fila($tabla($r['body']), 'Alq Vigente'), "cobrosAlquiler.php?no=$idVig") !== false);
prueba('proyecto de alquiler sin contrato ofrece cargarlo', strpos($fila($tabla($r['body']), 'Alq Extra'), 'detalleAlquiler.php') !== false);
$r = peticion("$base/detalleProyecto.php?no=$pVig", null, $cookieAdmin);
prueba('detalleProyecto de alquiler muestra el contrato sin errores', $r['code'] === 200 && strpos($r['body'], 'Contrato de alquiler') !== false && strpos($r['body'], 'Pérez e Hijos') !== false && !preg_match('/Fatal error|Warning:/', $r['body']));
$r = peticion("$base/Exportar/ExcelAlquileres.php", null, $cookieAdmin);
prueba('Excel de alquileres trae contratos y totales por moneda', $r['code'] === 200 && strpos($r['body'], 'Alq Vigente') !== false && strpos($r['body'], 'Totales en dólares (USD)') !== false && strpos($r['body'], 'Totales en pesos (ARS)') !== false);
$r = peticion("$base/Exportar/ExcelAlquileres.php?estado=Vencido", null, $cookieAdmin);
prueba('Excel de alquileres respeta el filtro de estado', strpos($r['body'], 'Alq Vencido') !== false && strpos($r['body'], 'Alq Vigente') === false);
prueba('Excel de alquileres sin sesion -> 403', peticion("$base/Exportar/ExcelAlquileres.php")['code'] === 403);
$r = peticion("$base/Exportar/ExcelProyectos.php?tipo=3", null, $cookieAdmin);
prueba('Excel de proyectos incluye inquilino y canon del alquiler', strpos($r['body'], 'Campos del Sur') !== false && strpos($r['body'], 'US$ 2.500,00 (Semestral)') !== false);
// 4d. Avisos de vencimientos y pagos por mail y WhatsApp (requiere migracion_notificaciones.sql)
require_once $raiz . '/Modelo/Notificaciones.php';
prueba('telefono AR "3492 15 123456" -> 5493492123456', normalizarTelefono('3492 15 123456') === '5493492123456');
prueba('telefono "+54 9 3492 123456" -> 5493492123456', normalizarTelefono('+54 9 3492 123456') === '5493492123456');
prueba('telefono BA "011 15 5555-1234" -> 5491155551234', normalizarTelefono('011 15 5555-1234') === '5491155551234');
prueba('telefono corto no es valido', !telefonoValido(normalizarTelefono('123')));
prueba('enlace de WhatsApp lleva el mensaje codificado', enlaceWhatsApp('5493492123456', 'Hola Ñandú') === 'https://wa.me/5493492123456?text=Hola%20%C3%91and%C3%BA');

$outbox = $raiz . '/storage/outbox';
$contarMails = function () use ($outbox): int { return count(glob($outbox . '/*.eml') ?: []); };
$leerMails = function () use ($outbox): array {
    $r = [];
    foreach (glob($outbox . '/*.eml') ?: [] as $f) {
        $txt = file_get_contents($f);
        [$cab, $cuerpo] = array_pad(explode("\r\n\r\n", $txt, 2), 2, '');
        preg_match('/^To: (.*)$/m', $cab, $to);
        $r[] = ['to' => trim($to[1] ?? ''), 'texto' => base64_decode($cuerpo)];
    }
    return $r;
};
foreach (glob($outbox . '/*.eml') ?: [] as $viejo) @unlink($viejo);

// Contacto del inquilino
$guardar = function (int $id, string $mail, string $tel, ?string $cookie = null) use ($base, $cookieAdmin) {
    return peticion("$base/guardarContacto.php", ['contrato' => $id, 'email' => $mail, 'telefono' => $tel], $cookie ?? $cookieAdmin);
};
prueba('guardarContacto con mail invalido -> error', strpos($guardar($idVen, 'esto-no-es-un-mail', '')['loc'], 'errorContacto=email') !== false);
prueba('guardarContacto con telefono invalido -> error', strpos($guardar($idVen, '', '123')['loc'], 'errorContacto=telefono') !== false);
prueba('guardarContacto sin sesion redirige a login', strpos(peticion("$base/guardarContacto.php", ['contrato' => $idVen, 'email' => 'a@b.com'])['loc'], 'login.php') !== false);
prueba('guardarContacto valido -> ok', strpos($guardar($idVen, 'inquilino@ejemplo.com', '3492 15 123456')['loc'], '&ok') !== false);
prueba('el contrato muestra el mail guardado', strpos(peticion("$base/cobrosAlquiler.php?no=$idVen", null, $cookieAdmin)['body'], 'value="inquilino@ejemplo.com"') !== false);

// Pantalla de avisos
$r = peticion("$base/avisos.php", null, $cookieAdmin);
prueba('avisos.php responde 200 sin errores PHP', $r['code'] === 200 && !preg_match('/Fatal error|Warning:|Notice:/', $r['body']));
prueba('avisos sin sesion redirige a login', strpos(peticion("$base/avisos.php")['loc'], 'login.php') !== false);
$filaVen = $fila($tabla($r['body']), 'Viejo Inquilino');
prueba('avisos agrupa las 12 cuotas vencidas en un solo aviso', substr_count($tabla($r['body']), 'Viejo Inquilino') === 1 && strpos($filaVen, '12 cuotas vencidas') !== false);
prueba('avisos muestra el total adeudado en su moneda (US$ 3.600,00)', strpos($filaVen, 'US$ 3.600,00') !== false);
prueba('avisos muestra mail y WhatsApp normalizado del inquilino', strpos($filaVen, 'inquilino@ejemplo.com') !== false && strpos($filaVen, '+5493492123456') !== false);
prueba('avisos marca "Sin mail" cuando falta el contacto', strpos($fila($tabla($r['body']), 'Pérez e Hijos'), 'Sin mail') !== false || strpos($tabla($r['body']), 'Pérez e Hijos') === false);
prueba('avisos incluye el contrato por vencer', strpos($tabla($r['body']), 'Contrato termina en') !== false);
preg_match('~name="tipo" value="([a-z_]+)".*?name="ref" value="(\d+)"~s', $filaVen, $mAv);
$tipoVen = $mAv[1] ?? ''; $refVen = (int)($mAv[2] ?? 0);
prueba('el aviso de cuotas vencidas es por contrato', $tipoVen === 'cuotas_vencidas' && $refVen === $idVen);

$enviar = function (array $d, ?string $cookie = null) use ($base, $cookieAdmin) { return peticion("$base/enviarAviso.php", $d, $cookie ?? $cookieAdmin); };

// Seguridad
prueba('enviarAviso sin sesion redirige a login', strpos(peticion("$base/enviarAviso.php", ['accion' => 'todos'])['loc'], 'login.php') !== false);
$lector = login($base, 'tu turrito', '1234');
prueba('un usuario Lector no puede enviar avisos (403)', $lector['cookie'] !== '' && $enviar(['accion' => 'todos'], $lector['cookie'])['code'] === 403);
prueba('enviarAviso con aviso inexistente -> error', strpos($enviar(['accion' => 'uno', 'tipo' => 'cuotas_vencidas', 'ref' => 999999, 'canal' => 'email'])['loc'], 'avisos.php?error') !== false);
prueba('enviarAviso con canal inventado -> error', strpos($enviar(['accion' => 'uno', 'tipo' => $tipoVen, 'ref' => $refVen, 'canal' => 'paloma'])['loc'], 'avisos.php?error') !== false);

// Envio por mail (modo prueba: guarda el .eml en storage/outbox)
$antes = $contarMails();
$r = $enviar(['accion' => 'uno', 'tipo' => $tipoVen, 'ref' => $refVen, 'canal' => 'email']);
prueba('enviar aviso por mail -> ok', strpos($r['loc'], 'avisos.php?ok') !== false && $contarMails() === $antes + 1);
$mails = $leerMails();
$ultimo = end($mails) ?: ['to' => '', 'texto' => ''];
prueba('el mail va al inquilino', $ultimo['to'] === 'inquilino@ejemplo.com');
prueba('el mail trae cuotas, total en dolares y empresa', strpos($ultimo['texto'], 'Viejo Inquilino') !== false && strpos($ultimo['texto'], '12 cuotas') !== false
    && strpos($ultimo['texto'], 'US$ 3.600,00') !== false && strpos($ultimo['texto'], 'Don Juan S.R.L') !== false);
$r = peticion("$base/avisos.php", null, $cookieAdmin);
prueba('avisos marca con tilde lo ya enviado por mail', strpos($fila($tabla($r['body']), 'Viejo Inquilino'), 'Mail ✓') !== false);

// Envio masivo: no repite lo ya avisado
$antes = $contarMails();
$enviar(['accion' => 'todos']);
prueba('enviar todos no repite un aviso reciente', $contarMails() === $antes);
$guardar($idPor, 'camposur@ejemplo.com', '');
$antes = $contarMails();
$r = $enviar(['accion' => 'todos']);
prueba('enviar todos manda los avisos nuevos (2 al segundo inquilino)', strpos($r['loc'], 'avisos.php?ok') !== false && $contarMails() === $antes + 2);
$aCampo = array_filter($leerMails(), function ($m) { return $m['to'] === 'camposur@ejemplo.com'; });
prueba('los mails nuevos van solo al inquilino con mail cargado', count($aCampo) === 2);
$antes = $contarMails();
$enviar(['accion' => 'todos']);
prueba('un segundo envio masivo no manda nada', $contarMails() === $antes);

// WhatsApp (modo enlace)
$r = $enviar(['accion' => 'uno', 'tipo' => $tipoVen, 'ref' => $refVen, 'canal' => 'whatsapp']);
prueba('WhatsApp abre wa.me con el numero normalizado', strpos($r['loc'], 'https://wa.me/5493492123456?text=') === 0);
prueba('el texto de WhatsApp lleva el nombre y el total', strpos(urldecode($r['loc']), 'Viejo Inquilino') !== false && strpos(urldecode($r['loc']), 'US$ 3.600,00') !== false);
$r = peticion("$base/avisos.php", null, $cookieAdmin);
prueba('avisos marca con tilde lo ya enviado por WhatsApp', strpos($fila($tabla($r['body']), 'Viejo Inquilino'), 'WhatsApp ✓') !== false);
$filaPor = $fila($tabla($r['body']), 'Campos del Sur');
preg_match('~name="tipo" value="([a-z_]+)".*?name="ref" value="(\d+)"~s', $filaPor, $mPor2);
prueba('WhatsApp sin telefono cargado -> error', strpos($enviar(['accion' => 'uno', 'tipo' => $mPor2[1] ?? 'x', 'ref' => (int)($mPor2[2] ?? 0), 'canal' => 'whatsapp'])['loc'], 'avisos.php?error') !== false);
prueba('resumen interno sin destinatario configurado -> error claro', strpos(urldecode($enviar(['accion' => 'resumen'])['loc']), 'aviso_interno_email') !== false);

// Tarea diaria por consola
prueba('el script de avisos diarios no se ejecuta por web (403)', peticion("$base/cron/enviar_avisos.php")['code'] === 403);
$antes = $contarMails();
$salidaCron = [];
exec('"' . PHP_BINARY . '" -c ' . escapeshellarg((string)php_ini_loaded_file()) . ' ' . escapeshellarg($raiz . '/cron/enviar_avisos.php') . ' 2>&1', $salidaCron, $rcCron);
prueba('el script diario termina bien y resume lo hecho', $rcCron === 0 && strpos(implode("\n", $salidaCron), 'avisos:') !== false);
prueba('el script diario tampoco repite avisos recientes', $contarMails() === $antes);

// Envio SMTP real contra un servidor de mentira
$puerto = random_int(20000, 40000);
$salidaSmtp = tempnam(sys_get_temp_dir(), 'smtp');
$proc = proc_open([PHP_BINARY, '-c', (string)php_ini_loaded_file(), $raiz . '/tests/fake_smtp.php', (string)$puerto, $salidaSmtp], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
usleep(1200000);
$cfgSmtp = configNotificaciones();
$cfgSmtp['email'] = array_merge($cfgSmtp['email'], ['driver' => 'smtp', 'host' => '127.0.0.1', 'puerto' => $puerto, 'seguridad' => 'none',
    'usuario' => 'avisos', 'clave' => 's3cr3t', 'remitente' => 'avisos@ejemplo.com', 'nombre_remitente' => 'Don Juan S.R.L']);
[$okSmtp, $errSmtp] = enviarEmail($cfgSmtp, 'inquilino@ejemplo.com', 'Cuota vencida - ñandú', "Hola.\n.\nLínea que empieza con punto y acentos: cuota vencida.");
proc_close($proc);
$recibido = is_file($salidaSmtp) ? file_get_contents($salidaSmtp) : '';
prueba('SMTP: el envio termina bien' . ($okSmtp ? '' : " ($errSmtp)"), $okSmtp === true);
prueba('SMTP: se autentica con usuario y clave', strpos($recibido, 'USUARIO:avisos') !== false && strpos($recibido, 'CLAVE:s3cr3t') !== false);
prueba('SMTP: remitente y destinatario correctos', strpos($recibido, 'MAIL FROM:<avisos@ejemplo.com>') !== false && strpos($recibido, 'RCPT TO:<inquilino@ejemplo.com>') !== false);
[$cabSmtp, $cuerpoSmtp] = array_pad(explode("\n---\n", $recibido, 2), 2, '');
preg_match('/Subject: =\?UTF-8\?B\?(.*?)\?=/', $cuerpoSmtp, $mSub);
$partesMime = explode("\r\n\r\n", $cuerpoSmtp, 2);
prueba('SMTP: asunto con acentos viaja codificado y se recupera', base64_decode($mSub[1] ?? '') === 'Cuota vencida - ñandú');
prueba('SMTP: el cuerpo llega intacto (incluida una linea que empieza con punto)', strpos(base64_decode($partesMime[1] ?? ''), "Hola.\n.\nLínea que empieza con punto") !== false);
@unlink($salidaSmtp);
$cfgRoto = $cfgSmtp; $cfgRoto['email']['puerto'] = 1;
[$okRoto, $errRoto] = enviarEmail($cfgRoto, 'inquilino@ejemplo.com', 'x', 'y');
prueba('SMTP: un servidor inaccesible devuelve un error claro', $okRoto === false && strpos((string)$errRoto, 'No se pudo conectar') !== false);
[$okMal, ] = enviarEmail($cfgSmtp, 'esto-no-es-un-mail', 'x', 'y');
prueba('mail a una direccion invalida se rechaza antes de enviar', $okMal === false);
foreach (glob($outbox . '/*.eml') ?: [] as $f) @unlink($f);

// 4e. Nombres siempre con mayuscula inicial
$r = peticion("$base/inicio.php", null, $cookieAdmin);
prueba('el saludo muestra "Lucila Dolce" con mayusculas', strpos($r['body'], 'Bienvenido/a, Lucila Dolce') !== false);
$r = peticion("$base/usuarios.php", null, $cookieAdmin);
prueba('usuarios.php muestra nombres con mayuscula inicial', strpos($r['body'], '>Lucila<') !== false && strpos($r['body'], '>Dolce<') !== false && strpos($r['body'], '>lucila<') === false && strpos($r['body'], '>dolce<') === false);
$nuevoUsr = ['usuario' => 'testcap1', 'pass' => 'x1', 'nombre' => 'maría josé', 'apellido' => 'de la cruz', 'tipo' => 2, 'estado' => 1, 'email' => 'testcap1@test.local'];
peticion("$base/agrUsuario.php", $nuevoUsr, $cookieAdmin);
$nuevoUsr2 = ['usuario' => 'testcap2', 'pass' => 'x2', 'nombre' => 'JUAN CARLOS', 'apellido' => "o'brien-pérez", 'tipo' => 2, 'estado' => 1, 'email' => 'testcap2@test.local'];
peticion("$base/agrUsuario.php", $nuevoUsr2, $cookieAdmin);
$r = peticion("$base/usuarios.php", null, $cookieAdmin);
prueba('al crear un usuario el nombre se guarda con mayuscula inicial ("María José De la Cruz")', strpos($r['body'], '>María José<') !== false && strpos($r['body'], '>De la Cruz<') !== false);
prueba('mayusculas respetan apostrofes, guiones y tildes ("Juan Carlos O\'Brien-Pérez")', strpos($r['body'], '>Juan Carlos<') !== false && strpos(html_entity_decode($r['body'], ENT_QUOTES), "O'Brien-Pérez") !== false);
$r = peticion("$base/detalleUsuario.php?no=1", null, $cookieAdmin);
prueba('detalleUsuario muestra el nombre con mayuscula', strpos($r['body'], 'value="Lucila"') !== false && strpos($r['body'], 'value="Dolce"') !== false);
// 4f. Roles: Administrador / Operador / Lector
$op = ['usuario' => 'operador1', 'pass' => 'op1234', 'nombre' => 'ana', 'apellido' => 'gómez', 'tipo' => 3, 'estado' => 1, 'email' => 'operador1@test.local'];
peticion("$base/agrUsuario.php", $op, $cookieAdmin);
$rOp = login($base, 'operador1', 'op1234');
$cookieOp = $rOp['cookie'];
prueba('el operador puede iniciar sesion', $cookieOp !== '');
$cookieLector = $lector['cookie'];
$proy = ['btn1' => '1', 'cmbparcela1' => 3, 'nombre1' => 'Proy Rol', 'cmbTipoProyecto1' => 2, 'cantHas1' => 5, 'cmbEstado1' => 1];
$cuando = function (string $url, array $post, string $cookie) use ($base) { return peticion("$base/$url", $post, $cookie); };
foreach ([
    ['agrProyecto.php', $proy], ['agrParcela.php', ['dimension' => 10]], ['modParcela.php', ['id' => 1, 'dimension' => 10]],
    ['registrarCobro.php', ['cobro' => $idCobro, 'accion' => 'cobrar']], ['guardarContacto.php', ['contrato' => $idVen, 'email' => 'x@y.com']],
    ['agrDetalleAlquiler.php', ['cmbNombre' => $pDup]], ['cargaCompra.php', ['fecha' => '2030-01-01']], ['asignarCompra.php', ['compra' => 3, 'proyecto' => 5]],
    ['modProyecto.php', ['id' => 1, 'estado' => 2]], ['cargaPdf.php', ['nroFactura' => 'x']],
] as [$url, $datos]) {
    prueba("Lector no puede escribir: $url -> 403", $cuando($url, $datos, $cookieLector)['code'] === 403);
}
$r = $cuando('agrProyecto.php', $proy, $cookieLector);
prueba('el rechazo al Lector es una pagina clara, no un error tecnico', strpos($r['body'], 'solo lectura') !== false);
prueba('el Lector puede consultar compras, parcelas y avisos', peticion("$base/verCompras.php", null, $cookieLector)['code'] === 200
    && peticion("$base/parcelas.php", null, $cookieLector)['code'] === 200 && peticion("$base/avisos.php", null, $cookieLector)['code'] === 200
    && peticion("$base/asignarCompra.php", null, $cookieLector)['code'] === 200);
$b1 = peticion("$base/inicio.php", null, $cookieLector)['body'];
prueba('el menu del Lector oculta las opciones de carga y gestion', strpos($b1, 'href="./altaProyectos.php">Alta proyecto') === false && strpos($b1, 'href="./altaCompras.php">Cargar compra') === false
    && strpos($b1, 'href="./usuarios.php">Usuarios') === false && strpos($b1, '>Lector</small>') !== false);
$b2 = peticion("$base/inicio.php", null, $cookieAdmin)['body'];
prueba('el menu del Administrador muestra carga, gestion y su rol', strpos($b2, 'href="./altaProyectos.php">Alta proyecto') !== false && strpos($b2, 'href="./usuarios.php">Usuarios') !== false
    && strpos($b2, 'href="./respaldo.php">Copias de seguridad') !== false && strpos($b2, '>Administrador</small>') !== false);
prueba('el Lector no ve el formulario para agregar parcelas', strpos(peticion("$base/parcelas.php", null, $cookieLector)['body'], 'action="./agrParcela.php"') === false);
// Operador: carga datos pero no administra
prueba('el Operador puede cargar proyectos', strpos($cuando('agrProyecto.php', $proy, $cookieOp)['loc'], 'altaProyectos.php?ok') !== false);
prueba('el Operador puede registrar cobros', strpos($cuando('registrarCobro.php', ['cobro' => $idCobro, 'accion' => 'deshacer'], $cookieOp)['loc'], 'cobrosAlquiler.php') !== false);
prueba('el Operador puede agregar parcelas', strpos($cuando('agrParcela.php', ['dimension' => 77], $cookieOp)['loc'], 'parcelas.php?ok') !== false);
foreach ([['agrUsuario.php', $nuevoUsr2 + []], ['modProveedor.php', ['id' => 1, 'prov' => 'x']], ['agrCategoria.php', ['cat' => 'X']], ['enviarAviso.php', ['accion' => 'todos']],
          ['guardarEmpresa.php', ['nombre' => 'X', 'color' => '#000000']], ['descargarRespaldo.php', ['accion' => 'descargar']]] as [$url, $datos]) {
    prueba("Operador no administra: $url -> 403", $cuando($url, $datos, $cookieOp)['code'] === 403);
}
prueba('el Operador no entra a empresa.php ni respaldo.php', peticion("$base/empresa.php", null, $cookieOp)['code'] === 403 && peticion("$base/respaldo.php", null, $cookieOp)['code'] === 403);
prueba('el Operador no entra a la gestion de usuarios (detalleUsuario)', peticion("$base/detalleUsuario.php?no=1", null, $cookieOp)['code'] === 403);

// 4g. Parcelas
$r = peticion("$base/parcelas.php", null, $cookieAdmin);
prueba('parcelas muestra la parcela agregada (77 has) con libres', $r['code'] === 200 && strpos($r['body'], '>77<') !== false && !preg_match('/Fatal error|Warning:|Notice:/', $r['body']));
preg_match_all('~name="id" value="(\d+)"~', $r['body'], $mp);
$idParcelaNueva = (int)max($mp[1]);
prueba('modificar una parcela actualiza las hectareas', strpos($cuando('modParcela.php', ['id' => $idParcelaNueva, 'dimension' => 88], $cookieAdmin)['loc'], 'parcelas.php?ok') !== false
    && strpos(peticion("$base/parcelas.php", null, $cookieAdmin)['body'], '>88<') !== false);
prueba('parcela con hectareas 0 -> error', strpos($cuando('agrParcela.php', ['dimension' => 0], $cookieAdmin)['loc'], 'parcelas.php?error') !== false);
prueba('parcela con hectareas absurdas -> error', strpos($cuando('agrParcela.php', ['dimension' => 99999999], $cookieAdmin)['loc'], 'parcelas.php?error') !== false);
prueba('parcelas sin sesion redirige a login', strpos(peticion("$base/parcelas.php")['loc'], 'login.php') !== false);

// 4h. Empresa (marca blanca)
$png = tempnam(sys_get_temp_dir(), 'png');
$gd = imagecreatetruecolor(60, 60); imagefill($gd, 0, 0, imagecolorallocate($gd, 200, 30, 30)); imagepng($gd, $png);
$gran = tempnam(sys_get_temp_dir(), 'big'); file_put_contents($gran, str_repeat('A', 1100000));
$noImg = tempnam(sys_get_temp_dir(), 'txt'); file_put_contents($noImg, 'esto no es una imagen');
$guardarEmp = function (array $d, ?CURLFile $logo = null, ?string $cookie = null) use ($base, $cookieAdmin) {
    return peticion("$base/guardarEmpresa.php", $d, $cookie ?? $cookieAdmin, $logo ? ['logo' => $logo] : []);
};
$base0 = ['nombre' => 'Estancia La Aurora S.A.', 'color' => '#2a6bb5', 'lema' => '', 'cuit' => '30-12345678-9', 'email' => 'info@aurora.com', 'telefono' => '', 'direccion' => ''];
prueba('empresa sin sesion redirige a login', strpos(peticion("$base/guardarEmpresa.php", $base0)['loc'], 'login.php') !== false);
prueba('empresa: nombre vacio -> error', strpos($guardarEmp(['nombre' => '  '] + $base0)['loc'], 'error=nombre') !== false);
prueba('empresa: color invalido -> error', strpos($guardarEmp(['color' => 'rojo'] + $base0)['loc'], 'error=color') !== false);
prueba('empresa: color con codigo inyectado -> error', strpos($guardarEmp(['color' => '#fff;}</style><script>alert(1)</script>'] + $base0)['loc'], 'error=color') !== false);
prueba('empresa: mail invalido -> error', strpos($guardarEmp(['email' => 'no-es-mail'] + $base0)['loc'], 'error=email') !== false);
prueba('empresa: un archivo que no es imagen se rechaza', strpos($guardarEmp($base0, new CURLFile($noImg, 'image/png', 'logo.png'))['loc'], 'error=logo') !== false);
prueba('empresa: un logo de mas de 1 MB se rechaza', strpos($guardarEmp($base0, new CURLFile($gran, 'image/png', 'logo.png'))['loc'], 'error=logo') !== false);
prueba('empresa: sin logo cargado no queda ningun archivo', !is_file("$raiz/uploads/logo.png"));
$rg = $guardarEmp($base0, new CURLFile($png, 'image/png', 'evil.php'));
prueba('empresa: guardar nombre, color y logo -> ok', strpos($rg['loc'], 'empresa.php?ok') !== false);
prueba('el logo se guarda con nombre fijo, nunca con el del usuario (evil.php)', is_file("$raiz/uploads/logo.png") && !is_file("$raiz/uploads/evil.php"));
$b = peticion("$base/inicio.php", null, $cookieAdmin)['body'];
prueba('la cabecera muestra el nombre de la empresa', strpos($b, '<span class="marca-texto">Estancia La Aurora S.A.</span>') !== false && strpos($b, '<title>GAFA - Estancia La Aurora S.A.</title>') !== false);
prueba('la cabecera muestra el logo propio y aplica el color elegido', strpos($b, 'uploads/logo.png') !== false && strpos($b, '--verde: #2a6bb5') !== false);
prueba('el pie muestra la version y la empresa', strpos($b, 'GAFA v1.0.0 · Estancia La Aurora S.A.') !== false);
prueba('el saludo de inicio usa el nombre de la empresa', strpos($b, 'Todo el campo de Estancia La Aurora S.A.') !== false);
prueba('el login muestra solo "Sistema GAFA" sin el nombre de la empresa', strpos(peticion("$base/login.php")['body'], 'Sistema GAFA</p>') !== false && strpos(peticion("$base/login.php")['body'], 'Estancia La Aurora') === false);
prueba('el logo se sirve como imagen', ($rl = peticion("$base/uploads/logo.png"))['code'] === 200 && stripos($rl['headers'], 'image/png') !== false);
$ant = $contarMails();
$enviar(['accion' => 'uno', 'tipo' => $tipoVen, 'ref' => $refVen, 'canal' => 'email']);
$mails = $leerMails();
$ult = end($mails) ?: ['texto' => ''];
prueba('los mails de aviso salen con el nombre de la empresa configurada', $contarMails() === $ant + 1 && strpos($ult['texto'], 'Estancia La Aurora S.A.') !== false && strpos($ult['texto'], 'Don Juan S.R.L') === false);
foreach (glob($outbox . '/*.eml') ?: [] as $m) @unlink($m);
prueba('quitar el logo borra el archivo', strpos($guardarEmp($base0 + ['quitar_logo' => '1'])['loc'], 'empresa.php?ok') !== false && !is_file("$raiz/uploads/logo.png")
    && strpos(peticion("$base/inicio.php", null, $cookieAdmin)['body'], 'uploads/logo.png') === false);
$guardarEmp(['nombre' => 'Don Juan S.R.L', 'color' => '#2f5d34', 'cuit' => '', 'email' => '', 'lema' => '', 'telefono' => '', 'direccion' => '']);
$b = peticion("$base/inicio.php", null, $cookieAdmin)['body'];
prueba('restaurar los valores originales vuelve la marca anterior', strpos($b, '<span class="marca-texto">Don Juan S.R.L</span>') !== false && strpos($b, '--verde: #') === false);
@unlink($png); @unlink($gran); @unlink($noImg);

// 4i. Copias de seguridad
$dirResp = $raiz . '/storage/backups';
foreach (glob($dirResp . '/*.sql') ?: [] as $v) @unlink($v);
$r = peticion("$base/descargarRespaldo.php", ['accion' => 'descargar'], $cookieAdmin);
prueba('descargar copia: archivo .sql adjunto', $r['code'] === 200 && preg_match('/Content-Disposition: attachment; filename="gafa-\d{8}-\d{6}\.sql"/i', $r['headers']) === 1);
prueba('la copia incluye estructura y datos (usuarios, alquileres)', strpos($r['body'], 'CREATE TABLE `usuarios`') !== false && strpos($r['body'], 'INSERT INTO `usuarios`') !== false
    && strpos($r['body'], 'CREATE TABLE `cobrosalquiler`') !== false && strpos($r['body'], 'Viejo Inquilino') !== false && strpos($r['body'], 'CREATE TABLE `notificaciones`') !== false);
prueba('descargar copia sin sesion redirige a login', strpos(peticion("$base/descargarRespaldo.php", ['accion' => 'descargar'])['loc'], 'login.php') !== false);
prueba('el Lector no puede descargar copias (403)', peticion("$base/descargarRespaldo.php", ['accion' => 'descargar'], $cookieLector)['code'] === 403);
prueba('guardar copia en el servidor', strpos(peticion("$base/descargarRespaldo.php", ['accion' => 'guardar'], $cookieAdmin)['loc'], 'respaldo.php?ok') !== false && count(glob($dirResp . '/gafa-*.sql')) === 1);
$nombreGuardado = basename(glob($dirResp . '/gafa-*.sql')[0] ?? '');
prueba('respaldo.php lista la copia guardada', strpos(peticion("$base/respaldo.php", null, $cookieAdmin)['body'], $nombreGuardado) !== false);
$r = peticion("$base/descargarRespaldo.php", ['accion' => 'bajar', 'archivo' => $nombreGuardado], $cookieAdmin);
prueba('bajar una copia guardada', $r['code'] === 200 && strpos($r['body'], 'CREATE TABLE `usuarios`') !== false);
$r = peticion("$base/descargarRespaldo.php", ['accion' => 'bajar', 'archivo' => '../config/notificaciones.ejemplo.php'], $cookieAdmin);
prueba('no se puede bajar un archivo fuera de la carpeta de copias (path traversal)', strpos($r['loc'], 'respaldo.php?error') !== false && strpos($r['body'], 'dias_cuota_proxima') === false);
prueba('el script diario de copias no se ejecuta por web (403)', peticion("$base/cron/respaldo.php")['code'] === 403);
$php_ini = escapeshellarg((string)php_ini_loaded_file());
exec('"' . PHP_BINARY . '" -c ' . $php_ini . ' ' . escapeshellarg($raiz . '/cron/respaldo.php') . ' --conservar=1 2>&1', $o1, $rc1);
usleep(1100000);
exec('"' . PHP_BINARY . '" -c ' . $php_ini . ' ' . escapeshellarg($raiz . '/cron/respaldo.php') . ' --conservar=1 2>&1', $o2, $rc2);
prueba('el script diario de copias funciona y conserva solo las ultimas N', $rc1 === 0 && $rc2 === 0 && count(glob($dirResp . '/gafa-*.sql')) === 1);
foreach (glob($dirResp . '/*.sql') ?: [] as $v) @unlink($v);

// 4j. Instalador para una empresa nueva y conexion configurable
$esq = file_get_contents($raiz . '/instalacion/esquema_limpio.sql');
preg_match_all('/^CREATE TABLE `(\w+)`/m', $esq, $mt);
$dbTest = new mysqli('127.0.0.1', 'root', '', 'donjuanbd', 3307);
$tablasVivas = array_column($dbTest->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'")->fetch_all(), 0);
sort($tablasVivas); $enEsquema = $mt[1]; sort($enEsquema);
prueba('el esquema de instalacion esta al dia con la base (regenerar con instalacion/generar_esquema.php)', $tablasVivas === $enEsquema);
prueba('el esquema de instalacion no trae datos de ejemplo', strpos($esq, 'Viejo Inquilino') === false && strpos($esq, 'Lucila') === false && strpos($esq, "'admin'") === false);

$dbInst = 'gafa_test_instalacion';
$cfgTmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'gafa_cfg_' . bin2hex(random_bytes(3)) . '.php';
$dbTest->query("DROP DATABASE IF EXISTS `$dbInst`");
$inst = function (array $extra) use ($raiz, $php_ini, $dbInst, $cfgTmp, &$salida) {
    $args = array_merge(['host' => '127.0.0.1', 'puerto' => '3307', 'usuario' => 'root', 'clave' => '', 'base' => $dbInst, 'admin-usuario' => 'dueño', 'admin-clave' => 'ClaveTest123',
                         'empresa' => 'Estancia Test', 'config' => $cfgTmp], $extra);
    $cmd = '"' . PHP_BINARY . '" -c ' . $php_ini . ' ' . escapeshellarg($raiz . '/instalacion/instalar.php');
    foreach ($args as $k => $v) $cmd .= ' ' . escapeshellarg("--$k=$v");
    $salida = [];
    exec($cmd . ' 2>&1', $salida, $rc);
    return $rc;
};
prueba('instalador: una clave de administrador debil se rechaza', $inst(['admin-clave' => '123']) === 1);
prueba('instalador: un nombre de base invalido se rechaza', $inst(['base' => 'gafa; DROP DATABASE donjuanbd']) === 1);
prueba('instalador: crea la base de una empresa nueva', $inst(['admin-usuario' => 'dueno']) === 0 && strpos(implode("\n", $salida), 'Instalacion completa') !== false);
$dbN = new mysqli('127.0.0.1', 'root', '', $dbInst, 3307);
$n = fn(string $sql) => (int)$dbN->query($sql)->fetch_row()[0];
prueba('instalador: crea todas las tablas', $n("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = '$dbInst'") === count($enEsquema));
prueba('instalador: base limpia (sin proyectos, compras, parcelas ni usuarios de ejemplo)', $n("SELECT COUNT(*) FROM proyectos") === 0 && $n("SELECT COUNT(*) FROM compras") === 0
    && $n("SELECT COUNT(*) FROM parcela") === 0 && $n("SELECT COUNT(*) FROM usuarios") === 1);
prueba('instalador: catalogos cargados (roles, estados, monedas)', $n("SELECT COUNT(*) FROM tipo_usuario") === 3 && $n("SELECT COUNT(*) FROM moneda") === 2
    && $n("SELECT COUNT(*) FROM tipoproyecto") === 3 && $n("SELECT COUNT(*) FROM estadoproyecto") === 3);
$adm = $dbN->query("SELECT usuario, password, id_tipoUsuario, id_EstadoUsuario FROM usuarios")->fetch_assoc();
prueba('instalador: administrador activo con la clave guardada con hash', $adm['usuario'] === 'dueno' && (int)$adm['id_tipoUsuario'] === 1 && (int)$adm['id_EstadoUsuario'] === 1
    && password_verify('ClaveTest123', $adm['password']) && strpos($adm['password'], 'ClaveTest123') === false);
prueba('instalador: guarda el nombre de la empresa', $dbN->query("SELECT valor FROM configuracion WHERE clave = 'nombre'")->fetch_row()[0] === 'Estancia Test');
$cfgGen = is_file($cfgTmp) ? (require $cfgTmp) : [];
prueba('instalador: escribe el archivo de conexion', ($cfgGen['base'] ?? '') === $dbInst && (int)($cfgGen['puerto'] ?? 0) === 3307);
prueba('instalador: no instala sobre una base que ya tiene tablas', $inst(['admin-usuario' => 'otro']) === 2);
// La aplicacion usa config/base_datos.php cuando existe (se restaura enseguida)
$cfgApp = $raiz . '/config/base_datos.php';
$tieneCfg = is_file($cfgApp);
copy($cfgTmp, $cfgApp);
$baseUsada = [];
try {
    $sondaPhp = tempnam(sys_get_temp_dir(), 'sonda') . '.php';
    file_put_contents($sondaPhp, "<?php\nchdir(" . var_export($raiz, true) . ");\ninclude 'Modelo/Conexion.php';\necho mysqli_fetch_row(mysqli_query(\$conexion, 'SELECT DATABASE()'))[0];\n");
    exec('"' . PHP_BINARY . '" -c ' . $php_ini . ' ' . escapeshellarg($sondaPhp) . ' 2>&1', $baseUsada);
    @unlink($sondaPhp);
} finally {
    if (!$tieneCfg) @unlink($cfgApp);
}
prueba('la aplicacion toma la base de config/base_datos.php', trim(implode('', $baseUsada)) === $dbInst && !is_file($cfgApp));
$dbN->close();
$dbTest->query("DROP DATABASE IF EXISTS `$dbInst`");
@unlink($cfgTmp);
$dbTest->close();
// 4k. Ventas y resultados por proyecto (requiere migracion_ventas.sql)
$dbV = new mysqli('127.0.0.1', 'root', '', 'donjuanbd', 3307);
$venta = function (array $d, ?string $cookie = null) use ($base, $cookieAdmin) { return peticion("$base/registrarVenta.php", $d, $cookie ?? $cookieAdmin); };
$vb = ['accion' => 'crear', 'proyecto' => $pVig, 'fecha' => '2030-02-10', 'concepto' => 'TEST Venta de soja', 'cantidad' => '25', 'unidad' => 'tn', 'importe' => '5000', 'moneda' => 1, 'comprador' => 'juan perez', 'comprobante' => 'A-1', 'observaciones' => ''];
$r = peticion("$base/ventas.php", null, $cookieAdmin);
prueba('ventas.php responde 200 sin errores PHP', $r['code'] === 200 && !preg_match('/Fatal error|Warning:|Notice:/', $r['body']));
prueba('ventas sin sesion redirige a login', strpos(peticion("$base/ventas.php")['loc'], 'login.php') !== false);
prueba('registrarVenta sin sesion redirige a login', strpos(peticion("$base/registrarVenta.php", $vb)['loc'], 'login.php') !== false);
prueba('venta con importe 0 -> error importe', strpos($venta(['importe' => '0'] + $vb)['loc'], 'error=importe') !== false);
prueba('venta con importe negativo -> error importe', strpos($venta(['importe' => '-5'] + $vb)['loc'], 'error=importe') !== false);
prueba('venta con moneda inexistente -> error moneda', strpos($venta(['moneda' => 99] + $vb)['loc'], 'error=moneda') !== false);
prueba('venta con proyecto inexistente -> error proyecto', strpos($venta(['proyecto' => 999999] + $vb)['loc'], 'error=proyecto') !== false);
prueba('venta con fecha invalida -> error datos', strpos($venta(['fecha' => '2030-13-45'] + $vb)['loc'], 'error=datos') !== false);
prueba('venta sin concepto -> error datos', strpos($venta(['concepto' => '  '] + $vb)['loc'], 'error=datos') !== false);
$venta(['proyecto' => '1 OR 1=1', 'concepto' => "TEST x'); DROP TABLE ventas;--"] + $vb);
$lit = $dbV->query("SELECT COUNT(*) FROM ventas WHERE concepto = 'TEST x\'); DROP TABLE ventas;--'")->fetch_row()[0] ?? 0;
prueba('un concepto con SQL se guarda como texto literal y la tabla sigue intacta', (int)$lit === 1);
$dbV->query("DELETE FROM ventas WHERE concepto LIKE 'TEST x%'");
prueba('se crea una venta en pesos', strpos($venta($vb)['loc'], 'ventas.php?ok') !== false);
prueba('se crea una venta en dolares', strpos($venta(['concepto' => 'TEST Venta de terneros', 'cantidad' => '40', 'unidad' => 'cabezas', 'importe' => '800', 'moneda' => 2, 'comprador' => 'campos del sur'] + $vb)['loc'], 'ventas.php?ok') !== false);
prueba('el Operador puede registrar ventas', strpos($venta(['concepto' => 'TEST Venta operador', 'importe' => '100'] + $vb, $cookieOp)['loc'], 'ventas.php?ok') !== false);
prueba('el Lector no puede registrar ventas (403)', $venta(['concepto' => 'TEST Lector'] + $vb, $cookieLector)['code'] === 403);
prueba('el Lector no puede quitar ventas (403)', $venta(['accion' => 'eliminar', 'venta' => 1], $cookieLector)['code'] === 403);
$cb = $dbV->query("SELECT comprador FROM ventas WHERE concepto = 'TEST Venta de soja'")->fetch_row()[0] ?? '';
prueba('el comprador se guarda con mayuscula inicial ("Juan Perez")', $cb === 'Juan Perez');

$r = peticion("$base/ventas.php?q=TEST", null, $cookieAdmin);
prueba('ventas lista las 3 ventas de prueba con su moneda', substr_count($tabla($r['body']), '<tr>') === 3 && strpos($tabla($r['body']), 'US$ 800,00') !== false && strpos($tabla($r['body']), '$ 5.000,00') !== false
    && strpos($tabla($r['body']), 'moneda-usd') !== false && strpos($tabla($r['body']), 'moneda-ars') !== false);
prueba('ventas separa los totales en pesos y en dolares', strpos($r['body'], 'Vendido en pesos') !== false && strpos($r['body'], 'Vendido en dólares') !== false
    && strpos($r['body'], '$ 5.100,00') !== false);
$r = peticion("$base/ventas.php?q=TEST&proyecto=$pVig&desde=2030-02-01&hasta=2030-02-28", null, $cookieAdmin);
prueba('ventas filtra por proyecto y fechas', substr_count($tabla($r['body']), '<tr>') === 3);
prueba('ventas filtra por fechas fuera de rango', substr_count($tabla(peticion("$base/ventas.php?q=TEST&desde=2031-01-01", null, $cookieAdmin)['body']), '<tr>') === 0);
$r = peticion("$base/ventas.php?q=" . urlencode("' OR '1'='1"), null, $cookieAdmin);
prueba('ventas con busqueda inyectada no lista nada', $r['code'] === 200 && substr_count($tabla($r['body']), '<tr>') === 0 && !preg_match('/SQL syntax/', $r['body']));
$rl = peticion("$base/ventas.php", null, $cookieLector);
prueba('el Lector consulta ventas sin formularios de carga ni de borrado', $rl['code'] === 200 && strpos($rl['body'], 'action="./registrarVenta.php"') === false && strpos($rl['body'], 'Resultados por proyecto') !== false);
prueba('el Administrador ve el formulario de venta', strpos($r['body'], 'action="./registrarVenta.php"') !== false);

// Resultados: ingresos (ventas + alquiler cobrado) - gastos (compras asignadas), por moneda
peticion("$base/registrarCobro.php", ['cobro' => $idCobro, 'accion' => 'cobrar', 'fechaPago' => $hoy], $cookieAdmin);
$r = peticion("$base/resultados.php?no=$pVig", null, $cookieAdmin);
prueba('resultado del proyecto: ingresos en pesos = ventas 5.100 + alquiler cobrado 1.000', $r['code'] === 200 && strpos($r['body'], '$ 6.100,00') !== false && !preg_match('/Fatal error|Warning:|Notice:/', $r['body']));
prueba('resultado del proyecto: los dolares se muestran aparte (US$ 800,00) y no se suman a los pesos', strpos($r['body'], 'US$ 800,00') !== false && strpos($r['body'], '6.900,00') === false);
prueba('resultado del proyecto: lista la venta y el cobro de alquiler', strpos($r['body'], 'TEST Venta de soja') !== false && strpos($r['body'], 'Cobros de alquiler incluidos') !== false);
$gc = $dbV->query("SELECT c.importeTotal, m.moneda FROM compraproyecto cp JOIN compras c ON c.id_Compras = cp.id_Compras JOIN moneda m ON m.id_Moneda = c.id_Moneda WHERE cp.id_Compras = 2 AND cp.id_TipoCompra = 2")->fetch_assoc();
$r = peticion("$base/resultados.php?no=5", null, $cookieAdmin);
prueba('el gasto de una compra asignada reduce el resultado (perdida en su moneda)', $gc && strpos($r['body'], montoMoneda($gc['importeTotal'], $gc['moneda'])) !== false && strpos($r['body'], 'Pérdida') !== false);
prueba('resultados de un proyecto inexistente avisa sin romper', strpos(peticion("$base/resultados.php?no=999999", null, $cookieAdmin)['body'], 'PROYECTO NO ENCONTRADO') !== false);
$r = peticion("$base/resultados.php", null, $cookieAdmin);
prueba('resultados lista proyectos con su moneda y totales por moneda', $r['code'] === 200 && strpos($fila($tabla($r['body']), 'Alq Vigente'), 'moneda-ars') !== false
    && strpos($r['body'], 'Resultado en pesos') !== false && strpos($r['body'], 'Resultado en dólares') !== false);
$r = peticion("$base/resultados.php?q=Vigente", null, $cookieAdmin);
prueba('resultados filtra por nombre', strpos($tabla($r['body']), 'Alq Vigente') !== false && strpos($tabla($r['body']), 'Alq Vencido') === false);
prueba('resultados sin sesion redirige a login', strpos(peticion("$base/resultados.php")['loc'], 'login.php') !== false);
prueba('el Lector consulta resultados', peticion("$base/resultados.php", null, $cookieLector)['code'] === 200 && peticion("$base/resultados.php?no=$pVig", null, $cookieLector)['code'] === 200);
prueba('todosLosProyectos ofrece el enlace al resultado', strpos(peticion("$base/todosLosProyectos.php?q=" . urlencode('Alq Vigente'), null, $cookieAdmin)['body'], "resultados.php?no=$pVig") !== false);
$b = peticion("$base/inicio.php", null, $cookieLector)['body'];
prueba('el menu incluye Ventas y Resultados para todos los roles', strpos($b, 'href="./ventas.php"') !== false && strpos($b, 'href="./resultados.php"') !== false);

$r = peticion("$base/Exportar/ExcelVentas.php?q=TEST", null, $cookieAdmin);
prueba('Excel de ventas trae datos y totales por moneda', $r['code'] === 200 && strpos($r['body'], 'TEST Venta de soja') !== false && strpos($r['body'], 'Total en dólares (USD)') !== false && strpos($r['body'], 'Total en pesos (ARS)') !== false);
$r = peticion("$base/Exportar/ExcelVentas.php?q=terneros", null, $cookieAdmin);
prueba('Excel de ventas respeta el filtro', strpos($r['body'], 'TEST Venta de terneros') !== false && strpos($r['body'], 'TEST Venta de soja') === false);
prueba('Excel de ventas sin sesion -> 403', peticion("$base/Exportar/ExcelVentas.php")['code'] === 403);
$r = peticion("$base/Exportar/ExcelResultados.php", null, $cookieAdmin);
prueba('Excel de resultados trae proyectos y totales por moneda', $r['code'] === 200 && strpos($r['body'], 'Alq Vigente') !== false && strpos($r['body'], 'Totales en pesos (ARS)') !== false);
prueba('Excel de resultados sin sesion -> 403', peticion("$base/Exportar/ExcelResultados.php")['code'] === 403);

// Quitar una venta y limpiar los datos de prueba
preg_match('~name="venta" value="(\d+)"~', peticion("$base/ventas.php?q=" . urlencode('TEST Venta operador'), null, $cookieAdmin)['body'], $mv);
prueba('quitar una venta', strpos($venta(['accion' => 'eliminar', 'venta' => (int)($mv[1] ?? 0)])['loc'], 'ventas.php?borrada') !== false
    && substr_count($tabla(peticion("$base/ventas.php?q=TEST", null, $cookieAdmin)['body']), '<tr>') === 2);
prueba('quitar una venta inexistente -> error', strpos($venta(['accion' => 'eliminar', 'venta' => 999999])['loc'], 'error') !== false);
$dbV->query("DELETE FROM ventas WHERE concepto LIKE 'TEST%'");
peticion("$base/registrarCobro.php", ['cobro' => $idCobro, 'accion' => 'deshacer'], $cookieAdmin);
$dbV->close();

// 4l. Movimientos de hacienda (requiere migracion_hacienda.sql)
$dbH = new mysqli('127.0.0.1', 'root', '', 'donjuanbd', 3307);
$r = peticion("$base/movimientosHacienda.php", null, $cookieAdmin);
prueba('movimientosHacienda lista el stock sin errores PHP', $r['code'] === 200 && strpos($r['body'], 'STOCK DE CABEZAS') !== false && !preg_match('/Fatal error|Warning:|Notice:/', $r['body']));
prueba('movimientosHacienda sin sesion redirige a login', strpos(peticion("$base/movimientosHacienda.php")['loc'], 'login.php') !== false);
peticion("$base/agrProyecto.php", ['btn1' => '1', 'cmbparcela1' => 3, 'nombre1' => 'Hac Test', 'cmbTipoProyecto1' => 1, 'cantHas1' => 10, 'cmbEstado1' => 2], $cookieAdmin);
$r = peticion("$base/todosLosProyectos.php?q=" . urlencode('Hac Test'), null, $cookieAdmin);
$pHac = preg_match('~detalleProyecto\.php\?no=(\d+)~', $r['body'], $mh) ? (int)$mh[1] : 0;
prueba('se creo un proyecto de hacienda de prueba y el listado ofrece Movimientos', $pHac > 0 && strpos($r['body'], "movimientosHacienda.php?no=$pHac") !== false);
$mov = function (array $d, ?string $cookie = null) use ($base, $cookieAdmin) { return peticion("$base/registrarMovimiento.php", $d, $cookie ?? $cookieAdmin); };
$mb = ['accion' => 'crear', 'proyecto' => $pHac, 'fecha' => '2030-03-01', 'tipo' => 'Compra', 'cantidad' => 100, 'observaciones' => 'lote inicial'];
$stockHtml = function () use ($base, $cookieAdmin, $pHac) { return peticion("$base/movimientosHacienda.php?no=$pHac", null, $cookieAdmin)['body']; };
prueba('registrarMovimiento sin sesion redirige a login', strpos(peticion("$base/registrarMovimiento.php", $mb)['loc'], 'login.php') !== false);
prueba('una salida sin stock se rechaza (error=stock)', strpos($mov(['tipo' => 'Venta', 'cantidad' => 10] + $mb)['loc'], 'error=stock') !== false);
prueba('cantidad 0 -> error cantidad', strpos($mov(['cantidad' => 0] + $mb)['loc'], 'error=cantidad') !== false);
prueba('tipo de movimiento inventado -> error datos', strpos($mov(['tipo' => 'Regalo'] + $mb)['loc'], 'error=datos') !== false);
prueba('fecha invalida -> error datos', strpos($mov(['fecha' => '2030-02-31'] + $mb)['loc'], 'error=datos') !== false);
prueba('movimiento en un proyecto que no es de hacienda -> error proyecto', strpos($mov(['proyecto' => $pVig] + $mb)['loc'], 'error=proyecto') !== false);
prueba('compra de 100 cabezas', strpos($mov($mb)['loc'], 'ok') !== false);
prueba('nacimiento de 20 cabezas', strpos($mov(['tipo' => 'Nacimiento', 'cantidad' => 20] + $mb)['loc'], 'ok') !== false);
prueba('muerte de 5 cabezas', strpos($mov(['tipo' => 'Muerte', 'cantidad' => 5] + $mb)['loc'], 'ok') !== false);
prueba('el stock es 115 (100 + 20 - 5)', strpos($stockHtml(), '<span class="num">115</span>') !== false);
prueba('venta con mas cabezas que el stock -> error stock', strpos($mov(['tipo' => 'Venta', 'cantidad' => 1000] + $mb)['loc'], 'error=stock') !== false);
prueba('venta de 30 cabezas con importe en pesos', strpos($mov(['tipo' => 'Venta', 'cantidad' => 30, 'importe' => '3000', 'moneda' => 1, 'comprador' => 'maría lópez'] + $mb)['loc'], 'ok') !== false);
$h = $stockHtml();
prueba('el stock baja a 85 y el movimiento muestra su ingreso en pesos', strpos($h, '<span class="num">85</span>') !== false && strpos($h, '$ 3.000,00') !== false);
$vr = peticion("$base/ventas.php?proyecto=$pHac", null, $cookieAdmin)['body'];
prueba('la venta de hacienda tambien figura en Ventas (comprador con mayuscula)', strpos($vr, 'Venta de 30 cabezas de hacienda') !== false && strpos($vr, 'María López') !== false);
prueba('el resultado del proyecto de hacienda incluye ese ingreso', strpos(peticion("$base/resultados.php?no=$pHac", null, $cookieAdmin)['body'], '$ 3.000,00') !== false);
$e = peticion("$base/estadisticas.php", null, $cookieAdmin);
prueba('estadisticas muestra resultado total por moneda y stock de hacienda', $e['code'] === 200 && strpos($e['body'], 'Resultado total en') !== false && strpos($e['body'], 'Cabezas de hacienda en stock') !== false && !preg_match('/Fatal error|Warning:|Notice:/', $e['body']));
$listado = peticion("$base/movimientosHacienda.php", null, $cookieAdmin)['body'];
prueba('el listado general muestra el proyecto con su stock', strpos($listado, 'Hac Test') !== false);
preg_match('~name="movimiento" value="(\d+)"~', $h, $mm); // el mas reciente es la venta
$idMovCompra = (int)$dbH->query("SELECT id_Movimiento FROM movimientoshacienda WHERE id_Proyecto = $pHac AND tipo = 'Compra'")->fetch_row()[0];
prueba('no se puede quitar una compra si el stock quedaria negativo', strpos($mov(['accion' => 'eliminar', 'movimiento' => $idMovCompra])['loc'], 'error=stock') !== false);
prueba('quitar la venta devuelve las cabezas y borra el ingreso de Ventas', strpos($mov(['accion' => 'eliminar', 'movimiento' => (int)($mm[1] ?? 0)])['loc'], 'borrado') !== false
    && strpos($stockHtml(), '<span class="num">115</span>') !== false
    && strpos(peticion("$base/ventas.php?proyecto=$pHac", null, $cookieAdmin)['body'], 'Venta de 30 cabezas') === false);
prueba('el Operador puede registrar movimientos', strpos($mov(['tipo' => 'Nacimiento', 'cantidad' => 1] + $mb, $cookieOp)['loc'], 'ok') !== false);
prueba('el Lector no puede registrar ni quitar movimientos (403)', $mov($mb, $cookieLector)['code'] === 403 && $mov(['accion' => 'eliminar', 'movimiento' => $idMovCompra], $cookieLector)['code'] === 403);
$rl = peticion("$base/movimientosHacienda.php?no=$pHac", null, $cookieLector);
prueba('el Lector consulta el stock sin formulario', $rl['code'] === 200 && strpos($rl['body'], 'action="./registrarMovimiento.php"') === false);
$dbH->query("DELETE FROM ventas WHERE id_Proyecto = $pHac");
$dbH->query("DELETE FROM movimientoshacienda WHERE id_Proyecto = $pHac");

// 4m. Pantalla de inicio segun el rol
$ia = peticion("$base/inicio.php", null, $cookieAdmin)['body'];
$il = peticion("$base/inicio.php", null, $cookieLector)['body'];
prueba('el inicio del Administrador ofrece Gestion y los accesos nuevos', strpos($ia, 'Usuarios, proveedores, empresa y copias') !== false && strpos($ia, 'href="./movimientosHacienda.php"') !== false
    && strpos($ia, 'href="./ventas.php"') !== false && strpos($ia, 'href="./resultados.php"') !== false);
prueba('el inicio del Lector no muestra Gestion y ofrece Parcelas', strpos($il, 'Usuarios, proveedores, empresa y copias') === false && strpos($il, '<h3>Parcelas</h3>') !== false);

// 4n. Seguridad: bloqueo de intentos y registro de actividad (requiere migracion_seguridad.sql)
peticion("$base/agrUsuario.php", ['usuario' => 'bloq2', 'pass' => 'Clave1234', 'nombre' => 'x', 'apellido' => 'y', 'tipo' => 2, 'estado' => 1, 'email' => 'bloq2@test.local'], $cookieAdmin);
prueba('el usuario de prueba puede ingresar antes del bloqueo', strpos(login($base, 'bloq2', 'Clave1234')['loc'], '?ok') !== false);
for ($i = 0; $i < 5; $i++) login($base, 'bloq2', 'incorrecta');
prueba('tras 5 intentos fallidos el usuario queda bloqueado (aun con la clave correcta)', strpos(login($base, 'bloq2', 'Clave1234')['loc'], '?bloq') !== false);
prueba('el bloqueo de un usuario no afecta a los demas', strpos(login($base, 'admin', '123')['loc'], '?ok') !== false);
$dbH->query("DELETE FROM intentoslogin");
prueba('al pasar el bloqueo el usuario vuelve a ingresar', strpos(login($base, 'bloq2', 'Clave1234')['loc'], '?ok') !== false);
$au = peticion("$base/auditoria.php", null, $cookieAdmin);
prueba('el registro de actividad muestra ingresos, fallos, ventas y movimientos', $au['code'] === 200 && strpos($au['body'], 'login_fallido') !== false && strpos($au['body'], 'login_bloqueado') !== false
    && strpos($au['body'], 'venta_registrada') !== false && strpos($au['body'], 'hacienda_compra') !== false && strpos($au['body'], 'usuario_creado') !== false);
prueba('el registro de actividad filtra por usuario', strpos(peticion("$base/auditoria.php?usuario=bloq2", null, $cookieAdmin)['body'], 'venta_registrada') === false);
prueba('el registro de actividad es solo para el Administrador (Operador y Lector 403)', peticion("$base/auditoria.php", null, $cookieOp)['code'] === 403 && peticion("$base/auditoria.php", null, $cookieLector)['code'] === 403);
prueba('el registro de actividad sin sesion redirige a login', strpos(peticion("$base/auditoria.php")['loc'], 'login.php') !== false);
$dbH->query("DELETE FROM usuarios WHERE usuario = 'bloq2'");
$dbH->query("DELETE FROM intentoslogin");
$dbH->close();

// 4o. Recuperacion de contraseña por mail (requiere migracion_recuperacion.sql)
$dbR = new mysqli('127.0.0.1', 'root', '', 'donjuanbd', 3307);
foreach (glob($outbox . '/*.eml') ?: [] as $viejo) @unlink($viejo);
$nu = ['usuario' => 'recu1', 'pass' => 'ClaveVieja1', 'nombre' => 'rosa', 'apellido' => 'díaz', 'tipo' => 3, 'estado' => 1, 'email' => 'Recu1@Test.Local'];
$alta = function (array $d) use ($base, $cookieAdmin) { return peticion("$base/agrUsuario.php", $d, $cookieAdmin); };
prueba('alta de usuario sin mail -> error email', strpos($alta(['email' => ''] + $nu)['loc'], 'error=email') !== false);
prueba('alta de usuario con mail invalido -> error email', strpos($alta(['email' => 'no-es-un-mail'] + $nu)['loc'], 'error=email') !== false);
prueba('el usuario rechazado no se creo', (int)$dbR->query("SELECT COUNT(*) FROM usuarios WHERE usuario = 'recu1'")->fetch_row()[0] === 0);
prueba('alta de usuario con mail valido', strpos($alta($nu)['loc'], 'usuarios.php?ok') !== false);
prueba('el mail se guarda en minusculas', ($dbR->query("SELECT email FROM usuarios WHERE usuario = 'recu1'")->fetch_row()[0] ?? '') === 'recu1@test.local');
prueba('dos usuarios no pueden compartir el mismo mail', strpos($alta(['usuario' => 'recu1b', 'email' => 'recu1@test.local'] + $nu)['loc'], 'error') !== false
    && (int)$dbR->query("SELECT COUNT(*) FROM usuarios WHERE usuario = 'recu1b'")->fetch_row()[0] === 0);
prueba('usuarios.php muestra el mail y marca a quien no lo tiene', strpos(peticion("$base/usuarios.php", null, $cookieAdmin)['body'], 'recu1@test.local') !== false
    && strpos(peticion("$base/usuarios.php", null, $cookieAdmin)['body'], 'Sin mail') !== false);
$idRecu = (int)$dbR->query("SELECT id_usuario FROM usuarios WHERE usuario = 'recu1'")->fetch_row()[0];
prueba('detalleUsuario muestra el campo mail', strpos(peticion("$base/detalleUsuario.php?no=$idRecu", null, $cookieAdmin)['body'], 'value="recu1@test.local"') !== false);
prueba('modUsuario cambia el mail y lo guarda en minusculas', strpos(peticion("$base/modUsuario.php", ['id' => $idRecu, 'usuario' => 'recu1', 'nombre' => 'Rosa', 'apellido' => 'Díaz', 'tipo' => 3, 'estado' => 1, 'email' => 'Otro@Test.Local'], $cookieAdmin)['loc'], 'ok') !== false
    && ($dbR->query("SELECT email FROM usuarios WHERE id_usuario = $idRecu")->fetch_row()[0] ?? '') === 'otro@test.local');
prueba('modUsuario no admite un mail invalido', strpos(peticion("$base/modUsuario.php", ['id' => $idRecu, 'usuario' => 'recu1', 'nombre' => 'Rosa', 'apellido' => 'Díaz', 'tipo' => 3, 'estado' => 1, 'email' => 'roto@'], $cookieAdmin)['loc'], 'error=email') !== false);
peticion("$base/modUsuario.php", ['id' => $idRecu, 'usuario' => 'recu1', 'nombre' => 'Rosa', 'apellido' => 'Díaz', 'tipo' => 3, 'estado' => 1, 'email' => 'recu1@test.local'], $cookieAdmin);

$rl = peticion("$base/login.php");
prueba('el login ofrece "Olvidaste tu contraseña"', strpos($rl['body'], 'recuperar.php') !== false);
$rr = peticion("$base/recuperar.php");
prueba('recuperar.php es publica y pide usuario o mail', $rr['code'] === 200 && strpos($rr['body'], 'name="identificador"') !== false && !preg_match('/Fatal error|Warning:|Notice:/', $rr['body']));

$pedir = function (string $ident) use ($base) { return peticion("$base/recuperar.php", ['identificador' => $ident]); };
$antes = $contarMails();
$r = $pedir('usuario_que_no_existe');
prueba('pedido para un usuario inexistente responde igual y no envia nada', strpos($r['loc'], 'recuperar.php?enviado') !== false && $contarMails() === $antes);
$r = $pedir("' OR '1'='1");
prueba('pedido con SQL inyectado no envia nada ni rompe', strpos($r['loc'], 'recuperar.php?enviado') !== false && $contarMails() === $antes);
$tokenDe = function (array $mails, string $para) {
    foreach (array_reverse($mails) as $m) if ($m['to'] === $para && preg_match('~restablecer\.php\?t=([0-9a-f]{64})~', $m['texto'], $mt)) return $mt[1];
    return '';
};
$r = $pedir('recu1');
prueba('pedido por usuario: responde "enviado" y manda un mail', strpos($r['loc'], 'recuperar.php?enviado') !== false && $contarMails() === $antes + 1);
$mails = $leerMails();
$tok1 = $tokenDe($mails, 'recu1@test.local');
prueba('el mail llega al destinatario con un enlace de 64 caracteres y aviso de vencimiento', strlen($tok1) === 64 && strpos(end($mails)['texto'], 'una sola vez') !== false && strpos(end($mails)['texto'], 'recu1') !== false);
prueba('el enlace no se guarda en la base (solo su hash)', (int)$dbR->query("SELECT COUNT(*) FROM recuperacion WHERE tokenHash = '$tok1'")->fetch_row()[0] === 0
    && (int)$dbR->query("SELECT COUNT(*) FROM recuperacion WHERE tokenHash = '" . hash('sha256', $tok1) . "'")->fetch_row()[0] === 1);
$r = $pedir('RECU1@test.local');
$tok2 = $tokenDe($leerMails(), 'recu1@test.local');
prueba('tambien se puede pedir por mail y se genera un enlace nuevo', $contarMails() === $antes + 2 && $tok2 !== '' && $tok2 !== $tok1);
$g = peticion("$base/restablecer.php?t=$tok1");
prueba('el enlace anterior deja de servir al pedir uno nuevo', strpos($g['body'], 'no es válido') !== false && strpos($g['body'], 'name="pass"') === false);
$g = peticion("$base/restablecer.php?t=$tok2");
prueba('el enlace vigente muestra el formulario con el usuario y sin cache ni referrer', $g['code'] === 200 && strpos($g['body'], 'name="pass2"') !== false && strpos($g['body'], '<b>recu1</b>') !== false
    && stripos($g['headers'], 'Referrer-Policy: no-referrer') !== false && stripos($g['headers'], 'no-store') !== false);
prueba('un token inventado o mal formado no sirve', strpos(peticion("$base/restablecer.php?t=" . str_repeat('a', 64))['body'], 'no es válido') !== false
    && strpos(peticion("$base/restablecer.php?t=" . urlencode("' OR 1=1 --"))['body'], 'no es válido') !== false && strpos(peticion("$base/restablecer.php")['body'], 'no es válido') !== false);
$cambiar = function (string $t, string $p1, string $p2) use ($base) { return peticion("$base/restablecer.php", ['t' => $t, 'pass' => $p1, 'pass2' => $p2]); };
prueba('una contraseña corta se rechaza', strpos($cambiar($tok2, '1234567', '1234567')['body'], 'al menos 8') !== false);
prueba('contraseñas distintas se rechazan', strpos($cambiar($tok2, 'ClaveNueva99', 'ClaveNueva98')['body'], 'no coinciden') !== false);
prueba('tras los rechazos la clave vieja sigue funcionando', strpos(login($base, 'recu1', 'ClaveVieja1')['loc'], '?ok') !== false);
// Bloquear al usuario con intentos fallidos: restablecer la clave tambien levanta el bloqueo
for ($i = 0; $i < 5; $i++) login($base, 'recu1', 'mala');
prueba('el usuario queda bloqueado por intentos fallidos', strpos(login($base, 'recu1', 'ClaveVieja1')['loc'], '?bloq') !== false);
$r = $cambiar($tok2, 'ClaveNueva99', 'ClaveNueva99');
prueba('con el enlace se cambia la contraseña y se vuelve al login', strpos($r['loc'], 'login.php?clave') !== false);
prueba('la contraseña nueva funciona (y levanta el bloqueo)', strpos(login($base, 'recu1', 'ClaveNueva99')['loc'], '?ok') !== false);
prueba('la contraseña vieja ya no funciona', strpos(login($base, 'recu1', 'ClaveVieja1')['loc'], '?err') !== false);
prueba('el enlace no se puede usar dos veces', strpos(peticion("$base/restablecer.php?t=$tok2")['body'], 'no es válido') !== false
    && strpos($cambiar($tok2, 'OtraClave123', 'OtraClave123')['body'], 'no es válido') !== false && strpos(login($base, 'recu1', 'ClaveNueva99')['loc'], '?ok') !== false);
prueba('el login muestra el aviso de contraseña actualizada', strpos(peticion("$base/login.php?clave")['body'], 'Contraseña actualizada') !== false);

// Vencimiento, usuarios inactivos, sin mail y limite de pedidos
$n0 = $contarMails();
$pedir('recu1');
$tok3 = $tokenDe($leerMails(), 'recu1@test.local');
$dbR->query("UPDATE recuperacion SET expira = NOW() - INTERVAL 1 MINUTE");
prueba('un enlace vencido no sirve', $tok3 !== '' && strpos(peticion("$base/restablecer.php?t=$tok3")['body'], 'no es válido') !== false);
$alta(['usuario' => 'recu2', 'email' => 'recu2@test.local', 'estado' => 2] + $nu);
$n1 = $contarMails();
$pedir('recu2');
prueba('un usuario inactivo no recibe el enlace', $contarMails() === $n1);
$dbR->query("INSERT INTO usuarios (usuario, password, nombre, apellido, id_tipoUsuario, id_EstadoUsuario) VALUES ('recu_sinmail', 'x', 'S', 'M', 2, 1)");
$n1 = $contarMails();
$pedir('recu_sinmail');
prueba('un usuario sin mail cargado no recibe nada (y la respuesta no lo delata)', $contarMails() === $n1);
$alta(['usuario' => 'recu3', 'email' => 'recu3@test.local'] + $nu);
$n1 = $contarMails();
for ($i = 0; $i < 5; $i++) $pedir('recu3');
prueba('hay un limite de 3 pedidos por hora por usuario', $contarMails() === $n1 + 3);
$au = peticion("$base/auditoria.php", null, $cookieAdmin)['body'];
prueba('el registro de actividad deja constancia de la recuperacion', strpos($au, 'recuperacion_enviada') !== false && strpos($au, 'clave_restablecida') !== false
    && strpos($au, 'recuperacion_desconocida') !== false && strpos($au, 'recuperacion_limitada') !== false);
$dbR->query("DELETE FROM usuarios WHERE usuario LIKE 'recu%'");
$dbR->query("DELETE FROM recuperacion");
$dbR->query("DELETE FROM intentoslogin");
foreach (glob($outbox . '/*.eml') ?: [] as $viejo) @unlink($viejo);
$dbR->close();

// 4p. Version celular / app instalable (PWA)
$rm = peticion("$base/manifest.php");
$mj = json_decode($rm['body'], true);
prueba('manifest.php responde JSON de manifiesto con nombre, inicio y modo app', $rm['code'] === 200 && stripos($rm['headers'], 'application/manifest+json') !== false
    && is_array($mj) && ($mj['display'] ?? '') === 'standalone' && ($mj['start_url'] ?? '') === './inicio.php' && strpos($mj['name'] ?? '', 'GAFA') === 0);
$iconosOk = true;
foreach (($mj['icons'] ?? []) as $ic) { $ri = peticion("$base/" . $ic['src']); if ($ri['code'] !== 200 || stripos($ri['headers'], 'image/png') === false) $iconosOk = false; }
prueba('el manifiesto trae iconos 192, 512 y maskable y todos existen', $iconosOk && count($mj['icons'] ?? []) >= 3);
$rs = peticion("$base/sw.js");
prueba('service worker disponible, sin cachear paginas PHP', $rs['code'] === 200 && strpos($rs['body'], "mode === 'navigate'") !== false && strpos($rs['body'], 'sin-conexion.html') !== false);
prueba('pantalla "sin conexion" disponible', strpos(peticion("$base/sin-conexion.html")['body'], 'Sin conexión') !== false);
foreach (['Css/movil.css', 'pwa/movil.js', 'pwa/apple-touch-icon.png'] as $recurso) prueba("recurso movil $recurso se sirve", peticion("$base/$recurso")['code'] === 200);
$ri = peticion("$base/inicio.php", null, $cookieAdmin)['body'];
prueba('las paginas internas declaran viewport, manifiesto, estilos moviles, barra inferior y aviso de instalacion',
    strpos($ri, 'viewport-fit=cover') !== false && strpos($ri, 'rel="manifest"') !== false && strpos($ri, 'Css/movil.css') !== false
    && strpos($ri, 'class="barra-movil"') !== false && strpos($ri, 'id="aviso-instalar"') !== false && strpos($ri, 'pwa/movil.js') !== false);
prueba('la barra inferior marca la pagina actual', preg_match('~<a href="\./inicio\.php" class="activo">~', $ri) === 1);
prueba('las paginas internas no emiten avisos de PHP', !preg_match('/Notice:|Warning:|Fatal error/', peticion("$base/usuarios.php", null, $cookieAdmin)['body']));
foreach (['login.php', 'recuperar.php'] as $pg) prueba("$pg (publica) enlaza el manifiesto y es adaptable", strpos(peticion("$base/$pg")['body'], 'rel="manifest"') !== false && strpos(peticion("$base/$pg")['body'], 'width=device-width') !== false);
prueba('el CSS ya no fuerza un ancho fijo de 320px', strpos(file_get_contents("$raiz/estilos.css"), 'width: 320px !important') === false);
prueba('sin sesion no hay barra inferior ni datos en el manifiesto', strpos(peticion("$base/manifest.php")['body'], 'usuario') === false);

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
