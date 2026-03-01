param(
    [string]$ServerUrl = "",
    [switch]$Fullscreen
)

$scriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path
$configPath = Join-Path $scriptDir "kiosk_config.ps1"
$launcherPath = Join-Path $scriptDir "start_pos_kiosk.ps1"

if (-not (Test-Path $launcherPath)) {
    Write-Host "[ERROR] Launcher tidak ditemukan: $launcherPath"
    exit 1
}

$paramUrl = $ServerUrl.Trim()
$cfgUrl = ""
$cfgFullscreen = $false
if (Test-Path $configPath) {
    . $configPath
    if ($null -ne $ServerUrl -and $ServerUrl -is [string]) { $cfgUrl = $ServerUrl.Trim() }
    if ($null -ne $Fullscreen) { $cfgFullscreen = [bool]$Fullscreen }
}

$url = $paramUrl
if ($url -eq "") { $url = $cfgUrl }

if ($url -eq "" -or -not ($url -match '^https?://')) {
    Write-Host "[ERROR] URL server belum benar."
    Write-Host "Edit file: $configPath"
    Write-Host "Isi contoh: `$ServerUrl = \"http://192.168.1.10/ritel4/public/POS/login.php\""
    exit 1
}

$isFull = $Fullscreen.IsPresent -or $cfgFullscreen
$healthUrl = $url -replace '/login\.php(\?.*)?$', '/health.php'
if ($healthUrl -eq $url) {
    if ($url.EndsWith('/')) { $healthUrl = "${url}health.php" } else { $healthUrl = "$url/health.php" }
}

try {
    $resp = Invoke-WebRequest -Uri $healthUrl -UseBasicParsing -TimeoutSec 4
    if ($resp.StatusCode -ne 200) { throw "HTTP $($resp.StatusCode)" }
} catch {
    Write-Host ""
    Write-Host "[SERVER TIDAK TERJANGKAU]"
    Write-Host "URL    : $url"
    Write-Host "Health : $healthUrl"
    Write-Host ""
    Write-Host "Cek:"
    Write-Host "1. PC server menyala + Apache/MySQL jalan"
    Write-Host "2. PC kasir & server satu jaringan LAN"
    Write-Host "3. Firewall server sudah allow port 80"
    Write-Host ""
    exit 2
}

if ($isFull) {
    & $launcherPath -ServerUrl $url -Fullscreen
} else {
    & $launcherPath -ServerUrl $url
}
exit $LASTEXITCODE
