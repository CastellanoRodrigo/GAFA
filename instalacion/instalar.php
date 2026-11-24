<?php
// Instalador de GAFA para una empresa nueva: crea la base vacia, el usuario administrador y la configuracion.
//
//   php instalacion/instalar.php --usuario=root --clave= --base=gafa --admin-clave=MiClaveSegura --empresa="Estancia La Aurora"
//
// Opciones: --host=localhost --puerto=3306 --usuario=root --clave= --base=gafa
//           --admin-usuario=admin --admin-clave=... (minimo 6 caracteres) --empresa="Nombre"
//           --config=ruta   (donde escribir config/base_datos.php)
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Solo por linea de comandos'); }

$o = ['host' => 'localhost', 'puerto' => '3306', 'usuario' => 'root', 'clave' => '', 'base' => 'gafa',
      'admin-usuario' => 'admin', 'admin-clave' => '', 'empresa' => 'Mi Empresa', 'config' => __DIR__ . '/../config/base_datos.php'];
foreach (array_slice($argv, 1) as $a) {
    if ($a === '--ayuda' || $a === '-h') { echo file_get_contents(__FILE__, false, null, 0, 900) . "\n"; exit(0); }
    if (preg_match('/^--([a-z\-]+)=(.*)$/s', $a, $m) && array_key_exists($m[1], $o)) $o[$m[1]] = $m[2];
    else { fwrite(STDERR, "Opcion desconocida: $a (use --ayuda)\n"); exit(1); }
}
$fallar = function (string $m, int $c = 1) { fwrite(STDERR, "ERROR: $m\n"); exit($c); };

if (!preg_match('/^[A-Za-z0-9_]{1,60}$/', $o['base'])) $fallar('El nombre de la base solo admite letras, numeros y guion bajo.');
if (strlen($o['admin-clave']) < 6) $fallar('Indique --admin-clave con al menos 6 caracteres.');
if (!preg_match('/^[A-Za-z0-9_.@-]{3,45}$/', $o['admin-usuario'])) $fallar('--admin-usuario invalido (3 a 45 caracteres: letras, numeros, . _ - @).');
if (trim($o['empresa']) === '') $fallar('Indique --empresa.');
$esquema = __DIR__ . '/esquema_limpio.sql';
if (!is_file($esquema)) $fallar('No se encuentra instalacion/esquema_limpio.sql (ejecute instalacion/generar_esquema.php).');

mysqli_report(MYSQLI_REPORT_OFF);
$c = @mysqli_connect($o['host'], $o['usuario'], $o['clave'], '', (int)$o['puerto']);
if (!$c) $fallar('No se pudo conectar al servidor de base de datos: ' . mysqli_connect_error());
if (!$c->query("CREATE DATABASE IF NOT EXISTS `{$o['base']}` CHARACTER SET utf8mb4")) $fallar('No se pudo crear la base: ' . $c->error);
$c->select_db($o['base']);
$tablas = $c->query("SHOW TABLES")->num_rows;
if ($tablas > 0) $fallar("La base '{$o['base']}' ya tiene $tablas tablas. No se instala sobre una base con datos.", 2);

// Estructura y catalogos
if (!$c->multi_query(file_get_contents($esquema))) $fallar('Error al crear las tablas: ' . $c->error, 3);
do { if ($r = $c->store_result()) $r->free(); } while ($c->more_results() && $c->next_result());
if ($c->errno) $fallar('Error al crear las tablas: ' . $c->error, 3);

// Administrador y empresa
$hash = password_hash($o['admin-clave'], PASSWORD_DEFAULT);
$st = $c->prepare("INSERT INTO usuarios (usuario, password, nombre, apellido, id_tipoUsuario, id_EstadoUsuario) VALUES (?, ?, 'Administrador', '', 1, 1)");
$st->bind_param("ss", $o['admin-usuario'], $hash);
if (!$st->execute()) $fallar('No se pudo crear el administrador: ' . $st->error, 3);
$st->close();
$st = $c->prepare("INSERT INTO configuracion (clave, valor) VALUES ('nombre', ?)");
$st->bind_param("s", $o['empresa']);
$st->execute();
$st->close();

// Datos de conexion para la aplicacion
$cfg = "<?php\n// Generado por instalacion/instalar.php\nreturn " . var_export([
    'host' => $o['host'], 'puerto' => (int)$o['puerto'], 'usuario' => $o['usuario'], 'clave' => $o['clave'], 'base' => $o['base'],
], true) . ";\n";
if (@file_put_contents($o['config'], $cfg) === false) $fallar('Base creada, pero no se pudo escribir ' . $o['config'] . '. Creela a mano con config/base_datos.ejemplo.php.', 4);

echo "Instalacion completa.\n"
   . "  Base: {$o['base']} ({$o['host']}:{$o['puerto']})\n"
   . "  Empresa: {$o['empresa']}\n"
   . "  Administrador: {$o['admin-usuario']}\n\n"
   . "Proximos pasos:\n"
   . "  1. Abra el sistema en el navegador e ingrese con el administrador.\n"
   . "  2. Cargue las parcelas (menu Gestion > Parcelas) y los datos de la empresa (Gestion > Empresa).\n"
   . "  3. Programe php cron/respaldo.php y php cron/enviar_avisos.php una vez por dia.\n";
