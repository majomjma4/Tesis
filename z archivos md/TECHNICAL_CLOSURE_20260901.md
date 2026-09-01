# Cierre tecnico de auditoria

Fecha de corte: 2026-09-01.

Este documento describe el estado tecnico comprobado al cierre de los Bloques
1 a 5. Las secciones antiguas de `DEVELOPMENT_STATUS.md` conservan su valor
historico; cuando difieren de este cierre, prevalece este documento y el codigo
vigente.

## AUD-FINAL-012 - documentacion y operacion

- `database/snapshot.sql` es el baseline estructural actual de 54 tablas InnoDB.
  No contiene filas, credenciales, tokens ni datos academicos.
- La instalacion nueva usa una base vacia, el importador seguro y despues la
  migracion de control `20260901_create_schema_migrations.sql`. Las migraciones
  historicas se conservan para auditoria y no se ejecutan como comodin.
- El paquete cPanel se genera por allowlist, incluye `vendor` construido desde
  `composer.lock` y PHPMailer, y excluye SQL, recovery, backups, QA, fixtures,
  `storage` productivo, archivos privados y configuracion local.
- `app.local.php`, `database.local.php`, secretos SMTP y
  `APP_SETTINGS_ENCRYPTION_KEY` permanecen fuera de Git. La configuracion base
  usa produccion y autorecarga desactivada por defecto; los secretos quedan
  vacios hasta configurarse externamente.
- LibreOffice es una dependencia del servidor para previews privados DOCX;
  SMTP es opcional hasta completar sus variables y credenciales reales. La
  variable recomendada para la contraseña SMTP es `SMTP_PASSWORD`; se conserva
  `MAIL_PASSWORD` como compatibilidad de entorno anterior.
- `auth_required` no es una opcion operativa: el acceso se aplica en servidor
  mediante `RouteAccessService`.

## AUD-FINAL-015 - headers

`.htaccess` envia `X-Content-Type-Options`, `Referrer-Policy`, `X-Frame-Options`,
`Permissions-Policy` y una CSP compatible. La CSP permite los recursos que la
interfaz actual realmente usa: scripts/styles propios e inline, Font Awesome
desde cdnjs, fuentes del mismo CDN, imagenes `data:`/`blob:`, fetch/XHR del
mismo origen, frames propios y workers propios/blob.

HSTS se envia desde `index.php` solamente si el entorno es `production`, la
peticion es HTTPS y el host no es `localhost`, `127.0.0.1` ni `::1`. No se
activa en HTTP local. Un refactor futuro puede retirar `unsafe-inline` mediante
nonces o hashes, pero no forma parte de este cierre. La prueba repetible es
`scripts/test_security_headers.php`; valida los cinco headers y confirma que
HTTP local no recibe HSTS.

## AUD-FINAL-016 - tokens de recuperacion

La validez usa el TTL de configuracion, `expires_at` y `used_at`. El mecanismo
seguro es `scripts/cleanup_expired_password_reset_tokens.php`, con modos
`dry-run`, `cleanup` y `verify`; el modo de limpieza bloquea filas con
`FOR UPDATE` y solo elimina tokens que la misma logica actual considera
expirados y cuyo `used_at` sigue siendo `NULL`.

El cierre retiro un token expirado no usado y dejo cero candidatos. Los tokens
usados no se eliminaron. El script puede programarse en cron/cPanel; esa
programacion externa y la prueba SMTP real requieren QA del servidor final.

## AUD-FINAL-006 - archivos de apoyo no registrados

Los 37 archivos se mantienen como `F_UNKNOWN - CONSERVAR`: son artefactos
fisicos no referenciados, sin impacto funcional confirmado. Todos siguen el
layout actual, no coinciden por hash o nombre con registros, no tienen
referencias en codigo/fixtures/backups, no son publicos y no entran al paquete.
No se registraron ni eliminaron.

El inventario reproducible esta en
`database/recovery/audit_extraction_work_20260901_122000000/evidence/aud_final_006_support_inventory_20260901.json`.

## QA y comprobaciones

Los verificadores de storage son de solo lectura. La comprobacion actual
confirma 34 archivos de proyecto integros y 58 previews PDF validos. La
comprobacion de soporte informa los 37 `F_UNKNOWN` para impedir una limpieza
ciega; no implica que falten archivos registrados.

Antes de publicar en cPanel aun debe realizarse QA manual con HTTPS real,
SMTP real, LibreOffice instalado y permisos efectivos de `storage`. En local
HTTP, HSTS permanece ausente por diseño.
