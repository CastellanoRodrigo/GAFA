-- Recuperacion de contrasena: cada usuario tiene un mail y puede pedir un enlace de un solo uso.
-- Ejecutar una sola vez. Es seguro repetirlo (requiere MariaDB 10.5+, la que trae XAMPP actual).
-- Despues de ejecutarla, cargar el mail de cada usuario en Gestion > Usuarios.
ALTER TABLE `usuarios` ADD COLUMN IF NOT EXISTS `email` VARCHAR(120) DEFAULT NULL;
ALTER TABLE `usuarios` ADD UNIQUE KEY IF NOT EXISTS `uq_usuario_email` (`email`);

CREATE TABLE IF NOT EXISTS `recuperacion` (
  `id_Recuperacion` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `id_usuario` INT NOT NULL,
  `tokenHash` CHAR(64) NOT NULL COMMENT 'SHA-256 del enlace; el enlace en si nunca se guarda',
  `creado` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `expira` DATETIME NOT NULL,
  `usado` TINYINT NOT NULL DEFAULT 0,
  `ip` VARCHAR(45) DEFAULT NULL,
  UNIQUE KEY `uq_recup_token` (`tokenHash`),
  KEY `idx_recup_usuario` (`id_usuario`, `creado`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
