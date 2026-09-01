param(
    [ValidatePattern('^[A-Za-z0-9_]+$')]
    [string]$Database = 'tesis',
    [string]$HostName = '127.0.0.1',
    [int]$Port = 3306,
    [string]$User = 'root',
    [PSCredential]$Credential,
    [string]$MariaDbBin = 'C:\xampp\mysql\bin'
)

$ErrorActionPreference = 'Stop'
$dumpTool = Join-Path $MariaDbBin 'mysqldump.exe'
$snapshot = Join-Path $PSScriptRoot '..\database\snapshot.sql'
$rawDump = Join-Path ([IO.Path]::GetTempPath()) ('tesis-structural-' + [guid]::NewGuid().ToString('N') + '.sql')

if (-not (Test-Path -LiteralPath $dumpTool -PathType Leaf)) {
    throw "No se encontro mysqldump.exe en: $dumpTool"
}

$hadMysqlPassword = Test-Path Env:MYSQL_PWD
$previousMysqlPassword = $env:MYSQL_PWD
try {
    if ($Credential) {
        $User = $Credential.UserName
        $env:MYSQL_PWD = $Credential.GetNetworkCredential().Password
    }

    & $dumpTool `
        '--protocol=tcp' `
        "--host=$HostName" `
        "--port=$Port" `
        "--user=$User" `
        '--default-character-set=utf8mb4' `
        '--no-data' `
        '--skip-add-drop-table' `
        '--skip-comments' `
        '--single-transaction' `
        '--triggers' `
        "--result-file=$rawDump" `
        $Database

    if ($LASTEXITCODE -ne 0) { throw "mysqldump termino con codigo $LASTEXITCODE" }

    $body = [IO.File]::ReadAllText($rawDump, [Text.Encoding]::UTF8)
    $body = [regex]::Replace($body, '(?im)\sAUTO_INCREMENT=\d+', '')
    $header = @(
        '-- Canonical structural baseline for a NEW installation on an empty database.',
        '-- Generated from the live schema metadata of the canonical application database.',
        '-- Schema only: no rows, users, passwords, hashes, emails, tokens, or academic data.',
        '-- This file does not create, select, drop, or replace any database.',
        '-- Existing database updates must use approved UP migrations recorded after this baseline.',
        ''
    ) -join "`n"
    $encoding = New-Object Text.UTF8Encoding($false)
    [IO.File]::WriteAllText((Resolve-Path $snapshot).Path, ($header + [Environment]::NewLine + $body.TrimStart()).TrimEnd() + [Environment]::NewLine, $encoding)
    Write-Host "Baseline estructural actualizado: $snapshot"
}
finally {
    if (Test-Path -LiteralPath $rawDump) {
        Remove-Item -LiteralPath $rawDump -Force
    }
    if ($hadMysqlPassword) {
        $env:MYSQL_PWD = $previousMysqlPassword
    } else {
        Remove-Item Env:MYSQL_PWD -ErrorAction SilentlyContinue
    }
}
