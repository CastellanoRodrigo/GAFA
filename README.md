# GAFA — Ganadería y Agricultura de Fácil Administración

Sistema web para administrar un establecimiento agropecuario: **proyectos de siembra, hacienda y alquiler de parcelas**, compras y gastos, cobros de alquileres, estadísticas y **avisos de vencimientos y pagos por mail y WhatsApp**.

Hecho en PHP + MariaDB/MySQL. Se instala en una PC o servidor con XAMPP (Windows) o LAMP (Linux). Funciona en el navegador, también en celular.

## Qué hace

| Módulo | Qué resuelve |
|---|---|
| **Proyectos** | Siembra, hacienda y alquiler por parcela; estados; filtros; exportación a Excel con inversión total por moneda |
| **Hacienda** | Stock de cabezas por proyecto: compras, nacimientos, ventas y muertes; una venta con importe también se registra en Ventas |
| **Parcelas** | Hectáreas totales, en uso y libres de cada parcela |
| **Compras y gastos** | Carga de facturas con PDF, asignación a proyectos, filtros, totales **separados en pesos y dólares**, Excel |
| **Ventas y resultados** | Registro de ventas (cosecha, hacienda, otros) en pesos o dólares; **resultado por proyecto** = ventas + alquiler cobrado − compras asignadas, siempre separado por moneda; Excel |
| **Alquileres** | Contratos con inquilino, canon y periodicidad; cronograma de cuotas; registro de cobros; estados (vigente / por vencer / vencido) |
| **Avisos** | Cuotas vencidas, próximas y contratos por vencer; envío por **mail** y **WhatsApp**; resumen diario al dueño |
| **Usuarios y roles** | Administrador, Operador (carga datos) y Lector (solo consulta) |
| **Empresa** | Nombre, logo, color y datos de contacto propios de cada cliente |
| **Copias de seguridad** | Descarga manual y copia diaria automática, con retención |

Todos los importes muestran su moneda (`$` ARS, `US$` USD) y los totales nunca mezclan monedas.

## Instalación para una empresa nueva

Requisitos: PHP 8.1 o superior con las extensiones `mysqli`, `curl`, `fileinfo` y `gd`; MariaDB 10.4+ (la que trae XAMPP).

1. Copiar la carpeta del sistema a `htdocs` (XAMPP) o al directorio web.
2. Iniciar Apache y MariaDB.
3. Desde una consola, en la carpeta del sistema:

   ```
   php instalacion/instalar.php --usuario=root --clave= --base=gafa --admin-clave=UnaClaveSegura --admin-email=dueno@miempresa.com --empresa="Estancia La Aurora"
   ```

   Esto crea la base **vacía** (sin datos de ejemplo), el usuario administrador (`admin`, con la clave que indiques) y el archivo `config/base_datos.php`. Opciones: `--host`, `--puerto` (XAMPP suele usar 3306), `--admin-usuario`.
4. Abrir `http://localhost/GAFA/login.php` e ingresar.
5. En **Gestión**: cargar las **parcelas**, los **datos de la empresa** (logo y color) y los **usuarios**.
6. Programar las tareas diarias (ver más abajo).

El instalador se niega a trabajar sobre una base que ya tiene tablas.

## Actualizar una base existente

Si ya tenés el sistema con datos, ejecutá **una vez y en este orden** sobre tu base (phpMyAdmin → Importar), saltando las que ya aplicaste:

1. `migracion_password.sql` — claves con hash
2. `migracion_alquiler.sql` — contratos y cobros de alquiler
3. `migracion_notificaciones.sql` — contacto del inquilino y registro de avisos
4. `migracion_roles_empresa.sql` — rol Operador y datos de la empresa
5. `migracion_ventas.sql` — ventas e ingresos por proyecto
6. `migracion_hacienda.sql` — movimientos de hacienda y stock
7. `migracion_seguridad.sql` — bloqueo de intentos de ingreso y registro de actividad
8. `migracion_recuperacion.sql` — mail de cada usuario y recuperación de contraseña (requiere MariaDB 10.5 o superior). Después, cargá el mail de cada usuario en Gestión → Usuarios.

Cada pantalla nueva avisa con un mensaje claro si falta ejecutar alguna.

> El archivo `donjuanbd ultima mia 30-07.sql` es el volcado original (con datos de prueba) y no incluye las tablas de monedas y formas de pago que el sistema espera. Para empresas nuevas usá el instalador.

## Roles

| Rol | Puede |
|---|---|
| **Administrador** | Todo: usuarios, proveedores, categorías, empresa, copias, enviar avisos |
| **Operador** | Cargar y editar proyectos, compras, ventas, parcelas, contratos de alquiler y cobros |
| **Lector** | Solo consultar y exportar |

## Avisos por mail y WhatsApp

1. Copiar `config/notificaciones.ejemplo.php` como `config/notificaciones.php` y completar.
2. **Mail:** driver `smtp` con tu servidor (en Gmail, una "contraseña de aplicación"). El driver `archivo` no envía: guarda el mensaje en `storage/outbox` para probar.
3. **WhatsApp:** driver `enlace` (por defecto) abre WhatsApp con el mensaje escrito y se envía con un toque. El driver `cloud_api` envía automáticamente con WhatsApp Business (requiere cuenta de Meta y plantillas aprobadas).
4. Cargar el mail y el teléfono del inquilino en la pantalla del contrato.
5. Programar `php cron/enviar_avisos.php` una vez por día.

