-- Alquiler de parcelas: contratos y cobros.
-- Ejecutar una sola vez sobre la base de datos (donjuanbd). Es seguro repetirlo: usa IF NOT EXISTS.
-- El tipo de proyecto "Alquiler" (id 3) ya existe en la tabla tipoproyecto.

CREATE TABLE IF NOT EXISTS `detallealquiler` (
  `id_DetalleAlquiler` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `id_Proyecto` INT NOT NULL,
  `inquilino` VARCHAR(100) NOT NULL,
  `contacto` VARCHAR(100) DEFAULT NULL,
  `fechaInicio` DATE NOT NULL,
  `fechaFin` DATE NOT NULL,
  `canon` DECIMAL(14,2) NOT NULL COMMENT 'Monto por periodo de cobro',
  `periodicidad` VARCHAR(15) NOT NULL,
  `id_Moneda` INT NOT NULL,
  `tipoCambio` DECIMAL(10,2) DEFAULT NULL,
  `observaciones` VARCHAR(255) DEFAULT NULL,
  UNIQUE KEY `uq_alquiler_proyecto` (`id_Proyecto`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `cobrosalquiler` (
  `id_Cobro` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `id_DetalleAlquiler` INT NOT NULL,
  `fechaVencimiento` DATE NOT NULL,
  `monto` DECIMAL(14,2) NOT NULL,
  `fechaPago` DATE DEFAULT NULL,
  KEY `idx_cobro_contrato` (`id_DetalleAlquiler`),
  KEY `idx_cobro_vencimiento` (`fechaVencimiento`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
