# Despliegue en cPanel

## Requisitos

- PHP 8.1 o superior con PDO MySQL, mbstring, DOM, fileinfo y Phar o ZipArchive.
- MySQL o MariaDB con codificación `utf8mb4`.
- Apache con soporte para archivos `.htaccess`.

## Preparación

Puedes generar un ZIP limpio mediante:

```powershell
powershell -ExecutionPolicy Bypass -File scripts/build_cpanel_package.ps1
```

El resultado se crea en `dist/tesis-cpanel.zip` sin Git, herramientas del agente, documentación interna ni credenciales locales.

1. Sube el contenido del proyecto a la carpeta asignada al dominio, normalmente `public_html`.
2. Conserva el archivo `.htaccess` y los `.htaccess` internos. Impiden navegar por configuración, SQL, scripts, almacenamiento y documentación.
3. Copia `app/config/database.local.php.example` como `app/config/database.local.php`.
4. Copia `app/config/app.local.php.example` como `app/config/app.local.php` para activar producción y deshabilitar la recarga automática.
5. Completa las credenciales creadas desde **MySQL Databases** en cPanel. El nombre de la base y el usuario suelen incluir el prefijo de la cuenta.
6. No subas ni confirmes los archivos `*.local.php`; están excluidos por Git porque contienen configuración propia del servidor.
7. Copia `.user.ini.example` como `.user.ini` para ocultar errores técnicos en producción.

## Base de datos

Importa mediante phpMyAdmin, en este orden:

1. `database/migrations/20260715_create_notifications.sql`
2. `database/migrations/20260716_add_notification_archive.sql`
3. `database/migrations/20260718_create_academic_projects.sql`
3. `database/seeds/notifications_demo.sql` solo si necesitas datos demostrativos.

Las migraciones deben conservarse en el repositorio aunque ya se hayan ejecutado, porque permiten reconstruir y versionar la base de datos.

## Permisos

El usuario de PHP debe poder escribir en:

- `storage/calendar`
- `storage/repository`
- `storage/support-materials`

Usa primero permisos `755` para directorios y `644` para archivos. Si el proveedor lo requiere, aplica `775` únicamente a las carpetas de almacenamiento; evita `777`.

## Producción

Configura, cuando cPanel lo permita, las variables:

- `APP_ENV=production`
- `DEV_AUTORELOAD=false`

Si el panel no permite variables de entorno, `app.local.php` aplica estas opciones sin modificar archivos versionados. No guardes credenciales directamente en `app.php`.

## Verificación final

- La raíz abre `index.php` y no un prototipo HTML.
- Las rutas Dashboard, Proyectos, Repositorio, Calendario y Notificaciones responden correctamente.
- Acceder desde el navegador a `/app`, `/database`, `/storage`, `/scripts` o `/z archivos md` devuelve acceso denegado.
- La aplicación puede conectarse a MySQL sin mostrar credenciales ni errores técnicos.
- Las descargas y vistas previas funcionan con archivos privados.
- HTTPS está activo antes de habilitar `session.cookie_secure`.
