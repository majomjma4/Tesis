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

El ZIP de cPanel no incluye SQL ni datos. Prepara la base antes de publicar:

1. Crea una base nueva y vacia con `utf8mb4_unicode_ci`.
2. Importa `database/snapshot.sql` con
   `scripts/import_database.ps1 -DatabaseName <base_nueva>` o con una
   herramienta equivalente que acepte solamente el baseline estructural.
3. Ejecuta `20260901_create_schema_migrations.sql` y registra el marcador del
   baseline con su SHA-256.
4. Para actualizaciones, consulta `schema_migrations` y ejecuta solo archivos
   `UP` pendientes posteriores al baseline. Nunca ejecutes todos los `.sql`, un
   comodin que incluya `_down.sql` ni una restauracion sobre `tesis`.

El importador oficial rechaza la base activa `tesis`, destinos inexistentes y
cualquier destino que ya tenga tablas. Las migraciones historicas se conservan
para auditoria, pero no se reejecutan automaticamente en una instalacion nueva.

## Dependencias y seguridad

El builder usa una allowlist e incluye `vendor` construido desde `composer.lock`,
incluido PHPMailer. El ZIP no contiene SQL, datos productivos de `storage`,
archivos privados, recovery, backups, QA ni fixtures.

El `.htaccess` aplica `nosniff`, politica de referer, same-origin para frames,
Permissions-Policy y una CSP compatible con los scripts inline existentes,
Font Awesome de cdnjs, previews, imagenes `data:`/`blob:` y fetch/XHR interno.
HSTS se agrega solo para produccion HTTPS fuera de localhost; no se activa en
XAMPP HTTP. Retirar `unsafe-inline` requiere un refactor posterior con nonces
o hashes.

## Mantenimiento y QA

Estos comandos son verificadores o mantenimientos CLI y no deben publicarse
como rutas web:

```powershell
php scripts/check_project_document_storage.php
php scripts/check_support_material_storage.php
php scripts/audit_support_material_storage.php
php scripts/cleanup_preview_locks.php dry-run
php scripts/cleanup_preview_locks.php cleanup
php scripts/cleanup_expired_password_reset_tokens.php dry-run
php scripts/cleanup_expired_password_reset_tokens.php cleanup
php scripts/cleanup_expired_password_reset_tokens.php verify
php scripts/test_security_headers.php https://example.com/
```

El cleanup de tokens puede programarse mediante cron/cPanel. La prueba de
SMTP, LibreOffice, HTTPS real y permisos efectivos debe hacerse manualmente en
el servidor final; no se simula con datos de produccion.

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
