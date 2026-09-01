param(
    [Parameter(Mandatory = $true, Position = 0)]
    [ValidatePattern('^[A-Za-z0-9_]+$')]
    [string]$DatabaseName,
    [string]$HostName = '127.0.0.1',
    [int]$Port = 3306,
    [string]$User = 'root',
    [PSCredential]$Credential,
    [string]$MariaDbBin = 'C:\xampp\mysql\bin'
)

$ErrorActionPreference = 'Stop'
$mysqlTool = Join-Path $MariaDbBin 'mysql.exe'
$snapshot = (Resolve-Path (Join-Path $PSScriptRoot '..\database\snapshot.sql')).Path

if ($DatabaseName -ieq 'tesis') {
    throw "Por seguridad, este importador nunca puede escribir en la base activa 'tesis'. Usa el nombre de una base nueva y vacia."
}

if (-not (Test-Path -LiteralPath $mysqlTool -PathType Leaf)) {
    throw "No se encontro mysql.exe en: $mysqlTool"
}
if (-not (Test-Path -LiteralPath $snapshot -PathType Leaf)) {
    throw "No se encontro el snapshot estructural: $snapshot"
}

$snapshotText = Get-Content -LiteralPath $snapshot -Encoding UTF8 -Raw
if ($snapshotText -match '(?im)^\s*(CREATE\s+DATABASE|DROP\s+DATABASE|DROP\s+TABLE|USE)\b') {
    throw 'El snapshot contiene una instruccion de control de base de datos no permitida.'
}
if ($snapshotText -match '(?im)^\s*(INSERT|UPDATE|DELETE|REPLACE|TRUNCATE)\b') {
    throw 'El snapshot contiene datos o una operacion destructiva no permitida.'
}

function Invoke-MySqlScalar {
    param(
        [Parameter(Mandatory = $true)][string[]]$Arguments
    )

    $result = @(& $mysqlTool @Arguments 2>&1)
    if ($LASTEXITCODE -ne 0) {
        throw 'No fue posible consultar el servidor MariaDB.'
    }
    return (($result | Out-String).Trim())
}

$hadMysqlPassword = Test-Path Env:MYSQL_PWD
$previousMysqlPassword = $env:MYSQL_PWD
try {
    if ($Credential) {
        $User = $Credential.UserName
        $env:MYSQL_PWD = $Credential.GetNetworkCredential().Password
    }

    $connectionArguments = @(
        '--protocol=tcp',
        "--host=$HostName",
        "--port=$Port",
        "--user=$User",
        '--default-character-set=utf8mb4',
        '--binary-mode'
    )

    $databaseExists = Invoke-MySqlScalar -Arguments ($connectionArguments + @(
        '--batch',
        '--skip-column-names',
        "--execute=SELECT COUNT(*) FROM information_schema.schemata WHERE schema_name='$DatabaseName'"
    ))
    if ($databaseExists -ne '1') {
        throw "La base destino '$DatabaseName' no existe. Créala vacía antes de importar."
    }

    $tableCount = Invoke-MySqlScalar -Arguments ($connectionArguments + @(
        '--batch',
        '--skip-column-names',
        "--execute=SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='$DatabaseName'"
    ))
    $parsedTableCount = 0
    if (-not [int]::TryParse($tableCount, [ref]$parsedTableCount)) {
        throw 'No se pudo determinar si la base destino está vacía.'
    }
    if ($parsedTableCount -ne 0) {
        throw "Abortado: la base destino '$DatabaseName' ya contiene $parsedTableCount tabla(s). Este importador solo acepta una base nueva y vacía."
    }

    Write-Warning "Se importara unicamente el baseline estructural en la base nueva y vacia '$DatabaseName'. No se ejecutan DROP DATABASE, DROP TABLE ni migraciones DOWN."
    Get-Content -LiteralPath $snapshot -Encoding UTF8 | & $mysqlTool @($connectionArguments + "--database=$DatabaseName")
    if ($LASTEXITCODE -ne 0) {
        throw "La importacion del baseline fallo con codigo $LASTEXITCODE."
    }

    Write-Host "Baseline estructural importado correctamente en '$DatabaseName'."
}
finally {
    if ($hadMysqlPassword) {
        $env:MYSQL_PWD = $previousMysqlPassword
    } else {
        Remove-Item Env:MYSQL_PWD -ErrorAction SilentlyContinue
    }
}
