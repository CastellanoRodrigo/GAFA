<?php
// Servidor SMTP de mentira para probar el envio de mail sin credenciales reales.
// Uso: php tests/fake_smtp.php PUERTO ARCHIVO_SALIDA
// Acepta una conexion, responde como un SMTP simple (EHLO, AUTH LOGIN, MAIL, RCPT, DATA, QUIT)
// y deja en ARCHIVO_SALIDA lo que recibio.
[$_, $puerto, $salida] = $argv;
$srv = stream_socket_server("tcp://127.0.0.1:$puerto", $errno, $errstr);
if (!$srv) { fwrite(STDERR, "No se pudo abrir el puerto: $errstr\n"); exit(1); }
$conn = @stream_socket_accept($srv, 25);
if (!$conn) { exit(2); }

$log = [];
$datos = '';
$enData = false;
fwrite($conn, "220 fake ESMTP\r\n");
while (($linea = fgets($conn)) !== false) {
    $l = rtrim($linea, "\r\n");
    if ($enData) {
        if ($l === '.') { $enData = false; $log[] = 'DATA_FIN'; fwrite($conn, "250 OK\r\n"); }
        else { $datos .= $l . "\r\n"; }
        continue;
    }
    $log[] = $l;
    $u = strtoupper($l);
    if (strpos($u, 'EHLO') === 0) {
        fwrite($conn, "250-fake\r\n250 AUTH LOGIN\r\n");
    } elseif ($u === 'AUTH LOGIN') {
        fwrite($conn, "334 VXNlcm5hbWU6\r\n");
        $log[] = 'USUARIO:' . base64_decode(rtrim(fgets($conn)));
        fwrite($conn, "334 UGFzc3dvcmQ6\r\n");
        $log[] = 'CLAVE:' . base64_decode(rtrim(fgets($conn)));
        fwrite($conn, "235 autenticado\r\n");
    } elseif (strpos($u, 'MAIL FROM') === 0 || strpos($u, 'RCPT TO') === 0) {
        fwrite($conn, "250 OK\r\n");
    } elseif ($u === 'DATA') {
        $enData = true;
        fwrite($conn, "354 adelante\r\n");
    } elseif ($u === 'QUIT') {
        fwrite($conn, "221 chau\r\n");
        break;
    } else {
        fwrite($conn, "500 comando desconocido\r\n");
    }
}
file_put_contents($salida, implode("\n", $log) . "\n---\n" . $datos);
