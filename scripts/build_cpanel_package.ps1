param(
    [string]$Destination = (Join-Path $PSScriptRoot '..\dist'),
    [switch]$InstallDependencies
)

$projectRoot = [System.IO.Path]::GetFullPath((Join-Path $PSScriptRoot '..'))
$resolvedDestination = [System.IO.Path]::GetFullPath($Destination)
$defaultDist = [System.IO.Path]::GetFullPath((Join-Path $projectRoot 'dist'))
$defaultDistPrefix = $defaultDist.TrimEnd('\', '/') + [System.IO.Path]::DirectorySeparatorChar

if ($resolvedDestination -ne $defaultDist -and -not $resolvedDestination.StartsWith($defaultDistPrefix, [System.StringComparison]::OrdinalIgnoreCase)) {
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

function Copy-RequiredFile {
    param(
        [Parameter(Mandatory = $true)][string]$RelativePath,
        [Parameter(Mandatory = $true)][string]$TargetRoot
    )

    $source = Join-Path $projectRoot ($RelativePath.Replace('/', '\'))
    if (-not (Test-Path -LiteralPath $source -PathType Leaf)) {
        throw "Falta un archivo requerido para el paquete: $RelativePath"
    }

    $target = Join-Path $TargetRoot ($RelativePath.Replace('/', '\'))
    New-Item -ItemType Directory -Path (Split-Path -Parent $target) -Force | Out-Null
    Copy-Item -LiteralPath $source -Destination $target -Force
}

function Copy-AllowlistedTree {
    param(
        [Parameter(Mandatory = $true)][string]$RelativePath,
        [Parameter(Mandatory = $false)][AllowEmptyCollection()][string[]]$ExcludedRelativePaths = @()
    )

    $sourceRoot = Join-Path $projectRoot ($RelativePath.Replace('/', '\'))
    if (-not (Test-Path -LiteralPath $sourceRoot -PathType Container)) {
        throw "Falta un directorio requerido para el paquete: $RelativePath"
    }

    $excluded = @{}
    foreach ($item in $ExcludedRelativePaths) {
        $excluded[$item.Replace('\', '/').TrimStart('/').ToLowerInvariant()] = $true
    }

    Get-ChildItem -LiteralPath $sourceRoot -Recurse -Force -File | Sort-Object FullName | ForEach-Object {
        $relativeChild = $_.FullName.Substring($sourceRoot.Length).TrimStart('\', '/').Replace('\', '/')
        $comparisonPath = ($RelativePath.TrimEnd('/', '\') + '/' + $relativeChild).ToLowerInvariant()
        if (-not $excluded.ContainsKey($comparisonPath) -and -not $excluded.ContainsKey($relativeChild.ToLowerInvariant())) {
            $target = Join-Path $packageRoot ($RelativePath.Replace('/', '\') + '\' + $relativeChild.Replace('/', '\'))
            New-Item -ItemType Directory -Path (Split-Path -Parent $target) -Force | Out-Null
            Copy-Item -LiteralPath $_.FullName -Destination $target -Force
        }
    }
}

function Find-Php {
    $command = Get-Command php -ErrorAction SilentlyContinue
    if ($null -ne $command) {
        return $command.Source
    }

    $xamppPhp = Join-Path $projectRoot '..\..\php\php.exe'
    if (Test-Path -LiteralPath $xamppPhp -PathType Leaf) {
        return [System.IO.Path]::GetFullPath($xamppPhp)
    }

    return $null
}

function Ensure-ComposerVendor {
    $autoload = Join-Path $projectRoot 'vendor\autoload.php'
    if ((Test-Path -LiteralPath $autoload -PathType Leaf) -and -not $InstallDependencies) {
        return
    }

    $php = Find-Php
    $composerCommand = Get-Command composer -ErrorAction SilentlyContinue
    $composerPhar = Join-Path $projectRoot 'composer.phar'

    if ($null -ne $composerCommand) {
        & $composerCommand.Source install --no-dev --prefer-dist --no-interaction --no-progress --classmap-authoritative --working-dir=$projectRoot
    } elseif ($null -ne $php -and (Test-Path -LiteralPath $composerPhar -PathType Leaf)) {
        & $php $composerPhar install --no-dev --prefer-dist --no-interaction --no-progress --classmap-authoritative --working-dir=$projectRoot
    } else {
        throw 'No existe vendor/autoload.php y no se encontró Composer/PHP para ejecutar composer install basado en composer.lock.'
    }

    if ($LASTEXITCODE -ne 0) {
        throw "composer install terminó con código $LASTEXITCODE. No se ejecutó composer update."
    }
}

Ensure-ComposerVendor

$vendorAutoload = Join-Path $projectRoot 'vendor\autoload.php'
$phpMailerClass = Join-Path $projectRoot 'vendor\phpmailer\phpmailer\src\PHPMailer.php'
$installedMetadata = Join-Path $projectRoot 'vendor\composer\installed.php'
if (-not (Test-Path -LiteralPath $vendorAutoload -PathType Leaf)) {
    throw 'vendor/autoload.php no está disponible para el paquete.'
}
if (-not (Test-Path -LiteralPath $phpMailerClass -PathType Leaf)) {
    throw 'PHPMailer no está disponible en vendor.'
}
$installedText = if (Test-Path -LiteralPath $installedMetadata -PathType Leaf) { Get-Content -LiteralPath $installedMetadata -Raw } else { '' }
if ($installedText -notmatch "'phpmailer/phpmailer'" -or $installedText -notmatch "'v7\.1\.1'") {
    throw 'vendor no contiene PHPMailer v7.1.1 según su metadata instalada.'
}

# Allowlist de archivos de aplicación. Los archivos locales con credenciales nunca se copian.
@('.htaccess', '.user.ini.example', '.env.example', 'index.php', 'composer.json', 'composer.lock') | ForEach-Object {
    Copy-RequiredFile -RelativePath $_ -TargetRoot $packageRoot
}
Copy-AllowlistedTree -RelativePath 'app' -ExcludedRelativePaths @('app/config/app.local.php', 'app/config/database.local.php')
Copy-AllowlistedTree -RelativePath 'public' -ExcludedRelativePaths @()
Copy-AllowlistedTree -RelativePath 'vendor' -ExcludedRelativePaths @()

# Solo se distribuye la protección del directorio de base de datos; no se distribuyen SQL ni dumps.
Copy-RequiredFile -RelativePath 'database/.htaccess' -TargetRoot $packageRoot

# Storage se inicializa vacío. Las rutas son las que usa el runtime actual.
@('storage/.htaccess', 'storage/private/.htaccess') | ForEach-Object {
    Copy-RequiredFile -RelativePath $_ -TargetRoot $packageRoot
}
$runtimeDirectories = @(
    'storage/calendar',
    'storage/repository',
    'storage/support-materials',
    'storage/private/avatars',
    'storage/private/project-drafts',
    'storage/private/project-packages',
    'storage/private/project-previews',
    'storage/private/project-publication-preparations',
    'storage/private/project-review-representations',
    'storage/private/projects'
)
foreach ($relativeDirectory in $runtimeDirectories) {
    $directory = Join-Path $packageRoot ($relativeDirectory.Replace('/', '\'))
    New-Item -ItemType Directory -Path $directory -Force | Out-Null
    [System.IO.File]::WriteAllText((Join-Path $directory '.gitkeep'), '')
}

# Verificaciones de seguridad antes de crear el ZIP.
$packageFiles = @(Get-ChildItem -LiteralPath $packageRoot -Recurse -Force -File)
$packageRelativePaths = @($packageFiles | ForEach-Object {
    $_.FullName.Substring($packageRoot.Length).TrimStart('\', '/').Replace('\', '/')
})
$forbiddenPatterns = @(
    '^\.git(?:/|$)',
    '^database/(?:recovery|backups?)(?:/|$)',
    '^storage/(?:backups?|qa-backups)(?:/|$)',
    '^storage/qa-fixtures(?:/|$)',
    '^storage/private/(?:projects|project-packages|project-previews|project-review-representations|project-drafts|project-publication-preparations|avatars)(?:/|$)(?!\.gitkeep$)',
    '^storage/repository/(?!\.gitkeep$)',
    '^storage/support-materials/(?!\.gitkeep$)',
    '(?:^|/)(?:evidence|recovery|qa)(?:/|$)',
    '(?:^|/)(?:backup|backups?)[^/]*(?:/|$)',
    '\.(?:bak|log|tmp)$'
)
$forbiddenFiles = @($packageRelativePaths | Where-Object {
    $candidate = $_
    @($forbiddenPatterns | Where-Object { $candidate -match $_ }).Count -gt 0
})
if ($forbiddenFiles.Count -gt 0) {
    throw "El paquete contiene archivos prohibidos: $($forbiddenFiles -join ', ')"
}
if ($packageRelativePaths -contains 'app/config/app.local.php' -or $packageRelativePaths -contains 'app/config/database.local.php') {
    throw 'El paquete contiene configuración local privada.'
}

Add-Type -AssemblyName System.IO.Compression
Add-Type -AssemblyName System.IO.Compression.FileSystem
$zipStream = [System.IO.File]::Open($zipPath, [System.IO.FileMode]::CreateNew)
$archive = [System.IO.Compression.ZipArchive]::new($zipStream, [System.IO.Compression.ZipArchiveMode]::Create)
try {
    $fixedTimestamp = [DateTimeOffset]::Parse('1980-01-01T00:00:00Z')
    $packageFiles | Sort-Object FullName | ForEach-Object {
        $relativePath = $_.FullName.Substring($packageRoot.Length).TrimStart('\', '/').Replace('\', '/')
        $entry = $archive.CreateEntry($relativePath, [System.IO.Compression.CompressionLevel]::Optimal)
        $entry.LastWriteTime = $fixedTimestamp
        $input = [System.IO.File]::OpenRead($_.FullName)
        $output = $entry.Open()
        try {
            $input.CopyTo($output)
        } finally {
            $output.Dispose()
            $input.Dispose()
        }
    }
} finally {
    $archive.Dispose()
    $zipStream.Dispose()
}

$packageBytes = ($packageFiles | Measure-Object -Property Length -Sum).Sum
Write-Output "Paquete generado: $zipPath"
Write-Output "Archivos: $($packageFiles.Count)"
Write-Output "Bytes sin comprimir: $packageBytes"
Write-Output 'Contenido: app, public, vendor, configuracion de ejemplo y storage runtime vacio.'
Write-Output 'Excluido: database SQL/dumps, datos reales de storage, recovery, backups, QA y credenciales locales.'
