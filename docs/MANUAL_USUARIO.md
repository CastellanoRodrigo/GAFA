# Manual de usuario — GAFA

## Primeros pasos
1. Ingresá con tu usuario y contraseña. Arriba a la derecha ves tu nombre y tu rol.
2. El **Inicio** tiene accesos a Proyectos, Compras, Avisos, Estadísticas y Gestión.
3. El número junto a **Avisos** en el menú indica cuántos vencimientos o pagos requieren atención.

## Parcelas (Gestión → Parcelas)
Cada parcela tiene su número y sus hectáreas. La pantalla muestra cuántas hectáreas están en uso por proyectos iniciados y cuántas quedan libres. Agregá primero las parcelas: sin ellas no se pueden crear proyectos.

## Proyectos
- **Alta proyecto:** elegí la parcela, el tipo (Hacienda, Siembra o Alquiler), las hectáreas y el estado.
- **Detalle Siembra / Hacienda:** cargá cultivo o categoría, fechas, inversión y su moneda.
- **Listado de proyectos:** filtrá por tipo, estado, parcela o nombre y exportá a Excel. Arriba ves la inversión inicial **en pesos y en dólares por separado**.

## Alquiler de parcelas
1. Creá un proyecto de tipo **Alquiler**.
2. En **Detalle Alquiler** cargá inquilino, fechas del contrato, canon por período, cada cuánto se cobra y la moneda. Cargá también su **mail y WhatsApp** para los avisos.
3. El sistema genera el cronograma de cuotas. En **Alquileres y cobros** ves el estado de cada contrato (vigente, por vencer, vencido) y lo que se debe.
4. Dentro del contrato, **Registrar cobro** marca una cuota como cobrada (con la fecha) y **Deshacer cobro** la revierte.

## Compras y gastos
- **Cargar compra:** fecha, factura, proveedor, importes, moneda, forma de pago y el PDF de la factura. Podés asignarla a un proyecto o dejarla como general.
- **Ver compras:** filtrá por fechas, proveedor, número de factura o detalle. Cada compra muestra su moneda y los totales se separan en **pesos** y **dólares**.
- **Asignar compras:** pasa una compra general a un proyecto.

## Ventas y resultados por proyecto
- **Ventas → Ventas e ingresos:** registrá cada venta (proyecto, fecha, concepto, cantidad, importe, **moneda** y comprador). Los totales se muestran separados en pesos y en dólares. Se puede filtrar y exportar a Excel.
- **Ventas → Resultados por proyecto:** muestra, por proyecto y por moneda, las ventas, el alquiler cobrado, los gastos (compras asignadas al proyecto) y el **resultado** (ganancia o pérdida). Tocá **Ver** para el detalle de un proyecto.
- Para que el resultado sea correcto, asigná cada compra a su proyecto (Compras → Asignar compras) y marcá los cobros de alquiler.
- Los pesos y los dólares nunca se suman ni se convierten entre sí.

## Avisos de vencimientos y pagos
- **Avisos** lista las cuotas vencidas (agrupadas por contrato), las cuotas que vencen pronto y los contratos por terminar.
- Cada fila tiene **Mail** y **WhatsApp**. WhatsApp abre la conversación con el mensaje listo; solo tocás enviar.
- Una tilde ✓ indica que ya se avisó por ese canal. No se repite el mismo aviso antes de 7 días.
- **Enviar por mail todos los pendientes** manda a todos los inquilinos con mail, saltando los ya avisados.
- Solo los administradores pueden enviar avisos.

## Usuarios y roles (solo administrador)
- **Administrador:** todo.
- **Operador:** carga y edita datos (proyectos, compras, ventas, parcelas, alquileres, cobros).
- **Lector:** solo consulta y exporta.

Los nombres se guardan siempre con mayúscula inicial.

## Empresa (solo administrador)
Gestión → **Empresa y marca**: nombre, logo, color principal y datos de contacto. El nombre y el logo aparecen en la cabecera, el pie, el ingreso y los mails de aviso.

## Copias de seguridad (solo administrador)
Gestión → **Copias de seguridad**: descargá una copia cuando quieras. Guardala en un pendrive o en la nube, no solo en la computadora del sistema.

## Preguntas frecuentes
- **No veo el menú "Alta proyecto":** tu usuario es Lector. Pedile a un administrador el rol Operador.
- **Una pantalla dice que falta ejecutar una migración:** avisale al administrador técnico; está explicado en el README.
- **El mail dice "Modo prueba":** los avisos todavía no están conectados a un servidor de correo real.
