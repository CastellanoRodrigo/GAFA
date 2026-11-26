-- Seguridad: bloqueo de intentos de ingreso fallidos y registro de actividad (quien hizo que).
-- Ejecutar una sola vez. Es seguro repetirlo: usa IF NOT EXISTS.
CREATE TABLE IF NOT EXISTS `intentoslogin` (
  `id_Intento` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `usuario` VARCHAR(60) NOT NULL,
  `ip` VARCHAR(45) NOT NULL,
  `fecha` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_intento_usuario` (`usuario`, `fecha`),
  KEY `idx_intento_ip` (`ip`, `fecha`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `auditoria` (
  `id_Auditoria` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `fecha` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `usuario` VARCHAR(60) DEFAULT NULL,
  `accion` VARCHAR(40) NOT NULL,
  `detalle` VARCHAR(255) DEFAULT NULL,
  `ip` VARCHAR(45) DEFAULT NULL,
  KEY `idx_auditoria_fecha` (`fecha`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
