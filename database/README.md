# Respaldo transportable de la base de datos

La migración `20260817_project_document_management.sql` amplía de forma idempotente el contrato documental de proyectos con orden, retiro/restauración durante 24 horas, purga trazable y versiones conservadas por reemplazo. `projects.presentation_file_id` permanece como fuente única del archivo de presentación.

`snapshot.sql` contiene la estructura y los registros actuales de la base
`tesis`. Por eso, despues de un `git clone` solo hace falta iniciar MariaDB en
XAMPP y ejecutar desde la raiz del proyecto:

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\import_database.ps1
```

En Windows no debe canalizarse un dump con `Get-Content ... | mysql.exe`:
PowerShell puede decodificar el archivo como texto y volver a codificar su
salida con una página de códigos distinta, sustituyendo cada byte UTF-8 no
representable por `?`. El script oficial usa `SOURCE`, `utf8mb4` y modo binario
para que MariaDB lea directamente los bytes del snapshot.

La importacion elimina y recrea la base `tesis`. Para usar otras credenciales
sin exponer la contrasena como texto plano:

```powershell
$credencial = Get-Credential -UserName usuario
.\scripts\import_database.ps1 -Credential $credencial
```

Luego se debe copiar `app/config/database.local.php.example` como
`app/config/database.local.php` y completar la configuracion local.

## Actualizar el respaldo

Cada vez que deban conservarse nuevos registros en Git:

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\export_database.ps1
git add database/snapshot.sql
```

La exportación oficial usa `--result-file` y nunca pasa el SQL por la consola de
PowerShell, evitando la misma recodificación durante la generación del dump.

El snapshot puede contener correos y hashes de contrasenas. Debe guardarse en un
repositorio privado o generarse usando solamente datos de demostracion
anonimizados.

## Migraciones recientes

- `20260824_project_document_archives.sql`: añade estados físicos, retención, legal hold y manifiestos verificables sin mover ni eliminar binarios históricos.
- `20260822_project_file_version_changes.sql`: registra resúmenes estructurados de reemplazos documentales y su relación íntegra con observaciones atendidas.
- `20260820_project_adjustment_requests.sql`: incorpora solicitudes administrativas de ajuste, respuestas independientes y notificaciones consolidadas de tipo `adjustment`, sin mezclarlas con observaciones académicas.
- `20260818_project_delivery_corrections_result.sql`: reemplaza el resultado heredado `changes_required` de las entregas por `corrections_requested`, normaliza proyectos heredados a `development` sin alterar su actividad académica y conserva un respaldo técnico para la reversión controlada.
- `20260803_persist_support_materials.sql` traslada el material de apoyo y sus
  archivos a MariaDB. Permite editar metadatos, conservar descargas, gestionar
  documentos y retirar o restaurar publicaciones sin perder registros.
- `20260802_teacher_admin_privileges.sql` separa el perfil académico del
  privilegio administrativo. Solo un docente puede recibir acceso
  administrativo y el último administrador activo queda protegido.
- `20260728_single_career_defaults.sql` conserva como única carrera activa
  “Desarrollo de Software”. Los formularios usan además el periodo marcado como
  activo; el cambio de periodo se realiza desde Gestión académica.
- `20260729_normalize_demo_cedulas.sql` reemplaza los códigos alfanuméricos de
  estudiantes y docentes de demostración por cédulas ficticias únicas de diez
  dígitos.
- `20260726_standardize_project_codes.sql` normaliza los códigos existentes con
  prefijo por tipo, año y secuencia.
- `20260727_project_code_settings.sql` registra los prefijos y la cantidad de
  dígitos configurables para códigos futuros.

Los contadores de `project_code_sequences` forman parte del snapshot. No deben
reiniciarse al borrar proyectos, porque los códigos históricos nunca se
reutilizan.

## Auditoría administrativa

Las ediciones de proyectos se registran en `project_audit_log`. Cada entrada
conserva el identificador del administrador autenticado, fecha, hora y solamente
los campos que cambiaron. Los valores relacionados se guardan con etiquetas
legibles para su presentación posterior en historiales y reportes.
## Acceso administrativo inicial

