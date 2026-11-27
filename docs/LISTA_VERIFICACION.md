# Lista de verificación después de la puesta en marcha

Marcá cada punto a medida que lo pruebes. Si uno falla, no sigas con los siguientes sin resolverlo.

## A. Base de datos
- [ ] Hiciste la copia de seguridad antes de migrar y el archivo `.sql` no está vacío.
- [ ] Corriste las migraciones 1 a 8 en orden y ninguna dio error:
  1. `migracion_password.sql`
  2. `migracion_alquiler.sql`
  3. `migracion_notificaciones.sql`
  4. `migracion_roles_empresa.sql`
  5. `migracion_ventas.sql`
  6. `migracion_hacienda.sql`
  7. `migracion_seguridad.sql`
  8. `migracion_recuperacion.sql` (requiere MariaDB 10.5 o superior)
- [ ] En la base, `DESCRIBE usuarios;` muestra la columna `email`.
- [ ] `SHOW TABLES;` incluye `recuperacion`, `auditoria`, `intentoslogin`, `ventas` y `movimientoshacienda`.

## B. Ingreso y usuarios
- [ ] `login.php` carga sin errores y el pie dice solo "Sistema GAFA".
- [ ] Ingresás como administrador con tu clave normal.
- [ ] En Usuarios, la columna Mail aparece y ningún usuario activo figura como "Sin mail".
- [ ] Creás un usuario de prueba: sin mail te lo rechaza, con mail repetido te lo rechaza y con mail válido lo crea.
- [ ] Un usuario Lector no ve los botones de alta y edición. Un Operador no ve Gestión.

## C. Recuperación de contraseña
- [ ] En el login, "¿Olvidaste tu contraseña?" abre `recuperar.php`.
- [ ] Pedís el enlace con tu usuario de prueba. La pantalla dice siempre el mismo mensaje.
- [ ] El mail llega en pocos minutos, con el nombre de la empresa y un enlace que empieza con tu `url_base`.
- [ ] El enlace abre el formulario con el usuario correcto.
- [ ] Una clave de menos de 8 caracteres se rechaza. Dos claves distintas también.
- [ ] Con una clave válida vuelve al login con el aviso "Contraseña actualizada", y la clave nueva funciona.
- [ ] Abrís el mismo enlace otra vez: dice que no es válido.
- [ ] Pedís dos enlaces seguidos: solo sirve el segundo.
- [ ] Pedís un enlace con un usuario inventado: la pantalla responde igual y no llega ningún mail.
- [ ] Pedís 4 veces seguidas con el mismo usuario: solo llegan 3 mails.
- [ ] Bloqueás a tu usuario de prueba con 5 claves malas. Recuperar la clave le levanta el bloqueo.

## D. Seguridad
- [ ] La dirección del sitio empieza con `https://` y el navegador no avisa de nada.
- [ ] Entrás a `auditoria.php` como administrador y ves los pedidos, envíos y cambios de clave que hiciste.
- [ ] Entrar a `auditoria.php` sin sesión te manda al login.
- [ ] `config/notificaciones.php` no aparece en `git status`, para que no se suba la clave del mail.
- [ ] Cambiaste las claves de administrador que vinieran de pruebas o por defecto.

## E. Funciones del negocio
- [ ] Cargás una venta en un proyecto y aparece en Ventas, con ARS y USD separados.
- [ ] Resultados por proyecto muestra ventas + alquiler cobrado − compras asignadas, por moneda.
- [ ] Cargás una entrada y una salida de hacienda. El stock se actualiza y no deja quedar negativo.
- [ ] Una venta de hacienda con importe aparece también en Ventas.
- [ ] Las exportaciones a Excel de Ventas y Resultados se descargan y abren bien.
- [ ] Inicio muestra las tarjetas según el rol que ingresó.

## F. Copias de seguridad
- [ ] Hacés una copia desde el sistema y el archivo se genera.
- [ ] Probás restaurarla en una base vacía, no en la real. Una copia que no se probó no cuenta.

## G. Limpieza final
- [ ] Borrás los usuarios y datos de prueba que creaste.
- [ ] Vaciás `storage/outbox` si quedaron mails de prueba guardados.
- [ ] Anotás dónde están guardadas la clave del mail y las copias de seguridad.

## Si algo falla
- Si el mail de recuperación no llega, mirá el Registro de actividad: `recuperacion_error` trae el motivo.
- HTTPS depende del servidor web (Apache, Nginx o el hosting), no del sistema.
