param(
    [string]$Destination = (Join-Path $PSScriptRoot '..\dist')
)

$projectRoot = [System.IO.Path]::GetFullPath((Join-Path $PSScriptRoot '..'))
$resolvedDestination = [System.IO.Path]::GetFullPath($Destination)
$defaultDist = [System.IO.Path]::GetFullPath((Join-Path $projectRoot 'dist'))

if (-not $resolvedDestination.StartsWith($defaultDist, [System.StringComparison]::OrdinalIgnoreCase)) {
    throw 'El paquete solo puede generarse dentro del directorio dist del proyecto.'
}

$packageRoot = Join-Path $resolvedDestination 'cpanel'
$zipPath = Join-Path $resolvedDestination 'tesis-cpanel.zip'

if (Test-Path -LiteralPath $packageRoot) {
    Remove-Item -LiteralPath $packageRoot -Recurse -Force
}
if (Test-Path -LiteralPath $zipPath) {
    Remove-Item -LiteralPath $zipPath -Force
}

New-Item -ItemType Directory -Path $packageRoot -Force | Out-Null

$supportStorage = Join-Path $projectRoot 'storage\support-materials'
if (Test-Path -LiteralPath $supportStorage -PathType Container) {
    Write-Output 'Se incluirá storage/support-materials con su estructura y archivos actuales.'
} else {
    Write-Warning 'No existe storage/support-materials; el paquete no contendrá archivos de materiales de apoyo.'
}

$items = @('.htaccess', '.user.ini.example', 'index.php', 'app', 'public', 'database', 'storage')
foreach ($item in $items) {
    $source = Join-Path $projectRoot $item
    $target = Join-Path $packageRoot $item
    if (Test-Path -LiteralPath $source -PathType Container) {
        Copy-Item -LiteralPath $source -Destination $target -Recurse
    } elseif (Test-Path -LiteralPath $source) {
        Copy-Item -LiteralPath $source -Destination $target
    }
}

@('app.local.php', 'database.local.php') | ForEach-Object {
    $secretPath = Join-Path $packageRoot "app\config\$_"
    if (Test-Path -LiteralPath $secretPath) {
        Remove-Item -LiteralPath $secretPath -Force
    }
}
$localSnapshotBackup = Join-Path $packageRoot 'database\snapshot.before-portability.local.sql'
if (Test-Path -LiteralPath $localSnapshotBackup) {
    Remove-Item -LiteralPath $localSnapshotBackup -Force
}

Add-Type -AssemblyName System.IO.Compression
Add-Type -AssemblyName System.IO.Compression.FileSystem
$zipStream = [System.IO.File]::Open($zipPath, [System.IO.FileMode]::CreateNew)
$archive = [System.IO.Compression.ZipArchive]::new($zipStream, [System.IO.Compression.ZipArchiveMode]::Create)
try {
    Get-ChildItem -LiteralPath $packageRoot -Recurse -File | ForEach-Object {
        $relativePath = $_.FullName.Substring($packageRoot.Length).TrimStart('\', '/').Replace('\', '/')
        [System.IO.Compression.ZipFileExtensions]::CreateEntryFromFile(
            $archive,
            $_.FullName,
            $relativePath,
            [System.IO.Compression.CompressionLevel]::Optimal
        ) | Out-Null
    }
} finally {
    $archive.Dispose()
    $zipStream.Dispose()
}
Write-Output "Paquete generado: $zipPath"
