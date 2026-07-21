param(
    [string]$Database = 'tesis',
    [string]$HostName = '127.0.0.1',
    [int]$Port = 3306,
    [string]$User = 'root',
    [string]$Password = '',
    [string]$MariaDbBin = 'C:\xampp\mysql\bin'
)

$ErrorActionPreference = 'Stop'
$dumpTool = Join-Path $MariaDbBin 'mysqldump.exe'
$snapshot = Join-Path $PSScriptRoot '..\database\snapshot.sql'

if (-not (Test-Path -LiteralPath $dumpTool)) {
    throw "No se encontro mysqldump en: $dumpTool"
}

try {
    if ($Password) { $env:MYSQL_PWD = $Password }
    & $dumpTool `
        "--host=$HostName" `
        "--port=$Port" `
        "--user=$User" `
        '--default-character-set=utf8mb4' `
        '--single-transaction' `
        '--routines' `
        '--events' `
        '--triggers' `
        '--add-drop-database' `
        '--databases' $Database `
        "--result-file=$snapshot"

    if ($LASTEXITCODE -ne 0) { throw "mysqldump termino con codigo $LASTEXITCODE" }
    Write-Host "Snapshot actualizado: $snapshot"
}
finally {
    Remove-Item Env:MYSQL_PWD -ErrorAction SilentlyContinue
}