Las cuotas vencidas de un contrato se avisan en **un solo mensaje**, y no se repite un aviso reciente (por defecto, 7 días).

## Tareas diarias

Windows (Programador de tareas → Acción: iniciar un programa):

```
php.exe  D:\xampp\htdocs\GAFA\cron\enviar_avisos.php
php.exe  D:\xampp\htdocs\GAFA\cron\respaldo.php
```

Linux (`crontab -e`): `0 8 * * * php /var/www/GAFA/cron/enviar_avisos.php` y `30 2 * * * php /var/www/GAFA/cron/respaldo.php`.

## Copias de seguridad

Gestión → **Copias de seguridad**: descargar una copia en el momento o guardarla en el servidor. `cron/respaldo.php` guarda una por día y conserva las últimas 14. **Guardá una copia fuera de la computadora del sistema.** Para restaurar, importar el `.sql` en la base.

## Pruebas

```
php -S localhost:8000 -t .
php tests/run.php http://localhost:8000
```

Necesita una base de prueba cargada con el volcado original y las migraciones, más `tests/esquema_compras.sql`. Las pruebas crean y borran datos: **no las ejecutes sobre una base real**. El envío por SMTP se prueba contra un servidor de mentira (`tests/fake_smtp.php`).

## Versión celular (app instalable)

El sistema se adapta a pantallas de celular: menú desplegable, barra inferior de accesos rápidos (Inicio, Compras, Ventas, Hacienda, Avisos), formularios y filtros a todo el ancho, y los listados se muestran como tarjetas. Además es una **PWA**: se puede instalar en la pantalla de inicio y se abre como una app, sin tienda de aplicaciones.

- Archivos: `Css/movil.css`, `pwa/movil.js`, `manifest.php` (toma el nombre y el color de la empresa), `sw.js` y `sin-conexion.html`.
- El service worker guarda solo estilos, scripts e imágenes. **Las páginas con datos nunca se guardan en el teléfono**; sin conexión se muestra una pantalla de aviso.
- La instalación exige **HTTPS** (o `localhost`). En Android/Chrome aparece el botón "Instalar"; en iPhone: Safari → Compartir → "Agregar a inicio".
- Si cambiás `Css/movil.css` o `pwa/movil.js`, el teléfono toma la versión nueva en la siguiente carga con conexión.

## Qué está probado y qué no

- Probado de forma automática: login, permisos por rol, compras, ventas y resultados, alquileres, cobros, monedas, exportaciones, avisos, copias, instalador, subida de logo y archivos.
- **No verificado contra servicios reales:** el envío por Gmail/Outlook (se probó el protocolo SMTP contra un servidor de mentira) y la API de WhatsApp Business. Hacé un primer envío de prueba con tus credenciales.
- El aspecto celular se revisó con capturas en Chrome emulando un teléfono de 390 px de ancho. No se probó en un teléfono físico ni en iPhone/Safari.

## Seguridad

Bloqueo del usuario tras 5 intentos fallidos en 15 minutos (y de la IP tras 20), registro de actividad (Gestión → Registro de actividad, solo administrador), consultas preparadas en todo el sistema, claves con hash, permisos por rol en el servidor, subida de archivos validada (PDF y logo), exportaciones sin SQL del cliente. En Apache, `config/`, `storage/` y `uploads/` incluyen `.htaccess` que impide leer claves o copias por web y ejecutar código en `uploads/`. En otros servidores, bloqueá esas carpetas desde la configuración del servidor. **Si el sistema se publica en internet, usalo siempre con HTTPS** (certificado en el servidor): sin eso, las claves viajan sin cifrar. **Recuperación de contraseña:** cada usuario tiene un mail. En el ingreso, "¿Olvidaste tu contraseña?" envía un enlace de un solo uso que vence en 60 minutos (solo se guarda su hash, máximo 3 pedidos por hora por usuario, y la respuesta es siempre la misma para no revelar qué usuarios existen). Requiere el envío de mails configurado (ver *Avisos por mail y WhatsApp*); en producción conviene fijar `url_base` en `config/notificaciones.php` para que los enlaces apunten a la dirección pública del sistema. El administrador también puede cambiar una clave desde Gestión → Usuarios.

## Estructura

```
Modelo/        lógica compartida (Auth, Moneda, Alquileres, Notificaciones, Empresa, Respaldo...)
Template/      cabecera y pie
Css/           tema.css (estilos) y estiloLogin.css
cron/          tareas diarias: enviar_avisos.php, respaldo.php
instalacion/   instalar.php, esquema_limpio.sql, generar_esquema.php
config/        archivos de configuración (las claves reales no se suben a git)
tests/         pruebas automáticas
```

Si cambia la estructura de la base, ejecutá `php instalacion/generar_esquema.php` para actualizar el esquema de instalación (una prueba avisa si te olvidás).