Después de importar una base limpia y ejecutar las migraciones, crea la única cuenta administrativa temporal con:

```powershell
C:\xampp\php\php.exe scripts\create_initial_admin.php
```

El script no crea otra cuenta si ya existe un administrador activo. La contraseña se genera aleatoriamente, se muestra una sola vez y se almacena únicamente mediante `password_hash`. Para definir credenciales institucionales durante una instalación automatizada pueden usarse `INITIAL_ADMIN_EMAIL` e `INITIAL_ADMIN_PASSWORD`.

Para actualizar una instalación existente debe aplicarse
`database/migrations/20260802_teacher_admin_privileges.sql`. Para transportar el
estado completo actual basta con importar `database/snapshot.sql`, que ya
contiene esa estructura.

## Traslado entre computadoras

`git clone` y `git pull` no descargan los archivos incorporados por los usuarios.
La regla `/storage/support-materials/*` los mantiene fuera del repositorio; solo
se conserva `.gitkeep` para crear la carpeta base.

Para trasladar una instalación existente deben respaldarse y restaurarse como
una sola unidad:

1. Una exportación actual de la base de datos.
2. La carpeta completa `storage/support-materials/`.

Debe conservarse exactamente la estructura interna, incluidos los directorios
por material y los nombres físicos almacenados. Importar solamente la base puede
dejar registros sin archivos descargables. Copiar solamente `storage` puede
dejar archivos sin registros asociados.

Los archivos `app/config/app.local.php` y
`app/config/database.local.php` deben crearse y configurarse separadamente en
el equipo de destino a partir de sus archivos `.example`. Contienen
configuración local y no deben añadirse al repositorio.

Antes de habilitar acceso externo:

- confirma permisos de escritura sobre `storage/support-materials`;
- verifica `fileinfo` y `ZipArchive` o `PharData` con `php -m`;
- configura `auth_required => true`;
- importa `database/snapshot.sql`.

El script `scripts/build_cpanel_package.ps1` incluye la carpeta `storage` con su
estructura y archivos existentes, pero elimina `app.local.php` y
`database.local.php` del paquete para no distribuir credenciales. Si se usa para
una migración, revisa el mensaje emitido por el script para confirmar que
`storage/support-materials` estaba presente al construirlo.

La conciliación manual y no destructiva puede ejecutarse desde la raíz:

```powershell
C:\xampp\php\php.exe scripts\check_support_material_storage.php
```

El comando informa registros sin archivo y archivos sin registro. No elimina,
mueve ni modifica archivos o datos.
# Migraciones recientes

- `migrations/20260816_project_keywords.sql`: crea el catálogo UTF-8 de palabras clave de proyectos y su relación muchos a muchos, sin insertar etiquetas ni modificar proyectos existentes.
- `migrations/20260815_academic_period_transitions.sql`: registra cada cierre/promoción de PAO y permite revertir de forma auditada la transición más reciente durante 24 horas cuando el nuevo período no tiene actividad académica.
- `migrations/20260814_academic_catalogs.sql`: centraliza en `system_settings` los tipos y palabras clave que utiliza Materiales de apoyo, sin crear entidades paralelas.
- `migrations/20260813_project_repository_availability.sql`: separa la disponibilidad temporal de un proyecto publicado de su retiro del Repositorio.
- `migrations/20260812_support_material_version_integrity.sql`: fija la numeración histórica por archivo lógico y agrega huellas SHA-256 para verificar archivos vigentes y versiones.
- `migrations/20260810_support_material_admin_actions.sql`: agrega disponibilidad, primera publicación y baja lógica recuperable para materiales de apoyo.
- `migrations/20260729_support_material_audit_reads.sql`: conserva por administrador y material el último evento del historial consultado.
- `migrations/20260729_support_material_publication_date_readonly.sql`: permite que los borradores no tengan fecha y reserva su asignación para la publicación real.
