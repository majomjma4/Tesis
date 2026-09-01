# Baseline y despliegue de MariaDB

`database/snapshot.sql` es el baseline canonico estructural de la aplicacion.
Se genero desde el esquema real de `tesis` y contiene 54 tablas InnoDB, columnas,
claves, indices, claves foraneas y atributos de charset/collation. No contiene
filas, usuarios, contrasenas, hashes, correos, tokens ni datos academicos.

El baseline no crea, selecciona, elimina ni reemplaza ninguna base de datos.
Nunca se importa sobre la base activa `tesis`.

## Instalacion nueva

1. Crea una base nueva y vacia con el nombre elegido, por ejemplo
   `tesis_nueva`, usando `utf8mb4` y `utf8mb4_unicode_ci`.
2. Importa el baseline indicando siempre el destino:

   ```powershell
   powershell -ExecutionPolicy Bypass -File .\scripts\import_database.ps1 `
     -DatabaseName tesis_nueva
   ```

   El importador rechaza `tesis`, rechaza destinos inexistentes y aborta si el
   destino ya contiene tablas. Tampoco ejecuta migraciones `DOWN`, restauraciones
   ni instrucciones `DROP`.
3. Configura las credenciales fuera del repositorio, mediante variables de
   entorno o `app/config/database.local.php`.
4. Ejecuta solamente la migracion de control
   `20260901_create_schema_migrations.sql` en la base nueva y registra un
   marcador del baseline con el SHA-256 del archivo. La migracion de control no
   se ejecuta automaticamente sobre `tesis`.
5. Crea el primer administrador y verifica el acceso antes de publicar el sitio.

La autenticacion es obligatoria en todas las rutas no publicas. El interruptor
legacy `auth_required` se retiro del contrato oficial porque no controlaba el
acceso real. No se debe reintroducir para habilitar una instalacion.

## Estrategia de migraciones

El repositorio tenia 78 migraciones historicas y no existe un historial confiable
de cuales se ejecutaron en `tesis`. Por eso el baseline representa el estado
estructural actual sin inventar registros historicos. El inventario completo esta
en [MIGRATIONS_INVENTORY.md](MIGRATIONS_INVENTORY.md).

La estrategia oficial es:

- base nueva vacia -> baseline estructural -> migracion de control -> marcador
  `baseline:20260901`;
- una actualizacion consulta `schema_migrations`, calcula el SHA-256 de cada
  archivo y ejecuta solamente migraciones `UP` pendientes posteriores al
  baseline;
- cada migracion se registra solo despues de terminar correctamente;
- los archivos `_down.sql` son reversas manuales de administracion y nunca se
  incluyen en una ejecucion automatica;
- no se debe ejecutar un comodin sobre todos los `.sql` ni renombrar las
  migraciones con fecha nominal futura. Esas fechas y su anomalia quedan
  documentadas en el inventario.

`schema_migrations` se crea fuera del baseline actual porque no existe en el
esquema canonico de 54 tablas. Su contrato minimo es `migration_id`, `applied_at`
y `checksum_sha256`; un runner posterior debe validar que un archivo ya aplicado
no haya cambiado.

## Actualizacion del baseline

Para regenerar el baseline desde una base fuente autorizada y de solo lectura:

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\export_database.ps1 `
  -Database tesis
```

El exportador usa `--no-data`, no incluye `AUTO_INCREMENT` derivado de los datos,
y no genera `DROP DATABASE`, `DROP TABLE`, `USE` ni sentencias DML. Antes de
aceptar el archivo, ejecuta las validaciones del Bloque 2 y revisa el diff.

Las copias con datos de una instalacion existente son respaldos operativos y no
deben sustituir `database/snapshot.sql` ni entrar al paquete de cPanel.

## Configuracion segura

- Usa `.env.example` como referencia y mantén los valores reales en el entorno
  del servidor o en archivos locales ignorados por Git.
- El entorno por defecto es `production` y `DEV_AUTORELOAD` queda desactivado.
- `app/config/app.local.php` y `app/config/database.local.php` son privados y
  no deben eliminarse ni publicarse.
- `APP_SETTINGS_ENCRYPTION_KEY`, contrasenas SMTP y credenciales SQL nunca se
  escriben en el repositorio ni se imprimen durante las validaciones.

## Auditoria de la base activa

Las comprobaciones contra `tesis` deben limitarse a consultas de metadatos en
`information_schema` y a lecturas del esquema. No ejecutar en `tesis` `CREATE`,
`ALTER`, `INSERT`, `UPDATE`, `DELETE`, `TRUNCATE`, `DROP`, migraciones, imports ni
restauraciones. La validacion de importacion y comparacion se realiza en una base
temporal que se elimina al finalizar.
