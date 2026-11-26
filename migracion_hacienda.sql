-- Movimientos de hacienda: compras, nacimientos, ventas y muertes de cabezas por proyecto.
-- Stock actual = cabezas iniciales (detallehacienda) + entradas - salidas.
-- Ejecutar una sola vez. Es seguro repetirlo: usa IF NOT EXISTS.
CREATE TABLE IF NOT EXISTS `movimientoshacienda` (
  `id_Movimiento` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `id_Proyecto` INT NOT NULL,
  `fecha` DATE NOT NULL,
  `tipo` VARCHAR(15) NOT NULL COMMENT 'Compra, Nacimiento, Venta, Muerte',
  `cantidad` INT NOT NULL,
  `observaciones` VARCHAR(255) DEFAULT NULL,
  `id_Venta` INT DEFAULT NULL COMMENT 'Venta asociada en la tabla ventas, si se cargo el importe',
  KEY `idx_mov_proyecto` (`id_Proyecto`),
  KEY `idx_mov_fecha` (`fecha`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
