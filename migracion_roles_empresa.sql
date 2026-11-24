-- Version comercial: rol Operador y datos de la empresa (marca blanca).
-- Ejecutar una sola vez (es seguro repetirlo). Requiere MariaDB (el motor de XAMPP).

-- Roles: 1 = Administrador, 2 = Lector (solo consulta), 3 = Operador (carga y edita datos operativos)
INSERT INTO `tipo_usuario` (`id_tipoUsuario`, `tipo`)
SELECT 3, 'Operador' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `tipo_usuario` WHERE `id_tipoUsuario` = 3);

-- Datos de la empresa: nombre, logo, color, contacto (clave/valor)
CREATE TABLE IF NOT EXISTS `configuracion` (
  `clave` VARCHAR(50) NOT NULL PRIMARY KEY,
  `valor` TEXT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
