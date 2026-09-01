# Preparacion para MariaDB

La aplicacion utiliza MariaDB, InnoDB, `utf8mb4`, PDO y consultas preparadas.
El baseline estructural vigente (`database/snapshot.sql`) contiene 54 tablas y
ninguna fila transportable.
La persistencia queda deshabilitada por defecto cuando no se configura una base,
pero la autenticacion de las rutas no publicas siempre se valida en el servidor.

## Orden seguro de activacion

1. Crear una base nueva y vacia con `utf8mb4_unicode_ci`.
2. Importar `database/snapshot.sql` con
   `scripts/import_database.ps1 -DatabaseName <base_nueva>`.
3. Ejecutar `20260901_create_schema_migrations.sql` como la migracion de control.
4. Registrar el marcador del baseline y el SHA-256 del archivo; no inventar que
   las 78 migraciones historicas fueron aplicadas en una base sin historial.
5. Configurar credenciales SQL y secretos en variables de entorno o archivos
   locales ignorados por Git.
6. Crear el primer administrador y ejecutar `php scripts/database_health.php`.
7. Probar una ruta publica y una ruta autenticada antes de publicar.

## Reglas de migracion

- El baseline es el estado estructural actual y no incluye filas ni datos
  institucionales.
- Un actualizador consulta `schema_migrations` y ejecuta solo archivos `UP`
  pendientes posteriores al baseline, validando su checksum.
- Cada archivo se registra despues de una ejecucion exitosa.
- Los cuatro archivos `_down.sql` son reversas manuales y no forman parte de la
  ejecucion automatica.
- Las fechas nominalmente futuras no se renombran en masa; la anomalia queda en
  `database/MIGRATIONS_INVENTORY.md`.
- Las migraciones historicas que insertan, actualizan o eliminan datos no se
  ejecutan durante una instalacion nueva. Los datos iniciales se provisionan por
  un proceso separado y controlado.

## Principios de persistencia

- Todas las tablas usan InnoDB y claves foraneas.
- Los textos usan `utf8mb4` y la aplicacion usa consultas preparadas.
- Las operaciones compuestas deben ejecutarse en transacciones.
- El codigo de proyecto se reserva con bloqueo `FOR UPDATE` dentro de la
  transaccion.
- Los archivos permanecen fuera de `public`; la base guarda metadatos y rutas
  relativas, nunca BLOB.
- Los permisos visibles nunca sustituyen la validacion del servidor.
- Estudiante, Docente y Administrador son los roles globales iniciales; tutor,
  lider y tribunal son funciones internas de un proyecto.

## Convivencia temporal

`DB_ENABLED=false` permite conservar el modo demostrativo cuando no hay una base
configurada. Los servicios PDO se utilizan solo al activar la conexion. La
configuracion de produccion no habilita autorecarga ni guarda secretos en el
repositorio.
