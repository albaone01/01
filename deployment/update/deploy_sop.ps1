param(
    [string]$BaseUrl = "http://127.0.0.1/ritel4",
    [switch]$SkipBackup
)

$ErrorActionPreference = "Stop"
$scriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path

Write-Host "=========================================="
Write-Host " DEPLOY SOP - RITEL4 (PowerShell)"
Write-Host " BASE_URL   : $BaseUrl"
Write-Host " BACKUP     : " -NoNewline
if ($SkipBackup) { Write-Host "SKIPPED" } else { Write-Host "ENABLED" }
Write-Host "=========================================="

if (-not $SkipBackup) {
    & "$scriptDir\backup_db.bat"
    if ($LASTEXITCODE -ne 0) {
        throw "Tahap backup gagal."
    }
}

& "$scriptDir\restart_services.bat"
if ($LASTEXITCODE -ne 0) {
    throw "Tahap restart service gagal."
}

& powershell -ExecutionPolicy Bypass -File "$scriptDir\smoke_test.ps1" -BaseUrl $BaseUrl
if ($LASTEXITCODE -ne 0) {
    throw "Smoke test gagal."
}

Write-Host "[OK] DEPLOY SOP selesai."

