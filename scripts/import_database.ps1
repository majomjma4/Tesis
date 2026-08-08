param(
    [string]$HostName = '127.0.0.1',
    [int]$Port = 3306,
    [string]$User = 'root',
    [PSCredential]$Credential,
    [string]$MariaDbBin = 'C:\xampp\mysql\bin'
)

$ErrorActionPreference = 'Stop'
$mysqlTool = Join-Path $MariaDbBin 'mysql.exe'
$snapshot = (Resolve-Path (Join-Path $PSScriptRoot '..\database\snapshot.sql')).Path.Replace('\', '/')

if (-not (Test-Path -LiteralPath $mysqlTool)) {
    throw "No se encontro mysql.exe en: $mysqlTool"
}

Write-Warning 'La importacion reemplazara por completo la base de datos tesis.'
try {
    if ($Credential) {
        $User = $Credential.UserName
        $env:MYSQL_PWD = $Credential.GetNetworkCredential().Password
    }
    & $mysqlTool `
        "--host=$HostName" `
        "--port=$Port" `
        "--user=$User" `
        '--default-character-set=utf8mb4' `
        '--binary-mode' `
        "--execute=SOURCE $snapshot"

    if ($LASTEXITCODE -ne 0) { throw "mysql termino con codigo $LASTEXITCODE" }
    Write-Host 'Base de datos tesis restaurada correctamente.'
}
finally {
    Remove-Item Env:MYSQL_PWD -ErrorAction SilentlyContinue
}
