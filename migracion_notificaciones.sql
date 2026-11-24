-- Avisos por mail y WhatsApp: datos de contacto del inquilino y registro de avisos enviados.
-- Ejecutar una sola vez (requiere MariaDB, que es el motor de XAMPP; es seguro repetirlo).
-- Requiere haber ejecutado antes migracion_alquiler.sql.

ALTER TABLE `detallealquiler`
  ADD COLUMN IF NOT EXISTS `email` VARCHAR(120) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `telefono` VARCHAR(30) DEFAULT NULL;

CREATE TABLE IF NOT EXISTS `notificaciones` (
  `id_Notificacion` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `tipo` VARCHAR(30) NOT NULL,
  `id_Referencia` INT NOT NULL,
  `canal` VARCHAR(12) NOT NULL,
  `destinatario` VARCHAR(150) NOT NULL,
  `asunto` VARCHAR(200) DEFAULT NULL,
  `mensaje` TEXT,
  `estado` VARCHAR(12) NOT NULL,
  `detalleError` VARCHAR(255) DEFAULT NULL,
  `fecha` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_notif_ref` (`tipo`, `id_Referencia`, `canal`, `fecha`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
