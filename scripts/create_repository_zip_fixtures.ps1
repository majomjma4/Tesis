$ErrorActionPreference = 'Stop'

$storagePath = Join-Path $PSScriptRoot '..\storage\repository'
$storagePath = [System.IO.Path]::GetFullPath($storagePath)
[System.IO.Directory]::CreateDirectory($storagePath) | Out-Null

$codigoFuente = 'C' + [char]0x00F3 + 'digo Fuente'
$carpetaVacia = 'Carpeta Vac' + [char]0x00ED + 'a'
$version = 'versi' + [char]0x00F3 + 'n'

$entries = [ordered]@{
    "$codigoFuente/" = $null
    "$codigoFuente/app/" = $null
    "$codigoFuente/app/Controllers/" = $null
    "$codigoFuente/app/Controllers/InventoryController.php" = "<?php`n`ndeclare(strict_types=1);`n`nfinal class InventoryController {}`n"
    "$codigoFuente/public/" = $null
    "$codigoFuente/public/app.js" = "console.log('Proyecto');`n"
    'Informe/' = $null
    'Informe/Informe_Final.txt' = "Informe academico de demostracion.`nContenido utilizado para probar el explorador ZIP.`n"
    "Informe/Anexo ($version-final).txt" = "Anexo con espacios, parentesis, tilde y guion.`n"
    'Base de Datos/' = $null
    'Base de Datos/esquema.sql' = "CREATE TABLE productos (id INT PRIMARY KEY, nombre VARCHAR(120));`n"
    "$carpetaVacia/" = $null
    'README.md' = "# Proyecto`n`nArchivo de demostracion para el repositorio institucional.`n"
    'config.json' = '{"name":"Proyecto","version":1}'
    'respaldo.zip' = 'nested-archive-placeholder'
}

Add-Type -AssemblyName System.IO.Compression
Add-Type -AssemblyName System.IO.Compression.FileSystem

1..5 | ForEach-Object {
    $zipPath = Join-Path $storagePath ("project_{0}.zip" -f $_)
    if (Test-Path -LiteralPath $zipPath) {
        Remove-Item -LiteralPath $zipPath -Force
    }

    $stream = [System.IO.File]::Open($zipPath, [System.IO.FileMode]::CreateNew)
    try {
        $archive = New-Object System.IO.Compression.ZipArchive($stream, [System.IO.Compression.ZipArchiveMode]::Create)
        try {
            foreach ($entryName in $entries.Keys) {
                $entry = $archive.CreateEntry($entryName)
                if ($null -eq $entries[$entryName]) {
                    continue
                }
                $writer = New-Object System.IO.StreamWriter($entry.Open(), [System.Text.UTF8Encoding]::new($false))
                try {
                    $writer.Write($entries[$entryName])
                } finally {
                    $writer.Dispose()
                }
            }
        } finally {
            $archive.Dispose()
        }
    } finally {
        $stream.Dispose()
    }
}

$emptyZipPath = Join-Path $storagePath 'empty.zip'
if (Test-Path -LiteralPath $emptyZipPath) { Remove-Item -LiteralPath $emptyZipPath -Force }
$emptyStream = [System.IO.File]::Open($emptyZipPath, [System.IO.FileMode]::CreateNew)
try {
    $emptyArchive = New-Object System.IO.Compression.ZipArchive($emptyStream, [System.IO.Compression.ZipArchiveMode]::Create)
    $emptyArchive.Dispose()
} finally {
    $emptyStream.Dispose()
}

[System.IO.File]::WriteAllText((Join-Path $storagePath 'damaged.zip'), 'not-a-valid-zip', [System.Text.UTF8Encoding]::new($false))

Write-Output "Fixtures ZIP creados en $storagePath"
