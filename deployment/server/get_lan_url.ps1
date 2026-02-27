param(
    [string]$Path = "/ritel4/public/POS/login.php"
)

$ipv4 = Get-NetIPAddress -AddressFamily IPv4 |
    Where-Object {
        $_.IPAddress -notlike "169.254.*" -and
        $_.IPAddress -ne "127.0.0.1" -and
        $_.PrefixOrigin -ne "WellKnown"
    } |
    Sort-Object -Property InterfaceMetric |
    Select-Object -First 1 -ExpandProperty IPAddress

if (-not $ipv4) {
    Write-Host "IP LAN tidak ditemukan."
    exit 1
}

$url = "http://$ipv4$Path"
Write-Host "URL LAN POS: $url"
Write-Host "Healthcheck : http://$ipv4/ritel4/public/POS/health.php"
