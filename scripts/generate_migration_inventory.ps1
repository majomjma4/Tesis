param(
    [string]$MigrationDirectory = (Join-Path $PSScriptRoot '..\database\migrations'),
    [string]$OutputPath = (Join-Path $PSScriptRoot '..\database\MIGRATIONS_INVENTORY.md')
)

$ErrorActionPreference = 'Stop'
$controlName = '20260901_create_schema_migrations.sql'
$files = @(Get-ChildItem -LiteralPath $MigrationDirectory -Filter '*.sql' -File |
    Where-Object { $_.Name -ne $controlName } |
    Sort-Object Name)

$sb = New-Object Text.StringBuilder
[void]$sb.AppendLine('# Inventario de migraciones')
[void]$sb.AppendLine('')
[void]$sb.AppendLine("Este inventario audita $($files.Count) archivos SQL historicos presentes al 2026-09-01. La migracion nueva '$controlName' se mantiene separada porque crea el mecanismo de control posterior al baseline.")
[void]$sb.AppendLine('')
[void]$sb.AppendLine('El estado de aplicacion de tesis no puede reconstruirse con fiabilidad: no se inventan registros historicos. El baseline estructural actual absorbe el estado de esquema, pero no presupone los cambios de datos de las migraciones. Las instalaciones nuevas no deben ejecutar migraciones historicas ni comodines sobre todos los archivos SQL.')
[void]$sb.AppendLine('')
[void]$sb.AppendLine('Criterios: UP/DOWN se determina por el sufijo _down.sql; Estructural no contiene DML destructivo reconocido; Mutacion de datos incluye INSERT, UPDATE o REPLACE; Destructiva incluye DROP, DELETE o TRUNCATE. Idempotencia solo se marca como parcial cuando el archivo contiene IF EXISTS o IF NOT EXISTS; no sustituye un runner con checksum.')
[void]$sb.AppendLine('')
[void]$sb.AppendLine('| Archivo | Fecha nominal | Nombre | Tipo | Operaciones detectadas | Riesgo | Idempotencia | Estado vs actual | Nueva instalacion | Accion |')
[void]$sb.AppendLine('|---|---:|---|---|---|---|---|---|---|---|')

foreach ($file in $files) {
    $sql = [IO.File]::ReadAllText($file.FullName)
    $match = [regex]::Match($file.Name, '^(\d{8})_(.+)\.sql$')
    $date = if ($match.Success) { $match.Groups[1].Value } else { 'sin fecha' }
    $name = if ($match.Success) { $match.Groups[2].Value } else { $file.BaseName }
    $type = if ($file.Name -match '(?i)_down\.sql$') { 'DOWN' } else { 'UP' }
    $patterns = [ordered]@{
        'CREATE TABLE' = '(?im)^\s*CREATE\s+TABLE'
        'ALTER TABLE' = '(?im)^\s*ALTER\s+TABLE'
        'CREATE INDEX' = '(?im)^\s*CREATE\s+(?:UNIQUE\s+)?INDEX'
        'ADD' = '(?im)^\s*ADD\s+'
        'MODIFY' = '(?im)^\s*MODIFY\s+'
        'DROP' = '(?im)^\s*DROP\s+'
        'INSERT' = '(?im)^\s*INSERT\s+'
        'UPDATE' = '(?im)^\s*UPDATE\s+'
        'DELETE' = '(?im)^\s*DELETE\s+'
        'TRUNCATE' = '(?im)^\s*TRUNCATE\s+'
    }
    $ops = @()
    foreach ($entry in $patterns.GetEnumerator()) {
        $count = ([regex]::Matches($sql, $entry.Value)).Count
        if ($count -gt 0) { $ops += "$($entry.Key)=$count" }
    }
    if ($ops.Count -eq 0) { $ops = @('sin operaciones reconocidas') }

    $hasDrop = $sql -match '(?im)^\s*(DROP|TRUNCATE)\b'
    $hasDelete = $sql -match '(?im)^\s*DELETE\b'
    $hasDml = $sql -match '(?im)^\s*(INSERT|UPDATE|REPLACE)\b'
    if ($type -eq 'DOWN') { $risk = 'Alto: reversa manual' }
    elseif ($hasDrop -or $hasDelete) { $risk = 'Alto: destructiva' }
    elseif ($hasDml) { $risk = 'Medio: muta datos' }
    elseif ($sql -notmatch '(?im)\bIF\s+(?:NOT\s+)?EXISTS\b') { $risk = 'Medio: no idempotencia verificada' }
    else { $risk = 'Bajo/medio: revisar contexto' }

    $idempotence = if ($sql -match '(?im)\bIF\s+(?:NOT\s+)?EXISTS\b') { 'Parcial: usa IF EXISTS' } else { 'No verificada' }
    $future = $date -gt '20260901'
    $state = 'Historial no verificable; estructura absorbida por baseline'
    if ($future) { $state += '; fecha nominal futura, resultado ya presente en esquema actual' }
    $newInstall = 'No: usar baseline; seed separado'
    $action = if ($type -eq 'DOWN') { 'Solo reversa manual autorizada' } else { 'No re-ejecutar tras baseline' }
    $cells = @($file.Name, $date, $name, $type, ($ops -join ', '), $risk, $idempotence, $state, $newInstall, $action)
    $escaped = $cells | ForEach-Object { ([string]$_).Replace('|', '\|') }
    [void]$sb.AppendLine('| ' + ($escaped -join ' | ') + ' |')
}

[void]$sb.AppendLine('')
[void]$sb.AppendLine('## Anomalia de fechas')
[void]$sb.AppendLine('')
[void]$sb.AppendLine('Los archivos con fecha nominal posterior a 2026-09-01 se conservan sin renombrar. El esquema real ya contiene sus resultados estructurales, pero no existe evidencia confiable de que cada archivo haya sido ejecutado en tesis; por ello se tratan como parte del estado absorbido por el baseline y no como pendientes automaticos.')
[void]$sb.AppendLine('')
[void]$sb.AppendLine('## Migracion de control posterior al baseline')
[void]$sb.AppendLine('')
[void]$sb.AppendLine('20260901_create_schema_migrations.sql crea schema_migrations con migration_id, applied_at y checksum_sha256. Debe ejecutarse solo como paso posterior al import del baseline en una base nueva o como cambio administrativo aprobado; no se ejecuto sobre tesis durante esta auditoria.')

$utf8NoBom = New-Object Text.UTF8Encoding($false)
[IO.File]::WriteAllText($OutputPath, $sb.ToString(), $utf8NoBom)
Write-Host "Inventario generado: $OutputPath ($($files.Count) migraciones historicas)"
