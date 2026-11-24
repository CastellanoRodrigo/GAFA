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
$nuevoUsr = ['usuario' => 'testcap1', 'pass' => 'x1', 'nombre' => 'maría josé', 'apellido' => 'de la cruz', 'tipo' => 2, 'estado' => 1];
peticion("$base/agrUsuario.php", $nuevoUsr, $cookieAdmin);
$nuevoUsr2 = ['usuario' => 'testcap2', 'pass' => 'x2', 'nombre' => 'JUAN CARLOS', 'apellido' => "o'brien-pérez", 'tipo' => 2, 'estado' => 1];
peticion("$base/agrUsuario.php", $nuevoUsr2, $cookieAdmin);
$r = peticion("$base/usuarios.php", null, $cookieAdmin);
prueba('al crear un usuario el nombre se guarda con mayuscula inicial ("María José De la Cruz")', strpos($r['body'], '>María José<') !== false && strpos($r['body'], '>De la Cruz<') !== false);
prueba('mayusculas respetan apostrofes, guiones y tildes ("Juan Carlos O\'Brien-Pérez")', strpos($r['body'], '>Juan Carlos<') !== false && strpos(html_entity_decode($r['body'], ENT_QUOTES), "O'Brien-Pérez") !== false);
$r = peticion("$base/detalleUsuario.php?no=1", null, $cookieAdmin);
prueba('detalleUsuario muestra el nombre con mayuscula', strpos($r['body'], 'value="Lucila"') !== false && strpos($r['body'], 'value="Dolce"') !== false);
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
