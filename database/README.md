# Respaldo transportable de la base de datos

`snapshot.sql` contiene la estructura y los registros actuales de la base
`tesis`. Por eso, despues de un `git clone` solo hace falta iniciar MariaDB en
XAMPP y ejecutar desde la raiz del proyecto:

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\import_database.ps1
```

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

El snapshot puede contener correos y hashes de contrasenas. Debe guardarse en un
repositorio privado o generarse usando solamente datos de demostracion
anonimizados.

## Migraciones recientes

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
