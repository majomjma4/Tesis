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
