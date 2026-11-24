-- Ventas e ingresos por proyecto (cosecha, hacienda, otros). Permite calcular el resultado de cada proyecto.
-- Ejecutar una sola vez. Es seguro repetirlo: usa IF NOT EXISTS.
CREATE TABLE IF NOT EXISTS `ventas` (
  `id_Venta` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `id_Proyecto` INT NOT NULL,
  `fecha` DATE NOT NULL,
  `concepto` VARCHAR(150) NOT NULL,
  `cantidad` DECIMAL(14,2) DEFAULT NULL,
  `unidad` VARCHAR(20) DEFAULT NULL,
  `importe` DECIMAL(14,2) NOT NULL,
  `id_Moneda` INT NOT NULL,
  `comprador` VARCHAR(100) DEFAULT NULL,
  `nroComprobante` VARCHAR(40) DEFAULT NULL,
  `observaciones` VARCHAR(255) DEFAULT NULL,
  KEY `idx_venta_proyecto` (`id_Proyecto`),
  KEY `idx_venta_fecha` (`fecha`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
