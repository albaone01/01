param(
    [string]$XamppDir = "C:\xampp",
    [string]$AppPath = "/ritel4/public/POS/login.php",
    [int]$MaxWaitSeconds = 25
)

$apacheStart = Join-Path $XamppDir "apache_start.bat"
$mysqlStart  = Join-Path $XamppDir "mysql_start.bat"

if (-not (Test-Path $apacheStart)) {
    Write-Host "[ERROR] File tidak ditemukan: $apacheStart"
    exit 1
}
if (-not (Test-Path $mysqlStart)) {
    Write-Host "[ERROR] File tidak ditemukan: $mysqlStart"
    exit 1
}

Write-Host "Menjalankan Apache..."
& $apacheStart
Write-Host "Menjalankan MySQL..."
& $mysqlStart

$healthUrl = "http://127.0.0.1/ritel4/public/POS/health.php"
$ok = $false
$deadline = (Get-Date).AddSeconds($MaxWaitSeconds)

while ((Get-Date) -lt $deadline) {
    try {
        $resp = Invoke-WebRequest -Uri $healthUrl -UseBasicParsing -TimeoutSec 3
        if ($resp.StatusCode -eq 200) {
            $ok = $true
            break
        }
    } catch {
        Start-Sleep -Milliseconds 900
    }
}

if (-not $ok) {
    Write-Host "[WARN] Server belum merespons healthcheck: $healthUrl"
    Write-Host "Coba cek Apache/MySQL di XAMPP Control Panel."
} else {
    Write-Host "[OK] Healthcheck lokal berhasil: $healthUrl"
}

$ipv4 = Get-NetIPAddress -AddressFamily IPv4 |
    Where-Object {
        $_.IPAddress -notlike "169.254.*" -and
        $_.IPAddress -ne "127.0.0.1" -and
        $_.PrefixOrigin -ne "WellKnown"
    } |
    Sort-Object -Property InterfaceMetric |
    Select-Object -First 1 -ExpandProperty IPAddress

if ($ipv4) {
    $url = "http://$ipv4$AppPath"
    Write-Host ""
    Write-Host "URL POS LAN : $url"
    Write-Host "Health LAN  : http://$ipv4/ritel4/public/POS/health.php"
    Write-Host ""
    Write-Host "Gunakan URL ini di script kasir one-click."
} else {
    Write-Host "[WARN] IP LAN tidak ditemukan. Cek koneksi jaringan."
}

exit 0
