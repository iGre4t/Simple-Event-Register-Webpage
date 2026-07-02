param(
    [string]$OutputDirectory = ""
)

$ErrorActionPreference = "Stop"
$projectRoot = [System.IO.Path]::GetFullPath((Join-Path $PSScriptRoot ".."))
if ($OutputDirectory -eq "") {
    $OutputDirectory = Join-Path $projectRoot "backups"
}
$backupRoot = [System.IO.Path]::GetFullPath($OutputDirectory)
[System.IO.Directory]::CreateDirectory($backupRoot) | Out-Null

$stamp = Get-Date -Format "yyyyMMdd-HHmmss"
$stage = [System.IO.Path]::GetFullPath((Join-Path $backupRoot "stage-$stamp"))
if (-not $stage.StartsWith($backupRoot + [System.IO.Path]::DirectorySeparatorChar, [System.StringComparison]::OrdinalIgnoreCase)) {
    throw "Unsafe staging path: $stage"
}
[System.IO.Directory]::CreateDirectory($stage) | Out-Null

function Read-EnvironmentFile([string]$path) {
    $values = @{}
    if (-not (Test-Path -LiteralPath $path)) {
        throw "Missing environment file: $path"
    }
    foreach ($line in Get-Content -LiteralPath $path) {
        $trimmed = $line.Trim()
        if ($trimmed -eq "" -or $trimmed.StartsWith("#") -or -not $trimmed.Contains("=")) {
            continue
        }
        $parts = $trimmed.Split("=", 2)
        $values[$parts[0].Trim()] = $parts[1].Trim()
    }
    return $values
}

try {
    $envValues = Read-EnvironmentFile (Join-Path $projectRoot ".env.local")
    $dbHost = if ($envValues.DB_HOST) { $envValues.DB_HOST } else { "127.0.0.1" }
    $dbPort = if ($envValues.DB_PORT) { $envValues.DB_PORT } else { "3306" }
    $dbName = if ($envValues.DB_NAME) { $envValues.DB_NAME } else { "simple_event_register" }
    $dbUser = if ($envValues.DB_USER) { $envValues.DB_USER } else { "root" }
    $dbPassword = if ($null -ne $envValues.DB_PASSWORD) { $envValues.DB_PASSWORD } else { "" }

    $dumpCandidates = @()
    if ($env:XAMPP_HOME) {
        $dumpCandidates += Join-Path $env:XAMPP_HOME "mysql\bin\mysqldump.exe"
    }
    $dumpCandidates += "C:\xampp\mysql\bin\mysqldump.exe"
    $dumpCandidates += "mysqldump"
    $dumpCandidates = $dumpCandidates | Where-Object {
        $_ -and ($_ -eq "mysqldump" -or (Test-Path -LiteralPath $_))
    }
    $mysqldump = $dumpCandidates | Select-Object -First 1
    if (-not $mysqldump) {
        throw "mysqldump was not found."
    }

    $databaseDump = Join-Path $stage "database.sql"
    $dumpArgs = @(
        "--host=$dbHost",
        "--port=$dbPort",
        "--user=$dbUser",
        "--single-transaction",
        "--routines",
        "--triggers",
        "--events",
        "--default-character-set=utf8mb4",
        "--result-file=$databaseDump",
        $dbName
    )
    $previousPassword = $env:MYSQL_PWD
    $env:MYSQL_PWD = $dbPassword
    try {
        & $mysqldump @dumpArgs
        if ($LASTEXITCODE -ne 0) {
            throw "mysqldump failed with exit code $LASTEXITCODE."
        }
    } finally {
        $env:MYSQL_PWD = $previousPassword
    }

    $filesStage = Join-Path $stage "project"
    [System.IO.Directory]::CreateDirectory($filesStage) | Out-Null
    $excludedTopLevel = @(".git", "backups")
    Get-ChildItem -LiteralPath $projectRoot -Force | ForEach-Object {
        if ($excludedTopLevel -contains $_.Name) {
            return
        }
        Copy-Item -LiteralPath $_.FullName -Destination $filesStage -Recurse -Force
    }

    $manifest = [ordered]@{
        created_at = (Get-Date).ToString("o")
        database = $dbName
        includes_database_dump = $true
        includes_project_files = $true
        includes_env_local = (Test-Path -LiteralPath (Join-Path $filesStage ".env.local"))
        includes_legacy_storage = (Test-Path -LiteralPath (Join-Path $filesStage "storage"))
        warning = "Contains plaintext credentials and personal/payment data; encrypt and restrict access."
    }
    $manifest | ConvertTo-Json | Set-Content -LiteralPath (Join-Path $stage "manifest.json") -Encoding UTF8

    $archive = Join-Path $backupRoot "simple-event-register-full-$stamp.zip"
    Compress-Archive -Path (Join-Path $stage "*") -DestinationPath $archive -CompressionLevel Optimal
    $hash = (Get-FileHash -LiteralPath $archive -Algorithm SHA256).Hash
    Set-Content -LiteralPath ($archive + ".sha256") -Value "$hash  $([System.IO.Path]::GetFileName($archive))" -Encoding ASCII
    Write-Output $archive
} finally {
    if (Test-Path -LiteralPath $stage) {
        $resolvedStage = [System.IO.Path]::GetFullPath($stage)
        if ($resolvedStage.StartsWith($backupRoot + [System.IO.Path]::DirectorySeparatorChar, [System.StringComparison]::OrdinalIgnoreCase)) {
            Remove-Item -LiteralPath $resolvedStage -Recurse -Force
        }
    }
}
