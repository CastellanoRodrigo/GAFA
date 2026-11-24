-- Esquema de compras que espera el codigo (detalleCompra.php, altaCompras.php, cargaCompra.php).
-- El dump "donjuanbd ultima mia 30-07.sql" esta desactualizado: no tiene moneda ni formapago y
-- guarda moneda/formaPago/tipoCompra como texto. Este script solo se usa para PROBAR en una base
-- temporal; no lo ejecutes sobre tu base real sin comparar antes con su estructura actual.

CREATE TABLE moneda (
  id_Moneda INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  moneda VARCHAR(45) NOT NULL
);
INSERT INTO moneda (id_Moneda, moneda) VALUES (1, 'ARS'), (2, 'USD');

CREATE TABLE formapago (
  id_Forma INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  formaPago VARCHAR(45) NOT NULL
);
INSERT INTO formapago (id_Forma, formaPago) VALUES (1, 'Efectivo'), (2, 'Transferencia'), (3, 'Cheque');

CREATE TABLE compras_nueva (
  id_Compras INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  fecha DATE NOT NULL,
  tipoFactura VARCHAR(45) NOT NULL,
  puntoDeVenta INT NOT NULL,
  nroFactura INT NOT NULL,
  tipoDocEmisor VARCHAR(45) NOT NULL,
  nroDocEmisor INT NOT NULL,
  id_Proveedor INT NOT NULL,
  tipoCambio VARCHAR(45) NOT NULL,
  id_Moneda INT NOT NULL,
  importeNeto DECIMAL(10,0) NOT NULL,
  IVA VARCHAR(45) NOT NULL,
  importeTotal DECIMAL(10,0) NOT NULL,
  detalle VARCHAR(150) NOT NULL,
  id_Forma INT NOT NULL
);
INSERT INTO compras_nueva
SELECT c.id_Compras, c.fecha, c.tipoFactura, c.puntoDeVenta, c.nroFactura, c.tipoDocEmisor, c.nroDocEmisor,
       c.id_Proveedor, c.tipoCambio, m.id_Moneda, c.importeNeto, c.IVA, c.importeTotal, c.detalle, f.id_Forma
FROM compras c
JOIN moneda m ON m.moneda = c.moneda
JOIN formapago f ON f.formaPago = c.formaPago;
DROP TABLE compras;
RENAME TABLE compras_nueva TO compras;

CREATE TABLE compraproyecto_nueva (
  id_CompraProyecto INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  id_Compras INT NOT NULL,
  id_Proyecto INT DEFAULT NULL,
  id_TipoCompra INT NOT NULL
);
INSERT INTO compraproyecto_nueva (id_CompraProyecto, id_Compras, id_Proyecto, id_TipoCompra)
SELECT id_CompraProyecto, id_Compras, id_Proyecto, IF(tipoCompra = 'Asignada', 2, 1) FROM compraproyecto;
DROP TABLE compraproyecto;
RENAME TABLE compraproyecto_nueva TO compraproyecto;

-- Moneda de la inversion en los detalles de siembra y hacienda (las usa detalleProyecto.php y agrDetalle*.php).
ALTER TABLE detallehacienda ADD COLUMN id_Moneda INT NOT NULL DEFAULT 1, ADD COLUMN tipoCambio DECIMAL(10,2) NOT NULL DEFAULT 1;
ALTER TABLE detallesiembra ADD COLUMN id_Moneda INT NOT NULL DEFAULT 1, ADD COLUMN tipoCambio DECIMAL(10,2) NOT NULL DEFAULT 1;
-- Un proyecto de siembra en dolares, para probar totales discriminados por moneda.
UPDATE detallesiembra SET id_Moneda = 2, tipoCambio = 1000 WHERE id_Proyecto = 1;
