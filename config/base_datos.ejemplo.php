<?php
// Datos de conexion a la base. El instalador (instalacion/instalar.php) crea config/base_datos.php
// con estos campos; tambien se puede copiar este archivo y completarlo a mano.
// config/base_datos.php NO se sube a git (contiene la clave de la base).
return [
    'host' => 'localhost',
    'puerto' => 3306,       // XAMPP suele usar 3306 (en este proyecto de desarrollo se uso 3307)
    'usuario' => 'root',
    'clave' => '',
    'base' => 'gafa',
];
