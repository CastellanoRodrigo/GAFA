-- Esquema limpio de GAFA (sin datos de ejemplo)
-- Generada el 2026-11-26 01:54:14
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS=0;
SET SQL_MODE='NO_AUTO_VALUE_ON_ZERO';

DROP TABLE IF EXISTS `archivos`;
CREATE TABLE `archivos` (
  `id_archivo` int(5) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(255) NOT NULL,
  `url` varchar(255) NOT NULL,
  PRIMARY KEY (`id_archivo`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_uca1400_ai_ci;

DROP TABLE IF EXISTS `auditoria`;
CREATE TABLE `auditoria` (
  `id_Auditoria` int(11) NOT NULL AUTO_INCREMENT,
  `fecha` datetime NOT NULL DEFAULT current_timestamp(),
  `usuario` varchar(60) DEFAULT NULL,
  `accion` varchar(40) NOT NULL,
  `detalle` varchar(255) DEFAULT NULL,
  `ip` varchar(45) DEFAULT NULL,
  PRIMARY KEY (`id_Auditoria`),
  KEY `idx_auditoria_fecha` (`fecha`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

DROP TABLE IF EXISTS `categoria`;
CREATE TABLE `categoria` (
  `id_Categoria` int(11) NOT NULL AUTO_INCREMENT,
  `nombreCategoria` varchar(45) NOT NULL,
  PRIMARY KEY (`id_Categoria`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

INSERT INTO `categoria` (`id_Categoria`, `nombreCategoria`) VALUES
('1', 'Ternero'),
('2', 'Novillito'),
('3', 'Vaquillona'),
('4', 'Novillo'),
('5', 'Vaca'),
('6', 'Toro');

DROP TABLE IF EXISTS `cobrosalquiler`;
CREATE TABLE `cobrosalquiler` (
  `id_Cobro` int(11) NOT NULL AUTO_INCREMENT,
  `id_DetalleAlquiler` int(11) NOT NULL,
  `fechaVencimiento` date NOT NULL,
  `monto` decimal(14,2) NOT NULL,
  `fechaPago` date DEFAULT NULL,
  PRIMARY KEY (`id_Cobro`),
  KEY `idx_cobro_contrato` (`id_DetalleAlquiler`),
  KEY `idx_cobro_vencimiento` (`fechaVencimiento`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

DROP TABLE IF EXISTS `compraproyecto`;
CREATE TABLE `compraproyecto` (
  `id_CompraProyecto` int(11) NOT NULL AUTO_INCREMENT,
  `id_Compras` int(11) NOT NULL,
  `id_Proyecto` int(11) DEFAULT NULL,
  `id_TipoCompra` int(11) NOT NULL,
  PRIMARY KEY (`id_CompraProyecto`)
) ENGINE=InnoDB AUTO_INCREMENT=37 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

DROP TABLE IF EXISTS `compras`;
CREATE TABLE `compras` (
  `id_Compras` int(11) NOT NULL AUTO_INCREMENT,
  `fecha` date NOT NULL,
  `tipoFactura` varchar(45) NOT NULL,
  `puntoDeVenta` int(11) NOT NULL,
  `nroFactura` int(11) NOT NULL,
  `tipoDocEmisor` varchar(45) NOT NULL,
  `nroDocEmisor` int(11) NOT NULL,
  `id_Proveedor` int(11) NOT NULL,
  `tipoCambio` varchar(45) NOT NULL,
  `id_Moneda` int(11) NOT NULL,
  `importeNeto` decimal(10,0) NOT NULL,
  `IVA` varchar(45) NOT NULL,
  `importeTotal` decimal(10,0) NOT NULL,
  `detalle` varchar(150) NOT NULL,
  `id_Forma` int(11) NOT NULL,
  PRIMARY KEY (`id_Compras`)
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

DROP TABLE IF EXISTS `configuracion`;
CREATE TABLE `configuracion` (
  `clave` varchar(50) NOT NULL,
  `valor` text DEFAULT NULL,
  PRIMARY KEY (`clave`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

DROP TABLE IF EXISTS `cultivos`;
CREATE TABLE `cultivos` (
  `id_Cultivo` int(11) NOT NULL AUTO_INCREMENT,
  `nombreCultivo` varchar(45) NOT NULL,
  `kgSemillaHas` int(11) NOT NULL,
  `rindeEsperadoHas` varchar(45) NOT NULL,
  PRIMARY KEY (`id_Cultivo`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

INSERT INTO `cultivos` (`id_Cultivo`, `nombreCultivo`, `kgSemillaHas`, `rindeEsperadoHas`) VALUES
('1', 'Maiz de Primera', '20', '5000'),
('2', 'Maiz de Segunda', '20', '6000'),
('3', 'Girasol', '20', '2000'),
('4', 'Cebada', '110', '3000'),
('5', 'Avena', '6', '2000'),
('6', 'Alfalfa', '10', '5'),
('7', 'Soja', '70', '2500');

DROP TABLE IF EXISTS `detallealquiler`;
CREATE TABLE `detallealquiler` (
  `id_DetalleAlquiler` int(11) NOT NULL AUTO_INCREMENT,
  `id_Proyecto` int(11) NOT NULL,
  `inquilino` varchar(100) NOT NULL,
  `contacto` varchar(100) DEFAULT NULL,
  `fechaInicio` date NOT NULL,
  `fechaFin` date NOT NULL,
  `canon` decimal(14,2) NOT NULL COMMENT 'Monto por periodo de cobro',
  `periodicidad` varchar(15) NOT NULL,
  `id_Moneda` int(11) NOT NULL,
  `tipoCambio` decimal(10,2) DEFAULT NULL,
  `observaciones` varchar(255) DEFAULT NULL,
  `email` varchar(120) DEFAULT NULL,
  `telefono` varchar(30) DEFAULT NULL,
  PRIMARY KEY (`id_DetalleAlquiler`),
  UNIQUE KEY `uq_alquiler_proyecto` (`id_Proyecto`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

DROP TABLE IF EXISTS `detallehacienda`;
CREATE TABLE `detallehacienda` (
  `id_DetalleHacienda` int(11) NOT NULL AUTO_INCREMENT,
  `id_Proyecto` int(11) NOT NULL,
  `fechaInicio` date NOT NULL,
  `fechaCierre` date NOT NULL,
  `cantidadCabezas` int(11) NOT NULL,
  `id_Categoria` int(11) NOT NULL,
  `inversion` varchar(45) NOT NULL,
  `id_Moneda` int(11) NOT NULL DEFAULT 1,
  `tipoCambio` decimal(10,2) NOT NULL DEFAULT 1.00,
  PRIMARY KEY (`id_DetalleHacienda`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

DROP TABLE IF EXISTS `detallesiembra`;
CREATE TABLE `detallesiembra` (
  `id_DetalleSiembra` int(11) NOT NULL AUTO_INCREMENT,
  `id_Proyecto` int(11) NOT NULL,
  `fechaInicio` date NOT NULL,
  `fechaCierre` date NOT NULL,
  `id_Cultivo` int(11) NOT NULL,
  `inversion` double NOT NULL,
  `id_Moneda` int(11) NOT NULL DEFAULT 1,
  `tipoCambio` decimal(10,2) NOT NULL DEFAULT 1.00,
  PRIMARY KEY (`id_DetalleSiembra`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

DROP TABLE IF EXISTS `estadoproyecto`;
CREATE TABLE `estadoproyecto` (
  `id_EstadoProyecto` int(11) NOT NULL AUTO_INCREMENT,
  `estado` varchar(45) NOT NULL,
  PRIMARY KEY (`id_EstadoProyecto`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

INSERT INTO `estadoproyecto` (`id_EstadoProyecto`, `estado`) VALUES
('1', 'No iniciado'),
('2', 'Iniciado'),
('3', 'Finalizado');

DROP TABLE IF EXISTS `estadousuario`;
CREATE TABLE `estadousuario` (
  `id_EstadoUsuario` int(1) NOT NULL AUTO_INCREMENT,
  `estadoUsuario` varchar(10) NOT NULL,
  PRIMARY KEY (`id_EstadoUsuario`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_uca1400_ai_ci;

INSERT INTO `estadousuario` (`id_EstadoUsuario`, `estadoUsuario`) VALUES
('1', 'Activo'),
('2', 'Inactivo');

DROP TABLE IF EXISTS `formapago`;
CREATE TABLE `formapago` (
  `id_Forma` int(11) NOT NULL AUTO_INCREMENT,
  `formaPago` varchar(45) NOT NULL,
  PRIMARY KEY (`id_Forma`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

INSERT INTO `formapago` (`id_Forma`, `formaPago`) VALUES
('1', 'Efectivo'),
('2', 'Transferencia'),
('3', 'Cheque');

DROP TABLE IF EXISTS `intentoslogin`;
CREATE TABLE `intentoslogin` (
  `id_Intento` int(11) NOT NULL AUTO_INCREMENT,
  `usuario` varchar(60) NOT NULL,
  `ip` varchar(45) NOT NULL,
  `fecha` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_Intento`),
  KEY `idx_intento_usuario` (`usuario`,`fecha`),
  KEY `idx_intento_ip` (`ip`,`fecha`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

DROP TABLE IF EXISTS `moneda`;
CREATE TABLE `moneda` (
  `id_Moneda` int(11) NOT NULL AUTO_INCREMENT,
  `moneda` varchar(45) NOT NULL,
  PRIMARY KEY (`id_Moneda`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

INSERT INTO `moneda` (`id_Moneda`, `moneda`) VALUES
('1', 'ARS'),
('2', 'USD');

DROP TABLE IF EXISTS `movimientoshacienda`;
CREATE TABLE `movimientoshacienda` (
  `id_Movimiento` int(11) NOT NULL AUTO_INCREMENT,
  `id_Proyecto` int(11) NOT NULL,
  `fecha` date NOT NULL,
  `tipo` varchar(15) NOT NULL COMMENT 'Compra, Nacimiento, Venta, Muerte',
  `cantidad` int(11) NOT NULL,
  `observaciones` varchar(255) DEFAULT NULL,
  `id_Venta` int(11) DEFAULT NULL COMMENT 'Venta asociada en la tabla ventas, si se cargo el importe',
  PRIMARY KEY (`id_Movimiento`),
  KEY `idx_mov_proyecto` (`id_Proyecto`),
  KEY `idx_mov_fecha` (`fecha`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

DROP TABLE IF EXISTS `notificaciones`;
CREATE TABLE `notificaciones` (
  `id_Notificacion` int(11) NOT NULL AUTO_INCREMENT,
  `tipo` varchar(30) NOT NULL,
  `id_Referencia` int(11) NOT NULL,
  `canal` varchar(12) NOT NULL,
  `destinatario` varchar(150) NOT NULL,
  `asunto` varchar(200) DEFAULT NULL,
  `mensaje` text DEFAULT NULL,
  `estado` varchar(12) NOT NULL,
  `detalleError` varchar(255) DEFAULT NULL,
  `fecha` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_Notificacion`),
  KEY `idx_notif_ref` (`tipo`,`id_Referencia`,`canal`,`fecha`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

DROP TABLE IF EXISTS `parcela`;
CREATE TABLE `parcela` (
  `id_Parcela` int(11) NOT NULL AUTO_INCREMENT,
  `dimension` int(11) NOT NULL,
  PRIMARY KEY (`id_Parcela`)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

DROP TABLE IF EXISTS `proveedores`;
CREATE TABLE `proveedores` (
  `id_Proveedor` int(2) NOT NULL AUTO_INCREMENT,
  `proveedor` varchar(100) NOT NULL,
  PRIMARY KEY (`id_Proveedor`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_uca1400_ai_ci;

DROP TABLE IF EXISTS `proyectos`;
CREATE TABLE `proyectos` (
  `id_Proyecto` int(11) NOT NULL AUTO_INCREMENT,
  `id_Parcela` int(11) NOT NULL,
  `nombreProyecto` varchar(45) NOT NULL,
  `id_TipoProyecto` int(11) NOT NULL,
  `cantidadHas` int(11) NOT NULL,
  `id_EstadoProyecto` int(11) NOT NULL,
  PRIMARY KEY (`id_Proyecto`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

DROP TABLE IF EXISTS `tipo_usuario`;
CREATE TABLE `tipo_usuario` (
  `id_tipoUsuario` int(11) NOT NULL AUTO_INCREMENT,
  `tipo` varchar(45) NOT NULL,
  PRIMARY KEY (`id_tipoUsuario`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

INSERT INTO `tipo_usuario` (`id_tipoUsuario`, `tipo`) VALUES
('1', 'Administrador'),
('2', 'Lector'),
('3', 'Operador');

DROP TABLE IF EXISTS `tipoproyecto`;
CREATE TABLE `tipoproyecto` (
  `id_tipoProyecto` int(11) NOT NULL AUTO_INCREMENT,
  `tipoProyecto` varchar(45) NOT NULL,
  PRIMARY KEY (`id_tipoProyecto`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

INSERT INTO `tipoproyecto` (`id_tipoProyecto`, `tipoProyecto`) VALUES
('1', 'Hacienda'),
('2', 'Siembra'),
('3', 'Alquiler');

DROP TABLE IF EXISTS `usuarios`;
CREATE TABLE `usuarios` (
  `id_usuario` int(11) NOT NULL AUTO_INCREMENT,
  `usuario` varchar(45) NOT NULL,
  `password` varchar(255) NOT NULL,
  `nombre` varchar(45) NOT NULL,
  `apellido` varchar(45) NOT NULL,
  `id_tipoUsuario` int(11) NOT NULL,
  `id_EstadoUsuario` int(1) NOT NULL,
  PRIMARY KEY (`id_usuario`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

DROP TABLE IF EXISTS `ventas`;
CREATE TABLE `ventas` (
  `id_Venta` int(11) NOT NULL AUTO_INCREMENT,
  `id_Proyecto` int(11) NOT NULL,
  `fecha` date NOT NULL,
  `concepto` varchar(150) NOT NULL,
  `cantidad` decimal(14,2) DEFAULT NULL,
  `unidad` varchar(20) DEFAULT NULL,
  `importe` decimal(14,2) NOT NULL,
  `id_Moneda` int(11) NOT NULL,
  `comprador` varchar(100) DEFAULT NULL,
  `nroComprobante` varchar(40) DEFAULT NULL,
  `observaciones` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id_Venta`),
  KEY `idx_venta_proyecto` (`id_Proyecto`),
  KEY `idx_venta_fecha` (`fecha`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

SET FOREIGN_KEY_CHECKS=1;
